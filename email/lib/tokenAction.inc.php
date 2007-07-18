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

class tokenAction extends db_entity {
  var $classname = "tokenAction";
  var $data_table = "tokenAction";

  function tokenAction() {
    $this->db_entity(); 
    $this->key_field = new db_field("tokenActionID");
    $this->data_fields = array("tokenAction"=>new db_field("tokenAction")
                              ,"tokenActionType"=>new db_field("tokenActionType")
                              ,"tokenActionMethod"=>new db_field("tokenActionMethod")
                              );

  }
}



?>