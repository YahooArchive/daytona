function collapseAll() {
    $(".graph-panel").css("display", "none");
}

function expandAll() {
    $(".graph-panel").css("display", "block");
}

function formatDec(val) {
    return Math.round(val * 10000) / 10000;
}

function buildGraph(graphid, paths, id_map, title, err_threshold, start, end) {
    if(!err_threshold) {
        var chart = c3.generate({
            bindto: '#c3item' + graphid,
            data: {
                columns: []
            },
            axis: {
                x: {
                    label: {
                        text: 'Graph not found',
                        position: 'inner-center'
                    }
                }
            },
            padding: {
                right: 10
            },
            legend: {
                show: false
            },
            size: {
                height: 270
            }
        });
        $('#c3item' + graphid).data("c3-chart",chart);
        $('#c3footer' + graphid).css("display", "none");
        return;
    }

    var path_index;
    var columns = [];
    var xs = {};
    var metrics_arr = {};
    var paths_arr = paths.split(",");
    var id_arr = id_map.split(",");
    for(path_index in paths_arr) {
        var path_val = paths_arr[path_index];
        var path_col_arr = path_val.split(":");
        var date_arr = ["date_" + id_arr[path_index]];
        var value_arr = [id_arr[path_index]];
        xs[value_arr[0]] = date_arr[0];
        jQuery.ajax({
            url: "/fetchdata.php",
            type: "POST",
            data: {
              'filename': path_col_arr[0]
            },
            success: function(retdata) {
                var lines = retdata.split("\n");
                if(lines.length < 1) {
                    return;
                }
                var columnIdx = 1;
                var headers = lines[0].split(",");
                for(var i = 1; i < headers.length; ++i) {
                    if((path_col_arr[1]) && (path_col_arr[1].replace(/['"]+/g,'') == headers[i].replace(/['"]+/g,''))) {
                        columnIdx = i;
                        break;
                    }
                }
		var time_offset = new Date (lines[1].split(",")[0]);
		var start_arr;
		var end_arr;
		if(start != '' && end != ''){
		    start_arr = start.split(":");
		    end_arr = end.split(":");
		    var start_time = new Date();
		    start_time.setDate(start_arr[0]);
		    start_time.setHours(start_arr[1]);
                    start_time.setMinutes(start_arr[2]);
                    start_time.setSeconds(start_arr[3]);
		    var end_time = new Date();
                    end_time.setDate(end_arr[0]);
                    end_time.setHours(end_arr[1]);
                    end_time.setMinutes(end_arr[2]);
                    end_time.setSeconds(end_arr[3]);
		}
		if (typeof start_time == 'undefined' && typeof end_time == 'undefined'){
                    for(var i = 1, len = lines.length - 1; i < len; ++i) {
                	var line_split = lines[i].split(",");
                        var act_time = new Date(line_split[0]);
                        var time_diff = (act_time - time_offset)/1000;
                        var norm_time = new Date();
                        norm_time.setDate(1);
                        norm_time.setHours(0,0,0,0);
                        norm_time.setMinutes(0 - norm_time.getTimezoneOffset());
                        norm_time.setSeconds(time_diff);
                        var time_str = norm_time.toISOString().split('.')[0];
                        date_arr.push(time_str.replace(/-/g, ":"));
                        value_arr.push(line_split[columnIdx]);
		    }
                }else{
		    for(var i = 1, len = lines.length - 1; i < len; ++i) {
                        var line_split = lines[i].split(",");
                        var act_time = new Date(line_split[0]);
                        var time_diff = (act_time - time_offset)/1000;
                        var norm_time = new Date();
                        norm_time.setDate(1);
                        norm_time.setHours(0,0,0,0);
                        norm_time.setMinutes(0);
                        norm_time.setSeconds(time_diff);
			if ((start_time <= norm_time) && (end_time >= norm_time)){
			    norm_time.setMinutes(0 - norm_time.getTimezoneOffset());
			    norm_time.setMinutes(0);
                            norm_time.setSeconds(time_diff);
                            var time_str = norm_time.toISOString().split('.')[0];
                            date_arr.push(time_str.replace(/-/g, ":"));
                            value_arr.push(line_split[columnIdx]);    
			}
                    }
		}
                columns.push(date_arr);
                columns.push(value_arr);
                var value_data_arr = value_arr.slice(1);
                metrics_arr[id_arr[path_index]] = {};
                metrics_arr[id_arr[path_index]].max =
                    value_data_arr.reduce(function (m, v) {
                        if(!isNaN(v) && isNaN(m)) {
                            return v;
                        } else if(!isNaN(m) && isNaN(v)) {
                            return m;
                        }
                        return Math.max(m, v);
                    });
                metrics_arr[id_arr[path_index]].min =
                    value_data_arr.reduce(function (m, v) {
                        if(!isNaN(v) && isNaN(m)) {
                            return v;
                        } else if(!isNaN(m) && isNaN(v)) {
                            return m;
                        }
                        return Math.min(m, v);
                    });
                metrics_arr[id_arr[path_index]].avg =
                    value_data_arr.reduce(function (m, v) {
                        var mVal = parseFloat(m);
                        var vVal = parseFloat(v);
                        if(!isNaN(vVal) && isNaN(mVal)) {
                            return vVal;
                        } else if(!isNaN(mVal) && isNaN(vVal)) {
                            return mVal;
                        }
                        return mVal + vVal;
                    }) / value_data_arr.length;
            },
            async: false
        });
    }
    var width = $( window ).width();
    var tick_count;
    if (width < 768){
	tick_count = 3;
    }
    var chart = c3.generate({
        bindto: '#c3item' + graphid,
        data: {
            xs: xs,
            columns: columns,
            xFormat: '%Y:%m:%dT%H:%M:%S',
            type: 'step',
        },
        padding: {
            right: 10
        },
        axis: {
            x: {
                type: 'timeseries',
                tick: {
                    format: '%d:%H:%M:%S',
		    count: tick_count
                },
                label: {
                    text: 'Time'
                }
            },
            y: {
                tick: {
                    format: function (d) {
                        return Math.round(d * 10000) / 10000;
                    }
                }
            }
        },
        point: {
            show: false
        },
        size: {
            height: 270
        },
        legend: {
            show: false
        },
        grid: {
            x: {
                show: true
            },
            y: {
                show: true
            }
        }
    });

    function toggle(id) {
        chart.toggle(id);
    }

    d3.select("#c3footer" + graphid).insert('tbody', '.chart')
        .attr('class', 'graph-legend')
        .selectAll('tr')
        .data(id_arr)
        .enter()
        .append('tr')
        .html(function (id) {
            var ret = "<td class='emphasize'>" + id + "</td>";
            if(metrics_arr[id]) {
                ret += "<td>" + formatDec(metrics_arr[id].min) + "</td>";
                ret += "<td>" + formatDec(metrics_arr[id].max) + "</td>";
                ret += "<td>" + formatDec(metrics_arr[id].avg) + "</td>";
            }
            else {
                ret += "<td></td><td></td><td></td>";
            }
            return ret;
        })
        .each(function (id) {
            d3.select(this).style('color', chart.color(id));
        })
        .on('mouseover', function (id) {
            if(!$(this).hasClass("cut-opacity")) {
                chart.focus(id);
            }
        })
        .on('mouseout', function (id) {
            chart.revert();
        })
        .on('click', function (id) {
            chart.toggle(id);
            toggleRow(this);
        });

    $('#c3item' + graphid).data("c3-chart",chart);

    var zoomLink = $("<a></a>").addClass("search-plus")
        .attr("href", "#")
        .click(function() {
            $("#zoomModal").find(".modal-title").text(title);
            reset_metric_sort();
            buildGraphZoom(xs, columns, metrics_arr, id_arr);
            $("#zoomModal").modal("show");
            $("#zoom-body").data("c3-chart").flush();
        });
    var zoomImg = $("<i></i>").addClass("fa fa-search-plus");
    $(zoomLink).append(zoomImg);
    $('#c3item' + graphid).after(zoomLink);
}

function buildGraphZoom(xs, columns, metrics_arr, id_arr) {
    var width = $( window ).width();
    var tick_count;
    if (width < 768){
        tick_count = 3;
    }
    var chart = c3.generate({
        bindto: '#zoom-body',
        data: {
            xs: xs,
            columns: columns,
            xFormat: '%Y:%m:%dT%H:%M:%S',
            type: 'step',
        },
        subchart: {
            show: true
        },
        zoom: {
            enabled: true
        },
        padding: {
            right: 10
        },
        axis: {
            x: {
                type: 'timeseries',
                tick: {
                    format: '%H:%M:%S',
		    count:tick_count
                },
                label: {
                    text: 'Time'
                }
            },
            y: {
                tick: {
                    format: function (d) {
                        return Math.round(d * 10000) / 10000;
                    }
                }
            }
        },
        point: {
            show: false
        },
        size: {
            height: 400
        },
        legend: {
            show: false
        },
        grid: {
            x: {
                show: true
            },
            y: {
                show: true
            }
        }
    });

    function toggle(id) {
        chart.toggle(id);
    }

    $("#zoom-footer").remove();
    var zoomTable = $("<table></table>").addClass("table table-condensed")
        .attr("id", "zoom-footer");
    var zoomTHead = $("<thead></thead>");
    var zoomTr = $("<tr></tr>")
        .append($("<th></th>").text("Test")
            .click(function() { metric_custom_sort(this); }))
        .append($("<th></th>").text("Min")
            .click(function() { metric_custom_sort(this); }))
        .append($("<th></th>").text("Max")
            .click(function() { metric_custom_sort(this); }))
        .append($("<th></th>").text("Average")
            .click(function() { metric_custom_sort(this); }));
    $(zoomTHead).append(zoomTr);
    $(zoomTable).append(zoomTHead);
    $("#zoomModal .metric-footer").append(zoomTable);

    d3.select("#zoom-footer").insert('tbody', '.chart')
        .attr('class', 'graph-legend')
        .selectAll('tr')
        .data(id_arr)
        .enter()
        .append('tr')
        .html(function (id) {
            var ret = "<td class='emphasize'>" + id + "</td>";
            if(metrics_arr[id]) {
                ret += "<td>" + formatDec(metrics_arr[id].min) + "</td>";
                ret += "<td>" + formatDec(metrics_arr[id].max) + "</td>";
                ret += "<td>" + formatDec(metrics_arr[id].avg) + "</td>";
            }
            else {
                ret += "<td></td><td></td><td></td>";
            }
            return ret;
        })
        .each(function (id) {
            d3.select(this).style('color', chart.color(id));
        })
        .on('mouseover', function (id) {
            if(!$(this).hasClass("cut-opacity")) {
                chart.focus(id);
            }
        })
        .on('mouseout', function (id) {
            chart.revert();
        })
        .on('click', function (id) {
            chart.toggle(id);
            toggleRow(this);
        });
    $('#zoom-body').data("c3-chart",chart);
}

function toggleRow(referer) {
    if($(referer).hasClass("cut-opacity")) {
        $(referer).removeClass("cut-opacity");
    }
    else {
        $(referer).addClass("cut-opacity");
    }
}

function flushAllCharts() {
    var c3elem = $('.c3');
    $('.c3').each(function() {
        var chart = $(this).data("c3-chart");
        chart.flush();
    });
}

var cellIndex = -1;
var ascending = true;

function reset_metric_sort() {
    cellIndex = -1;
    ascending = true;
}

function metric_custom_sort(elem) {
    if (cellIndex == elem.cellIndex) {
        ascending = !ascending;
    }
    else {
        if (cellIndex != -1) {
            var prevElem = elem.parentNode.cells[cellIndex];
            $(prevElem).text($(prevElem).find(".mozilla").text());
            $(prevElem).find(".mozilla").remove();
        }
        cellIndex = elem.cellIndex;
        ascending = true;
    }
    var thDiv = $("<div></div>").addClass("mozilla")
        .text($(elem).text());
    var signSpan = $("<span></span>").addClass("sign arrow");
    $(thDiv).append(signSpan);
    if (!ascending) {
        $(signSpan).addClass("up");
    }
    $(elem).text("");
    $(elem).append(thDiv);
    var metricsTable = document.getElementById("zoom-footer");
    var rows_arr = metricsTable.rows;
    for (var i = 1; i < rows_arr.length; i++) {
        var row_data = rows_arr[i].cells;
        var max = parseFloat(row_data[cellIndex].firstChild.data);
        var max_index = i;
        for (var j = i+1; j < rows_arr.length; j++) {
            var test_value = parseFloat(rows_arr[j].cells[cellIndex].firstChild.data);
            if (ascending) {
                if (test_value < max) {
                    max = test_value;
                    max_index = j;
                }
            }
            else {
                if (test_value > max) {
                    max = test_value;
                    max_index = j;
                }
            }
        }
        swap_rows(metricsTable, i, max_index);
    }
}

function swap_rows(table, i1, i2) {
    if (i1 == i2) {
        return;
    }

    var rows_arr = table.rows;
    var row_parent = rows_arr[i1].parentNode;
    if (i1 > i2) {
        row_parent.insertBefore(rows_arr[i1], rows_arr[i2]);
        row_parent.insertBefore(rows_arr[i2+1], rows_arr[i1+1]);
    }
    else {
        row_parent.insertBefore(rows_arr[i2], rows_arr[i1]);
        row_parent.insertBefore(rows_arr[i1+1], rows_arr[i2+1]);
    }
}

function collapseGraph(referer) {
    var graph = $(referer).parent().parent().parent().parent().find(".graph-panel");
    if($(graph).css("display") === "block") {
        $(graph).css("display", "none");
    }
    else {
        $(graph).css("display", "block");
    }
}
