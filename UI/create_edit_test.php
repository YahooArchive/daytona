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

$testData = null;
if ($action == 'create') {
  if (! $frameworkId) {
    diePrint("No framework passed in");
  }
  $testId = null;
} else {
  // Edit or Clone
  $testId = getParam('testid');
  if (! $testId) {
    diePrint("No test ID passed in");
  }

  $testData = getTestById($db, $testId, true);
  if (!$testData) {
    diePrint("Could not find test ID: $testId");
  }
  $frameworkId = $testData['frameworkid'];
  $frameworkName = $testData['frameworkname'];
  $frameworkData = getFrameworkById($db, $frameworkId, true);

  if (! $frameworkData) {
    diePrint("Could not find framework: $frameworkName ($frameworkId)");
  }

  if ($action == 'clone') {
    $testId = null;
  }
}

$pageTitle = ucfirst($action) . " Test";
include_once('lib/header.php');

$deleteMessage = "WARNING: Deleting this test cannot be undone. Are you SURE you want to continue?";
include_once('lib/popup.php');
?>
<div class="content-wrapper" id="page-content">
 <div class="col-xs-12" id="content-div">
<form id="test-form">

<div id="main-panel-alt">
  <div class="zero-margin zero-padding">
    <input type="hidden" name="f_frameworkid" value="<?php echo $frameworkId; ?>">
    <input type="hidden" name="f_testid" value="<?php echo $testId; ?>">
    <div class="panel panel-info panel-sub-main">
      <div class="panel-heading">
        Test Fields (required in green)
      </div>
      <div class="panel-body" id='zero-padding'>
        <table class='table table-hover' id='result-table'>
          <tbody>
            <tr>
              <td class='active success' style='width: 30%; vertical-align: middle'>
                Title
              </td>
              <td class='zero-line-height tiny-padding'>
                <div class='form-group zero-line-height zero-margin'>
                  <input type='text' class='form-control form-input' name='f_title' id='f_title' value="<?php echo $testData ? $testData['title'] : null; ?>" required>
                </div>
              </td>
            </tr>
            <tr>
              <td class='active' style= 'vertical-align: middle'>
                Purpose
              </td>
              <td class='zero-line-height tiny-padding'>
                <div class='form-group zero-line-height zero-margin'>
                  <textarea class='form-control purpose-textarea' rows="4" name='f_purpose' id='f_purpose'><?php echo $testData ? $testData['purpose'] : null; ?></textarea>
                </div>
              </td>
            </tr>
            <tr>
              <td class='active success' style='vertical-align: middle'>
                Execution Host
              </td>
              <td class='zero-line-height tiny-padding'>
                <div class='form-group zero-line-height zero-margin'>
                  <input type='text' class='form-control form-input' name='f_execution' id='f_execution' value="<?php echo $testData ? $testData['execution'][0] : $frameworkData['execution_host']['default_value']; ?>" required>
                </div>
              </td>
            </tr>
            <tr>
              <td class='active' style='vertical-align: middle'>
                Statistics Hosts
              </td>
              <td class='zero-line-height tiny-padding'>
                <div class='form-group zero-line-height zero-margin'>
                  <input type='text' class='form-control form-input' name='f_statistics' id='f_statistics' value="<?php
		    if(array_key_exists("statistics",$testData)){
                      echo implode(",", $testData['statistics']);
                    }
                  ?>">
                </div>
              </td>
            </tr>
            <tr>
              <td class='active' style='vertical-align: middle'>
                Test Priority
              </td>
              <td class='zero-line-height tiny-padding'>
                <select class='form-control float-left' name='f_priority' id='f_priority'>
<?php
for ($x = 1; $x <=5; $x++) {
  if ($testData && intval($testData['priority']) == $x) {
    echo "                  <option value=\"$x\" selected>$x</option>\n";
  } else {
    echo "                  <option value=\"$x\">$x</option>\n";
  }
}
?>
                </select>
              </td>
            </tr>
            <tr>
              <td class='active' style='vertical-align: middle'>
                Timeout (minutes)
              </td>
              <td class='zero-line-height tiny-padding'>
                <div class='form-group zero-line-height zero-margin'>
                  <input type="number" min="0" class='form-control form-input' name='f_timeout' id='f_timeout' value="<?php echo $testData ? $testData['timeout'] : $frameworkData['default_timeout']; ?>">
                </div>
              </td>
            </tr>
            <tr>
              <td class='active' style='vertical-align: middle'>
                CC
              </td>
              <td class='zero-line-height tiny-padding'>
                <div class='form-group zero-line-height zero-margin'>
                  <input type='text' class='form-control form-input' name='f_cc_list' id='f_cc_list' value="<?php echo $testData ? $testData['cc_list'] : $frameworkData['default_cc_list']; ?>">
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div> <!-- .panel .panel-info .panel-sub-main -->

    <div class="panel panel-info panel-sub-main">
      <div class="panel-heading">
        Test Arguments
      </div>
      <div class="panel-body" id='zero-padding'>
	<table class='table table-hover' id='result-table'>
          <tbody>
<?php
if (isset($frameworkData['arguments'])) {
  foreach ($frameworkData['arguments'] as $row) {
    $argId = $row['framework_arg_id'];

    if ($row['widget_type'] == 'hidden') {
      echo "              <input type=\"hidden\" name=\"f_arg_"
        . $row['framework_arg_id']
        . "\" id=\"f_arg_$argId\" value=\"";
      if ($testData) {
        echo $testData['arguments'][$argId]['value'];
      } else {
        echo $row['argument_default'];
      }
      echo "\">\n";
      continue;
    }

    echo "            <tr>\n";

    // Argument name and optional description
    echo "              <td class=\"active\" style=\"width: 30%; vertical-align: middle\">";
    if ($row['argument_description']) {
      echo "<a href=\"#\" data-toggle=\"tooltip\" title=\""
        . $row['argument_description'] . "\">"
        . $row['argument_name'] . "</a>";
    } else {
      echo $row['argument_name'];
    }
    echo "</td>\n";

    // Argument input
    echo "              <td class=\"zero-line-height tiny-padding\">\n";
    switch($row['widget_type']) {
    case 'text':
      echo "                <div class=\"form-group zero-line-height zero-margin\">\n";
      echo "                  <input type='text' class='form-control form-input' name=\"f_arg_$argId\" id=\"f_arg_$argId\" value=\"";
      if ($testData) {
        echo $testData['arguments'][$argId]['value'];
      } else {
        echo $row['argument_default'];
      }
      echo "\">\n";
      echo "                </div>\n";
      break;
    case 'select':
      echo "                <select class=\"select-sm float-left\" "
        . "id=\"f_arg_$argId\" name=\"f_arg_$argId\" autocomplete=\"off\">\n";

      $options = explode(',', $row['argument_values']);
      $selected = $testData ? $testData['arguments'][$argId]['value'] : $row['argument_default'];
      foreach($options as $opt) {
        echo "                  <option value=\"$opt\""
          . ($selected == $opt ? 'selected' : '') 
          . ">$opt</option>\n";
      }
      echo "                </select>\n";
      break;
      /* DEPRECATED
    case "checkbox":
      $checked = $row["argument_default"] == "on" ? "checked" : "";
      echo "                  <div class='form-group zero-line-height zero-margin'>\n";
      echo "                    <input type='checkbox' class='form-input float-left' name='f_arg_" .
        $row["framework_arg_id"] . "' id='f_arg_" . $row["framework_arg_id"] . "' $checked autocomplete='off'>\n";
      echo "                  </div>\n";
      break;
     */
    }
    echo "              </td>\n";
    echo "            </tr>\n";
  }
}
?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<div id="main-panel-alt-bottom">
  <div class='col-md-6' id='action-buttons-div-left'>
<?php
  $ownerAuthDisable = $testData["username"] == $userId ||
    $action != "edit" ||
    $userIsAdmin ? "" : "disabled";
  if ($action == 'edit' && $testId) {
    echo "    <button type=\"button\" class=\"btn btn-danger btn-action\" data-toggle=\"modal\" data-target=\"#confirm-delete\" id=\"delete-button\" $ownerAuthDisable>";
    echo "      Delete\n";
    echo "    </a>\n";
  }
?>
</div>
<div class="col-md-6" id ="action-buttons-div" >
    <input name="action" type="hidden">
<?php
$cancel_href = $testId ? "test_info.php?testid=$testId" : "main.php?frameworkid=$frameworkId";
echo "    <a href=\"$cancel_href\" type=\"button\" class=\"btn btn-default btn-action\">\n";
?>
      Cancel
    </a>
    <button class="btn btn-default btn-action" id="button-save" <?php echo $ownerAuthDisable;?>>
      Save
    </button>
    <button class="btn btn-success btn-action" id="button-save-run" <?php echo $ownerAuthDisable;?>>
      Save &amp; Run
    </button>
  </div>
</div>

</form>
        </div>
    </div> <!-- .content-wrapper -->
    </main> <!-- .cd-main-content -->
<script src="js/create_edit_test.js"></script>
<script type="text/javascript">
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip({container: 'body'});
  buildTopNavBar('<?php echo $frameworkName; ?>', '<?php echo $testId; ?>', '<?php echo $userId; ?>');
  setDescription('<?php echo ucfirst($action); ?> Test');
  buildLeftPanel();
  buildUserAccountMenu('<?php echo $userId; ?>');
  buildLeftPanelTest(<?php echo $frameworkId; ?>, '<?php echo $userId; ?>');
  buildLeftPanelFramework('<?php echo $frameworkName; ?>', <?php echo $frameworkId; ?>);
  buildLeftPanelGlobal();
  loadNavigationBar();
<?php addFrameworkDropdownJS($db, $userId); ?>
});
</script>

<?php include_once('lib/footer.php'); ?>
