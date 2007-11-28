      <table class="filter" align="center" class="center">
        <tr>
          <td colspan="6" class="center">
            {get_side_by_side_links(array("simple_search"=>"Simple","advanced_search"=>"Advanced"),$_POST["search_type"])}
          </td>
        </tr>
        <tr>
          <td>
            <div id="advanced_search">
            <form action="{$url_alloc_transactionList}" method="post">
            <table>
              <tr>
                <td align="left">Start Date</td>
                <td align="left">End Date</td>
                <td align="left">Type</td>
                <td align="left">Status</td>
                <td align="left">Sort By</td>
                <td align="left">&nbsp;</td>
              </tr>
              <tr>
                <td>{get_calendar("startDate",$TPL["startDate"])}</td>
                <td>{get_calendar("endDate",$TPL["endDate"])}</td>
                <td><select name="transactionType"><option value=""> {$transactionTypeOptions}</select></td>
                <td><select name="status"><option value=""> {$statusOptions}</select></td>
                <td>
                <input type="radio" id="st_sd" name="sortTransactions" value="transactionSortDate"{$checked_transactionSortDate}> 
                <label for="st_sd">Last Modified</label><br>
                <input type="radio" id="st_td" name="sortTransactions" value="transactionDate"{$checked_transactionDate}> 
                <label for="st_td">Transaction Date</label>

                </td>
                <td><input type="hidden" name="tfID" value="{$tfID}">
                    <input type="submit" name="download" value="CSV">
                    <input type="submit" name="applyFilter" value="Filter">
                    <input type="hidden" name="search_type" value="advanced_search">
                </td>
              </tr>
            </table>
			      </form>
            </div>

          </td>
        </tr>
        <tr>
          <td align="center" colspan="10">
            <div id="simple_search">{$month_links}</div> 
          </td>
        </tr>
      </table>


{show_footer()}