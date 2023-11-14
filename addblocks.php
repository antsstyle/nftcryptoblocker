<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\Session;
use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

Session::checkSession();

$blockLists = CoreDB::getBlockLists();
$userTwitterID = $_SESSION['usertwitterid'];

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

$errors = 0;
$successful = 0;
foreach ($blockLists as $blockList) {
    $blockListNames[] = $blockList['name'];
}
foreach ($blockListNames as $blockListName) {
    $listToSet = filter_input(INPUT_POST, str_replace(" ", "_", $blockListName), FILTER_SANITIZE_STRING);
    if (!is_null($listToSet)) {
        $parameters = explode("_", $listToSet);
        if (!in_array($parameters[1], $blockListNames)) {
            $errors++;
            continue;
        }
        if ($parameters[0] == "block" || $parameters[0] == "unblock" || $parameters[0] == "mute" || $parameters[0] == "unmute") {
            $success = CoreDB::markListForUser($userTwitterID, $parameters[1], $parameters[0]);
        } else if ($parameters[0] == "noaction") {
            $success = true;
        } else {
            $errors++;
            $success = false;
            continue;
        }
        if (!$success) {
            $errors++;
        } else {
            $successful++;
        }
    }
}
$phraseSettings = filter_input(INPUT_POST, "phrases", FILTER_SANITIZE_STRING);
$urlSettings = filter_input(INPUT_POST, "urls", FILTER_SANITIZE_STRING);
$nftProfilePicturesSettings = filter_input(INPUT_POST, "nftprofilepictures", FILTER_SANITIZE_STRING);
$cryptoUserNamesSettings = filter_input(INPUT_POST, "cryptousernames", FILTER_SANITIZE_STRING);
$NFTFollowersSettings = filter_input(INPUT_POST, "nftfollowers", FILTER_SANITIZE_STRING);
$centralDatabaseSettings = filter_input(INPUT_POST, "centraldatabase", FILTER_SANITIZE_STRING);
$cryptoSpambotsSettings = filter_input(INPUT_POST, "cryptospambots", FILTER_SANITIZE_STRING);
$followerWhitelistSettings = filter_input(INPUT_POST, "followerwhitelist", FILTER_SANITIZE_STRING);

$automationSavedSuccess = CoreDB::updateUserAutomationSettings($userTwitterID, $phraseSettings, $urlSettings,
                $nftProfilePicturesSettings, $cryptoUserNamesSettings, $NFTFollowersSettings,
                $centralDatabaseSettings, $cryptoSpambotsSettings, $followerWhitelistSettings);
?>

<html>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <link rel="stylesheet" href=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.css"; ?> type="text/css">
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
            <script src=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.js"; ?>></script>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            <?php
            $homepage = Config::HOMEPAGE_URL;
            $settingspage = Config::SETTINGSPAGE_URL;
            $adminURL = Config::ADMIN_URL;
            $adminName = Config::ADMIN_NAME;
            if ($successful == 0) {
                echo "No options were specified. Go to the settings page to modify your settings.";
            } else if ($errors == 0) {
                echo "Block list settings and automation settings saved successfully. If you want to change your "
                . "settings, you can go back to the settings page.<br/><br/>You can go to the Stats page on the left to see how many accounts have been "
                . "blocked/muted for you, and how many are queued. ";
            } else if ($errors > 0 && $automationSavedSuccess) {
                echo "Automation settings were saved successfully, but block list settings were not saved successfully."
                . " $errors errors were encountered. Go back to the homepage to try"
                . " again, or contact <a href=$adminURL>$adminName</a> on Twitter if the problem persists.";
            } else {
                echo "Automation and block list settings were not saved successfully; $errors errors were encountered. "
                . "Go back to the homepage to try"
                . " again, or contact <a href=$adminURL>$adminName</a> on Twitter if the problem persists.";
            }
            ?>
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>
