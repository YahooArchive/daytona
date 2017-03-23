<?php
$secret_word = 'c9nwFUDili';
unset($userId);
if (isset($_COOKIE['login'])) {
      list($c_userId,$cookie_hash) = explode(',',$_COOKIE['login']);
      if (password_verify($c_userId.$secret_word, $cookie_hash)) {
          $userId = $c_userId;
      } else {
          header("Location: /login.php?incorrect=true");
      }
}
else {
      header("Location: /login.php");
}

unset($userIsAdmin);
require('lib/common.php');
$db = initDB();
if(isset($userId)) {
  $userAccountData = getUserAccount($db, $userId);
  $userState = $userAccountData->user_state;
  $userIsAdmin = $userAccountData->is_admin;
    if($userState != "Active") {
        header("Location: /login.php");
    }
}
?>
