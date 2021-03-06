<?php
/**
 * This page display basic test information of the test. User can compare multiple test on this page. UI will display
 * information from all the tests in a single view.
 */

require('lib/auth.php');

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
    diePrint("Could not find test ID: $testId", "Error");
}
$frameworkId = $origTestData['frameworkid'];
$frameworkData = getFrameworkById($db, $frameworkId, true);
$frameworkName = $origTestData['frameworkname'];
$allTestData[] = $origTestData;
$testRunning = checkTestRunning($db, $testId);

// Fetching list of comparison test if any
$compIds = getParam('compids');
if ($compIds && !preg_match('/^\d+(,\d+)*$/', $compIds)) {
    diePrint("compids is not valid.", "Error");
}
if ($compIds) {
    foreach (explode(',', $compIds) as $compId) {
        $testData = getTestById($db, $compId, true);
        if (!$testData) {
            diePrint("Could not find compare test ID: $compId", "Error");
        }
        if ($testData['frameworkname'] != $frameworkName) {
            diePrint("Cannot compare test IDs from different frameworks.", "Error");
        }
        $allTestData[] = $testData;
    }
}

// CSS color class scheme based on test status
function stateColorClass($status)
{
    switch ($status) {
        case 'finished clean':
            return 'emphasize-green';
        case 'aborted':
            return 'emphasize-yellow';
        default:
            return 'emphasize-red';
    }
}

// Prints table row html code for displaying array values indexed by $field in a HTML table
function printRowFields($allTestData, $field)
{
    foreach ($allTestData as $curTestData) {
        if (isset($curTestData[$field])) {
            $value = $curTestData[$field];
            if (is_array($value)) {
                $value = implode(",", $value);
            }
            echo "            <td class=\"test-data-td\">" . nl2br($value) . "</td>\n";
        } else {
            echo "            <td class=\"test-data-td\"></td>\n";
        }
    }
}

$pageTitle = "Test Info";
include_once('lib/header.php');
?>
<div class="content-wrapper" id="page-content">
    <div id="main-panel-alt-top">
        <!-- Top panel for displaying comparison text field and other action buttons like edit, clone, run test-->
        <div class="col-md-6" id="action-buttons-div-left">
            <form class="zero-margin form-inline" role="form" onSubmit="return checkTestCount()">
                <!--<div class="form-group">-->
                <div class="input-group" style="z-index:0">
                    <input type="text" class="form-control h-30" name="compids" id="compids"
                           value="<?php echo $compIds; ?>" placeholder="Example: 100,102,105">
                    <input type="hidden" name="testid" id="testid" value="<?php echo $testId; ?>">
                    <span class="input-group-btn">~
                        <button type="submit" class="btn btn-primary btn-action">
                          Compare
                        </button>
                    </span>
                </div>
            </form>
        </div>
        <div class="col-md-6 action-buttons-alt" id="action-buttons-div">
            <?php
            $disable_run = "";
            $ownerAuthDisable = $origTestData["username"] == $userId ||
            $userIsAdmin ? "" : "disabled";
            $testRunning = $testRunning ? "disabled" : "";
            $disable = "";
            if ($testRunning === "disabled") {
                $disable = "disabled";
            } elseif ($ownerAuthDisable === "disabled") {
                $disable = "disabled";
            } elseif ($origTestData["end_status"] === "imported") {
                $disable_run = "disabled";
            }
            echo "    <button onclick=\"window.location='create_edit_test.php?action=edit&testid=$testId'\" class=\"btn btn-default btn-action\" $disable>\n";
            ?>
            Edit
            </button>
            <?php
            echo "    <a href=\"create_edit_test.php?action=clone&testid=$testId\" class=\"btn btn-default btn-action\">\n";
            ?>
            Clone
            </a>
            <button type="button"
                    class="btn btn-success btn-action" <?php echo "onclick='runTest($testId)' $disable $disable_run"; ?>>
                Run
            </button>
        </div>
    </div>

    <div class="col-xs-12" id="content-div">
        <div id="main-panel-alt">
            <div class="panel panel-info panel-sub-main">

                <!-- This panel displays basic test information-->
                <div class="panel-heading">Test Information</div>
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
                            <td class="active">Framework</td>
                            <?php printRowFields($allTestData, 'frameworkname'); ?>
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
                            <td class="active">Timeout (seconds)</td>
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

            <div class="panel panel-info panel-sub-main">
                <!-- This panel displays test run information-->
                <div class="panel-heading">Run Status</div>
                <div class="panel-body" id='zero-padding'>
                    <table class="table table-hover" id="result-table">
                        <tbody>
                        <tr>
                            <td class="active">Creation Time</td>
                            <?php printRowFields($allTestData, 'creation_time'); ?>
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

            <div class="panel panel-info panel-sub-main">
                <!-- This panel displays test argument information-->
                <div class="panel-heading">Test Arguments</div>
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
                                if (array_key_exists($frameworkArg['framework_arg_id'], $curTestData['arguments'])) {
                                    echo $curTestData['arguments'][$frameworkArg['framework_arg_id']]['value'];
                                } else {
                                    echo "";
                                }
                                echo "</td>\n";
                            }
                            echo "          </tr>\n";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            $print_imported_args = false;
            foreach ($allTestData as $curTestData) {
                if (array_key_exists("imported_test_arg", $curTestData)) {
                    $print_imported_args = true;
                    break;
                }
            }

	    // Merging imported test arguments from different tests, this is to consolidate display of test arguments
            // having same set of arguments
            $imported_arg_map = array();
            if ($print_imported_args) {
                foreach ($allTestData as $curTestData) {
                    if (array_key_exists("imported_test_arg", $curTestData)) {
                        foreach ($curTestData['imported_test_arg'] as $row) {
                            if (array_key_exists($row['arg_name'], $imported_arg_map)) {
                                $imported_arg_map[$row['arg_name']][$curTestData['testid']] = $row['arg_value'];
                            }else {
                                $imported_arg_map[$row['arg_name']] = array();
                                $imported_arg_map[$row['arg_name']][$curTestData['testid']] = $row['arg_value'];
                            }
                        }
                    }
                }
                echo "    <div class='panel panel-info panel-sub-main'>\n";
                echo "        <div class='panel-heading'>Imported Test Arguments</div>\n";
                echo "        <div class='panel-body' id='zero-padding'>\n";
                echo "            <table class='table table-hover' id='result-table'>\n";
                echo "                <tbody>\n";
		// One row for each argument and if that argument value exist for a particular test then it will be
                // displayed in respective column
                foreach ($imported_arg_map as $imported_arg=>$imported_arg_array) {
                    echo "          <tr>\n";
                    echo "            <td class=\"active\">$imported_arg</td>\n";
                    for ($j = 0; $j < count($allTestData); $j++) {
                        $curr_testid = $allTestData[$j];
                        if (array_key_exists($curr_testid['testid'], $imported_arg_array)) {
                            $arg_value = $imported_arg_array[$curr_testid['testid']];
                            echo "            <td class='test-data-td'>$arg_value</td>\n";
                        }else {
                            echo "            <td class='test-data-td'></td>\n";
                        }
                    }
                }
                echo "                </tbody>\n";
                echo "            </table>\n";
                echo "        </div>\n";
                echo "    </div>\n";
            }
            ?>

            <div class="panel panel-info panel-sub-main">
                <!-- This panel displays strace configuration if user has configured it-->
                <div class="panel-heading">
                    Strace Configuration
                </div>
                <div class="panel-body" id='zero-padding'>
                    <?php
		    $print_strace_config = false;
                    foreach ($allTestData as $curTestData) {
                        if ($curTestData['strace']) {
                            $print_strace_config = true;
                            break;
                        }
                    }
                    if ($print_strace_config) {
                        echo "<table class='table table-hover form-table'>";
                        echo "<tbody>";
                        echo "<tr>";
                        echo "<td class=\"active\">Delay</td>";
                        printRowFields($allTestData, 'strace_delay');
                        echo "</tr>";
                        echo "<tr>";
                        echo "<td class=\"active\">Duration</td>";
                        printRowFields($allTestData, 'strace_duration');
                        echo "</tr>";
                        echo "<tr>";
                        echo "<td class=\"active\">Process Name</td>";
                        printRowFields($allTestData, 'strace_process');
                        echo "</tr>";
                        echo "</tbody>";
                        echo "</table>";
                    } else {
                        echo "<h5 class='padding-left'>No STRACE configuration available</h5>";
                    }
                    ?>
                </div>
            </div>
            <div class="panel panel-info panel-sub-main">
                <!-- This panel displays perf configuration -->
                <div class="panel-heading">
                    PERF Configuration
                </div>
                <div class="panel-body" id='zero-padding'>
                    <table class='table table-hover form-table'>
                        <tbody>
                        <tr>
                            <td class="active">Delay</td>
                            <?php printRowFields($allTestData, 'perf_delay'); ?>
                        </tr>
                        <tr>
                            <td class="active">Duration</td>
                            <?php printRowFields($allTestData, 'perf_duration'); ?>
                        </tr>
                        <?php
                        if ($allTestData[0]['perf_process']) {
                            echo "<tr>";
                            echo "<td class=\"active\">Process Name</td>";
                            printRowFields($allTestData, 'perf_process');
                            echo "</tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div> <!-- .content-wrapper -->
</main> <!-- .cd-main-content -->

<script type="text/javascript" src="js/output.js"></script>
<script type="text/javascript">
    $(document).ready(function () {
        $('[data-toggle="tooltip"]').tooltip();
        buildTopNavBar('<?php echo $frameworkName; ?>', '<?php echo $testId; ?>');
        setDescription('Test Information');
        buildUserAccountMenu('<?php echo $userId; ?>');
        buildLeftPanel();
        buildLeftPanelTest(<?php echo $frameworkId; ?>, '<?php echo $userId; ?>');
        buildLeftPanelViews('<?php echo $testId; ?>', '<?php echo $compIds; ?>');
        buildLeftPanelFramework('<?php echo $frameworkName; ?>', <?php echo $frameworkId; ?>);
        buildLeftPanelGlobal();
        <?php
        addFrameworkDropdownJS($db, $userId);
        addTestResults("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds, $origTestData["execution_script_location"]);
        if (array_key_exists("execution", $origTestData)) {
            echo "createLabel('System Metrics')\n";
            addSystemMetrics("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds, "exec");
        }
        if (array_key_exists("statistics", $origTestData)) {
            addSystemMetrics("test_data/$frameworkName/$testId/results", $origTestData["statistics"], $testId, $compIds, "stat");
        }
        $hosts['EXECHOST'] = $origTestData["execution"];
        if (array_key_exists("statistics", $origTestData)) {
            $hosts['STATHOST'] = $origTestData["statistics"];
        }
        addLogs("test_data/$frameworkName/$testId/results", $hosts, $testId, $compIds);
        ?>
        loadNavigationBar();
    });
</script>
<?php include_once('lib/footer.php'); ?>

