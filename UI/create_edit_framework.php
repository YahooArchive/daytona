<?php
// ini_set('display_errors',1);
// ini_set('display_startup_errors',1);
// error_reporting(-1);

require('lib/auth.php');
list($frameworkId, $frameworkName, $frameworkData) = initFramework($db, true);

$action = getParam('action') ?: 'create';
$action = strtolower($action);
if (! preg_match('/^(create|clone|edit)$/', $action)) {
  diePrint("Unknown action: $action");
}

if ($action == 'clone' || $action == 'edit') {
  if (! $frameworkData) {
    diePrint("No framework name or framework ID passed in");
  }
  if ($action == 'clone') {
    $frameworkId = null;
    $frameworkName = null;
  }
}

$pageTitle = ucfirst($action) . " Framework";
include_once('lib/header.php');

$deleteMessage = "WARNING: Deleting this framework will also delete all its associated tests. Furthermore, this action cannot be undone. Are you SURE you want to continue?";
include_once('lib/popup.php');
?>


<div class="content-wrapper" id="page-content">
        <div class="col-xs-12" id="content-div">

<form id="framework-form">

<div id="main-panel-alt">
  <div class="zero-margin zero-padding">
    <input type="hidden" name="f_frameworkid" value="<?php echo $frameworkId; ?>">
    <div class="panel panel-info panel-sub-main">
      <div class="panel-heading">Framework Properties (required in green)</div>
      <div class="panel-body" id='zero-padding'>
          <table class='table table-hover' id='result-table'>
            <tbody>
              <tr>
                <td class="active success" style="width: 30%; vertical-align: middle">Name</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                    <input type="text" class="form-control form-input" name="f_frameworkname" id="f_frameworkname" value="<?php echo ($action == 'edit' && $frameworkData)  ? $frameworkData['frameworkname'] : null; ?>" required <?php echo ($action == 'edit' && $frameworkData) ? 'readonly' : null; ?>>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active success" style="vertical-align: middle">Product</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                  <input type="text" class="form-control form-input" name="f_productname" id="f_productname" value="<?php echo $frameworkData ? $frameworkData['productname'] : null; ?>" required>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active" style="vertical-align: middle">Title</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                  <input type="text" class="form-control form-input" name="f_title" id="f_title" value="<?php echo $frameworkData ? $frameworkData['title'] : null; ?>">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active success" style="vertical-align: middle">Framework Owner</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                  <input type="text" class="form-control form-input" name="f_frameworkowner" id="f_frameworkowner" value="<?php if ($action == 'clone') { echo $userId; } else { echo $frameworkData ? $frameworkData['frameworkowner'] : $userId; } ?>" required pattern="^[a-zA-Z][a-zA-Z0-9_]*$">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active" style="vertical-align: middle">Run Exec Script As (User ID)</td>
                <td class="zero-line-height tiny-padding" style="vertical-align: middle">
                  <div class="form-group zero-line-height zero-margin">
                    <input type="text" class="form-control form-input" name="f_exec_user" id="f_exec_user" value="<?php echo $frameworkData ? $frameworkData['exec_user'] : $userId; ?>" required pattern="^[a-zA-Z][a-zA-Z0-9_]*$">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active" style="vertical-align: middle">Default Execution Hosts</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                    <input type="text" class="form-control form-input" name="f_execution_host" id="f_execution_host" value="<?php echo ($frameworkData && $frameworkData['execution_host']) ? $frameworkData['execution_host']['default_value'] : null ?>">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active" style="vertical-align: middle">Default Statistics Hosts</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                  <input type="text" class="form-control form-input" name="f_statistics_host" id="f_statistics_host" value="<?php echo ($frameworkData && $frameworkData['statistics_host']) ? $frameworkData['statistics_host']['default_value'] : null ?>">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active" style="width: 30%; vertical-align: middle">Purpose</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                  <textarea class="form-control purpose-textarea" rows="4" name="f_purpose" id="f_purpose"><?php echo $frameworkData ? $frameworkData['purpose'] : null; ?></textarea>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active" style="vertical-align: middle">
                  <a href='#' data-toggle='tooltip' title="No Timeout = 0">Default Timeout</a>
                </td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                  <input type="number" class="form-control form-input" name="f_default_timeout" id="f_default_timeout" placeholder="minutes" min="0" value="<?php echo $frameworkData ? $frameworkData['default_timeout'] : 0 ?>">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active success" style="vertical-align: middle">Test Files Root Directory</td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                    <input type="text" class="form-control form-input" name="f_file_root" id="f_file_root" value="<?php echo $frameworkData ? $frameworkData['file_root'] : '/export/crawlspace/daytona_exec' ?>">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active success" style="vertical-align: middle">
                <a href='#' data-toggle='tooltip' title="Example: system:/path/to/script.sh">Execution Script Path</a>
                </td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                  <input type="text" class="form-control form-input" name="f_execution_script_location" id="f_execution_script_location" placeholder="/path/to/script.sh" value="<?php echo $frameworkData ? $frameworkData['execution_script_location'] : null; ?>" required>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="active zero-line-height" style="vertical-align: middle">
                  <a href='#' data-toggle='tooltip' title="Feature disabled">Profilers</a>
                </td>
                <td class="zero-line-height tiny-padding">
                  <div class="form-group zero-line-height zero-margin">
                    <input type="text" class="form-control form-input" name="f_profilers" id="f_profilers" disabled>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="panel panel-info panel-sub-main test-report-panel">
      <div class="panel-heading">Test Report Page Settings</div>
      <div class="panel-body" id='zero-padding'>
        <table class="table table-hover" id="test-report-table">
          <thead>
            <tr>
              <th style="width: 40%">Filename</th>
              <th style="width: 40%">Title</th>
              <th style="width: 10%">Row</th>
              <th style="width: 5%">&nbsp;</th>
              <th style="width: 5%">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
        <div style="padding-left:5px;">
          <span class="checkbox form-checkbox">
            <label>
              <input type="checkbox" name="f_email_results" value="1" <?php echo ($frameworkData && $frameworkData['email_results']) ? 'checked' : null ?>>
              Email Results
            </label>
          </span>
          <span class="checkbox form-checkbox">
            <label>
              <input type="checkbox" name="f_email_include_results" value="1" <?php echo ($frameworkData && $frameworkData['email_include_results']) ? 'checked' : null ?>>
              Include <strong>results.csv</strong> in email
            </label>
          </span>
          <div class= "add-item" id="add_test_report_item">
            <button type="button" class="modify-add add-test-report-item">
              <i class="fa fa-plus fa-lg"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="panel panel-info panel-sub-main argument-panel">
      <div class="panel-heading">Arguments</div>
      <div class="panel-body" id='zero-padding'>
        <table class="table table-hover" id="args_table">
          <thead>
            <tr>
              <th style="width: 20%">Argument Name</th>
              <th style="width: 20%">Description</th>
              <th style="width: 10%">Widget Type</th>
              <th style="width: 20%">Values (for Select widget only)</th>
              <th style="width: 20%">Default Value</th>
              <th style="width: 5%">&nbsp;</th>
              <th style="width: 5%">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
        <div style="padding-left:5px;">
          <div class="form-select shift-popover">
<?php
$argpass_help = "By default, Daytona passes in arguments to the execution script in order.<br><br>" .
                "You also have the option of using long arguments of the format: " .
		"<strong>--arg=value</strong>";
echo "            <a href='#' data-toggle='popover' data-placement='top' data-html='true' " .
                 "data-trigger='hover' title=\"Help\" data-content='$argpass_help'>Argument Passing:</a>\n";
?>
            <select class="select-sm" name="f_argument_passing_format">
              <option value="arg_order" <?php echo ($frameworkData && $frameworkData['argument_passing_format'] == 'arg_order') ? 'selected' : null ?>>Use argument order</option>
              <option value="name_order" <?php echo ($frameworkData && $frameworkData['argument_passing_format'] == 'name_order') ? 'selected' : null ?>>Use named arguments</option>
            </select> </div> <div class="add-item" id="add_args_item">
            <button type="button" class="add-argument modify-add">
              <i class="fa fa-plus fa-lg"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

<div id="main-panel-alt-bottom">
  <div class='col-md-6' id='action-buttons-div-left'>
<?php
  $ownerAuthDisable = $frameworkData['frameworkowner'] == $userId ||
    $action != "edit" ||
    $userIsAdmin ? "" : "disabled";
  $adminAuthDisable = $userIsAdmin ? "" : "disabled";
  if ($action == 'edit') {
    echo "    <button type=\"button\" class=\"btn btn-danger btn-action\" data-toggle=\"modal\" data-target=\"#confirm-delete\" id=\"delete-button\" $adminAuthDisable>";
    echo "      Delete\n";
    echo "    </a>\n";
  }
?>
  </div>
  <div id="action-buttons-div" class="col-md-6">
    <input type="hidden" name="action" value="save_framework">
    <a href="/<?php echo $frameworkId ? "?frameworkid=$frameworkId" : "" ?>" type="button" class="btn btn-default btn-action">
      Cancel
    </a>
<?php
  if ($action == 'edit' && $frameworkId) {
    echo "    <a href=\"/create_edit_framework.php?action=clone&frameworkid=";
    echo $frameworkId;
    echo "\" type=\"button\" class=\"btn btn-default btn-action\">\n";
    echo "      Clone\n";
    echo "    </a>\n";
  }
?>
    <button type="submit" class="btn btn-primary btn-action" <?php echo $ownerAuthDisable;?>>
      Save
    </button>
  </div>
</div>
</div>
</form>
        </div>
    </div> <!-- .content-wrapper -->
    </main> <!-- .cd-main-content -->

<script src="js/create_edit_framework.js"></script>
<script type="text/javascript">
$(document).ready(function() {
  $('[data-toggle="tooltip"]').tooltip({container: 'body'});
  $('[data-toggle="popover"]').popover(); 
  buildTopNavBar('<?php echo $frameworkName ?: 'Global'; ?>', '', '<?php echo $userId; ?>');
  setDescription('<?php echo ucfirst($action); ?> Framework');
  buildUserAccountMenu('<?php echo $userId; ?>');
  buildLeftPanel();
<?php if ($frameworkId && $action == 'edit'): ?>
  buildLeftPanelTest(<?php echo $frameworkId ?>, '<?php echo $userId; ?>');
  buildLeftPanelFramework('<?php echo $frameworkName; ?>', <?php echo $frameworkId; ?>);
<?php else: ?>
  buildLeftPanelFramework();
<?php endif ?>
  buildLeftPanelGlobal();
  loadNavigationBar();
<?php addFrameworkDropdownJS($db, $userId); ?>
  fitPurposeTextArea();

// If editing a framework, insert test report items & arguments
// TODO: Find a better place to put this
<?php
if ($frameworkData && isset($frameworkData['test_report'])) {
  foreach ($frameworkData['test_report'] as $tr) {
    echo "    addTestReportItem(null, " . json_encode($tr) . ");\n";
  }
}
if ($frameworkData && isset($frameworkData['arguments'])) {
  foreach($frameworkData['arguments'] as $arg) {
    echo "    addFrameworkArgument(null, " . json_encode($arg) . ", " . ($action == "clone" ? "true" : "false") . ");\n";
  }
}
?>
});
</script>

<?php include_once('lib/footer.php'); ?>
