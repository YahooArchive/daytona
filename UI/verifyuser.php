<?php
/**
 * This is simple web service to authenticate user name and password. It is created for python cli authentication.
 * In future, we can remove this file once we implement password verification mechanism which includes PHP hash
 * generation through python.
 */

$user = '';
$password = '';

if(isset($_POST["user"])) {
    $user = $_POST["user"];
}else{
    echo http_response_code(400);
    return;
}

if(isset($_POST["password"])) {
    $password = $_POST["password"];
}else{
    echo http_response_code(400);
    return;
}

require('lib/common.php');
$db = initDB();

if(validatePassword($db, $user,$password)) {
    echo http_response_code(200);
    return;
}
else {
    echo http_response_code(401);
    return;
}
