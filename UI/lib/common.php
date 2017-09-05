<?php

function getUserAccount($db, $user, $email=null) {
    $email_query = $email ? "OR email = :email" : "";
    $query = "SELECT is_admin, email, user_state FROM LoginAuthentication WHERE username = :username $email_query LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':username', $user, PDO::PARAM_STR);
    if($email) {
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    }
    $stmt->execute();
    $accountInfo = $stmt->fetch(PDO::FETCH_OBJ);
    return $accountInfo;
}

// Prints a fatal message
function diePrint($message, $header) {
    if (!$header) {
        $header = "An error occurred";
    }
    $html = "<html><head><title>Daytona - Error!</title>";
    $html .= "<link rel=\"stylesheet\" href=\"css/style.css\">";
    $html .= "<body><div class=\"error status-panel\">";
    $html .= "<div class=\"header\">$header</div>";
    $html .= "<p>$message<br>";
    $html .= "Click <a href=\"#\" onclick=\"history.go(-1)\">here</a> to go back.";
    $html .= "</p></div></body>";
    $html .= "</html>";
    die($html);
}

// Retrieve a GET or POST parameter
function getParam($paramName, $paramType='GET') {
    if ($paramType == 'POST') {
        return isset($_POST[$paramName]) ? sanitize_input($_POST[$paramName]) : null;
    }
    return isset($_GET[$paramName]) ? sanitize_input($_GET[$paramName]) : null;
}

function initDB() {
    $conf = parse_ini_file('daytona_config.ini');
    $servername = $conf['servername'];
    $username = $conf['username'];
    $password = $conf['password'];
    $dbname = $conf['dbname'];
    date_default_timezone_set('America/Los_Angeles');
    try {
        $db = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    } catch(PDOException $e) {
        diePrint("Could not establish connection to DB: " . $e->getMessage());
    }
    return $db;
}

function getFrameworks($db, $userId) {
    if ($userId) {
        $query = "SELECT DISTINCT * FROM CommonFrameworkAuthentication JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE username = :userId ORDER BY frameworkname ASC";
    } else {
        $query = "SELECT DISTINCT * FROM CommonFrameworkAuthentication JOIN  ApplicationFrameworkMetadata USING(frameworkid) ORDER BY frameworkname ASC";
    }
//  $query = "SELECT DISTINCT * FROM ApplicationFrameworkMetadata ORDER BY frameworkname ASC";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFrameworkById($db, $fid, $full=false) {
    $query = "SELECT * FROM ApplicationFrameworkMetadata WHERE frameworkid = :frameworkid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkid', $fid, PDO::PARAM_INT);
    $stmt->execute();
    $frameworkData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send back if framework not found or we don't want full data
    if (!$full || !$frameworkData) {
        return $frameworkData;
    }

    return getFrameworkAuxData($db, $frameworkData);
}

function getFrameworkByName($db, $fname, $full=false) {
    $query = "SELECT * FROM ApplicationFrameworkMetadata WHERE frameworkname = :frameworkname";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkname', $fname, PDO::PARAM_STR);
    $stmt->execute();
    $frameworkData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send back if framework not found or we don't want full data
    if (!$full || !$frameworkData) {
        return $frameworkData;
    }

    return getFrameworkAuxData($db, $frameworkData);
}

function getFrameworkAuxData($db, $frameworkData) {
    // TODO: Is there a way to create a query where everything is returned,
    // including arguments & test report items as arrays?

    // Get arguments
    $query = "SELECT * FROM ApplicationFrameworkArgs WHERE frameworkid = :frameworkid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkid', $frameworkData['frameworkid']);
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $frameworkData['arguments'][] = $row;
    }
    // Get hosts
    $query = "SELECT * FROM HostAssociationType WHERE frameworkid = :frameworkid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkid', $frameworkData['frameworkid']);
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $frameworkData[$row['name'] . '_host'] = $row;
    }
    // Get test report
    $query = "SELECT * FROM TestResultFile WHERE frameworkid = :frameworkid ORDER BY filename_order ASC";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkid', $frameworkData['frameworkid']);
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $frameworkData['test_report'][] = $row;
    }

    return $frameworkData;
}

function getTestById($db, $testId, $full=false) {
    $query = "SELECT testid, frameworkname, TestInputData.title, TestInputData.purpose, priority, execution_script_location, timeout, cc_list, testid, frameworkid, TestInputData.modified, TestInputData.creation_time, start_time, end_time, end_status, username FROM TestInputData JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE testid = :testid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->execute();
    $testData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send back if framework not found or we don't want full data
    if (!$full || !$testData) {
        return $testData;
    }

    $query = "SELECT name, hostname FROM HostAssociation JOIN HostAssociationType USING(hostassociationtypeid) WHERE testid = :testid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->execute();

    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if(!isset($testData[$row['name']])) {
            $testData[$row['name']] = array();
        }
        array_push($testData[$row['name']], $row['hostname']);
    }

    // Fetch imported test argument, if any
    $query = "SELECT * FROM ImportedTestArgs WHERE testid=:testid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->execute();

    $old_testArgs = Array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $temp_arr = Array();
        $temp_arr['arg_name'] = $row['arg_name'];
        $temp_arr['arg_value'] = $row['arg_value'];
        array_push($old_testArgs,$temp_arr);
    }

    if (count($old_testArgs) > 0) {
        $testData['imported_test_arg'] = $old_testArgs;
    }

    $query = "SELECT argument_value, framework_arg_id, widget_type FROM TestArgs JOIN ApplicationFrameworkArgs USING(framework_arg_id) WHERE testid = :testid ORDER BY testargid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->execute();
    $testArgs = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $testArgs[$row['framework_arg_id']] = array();
        $testArgs[$row['framework_arg_id']]['value'] = $row['argument_value'];
        $testArgs[$row['framework_arg_id']]['widget_type'] = $row['widget_type'];
    }
    $testData['arguments'] = $testArgs;

    //Fetch Strace Configuration
    $query = "SELECT * FROM ProfilerFramework WHERE testid = :testid AND profiler = :profiler";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->bindValue(':profiler', 'STRACE', PDO::PARAM_STR);
    $stmt->execute();

    $strace_config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($strace_config){
        $testData['strace'] = True;
        $testData['strace_process'] = $strace_config['processname'];
        $testData['strace_duration'] = $strace_config['duration'];
        $testData['strace_delay'] = $strace_config['delay'];
    }else{
        $testData['strace'] = False;
    }

    //Fetch PERF Configuration
    $query = "SELECT * FROM ProfilerFramework WHERE testid = :testid AND profiler = :profiler";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->bindValue(':profiler', 'PERF', PDO::PARAM_STR);
    $stmt->execute();

    $perf_config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($perf_config){
        $testData['perf_duration'] = $perf_config['duration'];
        $testData['perf_delay'] = $perf_config['delay'];
        $testData['perf_process'] = $perf_config['processname'];
    }

    return $testData;
}

function checkTestRunning($db, $testId){
    $query = "SELECT * FROM CommonFrameworkSchedulerQueue where testid=:testid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row)
    {
        return true;
    }else{
        return false;
    }
}

function getTestResults($db, $testId) {
    $query = "SELECT columnnumber, rownumber, result_name, result_value FROM TestResults JOIN TestResultsColumn USING(resultsid) JOIN TestResultsPair USING(columnid) WHERE testid = :testid";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
    $stmt->execute();
    $testData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $testData;
}

function addFrameworkDropdownJS($db, $userId) {
    $frameworks = getFrameworks($db, $userId);
    foreach ($frameworks as $framework) {
        $fname = $framework['frameworkname'];
        $fid = $framework['frameworkid'];
        echo "  fillFrameworkDropDown('$fname', $fid);\n";
    }
}

function addTestResults($path, $hosts, $testid, $compids, $exec_script) {
    if (count($hosts) > 0 ){
        echo "createLabel('Test Results')\n";
        echo "createTestResultsRoot()\n";
    }
    foreach ($hosts as $key=>$host) {
        $files = glob("$path/$host/*.{plt,csv,txt}", GLOB_BRACE);
        foreach ($files as $file) {
            $ex_file = explode("/", $file);
            echo "  fillTestResults('$testid', '$compids', '" . end($ex_file) . "', '$file', '$key');\n";
        }
    }
    echo "buildExecScriptLink('$testid', '$compids', '$exec_script');\n" ;
}

function addSystemMetrics($path, $hosts, $testid, $compids, $label) {
    foreach ($hosts as $key=>$host) {
        if(strpos($label,"exec") !== false){
            $hostid = "-" . $label . $key;
            echo "  fillSystemMetricsHost('Execution Host','$hostid');\n";
        }else{
            echo "  fillSystemMetricsHost('$host','$key');\n";
        }
        $files = glob("$path/$host/*.{plt,csv,txt}", GLOB_BRACE);
        foreach ($files as $file) {
            $ex_file = explode("/", $file);
            echo "  fillSystemMetrics('$testid', '$compids', '" . end($ex_file) . "', '$file', '$key', '$label');\n";
        }
    }
}


function addLogs($path, $hosts, $testid, $compids) {
    if (count($hosts) > 0 ){
        echo "createLabel('Logs')\n";
        echo "  fillLogsHost('Files');\n";
    }
    foreach ($hosts as $hosttype=>$hosts_info) {
        foreach ($hosts_info as $key=>$host) {
            $filepath = '%'. $hosttype .',' . $key . '%/';
            $files = glob("$path/$host/*.{log}", GLOB_BRACE);
            foreach ($files as $file) {
                $ex_file = explode("/", $file);
                echo "  fillLogs('$testid', '$compids', '" . end($ex_file) . "', '$file', '$filepath');\n";
            }
            $filepath = '%'. $hosttype .',' . $key . '%/application/';
            $files = glob("$path/$host/application/*.{log}", GLOB_BRACE);
            foreach ($files as $file) {
                $ex_file = explode("/", $file);
                echo "  fillLogs('$testid', '$compids', '" . end($ex_file) . "', '$file', '$filepath');\n";
            }
        }
    }
}


function initFramework($db, $full=false) {
    $frameworkName = getParam('framework');
    // TODO: This is kinda hackish. Figure out a better way to handle this
    if ($frameworkName == 'Global') {
        return array(null, null, null);
    }
    $frameworkId = getParam('frameworkid');
    $fData = null;
    if ($frameworkName) {
        $fData = getFrameworkByName($db, $frameworkName, $full);
        if (! $fData) {
            diePrint("Could not find framework by name: $frameworkName");
        }
    } else if ($frameworkId) {
        $fData = getFrameworkById($db, $frameworkId, $full);
        if (! $fData) {
            diePrint("Could not find framework by ID: $frameworkId");
        }
    }
    if ($fData) {
        return array($fData['frameworkid'], $fData['frameworkname'], $fData);
    }
    return array(null, null, null);
}

function generateCompareCsv($csvfiles, $testid)
{
    $csv_files = explode(",", $csvfiles);
    $test_id = explode(",", $testid);
    $final_csv = array();

    for ($i = 0; $i < count($csv_files); ++$i) {
        $valid_path = validate_file_path($csv_files[$i]);
        if ($valid_path === false){
            return;
        }
        if ($i == 0) {
            $fp = fopen($csv_files[$i], 'r');
            if ($fp){
                while (($data = fgetcsv($fp, 0, ",")) !== FALSE) {
                    $csv_content[] = $data;
                }
                $csv_content = sort2DArray($csv_content);
                $final_csv["Test ID"]  = array($test_id[$i]);
                for ($j = 0; $j < count($csv_content); ++$j) {
                    $final_csv[trim($csv_content[$j][0])] = array(trim($csv_content[$j][1]));
                }
            }
        } else {
            unset($fp);
            unset($data);
            unset($csv_content);
            $fp = fopen($csv_files[$i], 'r');
            if ($fp){
                while (($data = fgetcsv($fp, 0, ",")) !== FALSE) {
                    $csv_content[] = $data;
                }
                $csv_content = sort2DArray($csv_content);
                for($k=0;$k<count($csv_content);++$k) {
                    if (array_key_exists($csv_content[$k][0],$final_csv)) {
                        $value = $final_csv[$csv_content[$k][0]];
                        array_push($value,trim($csv_content[$k][1]));
                        $final_csv[$csv_content[$k][0]] = $value;
                    }else{
                        $value=array();
                        for($x=0;$x<$i;++$x){
                            array_push($value,'null');
                        }
                        array_push($value,trim($csv_content[$k][1]));
                        $final_csv[$csv_content[$k][0]] = $value;
                    }
                }
                $key_value = $final_csv["Test ID"];
                $key_value[$i] = $test_id[$i];
                $final_csv["Test ID"] = $key_value;
                foreach($final_csv as $field => $value){
                    if(count($value) < ($i+1)){
                        array_push($value,'null');
                        $final_csv[$field] = $value;
                    }
                }
            }
        }
    }

    /*$fp = fopen('temp_compare.csv', 'w');//output file set here
    foreach ($final_csv as $field => $value) {
    array_unshift($value,$field);
        fputcsv($fp, $value);
    }*/
    fclose($fp);
    return $final_csv;
}

function getCsvColumnCount($csvfiles){
    $csv_files = explode(",", $csvfiles);
    $count = 0;
    if (count($csv_files) > 0){
        $valid_path = validate_file_path($csv_files[0]);
        if ($valid_path === false){
            return;
        }
        $fp = fopen($csv_files[0], 'r');
        if ($fp){
            $data = fgetcsv($fp, 0, ",");
            if (!is_null($data))
                $count = count($data);
        }
    }
    fclose($fp);
    return $count;

}

function sort2DArray($arr){
    $arr_sort = array_slice($arr,1);
    usort($arr_sort, function($a,$b) {
        return strcasecmp($a[0],$b[0]);
    });
    array_unshift($arr_sort, $arr[0]);
    return $arr_sort;
}

function validatePassword($db, $user, $password) {
    $query = "SELECT password FROM LoginAuthentication WHERE username = :username LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':username', $user, PDO::PARAM_STR);
    $stmt->execute();
    $storedPassword = $stmt->fetch(PDO::FETCH_OBJ);
    if(sizeof($storedPassword) and $storedPassword->password) {
        return password_verify($password, $storedPassword->password);
    }
    return false;
}

function returnError($message=null) {
    $returnObj = array(
        'status'  => 'ERROR',
        'message' => $message
    );
    header('Content-Type: application/json');
    echo json_encode($returnObj);
    exit;
}

function returnOk($returnData=array()) {
    $returnObj = array(
        'status'  => 'OK',
        'message' => 'OK'
    );
    $returnObj = array_merge($returnObj, $returnData);
    header('Content-Type: application/json');
    echo json_encode($returnObj);
    exit;
}

function validate_file_path($filepath){
    $conf = parse_ini_file('daytona_config.ini');
    $base = $conf['daytona_data_dir'];
    $real = realpath($filepath);
    if ($real === false || strncmp($real, $base, strlen($base)+1) <= 0){
        return false;
    }else{
        return true;
    }
}

function validatePasswordPolicy($password){
    if (!preg_match('/^(?=.*\d)(?=.*[@#\-_$%^&+=§!\?])(?=.*[a-z])(?=.*[A-Z])[0-9A-Za-z@#\-_$%^&+=§!\?]{8,12}$/',$password)){
        return false;
    }else{
        return true;
    }
}

function sanitize_input($data) {
    if (!is_array($data)){
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
    }
    return $data;
}

// Copy command just handles files hence using this recurse_copy snippet from PHP manual for recursive copy
// Reference : http://php.net/manual/en/function.copy.php
function recurse_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// rmdir delete directory only if it is empty hence using this snippet from PHP manual for recursive del
// Reference : http://www.php.net/rmdir
function recursive_rmdir($src) {
    $dir = opendir($src);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $full = $src . '/' . $file;
            if ( is_dir($full) ) {
                recursive_rmdir($full);
            }
            else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

?>
