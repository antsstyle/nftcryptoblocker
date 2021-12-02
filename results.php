<?php

namespace Antsstyle\NFTCryptoBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Session;
use Antsstyle\NFTCryptoBlocker\Credentials\APIKeys;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\Config;
use Abraham\TwitterOAuth\TwitterOAuth;

Session::checkSession();

if (!$_SESSION['oauth_token']) {
    $location = Config::HOMEPAGE_URL . "error";
    header("Location: $location", true, 302);
    exit();
}

$request_token = [];
$request_token['oauth_token'] = $_SESSION['oauth_token'];
$request_token['oauth_token_secret'] = $_SESSION['oauth_token_secret'];

$requestOAuthToken = filter_input(INPUT_GET, 'oauth_token', FILTER_SANITIZE_STRING);
$requestOAuthVerifier = filter_input(INPUT_GET, 'oauth_verifier', FILTER_SANITIZE_STRING);

if ($request_token['oauth_token'] !== $requestOAuthToken) {
    // Show error, redirect user back to homepage
    error_log("Non-matching OAuth tokens - aborting.");
    exit();
}

$connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
        $request_token['oauth_token'], $request_token['oauth_token_secret']);
try {
    $access_token = $connection->oauth("oauth/access_token", ["oauth_verifier" => $requestOAuthVerifier]);
} catch (\Exception $e) {
    error_log("Could not get access token");
    error_log(print_r($e, true));
    $location = Config::HOMEPAGE_URL . "failure";
    header("Location: $location", true, 302);
}

if (isset($access_token)) {
    $success = CoreDB::insertUserInformation($access_token);
    if ($success) {
        $_SESSION['usertwitterid'] = $access_token['user_id'];
        $location = Config::SETTINGSPAGE_URL;
        header("Location: $location", true, 302);
    } else {
        $location = Config::HOMEPAGE_URL . "failure";
        header("Location: $location", true, 302);
    }
}

