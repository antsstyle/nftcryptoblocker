<?php

namespace Antsstyle\NFTArtistBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\Session;
use Antsstyle\NFTArtistBlocker\Credentials\APIKeys;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;
use Antsstyle\NFTArtistBlocker\Core\Config;
use Abraham\TwitterOAuth\TwitterOAuth;

Session::checkSession();

if (!$_SESSION['oauth_token']) {
    header("Location: https://antsstyle.com/nftartistblocker/error", true, 302);
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
$access_token = $connection->oauth("oauth/access_token", ["oauth_verifier" => $requestOAuthVerifier]);

$success = CoreDB::insertUserInformation($access_token);
error_log("Success: $success");
if ($success) {
    $_SESSION['usertwitterid'] = $access_token['user_id'];
    $location = Config::SETTINGSPAGE_URL;
    header("Location: $location", true, 302);
} else {
    $location = Config::HOMEPAGE_URL . "failure";
    header("Location: $location", true, 302);
}