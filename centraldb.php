<?php

namespace Antsstyle\NFTCryptoBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\CachedVariables;
use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\Session;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

Session::checkSession();

$pageNum = filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT);
if (is_null($pageNum) || $pageNum === false) {
    $pageNum = 1;
}

$centralDBEntriesCount = CoreDB::getCentralDBCount();
$pageCount = ceil($centralDBEntriesCount / 100);
if ($pageNum > $pageCount) {
    $pageNum = $pageCount;
}
if ($pageNum < 1) {
    $pageNum = 1;
}

$nextPage = $pageNum + 1;
$prevPage = $pageNum - 1;

$centralDBEntriesPage = CoreDB::getSortedCentralDBEntries($pageNum);
$minFollowerCount = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_FOLLOWERCOUNT);
$minMatchCount = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_MATCHCOUNT);
?>

<html>
    <script src="src/ajax/tables.js"></script>
    <script src="src/ajax/dbsearch.js"></script>
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
    <body onload="storeSearchResults()">
        <div class="main">
            <script src=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.js"; ?>></script>
            <h2>Central Database Entries</h2>
            <p>
                <?php
                echo "This is the central database of detected NFT/crypto users, sorted by follower count (note that this page will only show detected users with "
                . "a minimum of $minFollowerCount followers, who matched filters from users at least $minMatchCount times). ";
                ?>

            </p>
            <p>
                You can search the entire database (not just this page) by twitter handle using the search box below.
            </p>
            This is page <?php echo "$pageNum of $pageCount"; ?>.
            <?php
            if ($prevPage >= 1) {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "centraldb?page=" . $prevPage . "';\">"
                . "Previous page"
                . "</button>";
            } else {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "centraldb?page=" . $prevPage . "';\" disabled>"
                . "Previous page"
                . "</button>";
            }
            echo "&nbsp;";
            if ($nextPage <= $pageCount) {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "centraldb?page=" . $nextPage . "';\">"
                . "Next page"
                . "</button>";
            } else {
                echo "<button onclick=\"window.location.href = '" . Config::HOMEPAGE_URL . "centraldb?page=" . $nextPage . "';\" disabled>"
                . "Next page"
                . "</button>";
            }
            ?>
            <br/><br/>

            <input type="text" id="centraldbsearch" placeholder="Search by twitter handle...">
            <button type="button" id="searchbutton" onclick="dbSearch('centraldbsearch', 'central')">Search</button>
            <button type="button" id="resetbutton" onclick="resetSearchTable()">Reset</button>
            <br/><br/>
            <div id="searchresultstextdiv">

            </div>
            Click a column header to sort by that column.
            <br/><br/>
            <div id="searchresultsdiv">
                <table id="maintable" class="dblisttable">
                    <tr>
                        <th onclick="sortTable(0, 'maintable')">Twitter Handle</th>
                        <th onclick="sortTable(1, 'maintable')">Matched filter type</th>
                        <th onclick="sortTable(2, 'maintable')">Matched filter content</th>
                        <th onclick="sortTable(3, 'maintable')">Date added</th>
                        <th onclick="sortTable(4, 'maintable')">Follower #</th>
                        <th onclick="sortTable(5, 'maintable')">Match count</th>
                    </tr>
                    <?php
                    if (!$centralDBEntriesPage) {
                        echo "Error: could not load central database entries.";
                    } else {
                        foreach ($centralDBEntriesPage as $centralDBEntry) {
                            $dateAdded = substr($centralDBEntry['dateadded'], 0, 10);
                            echo "<tr>";
                            echo "<td>@" . $centralDBEntry['twitterhandle'] . "</td>";
                            if ($centralDBEntry['matchedfiltertype'] === "nftprofilepictures") {
                                $content = $centralDBEntry['matchedfiltercontent'];
                                $href = "<a href=\"$content\" target=\"_blank\">Link to image</a>";
                                echo "<td>" . $centralDBEntry['matchedfiltertype'] . "</td>";
                                echo "<td>" . $href . "</td>";
                            } else {
                                echo "<td>" . $centralDBEntry['matchedfiltertype'] . "</td>";
                                echo "<td>" . $centralDBEntry['matchedfiltercontent'] . "</td>";
                            }
                            echo "<td>" . $dateAdded . "</td>";
                            echo "<td>" . $centralDBEntry['followercount'] . "</td>";
                            echo "<td>" . $centralDBEntry['matchcount'] . "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </table>
            </div>
            <div id="tablecachediv" class="hiddendiv" hidden>
                "We do beg your pardon, but we are in your garden"
            </div>
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>
