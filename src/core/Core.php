<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

use Antsstyle\NFTCryptoBlocker\Core\TwitterResponseStatus;
use Antsstyle\NFTCryptoBlocker\Credentials\APIKeys;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\TwitterUsers;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;
use Abraham\TwitterOAuth\TwitterOAuth;

class Core {

    public static $logger;

    public static function checkResponseForErrors($connection, $userTwitterID = null, $endpoint = null) {
        $headers = $connection->getLastXHeaders();
        $httpCode = $connection->getLastHttpCode();
        if ($httpCode != 200) {
            $requestBody = $connection->getLastBody();
            if (isset($requestBody->errors) && is_array($requestBody->errors)) {
                $error = $requestBody->errors[0];
                $errorCode = $error->code;
                $errorMessage = $error->message;
                if ($errorCode == TwitterResponseStatus::TWITTER_INVALID_ACCESS_TOKEN) {
                    Core::$logger->error("Revoking user with twitter ID: $userTwitterID, invalid access token.");
                    CoreDB::setUserRevoked("Y", $userTwitterID);
                }
                if ($errorCode == TwitterResponseStatus::TWITTER_USER_ACCOUNT_LOCKED) {
                    CoreDB::setUserLocked("Y", $userTwitterID);
                }
                if ($errorCode == TwitterResponseStatus::TWITTER_ACCOUNT_SUSPENDED) {
                    Core::$logger->error("Deleting user with twitter ID: $userTwitterID, account suspended.");
                    CoreDB::setUserRevoked("Y", $userTwitterID);
                }
                if ($httpCode == TwitterResponseStatus::HTTP_TOO_MANY_REQUESTS) {
                    Core::$logger->warning("Warning: rate limits exceeded, received error 429! User ID: $userTwitterID");
                    if (isset($headers['x_rate_limit_reset'])) {
                        $resettime = date("Y-m-d H:i:s", $headers['x_rate_limit_reset']);
                    } else {
                        $resettime = date("Y-m-d H:i:s", strtotime("+15 minutes"));
                    }
                    CoreDB::updateUserRateLimitInfo($userTwitterID, $endpoint, $resettime);
                }

                if (!(($httpCode >= 500 && $httpCode <= 599) || ($httpCode >= 200 && $httpCode <= 299))) {
                    Core::$logger->error("Response headers contained HTTP code: $httpCode, error code: $errorCode, user twitter ID: $userTwitterID. "
                            . "Response body was:");
                    Core::$logger->error(print_r($connection->getLastBody(), true));
                }
                return new TwitterResponseStatus($httpCode, $errorCode, $errorMessage);
            } else if (isset($requestBody->status) && $requestBody->status != 200) {
                if ($requestBody->status == TwitterResponseStatus::HTTP_FORBIDDEN &&
                        isset($requestBody->detail) && strpos($requestBody->detail, "Your account is temporarily locked") !== false) {
                    Core::$logger->info("User is temporarily locked by Twitter, locking here.");
                    CoreDB::setUserLocked("Y", $userTwitterID);
                }
                return new TwitterResponseStatus($httpCode, $requestBody->status, $requestBody->detail);
            }
            return new TwitterResponseStatus($httpCode, TwitterResponseStatus::NFTCRYPTOBLOCKER_QUERY_OK);
        }
        if (!isset($headers['x_rate_limit_remaining']) || !isset($headers['x_rate_limit_limit']) || !isset($headers['x_rate_limit_reset'])) {
            return new TwitterResponseStatus(200, 0);
        }
        if ($headers['x_rate_limit_remaining'] == 0) {
            if (isset($headers['x_rate_limit_reset'])) {
                $resettime = date("Y-m-d H:i:s", $headers['x_rate_limit_reset']);
            } else {
                $resettime = date("Y-m-d H:i:s", strtotime("+15 minutes"));
            }
            CoreDB::updateUserRateLimitInfo($userTwitterID, $endpoint, $resettime);
            return new TwitterResponseStatus(200, TwitterResponseStatus::NFTCRYPTOBLOCKER_RATE_LIMIT_ZERO);
        }
        return new TwitterResponseStatus(200, 0);
    }

    public static function getLoadBalanceValues($array, $numToAdd) {
        $copy = $array;
        $loadBalancedCopy = Core::loadBalanceArray($copy, $numToAdd);
        $newArray = [];
        foreach ($loadBalancedCopy as $key => $value) {
            $originalValue = $array[$key];
            $diff = $value - $originalValue;
            $newArray[$key] = $diff;
        }
        return $newArray;
    }

    public static function loadBalanceArray($array, $numToAdd) {
        if (!is_array($array)) {
            return;
        }
        if (count($array) === 0) {
            return;
        }
        $arrayCount = count($array);
        natsort($array);
        while ($numToAdd > 0) {
            reset($array);
            $minKey = 0;
            $nextKey = 0;
            while ($array[$minKey] == $array[$nextKey]) {
                $minKey = current($array);
                $minKey = key($array);
                $nextKey = next($array);
                if ($nextKey === false) {
                    break;
                }
                $nextKey = key($array);
            }

            if ($nextKey === false) {
                // All keys are equal - distribute remainder equally
                $addition = floor($numToAdd / $arrayCount);
                $remainder = $numToAdd % $arrayCount;
                $i = 0;
                foreach ($array as $key => $value) {
                    $array[$key] += $addition;
                    $numToAdd -= $addition;
                    if ($i < $remainder) {
                        $value++;
                        $i++;
                        $numToAdd--;
                    }
                }
            } else {
                $diff = $array[$nextKey] - $array[$minKey];
                $array[$minKey] += min($diff, $numToAdd);
                $numToAdd -= min($diff, $numToAdd);
            }
        }
        return $array;
    }

    public static function checkWhitelistForUser($userRow, $potentialIDs) {
        if ($userRow['whitelistfollowings'] != "Y") {
            return [];
        }
        if (!is_array($potentialIDs)) {
            Core::$logger->error("Invalid input supplied to checkWhitelistForUser - potentialIDs was not an array.");
            return null;
        }
        if (count($potentialIDs) == 0) {
            return [];
        }
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(0, 0);
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
            $twitterResponseStatus = Core::checkResponseForErrors($connection, $userRow['twitterid'], "friendships/lookup");
            if ($twitterResponseStatus->httpCode != TwitterResponseStatus::HTTP_QUERY_OK || $twitterResponseStatus->twitterCode != TwitterResponseStatus::NFTCRYPTOBLOCKER_QUERY_OK) {
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
    public static function checkFriendshipsAndExistingBlocksForUser($userRow, $blockIDs, $userOperationsMap) {
        if (!is_array($blockIDs)) {
            Core::$logger->error("Invalid input supplied to checkFriendshipsForUser - blockIDs was not an array.");
            return null;
        }
        if (count($blockIDs) === 0) {
            Core::$logger->error("Empty block IDs list!");
            return [];
        }
        $resetTime = CoreDB::getUserRateLimitRecords($userRow['twitterid'], "friendships/lookup");
        if (is_null($resetTime)) {
            Core::$logger->info("Couldn't ascertain reset time, returning.");
            return;
        }
        if ($resetTime) {
            $now = date("Y-m-d H:i:s");
            if (strtotime($resetTime) > strtotime($now)) {
                Core::$logger->info("User has 0 rate limit remaining on friendships/lookup calls, not retrieving info.");
                return false;
            }
        }
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
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(0, 0);
        foreach ($paramStrings as $paramString) {
            $friendships = $connection->get("friendships/lookup", ['user_id' => $paramString]);
            CoreDB::updateTwitterEndpointLogs("friendships/lookup", 1);
            $twitterResponseStatus = Core::checkResponseForErrors($connection, $userRow['twitterid'], "friendships/lookup");
            if ($twitterResponseStatus->httpCode !== TwitterResponseStatus::HTTP_QUERY_OK || ($twitterResponseStatus->twitterCode !== TwitterResponseStatus::NFTCRYPTOBLOCKER_QUERY_OK && $twitterResponseStatus->twitterCode !== TwitterResponseStatus::NFTCRYPTOBLOCKER_RATE_LIMIT_ZERO)) {
                Core::$logger->error("Failed to get friendships for user! HTTP code: " . $twitterResponseStatus->httpCode . " Twitter code: " . $twitterResponseStatus->twitterCode);
                Core::$logger->error("Failed to get friendships for user!" . print_r($friendships, true));
                //Core::$logger->error("Param string: $paramString");
                //Core::$logger->error("Block IDs list: " . print_r($blockIDs, true));
                Core::$logger->error("User row: " . print_r($userRow, true));
                return null;
            }
            foreach ($friendships as $friendship) {
                $userConnections = $friendship->connections;
                foreach ($userConnections as $userConnection) {
                    if (($userConnection === "following" || $userConnection === "following_requested") && $userRow['whitelistfollowings'] === "Y") {
                        $returnArray[$friendship->id] = $userConnection;
                    } else if (array_key_exists($friendship->id, $userOperationsMap) &&
                            $userOperationsMap[$friendship->id] === "Block" && $userConnection === "blocking") {
                        $returnArray[$friendship->id] = $userConnection;
                    } else if (array_key_exists($friendship->id, $userOperationsMap) &&
                            $userOperationsMap[$friendship->id] === "Mute" && $userConnection === "muting") {
                        $returnArray[$friendship->id] = $userConnection;
                    }
                }
            }
        }
        return $returnArray;
    }

    public static function updateCentralDBEntriesUserInfo($count) {
        for ($i = 0; $i <= $count; $i += 100) {
            Core::update100CentralDBEntriesUserInfo();
        }
    }

    public static function update100CentralDBEntriesUserInfo() {
        $lastCheckedDate = date("Y-m-d H:i:s", strtotime("-1 week"));
        $selectQuery = "SELECT blockableusertwitterid,markedfordeletiondate,markedfordeletion "
                . "FROM centralisedblocklist WHERE followercount IS NULL "
                . "OR lastcheckeddate < ? LIMIT 100";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$lastCheckedDate]);
        if (!$success) {
            Core::$logger->critical("Could not get central DB entries to get user info for, returning.");
            return;
        }
        if ($selectStmt->rowCount() === 0) {
            return;
        }
        $userMap = [];
        $userList = "";
        while ($row = $selectStmt->fetch()) {
            $userTwitterID = $row['blockableusertwitterid'];
            $userMap[$userTwitterID] = $row;
            $userList .= $userTwitterID;
            $userList .= ",";
        }
        $userList = substr($userList, 0, -1);
        $response = TwitterUsers::userLookup($userList);
        if ($response !== null) {
            $currentTime = date("Y-m-d H:i:s");
            $updateQuery = "UPDATE centralisedblocklist SET followercount=?, twitterhandle=?, lastcheckeddate=? WHERE blockableusertwitterid=?";
            $data = $response->data;
            $errors = $response->errors;
            $transactionErrors = 0;
            while ($transactionErrors < CoreDB::$maxTransactionRetries) {
                try {
                    CoreDB::$databaseConnection->beginTransaction();
                    if (is_array($data)) {
                        foreach ($data as $userData) {
                            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
                            $updateStmt->execute([$userData->public_metrics->followers_count, $userData->username, $currentTime, $userData->id]);
                        }
                    }
                    if (is_array($errors)) {
                        foreach ($errors as $userError) {
                            $detail = $userError->detail;
                            $userID = $userError->value;
                            $userEstablished = false;
                            $markReason = null;
                            if (strpos($detail, "User has been suspended") !== false && strpos($detail, (String) $userError->value) !== false) {
                                $userEstablished = true;
                                $markReason = "Suspended";
                            }
                            if (strpos($detail, "Could not find user with ids") !== false && strpos($detail, (String) $userError->value) !== false) {
                                $updateStmt = CoreDB::$databaseConnection->prepare("DELETE FROM centralisedblocklist WHERE blockableusertwitterid=?");
                                $updateStmt->execute([$userError->value]);
                                continue;
                            }
                            if (!$userEstablished) {
                                continue;
                            }
                            if (!array_key_exists($userID, $userMap)) {
                                continue;
                            }
                            $userRow = $userMap[$userID];
                            if (is_null($userRow['markedfordeletiondate'])) {
                                $updateStmt = CoreDB::$databaseConnection->prepare("UPDATE centralisedblocklist SET markedfordeletion=?, "
                                        . "markedfordeletiondate=?, markedfordeletionreason=?, lastcheckeddate=? WHERE blockableusertwitterid=?");
                                $updateStmt->execute(["Y", $currentTime, $markReason, $currentTime, $userError->value]);
                            } else {
                                $updateStmt = CoreDB::$databaseConnection->prepare("UPDATE centralisedblocklist SET markedfordeletion=?, "
                                        . "markedfordeletionreason=?, lastcheckeddate=? WHERE blockableusertwitterid=?");
                                $updateStmt->execute(["Y", $markReason, $currentTime, $userError->value]);
                            }
                        }
                    }
                    CoreDB::$databaseConnection->commit();
                    break;
                } catch (\Exception $e) {
                    CoreDB::$databaseConnection->rollback();
                    $transactionErrors++;
                }
            }
            if ($transactionErrors === CoreDB::$maxTransactionRetries) {
                Core::$logger->error("Error committing blocklist updates to DB: " . print_r($e, true));
            }
        } else {
            Core::$logger->error("Could not retrieve user list from API, cannot update central DB entries user info.");
        }
    }

    // Uses the user object returned for the tweet, not the tweet itself
    public static function checkFiltersForTweetSearch($userObject, $phrases, $urls, $regexes) {
        $tweetUserURLs = $userObject->entities->url;
        if (is_array($tweetUserURLs) && count($tweetUserURLs) > 0) {
            $tweetUserURL = $tweetUserURLs[0]->expanded_url;
            $tweetUserURL = filter_var($tweetUserURL, FILTER_VALIDATE_URL);
            if ($tweetUserURL) {
                $tweetURLHost = strtolower(parse_url($tweetUserURL, PHP_URL_HOST));
            }
        }
        $tweetUserDescription = $userObject->description;

        foreach ($phrases as $phrase) {
            $lowerCasePhrase = strtolower($phrase['phrase']);
            // Check entities instead of text for hashtags and cashtags
            if (strpos($lowerCasePhrase, "#") === 0) {
                if (isset($userObject->entities->description->hashtags)) {
                    $hashtagObjects = $userObject->entities->description->hashtags;
                    $phraseWithoutHash = substr($lowerCasePhrase, 1);
                    foreach ($hashtagObjects as $hashtagObject) {
                        $hashtag = $hashtagObject->tag;
                        $lowerCaseHashtag = strtolower($hashtag);
                        if ($lowerCaseHashtag === $phraseWithoutHash) {
                            return array("filtertype" => "matchingphrase",
                                "filtercontent" => $phrase['phrase']);
                        }
                    }
                }
            } else if (strpos($lowerCasePhrase, "$") === 0) {
                if (isset($userObject->entities->description->cashtags)) {
                    $cashtagObjects = $userObject->entities->description->cashtags;
                    $phraseWithoutHash = substr($lowerCasePhrase, 1);
                    foreach ($cashtagObjects as $cashtagObject) {
                        $cashtag = $cashtagObject->tag;
                        $lowerCaseHashtag = strtolower($cashtag);
                        if ($lowerCaseHashtag === $phraseWithoutHash) {
                            return array("filtertype" => "matchingphrase",
                                "filtercontent" => $phrase['phrase']);
                        }
                    }
                }
            } else if (strpos((String) $tweetUserDescription, (String) $lowerCasePhrase) !== false) {
                return array("filtertype" => "matchingphrase",
                    "filtercontent" => $phrase['phrase']);
            }
        }


        if ($userObject->ext_has_nft_avatar) {
            return array("filtertype" => "nftprofilepictures", "filtercontent" => null);
        }


        foreach ($urls as $url) {
            $urlHost = strtolower($url['url']);
            if (isset($tweetURLHost) && (strpos((String) $urlHost, (String) $tweetURLHost) !== false)) {
                return array("filtertype" => "urls", "filtercontent" => $url['url']);
            }
        }


        foreach ($regexes as $regex) {
            $userName = strtolower($userObject->name);
            if (preg_match($regex['regex'], $userName)) {
                return array("filtertype" => "cryptousernames",
                    "filtercontent" => $regex['regex']);
            }
        }

        return false;
    }

    public static function checkUserFiltersHomeTimeline($tweet, $userInfo, $phrases, $urls, $regexes) {
        $tweetURLHosts = [];
        $tweetUserURLs = $tweet->user->entities->url->urls;
        if (is_array($tweetUserURLs) && count($tweetUserURLs) > 0) {
            $userURL = $tweetUserURLs[0]->expanded_url;
            $userURL = filter_var($userURL, FILTER_VALIDATE_URL);
            if ($userURL) {
                $tweetURLHosts[] = strtolower(parse_url($userURL, PHP_URL_HOST));
            }
        }
        $tweetUserDescription = $tweet->user->description;
        $tweetUserDescriptionURLs = $tweet->user->entities->description->urls;
        if (is_array($tweetUserDescriptionURLs) && count($tweetUserDescriptionURLs) > 0) {
            foreach ($tweetUserDescriptionURLs as $descURLObject) {
                $userURL = $descURLObject->expanded_url;
                $userURL = filter_var($userURL, FILTER_VALIDATE_URL);
                if ($userURL) {
                    $tweetURLHosts[] = strtolower(parse_url($userURL, PHP_URL_HOST));
                }
            }
        }
        $tweetText = $tweet->full_text;
        if (!$tweetText) {
            $tweetText = $tweet->text;
        }
        $tweetText = strtolower($tweetText);
        if ($userInfo['matchingphraseoperation'] == "Block" || $userInfo['matchingphraseoperation'] == "Mute") {
            foreach ($phrases as $phrase) {
                $lowerCasePhrase = strtolower($phrase['phrase']);
                // Check entities instead of text for hashtags and cashtags
                if (strpos($lowerCasePhrase, "#") === 0) {
                    if (isset($tweet->user->entities->description->hashtags)) {
                        $hashtagObjects = $tweet->user->entities->description->hashtags;
                        $phraseWithoutHash = substr($lowerCasePhrase, 1);
                        foreach ($hashtagObjects as $hashtagObject) {
                            $hashtag = $hashtagObject->tag;
                            $lowerCaseHashtag = strtolower($hashtag);
                            if ($lowerCaseHashtag === $phraseWithoutHash) {
                                return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                                    "filtercontent" => $phrase['phrase']);
                            }
                        }
                    }
                } else if (strpos($lowerCasePhrase, "$") === 0) {
                    if (isset($tweet->user->entities->description->cashtags)) {
                        $cashtagObjects = $tweet->user->entities->description->cashtags;
                        $phraseWithoutHash = substr($lowerCasePhrase, 1);
                        foreach ($cashtagObjects as $cashtagObject) {
                            $cashtag = $cashtagObject->tag;
                            $lowerCaseHashtag = strtolower($cashtag);
                            if ($lowerCaseHashtag === $phraseWithoutHash) {
                                return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                                    "filtercontent" => $phrase['phrase']);
                            }
                        }
                    }
                } else if ((strpos((String) $tweetText, (String) $lowerCasePhrase) !== false) ||
                        (strpos((String) $tweetUserDescription, (String) $lowerCasePhrase) !== false)) {
                    return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                        "filtercontent" => $phrase['phrase']);
                }
            }
        }
        if ($userInfo['nftprofilepictureoperation'] == "Block" || $userInfo['nftprofilepictureoperation'] == "Mute") {
            if ($tweet->user->ext_has_nft_avatar) {
                return array("operation" => $userInfo['nftprofilepictureoperation'], "filtertype" => "nftprofilepictures",
                    "filtercontent" => $tweet->user->profile_image_url);
            }
        }
        if ($userInfo['urlsoperation'] == "Block" || $userInfo['urlsoperation'] == "Mute") {
            foreach ($urls as $url) {
                $urlHost = strtolower($url['url']);
                foreach ($tweetURLHosts as $tweetURLHost) {
                    if (isset($tweetURLHost) && (strpos((String) $urlHost, (String) $tweetURLHost) !== false)) {
                        return array("operation" => $userInfo['urlsoperation'], "filtertype" => "urls", "filtercontent" => $url['url']);
                    }
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

    public static function checkUserFiltersMentionTimelineApi11($mention, $userInfo, $phrases, $urls, $regexes) {
        $mentionURLHosts = [];
        $mentionUserURLs = $mention->user->entities->url->urls;
        if (is_array($mentionUserURLs) && count($mentionUserURLs) > 0) {
            $userURL = $mentionUserURLs[0]->expanded_url;
            $userURL = filter_var($userURL, FILTER_VALIDATE_URL);
            if ($userURL) {
                $mentionURLHosts[] = strtolower(parse_url($userURL, PHP_URL_HOST));
            }
        }
        $mentionUserDescription = $mention->user->description;
        $mentionUserDescriptionURLs = $mention->user->entities->description->urls;
        if (is_array($mentionUserDescriptionURLs) && count($mentionUserDescriptionURLs) > 0) {
            foreach ($mentionUserDescriptionURLs as $descURLObject) {
                $descURL = $descURLObject->expanded_url;
                $descURL = filter_var($descURL, FILTER_VALIDATE_URL);
                if ($descURL) {
                    $mentionURLHosts[] = strtolower(parse_url($descURL, PHP_URL_HOST));
                }
            }
        }
        $mentionTweetText = $mention->full_text;
        if (!$mentionTweetText) {
            $mentionTweetText = $mention->text;
        }
        $mentionTweetText = strtolower($mentionTweetText);
        if ($userInfo['matchingphraseoperation'] == "Block" || $userInfo['matchingphraseoperation'] == "Mute") {
            foreach ($phrases as $phrase) {
                $lowerCasePhrase = strtolower($phrase['phrase']);
                // Check entities instead of text for hashtags and cashtags
                if (strpos($lowerCasePhrase, "#") === 0) {
                    if (isset($mention->user->entities->description->hashtags)) {
                        $hashtagObjects = $mention->user->entities->description->hashtags;
                        $phraseWithoutHash = substr($lowerCasePhrase, 1);
                        foreach ($hashtagObjects as $hashtagObject) {
                            $hashtag = $hashtagObject->tag;
                            $lowerCaseHashtag = strtolower($hashtag);
                            if ($lowerCaseHashtag === $phraseWithoutHash) {
                                return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                                    "filtercontent" => $phrase['phrase']);
                            }
                        }
                    }
                } else if (strpos($lowerCasePhrase, "$") === 0) {
                    if (isset($mention->user->entities->description->cashtags)) {
                        $cashtagObjects = $mention->user->entities->description->cashtags;
                        $phraseWithoutHash = substr($lowerCasePhrase, 1);
                        foreach ($cashtagObjects as $cashtagObject) {
                            $cashtag = $cashtagObject->tag;
                            $lowerCaseHashtag = strtolower($cashtag);
                            if ($lowerCaseHashtag === $phraseWithoutHash) {
                                return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                                    "filtercontent" => $phrase['phrase']);
                            }
                        }
                    }
                } else if (strpos((String) $mentionUserDescription, (String) $lowerCasePhrase) !== false) {
                    return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                        "filtercontent" => $phrase['phrase']);
                }
            }
        }
        if ($userInfo['nftprofilepictureoperation'] == "Block" || $userInfo['nftprofilepictureoperation'] == "Mute") {
            if ($mention->user->ext_has_nft_avatar) {
                return array("operation" => $userInfo['nftprofilepictureoperation'], "filtertype" => "nftprofilepictures", "filtercontent" =>
                    $mention->user->profile_image_url_https);
            }
        }
        if ($userInfo['urlsoperation'] == "Block" || $userInfo['urlsoperation'] == "Mute") {
            foreach ($urls as $url) {
                $urlHost = strtolower($url['url']);
                foreach ($mentionURLHosts as $mentionURLHost) {
                    if (isset($mentionURLHost) && (strpos((String) $urlHost, (String) $mentionURLHost) !== false)) {
                        return array("operation" => $userInfo['urlsoperation'], "filtertype" => "profileurls", "filtercontent" => $url['url']);
                    }
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
        if ($userInfo['cryptospambotsoperation'] == "Block" || $userInfo['cryptospambotsoperation'] == "Mute") {
            $userNameEndsWith2Nums = is_numeric(substr(strtolower($mention->user->screen_name), -2));
            $createdLessThanYearAgo = strtotime($mention->user->created_at);
            $createdLessThanYearAgo = $createdLessThanYearAgo > strtotime("-1 year");
            $followersCountLowerThan20 = $mention->user->followers_count < 20;
            $friendsCountLowerThan20 = $mention->user->friends_count < 20;
            $statusesLessThan100 = $mention->user->statuses_count < 100;
            $favouritesLessThan100 = $mention->user->favourites_count < 100;
            $isReplyToExistingTweet = !is_null($mention->in_reply_to_status_id_str);
            $descriptionLessThan10 = is_null($mention->user->description) || (!is_null($mention->user->description) && strlen($mention->user->description) < 10);
            $includesLink = is_array($mention->entities->urls) && count($mention->entities->urls) > 0;
            /*if ($userInfo['usertwitterid'] == 3040755651) {
                self::$logger->error("P1: $userNameEndsWith2Nums P2: $createdLessThanYearAgo P3: $followersCountLowerThan20 P4: $friendsCountLowerThan20 P5: $statusesLessThan100 P6: $favouritesLessThan100 P7: $isReplyToExistingTweet P8: $descriptionLessThan10 P9: $includesLink Mention ID: " . $mention->id_str);
            }*/
            if ($userNameEndsWith2Nums && $createdLessThanYearAgo && $followersCountLowerThan20 && $friendsCountLowerThan20 && $statusesLessThan100 && $favouritesLessThan100 && $isReplyToExistingTweet && $descriptionLessThan10 && $includesLink) {
                return array("operation" => $userInfo['cryptospambotsoperation'], "filtertype" => "cryptospambots",
                    "filtercontent" => "conditionsmet");
            }
        }
        return false;
    }

    public static function checkUserFiltersMentionTimeline($mention, $userInfo, $phrases, $urls, $regexes) {
        $mentionURLHosts = [];
        $mentionUserURLs = $mention->mention_author->entities->url->urls;
        if (is_array($mentionUserURLs) && count($mentionUserURLs) > 0) {
            $userURL = $mentionUserURLs[0]->expanded_url;
            $userURL = filter_var($userURL, FILTER_VALIDATE_URL);
            if ($userURL) {
                $mentionURLHosts[] = strtolower(parse_url($userURL, PHP_URL_HOST));
            }
        }
        $mentionUserDescription = $mention->mention_author->description;
        $mentionUserDescriptionURLs = $mention->mention_author->entities->description->urls;
        if (is_array($mentionUserDescriptionURLs) && count($mentionUserDescriptionURLs) > 0) {
            foreach ($mentionUserDescriptionURLs as $descURLObject) {
                $descURL = $descURLObject->expanded_url;
                $descURL = filter_var($descURL, FILTER_VALIDATE_URL);
                if ($descURL) {
                    $mentionURLHosts[] = strtolower(parse_url($descURL, PHP_URL_HOST));
                }
            }
        }
        $mentionTweetText = $mention->full_text;
        if (!$mentionTweetText) {
            $mentionTweetText = $mention->text;
        }
        $mentionTweetText = strtolower($mentionTweetText);
        if ($userInfo['matchingphraseoperation'] == "Block" || $userInfo['matchingphraseoperation'] == "Mute") {
            foreach ($phrases as $phrase) {
                $lowerCasePhrase = strtolower($phrase['phrase']);
                // Check entities instead of text for hashtags and cashtags
                if (strpos($lowerCasePhrase, "#") === 0) {
                    if (isset($mention->mention_author->entities->description->hashtags)) {
                        $hashtagObjects = $mention->mention_author->entities->description->hashtags;
                        $phraseWithoutHash = substr($lowerCasePhrase, 1);
                        foreach ($hashtagObjects as $hashtagObject) {
                            $hashtag = $hashtagObject->tag;
                            $lowerCaseHashtag = strtolower($hashtag);
                            if ($lowerCaseHashtag === $phraseWithoutHash) {
                                return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                                    "filtercontent" => $phrase['phrase']);
                            }
                        }
                    }
                } else if (strpos($lowerCasePhrase, "$") === 0) {
                    if (isset($mention->mention_author->entities->description->cashtags)) {
                        $cashtagObjects = $mention->mention_author->entities->description->cashtags;
                        $phraseWithoutHash = substr($lowerCasePhrase, 1);
                        foreach ($cashtagObjects as $cashtagObject) {
                            $cashtag = $cashtagObject->tag;
                            $lowerCaseHashtag = strtolower($cashtag);
                            if ($lowerCaseHashtag === $phraseWithoutHash) {
                                return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                                    "filtercontent" => $phrase['phrase']);
                            }
                        }
                    }
                } else if (strpos((String) $mentionUserDescription, (String) $lowerCasePhrase) !== false) {
                    return array("operation" => $userInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                        "filtercontent" => $phrase['phrase']);
                }
            }
        }
        if ($userInfo['nftprofilepictureoperation'] == "Block" || $userInfo['nftprofilepictureoperation'] == "Mute") {
            if ($mention->mention_author->ext_has_nft_avatar) {
                return array("operation" => $userInfo['nftprofilepictureoperation'], "filtertype" => "nftprofilepictures", "filtercontent" => null);
            }
        }
        if ($userInfo['urlsoperation'] == "Block" || $userInfo['urlsoperation'] == "Mute") {
            foreach ($urls as $url) {
                $urlHost = strtolower($url['url']);
                foreach ($mentionURLHosts as $mentionURLHost) {
                    if (isset($mentionURLHost) && (strpos((String) $urlHost, (String) $mentionURLHost) !== false)) {
                        return array("operation" => $userInfo['urlsoperation'], "filtertype" => "profileurls", "filtercontent" => $url['url']);
                    }
                }
            }
        }
        if ($userInfo['cryptousernamesoperation'] == "Block" || $userInfo['cryptousernamesoperation'] == "Mute") {
            foreach ($regexes as $regex) {
                $userName = strtolower($mention->mention_author->name);
                if (preg_match($regex['regex'], $userName)) {
                    return array("operation" => $userInfo['cryptousernamesoperation'], "filtertype" => "cryptousernames",
                        "filtercontent" => $regex['regex']);
                }
            }
        }
        return false;
    }

    public static function processEntriesForAllUsers($pnum) {
        $selectQuery = "SELECT DISTINCT subjectusertwitterid FROM entriestoprocess WHERE pnum=? "
                . "AND subjectusertwitterid NOT IN (SELECT twitterid FROM users WHERE locked=? OR revoked=?) ORDER BY RAND() LIMIT 10000";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$pnum, "Y", "Y"]);
        if (!$success) {
            Core::$logger->critical("Could not get list of users to process entries for, terminating.");
            return;
        }
        $rowCount = $selectStmt->rowCount();
        Core::$logger->info("Processing entries for users, process number $pnum. Row count: $rowCount");
        $i = 0;
        while ($row = $selectStmt->fetch()) {
            $i++;
            if ($i % 10 === 0) {
                Core::$logger->debug("Processing entry $i of $rowCount for process number $pnum");
            }
            $invocationMap = Core::processEntriesForUser($row['subjectusertwitterid'], $pnum);
            if (!is_null($invocationMap) && is_array($invocationMap) && count($invocationMap) > 0) {
                foreach ($invocationMap as $endpoint => $count) {
                    if ($count > 0) {
                        CoreDB::updateTwitterEndpointLogs($endpoint, $count);
                    }
                }
            }
        }
    }

    public static function processEntriesForUser($userTwitterID, $pnum) {
        $selectQuery = "SELECT * FROM entriestoprocess WHERE subjectusertwitterid=? AND pnum=? LIMIT 200";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute([$userTwitterID, $pnum]);
        if (!$success) {
            Core::$logger->critical("Could not get entries to process for user ID $userTwitterID, terminating.");
            return;
        }
        $accessTokenSelectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid"
                . " WHERE twitterid=?";
        $accessTokenSelectStmt = CoreDB::$databaseConnection->prepare($accessTokenSelectQuery);
        $success = $accessTokenSelectStmt->execute([$userTwitterID]);
        if (!$success) {
            Core::$logger->critical("Could not get entries to process for user ID $userTwitterID, terminating.");
            return;
        }
        $userRow = $accessTokenSelectStmt->fetch();
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $deleteParams = [];
        $recordParams = [];
        $centralBlockListInsertParams = [];
        $centralBlockListMarkForDeletionParams = [];
        $operationRows = $selectStmt->fetchAll();
        $friendshipIDsToCheck = [];
        $userOperationsMap = [];
        $highestActionedCentralDBID = null;
        foreach ($operationRows as $operationRow) {
            $friendshipIDsToCheck[] = $operationRow['objectusertwitterid'];
            $userOperationsMap[$operationRow['objectusertwitterid']] = $operationRow['operation'];
        }
        $exclusionList = Core::checkFriendshipsAndExistingBlocksForUser($userRow, $friendshipIDsToCheck, $userOperationsMap);
        if (is_null($exclusionList)) {
            Core::$logger->error("Unable to process entries for user ID $userTwitterID - could not retrieve exclusion list.");
            return;
        }
        if (!is_array($exclusionList)) {
            return;
        }
        $rateLimitMap = ['Block' => false, 'Unblock' => false, 'Mute' => false, 'Unmute' => false];
        $endpointMap = ['Block' => 'users/:id/blocking', 'Unblock' => 'users/:source_user_id/blocking/:target_user_id',
            'Mute' => 'users/:id/muting', 'Unmute' => 'users/:source_user_id/muting/:target_user_id'];
        $invocationMap = ['users/:id/blocking' => 0, 'users/:source_user_id/blocking/:target_user_id' => 0, 'users/:id/muting' => 0,
            'users/:source_user_id/muting/:target_user_id' => 0];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setApiVersion('2');
        $connection->setRetries(0, 0);
        $totalInvocationCount = 0;
        $iterationCount = 0;
        $excludedCount = 0;
        $rateLimitCount = 0;
        foreach ($operationRows as $row) {
            $iterationCount++;
            $operation = $row['operation'];
            if (array_key_exists($row['objectusertwitterid'], $exclusionList)) {
                // Whitelisted or operation already completed; remove the entry to process and perform no operation
                $excludedCount++;
                $deleteParams[] = $row['id'];
                if ($row['addedfrom'] === "centraldb") {
                    if (is_null($highestActionedCentralDBID)) {
                        $highestActionedCentralDBID = $row['centraldbid'];
                    } else {
                        $highestActionedCentralDBID = max($highestActionedCentralDBID, $row['centraldbid']);
                    }
                }
                continue;
            } else {
                $endpoint = $endpointMap[$operation];
                if (!$endpoint) {
                    Core::$logger->error("Unknown operation value - cannot perform entry to be processed.");
                    continue;
                }
                if ($rateLimitMap[$operation] === true) {
                    $rateLimitCount++;
                    continue;
                }
                $rateLimitOK = CoreDB::verifyUserRateLimitOK($userTwitterID, $endpoint);
                if ($rateLimitOK !== true) {
                    $rateLimitCount++;
                    Core::$logger->info("Rate limit reached, not continuing.");
                    $rateLimitMap[$operation] = true;
                    continue;
                }
                try {
                    if ($endpoint === "users/:id/blocking" || $endpoint === "users/:id/muting") {
                        $params['target_user_id'] = $row['objectusertwitterid'];
                        $queryEndpoint = str_replace(":id", $userTwitterID, $endpoint);
                        $response = $connection->post($queryEndpoint, $params, true);
                    } else if ($endpoint === "users/:source_user_id/blocking/:target_user_id" || $endpoint === "users/:source_user_id/muting/:target_user_id") {
                        $queryEndpoint = str_replace(":source_user_id", $userTwitterID, $endpoint);
                        $queryEndpoint = str_replace(":target_user_id", $row['objectusertwitterid'], $queryEndpoint);
                        $response = $connection->delete($queryEndpoint);
                    } else {
                        Core::$logger->critical("Invalid endpoint - cannot process entries for user!");
                        break;
                    }
                } catch (\Exception $e) {
                    CoreDB::$logger->error("Exception: " . print_r($e, true));
                    continue;
                }
                $invocationMap[$endpointMap[$operation]] = $invocationMap[$endpointMap[$operation]] + 1;
                $totalInvocationCount++;
                $twitterResponseStatus = Core::checkResponseForErrors($connection, $userTwitterID, $endpoint);
                $message = $twitterResponseStatus->message;
                if ($twitterResponseStatus->httpCode !== TwitterResponseStatus::HTTP_QUERY_OK || !is_null($message)) {
                    $objectUserTwitterID = $row['objectusertwitterid'];
                    if (strpos($message, "that is not active") !== false) {
                        Core::$logger->info("User with ID $objectUserTwitterID not found - cannot process entry, deleting.");
                        $deleteParams[] = $row['id'];
                        $centralBlockListMarkForDeletionParams[] = $objectUserTwitterID;
                    } else if (strpos($message, "that is suspended") !== false) {
                        Core::$logger->info("User with ID $objectUserTwitterID is suspended - cannot process entry, deleting.");
                        $deleteParams[] = $row['id'];
                        $centralBlockListMarkForDeletionParams[] = $objectUserTwitterID;
                    } else if ($twitterResponseStatus->twitterCode === TwitterResponseStatus::TWITTER_USER_ALREADY_UNMUTED) {
                        Core::$logger->info("User with ID $objectUserTwitterID is already unmuted, deleting entry.");
                        $deleteParams[] = $row['id'];
                    } else if ($twitterResponseStatus->httpCode === TwitterResponseStatus::HTTP_TOO_MANY_REQUESTS) {
                        Core::$logger->info("User $userTwitterID over rate limit - skipping further operations.");
                        break;
                    } else if ($twitterResponseStatus->httpCode === TwitterResponseStatus::HTTP_FORBIDDEN) {
                        $resp = TwitterUsers::checkUserObjectValid(["accesstoken" => $accessToken, "accesstokensecret" => $accessTokenSecret], $userTwitterID);
                        if ($resp->httpCode === TwitterResponseStatus::HTTP_FORBIDDEN || $resp->httpCode === TwitterResponseStatus::HTTP_BAD_CREDENTIALS || $resp->httpCode === TwitterResponseStatus::HTTP_NOT_FOUND) {
                            Core::$logger->error("Locking user ID $userTwitterID - cannot identify user auth.");
                            CoreDB::setUserLocked("Y", $userTwitterID);
                            break;
                        }
                    } else {
                        Core::$logger->error("Unable to perform operation $operation on user ID $objectUserTwitterID on behalf of user ID $userTwitterID");
                        Core::$logger->error("Twitter response status: " . print_r($twitterResponseStatus, true));
                        Core::$logger->error("Request body: " . print_r($response, true));
                    }
                    continue;
                }
                if (is_object($response) && $response->data->blocking == true) {
                    $deleteParams[] = $row['id'];
                    if ($row['addtocentraldb'] == "Y") {
                        $centralBlockListInsertParams[] = [$row['objectusertwitterid'], $row['matchedfiltertype'], $row['matchedfiltercontent'],
                            $row['addedfrom'], $row['matchedfiltertype'], $row['matchedfiltercontent'], $row['addedfrom']];
                    }
                    $recordParams[] = $row;
                    if ($row['addedfrom'] === "centraldb") {
                        if (is_null($highestActionedCentralDBID)) {
                            $highestActionedCentralDBID = $row['centraldbid'];
                        } else {
                            $highestActionedCentralDBID = max($highestActionedCentralDBID, $row['centraldbid']);
                        }
                    }
                }
            }
        }
        /* Core::$logger->error("It count: $iterationCount. Op count: " . count($operationRows) . " Del count: " . count($deleteParams)
          . " Rlimit count: $rateLimitCount Excl count: $excludedCount"); */
        CoreDB::deleteProcessedEntries($deleteParams, $pnum);
        CoreDB::insertCentralBlockListEntries($centralBlockListInsertParams);
        if (!is_null($highestActionedCentralDBID)) {
            CoreDB::updateUserHighestActionedCentralDBID($userTwitterID, $highestActionedCentralDBID, $centralBlockListInsertParams);
        }
        CoreDB::markCentralBlockListEntriesForDeletion($centralBlockListMarkForDeletionParams);
        CoreDB::updateUserBlockRecords($recordParams);
        return $invocationMap;
    }

}

Core::$logger = LogManager::getLogger("Core");
