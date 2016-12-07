<?php
// ini_set('display_errors',1);
// ini_set('display_startup_errors',1);
// error_reporting(-1);

require('lib/auth.php');
list($frameworkId, $frameworkName, $frameworkData) = initFramework($db);

$ini_arr = parse_ini_file("daytona_config.ini");

$servername = $ini_arr["servername"];
$username = $ini_arr["username"];
$password = $ini_arr["password"];
$dbname = $ini_arr["dbname"];
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo "<script>alert('Connection failed');</script>\n";
    die("Connection failed: " . $conn->connect_error);
}

$search_where_clause_arr = array();
$s_framework = "Global";
$s_numresults = "20";
if ($frameworkName) {
    $s_framework = $frameworkName;
}
elseif (!empty($_GET["framework"])) {
    $s_framework = $_GET["framework"];
}
if ($s_framework != "Global") {
    array_push($search_where_clause_arr, "frameworkname='$s_framework'");
}
if (!empty($_GET["purpose"])) {
    $s_purpose = $_GET["purpose"];
    array_push($search_where_clause_arr, "TestInputData.purpose LIKE '%$s_purpose%'");
}
if (!empty($_GET["title"])) {
    $s_title = $_GET["title"];
    array_push($search_where_clause_arr, "TestInputData.title LIKE '%$s_title%'");
}
if (!empty($_GET["owner"])) {
    $s_owner = $_GET["owner"];
    array_push($search_where_clause_arr, "username LIKE '%$s_owner%'");
}
if (!empty($_GET["status"])) {
    $s_status = $_GET["status"];
    array_push($search_where_clause_arr, "end_status='$s_status'");
}
if (!empty($_GET["numresults"])) {
    $s_numresults = $_GET["numresults"];
}
if (!empty($_GET["start"])) {
    $s_start = $_GET["start"];
    $converted_start = convertToDateTime($s_start, true);
    array_push($search_where_clause_arr, "start_time>='$converted_start'");
}
if (!empty($_GET["end"])) {
    $s_end = $_GET["end"];
    $converted_end = convertToDateTime($s_end, false);
    array_push($search_where_clause_arr, "end_time<='$converted_end'");
}

$search_where_clause = "";
if (sizeof($search_where_clause_arr) > 0) {
    $search_where_clause = "WHERE " . implode(" AND ", $search_where_clause_arr);
}

function convertTime($str_time)
{
    if (!$str_time) {
        return "N/A";
    }
    $time = strtotime($str_time . " UTC");
    return date("m/d/Y g:i A", $time);
}

function convertToDateTime($str_time, $is_start)
{
    $time = $is_start ? "00:00:00" : "23:59:59";
    return "$str_time $time";
}

$frameworks_sql = "SELECT DISTINCT frameworkname from CommonFrameworkAuthentication
                   JOIN ApplicationFrameworkMetadata USING(frameworkid)
                   WHERE username='$userId'";
$frameworks_result = $conn->query($frameworks_sql);
$frameworks_arr = array();
while ($row = $frameworks_result->fetch_assoc()) {
    array_push($frameworks_arr, $row["frameworkname"]);
}

$search_sql = "SELECT testid,frameworkid,frameworkname,TestInputData.title,username,start_time,
               end_time,TestInputData.creation_time,end_status,TestInputData.purpose
               FROM TestInputData JOIN ApplicationFrameworkMetadata USING(frameworkid)
               $search_where_clause ORDER BY testid DESC LIMIT $s_numresults";
$search_result = $conn->query($search_sql);
$search_arr = array();
while ($row = $search_result->fetch_assoc()) {
    array_push($search_arr, $row);
}

$pageTitle = "Search";
include_once('lib/header.php');
?>
    <div class="content-wrapper" id="page-content">
        <div id="main-panel-alt-top">
            <div class='col-md-6' id='action-buttons-div-left'>
                <button type="button" class="btn btn-danger btn-action" data-toggle="modal"
                        data-target="#confirm-delete" id="delete-button" disabled>
                    Delete
                </button>
            </div>
            <div class="col-md-6" id="action-buttons-div">
                <button type="button" class="btn btn-default btn-action" onclick="submitAdvanceCompare()"
                        id="advance-compare-button" disabled>
                    Detailed Compare
                </button>
                <button type="button" class="btn btn-primary btn-action" onclick="submitBasicCompare()"
                        id="compare-button" disabled>
                    Compare
                </button>
            </div>
        </div>
        <div class="col-xs-12" id="content-div">
            <div id="main-panel-alt">
                <div class="panel panel-info panel-search">
                    <div class="panel-heading">
                        <button type='button' class='btn-sm' id='filter-toggle' data-toggle='collapse' data-target='#collapse1'>
                          <span class='glyphicon glyphicon glyphicon-plus'></span>
                        </button>
                        <p style='margin:0px 0px 0px 45px;padding-top:4px;'>Search Options</p>
                    </div>
                    <div id='collapse1' class='panel-collapse collapse'>
                    <div class="panel-body">
                        <form class="form-horizontal zero-margin" role="form">
                            <fieldset>
                                <div class="form-group" style="margin-top: 8px">
                                    <label for="title" class="col-sm-1 control-label">Title:</label>
                                    <div class="col-sm-2 text_div">
                                        <input class="form-control" type="text" name="title"
                                               value="<?php echo $s_title; ?>">
                                    </div>
                                    <label for="framework" class="col-sm-2 control-label">Framework:</label>
                                    <div class="col-sm-2 text_div">
                                        <select class="form-control" name="framework">
                                            <?php
                                            echo "              <option>Global</option>\n";
                                            foreach ($frameworks_arr as $l_framework) {
                                                $l_selected = $l_framework == $s_framework ? "selected" : "";
                                                echo "              <option $l_selected>$l_framework</option>\n";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <label for="status" class="col-sm-1 control-label">Status:</label>
                                    <div class="col-sm-2">
                                        <select class="form-control" name="status">
                                            <?php
                                            $status_options = array("", "finished clean", "aborted",
                                                "missing resource", "failed");
                                            foreach ($status_options as $l_status_opt) {
                                                $l_selected = $l_status_opt == $s_status ? "selected" : "";
                                                echo "              <option $l_selected>$l_status_opt</option>\n";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="owner" class="col-sm-1 control-label">Owner:</label>
                                    <div class="col-sm-2 text_div">
                                        <input class="form-control" type="text" name="owner"
                                               value="<?php echo $s_owner; ?>">
                                    </div>
                                    <label for="purpose" class="col-sm-2 control-label">Purpose:</label>
                                    <div class="col-sm-2 text_div">
                                        <input class="form-control" type="text" name="purpose"
                                               value="<?php echo $s_purpose; ?>">
                                    </div>
                                    <label for="numresults" class="col-sm-1 control-label">Results:</label>
                                    <div class="col-sm-2">
                                        <select class="form-control" name="numresults">
                                            <?php
                                            $numresults_options = array("20", "50", "100", "all");
                                            foreach ($numresults_options as $l_numresults_opt) {
                                                $l_selected = $l_numresults_opt == $s_numresults ? "selected" : "";
                                                echo "              <option $l_selected>$l_numresults_opt</option>\n";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 8px">
                                    <label class="col-sm-1 control-label">Date:</label>
                                    <div class="col-sm-2">
                                        <input class="form-control" id="datepicker" type="date" name="start"
                                               value="<?php echo $s_start; ?>">
                                    </div>
                                    <div class="col-sm-1" style="width:auto;margin-top:5px">
                                        <label class="control-label zero-margin">to</label>
                                    </div>
                                    <div class="col-sm-2 text_div">
                                        <input class="form-control" id="datepicker2" type="date" name="end"
                                               value="<?php echo $s_end; ?>">
                                    </div>
                                    <div class="col-sm-4">
                                        <button type="submit" class="btn btn-primary btn-search-submit">Submit</button>
                                    </div>
                                </div>
                                <fieldset>
                        </form>
                    </div>
                    </div>
                </div>
                <div class="panel panel-info panel-search">
                    <div class="panel-heading">Search Results</div>
                    <div class="panel-body" style="padding: 0px 8px">
                        <?php
                        if (sizeof($search_arr) > 0) {
                            foreach ($search_arr as $search_result) {
                                switch ($search_result["end_status"]) {
                                    case "finished clean":
                                        $status_style = "panel-success";
                                        $status_style_td = "emphasize-green";
                                        break;
                                    case "aborted":
                                        $status_style = "panel-warning";
                                        $status_style_td = "emphasize-yellow";
                                        break;
                                    default:
                                        $status_style = "panel-danger";
                                        $status_style_td = "emphasize-red";
                                }
                                echo "      <div class='panel panel-search-result $status_style'>\n";
                                echo "        <div class='panel-heading header-search'>\n";
                                echo "          <input name='test-checkbox' type='checkbox' data-testid='" . $search_result["testid"] . "'></input>\n";
                                echo "          <a href='test_info.php?testid=" . $search_result["testid"] .
                                    "'>" . $search_result["testid"] . ": " .
                                    $search_result["title"] . "</a>\n";
                                echo "        </div>\n";
                                echo "        <div class='panel-body' id='zero-padding'>\n";
                                echo "          <table class='table' id='result-table'>\n";
                                echo "            <tbody>\n";
                                echo "              <tr>\n";
                                echo "                <td class='active' style='width:30%'>Framework</td>\n";
                                echo "                <td class='test-data-td'><a href='/?frameworkid=". $search_result["frameworkid"]  . "'><b>" . $search_result["frameworkname"] . "</b></a></td>\n";
                                echo "              </tr>\n";
                                echo "              <tr>\n";
                                echo "                <td class='active' style='width:30%'>User</td>\n";
                                echo "                <td class='test-data-td'>" . $search_result["username"] . "</td>\n";
                                echo "              </tr>\n";
                                echo "              <tr>\n";
                                echo "                <td class='active'>Last Run</td>\n";
                                echo "                <td class='test-data-td'>" . convertTime($search_result["start_time"]) .
                                    " to " . convertTime($search_result["end_time"]) . "</td>\n";
                                echo "              </tr>\n";
                                echo "              <tr>\n";
                                echo "                <td class='active'>Creation Time</td>\n";
                                echo "                <td class='test-data-td'>" . convertTime($search_result["creation_time"]) . "</td>\n";
                                echo "              </tr>\n";
                                echo "              <tr>\n";
                                echo "                <td class='active'>Status</td>\n";
                                echo "                <td class='test-data-td $status_style_td'>" . $search_result["end_status"] . "</td>\n";
                                echo "              </tr>\n";
                                echo "              <tr>\n";
                                echo "                <td class='active'>Purpose</td>\n";
                                echo "                <td class='test-data-td'>" . nl2br($search_result["purpose"]) . "</td>\n";
                                echo "              </tr>\n";
                                echo "            </tbody>\n";
                                echo "          </table>\n";
                                echo "        </div>\n";
                                echo "      </div>\n";
                            }
                        } else {
                            echo "      <p class='no-test-label'>No results found</p>\n";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- .content-wrapper -->
    </main> <!-- .cd-main-content -->

<?php
$deleteMessage = "WARNING: Deleting this/these test(s) cannot be undone. Are you SURE you want to continue?";
include_once('lib/popup.php');
?>

    <script src="js/search.js"></script>
    <script type="text/javascript">
        $(document).ready(function () {
          $("#collapse1").on("hide.bs.collapse", function(){
              $("#filter-toggle").html('<span class="glyphicon glyphicon glyphicon-plus"></span>');
            });
            $("#collapse1").on("show.bs.collapse", function(){
              $("#filter-toggle").html('<span class="glyphicon glyphicon glyphicon-minus"></span>');
            });

            buildTopNavBar('<?php echo $frameworkName ?: 'Global'; ?>', '', '<?php echo $userId; ?>');
            setDescription('Search');
            buildUserAccountMenu('<?php echo $userId; ?>');
            buildLeftPanel();
            <?php if ($frameworkId): ?>
            buildLeftPanelTest(<?php echo $frameworkId; ?>, '<?php echo $userId; ?>');
            buildLeftPanelFramework('<?php echo $frameworkName; ?>', <?php echo $frameworkId; ?>);
            <?php else: ?>
            buildLeftPanelFramework();
            <?php endif ?>
            buildLeftPanelGlobal();
            loadNavigationBar();
            <?php addFrameworkDropdownJS($db, $userId); ?>
        });
    </script>

    </body>
    </html>
<?php
$conn->close();
?>
