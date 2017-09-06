<?php
/**
 * This file is used for processing log files in order to provide processed data to client's browser for file rendering.
 * With server side data processing load on client's browser for rendering log file is very less. We provide
 * processed data in JSON format to browser which then use by c3js library for rendering.
 */


$div_id = 1;
$header_validity = array();

/**
 * This function verifies the PLT content for checking if each row start with timestamp and all subsequent values are
 * numeric. Also set global variable header_validity which keeps track of all the columns in a PLT file
 *
 * @param $file - Input PLT file path which we are validating
 *
 * @return int $ret_code - which is return code where 0 denotes file is valid, 1 denotes that column 1 is not timestamp and
 * 2 denotes that file doesn't exists.
 */

function verifyPltContentForGraph($file)
{
    global $header_validity;
    $ret_code = 0;
    $time_regex = '/^(\d{4})-(\d{2})-(\d{2})T(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/';
    unset($handle);
    $process_header = 1;
    $header_validity = array();
    $column_name = array();
    $valid_path = validate_file_path($file);
    if ($valid_path === false) {
        $ret_code = 3;
        return $ret_code;
    }
    $handle = fopen($file, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line_arr = explode(',', $line);
            if ($process_header) {
                for ($i = 0; $i < sizeof($line_arr); $i++) {
                    $col = str_replace("\r\n", '', $line_arr[$i]);
                    $col = str_replace("\n", '', $col);
                    $col = str_replace('"', '', $col);
                    $header_validity[$col] = 1;
                    $column_name[] = $col;
                }
                $process_header = 0;
                continue;
            }
            $line_arr[0] = str_replace("\r\n", '', $line_arr[0]);
            $line_arr[0] = str_replace("\n", '', $line_arr[0]);
            $line_arr[0] = str_replace('"', '', $line_arr[0]);
            if (preg_match($time_regex, $line_arr[0])) {
                for ($i = 1; $i < sizeof($line_arr); $i++) {
                    $col = str_replace("\r\n", '', $line_arr[$i]);
                    $col = str_replace("\n", '', $col);
                    $col = str_replace('"', '', $col);
                    $col = str_replace('%', '', $col);
                    if (!is_numeric($col)) {
                        $header_validity[$column_name[$i]] = 0;
                    }
                }
            } else {
                $ret_code = 1;
                break;
            }
        }
    } else {
        $ret_code = 2;
        return $ret_code;
    }
    return $ret_code;
}

/**
 * This function set the graph rendering type. As we have different graph rendering requirements for TOP output
 * files and all other PLT files, this function just set the graph mode based on file name and then
 * calls buildTestReportGraph function for processing the data. TOP output files are
 * cpu_usage.plt, memory_usage.plt and res_memory_usage.plt
 *
 * @param $div_id - this is the div panel id on test report page in which we render this graph
 * @param $file_paths - this is an array which contains actual file path of output file for all tests.
 * @param $s_compids_str - comma seperated string which contains all test IDs
 * @param $title - Graph title
 */

function buildTestReportGraphView($div_id, $file_paths, $s_compids_str, $title)
{

    if ((strpos($file_paths, 'cpu_usage') !== false) or (strpos($file_paths, 'memory_usage') !== false)) {
        $graph_mode = 0;
    } else {
        $graph_mode = 1;
    }
    buildTestReportGraph($div_id, $file_paths, $s_compids_str, $title, $graph_mode);
}

/**
 * This is actual function for data processing of PLT files for test report page. For any normal PLT file it is simple
 * straight forward graph rendering.
 *
 * For TOP output plt files if filename is mentioned with colon separated string in test report setting section of
 * framework definition then we add all the values of multiple processes with same name and renders
 * it on the graph.
 *
 * For example, if user mention file name as "cpu_usage.plt:httpd" then on test report page this function will add all
 * httpd process having different PIDs at particular time and display it on graph
 *
 * @param $div_id - this is the div panel id on test report page in which we render this graph
 * @param $file_paths - this is an array which contains actual file path of output file for all tests.
 * @param $s_compids_str - comma seperated string which contains all test IDs
 * @param $title - Graph title
 * @param $graph_mode - 0 for TOP plt files, 1 for other plt files
 */

function buildTestReportGraph($div_id, $file_paths, $s_compids_str, $title, $graph_mode)
{
    global $header_validity;
    $file_arr = explode(',', $file_paths);
    $testid_arr = explode(',', $s_compids_str);
    $file = array_shift($file_arr);
    $process_header = 1;
    $time_offset = 1;
    $column_data = array();
    $column_map = array();
    $xaxis_data = array();
    $header_validity = array();
    $filename = explode(':', $file)[0];
    if (array_key_exists(1, explode(':', $file))) {
        if ($graph_mode) {
            $column = '"' . explode(':', $file)[1] . '"';
        } else {
            $column = explode(':', $file)[1];
        }
    }

    if ($graph_mode) {
        $column_index = 0;
    } else {
        $column_index = array();
    }
    // Verifying PLT content, if ret_code is invalid then we are displaying error else we proceed with data processing.
    $error = verifyPltContentForGraph($filename);
    if ($error == 1) {
        $error_msg = "Cannot parse time format - Invalid time format";
        $error_type = 1;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
        return;
    } else if ($error == 2) {
        $error_msg = "Not able to read file";
        $error_type = 1;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
        return;
    } else if ($error == 3) {
        $error_msg = "Cannot access file or invalid URL";
        $error_type = 1;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
        return;
    } else {
        // Processing data for main test for which we are rendering PLT file
        $handle = fopen($filename, "r");
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            $line_arr = explode(',', $line);
            if ($process_header) {
                // Processing PLT header
                if (!empty($column)) {
                    // If user has provided coulmn name in test report page settings
                    $column = str_replace('"', '', $column);
                    if ($graph_mode) {
                        for ($i = 1; $i < sizeof($line_arr); $i++) {
                            $data = str_replace("\n", '', $line_arr[$i]);
                            $data = str_replace("\r\n", '', $data);
                            $data = str_replace('"', '', $data);
                            $data = str_replace("\r", '', $data);
                            if (strpos($data, $column) !== false) {
                                $column_index = $i;
                                break;
                            }
                        }
                    } else {
                        // For TOP output plt we save all column with contain proccess name in a map to add the values
                        for ($i = 1; $i < sizeof($line_arr); $i++) {
                            $data = str_replace("\n", '', $line_arr[$i]);
                            $data = str_replace("\r\n", '', $data);
                            $data = str_replace('"', '', $data);
                            $data = str_replace("\r", '', $data);
                            if (strpos($data, $column) !== false) {
                                $column_index[] = $i;
                                $column_map[] = $data;
                            }
                        }
                    }
                } else {
                    // No column name provided, then by default we pick first column
                    if ($graph_mode) {
                        $data = str_replace("\n", '', $line_arr[1]);
                        $column_index = 1;
                        $column = $data;
                    } else {
                        $error_msg = "Process name not mentioned in framework definition";
                        $error_type = 1;
                        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
                        return;
                    }

                }
                // Handling error if user provide invalid process name or column name
                if ($graph_mode) {
                    if ($column_index == 0) {
                        $error_msg = "Column Not found - Check Framework Definition";
                        $error_type = 1;
                        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
                        return;
                    }
                } else {
                    if (sizeof($column_index) == 0) {
                        $error_msg = "Process Not found - Check Framework Definition";
                        $error_type = 1;
                        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
                        return;
                    }
                }
                // Verifying if data is consistent in plt file
                $err_code = 0;
                if ($graph_mode) {
                    if ($header_validity[$column] == 0) {
                        break;
                    }
                } else {
                    foreach ($column_map as $column_val) {
                        if ($header_validity[$column_val] == 0) {
                            $err_code = 1;
                            break;
                        }
                    }
                    if ($err_code) {
                        break;
                    }
                }
                $xaxis_data[$testid_arr[$count]] = array();
                $column_data[$testid_arr[$count]] = array();
                $process_header = 0;
                continue;
            }
            // Saving time offset value keeping first time value as base value (01:00:00:00)
            if ($time_offset && !$process_header) {
                $offset_datetime = new DateTime($line_arr[0]);
                $time_offset = 0;
            }

            // Process further data of PLT file
            // Subtracting the time mentioned for each row with base value
            // Saving it in array which denotes xaxis value for a particular testid

            $current_datetime = new DateTime($line_arr[0]);
            $current_datetime->add(new DateInterval('P1D'));
            $interval = date_diff($current_datetime, $offset_datetime);
            $interval_string = $interval->format("%D:%H:%I:%S");
            $xaxis_data[$testid_arr[$count]][] = $interval_string;

            // After processing time, further processing data in a particular row
            if ($graph_mode) {
                // In this graph mode, we will save data as array in a main array '$column_data' with testid as array key.
                $data = str_replace("\n", '', $line_arr[$column_index]);
                $data = str_replace("\r\n", '', $data);
                $column_data[$testid_arr[$count]][] = $data;
            } else {
                // In this graph mode, we are only considering column which matches with given process name and adding values.
                $total = 0;
                foreach ($column_index as $column_val) {
                    $data = str_replace("\n", '', $line_arr[$column_val]);
                    $data = str_replace("\r\n", '', $data);
                    $total += $data;
                }
                $column_data[$testid_arr[$count]][] = $total;
            }
        }
        fclose($handle);
        unset($handle);
    }

    if (sizeof($file_arr) > 0) {
        foreach ($file_arr as $file) {
            // Processing plt data for other test mentioned in comparison field, Same as above
            $process_header = 1;
            $time_offset = 1;
            $count++;
            $header_validity = array();
            if ($graph_mode) {
                $column_index = 0;
            } else {
                $column_index = array();
            }

            $column_map = array();
            $filename = explode(':', $file)[0];
            $error = verifyPltContentForGraph($filename);
            if ($error == 1) {
                continue;
            } else if ($error == 2) {
                continue;
            } else if ($error == 3) {
                continue;
            } else {
                $handle = fopen($filename, "r");
                while (($line = fgets($handle)) !== false) {
                    $line_arr = explode(',', $line);
                    if ($process_header) {
                        if ($graph_mode) {
                            for ($i = 1; $i < sizeof($line_arr); $i++) {
                                $data = str_replace("\n", '', $line_arr[$i]);
                                $data = str_replace("\r\n", '', $data);
                                if (strpos($data, $column) !== false) {
                                    $column_index = $i;
                                    break;
                                }
                            }
                        } else {
                            for ($i = 1; $i < sizeof($line_arr); $i++) {
                                $data = str_replace("\n", '', $line_arr[$i]);
                                $data = str_replace("\r\n", '', $data);
                                if (strpos($data, $column) !== false) {
                                    $column_index[] = $i;
                                    $column_map[] = $data;
                                }
                            }
                        }
                        if ($graph_mode) {
                            if ($column_index == 0) {
                                break;
                            }
                        } else {
                            if (sizeof($column_index) == 0) {
                                break;
                            }
                        }

                        $err_code = 0;
                        if ($graph_mode) {
                            if ($header_validity[$column] == 0) {
                                break;
                            }
                        } else {
                            foreach ($column_map as $column_val) {
                                if ($header_validity[$column_val] == 0) {
                                    $err_code = 1;
                                    break;
                                }
                            }
                            if ($err_code) {
                                break;
                            }
                        }
                        $xaxis_data[$testid_arr[$count]] = array();
                        $column_data[$testid_arr[$count]] = array();
                        $process_header = 0;
                        continue;
                    }

                    if ($time_offset && !$process_header) {
                        $offset_datetime = new DateTime($line_arr[0]);
                        $time_offset = 0;
                    }
                    $current_datetime = new DateTime($line_arr[0]);
                    $current_datetime->add(new DateInterval('P1D'));
                    $interval = date_diff($current_datetime, $offset_datetime);
                    $interval_string = $interval->format("%D:%H:%I:%S");
                    $xaxis_data[$testid_arr[$count]][] = $interval_string;

                    if ($graph_mode) {
                        $data = str_replace("\n", '', $line_arr[$column_index]);
                        $data = str_replace("\r\n", '', $data);
                        $column_data[$testid_arr[$count]][] = $data;
                    } else {
                        $total = 0;
                        foreach ($column_index as $column_val) {
                            $data = str_replace("\n", '', $line_arr[$column_val]);
                            $data = str_replace("\r\n", '', $data);
                            $total += $data;
                        }
                        $column_data[$testid_arr[$count]][] = $total;
                    }
                }
                fclose($handle);
                unset($handle);
            }
        }
    }

    $graph_data = array();
    $metrics_array = array();
    $xs_array = array();

    // Evaluating other stats like mix, max, avg for all tests.
    foreach ($testid_arr as $testid) {
        if (array_key_exists($testid, $column_data)) {
            $array_column = $xaxis_data[$testid];
            array_unshift($array_column, "Time_" . $testid);
            $graph_data[] = $array_column;
            $array_column = $column_data[$testid];
            if (!empty($array_column)) {
                $metrics_array[$testid]['max'] = max($array_column);
                $metrics_array[$testid]['min'] = min($array_column);
                $metrics_array[$testid]['avg'] = array_sum($array_column) / count($array_column);
            }
            array_unshift($array_column, $testid);
            $graph_data[] = $array_column;
            $xs_array[$testid] = "Time_" . $testid;
        } else {
            $metrics_array[$testid]['max'] = 'NaN';
            $metrics_array[$testid]['min'] = 'NaN';
            $metrics_array[$testid]['avg'] = 'NaN';
        }
    }
    // Changing array into json to transfer data onto client's browser for graph rendering
    $graph_data_json = json_encode($graph_data);
    $column_json = json_encode($testid_arr);
    $metric_data = json_encode($metrics_array);
    $xs_json = json_encode($xs_array);
    $x_value = '';
    echo "    <div class='c3-graph-panel' id='c3item$div_id'></div>\n";
    echo "    <div class='metric-footer'>\n";
    echo "      <table class='table table-condensed sortable' id='c3footer$div_id'>\n";
    echo "        <thead>\n";
    echo "          <tr>\n";
    echo "            <th>Test</th>\n";
    echo "            <th>Min</th>\n";
    echo "            <th>Max</th>\n";
    echo "            <th>Average</th>\n";
    echo "          </tr>\n";
    echo "        </thead>\n";
    echo "      </table>\n";
    echo "    </div>\n";
    echo "    <script>buildGraph('$graph_data_json', '$column_json', '$metric_data', '$x_value' ,'$xs_json', '$title','$div_id');</script>\n";
}

/**
 * This function call different functions for data processing of different files. As we have three different types of
 * graph rendering, based on file name we call these functions.
 *
 * @param $file_paths - Comma separated string with file path details of a log file for each test (base test and comparison test)
 * @param $s_compids_str - Comma separated string of all testid's (base test and comparison test)
 * @param $act_file - Actual filename which we are rendering on this output page
 */
function buildOutputGraphView($file_paths, $s_compids_str, $act_file)
{
    if ((strpos($file_paths, 'cpu_usage') !== false) or (strpos($file_paths, 'memory_usage') !== false)) {
        buildGraphFilters($file_paths, $s_compids_str, $act_file);
    } elseif ((strpos($file_paths, 'docker_cpu') !== false) or (strpos($file_paths, 'docker_memory') !== false)) {
        buildSingleGraphView($file_paths, $s_compids_str, $act_file);
    } else {
        buildColumnGraphView($file_paths, $s_compids_str, $act_file);
    }
}

/**
 * This function renders all columns in a single graph. So for docker plt files we want to render stats from all the
 * containers in a single graph. So one graph for each test file.
 *
 * @param $file_paths - Comma separated string with file path details of a log file for each test (base test and comparison test)
 * @param $s_compids_str - Comma separated string of all testid's (base test and comparison test)
 * @param $act_file - Actual filename which we are rendering on this output page
 */

function buildSingleGraphView($file_paths, $s_compids_str, $act_file)
{

    global $div_id;
    global $header_validity;
    $div_id = 1;
    $file_arr = explode(',', $file_paths);
    $testid_arr = explode(',', $s_compids_str);

    $counter = 0;
    // Starting data processing of log file for all the tests (base test and comparison test if any)
    foreach ($file_arr as $file) {
        $title = $act_file . " : " . $testid_arr[$counter];
        $counter++;
        // Verifying PLT content, if ret_code is invalid then we are displaying error else we proceed with data processing.
        $error = verifyPltContentForGraph($file);
        if ($error == 1) {
            $error_msg = "Cannot parse time format - Invalid time format";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        } else if ($error == 2) {
            $error_msg = "Not able to read file";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        } else if ($error == 3) {
            $error_msg = "Cannot access file or invalid URL";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        }
        $process_header = 1;
        $time_offset = 1;
        $graph_data = array();
        $column_name = array();
        $handle = fopen($file, "r");

        while (($line = fgets($handle)) !== false) {
            $line_arr = explode(',', $line);
            if ($process_header) {
                // Processing PLT header
                foreach ($line_arr as $col) {
                    $col = str_replace("\n", '', $col);
                    $col = str_replace("\r", '', $col);
                    $temp_array = array();
                    $temp_array[] = $col;
                    $graph_data[] = $temp_array;
                    $column_name[] = $col;
                }
                $process_header = 0;
                continue;
            }
            // Saving time offset value keeping first time value as base value
            if ($time_offset && !$process_header) {
                $offset_datetime = new DateTime($line_arr[0]);
                $time_offset = 0;
            }
            // Process further data of PLT file
            // Subtracting the time mentioned for each row with base value
            // Saving it in array at 0th index which will be x axis values for this graph
            $current_datetime = new DateTime($line_arr[0]);
            $current_datetime->add(new DateInterval('P1D'));
            $interval = date_diff($current_datetime, $offset_datetime);
            $interval_string = $interval->format("%D:%H:%I:%S");
            $graph_data[0][] = $interval_string;
            for ($i = 1; $i < sizeof($line_arr); $i++) {
                // Now all subsequent values for each column will be saved in main array '$graph_data' corresponding
                // array index
                $data = str_replace("\n", '', $line_arr[$i]);
                $data = str_replace("\r", '', $data);
                $data = str_replace("%", '', $data);
                $graph_data[$i][] = $data;
            }
        }
        fclose($handle);

        $metrics_array = array();
        $count = 0;
        // Evaluating other stats like mix, max, avg for all tests.
        foreach ($graph_data as $dataset) {
            if (strcasecmp($dataset[0], "Time") == 0) {
                $count++;
                continue;
            }
            if (!$header_validity[$dataset[0]]) {
                array_splice($graph_data, $count, 1);
                $main_key = $dataset[0];
                $metrics_array[$main_key] = array();
                $metrics_array[$main_key]['max'] = 'NaN';
                $metrics_array[$main_key]['min'] = 'NaN';
                $metrics_array[$main_key]['avg'] = 'NaN';
                continue;
            }
            $main_key = $dataset[0];
            $metrics_array[$main_key] = array();
            array_shift($dataset);
            if (!empty($dataset)) {
                $metrics_array[$main_key]['max'] = max($dataset);
                $metrics_array[$main_key]['min'] = min($dataset);
                $metrics_array[$main_key]['avg'] = array_sum($dataset) / count($dataset);
            }
            $count++;
        }

        // Changing array into json to transfer data onto client's browser for graph rendering
        array_shift($column_name);
        $column_json = json_encode($column_name);
        $graph_data_json = json_encode($graph_data);
        $metric_data = json_encode($metrics_array);
        $x_value = 'Time';
        $xs_json = "{}";
        echo "    <script>buildOutputPageGraphView('$graph_data_json', '$column_json', '$metric_data', '$x_value' ,'$xs_json', '$title','$div_id');</script>\n";
        $div_id++;
    }
}

/**
 * This function helps in data processing of TOP output files i.e. cpu_usage.plt, memory_usage.plt and
 * res_memory_usage.plt
 *
 * With this function, we read plt file and fetch all process names in order to provide ability to user to select
 * processes in which he/she is interested and then we render graph for those processes only. This reduce
 * the load on client's browser when user perform some interaction action on graph. Maximum we allow
 * rendering of 10 processes (configurable in output.js)
 *
 * @param $file_paths - Comma separated string with file path details of a log file for each test (base test and comparison test)
 * @param $s_compids_str - Comma separated string of all testid's (base test and comparison test)
 * @param $act_file - Actual filename which we are rendering on this output page
 */

function buildGraphFilters($file_paths, $s_compids_str, $act_file)
{
    global $div_id;
    global $header_validity;
    $div_id = 1;
    $file_arr = explode(',', $file_paths);
    $testid_arr = explode(',', $s_compids_str);

    $counter = 0;
    foreach ($file_arr as $file) {
        $title = $act_file . " : " . $testid_arr[$counter];
        // Verifying PLT content, if ret_code is invalid then we are displaying error else we proceed with column reading
        $error = verifyPltContentForGraph($file);
        if ($error == 1) {
            $error_msg = "Cannot parse time format - Invalid time format";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        } else if ($error == 2) {
            $error_msg = "Not able to read file";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        } else if ($error == 3) {
            $error_msg = "Cannot access file or invalid URL";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        }
        $column_name = array();
        $handle = fopen($file, "r");
        $line = fgets($handle);
        $line_arr = explode(',', $line);
        for ($i = 1; $i < count($line_arr); $i++) {
            $col = str_replace("\r\n", '', $line_arr[$i]);
            $column_name[] = $col;
        }
        // we sort the column alphabetically and display all processes with same starting letter in single row.
        // This gives user a flexibility to select multiple processes with same name in single check
        $sorted_colmn = array();
        sort($column_name);
        foreach ($column_name as $col) {
            $key = strtoupper($col[0]);
            if (array_key_exists($key, $sorted_colmn)) {
                $sorted_colmn[$key][] = $col;
            } else {
                $sorted_colmn[$key] = array();
                $sorted_colmn[$key][] = $col;
            }
        }
        $tablewidth = max(array_map('count', $sorted_colmn));
        ?>
        <div class="panel panel-info partition-1" style="margin-bottom:20px;">
            <div class="panel-heading">
                <button type='button' class='btn-sm' id='filter-toggle' data-toggle='collapse'
                        data-target=<?php echo "#collapse" . $div_id; ?>><span
                            class='glyphicon glyphicon glyphicon-plus'></span></button>
                <p style='margin:0px 0px 0px 45px;padding-top:4px;'> Process Filters
                    : <?php echo $testid_arr[$counter] ?></p>
            </div>
            <div id=<?php echo "collapse" . $div_id; ?> class='panel-collapse collapse in graph-panel
            '>
            <div class="panel-body">
                <p style="margin-top:5px;">Select processes for graph display (Max 10)</p>
                <p style="color:#0088cc;">
                    <small>Alphabetically sorted <i class="fa fa-long-arrow-down" aria-hidden="true"></i></small>
                </p>
                <div id="filter-table-div">
                    <form class="form-horizontal zero-margin proc_filter_form" role="form">
                        <table class="table table-condensed filter-table">
                            <tbody>
                            <?php
                            foreach ($sorted_colmn as $key => $value) {
                                echo "<tr>";
                                echo "<td class='success'><input type='checkbox' name='group-$key$div_id' class='group-checkbox'/> $key</td>";
                                $x = 0;
                                $proc_list = 'proc_list' . $div_id . '[]';
                                foreach ($sorted_colmn[$key] as $item) {
                                    echo "<td><input type='checkbox' name='$proc_list' id='group[$key$div_id]' value='$item'/> $item</td>";
                                    $x++;
                                }
                                $y = $tablewidth - $x;
                                if ($y > 0) {
                                    echo "<td colspan=$y></td>";
                                }
                                echo "</tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                        <br>
                        <input type="hidden" name="file" value="<?php echo $file ?>"/>
                        <input type="hidden" name="div_id" value="<?php echo $div_id ?>"/>
                        <input type="hidden" name="title" value="<?php echo $title ?>"/>
                        <button type="submit" class="btn btn-xs btn-primary btn-search-submit">Submit</button>
                    </form>
                </div>
            </div>
        </div>
        </div>
        <?php
        echo "<div class='proc-graph-panel' id='graph-$div_id'></div>";
        $div_id++;
        $counter++;
    }
}

/**
 * This function reads the column_list selected by the user and does data processing for these columns only and returns
 * the processed data for graph rendering in a form of JSON string
 *
 * @param $file - Log file which we are rendering
 * @param $column_list - Column list of all column name selected by user for graph rendering
 * @return string - processed JSON data
 */

function buildGraphDataForFilteredColumns($file, $column_list)
{
    global $header_validity;
    $process_header = 1;
    $time_offset = 1;
    $graph_data = array();
    $column_name = array();
    $column_map = array();
    $handle = fopen($file, "r");
    // Verifying PLT content, if ret_code is invalid then we are displaying error else we proceed with data processing
    $error = verifyPltContentForGraph($file);
    if ($error == 1) {
        $error_msg = "Cannot parse time format - Invalid time format";
        return $error_msg;
    } else if ($error == 2) {
        $error_msg = "Not able to read file";
        return $error_msg;
    } else if ($error == 3) {
        $error_msg = "Cannot access file or invalid URL";
        return $error_msg;
    }
    while (($line = fgets($handle)) !== false) {
        $line_arr = explode(',', $line);
        if ($process_header) {
            // Processing PLT header
            $col = $line_arr[0];
            $temp_array = array();
            $temp_array[] = $col;
            $graph_data[] = $temp_array;
            $column_name[] = $col;
            for ($x = 0; $x < count($line_arr); $x++) {
                $col = str_replace("\r\n", '', $line_arr[$x]);
                if (in_array($col, $column_list)) {
                    // Saving column index for all columns selected by user using process filters
                    $temp_array = array();
                    $temp_array[] = $col;
                    $graph_data[] = $temp_array;
                    $column_name[] = $col;
                    $column_map[] = $x;
                }
            }
            $process_header = 0;
            continue;
        }

        // Saving time offset value keeping first time value as base value (01:00:00:00)
        if ($time_offset && !$process_header) {
            $offset_datetime = new DateTime($line_arr[0]);
            $time_offset = 0;
        }

        // Process further data of PLT file
        // Subtracting the time mentioned for each row with base value
        // Saving it in array at 0th index which will be x axis values for this graph

        $current_datetime = new DateTime($line_arr[0]);
        $current_datetime->add(new DateInterval('P1D'));
        $interval = date_diff($current_datetime, $offset_datetime);
        $interval_string = $interval->format("%D:%H:%I:%S");
        $graph_data[0][] = $interval_string;
        $i = 1;
        foreach ($column_map as $y) {
            // Now all subsequent values for each column from column map will be saved in main array '$graph_data'
            $data = str_replace("\r\n", '', $line_arr[$y]);
            $graph_data[$i++][] = $data;
        }
    }
    fclose($handle);
    $metrics_array = array();
    $count = 0;
    // Evaluating other stats like mix, max, avg for all tests.
    foreach ($graph_data as $dataset) {
        if (strcasecmp($dataset[0], "Time") == 0) {
            $count++;
            continue;
        }
        if (!$header_validity[$dataset[0]]) {
            array_splice($graph_data, $count, 1);
            $main_key = $dataset[0];
            $metrics_array[$main_key] = array();
            $metrics_array[$main_key]['max'] = 'NaN';
            $metrics_array[$main_key]['min'] = 'NaN';
            $metrics_array[$main_key]['avg'] = 'NaN';
            continue;
        }
        $main_key = $dataset[0];
        $metrics_array[$main_key] = array();
        array_shift($dataset);
        if (!empty($dataset)) {
            $metrics_array[$main_key]['max'] = max($dataset);
            $metrics_array[$main_key]['min'] = min($dataset);
            $metrics_array[$main_key]['avg'] = array_sum($dataset) / count($dataset);
        }
        $count++;
    }
    // Changing array into json to transfer data onto client's browser for graph rendering
    array_shift($column_name);
    $column_json = json_encode($column_name);
    $graph_data_json = json_encode($graph_data);
    $metric_data = json_encode($metrics_array);
    $x_value = 'Time';
    $xs_json = "{}";

    $response = array();
    $response['column_json'] = $column_json;
    $response['graph_data_json'] = $graph_data_json;
    $response['metric_data'] = $metric_data;
    $response['x_value'] = $x_value;
    $response['xs_json'] = $xs_json;

    $response_json = json_encode($response);
    return $response_json;
}

/**
 * This function process plt file data for column wise graph rendering. so each column will be rendered as a same graph.
 * During comparison of a test, value from same column of a log file will be rendered on same graph
 *
 * @param $file_paths - Comma separated string with file path details of a log file for each test (base test and comparison test)
 * @param $s_compids_str - Comma separated string of all testid's (base test and comparison test)
 * @param $act_file - Actual filename which we are rendering on this output page
 */

function buildColumnGraphView($file_paths, $s_compids_str, $act_file)
{
    global $div_id;
    global $header_validity;
    $div_id = 1;
    $file_arr = explode(',', $file_paths);
    $testid_arr = explode(',', $s_compids_str);
    $file = array_shift($file_arr);
    $process_header = 1;
    $time_offset = 1;
    $column_data = array();
    $xaxis_data = array();
    $column_map = array();
    $header_validity = array();
    // Verifying PLT content, if ret_code is invalid then we are displaying error else we proceed with data processing.
    $error = verifyPltContentForGraph($file);
    if ($error == 1) {
        $error_msg = "Cannot parse time format - Invalid time format";
        $error_type = 2;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$act_file','$error_type'); </script>\n";
        return;
    } else if ($error == 2) {
        $error_type = 2;
        $error_msg = "Not able to read file";
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$act_file','$error_type'); </script>\n";
        return;
    } else if ($error == 3) {
        $error_type = 2;
        $error_msg = "Cannot access file or invalid URL";
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$act_file','$error_type'); </script>\n";
        return;
    } else {
        // Processing data for base test for which we are rendering plt file
        $handle = fopen($file, "r");
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            $line_arr = explode(',', $line);
            if ($process_header) {
                // Processing plt header
                $xaxis_data[$testid_arr[$count]] = array();
                for ($i = 1; $i < sizeof($line_arr); $i++) {
                    // $column_data array has column name as key and then each array key is corresponds to another
                    // array indexed based on testid which actually holds value of that column for this test.
                    $data = str_replace("\n", '', $line_arr[$i]);
                    $data = str_replace("\r", '', $data);
                    $data = str_replace('"', '', $data);
                    $temp_array = array();
                    $temp_array[$testid_arr[$count]] = array();
                    $column_data[$data] = $temp_array;
                    $column_map[] = $data;
                }
                $process_header = 0;
                continue;
            }
            if ($time_offset && !$process_header) {
                $offset_datetime = new DateTime($line_arr[0]);
                $time_offset = 0;
            }
            $current_datetime = new DateTime($line_arr[0]);
            $current_datetime->add(new DateInterval('P1D'));
            $interval = date_diff($current_datetime, $offset_datetime);
            $interval_string = $interval->format("%D:%H:%I:%S");
            $xaxis_data[$testid_arr[$count]][] = $interval_string;
            for ($i = 1; $i < sizeof($line_arr); $i++) {
                $data = str_replace("\n", '', $line_arr[$i]);
                $data = str_replace("\r", '', $data);
                $data = str_replace('%', '', $data);
                $column_data[$column_map[$i - 1]][$testid_arr[$count]][] = $data;
            }
        }
        fclose($handle);
        unset($handle);
        foreach ($column_map as $col) {
            if (!$header_validity[$col]) {
                unset($column_data[$col][$testid_arr[$count]]);
            }
        }
    }

    if (sizeof($file_arr) > 0) {
        foreach ($file_arr as $file) {
            // Processing plt data for other test mentioned in comparison field, Same as above
            $process_header = 1;
            $time_offset = 1;
            $count++;
            $column_map = array();
            $header_validity = array();
            $error = verifyPltContentForGraph($file);
            if ($error == 1) {
                continue;
            } else if ($error == 2) {
                continue;
            } else if ($error == 3) {
                continue;
            } else {
                $handle = fopen($file, "r");
                while (($line = fgets($handle)) !== false) {
                    $line_arr = explode(',', $line);
                    if ($process_header) {
                        $xaxis_data[$testid_arr[$count]] = array();
                        for ($i = 1; $i < sizeof($line_arr); $i++) {
                            $data = str_replace("\n", '', $line_arr[$i]);
                            $data = str_replace('"', '', $data);
                            $data = str_replace("\r", '', $data);
                            if (array_key_exists($data, $column_data)) {
                                $column_data[$data][$testid_arr[$count]] = array();
                            }
                            $column_map[] = $data;
                        }
                        $process_header = 0;
                        continue;
                    }
                    if ($time_offset && !$process_header) {
                        $offset_datetime = new DateTime($line_arr[0]);
                        $time_offset = 0;
                    }
                    $current_datetime = new DateTime($line_arr[0]);
                    $current_datetime->add(new DateInterval('P1D'));
                    $interval = date_diff($current_datetime, $offset_datetime);
                    $interval_string = $interval->format("%D:%H:%I:%S");
                    $xaxis_data[$testid_arr[$count]][] = $interval_string;
                    for ($i = 1; $i < sizeof($line_arr); $i++) {
                        $data = str_replace("\n", '', $line_arr[$i]);
                        $data = str_replace("\r", '', $data);
                        $data = str_replace('%', '', $data);
                        if (array_key_exists($column_map[$i - 1], $column_data)) {
                            $column_data[$column_map[$i - 1]][$testid_arr[$count]][] = $data;
                        }
                    }
                }
                fclose($handle);
                unset($handle);
                foreach ($column_map as $col) {
                    if (!$header_validity[$col]) {
                        if (array_key_exists($col, $column_data)) {
                            unset($column_data[$col][$testid_arr[$count]]);
                        }
                    }
                }
            }
        }
    }
    // Evaluating other stats like mix, max, avg for all tests.
    foreach ($column_data as $key => $value) {
        $graph_data = array();
        $metrics_array = array();
        $title = $act_file . " : " . trim($key, '"');
        $xs_array = array();
        foreach ($testid_arr as $testid) {
            if (array_key_exists($testid, $column_data[$key])) {
                $array_column = $xaxis_data[$testid];
                array_unshift($array_column, "Time_" . $testid);
                $graph_data[] = $array_column;
                $array_column = $column_data[$key][$testid];
                if (!empty($array_column)) {
                    $metrics_array[$testid]['max'] = max($array_column);
                    $metrics_array[$testid]['min'] = min($array_column);
                    $metrics_array[$testid]['avg'] = array_sum($array_column) / count($array_column);
                }
                array_unshift($array_column, $testid);
                $graph_data[] = $array_column;
                $xs_array[$testid] = "Time_" . $testid;
            } else {
                $metrics_array[$testid]['max'] = 'NaN';
                $metrics_array[$testid]['min'] = 'NaN';
                $metrics_array[$testid]['avg'] = 'NaN';
            }
        }
        // Changing array into json to transfer data onto client's browser for graph rendering
        $graph_data_json = json_encode($graph_data);
        $column_json = json_encode($testid_arr);
        $metric_data = json_encode($metrics_array);
        $xs_json = json_encode($xs_array);
        $x_value = '';
        echo "    <script>buildOutputPageGraphView('$graph_data_json', '$column_json', '$metric_data', '$x_value' ,'$xs_json', '$title','$div_id');</script>\n";
        $div_id++;
    }
}

/**
 * This function read logs files and save the content of each file for a particular test as string in an array with
 * testid as index.
 *
 * @param $filepaths - Comma separated string with file path details of a log file for each test (base test and comparison test)
 * @param $test_ids - Comma separated string of all the testids (base test and comparison test)
 * @return array - An array with file content as array value and testid as array index or it return string with error msg
 */

function buildTestCompareData($filepaths, $test_ids)
{
    $file_arr = explode(',', $filepaths);
    $test_id_arr = explode(',', $test_ids);
    $ret_data = array();
    $finfo = finfo_open(FILEINFO_MIME);
    for ($i = 0; $i < sizeof($test_id_arr); $i++) {
        $file_data = "";

        // File checks, if file doesn't exists then throw error
        $file_exists = file_exists($file_arr[$i]);
        if (!$file_exists) {
            $file_data = "File Not Found";
            if ($i == 0) {
                return $file_data;
            } else {
                $ret_data[$test_id_arr[$i]] = $file_data;
                continue;
            }
        }
        // Validate the file path of a log file, real path should be inside daytona data directory, this is to protect
        // linux files outside daytona file system
        $valid_path = validate_file_path($file_arr[$i]);
        if ($valid_path === false) {
            $file_data = "Cannot access file or invalid URL";
            if ($i == 0) {
                return $file_data;
            } else {
                $ret_data[$test_id_arr[$i]] = $file_data;
                continue;
            }
        }

        // if file is not readable i.e. executable or binary then display error message
        if (substr(finfo_file($finfo, $file_arr[$i]), 0, 4) !== 'text') {
            $file_data = "Not a text file - unable to read";
            if ($i == 0) {
                return $file_data;
            } else {
                $ret_data[$test_id_arr[$i]] = $file_data;
                continue;
            }
        }

        // Reading file content
        $fileptr = fopen($file_arr[$i], "r");
        if ($fileptr) {
            while (($line = fgets($fileptr)) !== false) {
                $line = str_replace("\r\n", "\\r\n", $line);
                $line = str_replace("\t", "\\t", $line);
                $line = str_replace("\n", "\\n", $line);
                $line = str_replace('"', '', $line);
                $line = str_replace("'", "", $line);
                // Any line which contain "ERROR" as substring will be displayed in Red color, mainly for log files to
                // highlight any error message
                if (strpos($line, "ERROR") !== false) {
                    $line = "<font color=red>" . $line . "</font>";
                }
                $file_data .= $line;
            }
            fclose($fileptr);
        }

        if (strlen($file_data) == 0) {
            $file_data = "Empty File";
        }
        // saving file content in array $ret_data with testid as array index
        $ret_data[$test_id_arr[$i]] = $file_data;
    }
    return $ret_data;
}

