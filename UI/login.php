<?php
/**
 * This file contains code related to user account login, new user account registration, user session, and logout
 */

function logout() {
    unset($_COOKIE["login"]);
    setcookie("login", null, -1);
}

/**
 * Insert new user details into `LoginAuthentication`
 *
 * @param $db - database handle
 * @param $user - username
 * @param $password - password
 * @param $email - user email
 * @param $state - user account state
 * @return bool - return true if successful else return false
 */
function registerUser($db, $user, $password, $email, $state) {
    if(getUserAccount($db, $user, $email)) {
        return false;
    }
    $query = "INSERT IGNORE INTO LoginAuthentication (username, password, email, user_state) " .
        "VALUES( :username, :password, :email, :user_state )";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':username', $user, PDO::PARAM_STR);
    $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':user_state', $state, PDO::PARAM_STR);
    $stmt->execute();
    return true;
}

/**
 * Give access of all framework to a particular user ID.
 *
 * @param $db - database handle
 * @param $user - username
 * @return bool - return true if successful else return false
 */
function giveFrameworkAccess($db, $user) {
    $query = "select frameworkid from ApplicationFrameworkMetadata";
    $stmt = $db->prepare($query);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $query = "INSERT INTO CommonFrameworkAuthentication (username, administrator, frameworkid) VALUES( :userid, 0, :frameworkid ) ON DUPLICATE KEY UPDATE frameworkid = :frameworkid";
        $stmt = $db->prepare($query);
            $stmt->bindValue(':userid', $user, PDO::PARAM_STR);
            $stmt->bindValue(':frameworkid', $row['frameworkid'], PDO::PARAM_INT);
            $stmt->execute();
    }
    return true;
}

/**
 * This function sends email on user's email ID with verification link which contain unique code
 *
 * @param $email - Email ID of logged in user
 * @param $code - Unique code for validating user
 * @param $user - username
 */
function sendEmailValidation($email, $code, $user) {
    $message = "<html><head>Welcome to Daytona</head><body><p>Validate your email here: <a href='http://52.42.114.108/login.php?email_user=$user&email_code=$code'>Validate Me</a></p><p>Thanks!</p>";
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    $headers .= 'From: Daytona <DAYTONA_DO_NOT_REPLY>' . "\r\n";
    mail($email, "Daytona Registration", $message, $headers);
}

/**
 * This function verifies user email ID whenever user click on verification link it has received on his/her email. This
 * function verifies the unique code which is attached with verification link.
 *
 * @param $db - database handle
 * @param $user - username
 * @param $code - unique code attached with verification link
 * @return bool - return true if successful else return false
 */

function validateEmail($db, $user, $code) {
  $query = "SELECT user_state FROM LoginAuthentication WHERE username = :username";
  $stmt = $db->prepare($query);
  $stmt->bindValue(':username', $user, PDO::PARAM_STR);
  $stmt->execute();
  $user_state = $stmt->fetch(PDO::FETCH_OBJ)->user_state;
  $ex_user_state = explode(":", $user_state);
  if(sizeof($ex_user_state) < 3) {
    return false;
  }
  list($state_type, $valid_code, $attempts) = $ex_user_state;
  $success = false;
  $attemptsInt = intval($attempts) + 1;
  if($code == $valid_code) {
    $success = true;
    $c_query = "UPDATE LoginAuthentication SET user_state='Active' WHERE username = :username";
  }
  elseif($attemptsInt == 3) {
    $c_query = "UPDATE LoginAuthentication SET user_state='Flagged' WHERE username = :username";
  }
  else {
    $c_query = "UPDATE LoginAuthentication SET user_state='Email:$valid_code:$attemptsInt' WHERE username = :username";
  }
  $stmt = $db->prepare($c_query);
  $stmt->bindValue(':username', $user, PDO::PARAM_STR);
  $stmt->execute();
  return $success;
}

require('lib/common.php');
$db = initDB();

if(isset($_REQUEST["logout"])) {
    logout();
}

if(isset($_GET["email_code"]) && isset($_GET["email_user"])) {
    $email_validated = validateEmail($db, getParam("email_user"), getParam("email_code"));
}

$incorrect_login = isset($_GET["incorrect"]) ? getParam("incorrect") : false;
$not_active = isset($_GET["inactive"]) ? getParam("inactive") : false;
$conf = parse_ini_file('daytona_config.ini');
$cookie_key = $conf['cookie_key'];

// Handling user login and setting browser cookie with user details
if(isset($_REQUEST['username']) and isset($_REQUEST['password'])) {
    if(validatePassword($db, $_REQUEST['username'], $_REQUEST['password'])) {
        setcookie('login', $_REQUEST['username'].','.password_hash($_REQUEST['username'].$cookie_key, PASSWORD_DEFAULT));
        if(getUserAccount($db, $_REQUEST['username'])->user_state == "Active") {
            header("Location: /main.php");
        }
        else {
            $not_active = true;
        }
    }
    else {
        $incorrect_login = true;
    }
}

// if login cookie is already set then navigate to main.php directly
unset($username);
if (isset($_COOKIE['login'])) {
    list($c_username,$cookie_hash) = explode(',',$_COOKIE['login']);
    if (password_verify($c_username.$cookie_key, $cookie_hash)) {
        $username = $c_username;
        if(getUserAccount($db, $c_username)->user_state == "Active") {
            header("Location: /main.php");
        }
        else {
            $not_active = true;
        }
    } else {
        $incorrect_login = true;
    }
}

// New user registration handler
if(isset($_REQUEST["register_user"])) {
    $email_code = rand();
    if (validatePasswordPolicy($_REQUEST["register_password"])){
        $validNewAccount = registerUser($db, $_REQUEST["register_user"], $_REQUEST["register_password"], $_REQUEST["register_email"], "Email:$email_code:0");
        sendEmailValidation($_REQUEST["register_email"], $email_code, $_REQUEST["register_user"]);
	giveFrameworkAccess($db, $_REQUEST["register_user"]);
    }else{
        $validPassword = false;
        $error_msg = "<strong>User Registration Failed:</strong> Password failed to meet password policies<br>Password should contain : 8-12 characters, atleast one lowercase character, one uppercase character, one digit, atleast one special character : @#-_$%^&+=§!?";
    }
}
?>

<html>
<head>
  <title>Daytona Login</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/secondary.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
  <script src="js/jquery.menu-aim.js"></script>
  <script src="js/navigation-bar.js"></script>
  <script src="js/daytona.js"></script>
</head>

<body class="login-body">
<?php if($incorrect_login): ?>
<div class="alert alert-danger fade in login-alert">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <strong>Login Failed:</strong> Incorrect ID and password combination
</div>
<?php endif; ?>
<?php if(isset($email_validated) && $email_validated): ?>
<div class="alert alert-success fade in login-alert">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <strong>Email Validated:</strong> Account is ready for use
</div>
<?php elseif(isset($email_validated) && !$email_validated): ?>
<div class="alert alert-danger fade in login-alert">
   <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
   <strong>Email Validated Failed:</strong> Incorrect Email Validation Code
</div>
<?php endif; ?>
<?php if(isset($validPassword) and !$validPassword): ?>
    <div class="alert alert-danger fade in login-alert">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
        <?php echo $error_msg ?>
    </div>
<?php endif; ?>
<?php if($not_active): ?>
<div class="alert alert-danger fade in login-alert">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <strong>Login Failed:</strong> Account is not active
</div>
<?php endif; ?>
<?php if(isset($validNewAccount) and !$validNewAccount): ?>
<div class="alert alert-danger fade in login-alert">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <strong>Registration Failed:</strong> Account username or email already exists
</div>
<?php elseif(isset($validNewAccount) and $validNewAccount): ?>
<div class="alert alert-success fade in login-alert">
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  <strong>Registration Completed:</strong> Account has been created. Please verify your account using the link provided in the email
</div>
<?php endif; ?>
<div class="login-cover"></div>
<div class="container login-container">
  <h2 class="daytona-title"><img src="images/daytona_text.png" style="width:60%"></h2>
  <form class="form-horizontal" role="form" method="post" action="login.php">
    <div class="form-group">
      <div class="col-sm-12">
        <input type="text" class="form-control" name="username" placeholder="User ID">
      </div>
    </div>
    <div class="form-group">
      <div class="col-sm-12">
        <input type="password" class="form-control" name="password" placeholder="Password">
      </div>
    </div>
    <div class="form-group">
      <div class="col-sm-12">
        <button type="submit" class="btn btn-primary float-right" style="min-width: 100px">Sign In</button>
        <button type="button" class="btn btn-default float-right" style="min-width: 100px; margin-right: 5px" data-toggle="modal" data-target="#registerModal">Register</button>
      </div>
    </div>
  </form>
</div>

<div class="modal fade" id="registerModal" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-blue">
                <button type='button' class='close close-blue' data-dismiss='modal'>&times;</button>
                <h4 class='modal-title h4-blue'>Register</h4>
            </div>
            <form role="form" name="registerForm" method="post" action="login.php" onsubmit="return validatePasswordForm('registerForm', 'register_password', 'register_password2')">
            <div class='modal-body'>
                <label>User ID</label>
                <input type="text" name="register_user" placeholder="User ID" required pattern="^[a-zA-Z][a-zA-Z0-9_]*$" oninvalid="setError('Please enter alphanumeric characters only')" onchange="try{setError('')}catch(e){}">
                <label>Email</label>
                <input type="email" name="register_email" placeholder="Email" required>
                <label>Password</label>
                <input type="password" name="register_password" placeholder="Password" required>
                <label>Verify</label>
                <input type="password" name="register_password2" placeholder="Re-Type Password" required>
		<br>
                <h5 style="color:RED">Password Policy : 8-12 characters, atleast one lowercase character, one uppercase character, one digit, atleast one special character : @#-_$%^&+=§!?</h5>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-default' data-dismiss='modal'>Close</button>
                <button type='submit' class='btn btn-primary'>Register</button>
            </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
