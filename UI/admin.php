<?php

/**
 * This is framework settings page where user can select/deselect framework for UI display.
 *
 * Refer file admin.js for all Jquery function used in this file.
 */

require('lib/auth.php');

$frameworks = getFrameworks($db);
$frameworksParsed = array();
foreach ($frameworks as $row) {
  $frameworkName = $row['frameworkname'];
  if (!isset($frameworksParsed[$frameworkName])) {
    $frameworksParsed[$frameworkName] = array(
      'check'       => 0,
      'product'     => $row['productname'],
      'frameworkid' => $row['frameworkid'],
      'title'       => $row['title']
    );
  }
  if ($row['administrator']) {
    $frameworksParsed[$frameworkName]['owner'] = $row['username'];
  }
  if ($row['username'] == $userId) {
    if ($row['administrator']) {
      $frameworksParsed[$frameworkName]['check'] = 2;
    } else {
      $frameworksParsed[$frameworkName]['check'] = 1;
    }
  }
}

$pageTitle = "Framework Settings";
include_once('lib/header.php');
include_once('lib/popup.php');
?>
    <div class="content-wrapper" id="page-content">
        <div class="col-xs-12" id="content-div">
<div id="main-panel-alt">
  <div class="panel panel-info panel-sub-main">
    <div class="panel-heading">
      Active Frameworks
    </div>
    <div class="panel-body">
      <form class="zero-margin small-padding" role="form" id="admin-form" method="post">
        <div class="alert alert-warning admin-alert zero-margin">
          Select frameworks to be displayed in framework drop-down menus.<br>
          Note: <strong>Frameworks with administrator privileges are in yellow and cannot be deselected.</strong>
        </div>

        <table class="table table-hover table-striped sortable admin-table table-condensed">
          <thead>
            <tr>
              <th style="width:30%" data-defaultsort="asc">Framework</th>
              <th style="width:10%">Owner</th>
              <th style="width:20%">Product</th>
              <th style="width:40%">Title</th>
            </tr>
          </thead>
          <tbody>
<?php
foreach ($frameworksParsed as $frameworkName => $frameworkData) {
  $checked = '';
  $classAdmin = '';
  if($frameworkData['check'] == 1) {
    $checked = 'checked';
  }
  if($frameworkData['check'] == 2) {
    $checked = 'checked disabled';
    $classAdmin = 'warning';
  }
  echo "            <tr class=\"$classAdmin\">\n";
  echo "              <td class=\"align-left\">\n";
  echo "                <div class=\"checkbox zero-margin\">\n";
  echo "                  <label><input type=\"checkbox\" name=\"checkbox-frameworks[]\" data-frameworkid=\"" . $frameworkData['frameworkid'] . "\" $checked>$frameworkName</label>\n";
  echo "                </div>\n";
  echo "              </td>\n";
  echo "              <td>" . $frameworkData['owner'] . "</td>\n";
  echo "              <td>" . $frameworkData['product'] . "</td>\n";
  echo "              <td>" . $frameworkData['title'] . "</td>\n";
  echo "            </tr>\n";
}
?>
          </tbody>
        </table>
      </form>
    </div>
  </div>
</div>

<script src="js/admin.js"></script>
<script src="js/bootstrap-sortable.js"></script>
<script type="text/javascript">
$(document).ready(function() {
  buildTopNavBar('Global', '');
  setDescription('Framework Settings');
  buildLeftPanel();
  buildLeftPanelFramework();
  buildUserAccountMenu('<?php echo $userId; ?>');
  buildLeftPanelGlobal();
  loadNavigationBar();
<?php addFrameworkDropdownJS($db, $userId); ?>
});
</script>

<div id="main-panel-alt-bottom">
  <div class="action-buttons-div-left">
    <button type="button" onclick="adminSelectAll()" class="btn btn-info btn-action">
      <i class="fa fa-check-square-o fa-lg"></i> Select All
    </button>
    <button type="button" onclick="adminClearAll()" class="btn btn-info btn-action">
      <i class="fa fa-square-o fa-lg"></i> Clear All
    </button>
    <button type="button" class="btn btn-primary btn-action float-right" id="submit-button">
      Save
    </button>
  </div>
</div>

        </div>
    </div> <!-- .content-wrapper -->
    </main> <!-- .cd-main-content -->

<?php include_once('lib/footer.php'); ?>
