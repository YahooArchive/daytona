function submitBasicCompare() {
    var checkboxes = $(".header-search input:checkbox").filter(function() {
        return $(this).prop('checked');
    });
    if(checkboxes.length <= 0) {
        return;
    }
    var compareStr = "";
    for(var i = 1; i < checkboxes.length; ++i) {
        if(i == 1) {
            compareStr += "&compids=";
        } else {
            compareStr += ",";
        }
        compareStr += $(checkboxes[i]).attr("data-testid");
    }
    window.location = "/test_info.php?testid=" + $(checkboxes[0]).attr("data-testid") + compareStr;
}

function submitAdvanceCompare() {
    var checkboxes = $(".header-search input:checkbox").filter(function() {
        return $(this).prop('checked');
    });
    if(checkboxes.length <= 0) {
        return;
    }
    var compareStr = "";
    for(var i = 1; i < checkboxes.length; ++i) {
        if(i == 1) {
            compareStr += "&compids=";
        } else {
            compareStr += ",";
        }
        compareStr += $(checkboxes[i]).attr("data-testid");
    }
    window.location = "/test_report.php?testid=" + $(checkboxes[0]).attr("data-testid") + compareStr;
}


$(document).ready(function() {
  $('[name="test-checkbox"]').on('click', function(evt) {
    var count = $('[name="test-checkbox"]:checked').length;
    if (count > 0) {
      $('#delete-button').prop('disabled', false);
      $('#compare-button').prop('disabled', false);
      $('#advance-compare-button').prop('disabled', false);
    } else {
      $('#delete-button').prop('disabled', true);
      $('#compare-button').prop('disabled', true);
      $('#advance-compare-button').prop('disabled', true);
    }
  });

  $('#confirm-delete').on('click', '.btn-ok', function(evt) {
    var testIds = [];
    var checked = $('[name="test-checkbox"]:checked');
    checked.each(function(test) {
      testIds.push(parseInt($(this).attr('data-testid'), 10));
    });

    var modal = $(evt.delegateTarget);
    var data = {
      action: 'delete_tests',
      testids: testIds
    };
    modal.modal('hide');
    $('body').addClass('loading');
    $.post('/daytona_actions.php', data, function (response) {
      $('body').removeClass('loading');
      if (response.status === 'OK') {
        $('#status-message .modal-header').removeClass('bg-danger');
        $('#status-message .modal-header').addClass('bg-success');
        $('#status-message .modal-title').text('Success!');
        $('#status-message .modal-body').text('Test successfully deleted.');
        $('#status-message').on('hide.bs.modal', function(evt) {
          window.location.reload();
        });
      } else {
        $('#status-message .modal-header').removeClass('bg-success');
        $('#status-message .modal-header').addClass('bg-danger');
        $('#status-message .modal-title').text('Error!');
        $('#status-message .modal-body').text(response.message);
      }
      $('#status-message').modal('show');
    }, 'json').fail(function(error) {
      $('body').removeClass('loading');
      $('#status-message .modal-body').text(error.statusText);
      $('#status-message').modal('show');
    });
  });

});
