<?php

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\Session;

$searchString = filter_input(INPUT_POST, 'searchstring', FILTER_SANITIZE_STRING);
if (!preg_match("/^@?[A-Za-z0-9_]{1,15}$/", $searchString)) {
    echo "Invalid username";
    return;
}

$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
if ($type === "central") {
    
} else if ($type === "user") {
    Session::checkSession();

    if (!$_SESSION['oauth_token']) {
        echo "Invalid session";
        exit();
    }
    if (!$_SESSION['usertwitterid']) {
        echo "Invalid session";
        exit();
    }
} else {
    echo "Invalid type";
    return;
}

if ($type === "central") {
    $results = CoreDB::searchCentralDB($searchString);
    if ($results === false) {
        echo "";
    } else {
        $tableString = "<table id=\"maintable\" class=\"dblisttable\"><tr>
                <th onclick=\"sortTable(0, 'maintable')\">Twitter Handle</th>
                <th onclick=\"sortTable(1, 'maintable')\">Matched filter type</th>
                <th onclick=\"sortTable(2, 'maintable')\">Matched filter content</th>
                <th onclick=\"sortTable(3, 'maintable')\">Date added</th>
                <th onclick=\"sortTable(4, 'maintable')\">Follower #</th>
            </tr>";
        $resultCount = count($results);
        foreach ($results as $resultRow) {
            $dateAdded = substr($resultRow['dateadded'], 0, 10);
            $tableString .= "<tr>";
            $tableString .= "<td>@" . $resultRow['twitterhandle'] . "</td>";
            $tableString .= "<td>" . $resultRow['matchedfiltertype'] . "</td>";
            $tableString .= "<td>" . $resultRow['matchedfiltercontent'] . "</td>";
            $tableString .= "<td>" . $dateAdded . "</td>";
            $tableString .= "<td>" . $resultRow['followercount'] . "</td>";
            $tableString .= "</tr>";
        }
        $tableString .= "</table>";
        $returnArray['resultcount'] = $resultCount;
        $returnArray['tablestring'] = $tableString;
        echo json_encode($returnArray);
    }
} else if ($type === "user") {
    $results = CoreDB::searchUserBlockRecords($_SESSION['usertwitterid'], $searchString);
    if ($results === false) {
        echo "";
    } else {
        $tableString = "<table id=\"maintable\" class=\"dblisttable\"><tr>
                <th onclick=\"sortTable(0, 'maintable')\">Twitter Handle</th>
                <th onclick=\"sortTable(1, 'maintable')\">Matched filter type</th>
                <th onclick=\"sortTable(2, 'maintable')\">Matched filter content</th>
                <th onclick=\"sortTable(3, 'maintable')\">Date added</th>
                <th onclick=\"sortTable(4, 'maintable')\">Follower #</th>
            </tr>";
        $resultCount = count($results);
        foreach ($results as $resultRow) {
            $dateAdded = substr($resultRow['dateadded'], 0, 10);
            $tableString .= "<tr>";
            $tableString .= "<td>@" . $resultRow['twitterhandle'] . "</td>";
            $tableString .= "<td>" . $resultRow['matchedfiltertype'] . "</td>";
            $tableString .= "<td>" . $resultRow['matchedfiltercontent'] . "</td>";
            $tableString .= "<td>" . $dateAdded . "</td>";
            $tableString .= "<td>" . $resultRow['followercount'] . "</td>";
            $tableString .= "</tr>";
        }
        $tableString .= "</table>";
        $returnArray['resultcount'] = $resultCount;
        $returnArray['tablestring'] = $tableString;
        echo json_encode($returnArray);
    }
}