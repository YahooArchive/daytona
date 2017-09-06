<?php
/**
 *
 * This file provides import/export framework feature to Daytona. User can export all the test DB data and test logs
 * files as a zip file. User can upload this zip file on any other Daytona instance in order to import exported test
 * DB data and log files into some existing framework on new Daytona instance
 *
 */

require('lib/auth.php');
$pageTitle = "Import Export Framework";
$file = null;
$frameworks_arr = Array();

$accountInfo = getUserAccount($db, $userId);
unset($success);

// Getting list of frameworks for which logged in user is owner or fetching all framework if user is admin
try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();
    if ($accountInfo->is_admin){
        $query = "SELECT DISTINCT frameworkname from ApplicationFrameworkMetadata";
    }else{
        $query = "SELECT DISTINCT frameworkname from ApplicationFrameworkMetadata WHERE frameworkowner='$userId'";
    }
    $stmt = $db->prepare($query);
    $stmt->execute();
    $framework_row = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $frameworks_arr = array();
    foreach($framework_row as $row){
        array_push($frameworks_arr, $row["frameworkname"]);
    }
}catch (PDOException $e){
    $db->rollBack();
    diePrint("MySQL error: " . $e->getMessage(), "Error !!");
}

$action_type = getParam('action-type', 'POST');
$framework = getParam('framework', 'POST');
if (isset($_FILES["framework_tar"])){
    $file = $_FILES["framework_tar"];
}

if (isset($action_type)){
    if ($action_type == 'export'){
        if (!isset($framework)){
            diePrint("No framework details provided", "Form Error");
        }elseif (!in_array($framework,$frameworks_arr)){
            diePrint("Invalid framework detail provided or you are not the framework owner", "Error");
        }
        export_framework($framework);
    } elseif ($action_type == 'import') {
	if (!isset($framework)) {
            diePrint("Framework not selected", "Form Error");
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            diePrint("Error in file upload : " . "Error code { " . $file['error'] ." }", "Form Error");
        } elseif (!in_array($framework,$frameworks_arr)){
            diePrint("Invalid framework detail provided or you are not the framework owner", "Error");
        } elseif ($file['type'] !== "application/zip") {
            diePrint("Only upload zip file", "Form Error");
        }
        import_framework($framework, $file);
    } else {
        diePrint("Invalid form submission", "Form Error");
    }
}

/**
 * This is export framework function. It dumps all DB data associated with framework selected by the user
 *
 * @param $framework_name - Name of the framework user want to export
 */

function export_framework($framework_name) {
    try{
        global $db;

        // Get Framework ID
        $query = "SELECT frameworkid from ApplicationFrameworkMetadata WHERE frameworkname=:frameworkname";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':frameworkname', $framework_name, PDO::PARAM_STR);
        $stmt->execute();
        $framework_id = $stmt->fetch(PDO::FETCH_ASSOC)['frameworkid'];

        // Get Test List associated with this framework ID
        $query = "SELECT * from TestInputData WHERE frameworkid=:frameworkid";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':frameworkid', $framework_id, PDO::PARAM_INT);
        $stmt->execute();
        $test_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = count($test_list);
        for ($i = 0; $i < $count; $i++) {
            // Get host details for each test
            $query = "SELECT name, hostname FROM HostAssociation JOIN HostAssociationType USING(hostassociationtypeid) WHERE testid = :testid";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':testid', $test_list[$i]['testid'], PDO::PARAM_INT);
            $stmt->execute();

            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if(!isset($test_list[$i][$row['name']])) {
                    $test_list[$i][$row['name']] = array();
                }
                array_push($test_list[$i][$row['name']], $row['hostname']);
            }

            // Get test arguments for each test
            $query = "SELECT argument_value, ApplicationFrameworkArgs.argument_name FROM TestArgs JOIN ApplicationFrameworkArgs USING(framework_arg_id) WHERE testid = :testid ORDER BY testargid";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':testid', $test_list[$i]['testid'], PDO::PARAM_INT);
            $stmt->execute();
            $testArgs = array();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                array_push($testArgs, $row);
            }
            $test_list[$i]['arguments'] = $testArgs;

            //Fetch Strace Configuration
            $query = "SELECT * FROM ProfilerFramework WHERE testid = :testid AND profiler = :profiler";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':testid', $test_list[$i]['testid'], PDO::PARAM_INT);
            $stmt->bindValue(':profiler', 'STRACE', PDO::PARAM_STR);
            $stmt->execute();

            $strace_config = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($strace_config){
                $test_list[$i]['strace_process'] = $strace_config['processname'];
                $test_list[$i]['strace_duration'] = $strace_config['duration'];
                $test_list[$i]['strace_delay'] = $strace_config['delay'];
            }

            //Fetch PERF Configuration
            $query = "SELECT * FROM ProfilerFramework WHERE testid = :testid AND profiler = :profiler";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':testid', $test_list[$i]['testid'], PDO::PARAM_INT);
            $stmt->bindValue(':profiler', 'PERF', PDO::PARAM_STR);
            $stmt->execute();

            $perf_config = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($perf_config){
                $test_list[$i]['perf_duration'] = $perf_config['duration'];
                $test_list[$i]['perf_delay'] = $perf_config['delay'];
                if (!empty($perf_config['processname'])){
                    $test_list[$i]['perf_process'] = $perf_config['processname'];
                }
            }
        }
        $test_data = json_encode($test_list);
        $test_data_file = sys_get_temp_dir() . '/' . $framework_name . "_db_file_" . mt_rand() . ".json" ;
        $handle = fopen($test_data_file, "w");
        fwrite($handle, $test_data);
        fclose($handle);

        $zipname = $framework_name . "_" . mt_rand() . ".zip";
        $zippath = sys_get_temp_dir() . "/" . $zipname;
        $zip = new ZipArchive;
        $zip->open($zippath, ZipArchive::CREATE);
        if (is_file($test_data_file)){
            $zip->addFile($test_data_file, str_replace(sys_get_temp_dir() . "/","",$test_data_file));
        }

        $framework_fs = "test_data/" . $framework_name;
        if (is_dir($framework_fs)){
            $fileinfos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($framework_fs));
            foreach($fileinfos as $pathname => $fileinfo) {
                if (!$fileinfo->isFile()) continue;
                $zip->addFile($pathname, str_replace("test_data/","",$pathname));
            }
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.$zipname);
        header('Content-Length: ' . filesize($zippath));
        ob_clean();
        flush();
        readfile($zippath);
        unlink($zippath);
        unlink($test_data_file);
    }catch (PDOException $e){
        $db->rollBack();
        diePrint("MySQL error: " . $e->getMessage(), "Error !!");
    }
}

//

/**
 * This is import framework function. User upload zip file downloaded from other Daytona instance and select an
 * existing framework for importing test in that framework
 *
 * @param $framework_name - Name of the existing framework in which user want to import test data and logs
 * @param $file - Zip file downloaded from other Daytona instance which contains logs and db data
 */

function import_framework($framework_name, $file){
    global $userId, $db;
    $source = $file['tmp_name'];
    $extract_file_list = Array();
    $old_framework = explode("_",$file['name']);

    array_pop($old_framework);

    $old_framework = implode("_",$old_framework);

    $old_framework_db_file = $old_framework . "_db_file_";

    try{
        $temp_dir = sys_get_temp_dir() . "/" ;
        $extracted_dir = $temp_dir . "daytona_import_" . mt_rand() . "/";
        mkdir($extracted_dir, 0755, true);
        $imported_test_files = $extracted_dir . basename($file['name']);

        if (move_uploaded_file($source, $imported_test_files)){
            $zip = new ZipArchive();
            $x = $zip->open($imported_test_files);
            for( $i = 0; $i < $zip->numFiles; $i++ ){
                $stat = $zip->statIndex( $i );
                array_push($extract_file_list, $stat['name']);
            }
            if($x === true) {
                $zip->extractTo($extracted_dir);
                $zip->close();
                unlink($imported_test_files);
            } else {
                $error = "There was a problem. Please try again!";
                throw new Exception($error);
            }
        }else{
            $error = "Something went wrong with uploaded file";
            throw new Exception($error);
        }

        foreach ($extract_file_list as $file){
            if (strpos($file, $old_framework_db_file) !== false) {
                $old_framework_db_file = $file;
                break;
            }
        }
        $old_framework_db_file = $extracted_dir . $old_framework_db_file;
        $json_data = file_get_contents($old_framework_db_file);
        $old_framework_db_data = json_decode($json_data, true);

        // Get Framework ID
        $query = "SELECT frameworkid from ApplicationFrameworkMetadata WHERE frameworkname=:frameworkname";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':frameworkname', $framework_name, PDO::PARAM_STR);
        $stmt->execute();
        $framework_id = $stmt->fetch(PDO::FETCH_ASSOC)['frameworkid'];

        foreach($old_framework_db_data as $test_data) {
            // Add test in TestInput Data
            $query = "INSERT INTO TestInputData ( cc_list, start_time, end_time, creation_time, frameworkid, modified, priority, purpose, timeout, title, username, end_status) VALUES (:cc_list, :start_time, :end_time, :creation_time, :frameworkid, :modified_time, :priority, :purpose, :timeout, :title, :username, 'imported') ";
            $stmt = $db->prepare($query);

            $stmt->bindValue(':cc_list', $test_data['cc_list'], PDO::PARAM_STR);
            $stmt->bindValue(':start_time', $test_data['start_time'], PDO::PARAM_STR);
            $stmt->bindValue(':end_time', $test_data['end_time'], PDO::PARAM_STR);
            $stmt->bindValue(':creation_time', $test_data['creation_time'], PDO::PARAM_STR);
            $stmt->bindValue(':frameworkid', $framework_id, PDO::PARAM_INT);
            $stmt->bindValue(':modified_time', $test_data['modified'], PDO::PARAM_STR);
            $stmt->bindValue(':priority', $test_data['priority'], PDO::PARAM_INT);
            $stmt->bindValue(':purpose', $test_data['purpose'], PDO::PARAM_STR);
            $stmt->bindValue(':timeout', $test_data['timeout'], PDO::PARAM_INT);
            $stmt->bindValue(':title', $test_data['title'], PDO::PARAM_STR);
            $stmt->bindValue(':username', $userId, PDO::PARAM_STR);

            $stmt->execute();
            $testId = $db->lastInsertId();

            // Insert imported test argument in `ImportedTestArgs`
            $test_args = $test_data['arguments'];
            foreach ($test_args as $row){
                $query = "INSERT INTO ImportedTestArgs ( testid, arg_name, arg_value) VALUES (:testid, :arg_name, :arg_value) ";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
                $stmt->bindValue(':arg_name', $row['argument_name'], PDO::PARAM_STR);
                $stmt->bindValue(':arg_value', $row['argument_value'], PDO::PARAM_STR);
                $stmt->execute();
            }

            $hosttype = Array("execution","statistics");
            // Add hosts
            foreach ($hosttype as $host){
                if (array_key_exists($host,$test_data)) {
                    $host_list = $test_data[$host];
                    foreach ($host_list as $name){
                        $query = "INSERT INTO HostAssociation ( hostassociationtypeid, testid, hostname ) SELECT hostassociationtypeid, :testid, :hostname FROM HostAssociationType WHERE frameworkid = :frameworkid AND name = :host_type";
                        $stmt = $db->prepare($query);
                        $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
                        $stmt->bindValue(':hostname', $name, PDO::PARAM_STR);
                        $stmt->bindValue(':frameworkid', $framework_id, PDO::PARAM_INT);
                        $stmt->bindValue(':host_type', $host, PDO::PARAM_STR);
                        $stmt->execute();
                    }
                }
            }
            // Adding strace configuration(if any)
            if (array_key_exists("strace_process", $test_data)) {
                $query = "INSERT INTO ProfilerFramework (profiler, testid, processname, delay, duration) VALUES (:profiler, :testid, :processname, :delay, :duration)";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
                $stmt->bindValue(':profiler', 'STRACE', PDO::PARAM_STR);
                $stmt->bindValue(':processname', $test_data['strace_process'], PDO::PARAM_STR);
                $stmt->bindValue(':delay', $test_data['strace_delay'], PDO::PARAM_INT);
                $stmt->bindValue(':duration', $test_data['strace_duration'], PDO::PARAM_INT);
                $stmt->execute();
            }

            // Adding perf configuration
            $query = "INSERT INTO ProfilerFramework (profiler, testid, processname, delay, duration) VALUES (:profiler, :testid, :processname, :delay, :duration)";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
            $stmt->bindValue(':profiler', 'PERF', PDO::PARAM_INT);
            $stmt->bindValue(':delay', $test_data['perf_delay'], PDO::PARAM_INT);
            $stmt->bindValue(':duration', $test_data['perf_duration'], PDO::PARAM_INT);
            if (array_key_exists("perf_process", $test_data)) {
                $stmt->bindValue(':processname', $test_data['perf_process'], PDO::PARAM_INT);
            }else {
                $stmt->bindValue(':processname', NULL, PDO::PARAM_STR);
            }
            $stmt->execute();

            // Copy extracted test files to daytona file system

            $source_dir = $extracted_dir . $old_framework . "/" . $test_data['testid'] . "/results";
            $target_dir = "test_data/" . $framework_name . "/" . $testId . "/results";

            if (!file_exists($target_dir)){
                if (!mkdir($target_dir, 0755, true)) {
                    $error = 'Failed to access daytona file system';
                    throw new Exception($error);
                }
            }

            if (file_exists($source_dir)){
                recurse_copy($source_dir, $target_dir);
            }
        }

        recursive_rmdir($extracted_dir);

        // Commit DB changes
        $db->commit();

    }catch (Exception $err){
        $db->rollBack();
        diePrint($err, "Error");
    }
}

include_once('lib/header.php');
?>

<div class="content-wrapper" id="page-content">
  <div class="col-xs-12" id="content-div">
      <ul class="nav nav-tabs" style="margin: 20px 0 0 10px;border: none" id="import-export-nav">
              <li class="active">
                  <a data-toggle="tab" href="#home">
                      <div class="panel panel-info panel-short" id="import-export-panel">
                          <div class="panel-heading">Export</div>
                      </div>
                  </a>
              </li>
              <li>
                  <a data-toggle="tab" href="#menu1">
                      <div class="panel panel-info panel-short" id="import-export-panel">
                          <div class="panel-heading">Import</div>
                      </div>
                  </a>
              </li>
          </ul>

          <div class="tab-content">
              <div id="home" class="tab-pane fade in active div-tab">
                  <div class="panel-body" style="padding: 10px 0 0 0">
                      <h4>Export Framework Wizard</h4>
                      <form class="form-horizontal zero-margin" role="form" method="post" action='importexport.php'>
                          <fieldset>
                              <input type="hidden" name="action-type" value="export" />
                              <div class="form-group" style="margin-top: 8px">
                                  <label for="status" class="col-sm-1 control-label" style="padding-left: 15px;text-align: left">Select Framework:</label>
                                  <div class="col-sm-2 text_div">
                                      <select class="form-control" name="framework" required>
                                          <option value="" disabled selected></option>
                                          <?php
                                          foreach ($frameworks_arr as $l_framework) {
                                              echo "              <option>$l_framework</option>\n";
                                          }
                                          ?>
                                      </select>
                                  </div>
                              </div>
                              <div class="form-group" style="margin-bottom: 8px">
                                <div class="col-sm-12">
                                    <button type="submit" class="btn btn-primary btn-search-submit">Submit</button>
                                </div>
                              </div>
                          </fieldset>
                      </form>
                  </div>
              </div>
              <div id="menu1" class="tab-pane fade div-tab">
                  <div class="panel-body" style="padding: 10px 0 0 0">
                      <h4>Import Framework Wizard</h4>
                      <form class="form-horizontal zero-margin" role="form" method="POST" enctype="multipart/form-data">
                          <fieldset>
                                <input type="hidden" name="action-type" value="import" />
                                <div class="form-group" style="margin-top: 8px">
                                  <label for="status" class="col-sm-1 control-label" style="padding-left: 15px;text-align: left">Select Framework:</label>
                                  <div class="col-sm-2 text_div">
                                      <select class="form-control" name="framework" required>
                                          <option value="" disabled selected></option>
                                          <?php
                                          foreach ($frameworks_arr as $l_framework) {
                                              echo "              <option>$l_framework</option>\n";
                                          }
                                          ?>
                                      </select>
                                  </div>
                                </div>
                                <div class="form-group" style="margin-top: 8px">
                                    <label for="status" class="col-sm-1 control-label" style="padding-left: 15px;text-align: left">Upload File:</label>
                                    <div class="col-sm-2 text_div">
                                        <label class="btn btn-default btn-file control-label"><input type="file" name="framework_tar" required></label>
                                    </div>
                                    <p class="large-padding" style="font-style: italic">(only upload zip files downloaded from old daytona)</p>
                                </div>
                                <div class="form-group" style="margin-bottom: 8px">
                                    <div class="col-sm-12">
                                        <button type="submit" class="btn btn-primary btn-search-submit">Submit</button>
                                    </div>
                                </div>
                          </fieldset>
                      </form>
                  </div>
              </div>
          </div>
      </div>
    </div>
  </div>
</div>

</main>
<script src="js/account.js"></script>
<script type="text/javascript">
$(document).ready(function() {
  buildTopNavBar('Global', '');
  setDescription("Account Settings");
  buildLeftPanel();
  buildLeftPanelFramework();
  buildUserAccountMenu('<?php echo $userId; ?>');
  buildLeftPanelGlobal();
  loadNavigationBar();
<?php addFrameworkDropdownJS($db, $userId); ?>
});
</script>

<?php include_once('lib/footer.php'); ?>

