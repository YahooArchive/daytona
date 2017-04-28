$(document).ready(function() {

    $('#test-form #button-save-run').on('click', function(evt) {
        var valid = $('#test-form')[0].checkValidity();

        if (!valid){
            return;
        }
        evt.preventDefault();
        $('#test-form [name="action"]').val('save_run_test');
        saveTest();
    });

    $('#test-form #button-save').on('click', function(evt) {
        var valid = $('#test-form')[0].checkValidity();
        if (!valid){
            return;
        }
        evt.preventDefault();
        $('#test-form [name="action"]').val('save_test');
        saveTest();
    });

    function saveTest(runTest) {
        $('body').addClass('loading');

        var stat_hosts = $('#f_statistics').val().split(",");
        var exec_host = $('#f_execution').val().split(",");

        if(exec_host.length > 1){
            $('#status-message .modal-header').removeClass('bg-success');
            $('#status-message .modal-header').addClass('bg-danger');
            $('#status-message .modal-title').text('Error!');
            $('#status-message .modal-body').text("Only one IP address is allowed in Execution Host field");
            $('#status-message').modal('show');
            return;
        }

        for (var i = 0;i<stat_hosts.length;i++){
            if(stat_hosts[i].replace(" ","") == exec_host[0].replace(" ","")){
                $('#status-message .modal-header').removeClass('bg-success');
                $('#status-message .modal-header').addClass('bg-danger');
                $('#status-message .modal-title').text('Error!');
                $('#status-message .modal-body').text("Execution host and statistics host IP cannot be same");
                $('#status-message').modal('show');
                return;
            }
        }

        $.post('/daytona_actions.php', $('#test-form').serialize(), function (response) {
            $('body').addClass('ui-loading');
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
                        window.location.href = '/';
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
            $('body').removeClass('ui-loading');
            $('#status-message .modal-body').text(error.statusText);
            $('#status-message').modal('show');
        });
    };

    $('#f_strace').change(function() {
        if($(this).is(":checked")) {
            $('#strace_config').show();
            $('#f_strace_process').attr('disabled', false);
            $('#f_strace_delay').attr('disabled', false);
            $('#f_strace_duration').attr('disabled', false);
        }else {
            $('#strace_config').hide()
            $('#f_strace_process').attr('disabled', 'disabled');
            $('#f_strace_delay').attr('disabled', 'disabled');
            $('#f_strace_duration').attr('disabled', 'disabled');
        }

    });

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
                    window.location.href = '/';
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

