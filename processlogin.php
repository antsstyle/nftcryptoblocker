<?php

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\Session;

Session::checkSession();

$username = htmlspecialchars($_POST['username']);
$password = htmlspecialchars($_POST['password']);

if ($username === "") {
    $location = Config::HOMEPAGE_URL . "admin/login?error=invalidusername";
    header("Location: $location", true, 302);
    exit();
}

if ($password === "") {
    $location = Config::HOMEPAGE_URL . "admin/login?error=invalidpassword";
    header("Location: $location", true, 302);
    exit();
}

$userInfo = CoreDB::getUserByUsername($username);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if (password_verify($password, $userInfo['password'])) {
    error_log("Password verified.");
    $_SESSION['adminlogin'] = true;
    $_SESSION['usertwitterid'] = -1;
   // CoreDB::resetAdminUserLoginAttempts($username);
    $location = Config::HOMEPAGE_URL . "settings";
    header("Location: $location", true, 302);
    exit();
} else {
   // CoreDB::incrementAdminUserLoginAttempts($username);
    $location = Config::HOMEPAGE_URL . "twtadminlogin?error=incorrectpassword";
    header("Location: $location", true, 302);
    exit();
}