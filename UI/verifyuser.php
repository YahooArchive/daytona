<?php
/**
 * User: dmittal
 * Date: 3/2/17
 * Time: 3:40 PM
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
