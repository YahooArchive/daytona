<?php
function getUserAccountsAsAdmin($db) {
    $query = "SELECT username, is_admin, email, user_state FROM LoginAuthentication";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require('lib/auth.php');
$pageTitle = "Account Settings";
include_once('lib/header.php');

if(isset($_REQUEST["oldPassword"])) {
    if(validatePassword($db, $userId, $_REQUEST["oldPassword"])) {
        $query = "UPDATE LoginAuthentication SET password = :password WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':password', password_hash($_REQUEST["newPassword"], PASSWORD_DEFAULT), PDO::PARAM_STR);
        $stmt->bindValue(':username', $userId, PDO::PARAM_STR);
        $stmt->execute();
        $passwordChangeSuccess = true;
    }
    else {
        $passwordChangeSuccess = false;
    }
}

$accountInfo = getUserAccount($db, $userId);
if($accountInfo->is_admin) {
  if(isset($_REQUEST["ua_user"])) {
    $query = "UPDATE LoginAuthentication SET email = :email, user_state = :state WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':email', $_REQUEST["ua_email"], PDO::PARAM_STR);
    $stmt->bindValue(':state', $_REQUEST["ua_state"], PDO::PARAM_STR);
    $stmt->bindValue(':username', $_REQUEST["ua_user"], PDO::PARAM_STR);
    $stmt->execute();
  }
  $userAccountsList = json_encode(getUserAccountsAsAdmin($db));
}
?>

<div class="content-wrapper" id="page-content">
  <div class="col-xs-12" id="content-div">

<?php if(isset($passwordChangeSuccess) and $passwordChangeSuccess): ?>
<div class="alert alert-success fade in login-alert-alt">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <strong>Password Changed</strong> User password has been successfully changed
</div>
<?php elseif(isset($passwordChangeSuccess) and !$passwordChangeSuccess): ?>
<div class="alert alert-danger fade in login-alert-alt">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <strong>Incorrect Password:</strong> Please check that your password was correctly entered
</div>
<?php endif; ?>

    <div class="panel panel-info panel-short" id="account-info-panel">
      <div class="panel-heading">Account Information</div>
      <div class="panel-body"></div>
    </div>
<?php if($accountInfo->is_admin): ?>
    <div class="panel panel-info zero-margin" id="account-admin-panel">
      <div class="panel-heading">Administrator</div>
      <div class="panel-body"></div>
    </div>
<?php endif; ?>
  </div>
</div>

<div class="modal fade" id="changePasswordModal" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header modal-header-blue">
        <button type='button' class='close close-blue' data-dismiss='modal'>&times;</button>
        <h4 class='modal-title h4-blue'>Change Password</h4>
      </div>
      <form role="form" name="changePasswordForm" method="post" action="account.php" onsubmit="return validatePasswordForm('changePasswordForm', 'newPassword', 'verifyPassword')">
      <div class='modal-body'>
        <input type="password" name="oldPassword" placeholder="Old Password" required>
        <input type="password" name="newPassword" placeholder="New Password" required>
        <input type="password" name="verifyPassword" placeholder="Re-Type New Password" required>
      </div>
      <div class='modal-footer'>
        <button type='button' class='btn btn-default' data-dismiss='modal'>Close</button>
        <button type='submit' class='btn btn-primary'>Submit</button>
      </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="accountSettingsModal" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header modal-header-blue">
        <button type='button' class='close close-blue' data-dismiss='modal'>&times;</button>
        <h4 class='modal-title h4-blue'>Account Settings</h4>
      </div>
      <form role="form" name="updateAccountForm" method="post" action="account.php">
      <div class='modal-body container'>
        <div class='account-row col-xs-12'>
          <label class='account-label col-sm-1 col-xs-12'>User</label>
          <label class='account-label-value col-sm-4 col-xs-12' id="account-setting-user"></label>
          <input type='hidden' id="account-setting-user-hidden" name="ua_user"></input>
        </div>
        <div class='account-row col-xs-12'>
          <label class='account-label col-sm-1 col-xs-12'>Email</label>
          <input type="email" class='col-sm-4 col-xs-12' id="account-setting-email" name="ua_email" required></input>
        </div>
        <div class='account-row col-xs-12'>
          <label class='account-label col-sm-1 col-xs-12'>State</label>
          <select class='col-sm-4 col-xs-12' id="account-setting-state" name="ua_state">
            <option>Active</option>
            <option>Pending</option>
            <option>Inactive</option>
          </select>
        </div>
        <div class='account-row col-xs-12'>
          <label class='account-label col-sm-1 col-xs-12'>Role</label>
          <label class='account-label-value col-sm-4 col-xs-12' id="account-setting-role"></label>
        </div>
      </div>
      <div class='modal-footer'>
        <button type='button' class='btn btn-default' data-dismiss='modal'>Close</button>
        <button type='submit' class='btn btn-primary'>Submit</button>
      </div>
      </form>
    </div>
  </div>
</div>

</main>
<script src="js/account.js"></script>
<script type="text/javascript">
$(document).ready(function() {
  buildTopNavBar('Global', '', '<?php echo $userId; ?>');
  setDescription("Account Settings");
  buildLeftPanel();
  buildLeftPanelFramework();
  buildUserAccountMenu('<?php echo $userId; ?>');
  buildLeftPanelGlobal();
  loadNavigationBar();
  buildAccountCard(<?php echo "'$userId', '" .
    $accountInfo->email . "', " .
    $accountInfo->is_admin . ", '" .
    $accountInfo->user_state . "'"; ?>);
<?php addFrameworkDropdownJS($db, $userId); ?>
<?php
  if($accountInfo->is_admin) {
    echo "buildAdminPanel('$userAccountsList');\n";
  }
?>
});
</script>

<?php include_once('lib/footer.php'); ?>
