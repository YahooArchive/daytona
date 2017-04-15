<?php
header('Content-type: application/json');
require('lib/auth.php');

function convertTime($str_time) {
    if(!$str_time) {
        return "N/A";
    }
    $time = strtotime($str_time . " UTC");
    return date("m/d/Y g:i A", $time);
}

if(empty($_GET["query"])) {
    die("Required field 'query' is empty");
}

$testid = "";
if(getParam("query") == "killTest" || getParam("query") == "runTest") {
    if(empty($_GET["testid"])) {
        die("Required field 'testid' is empty");
    }
    $testid = getParam("testid");
    // SQL Injection Prevention
    if(!is_numeric($testid)) {
        die("'testid' must be numeric");
    }
    $testData = getTestById($db, $testid, true);
    if(!$userIsAdmin && $userId != $testData["username"]) {
        die("You are not the test owner (" . $testData['username'] . ")");
    }
}

$framework_clause_and = "";
$framework_clause_where = "";
$duration_clause_and = "";
$user_clause_and = "";
$frameworkid = 0;
$frameworkid_bool = False;
$username_bool = False;

if(isset($_GET["frameworkid"])) {
    $framework_clause_and = "AND frameworkid=:frameworkid";
    $framework_clause_where = "WHERE frameworkid=:frameworkid";
    $frameworkid = (int)getParam("frameworkid");
    $frameworkid_bool = True;
}

if(isset($_GET["duration"])) {
    switch(getParam("duration")) {
        case "1":
            $duration_clause_and = " AND end_time >= now() - INTERVAL 1 DAY";
            break;
        case "2":
            $duration_clause_and = " AND end_time >= now() - INTERVAL 1 WEEK";
            break;
        case "3":
            $duration_clause_and = " AND end_time >= now() - INTERVAL 1 MONTH";
            break;
        default:
            $duration_clause_and = "";
    }
}

if(isset($_GET["user"])) {
    switch(getParam("user")) {
        case "1":
	    $username_bool = True;
            $user_clause_and = " AND username=:userId";
            break;
        default:
            $user_clause_and = "";
            break;
    }
}
$sql_query = "";
switch(getParam("query")) {
    case "killTest":
        $sql_query = "DELETE FROM CommonFrameworkSchedulerQueue WHERE " .
            "testid=:testid;" .
            "UPDATE TestInputData SET end_status='kill' WHERE " .
            "testid=:testid";
        break;
    case "runTest":
        $sql_query = "INSERT INTO CommonFrameworkSchedulerQueue (testid,state,pid) " .
            "VALUES (:testid,'scheduled',0)";
        break;
    case "currentlyRunning":
        $sql_query = "SELECT frameworkid,frameworkname,testid,TestInputData.title,username,state,start_time,state_detail," .
            "queueid,TestInputData.purpose,priority FROM CommonFrameworkSchedulerQueue " .
            "JOIN TestInputData USING(testid) JOIN ApplicationFrameworkMetadata USING(frameworkid) " .
            "WHERE state='running' $framework_clause_and $user_clause_and";
        break;
    case "waitingQueue":
        $sql_query = "SELECT frameworkid,frameworkname,testid,TestInputData.title,username,state,start_time,state_detail," .
            "queueid,TestInputData.purpose,priority FROM CommonFrameworkSchedulerQueue " .
            "JOIN TestInputData USING(testid) JOIN ApplicationFrameworkMetadata USING(frameworkid) " .
            "WHERE state!='running' $framework_clause_and $user_clause_and";
        break;
    case "lastCompleted":
        $sql_query = "SELECT DISTINCT frameworkid,frameworkname,testid,TestInputData.title,username,end_status,start_time,end_time " .
            "FROM TestInputData JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE " .
            "testid NOT IN (SELECT testid FROM CommonFrameworkSchedulerQueue) " .
            "$framework_clause_and $duration_clause_and $user_clause_and ORDER BY end_time DESC LIMIT 15";
        break;
    case "queueCount":
        $sql_query = "SELECT COUNT(testid) FROM CommonFrameworkSchedulerQueue JOIN TestInputData " .
            "USING(testid) JOIN ApplicationFrameworkMetadata USING(frameworkid) " .
            $framework_clause_where;
        break;
}
$query_arr = explode(";", html_entity_decode($sql_query, ENT_QUOTES));
foreach($query_arr as $query) {
    try{
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();
        $stmt = $db->prepare($query);
        switch(getParam("query")) {
            case "killTest":
                $stmt->bindValue(':testid',$testid,PDO::PARAM_INT);
                $stmt->execute();
                break;
            case "runTest":
                $stmt->bindValue(':testid',(int)$testid,PDO::PARAM_INT);
                $stmt->execute();
                break;
            case "currentlyRunning":
		if ($frameworkid_bool){
		    $stmt->bindValue(':frameworkid',(int)$frameworkid,PDO::PARAM_INT);
		}
		if ($username_bool){
		    $stmt->bindValue(':userId',$userId,PDO::PARAM_STR);
		}
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            case "waitingQueue":
                if ($frameworkid_bool){
                    $stmt->bindValue(':frameworkid',(int)$frameworkid,PDO::PARAM_INT);
                }
		if ($username_bool){
                    $stmt->bindValue(':userId',$userId,PDO::PARAM_STR);
                }
		$stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            case "lastCompleted":
                if ($frameworkid_bool){
                    $stmt->bindValue(':frameworkid',(int)$frameworkid,PDO::PARAM_INT);
                }
		if ($username_bool){
                    $stmt->bindValue(':userId',$userId,PDO::PARAM_STR);
                }
		$stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            case "queueCount":
                if ($frameworkid_bool){
                    $stmt->bindValue(':frameworkid',(int)$frameworkid,PDO::PARAM_INT);
                }
		$stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
	$db->commit();
    }catch (PDOException $e){
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }
    if(!empty($_GET["respond"])) {
        $result_all = array();
        foreach($results as $row) {
            $result_all[] = $row;
        }

        foreach($result_all as $result_key => $result_val) {
            if(isset($result_val["start_time"])) {
                $result_all[$result_key]["start_time"] = convertTime($result_val["start_time"]);
            }
            if(isset($result_val["end_time"])) {
                $result_all[$result_key]["end_time"] = convertTime($result_val["end_time"]);
            }
        }
        echo json_encode($result_all);
    }
}
?>

