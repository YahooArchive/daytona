<?php
/**
 * This is Daytona homepage which displays tests queues (currently running, currently waiting and last completed).
 * It provide basic test information like tests ID, framework name, test state etc.
 */

require('lib/auth.php');
list($frameworkId, $frameworkName, $frameworkData) = initFramework($db);

$pageTitle = "Queue";
include_once('lib/header.php');
?>


    <div class="content-wrapper" id="page-content">
        <div class="col-xs-12" id="content-div">
            <div id="main-panel-alt">
              <div>
                <div class="panel-body zero-padding">
                  <div class="col-xs-12 zero-padding float-right" style="margin-bottom: 8px">
                    <div class="checkbox" style="margin:0px">
                      <label class="float-right">
                        <input type="checkbox" name="owner">Only My Tests</input>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="panel panel-info panel-sub-main">
                <div class="panel-heading">Currently Running</div>
                <div class="panel-body zero-padding" id='currently-running'></div>
              </div>
              <div class="panel panel-warning panel-sub-main">
                <div class="panel-heading">Waiting Queue</div>
                <div class="panel-body zero-padding" id='waiting-queue'></div>
              </div>
              <div class="panel panel-success zero-margin">
                <div class="panel-heading">Last 15 Completed</div>
                <div class="panel-body zero-padding" id='last-completed'></div>
              </div>
            </div>
        </div>
    </div> <!-- .content-wrapper -->
    </main> <!-- .cd-main-content -->

<script src="js/main.js"></script>
<script type="text/javascript">
$(document).ready(function() {
//  alert("<?php echo $userId; ?>");
  $('[data-toggle="tooltip"]').tooltip();
  buildTopNavBar('<?php echo $frameworkName ?: 'Global'; ?>', '');
  setDescription('Queue');
  buildUserAccountMenu('<?php echo $userId; ?>');
  buildLeftPanel();
<?php if ($frameworkId): ?>
  buildLeftPanelTest(<?php echo $frameworkId; ?>, '<?php echo $userId; ?>');
  buildLeftPanelFramework('<?php echo $frameworkName; ?>', <?php echo $frameworkId; ?>);
<?php else: ?>
  buildLeftPanelFramework();
<?php endif ?>
  buildLeftPanelGlobal();
  loadNavigationBar();
<?php addFrameworkDropdownJS($db, $userId); ?>
  $('input[name=owner]').change(function() {
    var user;
    if($('input[name=owner]').prop("checked")){
      user="1";
    }else{
      user="2";
    } 
    queryAll(<?php echo $frameworkId ?: 'undefined'; ?>, user, <?php echo $userIsAdmin ?>, '<?php echo $userId ?>');
  });
  queryAll(<?php echo $frameworkId ?: 'undefined'; ?>, "2", <?php echo $userIsAdmin ?>, '<?php echo $userId ?>');
  setInterval(function() {
    var user;
    if($('input[name=owner]').prop("checked")){
      user="1";
    }else{
      user="2";
    }
    queryAll(<?php echo $frameworkId ?: 'undefined'; ?>, user, <?php echo $userIsAdmin ?>, '<?php echo $userId ?>');
  }, 2000);
  $("select[id=framework-select] option").each(function() {
    if($(this).text() == "<?php echo $frameworkName ?: ''; ?>") {
      $(this).attr('selected', 'selected');
    }
  });
});
</script>

<?php include_once('lib/footer.php'); ?>

