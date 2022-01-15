<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\Session;

Session::checkSession();

if (!$_SESSION['oauth_token']) {
    $errorURL = Config::HOMEPAGE_URL . "error";
    header("Location: $errorURL", true, 302);
    exit();
}

if (!$_SESSION['usertwitterid']) {
    $errorURL = Config::HOMEPAGE_URL . "error";
    header("Location: $errorURL", true, 302);
    exit();
}

$userInfo = CoreDB::getUserInfo($_SESSION['usertwitterid']);
if ($userInfo === false) {
    $errorURL = Config::HOMEPAGE_URL . "error";
    header("Location: $errorURL", true, 302);
    exit();
} else if ($userInfo === null) {
    $errorURL = Config::HOMEPAGE_URL . "error";
    header("Location: $errorURL", true, 302);
    exit();
}

$userStats = CoreDB::getUserStats($_SESSION['usertwitterid']);
if ($userStats !== false) {
    if (isset($userStats['Block'])) {
        $blockCount = $userStats['Block'];
    } else {
        $blockCount = 0;
    }
    if (isset($userStats['Mute'])) {
        $muteCount = $userStats['Mute'];
    } else {
        $muteCount = 0;
    }
}
?>

<html>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <?php Core::echoSideBar(); ?>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            <h2>Statistics</h2>
            Below are statistics about how many accounts this app has blocked/muted for you so far.<br/><br/>
            <?php
            if ($userStats === false) {
                echo "A database error occurred retrieving statistics, try again later.";
            } else {
                echo "Blocked accounts: $blockCount<br/>";
                echo "Muted accounts: $muteCount</br>";
            }
            ?>
        </div>
    </body>
</html>