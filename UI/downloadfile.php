<?php

require('lib/auth.php');

if ($userId){

    $filename = getParam("filename");
    $testids = getParam("testids");

    $testid_arr = explode(",",$testids);

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

    $s_compids_arr = explode(",",$testids);
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
        $stmt->bindValue(':testid',$testid_arr[0],PDO::PARAM_INT);
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
	error_log(print_r($test_hosts_result,true));
        foreach ($test_hosts_result as $row){
            if(!isset($test_info_data[$row["testid"]][$row["name"]])) {
                $test_info_data[$row["testid"]][$row["name"]] = array();
            }
            array_push($test_info_data[$row["testid"]][$row["name"]], $row["hostname"]);
        }
    }catch (PDOException $e){
        $db->rollBack();
        returnError("MySQL error: " . $e->getMessage());
    }

    $file_paths = array();
    foreach ($testid_arr as $l_id) {
        $report_path = "test_data/" . $test_info_data["frameworkname"] . "/$l_id/results/" .
            formatFilePath($filename, $test_info_data[$l_id]);
        $valid_path = validate_file_path($report_path);
        if ($valid_path === true){
            if (file_exists($report_path)) {
                array_push($file_paths,$report_path);
            }
        }
    }
    if (sizeof($file_paths) > 0){
        $file_name = basename($filename);
        $zipname = $file_name . "-" . str_replace(",","-",$testids) . ".zip";
        $zippath = "/tmp/" . $zipname;
        $zip = new ZipArchive;
        $zip->open($zippath, ZipArchive::CREATE);
        $counter = 0;
        foreach ($file_paths as $file) {
            $zip->addFile($file,$testid_arr[$counter++] . "-" . basename($file));
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.$zipname);
        header('Content-Length: ' . filesize($zippath));
        ob_clean();
        flush();
        readfile($zippath);
        unlink($zippath);
    }else{
        echo http_response_code(404);
    }
}
