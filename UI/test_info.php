<?php
// ini_set('display_errors',1);
// ini_set('display_startup_errors',1);
// error_reporting(-1);

require('lib/auth.php');

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
    diePrint("Could not find test ID: $testId");
}
$frameworkId = $origTestData['frameworkid'];
$frameworkData = getFrameworkById($db, $frameworkId, true);
$frameworkName = $origTestData['frameworkname'];
$allTestData[] = $origTestData;
$testRunning = checkTestRunning($db, $testId);

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
        if ($testData['frameworkname'] != $frameworkName) {
            diePrint("Cannot compare test IDs from different frameworks.");
        }
        $allTestData[] = $testData;
    }
}

function convertTime($timeStr) {
    if (! $timeStr) {
        return 'N/A';
    }
    $time = strtotime("$timeStr UTC");
    return date("m/d/Y g:i:s A", $time);
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

$pageTitle = "Test Info";
include_once('lib/header.php');
?>
<div class="content-wrapper" id="page-content">
    <div id="main-panel-alt-top">
        <div class="col-md-6" id="action-buttons-div-left">
            <form class="zero-margin form-inline" role="form" onSubmit="return checkTestCount()">
                <!--<div class="form-group">-->
                <div class="input-group" style="z-index:0">
                    <input type="text" class="form-control h-30" name="compids" id="compids" value="<?php echo $compIds; ?>" placeholder="Example: 100,102,105">
                    <input type="hidden" name="testid" id="testid" value="<?php echo $testId; ?>">
                    <span class="input-group-btn">~
                <button type="submit" class="btn btn-primary btn-action">
                  Compare
                </button>
        >-</span>
                </div>
            </form>
        </div>
        <div class="col-md-6 action-buttons-alt" id="action-buttons-div">
            <?php
            $ownerAuthDisable = $origTestData["username"] == $userId ||
            $userIsAdmin ? "" : "disabled";
	    $testRunning = $testRunning ? "disabled" : "";
            $disable = "";
            if ($testRunning === "disabled"){
                $disable = "disabled";
            }elseif ($ownerAuthDisable === "disabled"){
                $disable = "disabled";
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
            <button type="button" class="btn btn-success btn-action" <?php echo "onclick='runTest($testId)' $disable"; ?>>
                Run
            </button>
        </div>
    </div>

    <div class="col-xs-12" id="content-div">

        <div id="main-panel-alt">

            <div class="panel panel-info panel-sub-main">
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
                <div class="panel-heading">Run Status</div>
                <div class="panel-body" id='zero-padding'>
                    <table class="table table-hover" id="result-table">
                        <tbody>
                        <tr>
                            <td class="active">Creation Time</td>
                            <?php printRowFields($allTestData, 'creation_time', true); ?>
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

            <div class="panel panel-info panel-sub-main">
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
        <div class="panel panel-info panel-sub-main">
            <div class="panel-heading">
                Strace Configuration
            </div>
            <div class="panel-body" id='zero-padding'>
                <?php
                if ($allTestData[0]['strace']){
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
                }else{
                    echo "<h5 class='padding-left'>No STRACE configuration available</h5>";
                }
                ?>
            </div>
        </div>
        <div class="panel panel-info panel-sub-main">
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
                            if ($allTestData[0]['perf_process']){
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
</div> <!-- .content-wrapper -->
</main> <!-- .cd-main-content -->

<script type="text/javascript" src="js/output.js"></script>
<script type="text/javascript">
    $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip();
        buildTopNavBar('<?php echo $frameworkName; ?>', '<?php echo $testId; ?>', '<?php echo $userId; ?>');
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
    });
</script>
<?php include_once('lib/footer.php'); ?>
