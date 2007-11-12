<?php

/*
 *
 * Copyright 2006, Alex Lance, Clancy Malcolm, Cybersource Pty. Ltd.
 *
 * This file is part of allocPSA <info@cyber.com.au>.
 *
 * allocPSA is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * allocPSA is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * allocPSA; if not, write to the Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 */

class invoice extends db_entity {
  var $data_table = "invoice";
  var $display_field_name = "invoiceName";

  function invoice() {
    $this->db_entity();
    $this->key_field = new db_field("invoiceID");
    $this->data_fields = array("invoiceName"=>new db_field("invoiceName")
                              ,"clientID"=>new db_field("clientID")
                              ,"invoiceDateFrom"=>new db_field("invoiceDateFrom")
                              ,"invoiceDateTo"=>new db_field("invoiceDateTo")
                              ,"invoiceNum"=>new db_field("invoiceNum")
                              ,"invoiceName"=>new db_field("invoiceName")
                              ,"invoiceStatus"=>new db_field("invoiceStatus")
      );
  }

  function get_invoice_statii() {
    return array("create"=>"Create"
                ,"edit"=>"Add Items"
                ,"generate"=>"Generate Invoice"
                ,"reconcile"=>"Approve/Reject"
                ,"finished"=>"Completed");

  }

  function get_invoice_statii_payment() {
    return array("pending"=>"Pending"
                ,"partly_paid"=>"Partly Paid"
                ,"rejected"=>"Not Going To Be Fully Paid"
                ,"fully_paid"=>"Fully Paid"
                );

  }

  function is_owner($person = "") {
    global $current_user;

    if ($person == "") {
      $person = $current_user;
    }

    $db = new db_alloc();
    $db->query("SELECT * FROM invoiceItem WHERE invoiceID=".$this->get_id());
    while ($db->next_record()) {
      $invoice_item = new invoiceItem();
      if ($invoice_item->read_db_record($db, false)) {
        if ($invoice_item->is_owner($person)) {
          return true;
        }
      }
    }
    return false;
  }

  function get_invoiceItems($invoiceID="") {
    $invoiceItemIDs = array();
    $id = $invoiceID or $id = $this->get_id();
    $q = sprintf("SELECT invoiceItemID FROM invoiceItem WHERE invoiceID = %d",$id);
    $db = new db_alloc();
    $db->query($q);
    while ($row = $db->row()) {
      $invoiceItemIDs[] = $row["invoiceItemID"];
    }
    return $invoiceItemIDs;
  }

  function get_transactions($invoiceID="") {
    $transactionIDs = array();
    $id = $invoiceID or $id = $this->get_id();
    $q = sprintf("SELECT transactionID FROM transaction WHERE invoiceID = %d",$id);
    $db = new db_alloc();
    $db->query($q);
    while ($row = $db->row()) {
      $transactionIDs[] = $row["transactionID"];
    }
    return $transactionIDs;
  }

  function get_next_invoiceNum() {
    $q = "SELECT coalesce(max(invoiceNum)+1,1) as newNum FROM invoice";
    $db = new db_alloc();
    $db->query($q);
    $db->row();
    return $db->f("newNum");
  }

  function get_invoiceItem_list_for_file() {
    $q = sprintf("SELECT * from invoiceItem WHERE invoiceID=%d ", $this->get_id());
    $q.= sprintf("ORDER BY iiDate,invoiceItemID");
    $db = new db_alloc;
    $db->query($q);
    $taxPercent = config::get_config_item("taxPercent");
    $taxPercentDivisor = ($taxPercent/100) + 1;
    $currency = '$';

    while ($db->next_record()) {
      $invoiceItem = new invoiceItem;
      $invoiceItem->read_db_record($db);


      $num = sprintf("%0.2f",$invoiceItem->get_value("iiAmount"));

      if ($taxPercent !== '') {
        $num_minus_gst = sprintf("%0.2f",$num / $taxPercentDivisor);
        $gst = sprintf("%0.2f",$num - $num_minus_gst);

        if (($num_minus_gst + $gst) != $num) {
          $num_minus_gst += $num - ($num_minus_gst + $gst); // round it up.
        }

        $rows[$invoiceItem->get_id()]["quantity"] = $invoiceItem->get_value("iiQuantity");
        $rows[$invoiceItem->get_id()]["unit"] = sprintf("%0.2f",$invoiceItem->get_value("iiUnitPrice"));
        $rows[$invoiceItem->get_id()]["money"]+= $num_minus_gst;
        $rows[$invoiceItem->get_id()]["gst"] += $gst;
        $info["total_gst"] += $gst;
        $info["total"] += $num_minus_gst;
      } else {
        $rows[$invoiceItem->get_id()]["quantity"] = $invoiceItem->get_value("iiQuantity");
        $rows[$invoiceItem->get_id()]["unit"] = sprintf("%0.2f",$invoiceItem->get_value("iiUnitPrice"));
        $rows[$invoiceItem->get_id()]["money"] += $num;
        $info["total"] += $num;
      }

      unset($str);
      $d = $invoiceItem->get_value('iiMemo');
      $str[] = stripslashes($d);

      // Get task description
      if ($invoiceItem->get_value("timeSheetID") && $_GET["printDesc"]) {
        $q = sprintf("SELECT * FROM timeSheetItem WHERE timeSheetID = %d",$invoiceItem->get_value("timeSheetID"));
        $db = new db_alloc();
        $db->query($q);
        while ($db->next_record()) {
          $str[$invoiceItem->get_id()].= stripslashes($db->f("description"));
        }
      }
      is_array($str) and $rows[$invoiceItem->get_id()]["desc"].= trim(implode(DEFAULT_SEP,$str));
    }
    $info["total_inc_gst"] = sprintf("$%0.2f",$info["total"]+$info["total_gst"]);

    // If we are in dollar mode, then prefix the total with a dollar sign
    $info["total"] = $currency.sprintf("%0.2f",$info["total"]);
    $info["total_gst"] = $currency.sprintf("%0.2f",$info["total_gst"]);
    $rows or $rows = array();
    $info or $info = array();
    return array($rows,$info);
  }

  function generate_invoice_file() {
    // Build PDF document
    require_once("../lib/class.ezpdf.php");
    $font1 = ALLOC_MOD_DIR."util/fonts/Helvetica.afm";
    $font2 = ALLOC_MOD_DIR."util/fonts/Helvetica-Oblique.afm";

    $db = new db_alloc;

    // Get client name
    $client = $this->get_foreign_object("client");
    $clientName = $client->get_value("clientName");

    // Get cyber info
    $companyName = config::get_config_item("companyName");
    $companyNos1 = config::get_config_item("companyACN");
    $companyNos2 = config::get_config_item("companyABN");
    $phone = config::get_config_item("companyContactPhone");
    $fax = config::get_config_item("companyContactFax");
    $phone and $phone = "Ph: ".$phone;
    $fax and $fax = "Fax: ".$fax;
    $img = config::get_config_item("companyImage");
    $companyContactAddress = config::get_config_item("companyContactAddress");
    $companyContactAddress2 = config::get_config_item("companyContactAddress2");
    $companyContactAddress3 = config::get_config_item("companyContactAddress3");
    $email = config::get_config_item("companyContactEmail");
    $email and $companyContactEmail = "Email: ".$email;
    $web = config::get_config_item("companyContactHomePage");
    $web and $companyContactHomePage = "Web: ".$web;
    $footer = stripslashes(config::get_config_item("timeSheetPrintFooter"));
    $taxName = config::get_config_item("taxName");


    // Get billing period
    $q = sprintf("SELECT max(iiDate) as maxDate, min(iiDate) as minDate
                    FROM invoiceItem
                   WHERE invoiceItem.invoiceID = %d
                 ",$this->get_id());
    $db->query($q);
    $row = $db->row();
    $period = format_date(DATE_FORMAT,$row["minDate"])." to ".format_date(DATE_FORMAT,$row["maxDate"]);

    $default_header = "Tax Invoice";
    $default_id_label = "Invoice Number";


    $pdf_table_options = array("showLines"=>0,"shaded"=>0,"showHeadings"=>0,"xPos"=>"left","xOrientation"=>"right","fontSize"=>11,"rowGap"=>0,"fontSize"=>10);

    $cols = array("one"=>"","two"=>"","three"=>"","four"=>"");
    $cols3 = array("one"=>"","two"=>"");
    $cols_settings["one"] = array("justification"=>"right");
    $cols_settings["three"] = array("justification"=>"right");
    $pdf_table_options2 = array("showLines"=>0,"shaded"=>0,"showHeadings"=>0, "width"=>400, "fontSize"=>11, "xPos"=>"center", "xOrientation"=>"center", "cols"=>$cols_settings);
    $cols_settings2["gst"] = array("justification"=>"right");
    $cols_settings2["money"] = array("justification"=>"right");
    $cols_settings2["unit"] = array("justification"=>"right");
    $pdf_table_options3 = array("showLines"=>2,"shaded"=>0,"width"=>400, "xPos"=>"center","fontSize"=>11,"cols"=>$cols_settings2,"lineCol"=>array(0.8, 0.8, 0.8),"splitRows"=>1,"protectRows"=>0);
    $cols_settings["two"] = array("justification"=>"right","width"=>80);
    $pdf_table_options4 = array("showLines"=>2,"shaded"=>0,"width"=>400, "showHeadings"=>0, "fontSize"=>11, "xPos"=>"center", "cols"=>$cols_settings,"lineCol"=>array(0.8, 0.8, 0.8));

    $pdf =& new Cezpdf();
    $pdf->ezSetMargins(90,90,90,90);

    $pdf->selectFont($font1);
    $pdf->ezStartPageNumbers(436,80,11,'right','Page {PAGENUM} of {TOTALPAGENUM}');
    $pdf->ezStartPageNumbers(200,80,11,'left','<b>'.$default_id_label.': </b>'.$this->get_value("invoiceNum"));
    $pdf->ezSetY(775);

    $companyName            and $contact_info[] = array($companyName);
    $companyContactAddress  and $contact_info[] = array($companyContactAddress);
    $companyContactAddress2 and $contact_info[] = array($companyContactAddress2);
    $companyContactAddress3 and $contact_info[] = array($companyContactAddress3);
    $companyContactEmail    and $contact_info[] = array($companyContactEmail);
    $companyContactHomePage and $contact_info[] = array($companyContactHomePage);
    $phone                  and $contact_info[] = array($phone);
    $fax                    and $contact_info[] = array($fax);

    $pdf->selectFont($font2);
    $y = $pdf->ezTable($contact_info,false,"",$pdf_table_options);
    $pdf->selectFont($font1);

    $line_y = $y-10;
    $pdf->setLineStyle(1,"round");
    $pdf->line(90,$line_y,510,$line_y);


    $pdf->ezSetY(782);
    $y = $pdf->ezText($companyName,27, array("justification"=>"right"));
    $nos_y = $line_y + 22;
    $companyNos2 and $nos_y = $line_y + 34;
    $pdf->ezSetY($nos_y);
    $companyNos1 and $y = $pdf->ezText($companyNos1,11, array("justification"=>"right"));
    $companyNos2 and $y = $pdf->ezText($companyNos2,11, array("justification"=>"right"));


    $pdf->ezSetY($line_y -20);
    $y = $pdf->ezText($default_header,20, array("justification"=>"center"));
    $pdf->ezSetY($y -20);

    $ts_info[] = array("one"=>"<b>".$default_id_label.":</b>","two"=>$this->get_value("invoiceNum"),"three"=>"<b>Date Issued:</b>","four"=>date("d/m/Y"));
    $ts_info[] = array("one"=>"<b>Client:</b>"        ,"two"=>$clientName,"three"=>"<b>Billing Period:</b>","four"=>$period);
    $y = $pdf->ezTable($ts_info,$cols,"",$pdf_table_options2);

    $pdf->ezSetY($y -20);

    list($rows,$info) = $this->get_invoiceItem_list_for_file();
    $cols2 = array("desc"=>"Description","quantity"=>"Qty","unit"=>"Unit Price","money"=>"Charges","gst"=>$taxName);
    $taxPercent = config::get_config_item("taxPercent");
    if ($taxPercent === '') unset($cols2["gst"]);
    $rows[] = array("desc"=>"<b>TOTAL</b>","money"=>$info["total"],"gst"=>$info["total_gst"]);
    $y = $pdf->ezTable($rows,$cols2,"",$pdf_table_options3);
    $pdf->ezSetY($y -20);
    if ($taxPercent !== '') $totals[] = array("one"=>"TOTAL ".$taxName,"two"=>$info["total_gst"]);
    $totals[] = array("one"=>"TOTAL CHARGES","two"=>$info["total"]);
    $totals[] = array("one"=>"<b>TOTAL AMOUNT PAYABLE</b>","two"=>"<b>".$info["total_inc_gst"]."</b>");
    $y = $pdf->ezTable($totals,$cols3,"",$pdf_table_options4);

    $pdf->ezSetY($y-20);
    #$pdf->ezText(str_replace(array("<br/>","<br>"),"\n",$footer,11);


    // Add footer
    #$all = $pdf->openObject();
    #$pdf->saveState();
    #$pdf->addText(415,80,12,"<b>".$default_id_label.":</b>".$this->get_value("invoiceNum"));
    #$pdf->restoreState();
    #$pdf->closeObject();
    #$pdf->addObject($all,'all');

    $dir = ATTACHMENTS_DIR."invoice".DIRECTORY_SEPARATOR.$this->get_id();
    if (!is_dir($dir)) {
      mkdir($dir);
    }

    $rows = get_attachments("invoice",$this->get_id());
    if ($rows) {
      $ar = end($rows);
      $file = $ar["text"];
    } else {
      $file = $this->get_value("invoiceNum")."-0.pdf";
    }

    $file = preg_replace("/-([0-9]+)\.pdf$/e","sprintf('-%d.pdf',\\1 + 1)",$file);



    //$debug = true;
    if (!$debug) {
      $fh = fopen($dir.DIRECTORY_SEPARATOR.$file,"w+");
      fputs($fh, $pdf->output());
      fclose($fh);
    } else {
      $pdf->ezStream();
    }


  }

  function has_attachment_permission($person) {
    return $person->have_role("admin");
  }

  function has_attachment_permission_delete($person) {
    return $person->have_role("admin");
  }

  function get_invoice_name() {
    return stripslashes($this->get_value("invoiceNum"));
  }

  function get_invoice_link() {
    global $TPL;
    return "<a href=\"".$TPL["url_alloc_invoice"]."invoiceID=".$this->get_id()."\">".$this->get_invoice_name()."</a>";
  }

  function get_invoice_list_filter($filter=array()) {
    global $current_user;
    $sql = array();
    $filter["invoiceID"]     and $sql[] = sprintf("(invoice.invoiceID = %d)",$filter["invoiceID"]);
    $filter["clientID"]      and $sql[] = sprintf("(invoice.clientID = %d)",$filter["clientID"]);
    $filter["invoiceNum"]    and $sql[] = sprintf("(invoice.invoiceNum = %d)",$filter["invoiceNum"]);
    $filter["dateOne"]       and $sql[] = sprintf("(invoice.invoiceDateFrom>='%s')",db_esc($filter["dateOne"]));
    $filter["dateTwo"]       and $sql[] = sprintf("(invoice.invoiceDateTo<='%s')",db_esc($filter["dateTwo"]));
    $filter["invoiceName"]   and $sql[] = sprintf("(invoice.invoiceName like '%%%s%%')",db_esc($filter["invoiceName"]));
    $filter["invoiceStatus"] and $sql[] = sprintf("(invoice.invoiceStatus = '%s')",db_esc($filter["invoiceStatus"]));
    return $sql;
  }

  function get_invoice_list_filter2($filter=array()) {

    // restrict non-admin users records
    if ($filter["personID"]) {
      $tfIDs = $current_user->get_tfIDs();
      if (is_array($tfIDs) && $tfIDs) {
        $filter["tfIDs"] = $tfIDs;
      } else {
        $filter["tfIDs"] = array(0);
      }
      $sql[] = sprintf("(transaction.tfID in (".implode(",",$filter["tfIDs"])."))");
      $sql[] = sprintf("(tfPerson.personID = %d)",$filter["personID"]);
    }

  
    // Filter for the HAVING clause
    $sql2 = array();
    if ($filter["invoiceStatusPayment"] == "pending") {
      $sql2[] = "(amountPaidPending <= iiAmountSum)";
    } else if ($filter["invoiceStatusPayment"] == "partly_paid") {
      $sql2[] = "(amountPaidApproved > 0 AND amountPaidApproved < iiAmountSum)";
    } else if ($filter["invoiceStatusPayment"] == "rejected") {
      $sql2[] = "(amountPaidRejected > 0 AND amountPaidRejected < iiAmountSum)";
    } else if ($filter["invoiceStatusPayment"] == "fully_paid") {
      $sql2[] = "(amountPaidApproved = iiAmountSum)";
    }

    return array($sql,$sql2);
  }

  function get_invoice_list($_FORM) {
    /*
     * This is the definitive method of getting a list of invoices that need a sophisticated level of filtering
     *
     * Display Options:
     *  showHeader
     *  showHeader
     *  showInvoiceNumber
     *  showInvoiceClient
     *  showInvoiceName
     *  showInvoiceAmount
     *  showInvoiceAmountPaid
     *  showInvoiceDate
     *  showInvoiceStatus
     *  showInvoiceStatusPayment
     *
     * Filter Options:
     *  invoiceID
     *  clientID
     *  invoiceNum
     *  dateOne
     *  dateTwo
     *  invoiceName
     *  invoiceStatus
     *  invoiceStatusPayment
     *  personID (sets tfIDs)
     *  return = html | dropdown_options
     *
     */

    $filter1_where = invoice::get_invoice_list_filter($_FORM);
    list($filter2_where,$filter2_having) = invoice::get_invoice_list_filter2($_FORM);

    $debug = $_FORM["debug"];
    $debug and print "<pre>_FORM: ".print_r($_FORM,1)."</pre>";
    $debug and print "<pre>filter1_where: ".print_r($filter1_where,1)."</pre>";
    $debug and print "<pre>filter2_where: ".print_r($filter2_where,1)."</pre>";
    $debug and print "<pre>filter2_having: ".print_r($filter2_having,1)."</pre>";

    $_FORM["return"] or $_FORM["return"] = "html";

    // A header row
    $summary.= invoice::get_invoice_list_tr_header($_FORM);

    is_array($filter1_where) && count($filter1_where) and $f1_where = " WHERE ".implode(" AND ",$filter1_where);
    is_array($filter2_where) && count($filter2_where) and $f2_where = " WHERE ".implode(" AND ",$filter2_where);
    is_array($filter2_having) && count($filter2_having) and $f2_having = " HAVING ".implode(" AND ",$filter2_having);
 
    $q1= "CREATE TEMPORARY TABLE invoice_details
          SELECT SUM(invoiceItem.iiAmount) as iiAmountSum
               , invoice.*
               , client.clientName
            FROM invoice
       LEFT JOIN invoiceItem on invoiceItem.invoiceID = invoice.invoiceID
       LEFT JOIN client ON invoice.clientID = client.clientID
              $f1_where
        GROUP BY invoice.invoiceID
        ORDER BY invoiceDateFrom";

    $db = new db_alloc;
    #$db->query("DROP TABLE IF EXISTS invoice_details");
    $db->query($q1);

    $q2= "SELECT invoice_details.*
               , SUM(transaction_approved.amount) as amountPaidApproved
               , SUM(transaction_pending.amount) as amountPaidPending
               , SUM(transaction_rejected.amount) as amountPaidRejected
            FROM invoice_details
       LEFT JOIN invoiceItem on invoiceItem.invoiceID = invoice_details.invoiceID
       LEFT JOIN transaction transaction_approved on invoiceItem.invoiceItemID = transaction_approved.invoiceItemID AND transaction_approved.status = 'approved'
       LEFT JOIN transaction transaction_pending on invoiceItem.invoiceItemID = transaction_pending.invoiceItemID AND transaction_pending.status = 'pending'
       LEFT JOIN transaction transaction_rejected on invoiceItem.invoiceItemID = transaction_rejected.invoiceItemID AND transaction_rejected.status = 'rejected'
       LEFT JOIN tfPerson ON tfPerson.tfID = transaction_approved.tfID OR tfPerson.tfID = transaction_pending.tfID OR tfPerson.tfID = transaction_rejected.tfID
              $f2_where
        GROUP BY invoice_details.invoiceID
              $f2_having
        ORDER BY invoiceDateFrom";

    $debug and print "<pre>Query1: ".$q1."</pre>";
    $debug and print "<pre>Query2: ".$q2."</pre>";
    $db->query($q2);

    while ($row = $db->next_record()) {
      $print = true;
      $i = new invoice;
      $i->read_db_record($db);
      $row["invoiceLink"] = $i->get_invoice_link();
      $summary.= invoice::get_invoice_list_tr($row,$_FORM);
      $summary_ops[$i->get_id()] = stripslashes($i->get_value("invoiceNum"));
    }

    if ($print && $_FORM["return"] == "html") {
      return "<table class=\"tasks\" border=\"0\" cellspacing=\"0\">".$summary."</table>";

    } else if ($print && $_FORM["return"] == "dropdown_options") {
      return $summary_ops;

    } else if (!$print && $_FORM["return"] == "html") {
      return "<table style=\"width:100%\"><tr><td colspan=\"10\" style=\"text-align:center\"><b>No Invoices Found</b></td></tr></table>";
    }

  }

  function get_invoice_list_tr_header($_FORM) {
    if ($_FORM["showHeader"]) {

      if ($_FORM["showInvoiceAmountPaid"]) {
        $summary.= "\n<tr>";
        $summary.= "\n<td colspan=\"7\">&nbsp;</td>";
        $summary.= "\n<th class=\"col\" colspan=\"3\" style=\"text-align:center;\">Transactions</th>";
        $summary.="\n</tr>";
      }

      $summary.= "\n<tr>";
      $_FORM["showInvoiceNumber"]       and $summary.= "\n<th class=\"col\">Invoice Number</th>";
      $_FORM["showInvoiceClient"]       and $summary.= "\n<th class=\"col\">Client</th>";
      $_FORM["showInvoiceName"]         and $summary.= "\n<th class=\"col\">Name</th>";
      $_FORM["showInvoiceDate"]         and $summary.= "\n<th class=\"col\">From</th>";
      $_FORM["showInvoiceDate"]         and $summary.= "\n<th class=\"col\">To</th>";
      $_FORM["showInvoiceStatus"]       and $summary.= "\n<th class=\"col\">Status</th>";
      $_FORM["showInvoiceAmount"]       and $summary.= "\n<th class=\"col\">Amount</th>";
      $_FORM["showInvoiceAmountPaid"]   and $summary.= "\n<th class=\"col\">Rejected</th>";
      $_FORM["showInvoiceAmountPaid"]   and $summary.= "\n<th class=\"col\">Pending</th>";
      $_FORM["showInvoiceAmountPaid"]   and $summary.= "\n<th class=\"col\">Approved</th>";
      $summary.="\n</tr>";
      return $summary;
    }
  }

  function get_invoice_list_tr($invoice,$_FORM) {
    global $TPL;

    static $odd_even;
    $odd_even = $odd_even == "even" ? "odd" : "even";
    $statii = invoice::get_invoice_statii();
    $currency = '$';

    $summary[] = "<tr class=\"".$odd_even."\">";
    $_FORM["showInvoiceNumber"]       and $summary[] = "  <td class=\"col\">".$invoice["invoiceLink"]."&nbsp;</td>";
    $_FORM["showInvoiceClient"]       and $summary[] = "  <td class=\"col\"><a href=\"".$TPL["url_alloc_client"]."clientID=".$invoice["clientID"]."\">".$invoice["clientName"]."</a></td>";
    $_FORM["showInvoiceName"]         and $summary[] = "  <td class=\"col\">".$invoice["invoiceName"]."&nbsp;</td>";
    $_FORM["showInvoiceDate"]         and $summary[] = "  <td class=\"col nobr\">".$invoice["invoiceDateFrom"]."&nbsp;</td>";
    $_FORM["showInvoiceDate"]         and $summary[] = "  <td class=\"col nobr\">".$invoice["invoiceDateTo"]."&nbsp;</td>";
    $_FORM["showInvoiceStatus"]       and $summary[] = "  <td class=\"col nobr\">".$statii[$invoice["invoiceStatus"]]."&nbsp;</td>";
    $_FORM["showInvoiceAmount"]       and $summary[] = "  <td class=\"col\">".$currency.sprintf("%0.2f",$invoice["iiAmountSum"])."&nbsp;</td>";
    $_FORM["showInvoiceAmountPaid"]   and $summary[] = "  <td class=\"col\">".$currency.sprintf("%0.2f",$invoice["amountPaidRejected"])."&nbsp;</td>";
    $_FORM["showInvoiceAmountPaid"]   and $summary[] = "  <td class=\"col\">".$currency.sprintf("%0.2f",$invoice["amountPaidPending"])."&nbsp;</td>";
    $_FORM["showInvoiceAmountPaid"]   and $summary[] = "  <td class=\"col\">".$currency.sprintf("%0.2f",$invoice["amountPaidApproved"])."&nbsp;</td>";
    $summary[] = "</tr>";

    $summary = "\n".implode("\n",$summary);
    return $summary;
  }

  function load_form_data($defaults=array()) {
    global $current_user;

    $page_vars = array("invoiceID"
                      ,"clientID"
                      ,"invoiceNum"
                      ,"dateOne"
                      ,"dateTwo"
                      ,"invoiceName"
                      ,"invoiceStatus"
                      ,"invoiceStatusPayment"
                      ,"tfIDs"
                      ,"url_form_action"
                      ,"form_name"
                      ,"dontSave"
                      ,"applyFilter"
                      ,"showHeader"
                      ,"showInvoiceNumber"
                      ,"showInvoiceClient"
                      ,"showInvoiceName"
                      ,"showInvoiceAmount"
                      ,"showInvoiceAmountPaid"
                      ,"showInvoiceDate"
                      ,"showInvoiceStatus"
                      ,"showInvoiceStatusPayment"
                      );

    $_FORM = get_all_form_data($page_vars,$defaults);

    if (!$_FORM["applyFilter"]) {
      $_FORM = $current_user->prefs[$_FORM["form_name"]];
      if (!isset($current_user->prefs[$_FORM["form_name"]])) {
        // defaults go here
        $_FORM["invoiceStatus"] = "edit";
      }

    } else if ($_FORM["applyFilter"] && is_object($current_user) && !$_FORM["dontSave"]) {
      $url = $_FORM["url_form_action"];
      unset($_FORM["url_form_action"]);
      $current_user->prefs[$_FORM["form_name"]] = $_FORM;
      $_FORM["url_form_action"] = $url;
    }

    return $_FORM;
  }

  function load_invoice_filter($_FORM) {
    global $TPL;

    // Load up the forms action url
    $rtn["url_form_action"] = $_FORM["url_form_action"];

    $statii = invoice::get_invoice_statii();
    unset($statii["create"]);
    $rtn["statusOptions"] = get_select_options($statii,$_FORM["invoiceStatus"]);
    $statii_payment = invoice::get_invoice_statii_payment();
    $rtn["statusPaymentOptions"] = get_select_options($statii_payment,$_FORM["invoiceStatusPayment"]);
    $rtn["status"] = $_FORM["status"];
    $rtn["dateOne"] = $_FORM["dateOne"];
    $rtn["dateTwo"] = $_FORM["dateTwo"];
    $rtn["invoiceID"] = $_FORM["invoiceID"];
    $rtn["invoiceName"] = $_FORM["invoiceName"];
    $rtn["invoiceNum"] = $_FORM["invoiceNum"];
    $rtn["invoiceItemID"] = $_FORM["invoiceItemID"];

    $options["clientStatus"] = "current";
    $options["return"] = "dropdown_options";
    $ops = client::get_client_list($options);
    $rtn["clientOptions"] = get_select_options($ops,$_FORM["clientID"]);

    // Get
    $rtn["FORM"] = "FORM=".urlencode(serialize($_FORM));

    return $rtn;
  }

  function update_invoice_dates($invoiceID) {
    $db = new db_alloc();
    $db->query(sprintf("SELECT max(iiDate) AS maxDate, min(iiDate) AS minDate
                          FROM invoiceItem
                         WHERE invoiceID=%d"
                      ,$invoiceID));
    $db->next_record();
    $invoice = new invoice;
    $invoice->set_id($invoiceID);
    $invoice->select();
    $invoice->set_value("invoiceDateFrom", $db->f("minDate"));
    $invoice->set_value("invoiceDateTo", $db->f("maxDate"));
    return $invoice->save();
  }

  function close_related_entities() {
    $db = new db_alloc();
    $invoiceItemIDs = $this->get_invoiceItems();
    foreach ($invoiceItemIDs as $invoiceItemID) {

      $q = sprintf("SELECT *
                      FROM transaction
                     WHERE invoiceItemID = %d
                       AND status = 'pending'"
                  ,$invoiceItemID);
      $db->query($q);
      if (!$db->next_record()) {
        $invoiceItem = new invoiceItem;
        $invoiceItem->set_id($invoiceItemID);
        $invoiceItem->select();
        $invoiceItem->close_related_entity();
      }
    }
  }

  function change_status($direction) {

    $steps["forwards"][""] = "edit";
    $steps["forwards"]["edit"] = "generate";
    $steps["forwards"]["generate"] = "reconcile";
    $steps["forwards"]["reconcile"] = "finished";

    $steps["backwards"]["finished"] = "reconcile";
    $steps["backwards"]["reconcile"] = "generate";
    $steps["backwards"]["generate"] = "edit";
    $steps["backwards"]["edit"] = "";

    $status = $this->get_value("invoiceStatus");
    $newstatus = $steps[$direction][$status];
    if ($newstatus) {
      $m = $this->{"move_status_to_".$newstatus}($direction);
      if (is_array($m)) {
        return implode("<br/>",$m);
      }
    }


  }

  function move_status_to_edit($direction) {
    $this->set_value("invoiceStatus", "edit");
  }

  function move_status_to_generate($direction) {
    global $current_user;
    if ($direction == "forwards") {
      $current_user->have_role('admin') && $this->generate_invoice_file();
    }
    $this->set_value("invoiceStatus", "generate");
  }

  function move_status_to_reconcile($direction) {
    $this->set_value("invoiceStatus", "reconcile");
  }

  function move_status_to_finished($direction) {
    global $TPL;
    if ($direction == "forwards") {
      if ($this->has_pending_transactions()) {
        $TPL["message"][] = "There are still Invoice Items in dispute. This Invoice cannot be marked completed.";
        return;
      } else {
        $this->close_related_entities();
      }
    }
    $this->set_value("invoiceStatus", "finished");
  }

  function has_pending_transactions() {
    $q = sprintf("SELECT * 
                    FROM transaction
               LEFT JOIN invoiceItem on transaction.invoiceItemID = invoiceItem.invoiceItemID
                   WHERE invoiceItem.invoiceID = %d AND transaction.status = 'pending' 
                   ",$this->get_id());
    $db = new db_alloc();
    $db->query($q);
    return $db->next_record();
  }


}



?>