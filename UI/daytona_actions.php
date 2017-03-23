<?php
/*
 * AJAX actions
 */

require 'lib/auth.php';

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

function save_framework($db) {
    global $userId, $userIsAdmin;

    // Check for framework ID
    $frameworkId = getParam('f_frameworkid', 'POST');
    $isNewFramework = $frameworkId  ? false : true;
    $frameworkName = getParam('f_frameworkname', 'POST');
    if ($isNewFramework) {
        // Check if framework name is unique
        $testFramework = getFrameworkByName($db, $frameworkName);
        if ($testFramework) {
            returnError("Framework '$frameworkName' already exists.");
        }
    } else {
        $frameworkData = getFrameworkById($db, $frameworkId);
        if (! $frameworkData) {
            returnError("Could not find framework ID: $frameworkId");
        }
        if (!$userIsAdmin && $userId != $frameworkData['frameworkowner']) {
            returnError("You are not the framework owner (" . $frameworkData['frameworkowner'] . ")");
        }
    }

    // TODO: Check for other errors? The form has 'required' inputs as well as
    // pattern checks. Should we still check them here?

    // TODO Validate Test Report args? Like same number for each?

    // Four steps
    // 1) Insert or update base framework config
    // 2) Insert each test argument individually
    // 3) Delete all associated hosts (exec/reserved/statistics) for test (if any)
    // 4) Insert every host (exec/stat/reserved) individually
    // 5) Insert test report items

    if ($isNewFramework) {
        $query = "INSERT INTO ApplicationFrameworkMetadata ( argument_passing_format, creation_time, default_cc_list, default_timeout, email_include_results, email_results, exec_user, execution_script_location, file_root, frameworkname, frameworkowner, last_modified, productname, purpose, show_profiling, title) VALUES ( :argument_passing_format, NOW(), :default_cc_list, :default_timeout, :email_include_results, :email_results, :exec_user, :execution_script_location, :file_root, :frameworkname, :frameworkowner, NOW(), :productname, :purpose, :show_profiling, :title )";
    } else {
        $query = "UPDATE ApplicationFrameworkMetadata SET argument_passing_format = :argument_passing_format, default_cc_list = :default_cc_list, default_timeout = :default_timeout, email_include_results = :email_include_results, email_results = :email_results, exec_user = :exec_user, execution_script_location = :execution_script_location, file_root = :file_root, frameworkname = :frameworkname, frameworkowner = :frameworkowner, last_modified = NOW(), productname = :productname, purpose = :purpose, show_profiling = :show_profiling, title = :title WHERE frameworkid = :frameworkid";
    }

    // TODO: Look into eliminating try-catch
    // Rationale: http://stackoverflow.com/questions/272203/pdo-try-catch-usage-in-functions/273090#273090

    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        $stmt = $db->prepare($query);
        // We could've created an array for all the bound values below, but I like
        // to be explicit with what we're inserting into the DB (eg. PDO::PARAM_STR)
        // For more details: http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers
        $stmt->bindValue(':argument_passing_format', getParam('f_argument_passing_format', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':default_cc_list', getParam('f_default_cc_list', 'POST'), PDO::PARAM_STR);
        $defaultTimeout = getParam('f_default_timeout', 'POST');
        $stmt->bindValue(':default_timeout', getParam('f_default_timeout') ?: 0, PDO::PARAM_INT);
        $stmt->bindValue(':email_include_results', getParam('f_email_include_results', 'POST') ?: 0, PDO::PARAM_INT);
        $stmt->bindValue(':email_results', getParam('f_email_results', 'POST') ?: 0, PDO::PARAM_INT);
        $stmt->bindValue(':exec_user', getParam('f_exec_user', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':execution_script_location', getParam('f_execution_script_location', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':file_root', getParam('f_file_root', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':frameworkname', getParam('f_frameworkname', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':frameworkowner', getParam('f_frameworkowner', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':productname', getParam('f_productname', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':purpose', getParam('f_purpose', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':show_profiling', getParam('f_show_profiling', 'POST') ?: 0, PDO::PARAM_INT);
        $stmt->bindValue(':title', getParam('f_title', 'POST'), PDO::PARAM_STR);

        if (!$isNewFramework) {
            $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
        }

        $stmt->execute();
        if ($isNewFramework) {
            $frameworkId = $db->lastInsertId();
        }

        if (!$frameworkId) {
            returnError("No framework ID generated.");
        }

        // Admin privileges (only on new framework)
        if ($isNewFramework) {
            $query = "INSERT INTO CommonFrameworkAuthentication ( administrator, frameworkid, username ) VALUES ( 1, :frameworkid, :username )";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
            $stmt->bindValue(':username', getParam('f_frameworkowner', 'POST'), PDO::PARAM_STR);
            $stmt->execute();
        }

        // Host associations
        // Execution
        if ($isNewFramework) {
            $query = "INSERT INTO HostAssociationType ( default_value, execution, frameworkid, name, statistics) VALUES ( :default_value, 1, :frameworkid, 'execution', 0 )";
        } else {
            $query = "UPDATE HostAssociationType SET default_value = :default_value WHERE frameworkid = :frameworkid AND name = 'execution'";
        }
        $stmt = $db->prepare($query);
        $stmt->bindValue(':default_value', getParam('f_execution_host', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
        $stmt->execute();
        $hostTypeIdExec = $db->lastInsertId();

        // Statistics
        if ($isNewFramework) {
            $query = "INSERT INTO HostAssociationType ( default_value, execution, frameworkid, name, statistics) VALUES ( :default_value, 0, :frameworkid, 'statistics', 1 )";
        } else {
            $query = "UPDATE HostAssociationType SET default_value = :default_value WHERE frameworkid = :frameworkid AND name = 'statistics'";
        }
        $stmt = $db->prepare($query);
        $stmt->bindValue(':default_value', getParam('f_statistics_host', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
        $stmt->execute();
        $hostTypeIdExec = $db->lastInsertId();

        // Test Report
        $query = "DELETE FROM TestResultFile WHERE frameworkid = :frameworkid";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
        $stmt->execute();

        $testReport = getParam('f_testreport', 'POST');
        if ($testReport) {
            // This count should be the same whether we look at 'filename' or 'row'
            $numItems = count($testReport['filename']);
            for ($x = 0; $x < $numItems; $x++) {
                $query = "INSERT INTO TestResultFile ( filename, filename_order, frameworkid, height, row, title, width ) VALUES ( :filename, :filename_order, :frameworkid, 0, :row, :title, 0 )";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':filename', $testReport['filename'][$x], PDO::PARAM_STR);
                $stmt->bindValue(':filename_order', $x, PDO::PARAM_INT);
                $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
                $stmt->bindValue(':row', $testReport['row'][$x] ?: 1, PDO::PARAM_INT);
                $stmt->bindValue(':title', $testReport['title'][$x], PDO::PARAM_STR);
                $stmt->execute();
            }
        }

        // Find all arguments with arg ID set (ie, not a new argument)
        $arguments = getParam('f_arguments', 'POST') ?: array();
        $argumentIds = array();
        foreach($arguments['arg_id'] as $argId) {
            // Ignore if empty
            if ($argId) {
                $argumentIds[] = $argId;
            }
        }

        // Delete all removed arguments
        $query = "DELETE FROM ApplicationFrameworkArgs WHERE frameworkid = ? ";
        if ($argumentIds) {
            $query .= "AND framework_arg_id NOT IN ( " . join(' , ', array_map(function () { return '?'; }, $argumentIds)) . " )";
        }
        $stmt = $db->prepare($query);
        $stmt->bindValue(1, $frameworkId, PDO::PARAM_INT);
        $idx = 1;
        foreach($argumentIds as $argId) {
            $stmt->bindValue(++$idx, $argId, PDO::PARAM_INT);
        }
        $stmt->execute();

        // Insert each argument in order. If there is already an arg ID, update that
        // record.
        $argCount = count($arguments['argument_name']);
        for ($argIdx = 0; $argIdx < $argCount; $argIdx++) {
            if ($arguments['arg_id'][$argIdx]) {
                $query = "UPDATE ApplicationFrameworkArgs SET argument_default = :argument_default, argument_description = :argument_description, argument_name = :argument_name, argument_order = :argument_order, argument_values = :argument_values, frameworkid = :frameworkid, widget_type = :widget_type WHERE framework_arg_id = :framework_arg_id";
            } else {
                $query = "INSERT INTO ApplicationFrameworkArgs ( argument_default, argument_description, argument_name, argument_order, argument_values, frameworkid, widget_type ) VALUES ( :argument_default, :argument_description, :argument_name, :argument_order, :argument_values, :frameworkid, :widget_type )";
            }
            $stmt = $db->prepare($query);
            $stmt->bindValue(':argument_default', $arguments['argument_default'][$argIdx], PDO::PARAM_STR);
            $stmt->bindValue(':argument_description', $arguments['argument_description'][$argIdx], PDO::PARAM_STR);
            $stmt->bindValue(':argument_name', $arguments['argument_name'][$argIdx], PDO::PARAM_STR);
            $stmt->bindValue(':argument_order', $argIdx, PDO::PARAM_INT);
            $stmt->bindValue(':argument_values', $arguments['argument_values'][$argIdx], PDO::PARAM_STR);
            $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
            $stmt->bindValue(':widget_type', $arguments['widget_type'][$argIdx], PDO::PARAM_STR);
            if ($arguments['arg_id'][$argIdx]) {
                $stmt->bindValue(':framework_arg_id', $arguments['arg_id'][$argIdx], PDO::PARAM_INT);
            }
            $stmt->execute();
        }

        $db->commit();
    } catch(PDOException $e) {
        // All updates will be discarded if there's any fatal error
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    return array(
        'framework' => array(
            'frameworkid'   => $frameworkId,
            'frameworkname' => $frameworkName
        )
    );
}

function delete_framework($db) {
    global $userId, $userIsAdmin;

    // Check for framework ID
    $frameworkId = getParam('f_frameworkid', 'POST');

    if (!$frameworkId) {
        returnError("No ID defined");
    }
    if (!is_numeric($frameworkId)) {
        returnError("frameworkid is not valid");
    }

    $frameworkData = getFrameworkById($db, $frameworkId);
    if (! $frameworkData) {
        returnError("Could not find framework ID: $frameworkId");
    }
    if (!$userIsAdmin) {
        returnError("You are not an administrator, you may not delete frameworks");
    }

    // DB is properly set up so if you delete the main framework configuration
    // data from ApplicationFrameworkMetadata, it will cascade down and delete the
    // associated entries in ApplicationFrameworkArgs, HostAssociationType, and
    // TestResultFile

    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        $query = "DELETE FROM ApplicationFrameworkMetadata WHERE frameworkid = :frameworkid";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
        $stmt->execute();

        $db->commit();
    } catch(PDOException $e) {
        // All updates will be discarded if there are any fatal errors
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    return array(
        'framework' => array(
            'frameworkid'   => $frameworkData['frameworkid'],
            'frameworkname' => $frameworkData['frameworkname']
        )
    );
}

function save_test($db,$state='new') {
    global $userId, $userIsAdmin;

    // Check for framework ID
    $frameworkId = getParam('f_frameworkid', 'POST');
    if (!$frameworkId) {
        returnError("POST data did not include a framework ID.");
    }
    if (!is_numeric($frameworkId)) {
        returnError("frameworkid (POST) is not valid.");
    }

    // Check for test ID
    $testId = getParam('f_testid', 'POST');
    $isNewTest = $testId ? false : true;

    // Validate test exists if updating
    if ($testId) {
        $testData = getTestById($db, $testId);
        if (!$testData) {
            returnError("Can't update test #$testId: test does not exist");
        }
        if (!$userIsAdmin && $testData['username'] != $userId) {
            returnError("You are not the test owner (" . $testData['username'] . ")");
        }
    }

    // TODO: Check for other errors? The form already checks for title and
    // exechost. Should we still check them here?

    // Retrieve argument IDs for framework (for test argument insertion)
    $query = "SELECT framework_arg_id, frameworkid, argument_name, argument_values, argument_default, argument_order FROM ApplicationFrameworkArgs WHERE frameworkid = :frameworkid ORDER BY argument_order";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
    $stmt->execute();
    $argRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Four steps
    // 1) Insert or update base test config
    // 2) Insert each test argument individually
    // 3) Delete all associated hosts (exec/reserved/statistics) for test (if any)
    // 4) Insert every host (exec/stat/reserved) individually

    if ($isNewTest) {
        $query = "INSERT INTO TestInputData ( cc_list, cpu_profiling, creation_time, frameworkid, modified, priority, profile_duration, profile_start, profilehostname, purpose, timeout, title, username, end_status) VALUES (:cc_list, 0, NOW(), :frameworkid, NOW(), :priority, :profile_duration, :profile_start, :profilehostname, :purpose, :timeout, :title, :username, '$state') ";
    } else {
        $query = "UPDATE TestInputData SET cc_list = :cc_list, cpu_profiling = 0, modified = NOW(), priority = :priority, profile_duration = :profile_duration, profile_start = :profile_start, profilehostname = :profilehostname, purpose = :purpose, timeout = :timeout, title = :title, end_status = '$state'  WHERE testid = :testid";
    }

    // TODO: Figure out what to do with 'cpu_profiling'

    // TODO: Look into eliminating try-catch
    // Rationale: http://stackoverflow.com/questions/272203/pdo-try-catch-usage-in-functions/273090#273090

    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        $stmt = $db->prepare($query);
        // We could've created an array for all the bound values below, but I like
        // to be explicit with what we're inserting into the DB (eg. PDO::PARAM_STR)
        // For more details: http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers
        $stmt->bindValue(':cc_list', getParam('f_cc_list', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':priority', getParam('f_priority', 'POST'), PDO::PARAM_INT);
        $stmt->bindValue(':profile_duration', getParam('f_profile_duration', 'POST'), PDO::PARAM_INT);
        $stmt->bindValue(':profile_start', getParam('f_profile_start', 'POST'), PDO::PARAM_INT);
        $stmt->bindValue(':profilehostname', getParam('f_profilehostname', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':purpose', getParam('f_purpose', 'POST'), PDO::PARAM_STR);
        $stmt->bindValue(':timeout', getParam('f_timeout', 'POST') ?: 0, PDO::PARAM_INT);
        $stmt->bindValue(':title', getParam('f_title', 'POST'), PDO::PARAM_STR);
        if ($isNewTest) {
            $stmt->bindValue(':username', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
        }

        $stmt->execute();
        if ($isNewTest) {
            $testId = $db->lastInsertId();
        }

        if (!$testId) {
            // Rollback?
            returnError("No test ID generated.");
        }

        // Insert arguments
        foreach ($argRows as $arg) {
            $argId = $arg['framework_arg_id'];
            $argValue = getParam("f_arg_$argId", 'POST');
            if ($isNewTest) {
                $query = "INSERT INTO TestArgs ( argument_value, framework_arg_id, testid ) VALUES ( :argument_value, :framework_arg_id, :testid )";
            } else {
                $query = "UPDATE TestArgs set argument_value = :argument_value WHERE framework_arg_id = :framework_arg_id AND testid = :testid";
            }
            $stmt = $db->prepare($query);
            $stmt->bindValue(':argument_value', $argValue, PDO::PARAM_STR);
            $stmt->bindValue(':framework_arg_id', $argId, PDO::PARAM_INT);
            $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Delete any previously associated hosts associated with testid
        $query = "DELETE FROM HostAssociation WHERE testid = :testid";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
        $stmt->execute();

        // Add each host
        $hostTypes = array('execution', 'statistics', 'reserved');
        foreach ($hostTypes as $hostType) {
            $hosts = getParam("f_$hostType", 'POST');
            if (!$hosts) {
                continue;
            }
            $hostsArray = explode(',', $hosts);
            foreach ($hostsArray as $host) {
                // TODO: Look into what CommonHardwareMetadata is actually used for
                // We still need it because we can't insert into HostAssociation w/o it
                $query = "INSERT INTO CommonHardwareMetadata ( hostname, added, updated ) VALUES ( :hostname, NOW(), NOW() ) ON DUPLICATE KEY UPDATE updated=NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':hostname', $host, PDO::PARAM_STR);
                $stmt->execute();

                // Insert
                $query = "INSERT INTO HostAssociation ( hostassociationtypeid, testid, hostname ) SELECT hostassociationtypeid, :testid, :hostname FROM HostAssociationType WHERE frameworkid = :frameworkid AND name = :host_type";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
                $stmt->bindValue(':hostname', $host, PDO::PARAM_STR);
                $stmt->bindValue(':frameworkid', $frameworkId, PDO::PARAM_INT);
                $stmt->bindValue(':host_type', $hostType, PDO::PARAM_STR);
                $stmt->execute();
            }
        }

        // Adding strace configuration (if any)

        if (isset($_POST['f_strace'])){
            try {
                $strace_process = getParam('f_strace_process','POST');
                $strace_delay = getParam('f_strace_delay','POST');
                $strace_duration = getParam('f_strace_duration','POST');
            }catch (Exception $ex){
                returnError("Some values missing for STRACE configuration");
            }

            $query = "SELECT profiler_framework_id FROM ProfilerFramework WHERE testid = :testid AND profiler = :profiler";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
            $stmt->bindValue(':profiler', 'STRACE', PDO::PARAM_INT);
            $stmt->execute();
            $profilerID = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profilerID){
                // Update previous strace configuration
                $query = "UPDATE ProfilerFramework SET processname = :processname, delay = :delay, duration = :duration WHERE testid = :testid AND profiler = :profiler";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
                $stmt->bindValue(':profiler', 'STRACE', PDO::PARAM_STR);
                $stmt->bindValue(':processname', $strace_process, PDO::PARAM_STR);
                $stmt->bindValue(':delay', $strace_delay, PDO::PARAM_INT);
                $stmt->bindValue(':duration', $strace_duration, PDO::PARAM_INT);
                $stmt->execute();
            }else{
                // Add new strace configuration
                $query = "INSERT INTO ProfilerFramework (profiler, testid, processname, delay, duration) VALUES (:profiler, :testid, :processname, :delay, :duration)";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
                $stmt->bindValue(':profiler', 'STRACE', PDO::PARAM_STR);
                $stmt->bindValue(':processname', $strace_process, PDO::PARAM_STR);
                $stmt->bindValue(':delay', $strace_delay, PDO::PARAM_INT);
                $stmt->bindValue(':duration', $strace_duration, PDO::PARAM_INT);
                $stmt->execute();
            }
        }else{
            if(!$isNewTest){
                // Delete any previous strace configuration
                $query = "DELETE FROM ProfilerFramework WHERE testid = :testid AND profiler = :profiler";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
                $stmt->bindValue(':profiler', 'STRACE', PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        $db->commit();
    } catch(PDOException $e) {
        // All updates will be discarded if there's any fatal error
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    return array(
        'test' => array(
            'testid'      => $testId,
            'frameworkid' => $frameworkId,
            'new'         => $isNewTest,
            'running'     => false
        )
    );
}

function save_run_test($db) {
    $testReturnData = save_test($db,'scheduled');

    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        $query = "INSERT INTO CommonFrameworkSchedulerQueue (testid, state, pid) VALUES ( :testid, 'scheduled', 0 )";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':testid', $testReturnData['test']['testid']);
        $stmt->execute();

        $db->commit();
    } catch(PDOException $e) {
        // All updates will be discarded if there are any fatal errors
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    $testReturnData['test']['running'] = true;
    return $testReturnData;
}

function delete_test($db) {
    global $userId, $userIsAdmin;

    // Check for test ID
    $testId = getParam('f_testid', 'POST');

    if (!$testId) {
        returnError("No ID defined");
    }
    if (!is_numeric($testId)) {
        returnError("testid is not valid");
    }

    $testData = getTestById($db, $testId);
    if (! $testData) {
        returnError("Could not find test ID: $testId");
    }
    $frameworkData = getFrameworkById($db, $testData['frameworkid']);

    if (!$userIsAdmin && $userId != $testData['username']) {
        returnError("You are not the test owner (" . $testData['username'] . ")");
    }

    // DB is properly set up so if you delete the main test configuration data
    // from TestInputData, it will cascade down and delete associated entries

    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        $query = "DELETE FROM TestInputData WHERE testid = :testid";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':testid', $testId, PDO::PARAM_INT);
        $stmt->execute();

        $db->commit();
    } catch(PDOException $e) {
        // All updates will be discarded if there are any fatal errors
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    return array(
        'test' => array(
            'frameworkid' => $testData['frameworkid'],
            'testid'      => $testId,
            'deleted'     => true
        )
    );
}

function delete_tests($db) {
    global $userId, $userIsAdmin;

    // TODO: Normalize with rest of functions
    $testIds = getParam('testids', 'POST');

    if (! $testIds) {
        returnError("No test IDs defined");
    }

    // Validate each test ID
    foreach($testIds as $testId) {
        if (!is_numeric($testId)) {
            returnError("One or more test IDs are not valid");
        }
        $testData = getTestById($db, $testId);
        if (!$testData) {
            returnError("Could not find test ID: $testId");
        }
        $frameworkData = getFrameworkById($db, $testData['frameworkid']);

        if (!$userIsAdmin && $userId != $testData['username']) {
            returnError("You are not the test owner for test: $testId");
        }
    }

    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        $query = "DELETE FROM TestInputData WHERE FIND_IN_SET(testid, :array)";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':array', implode(',', $testIds));
        $stmt->execute();

        $db->commit();
    } catch(PDOException $e) {
        // All updates will be discarded if there are any fatal errors
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    return array();
}

function set_user_frameworks($db) {
    global $userId;

    $frameworks = getParam('frameworks', 'POST');

    if (!$frameworks) {
        returnError("No frameworks are defined");
    }

    // TODO: Validate each framework?
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        $ret = "";
        foreach ($frameworks as $framework) {

            if ($framework['checked'] == 'true') {
                $query = "INSERT INTO CommonFrameworkAuthentication (username, administrator, frameworkid) VALUES( :userid, 0, :frameworkid ) ON DUPLICATE KEY UPDATE frameworkid = :frameworkid";
            } else {
                $query = "DELETE FROM CommonFrameworkAuthentication WHERE username = :userid AND frameworkid = :frameworkid";
            }
            $stmt = $db->prepare($query);
            $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':frameworkid', $framework['frameworkid'], PDO::PARAM_INT);
            $stmt->execute();
        }

        $db->commit();
    } catch(PDOException $e) {
        // All updates will be discarded if there are any fatal errors
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    return array();
}

// Script begins here

// Check for action type
$action = getParam('action', 'POST');
if (! $action) {
    returnError("No action defined");
}
if (! preg_match('/^(save_framework|delete_framework|save_test|save_run_test|delete_test|delete_tests|set_user_frameworks)$/', $action)) {
    returnError("Unknown action: $action");
}

if (! $userId) {
    returnError("No User defined");
}

$returnData = $action($db);

returnOk($returnData);
?>
