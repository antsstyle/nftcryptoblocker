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

$pageNum = filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT);
if (is_null($pageNum) || $pageNum === false) {
    $pageNum = 1;
}

$userBlockRecords = CoreDB::getUserBlockRecords($_SESSION['usertwitterid'], $pageNum);
$userBlockRecordsCount = CoreDB::getUserBlockRecordsCount($_SESSION['usertwitterid']);

$pageCount = ceil($userBlockRecordsCount / 100);
if ($pageNum > $pageCount) {
    $pageNum = $pageCount;
}
if ($pageNum < 1) {
    $pageNum = 1;
}

$nextPage = $pageNum + 1;
$prevPage = $pageNum - 1;

$blockListInfo = CoreDB::getBlockLists();
$blockListMap = [];
if (!is_null($blockListInfo)) {
    foreach ($blockListInfo as $blockListInfoEntry) {
        $blockListMap[$blockListInfoEntry['id']] = $blockListInfoEntry;
    }
} else {
    $blockListMap = null;
}
?>

<html>
    <script src="src/ajax/tables.js"></script>
    <script src="src/ajax/dbsearch.js"></script>
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
    <body onload="storeSearchResults()">
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
                    echo "Queued operations are done in batches of 45, once per 15 minutes, per user. As such, if you have many queued operations, "
                    . "expect them to take a while; check back again in a few hours to see how it is progressing.<br/><br/>";
                }
                // echo "<b>There is currently a very large backlog of queued actions to process; as such, actions will take longer to process than the above.</b><br/><br/>";
                echo "Blocked accounts: $blockCount &nbsp;&nbsp; (Currently queued for blocking: $queuedBlockCount)</br>";
                echo "Muted accounts: $muteCount &nbsp;&nbsp;&nbsp;&nbsp; (Currently queued for muting: $queuedMuteCount)</br>";
            }
            ?>
            <br/><br/><br/>
            <h2>Your block/mute records</h2>
            <p>
                This is the list of users the app has blocked or muted for you in the last 30 days, sorted by date processed. 
                Click a column header to sort this page of results.
            </p>
            <p>
                You can search the whole list (not just this page) by twitter handle using the search box below.
            </p>
            This is page <?php echo "$pageNum of $pageCount"; ?>.
            <?php
            if ($prevPage >= 1) {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "statistics?page=" . $prevPage . "';\">"
                . "Previous page"
                . "</button>";
            } else {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "statistics?page=" . $prevPage . "';\" disabled>"
                . "Previous page"
                . "</button>";
            }
            echo "&nbsp;";
            if ($nextPage <= $pageCount) {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "statistics?page=" . $nextPage . "';\">"
                . "Next page"
                . "</button>";
            } else {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "statistics?page=" . $nextPage . "';\" disabled>"
                . "Next page"
                . "</button>";
            }
            ?>
            <br/><br/>
            <input type="text" id="dbsearch" placeholder="Search by twitter handle...">
            <button type="button" id="searchbutton" onclick="dbSearch('dbsearch', 'user')">Search</button>
            <button type="button" id="resetbutton" onclick="resetSearchTable()">Reset</button>
            <br/><br/>
            <div id="searchresultstextdiv">

            </div>
            <div id="searchresultsdiv">
                <table id="maintable" class="dblisttable">
                    <tr>
                        <th onclick="sortTable(0, 'maintable')">Twitter Handle</th>
                        <th onclick="sortTable(1, 'maintable')">Added from</th>
                        <th onclick="sortTable(2, 'maintable')">Matched filter type</th>
                        <th onclick="sortTable(3, 'maintable')">Matched filter content</th>
                        <th onclick="sortTable(4, 'maintable')">Date processed</th>
                    </tr>
                    <?php
                    if (is_null($userBlockRecords)) {
                        echo "An error occurred retrieving your block and mute records, check back later.";
                    } else if (count($userBlockRecords) === 0) {
                        echo "You do not have any blocked or muted users from the app yet.";
                    } else {
                        foreach ($userBlockRecords as $userBlockRecord) {
                            $dateProcessed = substr($userBlockRecord['dateprocessed'], 0, 10);
                            $addedFrom = $userBlockRecord['addedfrom'];
                            $matchedFilterType = $userBlockRecord['matchedfiltertype'];
                            $matchedFilterContent = $userBlockRecord['matchedfiltercontent'];
                            if (is_null($matchedFilterType)) {
                                $matchedFilterType = "N/A";
                            }
                            if (is_null($matchedFilterContent)) {
                                $matchedFilterContent = "N/A";
                            }
                            if ($addedFrom == "blocklist" || $addedFrom == "Unknown") {
                                if (is_null($blockListMap)) {
                                    $addedFrom = "Blocklist (DB error)";
                                } else {
                                    $blockListEntry = $blockListMap[$userBlockRecord['blocklistid']];
                                    $addedFrom = "Blocklist (" . $blockListEntry['shortname'] . ")";
                                }
                            } else if ($addedFrom == "centraldb") {
                                $addedFrom = "Central blocklist";
                            } else if ($addedFrom == "statuses/home_timeline") {
                                $addedFrom = "Home timeline";
                            } else if ($addedFrom == "users/:id/followers") {
                                $addedFrom = "Followers";
                            } else if ($addedFrom == "users/:id/mentions") {
                                $addedFrom = "Mentions timeline";
                            }
                            echo "<tr>";
                            echo "<td>@" . $userBlockRecord['username'] . "</td>";
                            echo "<td>" . $addedFrom . "</td>";
                            if ($userBlockRecord['matchedfiltertype'] === "nftprofilepictures") {
                                $content = $userBlockRecord['matchedfiltercontent'];
                                $href = "<a href=\"$content\" target=\"_blank\">Link to image</a>";
                                echo "<td>" . $userBlockRecord['matchedfiltertype'] . "</td>";
                                echo "<td>" . $href . "</td>";
                            } else {
                                echo "<td>" . $matchedFilterType . "</td>";
                                echo "<td>" . $matchedFilterContent . "</td>";
                            }
                            echo "<td>" . $dateProcessed . "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </table>
            </div>
            <div id="tablecachediv" class="hiddendiv" hidden>
                "Say if a duck went 'quack', but not at the angle if it went 'FOX!'"
            </div>
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>
