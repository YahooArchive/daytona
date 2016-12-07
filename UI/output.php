<?php
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
//error_reporting(-1);

require('lib/auth.php');

$allTestData = array();
$testId = getParam('testid');
$filename = getParam('filename');
$columns = getParam('columns');
if (!$columns){
    $columns_val = "";
}else{
    $columns_val = implode(",",$columns);
}
$starttime = getParam('start');
$endtime = getParam('end');
$outputFormat = getParam('format');
if (!$testId) {
    diePrint("No test ID passed in");
}
if (!is_numeric($testId)) {
    diePrint("testid is not valid.");
}
/*if (!file_exists($outputPath)) {
  diePrint("$outputPath is not a valid file");
}*/
if (!$outputFormat) {
    $outputFormat = "plain";
}
$origTestData = getTestById($db, $testId, true);
if (!$origTestData) {
    diePrint("Could not find test ID: $testId");
}
$frameworkId = $origTestData['frameworkid'];
$frameworkData = getFrameworkById($db, $frameworkId, true);
$frameworkName = $origTestData['frameworkname'];
$allTestData[] = $origTestData;

$compIds = getParam('compids');
if ($compIds && !preg_match('/^\d+(,\d+)*$/', $compIds)) {
    diePrint("compids is not valid.");
}
if ($compIds) {
    foreach (split(',', $compIds) as $compId) {
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
$resultsData = getTestResults($db, $testId);

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

$s_compids_orig = "";
$s_compids_str = "";
$s_compids = array();
if (!empty($_GET["compids"])) {
    $s_compids = explode(",", $_GET["compids"]);
    $s_compids_orig = $_GET["compids"];
    $s_compids_str = $s_compids_orig;
}
$s_testid = "none";
if (isset($_GET["testid"])) {
    $s_testid = $_GET["testid"];
    array_unshift($s_compids, $s_testid);
    if(!empty($s_compids_str)){
      $s_compids_str = $s_testid . "," . $s_compids_str;
    }else{
      $s_compids_str = $s_testid;
    }
}

function formatFilePath($path, $mapping)
{
    $pattern = '/%(STAT|EXEC|RESERVED){1}HOST,([0-9]+)%(.*)/';
    if (preg_match($pattern, $path, $output_array)) {
        switch ($output_array[1]) {
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
    } else {
        return $mapping["execution"][0] . "/" . $path;
    }
}

$extension = pathinfo($filename, PATHINFO_EXTENSION);

$pageTitle = "Test Report ($testId)";
$test_info_sql = "SELECT frameworkname,TestInputData.title,TestInputData.purpose,exechostname,
                  stathostname,priority,timeout,cc_list,
                  TestInputData.creation_time,modified,start_time,end_time FROM TestInputData
                  JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE testid=$s_testid";
$test_info_result = $conn->query($test_info_sql);
$test_info_data = $test_info_result->fetch_assoc();

$test_hosts_sql = "SELECT testid,hostassociationid,hostname,name FROM HostAssociation JOIN HostAssociationType
                   USING(hostassociationtypeid) WHERE testid IN ($s_compids_str) ORDER BY hostassociationid";
$test_hosts_result = $conn->query($test_hosts_sql);
while ($row = $test_hosts_result->fetch_assoc()) {
    if (!isset($test_info_data[$row["testid"]][$row["name"]])) {
        $test_info_data[$row["testid"]][$row["name"]] = array();
    }
    array_push($test_info_data[$row["testid"]][$row["name"]], $row["hostname"]);
}
$err_threshold = sizeof($s_compids);
$marked = 0;
$full_paths = "";
foreach ($s_compids as $l_id) {
    if ($marked) {
        $full_paths .= ",";
    }
    $marked = 1;
    $report_path = "test_data/" . $test_info_data["frameworkname"] . "/$l_id/results/" .
        formatFilePath($filename, $test_info_data[$l_id]);
    $report_path_file = strpos($report_path, ":") ? substr($report_path, 0, strpos($report_path, ":")) : $report_path;
    if (!file_exists($report_path_file)) {
        $err_threshold--;
    }
    $full_paths .= $report_path;
}
include_once('lib/header.php');
?>
<script src="http://d3js.org/d3.v3.min.js" charset="utf-8"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.js" charset="utf-8"></script>
<script src="js/bootstrap-sortable.js"></script>
<script src="js/test_report.js"></script>
<script src="js/output.js"></script>


<div class="content-wrapper" id="page-content">
    <div id="main-panel-alt-top">
        <?php
        echo "  <div class='col-md-9' id='action-buttons-div-left'>\n";
        echo "    <form class='zero-margin form-inline' role='form' onSubmit='return checkTestCount()'>\n";
        echo "      <div class='input-group' style='z-index:0'>\n";
        echo "        <input type='text' class='form-control h-30' id='compids' name='compids' value='$compIds' placeholder='Example: 100,102,105'>\n";
        echo "        <input type='hidden' name='testid' id='testid' value='$testId'>\n";
        echo "        <input type='hidden' name='filename' value='$filename'>\n";
        echo "        <input type='hidden' name='format' value='$outputFormat'>\n";
        echo "        <input type='hidden' name='start' value='$starttime'>\n";
        echo "        <input type='hidden' name='end' value='$endtime'>\n";
        for($i = 0;$i<count($columns);$i++){
            echo "<input type='hidden' name='columns[]' value=$columns[$i] $checked>\n";
        }
        echo "        <span class='input-group-btn'>\n";
        echo "        <button type='submit' class='btn btn-primary btn-action'>\n";
        echo "          Compare\n";
        echo "        </button>\n";
        echo "       </span>\n";
        echo "      </div>\n";
        echo "    </form>\n";
        echo "  </div>\n";
        ?>
        <div class="col-md-3 col-xs-12 action-buttons-alt" id="action-buttons-div">
          <select class="form-control" onchange="switchFileViewerFormat(this)">
            <option <?php echo $outputFormat == "graph" ? "selected" : ""; ?>>Graph</option>
            <option <?php echo $outputFormat == "table" ? "selected" : ""; ?>>Table</option>
            <option <?php echo $outputFormat == "plain" ? "selected" : ""; ?>>Plain</option>
          </select>
        </div>
    </div>
    <div class="col-xs-12" id="content-div">
        <div id="main-panel-alt">
            <?php
	    $file_arr = explode(',',$full_paths);
	    $file_arr_final = array_reverse($file_arr);
	    $file_content = array_map("str_getcsv", file($file_arr_final[0],FILE_SKIP_EMPTY_LINES));
	    $file_header = array_shift($file_content);
            if (($outputFormat == "graph") && (strpos($extension, 'plt') !== false)) {
                echo "<div class='panel panel-info panel-sub-main'>";
                echo "<div class='panel-heading'>
			<button type='button' class='btn-sm' id='filter-toggle' data-toggle='collapse' data-target='#collapse1'>
			<span class='glyphicon glyphicon glyphicon-plus'></span>
			</button>
			<p style='margin:0px 0px 0px 45px;padding-top:4px;'>Filters</p>
		      </div>";
                echo "<div id='collapse1' class='panel-collapse collapse'>";
                echo "<div class='panel-body' style='padding-left:20px'>";
                echo "<form class='form-horizontal zero-margin' role='form' onSubmit='return verifyOutputFilters()'>";
                echo "<fieldset id='form1'>";
                echo "<div class='form-group'>";
                echo "<label>Select column for graph display</label>";
                echo "<br>";
		if($columns)
		array_unshift($columns, 'dummy');
		for($i = 1;$i<count($file_header);$i++){
		    $checked = '';
		    if ($columns && array_search(strval($i),$columns)){
			$checked = 'checked';
		    }
		    echo "<div class='col-sm-2 text_div'>";
                    echo "<input type='checkbox' name='columns[]' value=$i $checked> $file_header[$i]";
                    echo "</div>";
		}
		if($columns)
		array_shift($columns);
		echo "</div>";
		echo "<a rel='form1' href='#selectall'>Select All</a> | ";
                echo "<a rel='form1' href='#clearall'>Clear All</a> | ";
                echo "<a rel='form1' href='#invertselection'>Select Inverse</a>";
		echo "<div class='form-group' style='padding-top:20px;'>";
                echo "<label>Select Time Range</label> <span style='font-size:0.8em;font-style: italic;'>(Note : Enter '01' as 'dd' for Day 1)</span>";
		echo "<br>";
		echo "<div class='col-sm-2 text_div'>";
		echo "<input class='form-control' type='text' id='start' placeholder='dd:hh:mm:ss' value='$starttime' name='start'>";
		echo "</div>";
            	echo "<label class='col-sm-1 control-label' style='width:2%'>to</label>";
		echo "<div class='col-sm-2 text_div'>";
                echo "<input class='form-control' type='text' id='end' placeholder='dd:hh:mm:ss' value='$endtime' name='end'>";
		echo "<input type='hidden' name='compids' value='$compIds'>\n";
                echo "<input type='hidden' name='testid' value='$testId'>\n";
                echo "<input type='hidden' name='filename' value='$filename'>\n";
                echo "<input type='hidden' name='format' value='$outputFormat'>\n";
                echo "</div>";
                echo "</div>";
		echo "<a rel='form1' href='#cleartime'>Clear Time</a>";
                echo "<div class='form-group' style='padding-top:20px;'>";
                echo "<button type='submit' class='btn btn-primary btn-search-submit'>Submit</button>";
		echo "<br><br>";
		echo "<a rel='form1' href='#removeall'>Remove All Filters</a>";
                echo "</div>";
                echo "</fieldset>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }
            $act_file = pathinfo($filename, PATHINFO_BASENAME);
            if ($outputFormat == "graph") {
                echo "<div id='output-panel'></div>";
                echo "  <script> buildGraphView('$full_paths', '$s_compids_str','$starttime','$endtime','$columns_val','$act_file'); </script>\n";
            } else if ($outputFormat == "table") {
                echo "<div id='output-panel'>";
                echo "<div class='panel panel-info'>\n";
                echo "<div class='panel-heading'>\n";
                echo "<h3 class='panel-title centered'>$act_file</h3>\n";
                echo "</div>\n";
                echo "<div class='panel-body zero-padding' id='output-table-display'></div>\n";
                echo "</div>\n";
                echo "</div>";
                $file_arr = explode(',', $full_paths);
                $file_arr = array_reverse($file_arr);
                if (strpos($extension, 'plt') !== false) {
                    echo "    <script>buildFileToTableView('$file_arr[0]', '$s_compids_str','output-table-display');</script>\n";
                } else if((strpos($extension, 'csv') !== false)) {
                      $csv_col_count = getCsvColumnCount($full_paths);
                      if ($csv_col_count == 2){
                         $csv_compare = generateCompareCsv($full_paths,$s_compids_str);
                         $csv_compare_json = json_encode($csv_compare);
                         echo "    <script>buildJsonToTableView('$csv_compare_json', 'output-table-display');</script>\n";
                      }else{
                         echo "    <script>buildFileToTableView('$full_paths', '$s_compids_str', 'output-table-display');</script>\n";
                      }
                }else {
                    echo "    <script>buildTextCompareView('$full_paths', '$s_compids_str','output-table-display');</script>\n";
                }
            } else {
                echo "<div id='output-panel'>";
                echo "<div class='panel panel-info'>\n";
                echo "<div class='panel-heading'>\n";
                echo "<h3 class='panel-title centered'>$act_file</h3>\n";
                echo "</div>\n";
                echo "<div class='panel-body zero-padding' id='output-table-display'></div>\n";
                echo "</div>\n";
                echo "</div>";

                echo "    <script>buildTextCompareView('$full_paths', '$s_compids_str','output-table-display');</script>\n";
            }
            ?>
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

<script>
    $(document).ready(function () {
	 $("a[href='#selectall']").click( function() {
            $('input[type="checkbox"]').filter(function() {
    	    return !this.disabled;
  	    }).prop('checked', true);
        });
        $("a[href='#clearall']").click( function() {
	    $('input[type="checkbox"]').filter(function() {
            return !this.disabled;
  	    }).prop('checked', false);
        });
        $("a[href='#invertselection']").click( function() {
            $('input[type="checkbox"]').each( function() {
		$(this).prop('checked', !$(this).is(':checked'));
            });
        });
	$("a[href='#cleartime']").click( function() {
            $('#start').val("");
	    $('#end').val("");
        });
	$("a[href='#removeall']").click( function() {
	    $('input[type="checkbox"]').filter(function() {
            return !this.disabled;
            }).prop('checked', false);
            $('#start').val("");
            $('#end').val("");
        });
	$("#collapse1").on("hide.bs.collapse", function(){
    	    $("#filter-toggle").html('<span class="glyphicon glyphicon glyphicon-plus"></span>');
        });
  	$("#collapse1").on("show.bs.collapse", function(){
	    $("#filter-toggle").html('<span class="glyphicon glyphicon glyphicon-minus"></span>');
 	});
        $("#zoomModal").on('shown.bs.modal', function () {
            $("#zoom-body").data("c3-chart").flush();
        });
        buildTopNavBar('<?php echo $frameworkName; ?>', '<?php echo $testId; ?>', '<?php echo $userId; ?>');
        setDescription('Test Information');
        buildUserAccountMenu('<?php echo $userId; ?>');
        buildLeftPanel();
        buildLeftPanelTest(<?php echo $frameworkId; ?>, '<?php echo $userId; ?>');
        buildLeftPanelViews('<?php echo $testId; ?>', '<?php echo $compIds; ?>');
        buildLeftPanelFramework('<?php echo $frameworkName; ?>', <?php echo $frameworkId; ?>);
        buildLeftPanelGlobal();
        <?php addFrameworkDropdownJS($db, $userId); ?>
        <?php addTestResults("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds); ?>
        <?php addSystemMetrics("test_data/$frameworkName/$testId/results", $origTestData["statistics"], $testId, $compIds); ?>
        <?php addLogs("test_data/$frameworkName/$testId/results", $origTestData["execution"], $testId, $compIds); ?>
        loadNavigationBar();
        flushAllCharts();
    });
</script>

<?php include_once('lib/footer.php'); ?>
<?php
$conn->close();
?>
