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

    $test_info_sql = "SELECT frameworkname,TestInputData.title,TestInputData.purpose,exechostname,
                  stathostname,priority,timeout,cc_list,
                  TestInputData.creation_time,modified,start_time,end_time FROM TestInputData
                  JOIN ApplicationFrameworkMetadata USING(frameworkid) WHERE testid=$testid_arr[0]";
    $test_info_result = $conn->query($test_info_sql);
    $test_info_data = $test_info_result->fetch_assoc();

    $test_hosts_sql = "SELECT testid,hostassociationid,hostname,name FROM HostAssociation JOIN HostAssociationType
                   USING(hostassociationtypeid) WHERE testid IN ($testids) ORDER BY hostassociationid";
    $test_hosts_result = $conn->query($test_hosts_sql);
    while ($row = $test_hosts_result->fetch_assoc()) {
        if (!isset($test_info_data[$row["testid"]][$row["name"]])) {
            $test_info_data[$row["testid"]][$row["name"]] = array();
        }
        array_push($test_info_data[$row["testid"]][$row["name"]], $row["hostname"]);
    }
    $file_paths = array();
    foreach ($testid_arr as $l_id) {
        $report_path = "test_data/" . $test_info_data["frameworkname"] . "/$l_id/results/" .
            formatFilePath($filename, $test_info_data[$l_id]);
        $report_path_file = strpos($report_path, ":") ? substr($report_path, 0, strpos($report_path, ":")) : $report_path;
        if (file_exists($report_path_file)) {
            array_push($file_paths,$report_path);
        }
    }
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
    readfile($zippath);
    unlink($zippath);
}
