/**
 * This file implements all JQuery functions used on create_edit_framework.php page
 */

// Adjust height of purpose Text Area
function fitPurposeTextArea() {
    var tdHeight = $('td').innerHeight() * 4 - 17;
    $('.purpose-textarea').height(tdHeight);
}

// Add test report row function for appending new row in test report setting table
function addTestReportItem(target, reportConfig) {
    var container = $('#test-report-table');
    var reportCount = container.find('tbody > tr').length;

    // Test Report row that contains all elements
    var row = $('<tr></tr>')
        .addClass('test-report-row');

    // Test Report filename
    var tdFilename = $('<td></td>')
        .addClass('zero-line-height tiny-padding');
    var divFilename = $('<div></div>')
        .addClass('form-group zero-line-height zero-margin');
    var inputFilename = $('<input></input>')
        .addClass('form-control form-input testreport_filename')
        .prop('required', true)
        .attr('type', 'text')
        .attr('name', 'f_testreport[filename][]');
    if (reportConfig) {
        inputFilename.val(reportConfig.filename);
    }
    $(divFilename).append(inputFilename);
    $(tdFilename).append(divFilename);
    $(row).append(tdFilename);

    // Test Report title
    var tdTitle = $('<td></td>')
        .addClass('zero-line-height tiny-padding');
    var divTitle = $('<div></div>')
        .addClass('form-group zero-line-height zero-margin');
    var inputTitle = $('<input></input>')
        .addClass('form-control form-input')
        .attr('type', 'text')
        .attr('name', 'f_testreport[title][]');
    if (reportConfig) {
        inputTitle.val(reportConfig.title);
    }
    $(divTitle).append(inputTitle);
    $(tdTitle).append(divTitle);
    $(row).append(tdTitle);

    // Delete current Test Report item button
    var tdDel = $('<td></td>')
        .addClass('zero-line-height tiny-padding centered');
    var delButton = $('<button></button>')
        .addClass('modify-del delete-test-report-item');
    var delIcon = $('<i></i>')
        .addClass('fa fa-trash fa-lg');
    $(delButton).append(delIcon);
    $(tdDel).append(delButton);
    $(row).append(tdDel);

    if (target && $(target).parents('.test-report-row').length) {
        // Insert new row after the row where the add button was clicked
        $(target).parents('.test-report-row').after(row);
    } else {
        // The add button was outside of arg table, so there must be no arguments
        $('#test-report-table > tbody:last').append(row);
        // Hide the outside-table button
        $('#add_test_report_item').hide();
    }
}

function frameworkArgsTableChangeWidget(target, argConfig) {
    var widgetType = $(target).val();
    var row = $(target).parents('tr');
    var values = $(row).find('[name="f_arguments[argument_values][]"]');
    if (widgetType === 'select') {
        values.prop('readonly', false);
    } else {
        values.prop('readonly', true);
        values.val('');
    }

    // Empty out the argument default box before creating input/select element
    var argDefaultContainer = $(row).find('.argument-default-td');
    argDefaultContainer.empty();

    var newDefaultValues;
    switch(widgetType) {
        case 'select':
            newDefaultValues = $('<select></select>')
                .addClass('form-control select-sm')
                .attr('name', 'f_arguments[argument_default][]')
                .css('width', '100%');
            if (argConfig) {
                var values = argConfig.argument_values.split(',');
                values.forEach(function (val) {
                    val = val.trim();
                    var opt = $('<option></option>')
                        .val(val)
                        .text(val)
                    if (argConfig.argument_default === val) {
                        opt.prop('selected', true);
                    }
                    newDefaultValues.append(opt);
                });
            }
            break;
        default:
            newDefaultValues = $('<input></input>')
                .addClass('form-control form-input')
                .attr('type', 'text')
                .attr('name', 'f_arguments[argument_default][]');
            if (argConfig) {
                newDefaultValues.val(argConfig.argument_default);
            }
            break;
    }
    argDefaultContainer.append(newDefaultValues);
}

// Update dropdown list for user to select default value in test arguments based to input values provided by user
function frameworkArgsTableSelectOptions(target, argConfig) {
    var select = $(target).parents('tr').find('[name="f_arguments[argument_default][]"]');
    select.empty();
    var values = $(target).val().split(',');
    values.forEach(function (val) {
        varl = val.trim();
        var opt = $('<option></option>')
            .val(val)
            .text(val)
        if (argConfig && argConfig.argument_default === val) {
            opt.prop('selected', true);
        }
        select.append(opt);
    });
}

// This functions adds framework argument row when user click "add row" in test argument settings on framework
// definition page
function addFrameworkArgument(target, argConfig, clone) {
    if (clone == 'undefined'){
        clone = false;
    }
    var container = $('#args_table');
    var argCount = container.find('tbody > tr').length;

    var row = $('<tr></tr>')
        .addClass('arg-row');

    // Argument name & ID
    var tdArgName = $('<td></td>')
        .addClass('zero-line-height tiny-padding');
    var divArgName = $('<div></div>')
        .addClass('form-group zero-line-height zero-margin');
    var inputArgName = $('<input></input>')
        .addClass('form-control form-input')
        .attr('type', 'text')
        .attr('name', 'f_arguments[argument_name][]')
        .attr('required', true);
    var inputArgId = $('<input></input')
        .attr('name', 'f_arguments[arg_id][]')
        .attr('type', 'hidden');
    if (argConfig) {
        inputArgName.val(argConfig.argument_name);
    }
    if (argConfig && !clone) {
        inputArgId.val(argConfig.framework_arg_id);
    }
    $(divArgName).append(inputArgId);
    $(divArgName).append(inputArgName);
    $(tdArgName).append(divArgName);
    $(row).append(tdArgName);

    // Argument description
    var tdArgDesc = $('<td></td>')
        .addClass('zero-line-height tiny-padding');
    var divArgDesc = $('<div></div>')
        .addClass('form-group zero-line-height zero-margin');
    var inputArgDesc = $('<input></input>')
        .addClass('form-control form-input')
        .attr('type', 'text')
        .attr('name', 'f_arguments[argument_description][]');
    if (argConfig) {
        inputArgDesc.val(argConfig.argument_description);
    }
    $(divArgDesc).append(inputArgDesc);
    $(tdArgDesc).append(divArgDesc);
    $(row).append(tdArgDesc);

    // Argument widget
    var tdArgWidget = $('<td></td>')
        .addClass('zero-line-height tiny-padding');
    var divArgWidget = $('<div></div>')
        .addClass('form-group zero-line-height zero-margin');
    var selectArgWidget = $('<select></select>')
        .addClass('form-control select-sm argument-widget-type')
        .attr('name', 'f_arguments[widget_type][]')
        .change(function() { frameworkArgsTableChangeWidget(this); });
    ['text', 'select', 'hidden'].forEach(function (wType) {
        var option = $('<option></option>')
            .val(wType)
            .text(wType.charAt(0).toUpperCase() + wType.slice(1));
        if (argConfig && argConfig.widget_type === wType) {
            option.prop('selected', true);
        }
        $(selectArgWidget).append(option);
    });
    $(divArgWidget).append(selectArgWidget);
    $(tdArgWidget).append(divArgWidget);
    $(row).append(tdArgWidget);

    // Argument values (for select)
    var tdArgValues = $('<td></td>')
        .addClass('zero-line-height tiny-padding');
    var divArgValues = $('<div></div>')
        .addClass('form-group zero-line-height zero-margin');
    var inputArgValues = $('<input></input>')
        .addClass('form-control form-input')
        .attr('type', 'text')
        .attr('name', 'f_arguments[argument_values][]')
        .attr('readonly', 'true')
        .change(function() { frameworkArgsTableSelectOptions(this); });
    if (argConfig) {
        inputArgValues.val(argConfig.argument_values);
        if (argConfig.widget_type === 'select') {
            inputArgValues.removeAttr('readonly');
        }
    }
    $(divArgValues).append(inputArgValues);
    $(tdArgValues).append(divArgValues);
    $(row).append(tdArgValues);

    // Argument defaults
    var tdArgDefault = $('<td></td>')
        .addClass('zero-line-height tiny-padding argument-default-td');
    var divArgDefault = $('<div></div>')
        .addClass('form-group zero-line-height zero-margin');
    // We don't generate the input field here, instead relying on the
    // frameworkArgsTableChangeWidget() call
    $(tdArgDefault).append(divArgDefault);
    $(row).append(tdArgDefault);
    frameworkArgsTableChangeWidget(selectArgWidget, argConfig);

    // Delete current argument button
    var tdDel = $('<td></td>')
        .addClass('zero-line-height tiny-padding centered');
    var delButton = $('<button></button>')
        .addClass('modify-del delete-argument')
        .attr('type', 'button');
    var delIcon = $('<i></i>').addClass('fa fa-trash fa-lg');
    $(delButton).append(delIcon);
    $(tdDel).append(delButton);
    $(row).append(tdDel);

    if (target && $(target).parents('.arg-row').length) {
        // Insert new row after the row where the add button was clicked
        $(target).parents('.arg-row').after(row);
    } else {
        // The add button was outside of arg table, so there must be no arguments
        $('#args_table > tbody:last').append(row);
        // Hide the outside-table button
        $('#add_args_item').hide();
    }
}

// Delete a target's <table> row
// Works for both Test Report and Framework Argument
function deleteTableRow(target) {
    var tr = $(target).parents('tr');
    if (!tr) {
        console.warn('Could not find parent table row to delete');
        return;
    }
    tr.fadeOut(300, function () {
        $(this).remove();
    });
}

// Form submission and other button action on create_edit_framework page
$(document).ready(function() {
    // Event handlers for the add/del buttons
    $('.test-report-panel').on('click', '.add-test-report-item', function(evt) {
        evt.preventDefault();
        addTestReportItem(evt.target);
    });
    $('.test-report-panel').on('click', '.delete-test-report-item', function(evt) {
        evt.preventDefault();
        deleteTableRow(evt.target);
    });
    $('.argument-panel').on('click', '.add-argument', function(evt) {
        evt.preventDefault();
        addFrameworkArgument(evt.target);
    });
    $('.argument-panel').on('click', '.delete-argument', function(evt) {
        evt.preventDefault();
        deleteTableRow(evt.target);
    });
    $('#delete-button').on('click', function(evt) {
        evt.preventDefault();
    });

    $('#framework-form').on('submit', function(evt) {
        evt.preventDefault();

        var stat_hosts = $('#f_statistics_host').val().split(",");
        var exec_host = $('#f_execution_host').val().split(",");

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

        var files = "";
        $('.testreport_filename').each(function(d){
            var filename = $(this).val();
            if (~filename.toLowerCase().indexOf("cpu_usage") || ~filename.toLowerCase().indexOf("memory_usage")){
                var file_arr = filename.split(':');
                if((file_arr.length < 2) || (file_arr[1].length < 1)){
                    var last = file_arr[0].substring(file_arr[0].lastIndexOf("/") + 1, file_arr[0].length);
                    files += last + ", ";
                }
            }
        });

        if (files.length > 0){
            files = files.replace(/, $/g,'');
            $('#status-message .modal-header').removeClass('bg-success');
            $('#status-message .modal-header').addClass('bg-danger');
            $('#status-message .modal-title').text('Error!');
            $('#status-message .modal-body').text('Process name is mandatory for files : ' + files);
            $('#status-message').modal('show');
            return;
        }
        $('body').addClass('loading');

        $.post('/daytona_actions.php', $(this).serialize(), function (response) {
            $('body').removeClass('loading');
            if (response.status === 'OK') {
                $('#status-message .modal-header').removeClass('bg-danger');
                $('#status-message .modal-header').addClass('bg-success');
                $('#status-message .modal-title').text('Success!');
                $('#status-message .modal-body').text('Framework saved.');
                $('#status-message').on('hide.bs.modal', function(evt) {
                    if (response.framework && response.framework.frameworkid) {
                        window.location.href = '/?frameworkid=' + response.framework.frameworkid;
                    } else {
                        window.location.href = '/';
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
    });

    $('#confirm-delete').on('click', '.btn-ok', function(evt) {
        var modal = $(evt.delegateTarget);
        var frameworkId = $('[name="f_frameworkid"]').val();
        var data = {
            action: 'delete_framework',
            f_frameworkid: frameworkId
        };
        modal.modal('hide');
        $('body').addClass('loading');
        $.post('/daytona_actions.php', data, function (response) {
            $('body').removeClass('loading');
            if (response.status === 'OK') {
                $('#status-message .modal-header').addClass('bg-success');
                $('#status-message .modal-header').text('Success');
                $('#status-message .modal-body').text('Framework and its tests were successfully deleted.');
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


