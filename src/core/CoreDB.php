<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

use Antsstyle\NFTCryptoBlocker\Core\CachedVariables;
use Antsstyle\NFTCryptoBlocker\Credentials\DB;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

class CoreDB {

    const options = [
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ];

    public static $databaseConnection;
    public static $logger;

    public static function getConfiguration() {
        $selectQuery = "SELECT * FROM centralconfiguration";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->critical("Could not retrieve central configuration, returning.");
            return false;
        }
        $configArray = [];
        while ($row = $selectStmt->fetch()) {
            $configArray[$row['name']] = $row['value'];
        }
        return $configArray;
    }

    public static function assignEntryPNums() {
        $numThreads = CoreDB::getCachedVariable(CachedVariables::NUM_PROCESSENTRIES_THREADS);
        if (is_null($numThreads)) {
            return;
        }
        if ($numThreads === false) {
            CoreDB::$logger->critical("Could not retrieve number of ProcessEntries threads, returning.");
            return;
        }

        $selectQuery = "SELECT COUNT(*) AS pnumcount FROM entriestoprocess WHERE pnum IS NULL";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->critical("Could not retrieve entries to process counts, returning.");
            return false;
        }
        $nullCount = $selectStmt->fetchColumn();
        if ($nullCount === false || $nullCount === 0) {
            return;
        } else {
            error_log("Null pnum count: $nullCount");
        }
        $perThread = intval($nullCount / $numThreads);
        CoreDB::$logger->info("Per thread count: $perThread    Null count: $nullCount     Num threads: $numThreads");
        for ($i = 1; $i <= $numThreads; $i++) {
            $updateQuery = "UPDATE entriestoprocess SET pnum=? WHERE pnum IS NULL LIMIT $perThread";
            CoreDB::$logger->info("Update query: $updateQuery");
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$i]);
        }
    }

    public static function calculateCachedVariables() {
        $cachedVar = CachedVariables::CACHED_TOTAL_COUNTS_LAST_RECHECK_DATE;
        $row = CoreDB::getCachedVariable(CachedVariables::CACHED_TOTAL_COUNTS_LAST_RECHECK_DATE);
        if (is_null($row)) {
            CoreDB::$logger->error("Could not retrieve cached variable $cachedVar, cannot recalculate.");
            return;
        } else if ($row === false) {
            $calculate = true;
            $nextdate = date("Y-m-d H:i:s", strtotime("+1 hour"));
            CoreDB::updateCachedVariable(CachedVariables::CACHED_TOTAL_COUNTS_LAST_RECHECK_DATE, $nextdate);
        }
        $calculate = true;
        $now = time();

        if ($row !== false) {
            $nextdate = $row;
            if (!is_null($nextdate)) {
                $nextdatetimestamp = strtotime($nextdate);
                if ($nextdatetimestamp > $now) {
                    $calculate = false;
                }
            }
        }
        if ($calculate) {
            $selectQuery = "SELECT operation,COUNT(operation) AS opcount FROM userblockrecords GROUP BY operation";
            $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
            $success = $selectStmt->execute();
            if (!$success) {
                CoreDB::$logger->error("Could not retrieve total block counts!");
            } else {
                while ($dbRow = $selectStmt->fetch()) {
                    $nextdate = date("Y-m-d H:i:s", strtotime("+1 hour"));
                    $operation = $dbRow['operation'];
                    $opcount = $dbRow['opcount'];
                    if ($operation === "Block") {
                        CoreDB::updateCachedVariable(CachedVariables::CACHED_TOTAL_BLOCKS_COUNT, $opcount);
                    } else if ($operation === "Mute") {
                        CoreDB::updateCachedVariable(CachedVariables::CACHED_TOTAL_MUTES_COUNT, $opcount);
                    }
                }
            }
            CoreDB::updateCachedVariable(CachedVariables::CACHED_TOTAL_COUNTS_LAST_RECHECK_DATE, $nextdate);
        }
    }

    public static function getCachedVariable($name) {
        $selectQuery = "SELECT * FROM cachedvariables WHERE name=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$name]);
        if (!$success) {
            CoreDB::$logger->critical("Could not retrieve cached variable with name: $name, returning.");
            return null;
        }
        $row = $selectStmt->fetch();
        return $row['value'];
    }

    public static function updateCachedVariable($name, $value) {
        $row = CoreDB::getCachedVariable($name);
        if ($row === false) {
            $insertQuery = "INSERT INTO cachedvariables (name,value) VALUES (?,?)";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $success = $insertStmt->execute([$name, $value]);
            if (!$success) {
                CoreDB::$logger->error("Could not insert cached variable with name: $name, value: $value");
            }
        } else {
            $updateQuery = "UPDATE cachedvariables SET value=? WHERE name=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $success = $updateStmt->execute([$value, $name]);
            if (!$success) {
                CoreDB::$logger->error("Could not update cached variable with name: $name, value: $value");
            }
        }
        return $success;
    }

    public static function searchCentralDB($searchString) {
        if (!is_string($searchString) || strlen($searchString) == 0) {
            CoreDB::$logger->error("Invalid search string parameter given, returning.");
            return false;
        }
        if (strpos($searchString, "@") === 0) {
            $searchString = substr($searchString, 1);
            if (strlen($searchString) == 0) {
                CoreDB::$logger->error("Invalid search string parameter given, returning.");
                return false;
            }
        }
        $searchString = "%" . $searchString . "%";
        $selectQuery = "SELECT * FROM centralisedblocklist WHERE twitterhandle IS NOT NULL AND twitterhandle LIKE ? ORDER BY followercount DESC";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$searchString]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve central database entries for search request, returning.");
            return false;
        }
        $centralDBEntries = [];
        while ($row = $selectStmt->fetch()) {
            $centralDBEntries[] = $row;
        }
        return $centralDBEntries;
    }

    public static function getSortedCentralDBEntries($pageNumber = 1) {
        if (!is_numeric($pageNumber) || $pageNumber <= 0) {
            CoreDB::$logger->error("Invalid page number parameter given, returning.");
            return false;
        }
        $offSet = ($pageNumber - 1) * 100;
        $selectQuery = "SELECT * FROM centralisedblocklist WHERE followercount IS NOT NULL ORDER BY followercount DESC LIMIT 100 OFFSET $offSet";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve central database entries, returning.");
            return false;
        }
        $centralDBEntries = [];
        while ($row = $selectStmt->fetch()) {
            $centralDBEntries[] = $row;
        }
        return $centralDBEntries;
    }

    public static function getUserStats($userTwitterID) {
        $selectQuery = "SELECT operation,COUNT(operation) AS opcount FROM userblockrecords WHERE subjectusertwitterid=? GROUP BY operation";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user stats, returning.");
            return false;
        }
        while ($row = $selectStmt->fetch()) {
            $userStats[$row['operation']] = $row['opcount'];
        }
        $selectQuery = "SELECT operation,COUNT(operation) AS opcount FROM entriestoprocess WHERE subjectusertwitterid=? GROUP BY operation";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user stats, returning.");
            return false;
        }
        while ($row = $selectStmt->fetch()) {
            $userStats["queue" . $row['operation']] = $row['opcount'];
        }
        return $userStats;
    }

    public static function getFollowerCacheForUser($userTwitterID) {
        $selectQuery = "SELECT recentfollowerid FROM userfollowerscache WHERE usertwitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user follower cache, returning.");
            return false;
        }
        $followerIDs = [];
        while ($followerID = $selectStmt->fetchColumn()) {
            $followerIDs[] = $followerID;
        }
        return $followerIDs;
    }

    public static function updateFollowerCacheForUser($userTwitterID, $followerIDs) {
        if (!is_array($followerIDs)) {
            CoreDB::$logger->error("Follower IDs was not an array, cannot update follower cache.");
            return;
        }
        if (count($followerIDs) == 0) {
            return;
        }
        $deleteQuery = "DELETE FROM userfollowerscache WHERE usertwitterid=?";
        $deleteStmt = CoreDB::$databaseConnection->prepare($deleteQuery);
        $success = $deleteStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not delete user follower cache, returning.");
            return;
        }

        $selectQuery = "SELECT MAX(id) AS maxid FROM userfollowerscache";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not get max id from user followers cache, returning.");
            return;
        }
        $maxID = $selectStmt->fetchColumn();
        if (is_null($maxID)) {
            $maxID = 0;
        } else {
            $maxID++;
        }

        $insertQuery = "INSERT INTO userfollowerscache (id,usertwitterid,recentfollowerid) VALUES ";
        foreach ($followerIDs as $followerID) {
            $insertQuery .= "(?,?,?),";
            $insertParams[] = $maxID;
            $insertParams[] = $userTwitterID;
            $insertParams[] = $followerID;
            $maxID++;
        }
        $insertQuery = substr($insertQuery, 0, -1);
        $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
        $success = $insertStmt->execute($insertParams);
        if (!$success) {
            CoreDB::$logger->error("Could not insert user follower cache! User twitter ID: $userTwitterID");
            return;
        }
    }

    public static function checkCentralisedBlockListForUsers($idList = null) {
        $selectQuery = "SELECT MAX(id) AS maxid FROM centralisedblocklist";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not get max central database ID, returning.");
            return;
        }
        $maxID = $selectStmt->fetchColumn();
        if ($maxID === false) {
            CoreDB::$logger->error("Couldn't find max centralised blocklist ID, returning.");
            return;
        }

        $selectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid "
                . "WHERE (highestactionedcentraldbid IS NULL "
                . "OR highestactionedcentraldbid < ?) AND twitterid IN (SELECT "
                . "usertwitterid FROM userautomationsettings WHERE centraldatabaseoperation=? OR centraldatabaseoperation=?)"
                . " AND locked=?";
        $selectParams = [$maxID, "Block", "Mute", "N"];
        if (!is_null($idList) && is_array($idList) && count($idList) > 0) {
            $selectQuery .= " AND usertwitterid IN (";
            foreach ($idList as $userTwitterID) {
                $selectQuery .= "?,";
                $selectParams[] = $userTwitterID;
            }
            $selectQuery = substr($selectQuery, 0, -1);
            $selectQuery .= ")";
        } else if ($idList !== null) {
            CoreDB::$logger->error("Invalid arguments supplied to checkCentralisedBlockListForUsers, returning.");
            return;
        }
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute($selectParams);
        if (!$success) {
            CoreDB::$logger->error("Could not get users to block centralised blocklist entries for, returning.");
            return;
        }

        while ($userRow = $selectStmt->fetch()) {
            CoreDB::checkCentralisedBlockListForUserRow($userRow, $maxID);
        }
    }

    public static function checkCentralisedBlockListForUserRow($userRow, $maxCentralID) {
        $userTwitterID = $userRow['usertwitterid'];
        $highestActionedCentralDBID = $userRow['highestactionedcentraldbid'];
        $cryptoUsernamesOperation = $userRow['cryptousernamesoperation'];
        $matchingPhraseOperation = $userRow['matchingphraseoperation'];
        $nftProfilePictureOperation = $userRow['nftprofilepictureoperation'];
        $urlsOperation = $userRow['urlsoperation'];
        $nftFollowersOperation = $userRow['nftfollowersoperation'];
        $blockArray = [];
        $muteArray = [];
        $opsArray = array("matchingphrase" => $matchingPhraseOperation, "cryptousernames" => $cryptoUsernamesOperation,
            "urls" => $urlsOperation, "nftfollowers" => $nftFollowersOperation,
            "nftprofilepicture" => $nftProfilePictureOperation);
        foreach ($opsArray as $key => $value) {
            if ($value === "Mute") {
                $muteArray[] = $key;
            } else if ($value === "Block") {
                $blockArray[] = $key;
            }
        }
        $paramsArray = [];

        if (count($muteArray) > 0) {
            $insertQuery = "SET @usertwitterid = $userTwitterID; "
                    . "INSERT INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,addedfrom) "
                    . "SELECT @usertwitterid, blockableusertwitterid, 'Mute', 'centraldb' FROM centralisedblocklist WHERE "
                    . "blockableusertwitterid NOT IN (SELECT objectusertwitterid FROM "
                    . "userinitialblockrecords WHERE subjectusertwitterid=@usertwitterid AND operation='Mute') "
                    . "AND markedfordeletion=\"N\" ";
            if (!is_null($highestActionedCentralDBID)) {
                $insertQuery .= "AND id > ? ";
                $paramsArray[] = $highestActionedCentralDBID;
            }
            $insertQuery .= "AND matchedfiltertype IN (";
            foreach ($muteArray as $muteEntry) {
                $insertQuery .= "'" . $muteEntry . "',";
            }
            $insertQuery = substr($insertQuery, 0, -1);
            $insertQuery .= ") ";
            $insertQuery .= "ON DUPLICATE KEY UPDATE operation='Mute'";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $success = $insertStmt->execute($paramsArray);
            $insertStmt->closeCursor();
        }
        $paramsArray = [];
        if (count($blockArray) > 0) {
            $insertQuery = "SET @usertwitterid = $userTwitterID; "
                    . "INSERT INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,addedfrom) "
                    . "SELECT @usertwitterid, blockableusertwitterid, 'Block', 'centraldb' FROM centralisedblocklist WHERE "
                    . "blockableusertwitterid NOT IN (SELECT objectusertwitterid FROM "
                    . "userinitialblockrecords WHERE subjectusertwitterid=@usertwitterid AND operation='Block') ";
            if (!is_null($highestActionedCentralDBID)) {
                $insertQuery .= "AND id > ? ";
                $paramsArray[] = $highestActionedCentralDBID;
            }
            $insertQuery .= "AND matchedfiltertype IN (";
            foreach ($blockArray as $blockEntry) {
                $insertQuery .= "'" . $blockEntry . "',";
            }
            $insertQuery = substr($insertQuery, 0, -1);
            $insertQuery .= ") ";
            $insertQuery .= "ON DUPLICATE KEY UPDATE operation='Block'";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $success = $insertStmt->execute($paramsArray);
            $insertStmt->closeCursor();
        }

        $updateQuery = "UPDATE users SET highestactionedcentraldbid=? WHERE twitterid=?";
        $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
        $updateStmt->execute([$maxCentralID, $userTwitterID]);
    }

    public static function getMaxCentralBlockListID() {
        $selectQuery = "SELECT MAX(id) FROM centralisedblocklist";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve last blocklist update check date, returning.");
            return -1;
        }
        return $selectStmt->fetchColumn();
    }

    public static function checkBlockListUpdates() {
        CoreDB::$logger->info("checking blocklist updates");
        $selectQuery = "SELECT value FROM centralconfiguration WHERE name=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([CachedVariables::LAST_BLOCKLIST_UPDATE_CHECK]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve last blocklist update check date, returning.");
            return;
        }
        $dateString = $selectStmt->fetchColumn();
        $selectQuery = "SELECT MAX(dateadded) FROM blocklistentries";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve last blocklist update check date, returning.");
            return;
        }
        $maxDateAdded = $selectStmt->fetchColumn();
        if (!$maxDateAdded) {
            CoreDB::$logger->error("No blocklist entries exist, no need to check further - exiting update.");
            return;
        }
        if (!$dateString) {
            CoreDB::$logger->error("No config item for last blocklist update check exists, inserting new.");
            $insertQuery = "INSERT INTO centralconfiguration (name, value) VALUES (?,?)";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $success = $insertStmt->execute([CachedVariables::LAST_BLOCKLIST_UPDATE_CHECK, $maxDateAdded]);
            if (!$success) {
                CoreDB::$logger->error("Failed to insert last blocklist update check config item!");
                return;
            }
            return;
        }
        $dateTime = strtotime($dateString);
        $maxDateTime = strtotime($maxDateAdded);
        if ($maxDateTime > $dateTime) {
            // New entries since last check date; update blocklists for users
            $selectQuery = "SELECT * FROM blocklistentries WHERE dateadded > ?";
            $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
            $success = $selectStmt->execute([$dateString]);
            if (!$success) {
                CoreDB::$logger->error("Could not retrieve last blocklist update check date, returning.");
                return;
            }
            CoreDB::$databaseConnection->beginTransaction();
            while ($row = $selectStmt->fetch()) {
                $userSelectQuery = "SELECT twitterid FROM users WHERE twitterid IN (SELECT usertwitterid FROM userblocklistrecords "
                        . "WHERE blocklistid=? AND lastoperation=?) AND locked=?";
                $userBlockSelectStmt = CoreDB::$databaseConnection->prepare($userSelectQuery);
                $success = $userBlockSelectStmt->execute([$row['blocklistid'], "Block", "N"]);
                if (!$success) {
                    CoreDB::$logger->error("Failed to get list of users to block new blocklist entries for, returning.");
                    return;
                }

                $insertBlockQuery = "INSERT IGNORE INTO entriestoprocess (blocklistid,subjectusertwitterid,objectusertwitterid,operation) "
                        . "VALUES (?,?,?,?)";
                while ($userBlockTwitterID = $userBlockSelectStmt->fetchColumn()) {
                    $insertBlockStmt = CoreDB::$databaseConnection->prepare($insertBlockQuery);
                    $insertBlockStmt->execute([$row['blocklistid'], $userBlockTwitterID, $row['blockusertwitterid'], "Block"]);
                }

                $userMuteSelectStmt = CoreDB::$databaseConnection->prepare($userSelectQuery);
                $success = $userMuteSelectStmt->execute([$row['blocklistid'], "Mute", "N"]);
                if (!$success) {
                    CoreDB::$logger->error("Failed to get list of users to mute new blocklist entries for, returning.");
                    return;
                }

                while ($userMuteTwitterID = $userMuteSelectStmt->fetchColumn()) {
                    $insertMuteStmt = CoreDB::$databaseConnection->prepare($insertBlockQuery);
                    $insertMuteStmt->execute([$row['blocklistid'], $userBlockTwitterID, $row['blockusertwitterid'], "Mute"]);
                }
            }
            $updateQuery = "UPDATE centralconfiguration SET value=? WHERE name=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$maxDateAdded, CachedVariables::LAST_BLOCKLIST_UPDATE_CHECK]);
            CoreDB::$databaseConnection->commit();
        }
    }

    public static function updateUserAutomationSettings($userTwitterID, $phraseSettings, $urlSettings, $nftProfilePicturesSettings,
            $cryptoUserNamesSettings, $NFTFollowersSettings, $centralDatabaseSettings, $followerWhitelistSettings) {
        if ($phraseSettings !== "noaction_phrases" && $phraseSettings !== "block_phrases" && $phraseSettings !== "mute_phrases") {
            return "input error";
        }
        if ($urlSettings !== "noaction_urls" && $urlSettings !== "block_urls" && $urlSettings !== "mute_urls") {
            return "input error";
        }
        if ($nftProfilePicturesSettings !== "noaction_nftprofilepictures" && $nftProfilePicturesSettings !== "block_nftprofilepictures" &&
                $nftProfilePicturesSettings !== "mute_nftprofilepictures") {
            return "input error";
        }
        if ($cryptoUserNamesSettings !== "noaction_cryptousernames" && $cryptoUserNamesSettings !== "block_cryptousernames" &&
                $cryptoUserNamesSettings !== "mute_cryptousernames") {
            return "input error";
        }
        if ($centralDatabaseSettings !== "noaction_centraldatabase" && $centralDatabaseSettings !== "block_centraldatabase" &&
                $centralDatabaseSettings !== "mute_centraldatabase") {
            return "input error";
        }
        if ($NFTFollowersSettings !== "noaction_nftfollowers" && $NFTFollowersSettings !== "block_nftfollowers" &&
                $NFTFollowersSettings !== "mute_nftfollowers") {
            return "input error";
        }
        if ($followerWhitelistSettings !== "enable_followerwhitelist" && $followerWhitelistSettings !== "disable_followerwhitelist") {
            return "input error";
        }
        $phraseArray = explode("_", $phraseSettings);
        $phraseString = ucfirst($phraseArray[0]);
        $urlArray = explode("_", $urlSettings);
        $urlString = ucfirst($urlArray[0]);
        $nftProfilePicturesArray = explode("_", $nftProfilePicturesSettings);
        $nftProfilePicturesString = ucfirst($nftProfilePicturesArray[0]);
        $cryptoUserNamesArray = explode("_", $cryptoUserNamesSettings);
        $cryptoUserNamesString = ucfirst($cryptoUserNamesArray[0]);
        $NFTFollowersArray = explode("_", $NFTFollowersSettings);
        $NFTFollowersString = ucfirst($NFTFollowersArray[0]);
        $centralDatabaseArray = explode("_", $centralDatabaseSettings);
        $centralDatabaseString = ucfirst($centralDatabaseArray[0]);
        $followerWhitelistArray = explode("_", $followerWhitelistSettings);
        if ($followerWhitelistArray[0] == "disable") {
            $followerWhitelistString = "N";
        } else {
            $followerWhitelistString = "Y";
        }

        $selectQuery = "SELECT usertwitterid FROM userautomationsettings WHERE usertwitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not determine existence of automation settings for user ID $userTwitterID");
            return $success;
        }
        $returnedID = $selectStmt->fetchColumn();
        $addCentralDBEntries = false;
        if ($returnedID === false) {
            $addCentralDBEntries = true;
        }

        $insertQuery = "INSERT INTO userautomationsettings (usertwitterid,matchingphraseoperation,nftprofilepictureoperation,"
                . "urlsoperation,cryptousernamesoperation,nftfollowersoperation,centraldatabaseoperation,whitelistfollowings) VALUES (?,?,?,?,?,?,?,?) "
                . "ON DUPLICATE KEY UPDATE matchingphraseoperation=?, "
                . "nftprofilepictureoperation=?, urlsoperation=?, cryptousernamesoperation=?, "
                . "nftfollowersoperation=?, centraldatabaseoperation=?, whitelistfollowings=?";
        $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
        $success = $insertStmt->execute([$userTwitterID, $phraseString, $nftProfilePicturesString, $urlString, $cryptoUserNamesString,
            $NFTFollowersString, $centralDatabaseString, $followerWhitelistString, $phraseString,
            $nftProfilePicturesString, $urlString, $cryptoUserNamesString, $NFTFollowersString, $centralDatabaseString, $followerWhitelistString]);
        if (!$success) {
            CoreDB::$logger->error("Could not update automation settings for user ID $userTwitterID!");
            return $success;
        }

        if ($addCentralDBEntries) {
            $idList = [$userTwitterID];
            CoreDB::checkCentralisedBlockListForUsers($idList);
        }

        return $success;
    }

    public static function getUserInfo($userTwitterID) {
        $selectQuery = "SELECT * FROM users WHERE twitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user information, returning.");
            return null;
        }
        $row = $selectStmt->fetch();
        return $row;
    }

    public static function getUserAutomationSettings($userTwitterID) {
        $selectQuery = "SELECT * FROM userautomationsettings WHERE usertwitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user automation settings, returning.");
            return;
        }
        $row = $selectStmt->fetch();
        return $row;
    }

    public static function getUserBlocklistAutomationSettings($userTwitterID) {
        $selectQuery = "SELECT *,(SELECT name FROM blocklists WHERE userblocklistrecords.blocklistid=blocklists.id) AS name FROM "
                . "userautomationsettings INNER JOIN userblocklistrecords ON "
                . "userautomationsettings.usertwitterid=userblocklistrecords.usertwitterid WHERE userautomationsettings.usertwitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user blocklist automation settings, returning.");
            return;
        }
        $rows = $selectStmt->fetchAll();
        return $rows;
    }

    public static function getBlockablePhrases() {
        $selectQuery = "SELECT * FROM blockablephrases ORDER BY phrase ASC";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve list of blockable phrases, returning.");
            return;
        }
        $rows = $selectStmt->fetchAll();
        return $rows;
    }

    public static function getCentralDBCount() {
        $selectQuery = "SELECT COUNT(*) FROM centralisedblocklist";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve count of central DB rows, returning.");
            return;
        }
        $count = $selectStmt->fetchColumn();
        return $count;
    }

    function getBlockableURLs() {
        $selectQuery = "SELECT * FROM blockableurls ORDER BY url ASC";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve list of blockable URLs, returning.");
            return;
        }
        $rows = $selectStmt->fetchAll();
        return $rows;
    }

    public static function getBlockableUsernameRegexes() {
        $selectQuery = "SELECT * FROM blockableusernameregexes";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve list of blockable username regexes, returning.");
            return;
        }
        $rows = $selectStmt->fetchAll();
        return $rows;
    }

    public static function updateTwitterEndpointLogs($endpoint, $callCount) {
        $date = date("Y-m-d");
        $insertQuery = "INSERT INTO twitterendpointlogs (date,endpoint,callcount) VALUES (?,?,?) "
                . "ON DUPLICATE KEY UPDATE callcount=callcount+?";
        $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
        $success = $insertStmt->execute([$date, $endpoint, $callCount, $callCount]);
        if (!$success) {
            CoreDB::$logger->error("Could not update endpoints logs. Parameters were: $endpoint , $callCount");
        }
    }

    public static function setUserLocked($locked, $userTwitterID) {
        CoreDB::$logger->error("Setting locked status $locked for user twitter ID $userTwitterID");
        if ($locked !== "Y" && $locked !== "N") {
            CoreDB::$logger->error("Invalid parameters supplied to setUserLocked. Locked: $locked    User twitter ID: $userTwitterID");
            return;
        }
        $updateQuery = "UPDATE users SET locked=? WHERE twitterid=?";
        $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
        $success = $updateStmt->execute([$locked, $userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not update locked property for user with twitter ID $userTwitterID");
        }
        return $success;
    }

    public static function deleteUser($userTwitterID) {
        $deleteQuery = "DELETE FROM users WHERE twitterid=?";
        $deleteStmt = CoreDB::$databaseConnection->prepare($deleteQuery);
        $success = $deleteStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not delete user with twitter ID $userTwitterID");
        }
        return $success;
    }

    // This function is incomplete and does not work.
    public static function insertBannedUsers($users) {
        $selectQuery = "SELECT id FROM blocklists WHERE name=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute(["NFT Artists"]);
        if (!$success) {
            CoreDB::$logger->error("Could not get id of NFT Artists blocklist, returning.");
            return;
        }
        $blockListID = $selectStmt->fetchColumn();
        $screenNameKeys = array_keys($users);
        $totalCount = count($users);
        $i = 0;
        $parameterString = "";
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret);
        $connection->setRetries(1, 1);
        foreach ($screenNameKeys as $screenName) {
            $parameterString .= $screenName .= ",";
            $i++;
            if ($i % 100 == 0) {
                $userLookupResponse = $connection->post("users/lookup", $parameterString);
                $parameterString = "";
            }
        }
    }

    public static function insertUserInformation($access_token) {
        $accessToken = $access_token['oauth_token'];
        $accessTokenSecret = $access_token['oauth_token_secret'];
        $userTwitterID = $access_token['user_id'];
        $insertQuery = "INSERT INTO users (twitterid,accesstoken,accesstokensecret) VALUES (?,?,?) ON DUPLICATE KEY UPDATE "
                . "accesstoken=?, accesstokensecret=? ";
        $success = CoreDB::$databaseConnection->prepare($insertQuery)
                ->execute([$userTwitterID, $accessToken, $accessTokenSecret, $accessToken, $accessTokenSecret]);
        return $success;
    }

    public static function checkUserOperation($userTwitterID, $blockListName, $operation) {
        $selectQuery = "SELECT * FROM userblocklistrecords WHERE usertwitterid=? AND blocklistid=(SELECT id FROM blocklists WHERE name=?) "
                . "AND lastoperation=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $blockListName, $operation]);
        if (!$success) {
            CoreDB::$logger->error("Could not get id of NFT Artists blocklist, returning.");
            return;
        }
        $row = $selectStmt->fetch();
        if ($row) {
            return true;
        }
        return false;
    }

    public static function markListForUser($userTwitterID, $blockListName, $operation) {
        $operation = strtolower(filter_var($operation, FILTER_SANITIZE_STRING));
        $operation = ucfirst($operation);
        $userTwitterID = filter_var($userTwitterID, FILTER_SANITIZE_NUMBER_INT);
        $doneAlready = CoreDB::checkUserOperation($userTwitterID, $blockListName, $operation);
        if ($doneAlready) {
            return true;
        }
        $insertQuery = "set @usertwitterid = $userTwitterID; "
                . "INSERT INTO entriestoprocess (subjectusertwitterid,blocklistid,objectusertwitterid,operation) "
                . "SELECT @usertwitterid, blocklistid, blockusertwitterid, '$operation' FROM blocklistentries WHERE blocklistid="
                . "(SELECT id FROM blocklists WHERE name=?) AND blockusertwitterid NOT IN (SELECT objectusertwitterid FROM "
                . "userinitialblockrecords WHERE subjectusertwitterid=? AND operation='$operation') ON DUPLICATE KEY UPDATE operation='$operation'";
        $success1 = CoreDB::$databaseConnection->prepare($insertQuery)
                ->execute([$blockListName, $userTwitterID]);
        if (!$success1) {
            return false;
        }
        $insertQuery = "set @usertwitterid = $userTwitterID; "
                . "INSERT INTO userblocklistrecords (usertwitterid,blocklistid,lastoperation) "
                . "SELECT @usertwitterid, id, '$operation' FROM blocklists WHERE name=?"
                . " ON DUPLICATE KEY UPDATE lastoperation='$operation'";
        $success2 = CoreDB::$databaseConnection->prepare($insertQuery)
                ->execute([$blockListName]);
        return $success2;
    }

    public static function markCentralBlockListEntriesForDeletion($centralBlockListParams) {
        if (count($centralBlockListParams) == 0) {
            return;
        }
        CoreDB::$logger->error("Marking central blocklist entries for deletion.");
        $updateParams[] = "Y";
        $updateQuery = "UPDATE centralisedblocklist SET markedfordeletion=? WHERE blockableusertwitterid IN (";
        foreach ($centralBlockListParams as $blockableID) {
            $updateQuery .= "?,";
            $updateParams[] = $blockableID;
        }
        $updateQuery = substr($updateQuery, 0, -1);
        $updateQuery .= ")";
        try {
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute($updateParams);
        } catch (\Exception $e) {
            CoreDB::$logger->error("Failed to mark centralised block list entries for deletion!\n" . print_r($e, true));
            CoreDB::$logger->error("Update query: $updateQuery");
            CoreDB::$logger->error("Update params:\n" . print_r($updateParams, true));
        }
        $deleteParams[] = "centraldb";
        $deleteQuery = "DELETE FROM entriestoprocess WHERE addedfrom=? AND objectusertwitterid IN (";
        foreach ($centralBlockListParams as $blockableID) {
            $deleteQuery .= "?,";
            $deleteParams[] = $blockableID;
        }
        $deleteQuery = substr($deleteQuery, 0, -1);
        $deleteQuery .= ")";
        try {
            $deleteStmt = CoreDB::$databaseConnection->prepare($deleteQuery);
            $deleteStmt->execute($deleteParams);
        } catch (\Exception $e) {
            CoreDB::$logger->error("Failed to delete marked entries to process from queue!");
            CoreDB::$logger->error(print_r($e, true));
            CoreDB::$logger->error("Delete query: $deleteQuery");
            CoreDB::$logger->error("Delete params:");
            CoreDB::$logger->error(print_r($deleteParams, true));
        }
    }

    public static function insertCentralBlockListEntries($centralBlockListParams) {
        if (count($centralBlockListParams) == 0) {
            return;
        }
        $insertQuery = "INSERT INTO centralisedblocklist (blockableusertwitterid, matchedfiltertype, matchedfiltercontent, addedfrom) "
                . "VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE blockableusertwitterid=?, matchcount=matchcount+1";
        CoreDB::$databaseConnection->beginTransaction();
        try {
            foreach ($centralBlockListParams as $singleUserParams) {
                if (is_null($singleUserParams[2])) {
                    CoreDB::$logger->error("Null filtercontent!");
                    $filterType = $singleUserParams[1];
                    $blockedID = $singleUserParams[0];
                    CoreDB::$logger->error("Matched filter type: $filterType    Blockable user ID: $blockedID");
                }
                $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
                $insertStmt->execute($singleUserParams);
            }
            CoreDB::$databaseConnection->commit();
        } catch (\Exception $e) {
            CoreDB::$logger->error(print_r($e, true));
            CoreDB::$databaseConnection->rollback();
        }
    }

    public static function deleteProcessedEntries($deleteParams) {
        if (count($deleteParams) == 0) {
            return;
        }
        $deleteQuery = "DELETE FROM entriestoprocess WHERE id IN (?";
        for ($i = 1; $i < count($deleteParams); $i++) {
            $deleteQuery .= ",?";
        }
        $deleteQuery .= ")";
        $success = CoreDB::$databaseConnection->prepare($deleteQuery)
                ->execute($deleteParams);
        return $success;
    }

    public static function updateUserBlockRecords($recordRows) {
        $insertQuery = "INSERT INTO userblockrecords (subjectusertwitterid, blocklistid, objectusertwitterid, operation, matchedfiltertype, matchedfiltercontent, "
                . "dateprocessed, addedfrom) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE operation=?, matchedfiltertype=?, matchedfiltercontent=?";
        $dateProcessed = date('Y-m-d H:i:s');
        CoreDB::$databaseConnection->beginTransaction();
        foreach ($recordRows as $row) {
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $insertStmt->execute([$row['subjectusertwitterid'], $row['blocklistid'], $row['objectusertwitterid'], $row['operation'], $row['matchedfiltertype'],
                $row['matchedfiltercontent'], $dateProcessed, $row['addedfrom'], $row['operation'], $row['matchedfiltertype'],
                $row['matchedfiltercontent']]);
        }
        CoreDB::$databaseConnection->commit();
    }

    public static function getBlockLists() {
        $selectQuery = "SELECT * FROM blocklists";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not get list of users to process entries for, terminating.");
            return;
        }
        $rows = $selectStmt->fetchAll();
        return $rows;
    }

    public static function initialiseConnection() {
        try {
            $params = "mysql:host=" . DB::server_name . ";dbname=" . DB::database . ";port=" . DB::port . ";charset=UTF8MB4";
            CoreDB::$databaseConnection = new \PDO($params, DB::username, DB::password, CoreDB::options);
        } catch (\Exception $e) {
            CoreDB::$logger->emergency("Failed to create database connection. Exception:");
            CoreDB::$logger->emergency(print_r($e, true));
            echo "Failed to create database connection.";
            exit();
        }

        CoreDB::$databaseConnection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

}

CoreDB::$logger = LogManager::getLogger("CoreDB");
CoreDB::initialiseConnection();

