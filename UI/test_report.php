<?php
/**
 * This is test report page which provide extensive view for checking test results. By default it contains basic test
 * information as well it contains log file display as defined in framework definition. Basically this report provide
 * user a flexibility to user to view multiple log files in a single view with all basic test information.
 */

require('lib/auth.php');
include 'process_data.php';

$allTestData = array();
$testId = getParam('testid');
if (!$testId) {
    diePrint("No test ID passed in", "Error");
}
if (!is_numeric($testId)) {
    diePrint("testid is not valid.", "Error");
}
// Fetching test details of base test
$origTestData = getTestById($db, $testId, true);
if (!$origTestData) {
    diePrint("Could not find test id: $testId", "Error");
}
$frameworkId = $origTestData['frameworkid'];
$frameworkName = $origTestData['frameworkname'];
$frameworkData = getFrameworkById($db, $frameworkId, true);
$allTestData[] = $origTestData;

// Fetching list of comparison test if any
$compIds = getParam('compids');
if ($compIds && ! preg_match('/^\d+(,\d+)*$/', $compIds)) {
    diePrint("compids is not valid.", "Error");
}
if ($compIds) {
    foreach (explode(',', $compIds) as $compId) {
        $testData = getTestById($db, $compId, true);
        if (!$testData) {
            diePrint("Could not find compare test ID: $compId", "Error");
        }
        if ($testData['frameworkid'] != $frameworkId) {
            diePrint("Cannot compare test IDs from different frameworks.", "Error");
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

// Format path of log file based on execution/statistic host information and log file name.
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

// Prints table row html code for displaying array values indexed by $field in a HTML table
function printRowFields($allTestData, $field) {
    foreach ($allTestData as $curTestData) {
        if (isset($curTestData[$field])) {
            $value = $curTestData[$field];
            if(is_array($value)) {
                $value = implode(",", $value);
            }
            echo "            <td class=\"test-data-td\">" . nl2br($value) . "</td>\n";
        } else {
            echo "            <td class=\"test-data-td\"></td>\n";
        }
    }
}

// CSS color class scheme based on test status
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

include_once('lib/header.php');
?>
<script src="https://d3js.org/d3.v3.min.js" charset="utf-8"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.js" charset="utf-8"></script>
<script src="js/bootstrap-sortable.js"></script>
<script src="js/test_report.js"></script>
<script src="js/output.js"></script>
</head>
<?php
try{
    // Fetching test report configuration from framework definition
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();
    $query = "SELECT filename,TestResultFile.title FROM TestResultFile
               JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE frameworkname=:frameworkname ORDER BY filename_order";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkname',$frameworkName,PDO::PARAM_STR);
    $stmt->execute();
    $report_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $report_arr = array();
    $y = 1;
    foreach($report_result as $row){
        $report_arr[$y] = array();
        array_push($report_arr[$y], array(str_replace("rrd","csv",$row["filename"]), $row["title"]));
        ++$y;
    }

    // Creating host array for saving hosts information for all tests
    $host_array = array();
    foreach ($allTestData as $test) {
        $temp = array();
        $temp["execution"] = $test["execution"];
        if (array_key_exists("statistics",$temp)){
            $temp["statistics"] = $test["statistics"];
        }
        $host_array[$test["testid"]] = $temp;
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
                // Creating separate panel for each test report configuration defined in framework definition
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
                    // Based on file extension, using different file rendering options
                    if (strpos($extension, 'plt') !== false){
                        $marked = 0;
                        $full_paths = "";
                        foreach($s_compids as $l_id) {
                            if($marked) {
                                $full_paths .= ",";
                            }
                            $marked = 1;
                            $report_path = "test_data/" . $frameworkName . "/$l_id/results/" . formatFilePath($filename, $host_array[$l_id]);
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
                            $report_path = "test_data/" . $frameworkName . "/$l_id/results/" .
                                formatFilePath($report_identifier[0], $host_array[$l_id]);
                            $report_path_file = strpos($report_path, ":") ? substr($report_path, 0, strpos($report_path, ":")) : $report_path;
                            if(!file_exists($report_path_file)) {
                                $err_threshold--;
                            }
                            $full_paths .= $report_path;
                        }
                        $div_id = "output-panel" . $collapse_id;
                        $csv_col_count = getCsvColumnCount($full_paths);
                        if ($csv_col_count == 2){
                            // If CSV has 2 column, we consider it as key-value pair file hence for comparison we
                            // match keys from both the files
                            $csv_compare = generateCompareCsv($full_paths,$s_compids_str);
                            $csv_compare_json = json_encode($csv_compare);
                            echo "    <div class='fixed-height' id=$div_id></div>\n";
                            echo "    <script>buildJsonToTableView('$csv_compare_json', '$div_id');</script>\n";
                            echo "  </div>\n";
                        }else{
                            // Multi-column CSV is side by side tabular file rendering for comparison
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
                    // Panel for displaying basic test information, same as test_info page
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
                                <td class="active">Owner</td>
                                <?php printRowFields($allTestData, 'username'); ?>
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
                                <td class="active">Email List</td>
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
                            <?php printRowFields($allTestData, 'creation_time');?>
                        </tr>
                        <tr>
                            <td class="active">Last Modified</td>
                            <?php printRowFields($allTestData, 'modified'); ?>
                        </tr>
                        <tr>
                            <td class="active">Start Time</td>
                            <?php printRowFields($allTestData, 'start_time'); ?>
                        </tr>
                        <tr>
                            <td class='active'>End Time</td>
                            <?php printRowFields($allTestData, 'end_time'); ?>
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
        buildTopNavBar('<?php echo $frameworkName; ?>', '<?php echo $testId; ?>');
        setDescription('Test Report');
        buildUserAccountMenu('<?php echo $userId; ?>');
        buildLeftPanel();
        buildLeftPanelTest(<?php echo $frameworkId; ?>, '<?php echo $userId; ?>');
        buildLeftPanelViews('<?php echo $testId; ?>', '<?php echo $compIds; ?>');
        buildLeftPanelFramework('<?php echo $frameworkName ?>', <?php echo $frameworkId; ?>);
        buildLeftPanelGlobal();
        <?php
        addFrameworkDropdownJS($db, $userId);
        addTestResults("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds, $origTestData["execution_script_location"]);
        if(array_key_exists("execution",$origTestData)) {
            echo "createLabel('System Metrics')\n";
            addSystemMetrics("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds, "exec");
        }
        if(array_key_exists("statistics",$origTestData)){
            addSystemMetrics("test_data/$frameworkName/$testId/results", $origTestData["statistics"], $testId, $compIds, "stat");
        }
        $hosts['EXECHOST'] = $origTestData["execution"];
        if (array_key_exists("statistics",$origTestData)) {
            $hosts['STATHOST'] = $origTestData["statistics"];
        }
        addLogs("test_data/$frameworkName/$testId/results", $hosts, $testId, $compIds);
        ?>
        loadNavigationBar();
        $('#zoomModal').on('shown.bs.modal', function() {
            $('#zoom-body').data('c3-chart').flush();
        });
        flushAllCharts();
    });
</script>

<?php require_once('lib/footer.php'); ?>

