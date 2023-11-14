<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

use Antsstyle\NFTCryptoBlocker\Core\CachedVariables;
use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Credentials\DB;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

class CoreDB {

    const options = [
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ];

    public static $databaseConnection;
    public static $logger;
    public static $maxTransactionRetries = 5;

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

    public static function updateUserHighestActionedCentralDBID($userTwitterID, $highestActionedCentralDBID, $centralBlockListInsertParams) {
        $highestIDToSet = null;
        if (count($centralBlockListInsertParams) > 0) {
            $selectQuery = "SELECT MAX(id) AS highestid FROM centralisedblocklist WHERE blockableusertwitterid IN (";
            $selectParams = [];
            foreach ($centralBlockListInsertParams as $insertParams) {
                $selectQuery .= "?,";
                $selectParams[] = $insertParams[0];
            }
            $selectQuery = substr($selectQuery, 0, -1);
            $selectQuery .= ")";
            $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
            $success = $selectStmt->execute($selectParams);
            if (!$success) {
                CoreDB::$logger->critical("Unable to get max ID from centralised blocklist!");
                return null;
            }

            $row = $selectStmt->fetch();
            if ($row !== false && !is_null($row['highestid'])) {
                $highestIDToSet = max($row['highestid'], $highestActionedCentralDBID);
            } else {
                $highestIDToSet = $highestActionedCentralDBID;
            }
        } else {
            $highestIDToSet = $highestActionedCentralDBID;
        }
        $userInfo = CoreDB::getUserInfo($userTwitterID);
        $currentHighestID = $userInfo['highestactionedcentraldbid'];
        if ($highestIDToSet <= $currentHighestID) {
            return;
        }
        $updateQuery = "UPDATE users SET highestactionedcentraldbid=? WHERE twitterid=?";
        $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
        $updateStmt->execute([$highestIDToSet, $userTwitterID]);
    }

    public static function verifyUserRateLimitOK($userTwitterID, $endpoint) {
        $now = strtotime("now");
        $selectQuery = "SELECT resettime FROM userratelimitrecords WHERE usertwitterid=? AND endpoint=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $endpoint]);
        if (!$success) {
            CoreDB::$logger->emergency("Could not retrieve user rate limit records - unable to verify user rate limits!");
            return null;
        }
        $row = $selectStmt->fetch();
        if ($row === false) {
            return true;
        }
        $resettime = strtotime($row['resettime']);
        return ($now > $resettime);
    }

    public static function updateUserRateLimitInfo($userTwitterID, $endpoint, $resetTime) {
        $selectQuery = "SELECT usertwitterid FROM userratelimitrecords WHERE usertwitterid=? AND endpoint=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $endpoint]);
        if (!$success) {
            CoreDB::$logger->emergency("Could not retrieve user rate limit records - unable to update rate limits!");
            return false;
        }
        $row = $selectStmt->fetch();
        if ($row !== false) {
            $updateQuery = "UPDATE userratelimitrecords SET resettime=? WHERE usertwitterid=? AND endpoint=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $success = $updateStmt->execute([$resetTime, $userTwitterID, $endpoint]);
            if (!$success) {
                CoreDB::$logger->emergency("Could not update user rate limit records!");
                return false;
            }
        } else {
            $updateQuery = "INSERT INTO userratelimitrecords (usertwitterid,endpoint,resettime) VALUES (?,?,?)";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $success = $updateStmt->execute([$userTwitterID, $endpoint, $resetTime]);
            if (!$success) {
                CoreDB::$logger->emergency("Could not update user rate limit records!");
                return false;
            }
        }
    }

    public static function updateObjectUserInfo() {
        $selectQuery = "SELECT DISTINCT objectusertwitterid FROM userblockrecords WHERE objectusertwitterid "
                . "NOT IN (SELECT objectusertwitterid FROM objectuserinformation) LIMIT 1000";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user block records to update, returning.");
            return false;
        }
        $lookupString = "";
        $count = 0;
        $insertQuery = "INSERT IGNORE INTO objectuserinformation (objectusertwitterid,username) VALUES (?,?)";
        $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
        $i = 0;
        while ($userTwitterID = $selectStmt->fetchColumn()) {
            $i++;
            $lookupString .= $userTwitterID .= ",";
            $count++;
            if ($count === 100) {
                $lookupString = substr($lookupString, 0, -1);
                $response = TwitterUsers::userLookup($lookupString);
                if (is_null($response)) {
                    CoreDB::$logger->error("Response was null");
                    return;
                }
                $data = $response->data;
                $transactionErrors = 0;
                while ($transactionErrors < CoreDB::$maxTransactionRetries) {
                    try {
                        CoreDB::$databaseConnection->beginTransaction();
                        foreach ($data as $dataEntry) {
                            $userHandle = $dataEntry->username;
                            $userID = $dataEntry->id;
                            $insertStmt->execute([$userID, $userHandle]);
                        }
                        CoreDB::$databaseConnection->commit();
                        break;
                    } catch (\Exception $e) {
                        CoreDB::$databaseConnection->rollback();
                        $transactionErrors++;
                    }
                }
                if ($transactionErrors === CoreDB::$maxTransactionRetries) {
                    CoreDB::$logger->critical("Failed to commit transaction for updating object user information: " . print_r($e, true));
                }

                $lookupString = "";
            }
        }
    }

    public static function getUserRateLimitRecords($userTwitterID, $endpoint) {
        $selectQuery = "SELECT resettime FROM userratelimitrecords WHERE usertwitterid=? AND endpoint=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $endpoint]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user rate limit info for user twitter ID $userTwitterID and endpoint $endpoint");
            return null;
        }
        return $selectStmt->fetchColumn();
    }

    public static function getUserBlockRecordsCount($userTwitterID) {
        $date1MonthAgo = date("Y-m-d H:i:s", strtotime("-1 month"));
        $selectQuery = "SELECT COUNT(*) AS rowcount "
                . "FROM userblockrecords INNER JOIN objectuserinformation ON userblockrecords.objectusertwitterid=objectuserinformation.objectusertwitterid "
                . "WHERE subjectusertwitterid=? AND dateprocessed >= ?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $date1MonthAgo]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve list of blockable phrases, returning.");
            return null;
        }
        $count = $selectStmt->fetchColumn();
        return $count;
    }

    public static function getUserBlockRecords($userTwitterID, $pageNumber) {
        $offSet = ($pageNumber - 1) * 100;
        $date1MonthAgo = date("Y-m-d H:i:s", strtotime("-1 month"));
        $selectQuery = "SELECT objectuserinformation.username AS username,dateprocessed,operation,matchedfiltertype,matchedfiltercontent,"
                . "addedfrom,blocklistid "
                . "FROM userblockrecords INNER JOIN objectuserinformation ON userblockrecords.objectusertwitterid=objectuserinformation.objectusertwitterid "
                . "WHERE subjectusertwitterid=? AND dateprocessed >= ? ORDER BY dateprocessed DESC LIMIT 100 OFFSET $offSet";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $date1MonthAgo]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve list of blockable phrases, returning.");
            return null;
        }
        $rows = $selectStmt->fetchAll();
        return $rows;
    }

    public static function getLowestCountinPNumMap($pnumMap) {
        $lowestValue = null;
        $lowestKey = null;
        foreach ($pnumMap as $key => $value) {
            if (count($pnumMap) === 1) {
                return $key;
            } else if ($value === 0) {
                return $key;
            } else if (is_null($lowestValue)) {
                $lowestValue = $value;
                $lowestKey = $key;
            } else if ($lowestValue > $value) {
                $lowestValue = $value;
                $lowestKey = $key;
            }
        }
        return $lowestKey;
    }

    public static function assignEntryPNumsByUser() {
        $numThreads = CoreDB::getCachedVariable(CachedVariables::NUM_PROCESSENTRIES_THREADS);
        if (is_null($numThreads)) {
            CoreDB::$logger->critical("Could not retrieve number of ProcessEntries threads, returning (was null).");
            return;
        }
        if ($numThreads === false) {
            CoreDB::$logger->critical("Could not retrieve number of ProcessEntries threads, returning.");
            return;
        }

        $userCurrentMap = [];
        $userPendingMap = [];

        $selectQuery = "SELECT pnum,subjectusertwitterid,COUNT(pnum) AS recordcount FROM entriestoprocess "
                . "WHERE pnum IS NOT NULL GROUP BY pnum,subjectusertwitterid";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->critical("Could not retrieve entries to process counts, returning.");
            return false;
        }
        while ($row = $selectStmt->fetch()) {
            $info = new \stdClass();
            $info->recordcount = $row['recordcount'];
            $info->pnum = $row['pnum'];
            $userCurrentMap[$row['subjectusertwitterid']] = $info;
        }

        $selectQuery = "SELECT subjectusertwitterid,COUNT(subjectusertwitterid) AS recordcount FROM entriestoprocess "
                . "WHERE pnum IS NULL GROUP BY subjectusertwitterid";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->critical("Could not retrieve entries to process counts, returning.");
            return false;
        }
        while ($row = $selectStmt->fetch()) {
            $userPendingMap[$row['subjectusertwitterid']] = $row['recordcount'];
        }

        $selectQuery = "SELECT pnum,COUNT(pnum) AS pnumcount FROM entriestoprocess GROUP BY pnum";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->critical("Could not retrieve grouped entries to process counts, returning.");
            return false;
        }
        $pnumMap = [];
        while ($row = $selectStmt->fetch()) {
            if (!is_null($row['pnum'])) {
                $pnumMap[$row['pnum']] = $row['pnumcount'];
            }
        }
        for ($i = 1; $i <= $numThreads; $i++) {
            if (!array_key_exists($i, $pnumMap)) {
                $pnumMap[$i] = 0;
            }
        }
        $updateQuery = "UPDATE entriestoprocess SET pnum=? WHERE subjectusertwitterid=? AND pnum IS NULL";
        $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
        foreach ($userPendingMap as $userID => $userPendingCount) {
            $info = $userCurrentMap[$userID];
            if (is_null($info) || $info === false) {
                $lowestPnum = self::getLowestCountinPNumMap($pnumMap);
                $params = [$lowestPnum, $userID];
                $pnumMap[$lowestPnum] = $pnumMap[$lowestPnum] + $userPendingCount;
            } else {
                $params = [$info->pnum, $userID];
            }
            $errors = 0;
            while ($errors < 20) {
                try {
                    $updateStmt->execute($params);
                    break;
                } catch (\Exception $ex) {
                    $errors++;
                }
            }
            if ($errors === 20) {
                CoreDB::$logger->error("Failed to update entries to process pnums after 20 attempts: " . print_r($ex, true));
            }
        }
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
        }
        $balancedPerThread = intval($nullCount / $numThreads);
        $selectQuery = "SELECT pnum,COUNT(pnum) AS pnumcount FROM entriestoprocess GROUP BY pnum";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->critical("Could not retrieve grouped entries to process counts, returning.");
            return false;
        }
        CoreDB::$logger->info("Per thread count: $balancedPerThread    Null count: $nullCount     Num threads: $numThreads");
        $pnumMap = [];
        while ($row = $selectStmt->fetch()) {
            if (!is_null($row['pnum'])) {
                $pnumMap[$row['pnum']] = $row['pnumcount'];
            }
        }
        for ($i = 1; $i <= $numThreads; $i++) {
            if (!array_key_exists($i, $pnumMap)) {
                $pnumMap[$i] = 0;
            }
        }
        CoreDB::$logger->info("Current workload: " . print_r($pnumMap, true));
        $pnumMap = Core::getLoadBalanceValues($pnumMap, $nullCount);
        CoreDB::$logger->info("pnumMap: " . print_r($pnumMap, true));
        foreach ($pnumMap as $key => $value) {
            if ($value === 0) {
                continue;
            }
            $updateQuery = "UPDATE entriestoprocess SET pnum=? WHERE pnum IS NULL LIMIT $value";
            CoreDB::$logger->info("Adding $value entries to pnum $key");
            $errors = 0;
            while ($errors < 5) {
                try {
                    $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
                    $updateStmt->execute([$key]);
                    break;
                } catch (\Exception $e) {
                    $errors++;
                }
            }
            if ($errors === 5) {
                CoreDB::$logger->warn("Five errors assining $value entries to pnum $key");
            }
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
                    $userBlockRecordsTotal = $dbRow['opcount'];
                    if ($operation === "Block") {
                        $recordSelectQuery = "SELECT SUM(blockcount) FROM userblockcounts";
                        $recordSelectStmt = CoreDB::$databaseConnection->prepare($recordSelectQuery);
                        $success = $recordSelectStmt->execute();
                        if (!$success) {
                            CoreDB::$logger->error("Could not retrieve total block counts from userblockcounts table!");
                        } else {
                            $userBlockCountsTotal = $recordSelectStmt->fetchColumn();
                            $finalCount = $userBlockRecordsTotal + $userBlockCountsTotal;
                            CoreDB::$logger->error("Updating block counts with: $userBlockRecordsTotal block records and $userBlockCountsTotal block counts for a final count of $finalCount");
                            CoreDB::updateCachedVariable(CachedVariables::CACHED_TOTAL_BLOCKS_COUNT, $finalCount);
                        }
                    } else if ($operation === "Mute") {
                        $recordSelectQuery = "SELECT SUM(mutecount) FROM userblockcounts";
                        $recordSelectStmt = CoreDB::$databaseConnection->prepare($recordSelectQuery);
                        $success = $recordSelectStmt->execute();
                        if (!$success) {
                            CoreDB::$logger->error("Could not retrieve total mute counts from userblockcounts table!");
                        } else {
                            $userBlockCountsTotal = $recordSelectStmt->fetchColumn();
                            $finalCount = $userBlockRecordsTotal + $userBlockCountsTotal;
                            CoreDB::$logger->error("Updating mute counts with: $userBlockRecordsTotal mute records and $userBlockCountsTotal mute counts for a final count of $finalCount");
                            CoreDB::updateCachedVariable(CachedVariables::CACHED_TOTAL_MUTES_COUNT, $finalCount);
                        }
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
            CoreDB::$logger->critical("Database error retrieving cached variable with name: $name.");
            return null;
        }
        $row = $selectStmt->fetch();
        if ($row === false) {
            CoreDB::$logger->error("Cached variable with name: $name does not exist.");
            return false;
        }
        return $row['value'];
    }

    public static function updateCachedVariable($name, $value) {
        $row = CoreDB::getCachedVariable($name);
        if ($row === false) {
            $insertQuery = "INSERT INTO cachedvariables (name,value) VALUES (?,?)";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            try {
                $success = $insertStmt->execute([$name, $value]);
                if (!$success) {
                    CoreDB::$logger->error("Could not insert cached variable with name: $name, value: $value");
                }
            } catch (\Exception $e) {
                CoreDB::$logger->error("Could not insert cached variable with name: $name, value: $value. " . print_r($e, true));
            }
        } else {
            $updateQuery = "UPDATE cachedvariables SET value=? WHERE name=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            try {
                $success = $updateStmt->execute([$value, $name]);
                if (!$success) {
                    CoreDB::$logger->error("Could not update cached variable with name: $name, value: $value");
                }
            } catch (\Exception $e) {
                CoreDB::$logger->error("Could not insert cached variable with name: $name, value: $value. " . print_r($e, true));
            }
        }
        return $success;
    }

    public static function searchUserBlockRecords($userTwitterID, $searchString) {
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
        $selectQuery = "SELECT username,addedfrom,matchedfiltertype,matchedfiltercontent,blocklistid "
                . "FROM userblockrecords INNER JOIN objectuserinformation ON "
                . "userblockrecords.objectusertwitterid=objectuserinformation.objectusertwitterid "
                . "WHERE subjectusertwitterid=? AND (username IS NOT NULL AND username LIKE ?) ORDER BY username ASC";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $searchString]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve block record entries for user search request, returning.");
            return false;
        }
        $userBlockRecords = [];
        while ($row = $selectStmt->fetch()) {
            $userBlockRecords[] = $row;
        }
        return $userBlockRecords;
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
        $minFollowers = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_FOLLOWERCOUNT);
        $minMatchCount = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_MATCHCOUNT);
        $offSet = ($pageNumber - 1) * 100;
        $selectQuery = "SELECT * FROM centralisedblocklist WHERE followercount IS NOT NULL AND followercount > ? AND matchcount >= ? "
                . "AND markedfordeletion=? ORDER BY followercount DESC LIMIT 100 OFFSET $offSet";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$minFollowers, $minMatchCount, "N"]);
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
        $selectQuery = "SELECT blockcount,mutecount,unblockcount,unmutecount FROM userblockcounts WHERE usertwitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user stats, returning.");
            return false;
        }
        $row = $selectStmt->fetch();
        if ($row['blockcount'] > 0) {
            $userStats['Block'] = $row['blockcount'];
        }
        if ($row['mutecount'] > 0) {
            $userStats['Mute'] = $row['mutecount'];
        }
        if ($row['unblockcount'] > 0) {
            $userStats['Unblock'] = $row['unblockcount'];
        }
        if ($row['unmutecount'] > 0) {
            $userStats['Unmute'] = $row['unmutecount'];
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

        $selectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid "
                . "WHERE (centraldatabaseoperation=? OR centraldatabaseoperation=?)"
                . " AND locked=?";
        $selectParams = ["Block", "Mute", "N"];
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
            CoreDB::checkCentralisedBlockListForUserRow($userRow);
        }
    }

    public static function checkCentralisedBlockListForUserRow($userRow) {
        $userTwitterID = $userRow['usertwitterid'];
        $cryptoUsernamesOperation = $userRow['cryptousernamesoperation'];
        $matchingPhraseOperation = $userRow['matchingphraseoperation'];
        $nftProfilePictureOperation = $userRow['nftprofilepictureoperation'];
        $urlsOperation = $userRow['urlsoperation'];
        $nftFollowersOperation = $userRow['nftfollowersoperation'];
        $blockArray = [];
        $muteArray = [];
        $opsArray = array("matchingphrase" => $matchingPhraseOperation, "cryptousernames" => $cryptoUsernamesOperation,
            "urls" => $urlsOperation, "profileurls" => $urlsOperation, "nftprofilepictures" => $nftProfilePictureOperation,
            "nftfollowers" => $nftFollowersOperation);
        foreach ($opsArray as $key => $value) {
            if ($value === "Mute") {
                $muteArray[] = $key;
            } else if ($value === "Block") {
                $blockArray[] = $key;
            }
        }
        $paramsArray = [];
        $minFollowerCount = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_FOLLOWERCOUNT);
        $minMatchCount = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_MATCHCOUNT);
        if (is_null($minFollowerCount) || $minFollowerCount === false) {
            $minFollowerCount = 10000;
        }
        if (is_null($minMatchCount) || $minMatchCount === false) {
            $minMatchCount = 2;
        }
        $paramsArray[] = $minFollowerCount;
        $paramsArray[] = $minMatchCount;
        if (count($muteArray) > 0) {
            $insertQuery = ""
                    . "INSERT INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,addedfrom,matchedfiltertype,"
                    . "matchedfiltercontent,centraldbid) "
                    . "(SELECT $userTwitterID, blockableusertwitterid, 'Mute', 'centraldb', matchedfiltertype, matchedfiltercontent, id "
                    . "FROM centralisedblocklist WHERE "
                    . "centralisedblocklist.id > (SELECT highestactionedcentraldbid FROM users WHERE twitterid=$userTwitterID) "
                    . "AND markedfordeletion=\"N\" AND (followercount IS NOT NULL AND followercount > ?) AND matchcount >= ? ";
            $insertQuery .= "AND matchedfiltertype IN (";
            foreach ($muteArray as $muteEntry) {
                $insertQuery .= "'" . $muteEntry . "',";
            }
            $insertQuery = substr($insertQuery, 0, -1);
            $insertQuery .= ")) ON DUPLICATE KEY UPDATE operation='Mute'";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $success = $insertStmt->execute($paramsArray);
            $insertStmt->closeCursor();
        }
        $paramsArray = [];
        $paramsArray[] = $minFollowerCount;
        $paramsArray[] = $minMatchCount;
        if (count($blockArray) > 0) {
            $insertQuery = "INSERT INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,addedfrom,matchedfiltertype,"
                    . "matchedfiltercontent,centraldbid) "
                    . "(SELECT $userTwitterID, blockableusertwitterid, 'Block', 'centraldb', matchedfiltertype, matchedfiltercontent, id "
                    . "FROM centralisedblocklist WHERE "
                    . "centralisedblocklist.id > (SELECT highestactionedcentraldbid FROM users WHERE twitterid=$userTwitterID) "
                    . "AND markedfordeletion=\"N\" AND (followercount IS NOT NULL AND followercount > ?) AND matchcount >= ? ";
            $insertQuery .= "AND matchedfiltertype IN (";
            foreach ($blockArray as $blockEntry) {
                $insertQuery .= "'" . $blockEntry . "',";
            }
            $insertQuery = substr($insertQuery, 0, -1);
            $insertQuery .= ")) ON DUPLICATE KEY UPDATE operation='Block'";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $success = $insertStmt->execute($paramsArray);
            $insertStmt->closeCursor();
        }
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
            $transactionErrors = 0;
            while ($transactionErrors < CoreDB::$maxTransactionRetries) {
                try {
                    CoreDB::$databaseConnection->beginTransaction();
                    while ($row = $selectStmt->fetch()) {
                        $userSelectQuery = "SELECT twitterid FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid "
                                . "WHERE twitterid IN (SELECT usertwitterid FROM userblocklistrecords "
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
                    break;
                } catch (\Exception $e) {
                    CoreDB::$databaseConnection->rollback();
                    $transactionErrors++;
                }
            }
            if ($transactionErrors === CoreDB::$maxTransactionRetries) {
                CoreDB::$logger->critical("Failed to commit blocklist update checks to database: " . print_r($e, true));
            }
        }
    }

    public static function updateUserAutomationSettings($userTwitterID, $phraseSettings, $urlSettings, $nftProfilePicturesSettings,
            $cryptoUserNamesSettings, $NFTFollowersSettings, $centralDatabaseSettings, $cryptoSpambotsSettings, $followerWhitelistSettings) {
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
        if ($cryptoSpambotsSettings !== "noaction_cryptospambots" && $cryptoSpambotsSettings !== "block_cryptospambots" &&
                $cryptoSpambotsSettings !== "mute_cryptospambots") {
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
        $cryptoSpambotsArray = explode("_", $cryptoSpambotsSettings);
        $cryptoSpambotsString = ucfirst($cryptoSpambotsArray[0]);
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
                . "urlsoperation,cryptousernamesoperation,nftfollowersoperation,centraldatabaseoperation,cryptospambotsoperation,whitelistfollowings) VALUES (?,?,?,?,?,?,?,?,?) "
                . "ON DUPLICATE KEY UPDATE matchingphraseoperation=?, "
                . "nftprofilepictureoperation=?, urlsoperation=?, cryptousernamesoperation=?, "
                . "nftfollowersoperation=?, centraldatabaseoperation=?, cryptospambotsoperation=?, whitelistfollowings=?";
        $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
        $success = $insertStmt->execute([$userTwitterID, $phraseString, $nftProfilePicturesString, $urlString, $cryptoUserNamesString,
            $NFTFollowersString, $centralDatabaseString, $cryptoSpambotsString, $followerWhitelistString, $phraseString,
            $nftProfilePicturesString, $urlString, $cryptoUserNamesString, $NFTFollowersString, $centralDatabaseString, $cryptoSpambotsString, $followerWhitelistString]);
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

    public static function getUserByUsername($userName) {
        $selectQuery = "SELECT * FROM users WHERE username=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userName]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve user information, returning.");
            return null;
        }
        $row = $selectStmt->fetch();
        return $row;
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
        $minFollowers = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_FOLLOWERCOUNT);
        $minMatchCount = CoreDB::getCachedVariable(CachedVariables::CENTRALISEDBLOCKLIST_MIN_MATCHCOUNT);
        $selectQuery = "SELECT COUNT(*) FROM centralisedblocklist WHERE followercount IS NOT NULL AND followercount > ? AND matchcount >= ?"
                . " AND markedfordeletion=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$minFollowers, $minMatchCount, "N"]);
        if (!$success) {
            CoreDB::$logger->error("Could not retrieve count of central DB rows, returning.");
            return;
        }
        $count = $selectStmt->fetchColumn();
        return $count;
    }

    public static function getBlockableURLs() {
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
        try {
            $insertStmt->execute([$date, $endpoint, $callCount, $callCount]);
        } catch (\Exception $e) {
            CoreDB::$logger->error("Could not update endpoints logs. Parameters were: $endpoint , $callCount. Exception: " . print_r($e, true));
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

    public static function setUserRevoked($revoked, $userTwitterID) {
        $deleteQuery = "UPDATE users SET revoked=? WHERE twitterid=?";
        $deleteStmt = CoreDB::$databaseConnection->prepare($deleteQuery);
        $success = $deleteStmt->execute([$revoked, $userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not revoke user with twitter ID $userTwitterID");
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
                . "accesstoken=?, accesstokensecret=?, locked=?, revoked=? ";
        $success = CoreDB::$databaseConnection->prepare($insertQuery)
                ->execute([$userTwitterID, $accessToken, $accessTokenSecret, $accessToken, $accessTokenSecret, "N", "N"]);
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
                . "INSERT INTO entriestoprocess (subjectusertwitterid,blocklistid,objectusertwitterid,operation,addedfrom) "
                . "SELECT @usertwitterid, blocklistid, blockusertwitterid, '$operation', 'blocklist' FROM blocklistentries WHERE blocklistid="
                . "(SELECT id FROM blocklists WHERE name=?) ON DUPLICATE KEY UPDATE operation='$operation'";
        $success1 = CoreDB::$databaseConnection->prepare($insertQuery)
                ->execute([$blockListName]);
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
                . "VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE "
                . "matchedfiltertype=?, matchedfiltercontent=?, addedfrom=?, matchcount=matchcount+1";

        $transactionErrors = 0;
        while ($transactionErrors < CoreDB::$maxTransactionRetries) {
            try {
                CoreDB::$databaseConnection->beginTransaction();
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
                break;
            } catch (\Exception $e) {
                CoreDB::$databaseConnection->rollback();
                $transactionErrors++;
            }
        }
        if ($transactionErrors === CoreDB::$maxTransactionRetries) {
            CoreDB::$logger->critical("Failed to commit central blocklist entries transaction: " . print_r($e, true));
        }
    }

    public static function deleteProcessedEntries($deleteParams, $pnum) {
        if (count($deleteParams) == 0) {
            return;
        }
        foreach ($deleteParams as $param) {
            $deleteQuery = "DELETE FROM entriestoprocess WHERE id=?";
            try {
                CoreDB::$databaseConnection->prepare($deleteQuery)
                        ->execute([$param]);
            } catch (\Exception $e) {
                Core::$logger->error("Failed to delete entry to process with ID " . $param . " : " . print_r($e, true));
            }
        }
    }

    /*
      public static function deleteProcessedEntries($deleteParams, $pnum) {
      if (count($deleteParams) == 0) {
      return;
      }
      $deleteQuery = "DELETE FROM entriestoprocess WHERE pnum=? AND (id=?";
      for ($i = 1; $i < count($deleteParams); $i++) {
      $deleteQuery .= " OR id=?";
      }
      $deleteQuery .= ")";
      array_unshift($deleteParams, $pnum);
      $errors = 0;
      while ($errors < 10) {
      try {
      CoreDB::$databaseConnection->prepare($deleteQuery)
      ->execute($deleteParams);
      break;
      } catch (\Exception $ex) {
      $errors++;
      }
      }
      if ($errors === 10) {
      CoreDB::$logger->error("10 failures to delete processed entries - terminating.");
      throw new \Exception("10 failures to delete processed entries - terminating.");
      }
      }
     */

    public static function incrementUserBlockCounts($recordRows) {
        if (!is_array($recordRows) || count($recordRows) === 0) {
            return;
        }
        $userTwitterID = $recordRows[0]['subjectusertwitterid'];
        $selectQuery = "SELECT * FROM userblockcounts WHERE usertwitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not get userblockcounts record for user twitter ID " . $recordRows[0]['subjectusertwitterid']);
            return null;
        }
        $blockCountsRow = $selectStmt->fetch();
        $blockCount = 0;
        $muteCount = 0;
        $unblockCount = 0;
        $unmuteCount = 0;
        foreach ($recordRows as $row) {
            $operation = strtolower($row['operation']);
            if ($operation === "block") {
                $blockCount++;
            } else if ($operation === "mute") {
                $muteCount++;
            } else if ($operation === "unblock") {
                $unblockCount++;
            } else if ($operation === "unmute") {
                $unmuteCount++;
            }
        }
        if ($blockCountsRow === false) {
            $insertQuery = "INSERT INTO userblockcounts (usertwitterid,blockcount,mutecount,unblockcount,unmutecount) VALUES (?,?,?,?,?)";
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $insertStmt->execute([$userTwitterID, $blockCount, $muteCount, $unblockCount, $unmuteCount]);
        } else {
            $updateStmt = "UPDATE userblockcounts SET blockcount=blockcount+?, mutecount=mutecount+?, unblockcount=unblockcount+?, "
                    . "unmutecount=unmutecount+? WHERE usertwitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateStmt);
            $updateStmt->execute([$blockCount, $muteCount, $unblockCount, $unmuteCount, $userTwitterID]);
        }
    }

    public static function updateUserBlockRecords($recordRows) {
        $insertQuery = "INSERT INTO userblockrecords (subjectusertwitterid, blocklistid, objectusertwitterid, operation, matchedfiltertype, matchedfiltercontent, "
                . "dateprocessed, addedfrom) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE operation=?, matchedfiltertype=?, matchedfiltercontent=?";
        $dateProcessed = date('Y-m-d H:i:s');
        $transactionErrors = 0;
        while ($transactionErrors < CoreDB::$maxTransactionRetries) {
            try {
                CoreDB::$databaseConnection->beginTransaction();
                foreach ($recordRows as $row) {
                    $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
                    $insertStmt->execute([$row['subjectusertwitterid'], $row['blocklistid'], $row['objectusertwitterid'], $row['operation'], $row['matchedfiltertype'],
                        $row['matchedfiltercontent'], $dateProcessed, $row['addedfrom'], $row['operation'], $row['matchedfiltertype'],
                        $row['matchedfiltercontent']]);
                }
                CoreDB::$databaseConnection->commit();
                break;
            } catch (\Exception $e) {
                CoreDB::$databaseConnection->rollback();
                $transactionErrors++;
            }
        }
        if ($transactionErrors === CoreDB::$maxTransactionRetries) {
            CoreDB::$logger->critical("Failed to commit update to user block records: " . print_r($e, true));
        }
    }

    public static function getBlockLists() {
        $selectQuery = "SELECT * FROM blocklists";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not get list of users to process entries for, terminating.");
            return null;
        }
        $rows = $selectStmt->fetchAll();
        return $rows;
    }

    public static function getUserBlockCounts($userTwitterID) {
        $selectQuery = "SELECT * FROM userblockcounts WHERE usertwitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            CoreDB::$logger->error("Could not get userblockcounts record for user twitter ID $userTwitterID");
            return null;
        }
        return $selectStmt->fetch();
    }

    public static function archiveIndividualUserBlockRecords() {
        $selectQuery = "SELECT DISTINCT subjectusertwitterid FROM userblockrecords";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            CoreDB::$logger->error("Could not get list of users to process entries for, terminating.");
            return null;
        }
        while ($userRow = $selectStmt->fetch()) {
            $userTwitterID = $userRow['subjectusertwitterid'];
            $minus30Days = date("Y-m-d H:i:s", strtotime("-30 days"));
            $selectQuery = "SELECT operation,COUNT(operation) AS recordcount FROM userblockrecords "
                    . "WHERE dateprocessed < ? AND subjectusertwitterid=? GROUP BY subjectusertwitterid,operation";
            $recordSelectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
            $success = $recordSelectStmt->execute([$minus30Days, $userTwitterID]);
            if (!$success) {
                CoreDB::$logger->error("Could not get list of users to process entries for, terminating.");
                return null;
            }
            $unmuteCount = 0;
            $muteCount = 0;
            $blockCount = 0;
            $unblockCount = 0;
            while ($recordRow = $recordSelectStmt->fetch()) {
                $operation = strtolower($recordRow['operation']);
                $recordCount = $recordRow['recordcount'];
                if ($operation === "block") {
                    $blockCount = $recordCount;
                } else if ($operation === "mute") {
                    $muteCount = $recordCount;
                } else if ($operation === "unmute") {
                    $unmuteCount = $recordCount;
                } else if ($operation === "unblock") {
                    $unblockCount = $recordCount;
                }
            }
            $existingRecordRow = self::getUserBlockCounts($userTwitterID);
            if (is_null($existingRecordRow)) {
                self::$logger->error("Could not obtain existing user record row information - cannot update user block records.");
            } else {
                if ($existingRecordRow === false) {
                    $insertQuery = "INSERT INTO userblockcounts (usertwitterid,blockcount,mutecount,unblockcount,unmutecount) VALUES (?,?,?,?,?)";
                    $insertQuery = CoreDB::$databaseConnection->prepare($insertQuery);
                    $result = $insertQuery->execute([$userTwitterID, $blockCount, $muteCount, $unblockCount, $unmuteCount]);
                } else {
                    $updateQuery = "UPDATE userblockcounts SET blockcount=blockcount+?, mutecount=mutecount+?, unblockcount=unblockcount+?, unmutecount=unmutecount+? WHERE usertwitterid=?";
                    $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
                    $result = $updateStmt->execute([$blockCount, $muteCount, $unblockCount, $unmuteCount, $userTwitterID]);
                }
                if ($result) {
                    $total = $blockCount + $muteCount + $unblockCount + $unmuteCount;
                    if ($total > 0) {
                        CoreDB::$logger->error("Archived a total of $total operations for user ID $userTwitterID.");
                    }
                    $deleteQuery = "DELETE FROM userblockrecords WHERE subjectusertwitterid=? AND dateprocessed < ?";
                    $deleteStmt = CoreDB::$databaseConnection->prepare($deleteQuery);
                    $deleteStmt->execute([$userTwitterID, $minus30Days]);
                    $rc = $deleteStmt->rowCount();
                    CoreDB::$logger->error("Deleted " . $rc . " old record rows for user twitter ID $userTwitterID.");
                } else {
                    self::$logger->error("Failed to update or insert user block counts - cannot delete old block records.");
                }
            }
        }
    }

    public static function archiveBlockRecords() {
        $minus30Days = date("Y-m-d H:i:s", strtotime("-30 days"));
        $selectQuery = "SELECT COUNT(*) AS recordcount,subjectusertwitterid FROM userblockrecords WHERE dateprocessed < ? GROUP BY subjectusertwitterid";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$minus30Days]);
        if (!$success) {
            CoreDB::$logger->error("Could not get list of users to process entries for, terminating.");
            return null;
        }
        while ($row = $selectStmt->fetch()) {
            $userTwitterID = $row['subjectusertwitterid'];
            $recordCount = $row['recordcount'];
        }
    }

    public static function initialise() {
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

        /* $retries = CoreDB::getCachedVariable(CachedVariables::MAX_TRANSACTION_RETRIES);
          if ($retries === false) {
          CoreDB::updateCachedVariable(CachedVariables::MAX_TRANSACTION_RETRIES, 5);
          } else if (!is_null($retries) && is_numeric($retries)) {
          CoreDB::$maxTransactionRetries = $retries;
          } */
    }

}

CoreDB::$logger = LogManager::getLogger("CoreDB");
CoreDB::initialise();

