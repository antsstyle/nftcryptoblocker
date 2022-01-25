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
    if (isset($userStats['queueBlock'])) {
        $queuedBlockCount = $userStats['queueBlock'];
    } else {
        $queuedBlockCount = 0;
    }
    if (isset($userStats['queueMute'])) {
        $queuedMuteCount = $userStats['queueMute'];
    } else {
        $queuedMuteCount = 0;
    }
    $totalQueueCount = $queuedBlockCount + $queuedMuteCount;
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
                if ($totalQueueCount > 100) {
                    echo "Queued operations are done in batches of 15, once per 15 minutes, per user. As such, if you have many queued operations, "
                    . "expect them to take a while; check back again in a few hours to see how it is progressing.<br/><br/>";
                }
                echo "<b>There is currently a very large backlog of queued actions to process; as such, actions will take longer to process than the above.</b><br/><br/>";
                echo "Blocked accounts: $blockCount &nbsp;&nbsp; (Currently queued for blocking: $queuedBlockCount)</br>";
                echo "Muted accounts: $muteCount &nbsp;&nbsp;&nbsp;&nbsp; (Currently queued for muting: $queuedMuteCount)</br>";
            }
            ?>
        </div>
    </body>
</html>