<?php

namespace Antsstyle\NFTArtistBlocker\Core;

use Antsstyle\NFTArtistBlocker\Core\StatusCodes;
use Antsstyle\NFTArtistBlocker\Credentials\APIKeys;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;
use Abraham\TwitterOAuth\TwitterOAuth;

class Core {

    public static function checkResponseHeadersForErrors($connection, $userTwitterID = null) {
        $headers = $connection->getLastXHeaders();
        $httpCode = $connection->getLastHttpCode();
        if ($httpCode != 200) {
            $requestBody = $connection->getLastBody();
            if (isset($requestBody->errors) && is_array($requestBody->errors)) {
                $error = $requestBody->errors[0];
                $errorCode = $error->code;
                if ($errorCode == StatusCodes::INVALID_ACCESS_TOKEN) {
                    CoreDB::deleteUser($userTwitterID);
                    return StatusCodes::INVALID_ACCESS_TOKEN;
                }
                if (!(($httpCode >= 500 && $httpCode <= 599) || ($httpCode >= 200 && $httpCode <= 299))) {
                    error_log("Response headers contained HTTP code: $httpCode. Response body was:");
                    error_log(print_r($connection->getLastBody(), true));
                }
            }
            return $httpCode;
        }
        if (!isset($headers['x_rate_limit_remaining']) || !isset($headers['x_rate_limit_limit']) || !isset($headers['x_rate_limit_reset'])) {
            return StatusCodes::QUERY_OK;
        }
        if ($headers['x_rate_limit_remaining'] == 0) {
            return StatusCodes::RATE_LIMIT_ZERO;
        }
        return StatusCodes::QUERY_OK;
    }

    public static function checkWhitelistForUser($userRow, $potentialIDs) {
        if ($userRow['whitelistfollowings'] != "Y") {
            return [];
        }
        if (!is_array($potentialIDs)) {
            error_log("Invalid input supplied to checkWhitelistForUser - potentialIDs was not an array.");
            return null;
        }
        if (count($potentialIDs) == 0) {
            return [];
        }
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(1, 1);
        $totalCount = count($potentialIDs);
        $paramStrings = [];
        for ($i = 0; $i < $totalCount; $i += 100) {
            $separatedArray = array_slice($potentialIDs, $i, 100);
            if (count($separatedArray) > 0) {
                $paramString = "";
                foreach ($separatedArray as $entry) {
                    $paramString .= $entry . ",";
                }
                $paramString = substr($paramString, 0, -1);
                $paramStrings[] = $paramString;
            }
        }
        $returnArray = [];
        foreach ($paramStrings as $paramString) {
            $friendships = $connection->get("friendships/lookup", ['user_id' => $paramString]);
            CoreDB::updateTwitterEndpointLogs("friendships/lookup", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode != StatusCodes::QUERY_OK) {
                return null;
            }
            foreach ($friendships as $friendship) {
                $connections = $friendship->connections;
                foreach ($connections as $connection) {
                    if (($connection == "following" || $connection == "following_requested") && $userRow['whitelistfollowings'] == "Y") {
                        $returnArray[$friendship->id] = $connection;
                    }
                }
            }
        }
        return $returnArray;
    }

    // Checks if we need to perform block or mute operations, returns an array of exclusions.
    public static function checkFriendshipsForUser($userRow, $blockIDs, $userOperationsMap) {
        if (!is_array($blockIDs)) {
            error_log("Invalid input supplied to checkFriendshipsForUser - blockIDs was not an array.");
            return null;
        }
        if (count($blockIDs) == 0) {
            return [];
        }
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(1, 1);
        $totalCount = count($blockIDs);
        $paramStrings = [];
        for ($i = 0; $i < $totalCount; $i += 100) {
            $separatedArray = array_slice($blockIDs, $i, 100);
            if (count($separatedArray) > 0) {
                $paramString = "";
                foreach ($separatedArray as $entry) {
                    $paramString .= $entry . ",";
                }
                $paramString = substr($paramString, 0, -1);
                $paramStrings[] = $paramString;
            }
        }
        $returnArray = [];
        foreach ($paramStrings as $paramString) {
            $friendships = $connection->get("friendships/lookup", ['user_id' => $blockIDs]);
            CoreDB::updateTwitterEndpointLogs("friendships/lookup", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode != StatusCodes::QUERY_OK) {
                return null;
            }
            foreach ($friendships as $friendship) {
                $connections = $friendship->connections;
                foreach ($connections as $connection) {
                    if (($connection == "following" || $connection == "following_requested") && $userRow['whitelistfollowings'] == "Y") {
                        $returnArray[$friendship->id] = $connection;
                    } else if (array_key_exists($friendship->id, $userOperationsMap) &&
                            $userOperationsMap[$friendship->id] == "Block" && $connection == "blocking") {
                        $returnArray[$friendship->id] = $connection;
                    } else if (array_key_exists($friendship->id, $userOperationsMap) &&
                            $userOperationsMap[$friendship->id] == "Mute" && $connection == "muting") {
                        $returnArray[$friendship->id] = $connection;
                    }
                }
            }
        }

        return $returnArray;
    }

    public static function getUserBlockOrMuteList($userTwitterID, $operation) {
        if ($operation !== "Block" && $operation !== "Mute") {
            error_log("Invalid operation supplied to getUserBlockOrMuteList, not retrieving.");
            return;
        }
        $selectQuery = "SELECT * FROM users WHERE twitterid=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            error_log("Could not get user to retrieve block or mute list for, returning.");
            return;
        }
        $userRow = $selectStmt->fetch();
        if (!$userRow) {
            error_log("No user found for user twitter id $userTwitterID, cannot retrieve initial block or mute list.");
            return;
        }
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $userRow['accesstoken'], $userRow['accesstokensecret']);
        $connection->setRetries(1, 1);
        $params = [];
        if ($operation == "Block") {
            $blockList = $connection->get("blocks/ids", $params);
            CoreDB::updateTwitterEndpointLogs("blocks/ids", 1);
        } else {
            $blockList = $connection->get("mutes/users/ids", $params);
            CoreDB::updateTwitterEndpointLogs("mutes/users/ids", 1);
        }
        $statusCode = Core::checkResponseHeadersForErrors($connection);
        if ($statusCode != StatusCodes::QUERY_OK) {
            error_log("Unable to retrieve block or mute list for user ID $userTwitterID!");
            return null;
        }
        $insertQuery = "INSERT IGNORE INTO userinitialblockrecords (subjectusertwitterid,objectusertwitterid,operation)"
                . " VALUES (?,?,?)";
        $usersInList = $blockList->ids;
        CoreDB::$databaseConnection->beginTransaction();
        foreach ($usersInList as $listUserID) {
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $insertStmt->execute([$userTwitterID, $listUserID, $operation]);
        }
        CoreDB::$databaseConnection->commit();
    }

    public static function CheckUserFiltersHomeTimeline($tweet, $userInfo, $phrases, $urls, $regexes) {
        $tweetUserURLs = $tweet->user->entities->url;
        if (is_array($tweetUserURLs) && count($tweetUserURLs) > 0) {
            $tweetUserURL = $tweetUserURLs[0]->expanded_url;
            $tweetUserURL = filter_var($tweetUserURL, FILTER_VALIDATE_URL);
            if ($tweetUserURL) {
                $tweetURLHost = strtolower(parse_url($tweetUserURL, PHP_URL_HOST));
            }
        }
        $tweetUserDescription = $tweet->user->description;
        $tweetText = $tweet->full_text;
        if (!$tweetText) {
            $tweetText = $tweet->text;
        }
        $tweetText = strtolower($tweetText);
        if ($userInfo['matchingphraseoperation'] == "Block" || $userInfo['matchingphraseoperation'] == "Mute") {
            foreach ($phrases as $phrase) {
                $lowerCasePhrase = strtolower($phrase['phrase']);
                if ((strpos((String) $tweetText, (String) $lowerCasePhrase) !== false) ||
                        (strpos((String) $tweetUserDescription, (String) $lowerCasePhrase) !== false)) {
                    return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                        "filtercontent" => $phrase['phrase']);
                }
            }
        }
        if ($userInfo['nftprofilepictureoperation'] == "Block" || $userInfo['nftprofilepictureoperation'] == "Mute") {
            if ($tweet->user->ext_has_nft_avatar) {
                return array("operation" => $userInfo['nftprofilepictureoperation'], "filtertype" => "nftprofilepictures", "filtercontent" => null);
            }
        }
        if ($userInfo['profileurlsoperation'] == "Block" || $userInfo['profileurlsoperation'] == "Mute") {
            foreach ($urls as $url) {
                $urlHost = strtolower(parse_url($url['url'], PHP_URL_HOST));
                if (isset($tweetURLHost) && (strpos((String) $urlHost, (String) $tweetURLHost) !== false)) {
                    return array("operation" => $userInfo['profileurlsoperation'], "filtertype" => "profileurls", "filtercontent" => $url['url']);
                }
            }
        }
        if ($userInfo['cryptousernamesoperation'] == "Block" || $userInfo['cryptousernamesoperation'] == "Mute") {
            foreach ($regexes as $regex) {
                $userName = strtolower($tweet->user->name);
                if (preg_match($regex['regex'], $userName)) {
                    return array("operation" => $userInfo['cryptousernamesoperation'], "filtertype" => "cryptousernames",
                        "filtercontent" => $regex['regex']);
                }
            }
        }
        return false;
    }

    public static function checkUserFiltersMentionTimeline($mention, $userInfo, $phrases, $urls, $regexes) {
        $mentionUserURLs = $mention->user->entities->url;
        if (is_array($mentionUserURLs) && count($mentionUserURLs) > 0) {
            $mentionUserURL = $mentionUserURLs[0]->expanded_url;
            $mentionUserURL = filter_var($mentionUserURL, FILTER_VALIDATE_URL);
            if ($mentionUserURL) {
                $mentionURLHost = strtolower(parse_url($mentionUserURL, PHP_URL_HOST));
            }
        }
        $mentionUserDescription = $mention->user->description;
        $mentionTweetText = $mention->full_text;
        if (!$mentionTweetText) {
            $mentionTweetText = $mention->text;
        }
        $mentionTweetText = strtolower($mentionTweetText);
        if ($userInfo['matchingphraseoperation'] == "Block" || $userInfo['matchingphraseoperation'] == "Mute") {
            foreach ($phrases as $phrase) {
                $lowerCasePhrase = strtolower($phrase['phrase']);
                if ((strpos((String) $mentionTweetText, (String) $lowerCasePhrase) !== false) ||
                        (strpos((String) $mentionUserDescription, (String) $lowerCasePhrase) !== false)) {
                    return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                        "filtercontent" => $phrase['phrase']);
                }
            }
        }
        if ($userInfo['nftprofilepictureoperation'] == "Block" || $userInfo['nftprofilepictureoperation'] == "Mute") {
            if ($mention->user->ext_has_nft_avatar) {
                return array("operation" => $userInfo['nftprofilepictureoperation'], "filtertype" => "nftprofilepictures", "filtercontent" => null);
            }
        }
        if ($userInfo['profileurlsoperation'] == "Block" || $userInfo['profileurlsoperation'] == "Mute") {
            foreach ($urls as $url) {
                $urlHost = strtolower(parse_url($url['url'], PHP_URL_HOST));
                if (isset($mentionURLHost) && (strpos((String) $urlHost, (String) $mentionURLHost) !== false)) {
                    return array("operation" => $userInfo['profileurlsoperation'], "filtertype" => "profileurls", "filtercontent" => $url['url']);
                }
            }
        }
        if ($userInfo['cryptousernamesoperation'] == "Block" || $userInfo['cryptousernamesoperation'] == "Mute") {
            foreach ($regexes as $regex) {
                $userName = strtolower($mention->user->name);
                if (preg_match($regex['regex'], $userName)) {
                    return array("operation" => $userInfo['cryptousernamesoperation'], "filtertype" => "cryptousernames",
                        "filtercontent" => $regex['regex']);
                }
            }
        }
        return false;
    }

    public static function processEntriesForAllUsers() {
        $selectQuery = "SELECT DISTINCT subjectusertwitterid FROM entriestoprocess";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            error_log("Could not get list of users to process entries for, terminating.");
            return;
        }
        while ($row = $selectStmt->fetch()) {
            self::processEntriesForUser($row['subjectusertwitterid']);
        }
    }

    public static function processEntriesForUser($userTwitterID) {
        $selectQuery = "SELECT * FROM entriestoprocess WHERE subjectusertwitterid=? LIMIT 50";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID]);
        if (!$success) {
            error_log("Could not get entries to process for user ID $userTwitterID, terminating.");
            return;
        }
        $accessTokenSelectQuery = "SELECT * FROM users WHERE twitterid=?";
        $accessTokenSelectStmt = CoreDB::$databaseConnection->prepare($accessTokenSelectQuery);
        $success = $accessTokenSelectStmt->execute([$userTwitterID]);
        if (!$success) {
            error_log("Could not get entries to process for user ID $userTwitterID, terminating.");
            return;
        }
        $userRow = $accessTokenSelectStmt->fetch();
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $deleteParams = [];
        $centralBlockListInsertParams = [];
        $operationRows = $selectStmt->fetchAll();
        $friendshipIDsToCheck = [];
        $userOperationsMap = [];
        foreach ($operationRows as $operationRow) {
            $friendshipIDsToCheck[] = $operationRow['objectusertwitterid'];
            $userOperationsMap[$operationRow['objectusertwitterid']] = $operationRow['operation'];
        }
        $friendshipIDsToCheck = substr($friendshipIDsToCheck, 0, -1);
        $exclusionList = Core::checkFriendshipsForUser($userRow, $friendshipIDsToCheck, $userOperationsMap);
        if ($exclusionList == null) {
            error_log("Unable to process entries for user ID $userTwitterID - could not retrieve exclusion list.");
            return;
        }
        $endpointMap = ['Block' => 'blocks/create', 'Unblock' => 'blocks/destroy', 'Mute' => 'mutes/users/create',
            'Unmute' => 'mutes/users/destroy'];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(1, 1);
        foreach ($operationRows as $row) {
            $operation = $row['operation'];
            if (array_key_exists($row['objectusertwitterid'], $exclusionList)) {
                // Whitelisted or operation already completed; remove the entry to process and perform no operation
                $deleteParams[] = $row['id'];
                continue;
            } else {
                $endpoint = $endpointMap[$operation];
                if (!$endpoint) {
                    error_log("Unknown operation value - cannot perform entry to be processed.");
                    continue;
                }
                $params['user_id'] = $row['objectusertwitterid'];
                if ($operation == 'Block' || $operation == 'Unblock') {
                    $params['skip_status'] = 'true';
                }
                $response = $connection->post($endpoint, $params);
                CoreDB::updateTwitterEndpointLogs($endpoint, 1);
                $statusCode = Core::checkResponseHeadersForErrors($connection);
                if ($statusCode != StatusCodes::QUERY_OK) {
                    $objectUserTwitterID = $row['objectusertwitterid'];
                    error_log("Unable to perform operation $operation on user ID $objectUserTwitterID on behalf of user ID $userTwitterID");
                    continue;
                }
                if (is_object($response) && isset($response->id) && ($response->id == $row['objectusertwitterid'])) {
                    $deleteParams[] = $row['id'];
                    if ($row['addtocentraldb'] == "Y") {
                        $centralBlockListInsertParams[] = [$row['objectusertwitterid'], $row['matchedfiltertype'], $row['matchedfiltercontent']];
                    }
                }
            }
        }
        CoreDB::deleteProcessedEntries($deleteParams);
        CoreDB::insertCentralBlockListEntries($centralBlockListInsertParams);
    }

}
