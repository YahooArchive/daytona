$(document).ready(function() {

  $('#test-form #button-save-run').on('click', function(evt) {
    evt.preventDefault();
    $('#test-form [name="action"]').val('save_run_test');
    saveTest();
  });

  $('#test-form #button-save').on('click', function(evt) {
    evt.preventDefault();
    $('#test-form [name="action"]').val('save_test');
    saveTest();
  });

  function saveTest(runTest) {
    $('body').addClass('loading');
    $.post('/daytona_actions.php', $('#test-form').serialize(), function (response) {
      $('body').removeClass('loading');
      if (response.status === 'OK') {
        $('#status-message .modal-header').removeClass('bg-danger');
        $('#status-message .modal-header').addClass('bg-success');
        $('#status-message .modal-title').text('Success!');
        var message = 'Test #' + response.test.testid + ' ';
        if (response.test.new) {
          message += 'created';
        } else {
          message += 'updated';
        }
        if (response.test && response.test.running) {
          message += ' & queued';
        }
        $('#status-message .modal-body').text(message);
        $('#status-message').on('hide.bs.modal', function(evt) {
          if (response.test.running) {
            window.location.href = '/?frameworkid=' + response.test.frameworkid;
          } else {
            window.location.href = '/test_info.php?testid=' + response.test.testid;
          }
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
  };

  $('#confirm-delete').on('click', '.btn-ok', function(evt) {
    var modal = $(evt.delegateTarget);
    var testId = $('[name="f_testid"]').val();
    var data = {
      action: 'delete_test',
      f_testid: testId
    };
    modal.modal('hide');
    $('body').addClass('loading');
    $.post('/daytona_actions.php', data, function (response) {
      $('body').removeClass('loading');
      if (response.status === 'OK') {
        $('#status-message .modal-header').addClass('bg-success');
        $('#status-message .modal-header').text('Success');
        $('#status-message .modal-body').text('Test successfully deleted.');
        $('#status-message').on('hide.bs.modal', function(evt) {
          window.location.href = '/?frameworkid=' + response.test.frameworkid;
        });
      } else {
        $('#status-message .modal-header').addClass('bg-danger');
        $('#status-message .modal-header').text('Error');
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
