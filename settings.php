<?php

namespace Antsstyle\NFTArtistBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\Session;
use Antsstyle\NFTArtistBlocker\Core\Config;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;

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

$blockListNames = CoreDB::getBlockListNames();
$blockablePhrases = CoreDB::getBlockablePhrases();
$blockableURLs = CoreDB::getBlockableURLs();
$blockableURLsPage = Config::HOMEPAGE_URL . "blockableurls";
$blockablePhrasesPage = Config::HOMEPAGE_URL . "blockablephrases";
?>


<html>
    <script src="coreajax.js"></script>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <title>
        NFT Artist Blocker
    </title>
    <body onload="getUserInformation('<?php echo $_SESSION['usertwitterid']; ?>')">
        <div class="start">
            Twitter authentication successful. Choose the settings you want below and press the 'Save Settings' button at the bottom of the page
            (if you already have saved settings, they will be preselected).<br/>
            Note that it can take up to an hour for your settings to go into effect, due to Twitter rate limits.
        </div>
        <p>
            <?php
            if (!$blockListNames) {
                echo "Error: could not load block list names.<br/><br/>";
                exit();
            }
            echo "<h2>Lists</h2>";
            echo "If you choose to block or mute the NFT Artists list, it will automatically block new entries for you when the list is updated. "
            . "You won't need to sign in again unless you want to change your settings.<br/><br/>";
            echo "<form action=\"addblocks\" method=\"post\">";
            foreach ($blockListNames as $blockListName) {
                echo "<b>For all users on list \"NFT Artists\":</b><br/><br/>";
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
                echo "<label for=\"noaction $blockListName\"> Do nothing </label></div>";
            }
            echo "<h2>Auto-block and auto-mute options</h2>";
            echo "The options below will scan tweets that mention you and tweets which appear in your timeline, and block or mute"
            . " the user who posted that tweet depending on which option you select.<br/><br/>";
            echo "<b>Tweets containing certain phrases:</b> <a href=\"$blockablePhrasesPage\" target=\"_blank\">"
            . "(click here to see the list of phrases)</a>";
            echo "<br/><br/><div class=\"formsection\">";
            echo "<input type=\"radio\" id=\"block phrases\" name=\"phrases\" value=\"block_phrases\">";
            echo "<label for=\"block phrases\"> Block </label>";
            echo "<input type=\"radio\" id=\"mute phrases\" name=\"phrases\" value=\"mute_phrases\">";
            echo "<label for=\"mute phrases\"> Mute </label>";
            echo "<input type=\"radio\" id=\"noaction phrases\" name=\"phrases\" value=\"noaction_phrases\" checked=\"checked\">";
            echo "<label for=\"noaction phrases\"> Do nothing </label></div><br/><br/>";
            echo "<b>Tweets containing certain URLs, or users with those URLs in their profile:</b> "
            . "<a href=\"$blockableURLsPage\" target=\"_blank\">(click here to see the list of URLs)</a>";

            echo "<br/><br/><div class=\"formsection\">";
            echo "<input type=\"radio\" id=\"block urls\" name=\"urls\" value=\"block_urls\">";
            echo "<label for=\"block urls\"> Block </label>";
            echo "<input type=\"radio\" id=\"mute urls\" name=\"urls\" value=\"mute_urls\">";
            echo "<label for=\"mute urls\"> Mute </label>";
            echo "<input type=\"radio\" id=\"noaction urls\" name=\"urls\" value=\"noaction_urls\" checked=\"checked\">";
            echo "<label for=\"noaction urls\"> Do nothing </label></div><br/><br/>";

            echo "<b>Users with 'verified' NFT profile pictures (currently not supported by Twitter API: you can mark it here and "
            . "they'll be actioned later when the API is updated.):</b>";
            echo "<br/><br/><div class=\"formsection\">";
            echo "<input type=\"radio\" id=\"block nftprofilepictures\" name=\"nftprofilepictures\" value=\"block_nftprofilepictures\">";
            echo "<label for=\"block nftprofilepictures\"> Block </label>";
            echo "<input type=\"radio\" id=\"mute nftprofilepictures\" name=\"nftprofilepictures\" value=\"mute_nftprofilepictures\">";
            echo "<label for=\"mute nftprofilepictures\"> Mute </label>";
            echo "<input type=\"radio\" id=\"noaction nftprofilepictures\" name=\"nftprofilepictures\" value=\"noaction_nftprofilepictures\" checked=\"checked\">";
            echo "<label for=\"noaction nftprofilepictures\"> Do nothing </label></div><br/><br/>";

            echo "<b>Users with .eth in their display name:</b>";
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

            echo "<b>Block all users in NFT Artist Blocker's database?</b>";
            echo "<br/><br/>"
            . "When anyone blocks a user via one of the above auto-block or auto-mute options, this app keeps a record of it. "
            . "If you enable this option, you can block all users in that database."
            . "<br/><br/>"
            . "<div class=\"formsection\">";
            echo "<input type=\"radio\" id=\"block centraldatabase\" name=\"centraldatabase\" value=\"block_centraldatabase\">";
            echo "<label for=\"block centraldatabase\"> Block </label>";
            echo "<input type=\"radio\" id=\"mute centraldatabase\" name=\"centraldatabase\" value=\"mute_centraldatabase\">";
            echo "<label for=\"mute centraldatabase\"> Mute </label>";
            echo "<input type=\"radio\" id=\"noaction centraldatabase\" name=\"centraldatabase\" value=\"noaction_centraldatabase\" checked=\"checked\">";
            echo "<label for=\"noaction centraldatabase\"> Do nothing </label></div><br/><br/>";

            echo "<b>Follower whitelist</b>";
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
    </body>
</html>