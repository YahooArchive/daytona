<?php
/**
 * Created by PhpStorm.
 * User: dmittal
 * Date: 1/31/17
 * Time: 2:14 PM
 */


$div_id = 1;
$header_validity = array();

function verifyPltContentForGraph($file){
    global $header_validity;
    $ret_code = 0;
    $time_regex = '/^(\d{4})-(\d{2})-(\d{2})T(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/';
    unset($handle);
    $process_header = 1;
    $header_validity = array();
    $column_name = array();
    $valid_path = validate_file_path($file);
    if ($valid_path === false){
        $ret_code = 3;
        return $ret_code;
    }
    $handle = fopen($file, "r");
    if ($handle){
        while (($line = fgets($handle)) !== false) {
            $line_arr = explode(',', $line);
            if($process_header){
                for($i=0; $i<sizeof($line_arr); $i++){
                    $col = str_replace("\r\n",'', $line_arr[$i]);
                    $col = str_replace("\n",'', $col);
		    $col = str_replace('"','', $col);
                    $header_validity[$col] = 1;
                    $column_name[] = $col;
                }
                $process_header = 0;
                continue;
            }
            $line_arr[0] = str_replace("\r\n",'', $line_arr[0]);
            $line_arr[0] = str_replace("\n",'', $line_arr[0]);
	    $line_arr[0] = str_replace('"','', $line_arr[0]);
            if(preg_match($time_regex,$line_arr[0])){
                for($i=1; $i<sizeof($line_arr); $i++){
                    $col = str_replace("\r\n",'', $line_arr[$i]);
                    $col = str_replace("\n",'', $col);
		    $col = str_replace('"','', $col);
                    if(!is_numeric($col)){
                        $header_validity[$column_name[$i]] = 0;
                    }
                }
            }else{
                $ret_code = 1;
                break;
            }
        }
    }else{
        $ret_code = 2;
        return $ret_code;
    }
    return $ret_code;
}

function buildTestReportGraphView($div_id, $file_paths, $s_compids_str, $title){

    if ((strpos($file_paths, 'cpu_usage') !== false) or (strpos($file_paths, 'memory_usage') !== false)) {
        $graph_mode = 0;
    }else{
        $graph_mode = 1;
    }
    buildTestReportGraph($div_id, $file_paths, $s_compids_str, $title, $graph_mode);
}

function buildTestReportGraph($div_id, $file_paths, $s_compids_str, $title, $graph_mode){
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
    $filename = explode(':',$file)[0];
    if(array_key_exists(1,explode(':',$file))){
        if($graph_mode){
            $column = '"' . explode(':',$file)[1] . '"';
        }else{
            $column = explode(':',$file)[1];
        }
    }

    if($graph_mode){
        $column_index = 0;
    }else{
        $column_index = array();
    }
    $error = verifyPltContentForGraph($filename);
    if ($error == 1){
        $error_msg = "Cannot parse time format - Invalid time format";
        $error_type = 1;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
        return;
    }else if ($error == 2) {
        $error_msg = "Not able to read file";
        $error_type = 1;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
        return;
    }else if ($error == 3) {
        $error_msg = "Cannot access file or invalid URL";
        $error_type = 1;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
        return;
    }else{
        $handle = fopen($filename, "r");
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            $line_arr = explode(',', $line);
            if($process_header){
                if (!empty($column)){
		    $column = str_replace('"','', $column);
                    if($graph_mode){
                        for($i = 1;$i < sizeof($line_arr);$i++){
                            $data = str_replace("\n",'', $line_arr[$i]);
                            $data = str_replace("\r\n",'', $data);
			    $data = str_replace('"','', $data);
			    $data = str_replace("\r",'', $data);
                            if(strpos($data,$column) !== false){
                            $column_index = $i;
                            break;
                            }
                        }
                    }else{
                        for($i = 1;$i < sizeof($line_arr);$i++){
                            $data = str_replace("\n",'', $line_arr[$i]);
                            $data = str_replace("\r\n",'', $data);
			    $data = str_replace('"','', $data);
			    $data = str_replace("\r",'', $data);
                            if(strpos($data,$column) !== false){
                                $column_index[] = $i;
                                $column_map[] = $data;
                            }
                        }
                    }
                }else{
                    if($graph_mode){
                        $data = str_replace("\n",'', $line_arr[1]);
                        $column_index = 1;
                        $column = $data;
                    }else{
                        $error_msg = "Process name not mentioned in framework definition";
                        $error_type = 1;
                        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
                        return;
                    }

                }
                if($graph_mode){
                    if($column_index == 0){
                        $error_msg = "Column Not found - Check Framework Definition";
                        $error_type = 1;
                        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
                        return;
                    }
                }else{
                    if(sizeof($column_index) == 0){
                        $error_msg = "Process Not found - Check Framework Definition";
                        $error_type = 1;
                        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
                        return;
                    }
                }
                $err_code = 0;
                if($graph_mode){
                    if($header_validity[$column] == 0){
                        break;
                    }
                }else{
                    foreach($column_map as $column_val){
                        if($header_validity[$column_val] == 0){
                            $err_code = 1;
                            break;
                        }
                    }
                    if ($err_code){
                        break;
                    }
                }
                $xaxis_data[$testid_arr[$count]] = array();
                $column_data[$testid_arr[$count]] = array();
                $process_header = 0;
                continue;
            }

            if ($time_offset && !$process_header){
                $offset_datetime = new DateTime($line_arr[0]);
                $time_offset = 0;
            }

            $current_datetime = new DateTime($line_arr[0]);
            $current_datetime->add(new DateInterval('P1D'));
            $interval = date_diff($current_datetime,$offset_datetime);
            $interval_string = $interval->format("%D:%H:%I:%S");
            $xaxis_data[$testid_arr[$count]][] = $interval_string;

            if($graph_mode){
                $data = str_replace("\n", '', $line_arr[$column_index]);
                $data = str_replace("\r\n",'', $data);
                $column_data[$testid_arr[$count]][] = $data;
            }else{
                $total = 0;
                foreach($column_index as $column_val){
                    $data = str_replace("\n", '', $line_arr[$column_val]);
                    $data = str_replace("\r\n",'', $data);
                    $total += $data;
                }
                $column_data[$testid_arr[$count]][] = $total;
            }
        }
        fclose($handle);
        unset($handle);
    }

    if(sizeof($file_arr) > 0){
        foreach ($file_arr as $file){
            $process_header = 1;
            $time_offset = 1;
            $count++;
            $header_validity = array();
            if ($graph_mode){
                $column_index = 0;
            }else{
                $column_index = array();
            }

            $column_map = array();
            $filename = explode(':',$file)[0];
            $error = verifyPltContentForGraph($filename);
            if ($error == 1){
                continue;
            }else if ($error == 2) {
                continue;
            }else if ($error == 3) {
                continue;
            }else{
                $handle = fopen($filename, "r");
                while (($line = fgets($handle)) !== false) {
                    $line_arr = explode(',', $line);
                    if($process_header){
                        if($graph_mode){
                            for($i = 1;$i < sizeof($line_arr);$i++){
                                $data = str_replace("\n",'', $line_arr[$i]);
                                $data = str_replace("\r\n",'', $data);
                                if(strpos($data,$column) !== false){
                                    $column_index = $i;
                                    break;
                                }
                            }
                        }else{
                            for($i = 1;$i < sizeof($line_arr);$i++){
                                $data = str_replace("\n",'', $line_arr[$i]);
                                $data = str_replace("\r\n",'', $data);
                                if(strpos($data,$column) !== false){
                                    $column_index[] = $i;
                                    $column_map[] = $data;
                                }
                            }
                        }
                        if($graph_mode){
                            if($column_index == 0){
                                break;
                            }
                        }else{
                            if(sizeof($column_index) == 0){
                                break;
                            }
                        }

                        $err_code = 0;
                        if($graph_mode){
                            if($header_validity[$column] == 0){
                                break;
                            }
                        }else{
                            foreach($column_map as $column_val){
                                if($header_validity[$column_val] == 0){
                                    $err_code = 1;
                                    break;
                                }
                            }
                            if ($err_code){
                                break;
                            }
                        }
                        $xaxis_data[$testid_arr[$count]] = array();
                        $column_data[$testid_arr[$count]] = array();
                        $process_header = 0;
                        continue;
                    }

                    if ($time_offset && !$process_header){
                        $offset_datetime = new DateTime($line_arr[0]);
                        $time_offset = 0;
                    }
                    $current_datetime = new DateTime($line_arr[0]);
                    $current_datetime->add(new DateInterval('P1D'));
                    $interval = date_diff($current_datetime,$offset_datetime);
                    $interval_string = $interval->format("%D:%H:%I:%S");
                    $xaxis_data[$testid_arr[$count]][] = $interval_string;

                    if($graph_mode){
                        $data = str_replace("\n", '', $line_arr[$column_index]);
                        $data = str_replace("\r\n",'', $data);
                        $column_data[$testid_arr[$count]][] = $data;
                    }else{
                        $total = 0;
                        foreach($column_index as $column_val){
                            $data = str_replace("\n", '', $line_arr[$column_val]);
                            $data = str_replace("\r\n",'', $data);
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
    foreach($testid_arr as $testid){
        if(array_key_exists($testid,$column_data)){
            $array_column = $xaxis_data[$testid];
            array_unshift($array_column, "Time_" . $testid);
            $graph_data[] = $array_column;
            $array_column = $column_data[$testid];
            if(!empty($array_column)){
                $metrics_array[$testid]['max'] = max($array_column);
                $metrics_array[$testid]['min'] = min($array_column);
                $metrics_array[$testid]['avg'] = array_sum($array_column) / count($array_column);
            }
            array_unshift($array_column,$testid);
            $graph_data[] = $array_column;
            $xs_array[$testid] = "Time_" . $testid;
        }else{
            $metrics_array[$testid]['max'] = 'NaN';
            $metrics_array[$testid]['min'] = 'NaN';
            $metrics_array[$testid]['avg'] = 'NaN';
        }
    }

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

function buildOutputGraphView($file_paths, $s_compids_str, $act_file){
    if ((strpos($file_paths, 'cpu_usage') !== false) or (strpos($file_paths, 'memory_usage') !== false)) {
        buildGraphFilters($file_paths, $s_compids_str, $act_file);
    }else{
        buildColumnGraphView($file_paths, $s_compids_str, $act_file);
    }
}

function buildGraphFilters($file_paths, $s_compids_str, $act_file){
    global $div_id;
    global $header_validity;
    $div_id = 1;
    $file_arr = explode(',', $file_paths);
    $testid_arr = explode(',', $s_compids_str);

    $counter = 0;
    foreach ($file_arr as $file){
        $title = $act_file . " : " . $testid_arr[$counter];
        $error = verifyPltContentForGraph($file);
        if ($error == 1){
            $error_msg = "Cannot parse time format - Invalid time format";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        }else if ($error == 2){
            $error_msg = "Not able to read file";
            $error_type = 2;
            echo "  <script> buildGraphErrorView('$error_msg','$div_id','$title','$error_type'); </script>\n";
            $div_id++;
            continue;
        }else if ($error == 3){
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

        $sorted_colmn = array();
        sort($column_name);
        foreach ($column_name as $col){
            $key = strtoupper($col[0]);
            if (array_key_exists($key, $sorted_colmn)){
                $sorted_colmn[$key][] = $col;
            }else{
                $sorted_colmn[$key] = array();
                $sorted_colmn[$key][] = $col;
            }
        }
        $tablewidth = max(array_map('count', $sorted_colmn));
        ?>
        <div class="panel panel-info partition-1" style="margin-bottom:20px;">
            <div class="panel-heading">
                <button type='button' class='btn-sm' id='filter-toggle' data-toggle='collapse' data-target=<?php echo "#collapse" . $div_id; ?>><span class='glyphicon glyphicon glyphicon-plus'></span></button>
                <p style='margin:0px 0px 0px 45px;padding-top:4px;'> Process Filters : <?php echo $testid_arr[$counter] ?></p>
            </div>
            <div id=<?php echo "collapse" . $div_id; ?> class='panel-collapse collapse in graph-panel'>
            <div class="panel-body">
                <p style="margin-top:5px;">Select processes for graph display (Max 10)</p>
                <p style="color:#0088cc;"><small>Alphabetically sorted <i class="fa fa-long-arrow-down" aria-hidden="true"></i></small></p>
                <div id="filter-table-div">
                    <form class="form-horizontal zero-margin proc_filter_form" role="form">
                        <table class="table table-condensed filter-table">
                            <tbody>
                            <?php
                            foreach ($sorted_colmn as $key => $value){
                                echo "<tr>";
                                echo "<td class='success'><input type='checkbox' name='group-$key$div_id' class='group-checkbox'/> $key</td>";
                                $x = 0;
				$proc_list = 'proc_list' . $div_id . '[]';
                                foreach ($sorted_colmn[$key] as $item){
                                    echo "<td><input type='checkbox' name='$proc_list' id='group[$key$div_id]' value='$item'/> $item</td>";
                                    $x++;
                                }
                                $y = $tablewidth-$x;
                                if ($y > 0){
                                    echo "<td colspan=$y></td>";
                                }
                                echo "</tr>";
                            }
                            ?>
                            </tbody>
                        </table>
			<br>
			<input type="hidden" name="file" value="<?php echo $file ?>" />
			<input type="hidden" name="div_id" value="<?php echo $div_id ?>" />
			<input type="hidden" name="title" value="<?php echo $title ?>" />
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

function buildSingleGraphView($file, $proc_list){
    global $header_validity;
    $process_header = 1;
    $time_offset = 1;
    $graph_data = array();
    $column_name = array();
    $column_map = array();
    $handle = fopen($file, "r");
    $error = verifyPltContentForGraph($file);
    if ($error == 1){
        $error_msg = "Cannot parse time format - Invalid time format";
        return $error_msg;
    }else if ($error == 2){
        $error_msg = "Not able to read file";
        return $error_msg;
    }else if ($error == 3){
        $error_msg = "Cannot access file or invalid URL";
        return $error_msg;
    }
    while (($line = fgets($handle)) !== false) {
        $line_arr = explode(',', $line);
        if($process_header){
	    $col = $line_arr[0];
            $temp_array = array();
            $temp_array[] = $col;
            $graph_data[] = $temp_array;
            $column_name[] = $col;
	    for($x = 0; $x < count($line_arr); $x++) {
                $col = str_replace("\r\n",'', $line_arr[$x]);
                if (in_array($col, $proc_list)){
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
        if ($time_offset && !$process_header){
            $offset_datetime = new DateTime($line_arr[0]);
            $time_offset = 0;
        }
        $current_datetime = new DateTime($line_arr[0]);
        $current_datetime->add(new DateInterval('P1D'));
        $interval = date_diff($current_datetime,$offset_datetime);
        $interval_string = $interval->format("%D:%H:%I:%S");
        $graph_data[0][] = $interval_string;
	$i = 1;
        foreach ($column_map as $y){
            $data = str_replace("\r\n",'', $line_arr[$y]);
            $graph_data[$i++][] = $data;
        }
    }
    fclose($handle);
    $metrics_array = array();
    $count = 0;
    foreach ($graph_data as $dataset){
        if (strcasecmp($dataset[0], "Time") == 0){
            $count++;
            continue;
        }
        if (!$header_validity[$dataset[0]]){
            array_splice($graph_data,$count,1);
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
        if(!empty($dataset)){
            $metrics_array[$main_key]['max'] = max($dataset);
            $metrics_array[$main_key]['min'] = min($dataset);
            $metrics_array[$main_key]['avg'] = array_sum($dataset) / count($dataset);
        }
        $count++;
    }
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

function buildColumnGraphView($file_paths, $s_compids_str, $act_file){
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
    $error = verifyPltContentForGraph($file);
    if ($error == 1){
        $error_msg = "Cannot parse time format - Invalid time format";
        $error_type = 2;
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$act_file','$error_type'); </script>\n";
        return;
    }else if ($error == 2){
        $error_type = 2;
        $error_msg = "Not able to read file";
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$act_file','$error_type'); </script>\n";
        return;
    }else if ($error == 3){
        $error_type = 2;
        $error_msg = "Cannot access file or invalid URL";
        echo "  <script> buildGraphErrorView('$error_msg','$div_id','$act_file','$error_type'); </script>\n";
        return;
    }else{
        $handle = fopen($file, "r");
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            $line_arr = explode(',', $line);
            if($process_header){
                $xaxis_data[$testid_arr[$count]] = array();
                for($i = 1;$i < sizeof($line_arr);$i++){
                    $data = str_replace("\n",'', $line_arr[$i]);
		    $data = str_replace('"','', $data);
                    $temp_array = array();
                    $temp_array[$testid_arr[$count]] = array();
                    $column_data[$data] = $temp_array;
                    $column_map[] = $data;
                }
                $process_header = 0;
                continue;
            }
            if ($time_offset && !$process_header){
                $offset_datetime = new DateTime($line_arr[0]);
                $time_offset = 0;
            }
            $current_datetime = new DateTime($line_arr[0]);
            $current_datetime->add(new DateInterval('P1D'));
            $interval = date_diff($current_datetime,$offset_datetime);
            $interval_string = $interval->format("%D:%H:%I:%S");
            $xaxis_data[$testid_arr[$count]][] = $interval_string;
            for ($i = 1; $i < sizeof($line_arr); $i++) {
                $data = str_replace("\n", '', $line_arr[$i]);
                $column_data[$column_map[$i - 1]][$testid_arr[$count]][] = $data;
            }
        }
        fclose($handle);
        unset($handle);
        foreach ($column_map as $col){
            if(!$header_validity[$col]){
                unset($column_data[$col][$testid_arr[$count]]);
            }
        }
    }

    if(sizeof($file_arr) > 0){
        foreach ($file_arr as $file){
            $process_header = 1;
            $time_offset = 1;
            $count++;
            $column_map = array();
            $header_validity = array();
            $error = verifyPltContentForGraph($file);
            if ($error == 1){
                continue;
            }else if ($error == 2) {
                continue;
            }else if ($error == 3) {
                continue;
            }else{
                $handle = fopen($file, "r");
                while (($line = fgets($handle)) !== false) {
                    $line_arr = explode(',', $line);
                    if($process_header){
                        $xaxis_data[$testid_arr[$count]] = array();
                        for($i = 1;$i < sizeof($line_arr);$i++){
                            $data = str_replace("\n",'', $line_arr[$i]);
			    $data = str_replace('"','', $data);
                            if (array_key_exists($data,$column_data)){
                                $column_data[$data][$testid_arr[$count]] = array();
                            }
                            $column_map[] = $data;
                        }
                        $process_header = 0;
                        continue;
                    }
                    if ($time_offset && !$process_header){
                        $offset_datetime = new DateTime($line_arr[0]);
                        $time_offset = 0;
                    }
                    $current_datetime = new DateTime($line_arr[0]);
                    $current_datetime->add(new DateInterval('P1D'));
                    $interval = date_diff($current_datetime,$offset_datetime);
                    $interval_string = $interval->format("%D:%H:%I:%S");
                    $xaxis_data[$testid_arr[$count]][] = $interval_string;
                    for ($i = 1; $i < sizeof($line_arr); $i++) {
                        $data = str_replace("\n", '', $line_arr[$i]);
                        if (array_key_exists($column_map[$i - 1],$column_data)){
                            $column_data[$column_map[$i - 1]][$testid_arr[$count]][] = $data;
                        }
                    }
                }
                fclose($handle);
                unset($handle);
                foreach ($column_map as $col){
                    if(!$header_validity[$col]){
                        if (array_key_exists($col,$column_data)){
                            unset($column_data[$col][$testid_arr[$count]]);
                        }
                    }
                }
            }
        }
    }

    foreach($column_data as $key => $value){
        $graph_data = array();
        $metrics_array = array();
        $title = $act_file . " : " . trim($key,'"');
        $xs_array = array();
        foreach($testid_arr as $testid){
            if(array_key_exists($testid,$column_data[$key])){
                $array_column = $xaxis_data[$testid];
                array_unshift($array_column, "Time_" . $testid);
                $graph_data[] = $array_column;
                $array_column = $column_data[$key][$testid];
                if(!empty($array_column)){
                    $metrics_array[$testid]['max'] = max($array_column);
                    $metrics_array[$testid]['min'] = min($array_column);
                    $metrics_array[$testid]['avg'] = array_sum($array_column) / count($array_column);
                }
                array_unshift($array_column,$testid);
                $graph_data[] = $array_column;
                $xs_array[$testid] = "Time_" . $testid;
            }else{
                $metrics_array[$testid]['max'] = 'NaN';
                $metrics_array[$testid]['min'] = 'NaN';
                $metrics_array[$testid]['avg'] = 'NaN';
            }
        }
        $graph_data_json = json_encode($graph_data);
        $column_json = json_encode($testid_arr);
        $metric_data = json_encode($metrics_array);
        $xs_json = json_encode($xs_array);
        $x_value = '';
        echo "    <script>buildOutputPageGraphView('$graph_data_json', '$column_json', '$metric_data', '$x_value' ,'$xs_json', '$title','$div_id');</script>\n";
        $div_id++;
    }
}

function buildTestCompareData($filepaths,$test_ids){
    $file_arr = explode(',',$filepaths);
    $test_id_arr = explode(',',$test_ids);
    $ret_data = array();
    $file_data = "";
    $finfo = finfo_open(FILEINFO_MIME);
    for($i = 0; $i < sizeof($test_id_arr);$i++){
        $file_data = "";
        $file_exists = file_exists($file_arr[$i]);
        if (!$file_exists) {
            $file_data = "File Not Found";
            if ($i == 0){
                return $file_data;
            }else {
                $ret_data[$test_id_arr[$i]] = $file_data;
                continue;
	    }
        }
        $valid_path = validate_file_path($file_arr[$i]);
        if ($valid_path === false){
            $file_data = "Cannot access file or invalid URL";
            if ($i == 0){
                return $file_data;
            }else {
                $ret_data[$test_id_arr[$i]] = $file_data;
                continue;
	    }
        }
        if (substr(finfo_file($finfo, $file_arr[$i]), 0, 4) !== 'text') {
            $file_data = "Not a text file - unable to read";
            if ($i == 0){
                return $file_data;
            }else {
                $ret_data[$test_id_arr[$i]] = $file_data;
                continue;
            }
        }
        $fileptr = fopen($file_arr[$i], "r");
        if ($fileptr) {
            while (($line = fgets($fileptr)) !== false) {
                $line = str_replace("\r\n", "\\r\n", $line);
                $line = str_replace("\t", "\\t", $line);
                $line = str_replace("\n", "\\n", $line);
                $line = str_replace('"', '', $line);
                $line = str_replace("'", "", $line);
		if (strpos($line,"ERROR") !== false){
		    $line = "<font color=red>" . $line . "</font>";
		}
                $file_data .= $line ;
            }
            fclose($fileptr);
        }

        if(strlen($file_data) == 0){
            $file_data = "Empty File";
        }

        $ret_data[$test_id_arr[$i]] = $file_data;
    }
    return $ret_data;
}
