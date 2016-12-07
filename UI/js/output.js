function buildTextCompareView(file_list,test_list,div_id){
  if (div_id === "output-table-display"){
    div_class="output-text-panel";
  }else{
    div_class="compare-text-div";
  }
  var file_arr = file_list.split(",");
  var test_arr = test_list.split(",");
  var div_size = 12 / file_arr.length;

  var main_div = $("div#" + div_id);
  for (var i=0;i<file_arr.length;i++){
  $.ajax({
        url: "/fetchdata.php",
        type: "POST",
        index: i,
        async:false,
        data: {
        'filename': file_arr[i]
        },  
        success: function(retdata) {
	  var text_div = $('<div></div>').addClass('col-sm-' + div_size  + ' ' + div_class  + ' panel panel-default');
	  var text_div_heading = $('<div></div>').addClass('panel-heading')
				 .attr("style","text-align:center;")
				 .text(test_arr[this.index]);
	  var text_div_body = $('<div></div>').addClass('panel-body').text(retdata);
	  $(text_div).append(text_div_heading);
          $(text_div).append(text_div_body);
	  $(main_div).append(text_div);
	}
  });
  }
}

function verifyPltContent(filepath){
    var file_arr = filepath.split(",");
    var error = false;

    for (var i=0;i<file_arr.length;i++) {
        $.ajax({
            url: "/fetchdata.php",
            type: "POST",
            index: i,
            async:false,
            data: {
                'filename': file_arr[i]
            },
            success: function(retdata) {
                var lines = retdata.split("\n");
                if (this.index == 0){
                    if(lines.length < 1) {
                        unsupportedView("Empty file","output-panel");
                        error = true;
                        return;
                    }
                }
                var timePattern = new RegExp("[0-9]{4}[:,-][0-9]{2}[:,-][0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[Z]?");
                for (var i = 1; i < lines.length; ++i) {
                    if (lines[i].trim().length > 0) {
                        var row = lines[i].split(",");
                        for (var j = 0; j < row.length; ++j) {
                            if (j == 0 && !timePattern.test(row[j])) {
                                unsupportedView("Cannot parse time format","output-panel");
                                error = true;
                                return;
                            }
                        }
                    }
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                if(this.index == 0) {
                    unsupportedView(errorThrown,"output-panel");
                    error = true;
                    return;
                }
            }
        });
    }
    return error;
}

function buildTestReportGraphView(g_id, filepath, testid, graphtitle,flag) {
    var file_arr = filepath.split(",");
    for (var i=0;i<file_arr.length;i++){
      var temp_file = file_arr[i].split(":");
      file_arr[i] = temp_file[0];
    }
    var filename = file_arr.join();
    var error = verifyPltContent(filename);
    buildGraph(g_id, filepath,testid, graphtitle, !error, "", "");
}

function buildGraphView(filepath, testid, start, end, columns,actfilename) {
    var file_arr = filepath.split(",");
    var test_id = testid.split(",");
    var validity = [];
    var column_arr = columns.split(",");
    
    var error = verifyPltContent(filepath);
    
    
    if (!error){
        $.ajax({
            url: "/fetchdata.php",
            type: "POST",
            async:false,
            data: {
                'filename': file_arr[0]
            },
            success: function (retdata) {
                var lines = retdata.split("\n");
                var headers = lines[0].split(",");
                for (var i = 1; i < headers.length; ++i) {
                    validity[headers[i]] = true;
                }
                for (var i = 1; i < lines.length; ++i) {
                    if (lines[i].trim().length > 0) {
                        var row = lines[i].split(",");
                        for (var j = 0; j < row.length; ++j) {
                            if (j > 0 && (row[j] !== "NaN" && isNaN(row[j]))) {
                                validity[headers[j]] = false;
                            }
                        }
                    }
                }
                var g_id = 1;
                var index = 1;
                for (var header in validity) {
                    if((columns == "") || ((column_arr.indexOf(index.toString())) != -1)){
                        var panel = $("<div></div>").addClass("panel panel-info partition-1");
                        var pHeading = $("<div></div>").addClass("panel-heading collapse-heading");
                        var pTitle = $("<h4></h4>").addClass("panel-title text-center")
                            .text(actfilename + " : ");
                        var column_name = $("<span></span>").text(header.replace(/\"/g, "")).attr("style","font-weight: bold;");
                        $(pTitle).append(column_name);
                        $(pHeading).append(pTitle);
                        var pGraphDiv = $("<div></div>").addClass("graph-panel");
                        var pGraph = $("<div></div>").addClass("c3-graph-panel")
                            .attr("id", "c3item" + g_id);
                        var pFooter = $("<div></div>").addClass("metric-footer");
                        var pLegend = $("<table></table>").addClass("table table-condensed sortable")
                            .attr("id", "c3footer" + g_id)
                            .append($("<thead></thead>")
                                .append($("<tr></tr>")
                                    .append($("<th></th>").text("Test"))
                                    .append($("<th></th>").text("Min"))
                                    .append($("<th></th>").text("Max"))
                                    .append($("<th></th>").text("Average"))));
                        $(pFooter).append(pLegend);
                        $(pGraphDiv).append(pGraph).append(pFooter);
                        $(panel).append(pHeading).append(pGraphDiv);
                        $("#output-panel").append(panel);
                        var full_path = "";
                        for (var j = 0;j < file_arr.length; j++){
                            full_path += file_arr[j] + ":" + header;
                            if (j != file_arr.length - 1){
                                full_path += ",";
                            }
                        }
                        buildGraph(g_id++, full_path + ":" + header,
                            testid, pTitle.text(), validity[header], start, end);

                    }
                    index++;
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                unsupportedView(errorThrown,"output-panel");
            }
        });
    }
}

function unsupportedView(message,div_id) {
    var label = $("<h2></h2").addClass("err-msg-label")
        .text(message);
    div_id = "#" + div_id;
    $(div_id).append(label);
}

function verifyOutputFilters(){
    var start_time = new Date();
    var end_time = new Date();
    var start_input = $('#start');
    var end_input = $('#end');

    if ((!(start_input.val() == '') && (end_input.val() == '')) || ((start_input.val() == '') && !(end_input.val() == ''))){
        start_input.val("");
        end_input.val("");
        alert("Please enter both START time and END time");
	return false;
    }

    var timecheck = /^(0?[1-9]|[1-9][0-9]):([01]?\d|2[0-3]):([0-5]?\d):([0-5]?\d)$/.test(start_input.val());
    if (start_input.val() != ''){
	if(!timecheck){
      	    start_input.val("");
	    end_input.val("");
	    alert("Please enter START time in dd:hh:mm:ss format e.g. 01:00:15:00");
	    return false;
	}else{
	    var start_arr = start_input.val().split(":");
	    start_time.setDate(start_arr[0]);
            start_time.setHours(start_arr[1]);
            start_time.setMinutes(start_arr[2]);
            start_time.setSeconds(start_arr[3]);
	} 
    }

    var timecheck = /^([0-9][0-9]):([01]?\d|2[0-3]):([0-5]?\d):([0-5]?\d)$/.test(end_input.val());
    if (end_input.val() != ''){
        if(!timecheck){
            start_input.val("");
            end_input.val("");
            alert("Please enter END time in dd:hh:mm:ss format e.g. 01:00:15:00");
	    return false;
        }else{
            var end_arr = end_input.val().split(":");
            end_time.setDate(end_arr[0]);
            end_time.setHours(end_arr[1]);
            end_time.setMinutes(end_arr[2]);
            end_time.setSeconds(end_arr[3]);
        }
    }
   
    if ((start_time != 'undefined') && (end_time != 'undefined')){
	if (start_time > end_time){
	    start_input.val("");
            end_input.val("");
            alert("END time should be after START time");
	    return false;
	}
    }

    return true;
}

function checkTestCount(){
   var testid = $('#testid').val();
   var compids = $('#compids').val().split(",");
   var testcount = compids.length; 
   if(compids.indexOf(testid) != -1){
	alert("Compare field contains Test ID - Please remove Test ID");
	return false;
   }

   var width = $( window ).width();
   if (width < 768){
	if (testcount > 1){
            alert("On this device only 2 test comparison is allowed");
            return false;
	}
   }else{
	if (testcount > 3){
            alert("Maximum 4 test can be compared - Please remove some test from comparison.");
            return false;
        }
   }
   return true;

}

function buildFileToTableView(file_list,test_list,div_id) {
    if (div_id === "output-table-display"){
      div_class="output-text-panel";
    }else{
      div_class="compare-text-div";
    }

    var file_arr = file_list.split(",");
    var test_arr = test_list.split(",");
    var div_size = 12 / file_arr.length;
    var main_div = $("div#" + div_id);
    for (var i=0;i<file_arr.length;i++){
      $.ajax({
        url: "/fetchdata.php",
        type: "POST",
        index: i,
        async:false,
        data: {
        'filename': file_arr[i]
        },
        success: function (retdata) {
            var lines = retdata.split("\n");
            if(lines.length < 1) {
                unsupportedView("Empty file",div_id);
                return;
            }
            var csv_div = $('<div></div>').addClass('col-sm-' + div_size  + ' ' + div_class  + ' panel panel-default');
            var csv_div_heading = $('<div></div>').addClass('panel-heading')
                                    .attr("style","text-align:center;")
                                    .text(test_arr[this.index]);
            var csv_div_body = $('<div></div>').addClass('panel-body').attr("id","csv_table_view");
            var table = $("<table></table>").addClass("table table-hover")
                        .attr("id","csv-table");
            var thead = $("<thead></thead>");
            var thead_tr = $("<tr></tr>");
            var headers = lines[0].split(",");
            for(var j = 0; j < headers.length; ++j) {
              $(thead_tr).append($("<th></th>")
                .text(headers[j].replace(/\"/g, "")));
            }
            var tbody = $("<tbody></tbody>");
            for(var j = 1; j < lines.length; ++j) {
                if(lines[j].trim().length > 1) {
                    var tbody_tr = $("<tr></tr>");
                    var rowData = lines[j].split(",");
                    for(var k = 0; k < rowData.length; ++k) {
                        $(tbody_tr).append($("<td></td>").text(rowData[k]));
                    }
                    $(tbody).append(tbody_tr);
                }
            }
            $(thead).append(thead_tr);
            $(table).append(thead);
            $(table).append(tbody);
            $(csv_div_body).append(table);
            $(csv_div).append(csv_div_heading);
            $(csv_div).append(csv_div_body);
            $(main_div).append(csv_div);
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            unsupportedView(errorThrown,div_id);
        }
      });
    }
}

function buildJsonToTableView(json_data,div_id) {
  var compare_data = JSON.parse(json_data);
  if (compare_data.length < 2){
    unsupportedView("Empty file",div_id);
    return;
  }

  var table = $("<table></table>").addClass("table table-hover")
                            .attr("id","result-table");
  var thead = $("<thead></thead>");
  var thead_tr = $("<tr></tr>");
  var headers = compare_data["Test ID"];
  $(thead_tr).append($("<th></th>").addClass("active").text("Test ID".replace(/\"/g,"")));
  for(var j = 0; j < headers.length; ++j) {
    $(thead_tr).append($("<th></th>").addClass("test-data-td")
      .text(headers[j].replace(/\"/g, "")));
  }
  var tbody = $("<tbody></tbody>");
  for (var line_key in compare_data){
    if ((line_key.localeCompare("Test ID") != 0) && compare_data.hasOwnProperty(line_key)) {
         var tbody_tr = $("<tr></tr>");
         $(tbody_tr).append($("<td></td>").addClass("active").text(line_key));
         var line_value = compare_data[line_key];
         for(var k = 0; k < line_value.length; ++k) {
            $(tbody_tr).append($("<td></td>").addClass("test-data-td").text(line_value[k]));
         }
         $(tbody).append(tbody_tr);
    }
  }
  $(thead).append(thead_tr);
  $(table).append(thead);
  $(table).append(tbody);
  $("div#" + div_id).append(table);

}
function switchFileViewerFormat(referer) {
    var fileType = $(referer).find("option:selected").text().toLowerCase();
    location.search = location.search.replace(/&format=[^&$]*/i, '&format=' + fileType);
}
