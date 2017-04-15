<?php
// ini_set('display_errors',1);
// ini_set('display_startup_errors',1);
// error_reporting(-1);

require('lib/auth.php');
include 'process_data.php';

$allTestData = array();
$testId = getParam('testid');
if (!$testId) {
    diePrint("No test ID passed in");
}
if (!is_numeric($testId)) {
    diePrint("testid is not valid.");
}
$origTestData = getTestById($db, $testId, true);
if (!$origTestData) {
    diePrint("Could not find test id: $testId");
}
$frameworkId = $origTestData['frameworkid'];
$frameworkName = $origTestData['frameworkname'];
$frameworkData = getFrameworkById($db, $frameworkId, true);
$allTestData[] = $origTestData;

$compIds = getParam('compids');
if ($compIds && ! preg_match('/^\d+(,\d+)*$/', $compIds)) {
    diePrint("compids is not valid.");
}
if ($compIds) {
    foreach (explode(',', $compIds) as $compId) {
        $testData = getTestById($db, $compId, true);
        if (!$testData) {
            diePrint("Could not find compare test ID: $compId");
        }
        if ($testData['frameworkid'] != $frameworkId) {
            diePrint("Cannot compare test IDs from different frameworks.");
        }
        $allTestData[] = $testData;
    }
}

$s_compids_orig = "";
$s_compids_str = "";
$s_compids = array();
if(!empty($_GET["compids"])) {
    $s_compids = explode(",", getParam("compids"));
    $s_compids_orig = getParam("compids");
    $s_compids_str = $s_compids_orig;
}
$s_testid = "none";
if(isset($_GET["testid"])) {
    $s_testid = getParam("testid");
    array_unshift($s_compids, $s_testid);
    if(!empty($s_compids_str)){
        $s_compids_str = $s_testid . "," . $s_compids_str;
    }else{
        $s_compids_str = $s_testid;
    }
}
function formatFilePath($path, $mapping) {
    $pattern = '/%(STAT|EXEC|RESERVED){1}HOST,([0-9]+)%(.*)/';
    if(preg_match($pattern, $path, $output_array)) {
        switch($output_array[1]) {
            case "STAT":
                return $mapping["statistics"][$output_array[2]] . $output_array[3];
                break;
            case "EXEC":
                return $mapping["execution"][$output_array[2]] . $output_array[3];
                break;
            case "RESERVED":
                return $mapping["reserved"][$output_array[2]] . $output_array[3];
                break;
        }
    }
    else {
        return $mapping["execution"][0] . "/" . $path;
    }
}

function printRowFields($allTestData, $field, $isTime=false) {
    foreach ($allTestData as $curTestData) {
        if (isset($curTestData[$field])) {
            $value = $isTime ? convertTime($curTestData[$field]) : $curTestData[$field];
            if(is_array($value)) {
                $value = implode(",", $value);
            }
            echo "            <td class=\"test-data-td\">" . nl2br($value) . "</td>\n";
        } else {
            echo "            <td class=\"test-data-td\"></td>\n";
        }
    }
}

function stateColorClass($status) {
    switch($status) {
        case 'finished clean':
            return 'emphasize-green';
        case 'aborted':
            return 'emphasize-yellow';
        default:
            return 'emphasize-red';
    }
}

function convertTime($timeStr) {
    if (! $timeStr) {
        return 'N/A';
    }
    $time = strtotime("$timeStr UTC");
    return date("m/d/Y g:i:s A", $time);
}
include_once('lib/header.php');
?>
<script src="https://d3js.org/d3.v3.min.js" charset="utf-8"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.js" charset="utf-8"></script>
<script src="js/bootstrap-sortable.js"></script>
<script src="js/test_report.js"></script>
<script src="js/output.js"></script>
</head>
<?php
$s_compids_arr = explode(",",$s_compids_str);
$bind_vale_str = "";
for ($x = 1; $x <=sizeof($s_compids_arr); $x++){
    $bind_vale_str = $bind_vale_str . ":test" . $x;
    if($x !== sizeof($s_compids_arr)){
        $bind_vale_str = $bind_vale_str . ",";
    }
}

try{
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();
    $query = "SELECT frameworkname,TestInputData.title,TestInputData.purpose,exechostname,
                  stathostname,priority,timeout,cc_list,
                  TestInputData.creation_time,modified,start_time,end_time FROM TestInputData
                  JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE testid=:testid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid',$s_testid,PDO::PARAM_INT);
    $stmt->execute();
    $test_info_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $query = "SELECT testid,hostassociationid,hostname,name FROM HostAssociation JOIN HostAssociationType
                   USING(hostassociationtypeid) WHERE testid IN (" . $bind_vale_str . ") ORDER BY hostassociationid";
    $stmt = $db->prepare($query);
    for ($x = 1; $x <=sizeof($s_compids_arr); $x++){
        $stmt->bindValue(':test'.$x ,$s_compids_arr[$x-1], PDO::PARAM_INT);
    }
    $stmt->execute();
    $test_hosts_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($test_hosts_result as $row){
        if(!isset($test_info_data[$row["testid"]][$row["name"]])) {
            $test_info_data[$row["testid"]][$row["name"]] = array();
        }
        array_push($test_info_data[$row["testid"]][$row["name"]], $row["hostname"]);
    }
    $query = "SELECT argument_name,argument_default,argument_description,argument_value
                  FROM TestArgs JOIN ApplicationFrameworkArgs USING(framework_arg_id)
                  WHERE testid=:testid ORDER BY testargid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid',$s_testid,PDO::PARAM_INT);
    $stmt->execute();
    $test_args_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $test_args_arr = array();
    foreach ($test_args_arr as $row) {
        $l_test_arg_row = array();
        $l_test_arg_row[0] = $row["argument_name"];
        $l_test_arg_row[1] = $row["argument_default"];
        $l_test_arg_row[2] = $row["argument_description"];
        $l_test_arg_row[3] = $row["argument_value"];
        array_push($test_args_arr, $l_test_arg_row);
    }
    $query = "SELECT filename,TestResultFile.title,row FROM TestResultFile
               JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE frameworkname=:frameworkname ORDER BY filename_order";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkname',$test_info_data["frameworkname"],PDO::PARAM_STR);
    $stmt->execute();
    $report_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $report_arr = array();
    foreach($report_result as $row){
        if(!isset($report_arr[$row["row"]])) {
            $report_arr[$row["row"]] = array();
        }
        array_push($report_arr[$row["row"]], array(str_replace("rrd","csv",$row["filename"]), $row["title"]));
    }
}catch (PDOException $e){
    $db->rollBack();
    returnError("MySQL error: " . $e->getMessage());
}
?>


<div class="content-wrapper" id="page-content">
    <div id="main-panel-alt-top">
        <div class='col-md-6' id="action-buttons-div-left">
            <form class="zero-margin form-inline" role="form" onSubmit="return checkTestCount()">
                <div class='input-group' style='z-index:0'>
                    <input type="text" class="form-control h-30" name="compids" id="compids" value="<?php echo $s_compids_orig; ?>" placeholder="Example: 100,102,105">
                    <input type="hidden" name="testid" id="testid" value="<?php echo $s_testid; ?>">
                    <span class='input-group-btn'>
                <button type="submit" class="btn btn-primary btn-action">
                  Compare
                </button>
               </span>
                </div>
            </form>
        </div>
        <div class="col-md-6 action-buttons-alt" id ="action-buttons-div">
            <button type="button" onclick="collapseAllGraph()" class="btn btn-info btn-action">
                <i class="fa fa-minus-square fa-lg"></i>
                &nbsp;Collapse
            </button>
            <button type="button" onclick="expandAllGraph()" class="btn btn-info btn-action">
                <i class="fa fa-plus-square fa-lg"></i>
                &nbsp;Expand
            </button>
        </div>
    </div>
    <div class="col-xs-12" id="content-div">
        <div id="main-panel-alt">
            <?php
            $collapse_id = 0;
            foreach($report_arr as $rownum => $row_reports) {
                $graph_class = "partition-" . sizeof($row_reports);
                echo "<div>\n";
                foreach($row_reports as $report_identifier) {
                    $filename = $report_identifier[0];
                    $title = $report_identifier[1];
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $collapse_id++;
                    echo "<div class='panel panel-info panel-test-report $graph_class'>\n";
                    echo "  <div class='panel-heading collapse-heading'>\n";
                    echo "    <h4 class='panel-title text-center'>\n";
                    echo "      <a data-toggle='collapse' class='collapse-link' href='#collapse$collapse_id'>" . $title . "</a>\n";
                    echo "    </h4>\n";
                    echo "  </div>\n";
                    echo "  <div id='collapse$collapse_id' class='panel-collapse collapse graph-panel in no-transition'>\n";
                    if (strpos($extension, 'plt') !== false){
                        $marked = 0;
                        $full_paths = "";
                        foreach($s_compids as $l_id) {
                            if($marked) {
                                $full_paths .= ",";
                            }
                            $marked = 1;
                            $report_path = "test_data/" . $test_info_data["frameworkname"] . "/$l_id/results/" . formatFilePath($filename, $test_info_data[$l_id]);
                            $full_paths .= $report_path;
                        }
                        buildTestReportGraphView($collapse_id, $full_paths, $s_compids_str, $title);
                        echo "  </div>\n";
                    }elseif (strpos($extension, 'csv') !== false){
                        $err_threshold = sizeof($s_compids);
                        $marked = 0;
                        $full_paths = "";
                        foreach($s_compids as $l_id) {
                            if($marked) {
                                $full_paths .= ",";
                            }
                            $marked = 1;
                            $report_path = "test_data/" . $test_info_data["frameworkname"] . "/$l_id/results/" .
                                formatFilePath($report_identifier[0], $test_info_data[$l_id]);
                            $report_path_file = strpos($report_path, ":") ? substr($report_path, 0, strpos($report_path, ":")) : $report_path;
                            if(!file_exists($report_path_file)) {
                                $err_threshold--;
                            }
                            $full_paths .= $report_path;
                        }
                        $div_id = "output-panel" . $collapse_id;
                        $csv_col_count = getCsvColumnCount($full_paths);
                        if ($csv_col_count == 2){
                            $csv_compare = generateCompareCsv($full_paths,$s_compids_str);
                            $csv_compare_json = json_encode($csv_compare);
                            echo "    <div class='fixed-height' id=$div_id></div>\n";
                            echo "    <script>buildJsonToTableView('$csv_compare_json', '$div_id');</script>\n";
                            echo "  </div>\n";
                        }else{
                            echo "    <div class='fixed-height' id=$div_id></div>\n";
                            $file_data_array = buildTestCompareData($full_paths,$s_compids_str);
                            if (strcmp(gettype($file_data_array),"string") !== 0){
                                $file_data_json = json_encode($file_data_array);
                                echo "    <script>buildFileToTableView('$file_data_json', '$s_compids_str','$div_id');</script>\n";
                            }else{
                                echo "  <script> buildGraphErrorView('$file_data_array','$div_id','Error','3'); </script>\n";
                            }
                            echo "  </div>\n";
                        }
                    }else {
                        $err_threshold = sizeof($s_compids);
                        $marked = 0;
                        $full_paths = "";
                        foreach($s_compids as $l_id) {
                            if($marked) {
                                $full_paths .= ",";
                            }
                            $marked = 1;
                            $report_path = "test_data/" . $test_info_data["frameworkname"] . "/$l_id/results/" .
                                formatFilePath($report_identifier[0], $test_info_data[$l_id]);
                            $report_path_file = strpos($report_path, ":") ? substr($report_path, 0, strpos($report_path, ":")) : $report_path;
                            if(!file_exists($report_path_file)) {
                                $err_threshold--;
                            }
                            $full_paths .= $report_path;
                        }
                        $div_id = "output-panel" . $collapse_id;
                        echo "    <div id=$div_id></div>\n";
                        $file_data_array = buildTestCompareData($full_paths,$s_compids_str);
                        if (strcmp(gettype($file_data_array),"string") !== 0){
                            $file_data_json = json_encode($file_data_array);
                            echo "    <script>buildTextCompareView('$file_data_json', '$s_compids_str','$div_id');</script>\n";
                        }else{
                            echo "  <script> buildGraphErrorView('$file_data_array','$div_id','Error','3'); </script>\n";
                        }
                        echo "  </div>\n";
                    }

                    echo "</div>";
                }
                echo "</div>";
            }
            ?>
            <div>
                <div class="panel panel-info panel-sub-main">
                    <?php
                    $collapse_id++;
                    echo "  <div class='panel-heading collapse-heading'>\n";
                    echo "    <h4 class='panel-title text-center'>\n";
                    echo "      <a data-toggle='collapse' class='collapse-link' href='#collapse$collapse_id'>Test Information</a>\n";
                    echo "    </h4>\n";
                    echo "  </div>\n";
                    echo "  <div id='collapse$collapse_id' class='panel-collapse collapse graph-panel in no-transition'>\n";
                    ?>
                    <div class="panel-body" id='zero-padding'>
                        <table class='table table-hover' id='result-table'>
                            <tbody>
                            <tr>
                                <td class="active">Test ID</td>
                                <?php printRowFields($allTestData, 'testid'); ?>
                            </tr>
                            <tr>
                                <td class="active">Title</td>
                                <?php printRowFields($allTestData, 'title'); ?>
                            </tr>
                            <tr>
                                <td class="active">Purpose</td>
                                <?php printRowFields($allTestData, 'purpose'); ?>
                            </tr>
                            <tr>
                                <td class="active">Execution Hosts</td>
                                <?php printRowFields($allTestData, 'execution'); ?>
                            </tr>
                            <tr>
                                <td class="active">Statistics Hosts</td>
                                <?php printRowFields($allTestData, 'statistics'); ?>
                            </tr>
                            <tr>
                                <td class="active">Test Priority</td>
                                <?php printRowFields($allTestData, 'priority'); ?>
                            </tr>
                            <tr>
                                <td class="active">Timeout (minutes)</td>
                                <?php printRowFields($allTestData, 'timeout'); ?>
                            </tr>
                            <tr>
                                <td class="active">CC List</td>
                                <?php printRowFields($allTestData, 'cc_list'); ?>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="panel panel-info panel-sub-main">
                <?php
                $collapse_id++;
                echo "  <div class='panel-heading collapse-heading'>\n";
                echo "    <h4 class='panel-title text-center'>\n";
                echo "      <a data-toggle='collapse' class='collapse-link' href='#collapse$collapse_id'>Run Status</a>\n";
                echo "    </h4>\n";
                echo "  </div>\n";
                echo "  <div id='collapse$collapse_id' class='panel-collapse collapse graph-panel in no-transition'>\n";
                ?>
                <div class="panel-body" id='zero-padding'>
                    <table class="table table-hover" id="result-table">
                        <tbody>
                        <tr>
                            <td class="active">Creation Time</td>
                            <?php printRowFields($allTestData, 'creation_time', true);?>
                        </tr>
                        <tr>
                            <td class="active">Last Modified</td>
                            <?php printRowFields($allTestData, 'modified', true); ?>
                        </tr>
                        <tr>
                            <td class="active">Start Time</td>
                            <?php printRowFields($allTestData, 'start_time', true); ?>
                        </tr>
                        <tr>
                            <td class='active'>End Time</td>
                            <?php printRowFields($allTestData, 'end_time', true); ?>
                        </tr>
                        <tr>
                            <td class="active">Current Status</td>
                            <?php
                            foreach ($allTestData as $curTestData) {
                                if (isset($curTestData['state'])) {
                                    echo "            <td class=\"test-data-td\">" . $curTestData['state'] . "</td>\n";
                                } else {
                                    $colorClass = stateColorClass($curTestData['end_status']);
                                    echo "            <td class=\"test-data-td $colorClass\"'>" . $curTestData['end_status'] . "</td>\n";
                                }
                            }
                            ?>
                        </tr>
                        <tr>
                            <td class="active">Status Detail</td>
                            <?php printRowFields($allTestData, 'state_detail'); ?>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="panel panel-info panel-sub-main">
            <?php
            $collapse_id++;
            echo "  <div class='panel-heading collapse-heading'>\n";
            echo "    <h4 class='panel-title text-center'>\n";
            echo "      <a data-toggle='collapse' class='collapse-link' href='#collapse$collapse_id'>Test Arguments</a>\n";
            echo "    </h4>\n";
            echo "  </div>\n";
            echo "  <div id='collapse$collapse_id' class='panel-collapse collapse graph-panel in no-transition'>\n";
            ?>
            <div class="panel-body" id='zero-padding'>
                <table class="table table-hover" id="result-table">
                    <tbody>
                    <?php
                    foreach ($frameworkData['arguments'] as $frameworkArg) {
                        echo "          <tr>\n";
                        echo "            <td class=\"active\">";
                        if ($frameworkArg['argument_description']) {
                            echo "<a href=\"#\" data-toggle=\"tooltip\" title=\"" . $frameworkArg['argument_description'] . "\">";
                            echo $frameworkArg['argument_name'] . "</a></td>\n";
                        } else {
                            echo $frameworkArg['argument_name'] . "</td>\n";
                        }
                        foreach ($allTestData as $curTestData) {
                            echo "            <td class='test-data-td'>";
                            echo $curTestData['arguments'][$frameworkArg['framework_arg_id']]['value'];
                            echo "</td>\n";
                        }
                        echo "          </tr>\n";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
</div>
</div> <!-- .content-wrapper -->
</main> <!-- .cd-main-content -->

<div class="modal fade" id="zoomModal" role="dialog">
    <div class="modal-dialog modal-full">
        <div class="modal-content">
            <div class="modal-header modal-header-blue">
                <button type='button' class='close close-blue' data-dismiss='modal'>&times;</button>
                <h4 class='modal-title h4-blue'></h4>
            </div>
            <div class='modal-body'>
                <div class='c3-graph-panel' id='zoom-body'></div>
                <div class='metric-footer'></div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-default' data-dismiss='modal'>Close</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
        buildTopNavBar('<?php echo $frameworkName; ?>', '<?php echo $testId; ?>', '<?php echo $userId; ?>');
        setDescription('Test Report');
        buildUserAccountMenu('<?php echo $userId; ?>');
        buildLeftPanel();
        buildLeftPanelTest(<?php echo $frameworkId; ?>, '<?php echo $userId; ?>');
        buildLeftPanelViews('<?php echo $testId; ?>', '<?php echo $compIds; ?>');
        buildLeftPanelFramework('<?php echo $frameworkName ?>', <?php echo $frameworkId; ?>);
        buildLeftPanelGlobal();
	<?php
          addFrameworkDropdownJS($db, $userId);
          addTestResults("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds);
          if(array_key_exists("execution",$origTestData)) {
            echo "createLabel('System Metrics')\n";
            addSystemMetrics("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds, "exec");
          }
          if(array_key_exists("statistics",$origTestData)){
            addSystemMetrics("test_data/$frameworkName/$testId/results", $origTestData["statistics"], $testId, $compIds, "stat");
          }
          addLogs("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds);
        ?>
        loadNavigationBar();
        $('#zoomModal').on('shown.bs.modal', function() {
            $('#zoom-body').data('c3-chart').flush();
        });
        flushAllCharts();
    });
</script>

<?php require_once('lib/footer.php'); ?>

