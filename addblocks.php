<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\Session;
use Antsstyle\NFTArtistBlocker\Core\Config;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;

Session::checkSession();

$blockListNames = CoreDB::getBlockListNames();
$userTwitterID = $_SESSION['usertwitterid'];

if (!$_SESSION['usertwitterid']) {
    $errorURL = Config::HOMEPAGE_URL . "error";
    header("Location: $errorURL", true, 302);
    exit();
}

$errors = 0;
$successful = 0;
foreach ($blockListNames as $blockListName) {
    $listToSet = filter_input(INPUT_POST, str_replace(" ", "_", $blockListName), FILTER_SANITIZE_STRING);
    if ($listToSet !== null) {
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
$followerWhitelistSettings = filter_input(INPUT_POST, "followerwhitelist", FILTER_SANITIZE_STRING);

$automationSavedSuccess = CoreDB::updateUserAutomationSettings($userTwitterID, $phraseSettings, $urlSettings,
                $nftProfilePicturesSettings, $cryptoUserNamesSettings, $NFTFollowersSettings,
                $centralDatabaseSettings, $followerWhitelistSettings);
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
        <?php
        $homepage = Config::HOMEPAGE_URL;
        $settingspage = Config::SETTINGSPAGE_URL;
        $adminURL = Config::ADMIN_URL;
        $adminName = Config::ADMIN_NAME;
        if ($successful == 0) {
            echo "No options were specified. Go to the <a href=$settingspage>settings page</a> to modify your settings.";
        } else if ($errors == 0) {
            echo "Block list settings and automation settings saved successfully. If you want to change your settings, you can go back to the <a href=$settingspage>settings page</a>.";
        } else if ($errors > 0 && $automationSavedSuccess) {
            echo "Automation settings were saved successfully, but block list settings were not saved successfully."
            . " $errors errors were encountered. Go back to the <a href=$homepage>homepage</a> to try"
            . " again, or contact <a href=$adminURL>$adminName</a> on Twitter if the problem persists.";
        } else {
            echo "Automation and block list settings were not saved successfully; $errors errors were encountered. "
            . "Go back to the <a href=$homepage>homepage</a> to try"
            . " again, or contact <a href=$adminURL>$adminName</a> on Twitter if the problem persists.";
        }
        ?>
    </body>
</html>
