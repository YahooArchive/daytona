function collapseAllGraph() {
    $(".graph-panel").each(function(d){
        $(this).removeClass('in');
    });
}

function expandAllGraph() {
    $(".graph-panel").each(function(d){
        $(this).addClass('in');
        $(this).removeAttr("style");
    });
}

function formatDec(val) {
    return Math.round(val * 10000) / 10000;
}

function buildGraph(json_data, col_name, metric_json, x_value ,xs_json ,title, graphid){
    var graph_data = JSON.parse(json_data);
    var col_array = JSON.parse(col_name);
    var metrics_array = JSON.parse(metric_json);
    var xs = JSON.parse(xs_json);
    title = title.replace(/"/g, "");

    var chart = drawNormalGrpah(graphid,x_value,xs,graph_data);

    function toggle(id) {
        chart.toggle(id);
    }

    d3.select("#c3footer" + graphid).insert('tbody', '.chart')
        .attr('class', 'graph-legend')
        .selectAll('tr')
        .data(col_array)
        .enter()
        .append('tr').attr("class","no-opacity")
        .html(function (id) {
            var ret = "<td class='emphasize'>" + id + "</td>";
            if(metrics_array[id]) {
                ret += "<td>" + formatDec(metrics_array[id].min) + "</td>";
                ret += "<td>" + formatDec(metrics_array[id].max) + "</td>";
                ret += "<td>" + formatDec(metrics_array[id].avg) + "</td>";
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
            buildGraphZoomJson(xs, x_value, graph_data, metrics_array, col_array);
            $("#zoomModal").modal("show");
            $("#zoom-body").data("c3-chart").flush();
        });
    var zoomImg = $("<i></i>").addClass("fa fa-search-plus").attr('title','Graph Zoom Mode');
    $(zoomLink).append(zoomImg);

    var toggleLink = $("<a></a>").addClass("toggle-button")
        .attr("href", "#")
        .click(function() {
            toggleAll(chart,graphid);
            //chart.toggle();
        });
    var toggleImg = $("<i></i>").addClass("fa fa-toggle-on").attr('id','toggle-active' + graphid).attr('title','Toggle Graph');
    $(toggleLink).append(toggleImg);

    var subGraphLink = $("<a></a>").addClass("subgraph-button")
        .attr("href", "#")
        .click(function() {
            chart = CreateSubGraph(graphid,x_value,xs,graph_data);
            //chart.toggle();
        });
    var subGraphImg = $("<i></i>").addClass("fa fa-area-chart cut-opacity").attr('id','subgraph-active' + graphid).attr('title','Sub-Graph Enable/Disable');
    $(subGraphLink).append(subGraphImg);

    $('#c3item' + graphid).after('<br /><br />');
    $('#c3item' + graphid).after(zoomLink);
    $('#c3item' + graphid).after(toggleLink);
    $('#c3item' + graphid).after(subGraphLink);


}

function drawNormalGrpah(graphid,x_value,xs,graph_data){

    var width = $( window ).width();
    var tick_count;
    if (width < 768){
        tick_count = 3;
    }


    var chart = c3.generate({
        bindto: '#c3item' + graphid,
        data: {
            x : x_value,
            xs: xs,
            columns: graph_data,
            xFormat: '%d:%H:%M:%S',
            type: 'line',
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

    return chart;

}

function drawNormalSubGrpah(graphid,x_value,xs,graph_data){

    var width = $( window ).width();
    var tick_count;
    if (width < 768){
        tick_count = 3;
    }


    var chart = c3.generate({
        bindto: '#c3item' + graphid,
        data: {
            x : x_value,
            xs: xs,
            columns: graph_data,
            xFormat: '%d:%H:%M:%S',
            type: 'line',
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
        subchart: {
            show: true
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

    return chart;

}

function toggleAll(chart, graphid){
    var toggle_element = '#toggle-active' + graphid;
    var toggle = $(toggle_element).attr("class");

    var toggle_opacity = '#c3footer' + graphid + ' > tbody > tr.cut-opacity';
    var toggle_no_opacity = '#c3footer' + graphid + ' > tbody > tr.no-opacity';

    if (~toggle.indexOf("fa-rotate-180")){
        $(toggle_element).removeClass("fa-rotate-180");
        $(toggle_opacity).each(function () {
            var id = $(this).find('td:first').text();
            toggleRow(this);
            chart.toggle(id);
        });
    }else{
        $(toggle_element).addClass("fa-rotate-180");
        $(toggle_no_opacity).each(function () {
            var id = $(this).find('td:first').text();
            toggleRow(this);
            chart.toggle(id);
        });
    }
}

function CreateSubGraph(graphid, x_value, xs, graph_data){

    var graph_element = '#subgraph-active' + graphid;
    var graph = $(graph_element).attr("class");

    var toggle_element = '#toggle-active' + graphid;
    var toggle = $(toggle_element).attr("class");
    var toggle_opacity = '#c3footer' + graphid + ' > tbody > tr.cut-opacity';

    if (~graph.indexOf("cut-opacity")){
        $(graph_element).removeClass("cut-opacity");
        var chart = drawNormalSubGrpah(graphid,x_value,xs,graph_data);
    }else{
        $(graph_element).addClass("cut-opacity");
        var chart = drawNormalGrpah(graphid,x_value,xs,graph_data);
    }

    if (~toggle.indexOf("fa-rotate-180")){
        $(toggle_element).removeClass("fa-rotate-180");
    }
    $(toggle_opacity).each(function () {
        var id = $(this).find('td:first').text();
        toggleRow(this);
    });


    return chart;
}


function buildGraphErrorView(text, graphid,title,error_type){
    if(error_type == '1'){
        var pGraph = $("<div></div>").addClass("c3-graph-panel")
            .attr("id", "c3item" + graphid);
        var pText = $('<p></p>').text(text).attr("id","graph-error");
        $(pGraph).append(pText);
        var pGraphDiv = '#collapse' + graphid;
        $(pGraphDiv).append(pGraph);
        return;
    }else if(error_type == '2'){
        var panel = $("<div></div>").addClass("panel panel-info partition-1");
        var pHeading = $("<div></div>").addClass("panel-heading collapse-heading");
        var pTitle = $("<h4></h4>").addClass("panel-title text-center");
        var pLink = $("<a></a>").attr('data-toggle','collapse').attr('href','#graph-div-'+graphid)
            .text(title);
        $(pTitle).append(pLink);
        $(pHeading).append(pTitle);
        var pGraphDiv = $("<div></div>").addClass("graph-panel panel-collapse collapse in").attr("id",'graph-div-'+graphid);
        var pGraph = $("<div></div>").addClass("c3-graph-panel")
            .attr("id", "c3item" + graphid);
        var pText = $('<p></p>').text(text).attr("id","graph-error");
        $(pGraph).append(pText);
        $(pGraphDiv).append(pGraph);
        $(panel).append(pHeading).append(pGraphDiv);
        $("#output-panel").append(panel);
        return;
    }
    else{
        var div_id = "#" + graphid;
        var pText = $('<p></p>').text(text).attr("id","graph-error");
        $(div_id).append(pText);
    }
}


function buildGraphZoomJson(xs, x_value, columns, metrics_arr, id_arr) {
    var width = $( window ).width();
    var tick_count;
    if (width < 768){
        tick_count = 3;
    }
    var chart = c3.generate({
        bindto: '#zoom-body',
        data: {
            x : x_value,
            xs: xs,
            columns: columns,
            xFormat: '%d:%H:%M:%S',
            type: 'line',
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
                    format: '%d:%H:%M:%S',
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
        $(referer).addClass("no-opacity");
    }
    else {
        $(referer).removeClass("no-opacity");
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
