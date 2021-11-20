<?php

require 'vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\Session;
use Antsstyle\NFTArtistBlocker\Credentials\APIKeys;
use Abraham\TwitterOAuth\TwitterOAuth;

Session::checkSession();

$connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret);
$response = $connection->oauth("oauth/request_token", ["oauth_callback" => "https://antsstyle.com/nftartistblocker/results"]);
$httpcode = $connection->getLastHttpCode();
if ($httpcode != 200) {
    error_log("Failed to get request token!");
    // Show error page
}
$oauth_token = $response['oauth_token'];
$oauth_token_secret = $response['oauth_token_secret'];
$oauth_callback_confirmed = $response['oauth_callback_confirmed'];
$_SESSION['oauth_token'] = $oauth_token;
$_SESSION['oauth_token_secret'] = $oauth_token_secret;
$oauth_token_array['oauth_token'] = $oauth_token;
$url = $connection->url('oauth/authenticate', array('oauth_token' => $oauth_token));
?>

<html>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <title>
        NFT Artist Blocker
    </title>
    <body>
        <p>
            The NFT artist blocker can automatically block all NFT artists on Antsstyle's current list for your account. You can find the list here 
            (opens in a new tab):
            <a href="https://docs.google.com/spreadsheets/d/1FYwjh0hewS-KMseh51GdZFBXrE4136VdfwPn0wjW3qk/edit?usp=sharing" target="_blank">
                NFT Artists not worth your time or money</a>
        </p>
        <p>
            The process consists of two steps:
        <ol>
            <li>Signing in with Twitter</li>
            <li>Choosing to apply or unapply the blocklist</li>
        </ol>
        </p>
        <p>
            To use the NFT artist blocker, you must first sign in with Twitter. Use the button below to proceed.
        </p>
        <br/>
        <a href=<?php echo "$url" ?>>
            <img alt="Sign in with Twitter" src="src/images/signinwithtwitter.png"
                 width=158" height="28">
        </a>
    </body>
</html>
