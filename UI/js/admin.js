$(document).ready(function() {

  $('#submit-button').on('click', function(evt) {
    evt.preventDefault();
    var sendData = {
      action: 'set_user_frameworks',
      username: $('#admin-form [name="username"]').val(),
      frameworks: [],
    };
    $('#admin-form input[name^="checkbox-frameworks"]').each(function (cb) {
      sendData.frameworks.push({
        frameworkid: parseInt($(this).attr('data-frameworkid'), 10),
        checked: $(this).is(':checked')
      });
    });

    $('body').addClass('loading');
    $.post('/daytona_actions.php', sendData, function(response) {
      $('body').removeClass('loading');
      if (response.status === 'OK') {
        $('#status-message .modal-header').removeClass('bg-danger');
        $('#status-message .modal-header').addClass('bg-success');
        $('#status-message .modal-title').text('Success!');
        $('#status-message .modal-body').html('Frameworks updated');
      } else {
        $('#status-message .modal-header').removeClass('bg-success');
        $('#status-message .modal-header').addClass('bg-danger');
        $('#status-message .modal-title').text('Error!');
        $('#status-message .modal-body').text(response.message);
      }
      $('#status-message').on('hide.bs.modal', function(evt) {
        window.location.reload();
      });
      $('#status-message').modal('show');
    }, 'json').fail(function(error) {
      $('body').removeClass('loading');
      $('#status-message .modal-body').text(error.statusText);
      $('#status-message').modal('show');
    });
  });
});

function adminSelectAll() {
  $('input[type="checkbox"]').filter(function() {
    return !this.disabled;
  }).prop('checked', true);
}

function adminClearAll() {
  $('input[type="checkbox"]').filter(function() {
    return !this.disabled;
  }).prop('checked', false);
}
