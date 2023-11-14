<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CachedVariables;
use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;
use Antsstyle\NFTCryptoBlocker\Core\Session;
use Antsstyle\NFTCryptoBlocker\Credentials\APIKeys;
use Abraham\TwitterOAuth\TwitterOAuth;

Session::checkSession();

$connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret);
try {
    $response = $connection->oauth("oauth/request_token", ["oauth_callback" => "https://antsstyle.com/nftcryptoblocker/results"]);
    $httpcode = $connection->getLastHttpCode();
    if ($httpcode != 200) {
        LogManager::$webLogger->error("Failed to get request token!");
        // Show error page
    }
} catch (\Exception $e) {
    
}
$oauth_token = $response['oauth_token'];
$oauth_token_secret = $response['oauth_token_secret'];
$oauth_callback_confirmed = $response['oauth_callback_confirmed'];
$_SESSION['oauth_token'] = $oauth_token;
$_SESSION['oauth_token_secret'] = $oauth_token_secret;
$oauth_token_array['oauth_token'] = $oauth_token;
try {
    $url = $connection->url('oauth/authenticate', array('oauth_token' => $oauth_token));
} catch (\Exception $e) {
    
}

$blockCount = CoreDB::getCachedVariable(CachedVariables::CACHED_TOTAL_BLOCKS_COUNT);
if ($blockCount !== null && $blockCount !== false) {
    if ($blockCount > 1000000000) {
        $blockCount = round($blockCount / 1000000000, 1, PHP_ROUND_HALF_DOWN) . "B";
    } else if ($blockCount > 100000000) {
        $blockCount = round($blockCount / 1000000, 0, PHP_ROUND_HALF_DOWN) . "M";
    } else if ($blockCount > 10000000) {
        $blockCount = round($blockCount / 1000000, 1, PHP_ROUND_HALF_DOWN) . "M";
    } else if ($blockCount > 1000000) {
        $blockCount = round($blockCount / 1000000, 2, PHP_ROUND_HALF_DOWN) . "M";
    } else if ($blockCount > 1000) {
        $blockCount = floor($blockCount / 1000) . "K";
    }
}
$muteCount = CoreDB::getCachedVariable(CachedVariables::CACHED_TOTAL_MUTES_COUNT);
if ($muteCount !== null && $muteCount !== false) {
    if ($muteCount > 1000000000) {
        $muteCount = round($muteCount / 1000000000, 1, PHP_ROUND_HALF_DOWN) . "B";
    } else if ($muteCount > 100000000) {
        $muteCount = round($muteCount / 1000000, 0, PHP_ROUND_HALF_DOWN) . "M";
    } else if ($muteCount > 10000000) {
        $muteCount = round($muteCount / 1000000, 1, PHP_ROUND_HALF_DOWN) . "M";
    } else if ($muteCount > 1000000) {
        $muteCount = round($muteCount / 1000000, 2, PHP_ROUND_HALF_DOWN) . "M";
    } else if ($muteCount > 1000) {
        $muteCount = floor($muteCount / 1000) . "K";
    }
}
?>

<html>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <link rel="stylesheet" href=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.css"; ?> type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:site" content="@antsstyle" />
        <meta name="twitter:title" content="NFT Artist & Cryptobro Blocker" />
        <meta name="twitter:description" content="Auto-blocks or auto-mutes NFT artists and cryptobros on your timeline and/or in your mentions." />
        <meta name="twitter:image" content="<?php echo Config::CARD_IMAGE_URL; ?>" />
    </head>
    <title>
        NFT Artist & Cryptobro Blocker
    </title>
    <body>
        <div class="main">
            <script src=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.js"; ?>></script>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            <p>
                This app can automatically block or mute cryptobros and NFT artists for you.
            </p>
            <?php
            if ((!is_null($blockCount) && $blockCount !== false) || (!is_null($muteCount) && $muteCount !== false)) {
                echo "<p>So far, it has performed ";
                if ((!is_null($blockCount) && $blockCount !== false)) {
                    echo "<b>" . $blockCount . "</b> blocks ";
                }
                if ((!is_null($muteCount) && $muteCount !== false)) {
                    if ((!is_null($blockCount) && $blockCount !== false)) {
                        echo "and ";
                    }
                    echo "<b>" . $muteCount . "</b> mutes ";
                }
                echo "on behalf of users.</p>";
            }
            ?>
            <p>
                Once you sign in, you will be taken to the settings page where you can decide what conditions to set. 
                The app will not block or mute anything until you save your settings.
            </p>
            <p>
                To use this app or change your settings, you must first sign in with Twitter. Use the button below to proceed.
            </p>
            <br/>
            <a href="<?php echo $url; ?>">
                <img alt="Sign in with Twitter" src="src/images/signinwithtwitter.png"
                     width=158" height="28">
            </a>
            <br/><br/>
            <p>
                Hosting this app costs money, and developing this app is taking a lot of my time at the moment. If you'd like to support me, 
                <a href="https://patreon.com/antsstyle" target="_blank">I have a patreon here.</a> I'd be very grateful for your support! It will 
                allow me to pay for the hosting costs and spend more time on developing this app and others.
                <br/><br/>
                <a href="https://www.patreon.com/bePatron?u=406925" data-patreon-widget-type="become-patron-button">Become a Patron!</a>
                <script async src="https://c6.patreon.com/becomePatronButton.bundle.js"></script>
            </p>
            <hr>
            <h2>FAQs (for more information, see the Info page on the left)
                <h3>Does this app perform any actions if I sign in but don't save any settings?</h3>
                <p>
                    No. The app will not perform any actions on your account until you save your settings. 
                    If you do not save any settings, it will do nothing.
                </p>
                <h3>Why does this app need so many permissions?</h3>
                <p>
                    The Twitter API only allows developers to request 'read', 'write' and 'direct message' permissions. 
                    As this app needs to be able to read your home timeline, and block or mute users for you (which count as "writes"), 
                    it needs both read and write permissions. 
                    <br/><br/>
                    This means that on the authorization page after pressing the Sign In with Twitter button, the app requests many write permissions 
                    that it doesn't need or use (such as posting tweets). At present there is no way around this, but the upcoming newer version of the 
                    Twitter API will enable the app to only request the exact permissions it needs. When this is available, I will implement it.
                    <br/><br/>
                    This app does not request direct message permissions as it does not interact with your direct messages in any way.
                </p>
                <h3>Is the source code for this app available?</h3>
                <p>
                    Yes. You can find it here: <a href="https://github.com/antsstyle/nftcryptoblocker">https://github.com/antsstyle/nftcryptoblocker</a>
                </p>

        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>
