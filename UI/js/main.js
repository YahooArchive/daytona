function updateCurrentlyRunning(json_obj) {
  var parentContainer = $('#currently-running');
  $(parentContainer).empty();
  if (json_obj.length <= 0) {
    $(parentContainer).append($('<p></p>')
      .addClass('no-test-label')
      .text('No tests are currently running'));
    return;
  }
  var table = $('<table></table>')
    .addClass('table table-hover table-striped output-table');
  var thead = $('<thead></thead>')
    .append($('<tr></tr>')
    .append($('<th></th>').css('width', '17%').text('Framework'))
    .append($('<th></th>').css('width', '10%').text('Test'))
    .append($('<th></th>').css('width', '25%').text('Title'))
    .append($('<th></th>').css('width', '20%').text('User'))
    .append($('<th></th>').css('width', '10%').text('Status'))
    .append($('<th></th>').css('width', '15%').text('Start Time'))
    .append($('<th></th>').css('width', '3%').text(' ')));
  var tbody = $('<tbody></tbody>');
  for (var i = 0; i < json_obj.length; ++i) {
    var tr = $('<tr></tr>');
    var td_frameworkname = $('<td></td>')
      .append($('<a></a>')
      .attr('href', '/?frameworkid=' + json_obj[i].frameworkid)
      .text(json_obj[i].frameworkname));
    var td_testid = $('<td></td>')
      .append($('<a></a>')
      .attr('href', 'test_info.php?testid=' + json_obj[i].testid)
      .text(json_obj[i].testid));
    var td_title = $('<td></td>').text(json_obj[i].title);
    var td_username = $('<td></td>').text(json_obj[i].username);
    var td_state_detail = $('<td></td>').text(json_obj[i].state_detail);
    var td_start_time = $('<td></td>').text(json_obj[i].start_time);
    var td_kill = $('<td></td>').css('vertical-align', 'middle')
      .append($('<a></a>').css('cursor', 'pointer')
      .append($('<i></i>').addClass('fa fa-times-circle fa-lg abort-btn'))
      .attr('onclick', 'killTest(' + json_obj[i].testid + ')'));
    $(tr).append(td_frameworkname)
      .append(td_testid)
      .append(td_title)
      .append(td_username)
      .append(td_state_detail)
      .append(td_start_time)
      .append(td_kill);
    $(tbody).append(tr);
  }
  $(table).append(thead).append(tbody);
  $(parentContainer).append(table);
}

function queryCurrentlyRunning(frameworkId,user) {
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (xhttp.readyState === 4 && xhttp.status === 200) {
      updateCurrentlyRunning(JSON.parse(xhttp.responseText));
    }
  };
  var f_clause = frameworkId ? '&frameworkid=' + frameworkId : '';
  var u_clause = user ? '&user=' + user : '';
  xhttp.open('GET', location.protocol + '//' + location.host +
    '/database_access.php?query=currentlyRunning&respond=1' + f_clause + u_clause, true);
  xhttp.send();
}

function updateWaitingQueue(json_obj) {
  var parentContainer = $('#waiting-queue');
  $(parentContainer).empty();
  if (json_obj.length <= 0) {
    $(parentContainer)
      .append($('<p></p>')
      .addClass('no-test-label')
      .text('No tests are currently queued'));
    return;
  }
  var table = $('<table></table>')
    .addClass('table table-hover table-striped output-table');
  var thead = $('<thead></thead>')
    .append($('<tr></tr>')
    .append($('<th></th>').css('width', '17%').text('Framework'))
    .append($('<th></th>').css('width', '10%').text('Test'))
    .append($('<th></th>').css('width', '25%').text('Title'))
    .append($('<th></th>').css('width', '20%').text('User'))
    .append($('<th></th>').css('width', '10%').text('Waiting On'))
    .append($('<th></th>').css('width', '18%').text(' ')));
  var tbody = $('<tbody></tbody>');
  for (var i = 0; i < json_obj.length; ++i) {
    var tr = $('<tr></tr>');
    var td_frameworkname = $('<td></td>')
      .append($('<a></a>')
      .attr('href', '/?frameworkid=' + json_obj[i].frameworkid)
      .text(json_obj[i].frameworkname));
    var td_testid = $('<td></td>')
      .append($('<a></a>')
      .attr('href', 'test_info.php?testid=' + json_obj[i].testid)
      .text(json_obj[i].testid));
    var td_title = $('<td></td>').text(json_obj[i].title);
    var td_username = $('<td></td>').text(json_obj[i].username);
    var td_state_detail = $('<td></td>').text(json_obj[i].state_detail);
    var td_kill = $('<td></td>')
      .css('vertical-align', 'middle')
      .append($('<a></a>')
      .css('cursor', 'pointer')
      .append($('<i></i>')
      .addClass('fa fa-times-circle fa-lg abort-btn'))
      .attr('onclick', 'killTest(' + json_obj[i].testid + ')'));
    $(tr)
      .append(td_frameworkname)
      .append(td_testid)
      .append(td_title)
      .append(td_username)
      .append(td_state_detail)
      .append(td_kill);
    $(tbody).append(tr);
  }
  $(table).append(thead).append(tbody);
  $(parentContainer).append(table);
}

function queryWaitingQueue(frameworkId,user) {
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (xhttp.readyState === 4 && xhttp.status === 200) {
      updateWaitingQueue(JSON.parse(xhttp.responseText));
    }
  };
  var f_clause = frameworkId ? '&frameworkid=' + frameworkId : '';
  var u_clause = user ? '&user=' + user : '';
  xhttp.open('GET', location.protocol + '//' + location.host +
    '/database_access.php?query=waitingQueue&respond=1' + f_clause + u_clause, true);
  xhttp.send();
}

function stateColorClass(state) {
  switch(state) {
    case 'finished clean':
      var hoverlink = $('<a></a>').attr('href','#').attr('data-toggle','tooltip').attr('title',state);
      var color = $('<i></i>').addClass('fa fa-circle').attr('style', 'color:#00e64d;padding-left:5px;');
      $(hoverlink).append(color);
      return hoverlink;
    case 'aborted':
      var hoverlink = $('<a></a>').attr('href','#').attr('data-toggle','tooltip').attr('title',state);
      var color = $('<i></i>').addClass('fa fa-circle').attr('style', 'color:#ffff00;padding-left:5px;');
      $(hoverlink).append(color);
      return hoverlink;
    default:
      var hoverlink = $('<a></a>').attr('href','#').attr('data-toggle','tooltip').attr('title',state);
      var color = $('<i></i>').addClass('fa fa-circle').attr('style', 'color:#ff3300;padding-left:5px;');
      $(hoverlink).append(color);
      return hoverlink;
  }
}

function updateLastCompleted(json_obj) {
  var parentContainer = $('#last-completed');
  $(parentContainer).empty();
  if (json_obj.length <= 0) {
    $(parentContainer)
      .append($('<p></p>')
      .addClass('no-test-label')
      .text('No tests have been completed recently'));
    return;
  }
  var table = $('<table></table>')
    .addClass('table table-hover table-striped output-table').attr("id","lastCompletedTest");
  var thead = $('<thead></thead>')
    .append($('<tr></tr>')
    .append($('<th></th>').css('width', '17%').text('Framework'))
    .append($('<th></th>').css('width', '10%').text('Test'))
    .append($('<th></th>').css('width', '25%').text('Title'))
    .append($('<th></th>').css('width', '20%').text('User'))
    .append($('<th></th>').css('width', '10%').text('State'))
    .append($('<th></th>').css('width', '18%').text('End Time')));
  var tbody = $('<tbody></tbody>');
  for (var i = 0; i < json_obj.length; ++i) {
    var tr = $('<tr></tr>');
    var td_frameworkname = $('<td></td>')
      .append($('<a></a>')
      .attr('href', '/?frameworkid=' + json_obj[i].frameworkid)
      .text(json_obj[i].frameworkname));
    var td_testid = $('<td></td>')
      .append($('<a></a>')
      .attr('href', 'test_info.php?testid=' + json_obj[i].testid)
      .text(json_obj[i].testid));
    var td_title = $('<td></td>').text(json_obj[i].title);
    var td_username = $('<td></td>').text(json_obj[i].username);
    var stateColor = stateColorClass(json_obj[i].end_status);
    var td_end_status = $('<td></td>').append(stateColor);
    var td_end_time = $('<td></td>').text(json_obj[i].end_time);
    $(tr)
      .append(td_frameworkname)
      .append(td_testid)
      .append(td_title)
      .append(td_username)
      .append(td_end_status)
      .append(td_end_time);
    $(tbody).append(tr);
  }
  $(table).append(thead)
    .append(tbody);
  $(parentContainer).append(table);
}

function queryLastCompleted(frameworkId,user) {
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (xhttp.readyState == 4 && xhttp.status == 200) {
      updateLastCompleted(JSON.parse(xhttp.responseText));
    }
  };
  var f_clause = frameworkId ? '&frameworkid=' + frameworkId : '';
  var u_clause = user ? '&user=' + user : '';

  xhttp.open('GET', location.protocol + '//' + location.host +
    '/database_access.php?query=lastCompleted&respond=1' + f_clause + u_clause, true);
  xhttp.send();
}

function queryAll(frameworkId,user) {
  queryCurrentlyRunning(frameworkId,user);
  queryWaitingQueue(frameworkId,user);
  queryLastCompleted(frameworkId,user);
}
