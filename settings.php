<?php

namespace Antsstyle\NFTCryptoBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\Session;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

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

//$newUser = $userInfo['newuser'];

$blockLists = CoreDB::getBlockLists();
$centralDBCount = CoreDB::getCentralDBCount();
$blockableURLsPage = Config::HOMEPAGE_URL . "blockableurls";
$blockablePhrasesPage = Config::HOMEPAGE_URL . "blockablephrases";
?>


<html>
    <script src="coreajax.js"></script>
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
    <body onload="getUserInformation('<?php echo $_SESSION['usertwitterid']; ?>')">
        <div class="main">
            <?php Core::echoSideBar(); ?>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            <div class="start">
                Twitter authentication successful. Choose the settings you want below and press the 'Save Settings' button at the bottom of the page
                (if you already have saved settings, they will be preselected).
            </div>
            <p>
                <?php
                /*if ($newUser === "Y") {
                    echo "Error: New users cannot currently use the app at this time.<br/><br/>";
                    exit();
                }*/
                if (!$blockLists) {
                    echo "Error: could not load block list names.<br/><br/>";
                    exit();
                }
                echo "<h2>Lists</h2>";
                echo "If you choose to block or mute a list, it will automatically act on new entries for you when the list is updated. "
                . "You won't need to sign in again unless you want to change your settings.<br/><br/>";
                echo "<form action=\"addblocks\" method=\"post\">";
                foreach ($blockLists as $blockList) {
                    $blockListName = $blockList['name'];
                    $blockListURL = $blockList['url'];
                    if (is_null($blockListURL)) {
                        echo "<b>$blockListName:</b><br/><br/>";
                    } else {
                        echo "<b>$blockListName:</b> <a href=\"$blockListURL\" target=\"_blank\">(list)</a><br/><br/>";
                    }
                    echo "<div class=\"formsection\" style=\"max-width:480px;\">";
                    echo "<input type=\"radio\" id=\"block $blockListName\" name=\"$blockListName\" value=\"block_$blockListName\">";
                    echo "<label for=\"block $blockListName\"> Block </label>";
                    echo "<input type=\"radio\" id=\"unblock $blockListName\" name=\"$blockListName\" value=\"unblock_$blockListName\">";
                    echo "<label for=\"unblock $blockListName\"> Unblock </label>";
                    echo "<input type=\"radio\" id=\"mute $blockListName\" name=\"$blockListName\" value=\"mute_$blockListName\">";
                    echo "<label for=\"mute $blockListName\"> Mute </label>";
                    echo "<input type=\"radio\" id=\"unmute $blockListName\" name=\"$blockListName\" value=\"unmute_$blockListName\">";
                    echo "<label for=\"unmute $blockListName\"> Unmute </label>";
                    echo "<input type=\"radio\" id=\"noaction $blockListName\" name=\"$blockListName\" value=\"noaction_$blockListName\" checked=\"checked\">";
                    echo "<label for=\"noaction $blockListName\"> Do nothing </label></div><br/>";
                }
                echo "<h2>Auto-block and auto-mute options</h2>";
                echo "The options below will scan tweets which appear in your timeline and tweets that mention you, and block or mute"
                . " the user who posted that tweet depending on which option you select.<br/><br/>";
                echo "<b>Tweets containing crypto/NFT related phrases:</b> <a href=\"$blockablePhrasesPage\" target=\"_blank\">"
                . "(list of phrases)</a>";
                echo "<br/><br/><div class=\"formsection\">";
                echo "<input type=\"radio\" id=\"block phrases\" name=\"phrases\" value=\"block_phrases\">";
                echo "<label for=\"block phrases\"> Block </label>";
                echo "<input type=\"radio\" id=\"mute phrases\" name=\"phrases\" value=\"mute_phrases\">";
                echo "<label for=\"mute phrases\"> Mute </label>";
                echo "<input type=\"radio\" id=\"noaction phrases\" name=\"phrases\" value=\"noaction_phrases\" checked=\"checked\">";
                echo "<label for=\"noaction phrases\"> Do nothing </label></div><br/><br/>";
                echo "<b>Tweets containing crypto/NFT URLs, or users with those URLs in their profile:</b> "
                . "<a href=\"$blockableURLsPage\" target=\"_blank\">(list of URLs)</a>";

                echo "<br/><br/><div class=\"formsection\">";
                echo "<input type=\"radio\" id=\"block urls\" name=\"urls\" value=\"block_urls\">";
                echo "<label for=\"block urls\"> Block </label>";
                echo "<input type=\"radio\" id=\"mute urls\" name=\"urls\" value=\"mute_urls\">";
                echo "<label for=\"mute urls\"> Mute </label>";
                echo "<input type=\"radio\" id=\"noaction urls\" name=\"urls\" value=\"noaction_urls\" checked=\"checked\">";
                echo "<label for=\"noaction urls\"> Do nothing </label></div><br/><br/>";

                echo "<b>Users with 'verified' NFT profile pictures:</b>";
                echo "<br/><br/><div class=\"formsection\">";
                echo "<input type=\"radio\" id=\"block nftprofilepictures\" name=\"nftprofilepictures\" value=\"block_nftprofilepictures\">";
                echo "<label for=\"block nftprofilepictures\"> Block </label>";
                echo "<input type=\"radio\" id=\"mute nftprofilepictures\" name=\"nftprofilepictures\" value=\"mute_nftprofilepictures\">";
                echo "<label for=\"mute nftprofilepictures\"> Mute </label>";
                echo "<input type=\"radio\" id=\"noaction nftprofilepictures\" name=\"nftprofilepictures\" value=\"noaction_nftprofilepictures\" checked=\"checked\">";
                echo "<label for=\"noaction nftprofilepictures\"> Do nothing </label></div><br/><br/>";

                echo "<b>Users with cryptocurrencies (e.g. .eth or .sol) at the end of their display name:</b>";
                echo "<br/><br/><div class=\"formsection\">";
                echo "<input type=\"radio\" id=\"block cryptousernames\" name=\"cryptousernames\" value=\"block_cryptousernames\">";
                echo "<label for=\"block cryptousernames\"> Block </label>";
                echo "<input type=\"radio\" id=\"mute cryptousernames\" name=\"cryptousernames\" value=\"mute_cryptousernames\">";
                echo "<label for=\"mute cryptousernames\"> Mute </label>";
                echo "<input type=\"radio\" id=\"noaction cryptousernames\" name=\"cryptousernames\" value=\"noaction_cryptousernames\" checked=\"checked\">";
                echo "<label for=\"noaction cryptousernames\"> Do nothing </label></div><br/><br/>";

                echo "<b>Block followers who match your above settings?:</b>";
                echo "<br/><br/>"
                . "By default, this app blocks matching users who mention you in tweets or who appear on your home timeline. "
                . "If you enable this option, the app will block new and existing followers who also meet the criteria."
                . "<br/><br/>"
                . "<div class=\"formsection\">";
                echo "<input type=\"radio\" id=\"block nftfollowers\" name=\"nftfollowers\" value=\"block_nftfollowers\">";
                echo "<label for=\"block nftfollowers\"> Block </label>";
                echo "<input type=\"radio\" id=\"mute nftfollowers\" name=\"nftfollowers\" value=\"mute_nftfollowers\">";
                echo "<label for=\"mute nftfollowers\"> Mute </label>";
                echo "<input type=\"radio\" id=\"noaction nftfollowers\" name=\"nftfollowers\" value=\"noaction_nftfollowers\" checked=\"checked\">";
                echo "<label for=\"noaction nftfollowers\"> Do nothing </label></div><br/><br/>";

                echo "<b>Central database</b>";
                echo "<br/><br/>"
                . "When anyone blocks a user via one of the above auto-block or auto-mute options, this app keeps a record of that user and which filter they matched. "
                . "If you enable this option, you can block or mute some of the highest matched users in that database. It will only do this for central"
                . " database entries that match filters you have chosen to block or mute above."
                . "<br/><br/>"
                . "Note that due to processing constraints, the number of central DB entries the app will block for you is subject to change. It will "
                . "currently block or mute all users who meet the match criteria."
                . "</br><br/>"
                . "There are currently <b>$centralDBCount</b> crypto/NFT users in the central database who meet the match criteria.<br/><br/>"
                . "<div class=\"formsection\">";
                echo "<input type=\"radio\" id=\"block centraldatabase\" name=\"centraldatabase\" value=\"block_centraldatabase\">";
                echo "<label for=\"block centraldatabase\"> Block </label>";
                echo "<input type=\"radio\" id=\"mute centraldatabase\" name=\"centraldatabase\" value=\"mute_centraldatabase\">";
                echo "<label for=\"mute centraldatabase\"> Mute </label>";
                echo "<input type=\"radio\" id=\"noaction centraldatabase\" name=\"centraldatabase\" value=\"noaction_centraldatabase\" checked=\"checked\">";
                echo "<label for=\"noaction centraldatabase\"> Do nothing </label></div><br/><br/>";

                echo "<b>Following whitelist</b>";
                echo "<br/><br/>"
                . "By default, NFT Artist Blocker will not block anyone you follow, even if they match your filter criteria. "
                . "You can disable this option here if you like."
                . "<br/><br/>"
                . "<div class=\"formsection\">";
                echo "<input type=\"radio\" id=\"enable followerwhitelist\" name=\"followerwhitelist\" value=\"enable_followerwhitelist\" checked=\"checked\">";
                echo "<label for=\"enable followerwhitelist\"> Enabled </label>";
                echo "<input type=\"radio\" id=\"disable followerwhitelist\" name=\"followerwhitelist\" value=\"disable_followerwhitelist\">";
                echo "<label for=\"disable followerwhitelist\"> Disabled </label></div><br/><br/>";
                echo "<input type=\"submit\" value=\"Save Settings\">";
                echo "</form>";
                ?>
            </p>
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>