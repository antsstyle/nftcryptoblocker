<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

use Antsstyle\NFTCryptoBlocker\Credentials\APIKeys;
use Abraham\TwitterOAuth\TwitterOAuth;
use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

class TwitterTimelines {

    public static $logger;

    public static function checkHomeTimelineForAllUsers() {
        $currentTime = date("Y-m-d H:i:s");
        $selectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid"
                . " WHERE locked=? AND revoked=? AND (hometimelinenextcheckdate IS NULL OR hometimelinenextcheckdate <= ?)"
                . " AND (matchingphraseoperation != 'Noaction' OR nftprofilepictureoperation != 'Noaction' OR "
                . "urlsoperation != 'Noaction' OR cryptousernamesoperation != 'Noaction')";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute(["N", "N", $currentTime]);
        if (!$success) {
            TwitterTimelines::$logger->critical("Could not get users to retrieve all home timeline entries for, returning.");
            return;
        }
        $phrases = CoreDB::getBlockablePhrases();
        $urls = CoreDB::getBlockableURLs();
        $regexes = CoreDB::getBlockableUsernameRegexes();
        if (!$phrases || !$urls || !$regexes) {
            TwitterTimelines::$logger->critical("Could not retrieve filters for user mentions, returning.");
            return;
        }
        $invocationCount = 0;
        while ($userRow = $selectStmt->fetch()) {
            $invocationCount += TwitterTimelines::checkHomeTimelineForUser($userRow, $phrases, $urls, $regexes);
        }
        CoreDB::updateTwitterEndpointLogs("statuses/home_timeline", $invocationCount);
    }

    public static function checkHomeTimelineForUser($userRow, $phrases, $urls, $regexes) {
        $params['tweet_mode'] = "extended";
        $params['count'] = 200;
        $params['include_ext_has_nft_avatar'] = true;
        $lowestMaxID = null;
        $highestSinceID = 0;
        if ($userRow['hometimelineendreached'] == "Y" && $userRow['hometimelinesinceid']) {
            $params['since_id'] = $userRow['hometimelinesinceid'];
            $highestSinceID = $userRow['hometimelinesinceid'];
        } else if ($userRow['hometimelineendreached'] == "N" && $userRow['hometimelinemaxid']) {
            $params['max_id'] = $userRow['hometimelinemaxid'];
            $lowestMaxID = $userRow['hometimelinemaxid'];
        }
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $insertParams = [];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(0, 0);
        $tweetCount = 1;
        $resultCount = 0;
        $endReached = false;
        $invocationCount = 0;
        while ($tweetCount > 0 && $invocationCount < 14) {
            try {
                $tweets = $connection->get("statuses/home_timeline", $params);
                $invocationCount++;
            } catch (\Exception $e) {
                TwitterTimelines::$logger->error("TwitterOAuth failed to get a response: " . print_r($e, true));
                continue;
            }
            $twitterResponseStatus = Core::checkResponseForErrors($connection, $userRow['twitterid'], "statuses/home_timeline");
            if ($twitterResponseStatus->httpCode !== TwitterResponseStatus::HTTP_QUERY_OK || $twitterResponseStatus->twitterCode !== TwitterResponseStatus::NFTCRYPTOBLOCKER_QUERY_OK) {
                if ($twitterResponseStatus->httpCode === 429) {
                    TwitterTimelines::$logger->critical("Rate limit exceeded in home timeline! "
                            . "Invocation count upon rate limit exceeded was: $invocationCount");
                }
                break;
            }
            $tweetCount = count($tweets);
            if ($tweetCount == 0) {
                $endReached = true;
                break;
            }
            $resultCount += $tweetCount;
            foreach ($tweets as $tweet) {
                if (!is_null($userRow['hometimelinesinceid']) && ($tweet->id <= $userRow['hometimelinesinceid'])) {
                    $foundAllNewResults = true;
                    break;
                }
                // no need to examine mentions by the user to themselves
                if ($tweet->user->id == $userRow['usertwitterid']) {
                    $tweetID = $tweet->id;
                    if ($tweetID > $highestSinceID) {
                        $highestSinceID = $tweetID;
                    }
                    if (is_null($lowestMaxID) || ($tweetID < $lowestMaxID)) {
                        $lowestMaxID = $tweetID - 1;
                    }
                    continue;
                }
                // if the user retweeted a status, we check the original status, not the retweet
                if ($tweet->retweeted_status) {
                    $tweet = $tweet->retweeted_status;
                }
                $filtersMatched = Core::checkUserFiltersHomeTimeline($tweet, $userRow, $phrases, $urls, $regexes);
                if ($filtersMatched) {
                    TwitterTimelines::$logger->info("Filters matched for home timeline, filters array:");
                    TwitterTimelines::$logger->info(print_r($filtersMatched, true));
                    $userID = $tweet->user->id;
                    TwitterTimelines::$logger->info("Filters matched for home timeline, object user ID: $userID");
                    if ($filtersMatched['operation'] == "Block") {
                        $insertParams[] = [$userRow['usertwitterid'], $tweet->user->id, "Block", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y", "statuses/home_timeline"];
                    } else if ($filtersMatched['operation'] == "Mute") {
                        $insertParams[] = [$userRow['usertwitterid'], $tweet->user->id, "Mute", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y", "statuses/home_timeline"];
                    } else {
                        $userOp = $filtersMatched['operation'];
                        TwitterTimelines::$logger->error("Unrecognised user automation operation, text was: $userOp");
                    }
                }
                $tweetID = $tweet->id;
                if ($tweetID > $highestSinceID) {
                    $highestSinceID = $tweetID;
                }
                if (is_null($lowestMaxID) || ($tweetID < $lowestMaxID)) {
                    $lowestMaxID = $tweetID - 1;
                }
            }
            if ($userRow['hometimelineendreached'] == "Y") {
                $params['since_id'] = $highestSinceID;
            } else {
                $params['max_id'] = $lowestMaxID;
            }
            if ($foundAllNewResults === true) {
                break;
            }
        }
        $transactionErrors = 0;
        while ($transactionErrors < CoreDB::$maxTransactionRetries) {
            try {
                CoreDB::$databaseConnection->beginTransaction();
                TwitterTimelines::updateLastHomeTimelineChecked($userRow, $lowestMaxID, $highestSinceID, $endReached, $resultCount);
                $insertQuery = "INSERT IGNORE INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,"
                        . "matchedfiltertype,matchedfiltercontent,addtocentraldb,addedfrom) VALUES (?,?,?,?,?,?,?)";

                foreach ($insertParams as $insertParamsForUser) {
                    $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
                    $insertStmt->execute($insertParamsForUser);
                }
                CoreDB::$databaseConnection->commit();
                break;
            } catch (\Exception $e) {
                CoreDB::$databaseConnection->rollback();
                $transactionErrors++;
            }
        }
        if ($transactionErrors === CoreDB::$maxTransactionRetries) {
            TwitterTimelines::$logger->critical("Ffailed to commit entries to process to DB: " . print_r($e, true));
        }

        return $invocationCount;
    }

    public static function updateLastHomeTimelineChecked($userRow, $maxID, $sinceID, $endReached, $resultCount) {
        $lastChecked = date("Y-m-d H:i:s");
        $resultCountRatio = max(floor($resultCount / 80), 1);
        $hoursToNextCheck = ceil(24 / $resultCountRatio);
        $nextCheckDate = date("Y-m-d H:i:s", strtotime("+$hoursToNextCheck hours"));
        if ($endReached && ($userRow['hometimelineendreached'] == "N")) {
            $updateQuery = "UPDATE users SET hometimelinemaxid=?, hometimelineendreached=?, hometimelinelastchecked=?, "
                    . "hometimelinelastresultcount=?, hometimelinenextcheckdate=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$maxID, "Y", $lastChecked, $resultCount, $nextCheckDate, $userRow['usertwitterid']]);
        } else if ($userRow['hometimelineendreached'] == "N") {
            $updateQuery = "UPDATE users SET hometimelinemaxid=?, hometimelinelastchecked=?, "
                    . "hometimelinelastresultcount=?, hometimelinenextcheckdate=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$maxID, $lastChecked, $resultCount, $nextCheckDate, $userRow['usertwitterid']]);
        } else {
            $updateQuery = "UPDATE users SET hometimelinesinceid=?, hometimelinelastchecked=?, "
                    . "hometimelinelastresultcount=?, hometimelinenextcheckdate=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$sinceID, $lastChecked, $resultCount, $nextCheckDate, $userRow['usertwitterid']]);
        }
    }

    public static function checkMentionsTimelineForAllUsers() {
        $currentTime = date("Y-m-d H:i:s");
        $selectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid"
                . " WHERE locked=? AND revoked=? AND (mentionstimelinenextcheckdate IS NULL OR mentionstimelinenextcheckdate <= ?)"
                . " AND (matchingphraseoperation != 'Noaction' OR nftprofilepictureoperation != 'Noaction' OR "
                . "urlsoperation != 'Noaction' OR cryptousernamesoperation != 'Noaction' OR cryptospambotsoperation != 'Noaction')";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute(["N", "N", $currentTime]);
        if (!$success) {
            TwitterTimelines::$logger->critical("Could not get users to retrieve all mentions for, returning.");
            return;
        }
        $phrases = CoreDB::getBlockablePhrases();
        $urls = CoreDB::getBlockableURLs();
        $regexes = CoreDB::getBlockableUsernameRegexes();
        if (!$phrases || !$urls || !$regexes) {
            TwitterTimelines::$logger->critical("Could not retrieve filters for user mentions, returning.");
            return;
        }
        $mentionInvocationCount = 0;
        while ($userRow = $selectStmt->fetch()) {
            //$mentionInvocationCount += TwitterTimelines::checkMentionsTimelineForUser($userRow, $phrases, $urls, $regexes);
            $mentionInvocationCount += TwitterTimelines::checkMentionsTimelineForUserApi11($userRow, $phrases, $urls, $regexes);
        }
        //CoreDB::updateTwitterEndpointLogs("users/:id/mentions", $mentionInvocationCount);
        CoreDB::updateTwitterEndpointLogs("statuses/mentions_timeline", $mentionInvocationCount);
    }

    public static function checkMentionsTimelineForUserApi11($userRow, $phrases, $urls, $regexes) {
        $highestSinceID = 0;
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $insertParams = [];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(0, 0);
        $endpointInvocationCount = 0;
        $queryParams['tweet_mode'] = "extended";
        $queryParams['count'] = 200;
        $queryParams['include_ext_has_nft_avatar'] = true;
        if (!is_null($userRow['mentionstimelinesinceid'])) {
            $queryParams['since_id'] = $userRow['mentionstimelinesinceid'];
        }

        try {
            $response = $connection->get("statuses/mentions_timeline", $queryParams);
            $endpointInvocationCount++;
        } catch (\Exception $e) {
            TwitterTimelines::$logger->error("TwitterOAuth failed to get a response. " . print_r($e, true));
            return $endpointInvocationCount;
        }
        $twitterResponseStatus = Core::checkResponseForErrors($connection, $userRow['twitterid'], "statuses/mentions_timeline");
        if ($twitterResponseStatus->httpCode != TwitterResponseStatus::HTTP_QUERY_OK || $twitterResponseStatus->twitterCode != TwitterResponseStatus::NFTCRYPTOBLOCKER_QUERY_OK) {
            TwitterTimelines::updateLastMentionTimelineChecked($userRow['usertwitterid'], $highestSinceID, 0);
            return $endpointInvocationCount;
        }
        $resultCount = count($response);
        if (count($response) === 0) {
            TwitterTimelines::updateLastMentionTimelineChecked($userRow['usertwitterid'], $highestSinceID, $resultCount);
            return $endpointInvocationCount;
        }

        foreach ($response as $mention) {
            $mentionID = $mention->id;
            if (!is_null($userRow['mentionstimelinesinceid']) && ($mentionID <= $userRow['mentionstimelinesinceid'])) {
                break;
            }

            // check description, profile picture: add block to entries to process if match found
            $filtersMatched = Core::checkUserFiltersMentionTimelineApi11($mention, $userRow, $phrases, $urls, $regexes);
            if ($filtersMatched) {
                TwitterTimelines::$logger->info("Filters matched for mentions timeline, filters array:");
                TwitterTimelines::$logger->info(print_r($filtersMatched, true));
                $mentionAuthorID = $mention->user->id;
                TwitterTimelines::$logger->info("Filters matched for mentions timeline, object user ID: $mentionAuthorID");
                if ($filtersMatched['operation'] == "Block") {
                    $insertParams[] = [$userRow['usertwitterid'], $mentionAuthorID, "Block", $filtersMatched['filtertype'],
                        $filtersMatched['filtercontent'], "Y", "statuses/mentions_timeline"];
                    // Add to entries to process along with reason information
                } else if ($filtersMatched['operation'] == "Mute") {
                    $insertParams[] = [$userRow['usertwitterid'], $mentionAuthorID, "Mute", $filtersMatched['filtertype'],
                        $filtersMatched['filtercontent'], "Y", "statuses/mentions_timeline"];
                    // Add to entries to process along with reason information
                } else {
                    $userOp = $filtersMatched['operation'];
                    TwitterTimelines::$logger->error("Unrecognised user automation operation, text was: $userOp");
                }
            }
            $tweetID = $mention->id;
            if ($tweetID > $highestSinceID) {
                $highestSinceID = $tweetID;
            }
        }

        $transactionErrors = 0;
        while ($transactionErrors < CoreDB::$maxTransactionRetries) {
            try {
                CoreDB::$databaseConnection->beginTransaction();
                TwitterTimelines::updateLastMentionTimelineChecked($userRow['usertwitterid'], $highestSinceID, $resultCount);
                $insertQuery = "INSERT IGNORE INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,"
                        . "matchedfiltertype,matchedfiltercontent,addtocentraldb,addedfrom) VALUES (?,?,?,?,?,?,?)";

                foreach ($insertParams as $insertParamsForUser) {
                    $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
                    $insertStmt->execute($insertParamsForUser);
                }
                CoreDB::$databaseConnection->commit();
                break;
            } catch (\Exception $e) {
                CoreDB::$databaseConnection->rollback();
                $transactionErrors++;
            }
        }
        if ($transactionErrors === CoreDB::$maxTransactionRetries) {
            TwitterTimelines::$logger->critical("Failed to commit entries to process to DB: " . print_r($e, true));
        }

        return $endpointInvocationCount;
    }

    public static function updateLastMentionTimelineChecked($userTwitterID, $sinceID, $resultCount) {
        $lastChecked = date("Y-m-d H:i:s");
        $resultCountRatio = max(floor($resultCount / 80), 1);
        $hoursToNextCheck = ceil(24 / $resultCountRatio);
        $nextCheckDate = date("Y-m-d H:i:s", strtotime("+$hoursToNextCheck hours"));
        if ($sinceID !== 0) {
            $updateQuery = "UPDATE users SET mentionstimelinesinceid=?, mentionstimelinelastchecked=?, "
                    . "mentionstimelinelastresultcount=?, mentionstimelinenextcheckdate=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$sinceID, $lastChecked, $resultCount, $nextCheckDate, $userTwitterID]);
        } else {
            $updateQuery = "UPDATE users SET mentionstimelinelastchecked=?, mentionstimelinelastresultcount=?, "
                    . "mentionstimelinenextcheckdate=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$lastChecked, $resultCount, $nextCheckDate, $userTwitterID]);
        }
    }

    public static function checkMentionsTimelineForUser($userRow, $phrases, $urls, $regexes) {
        $highestSinceID = 0;
        if ($userRow['mentionstimelineendreached'] == "Y" && $userRow['mentionstimelinesinceid']) {
            $queryParams['since_id'] = $userRow['mentionstimelinesinceid'];
            $highestSinceID = $userRow['mentionstimelinesinceid'];
        } else if ($userRow['mentionstimelineendreached'] == "N" && $userRow['mentionstimelinepaginationtoken']) {
            $queryParams['pagination_token'] = $userRow['mentionstimelinepaginationtoken'];
        }
        $queryParams['max_results'] = 100;
        $queryParams['expansions'] = "author_id";
        $queryParams['user.fields'] = "description,entities,id,name,profile_image_url,url,username";
        $queryParams['tweet.fields'] = "entities,text,in_reply_to_user_id";
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $insertParams = [];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setApiVersion('2');
        $connection->setRetries(0, 0);
        $mentionCount = 1;
        $endReached = false;
        $endpointInvocationCount = 0;
        while ($mentionCount > 0) {
            $query = "users/" . $userRow['usertwitterid'] . "/mentions";
            try {
                $response = $connection->get($query, $queryParams);
                $endpointInvocationCount++;
            } catch (\Exception $e) {
                TwitterTimelines::$logger->error("TwitterOAuth failed to get a response. " . print_r($e, true));
                continue;
            }
            $twitterResponseStatus = Core::checkResponseForErrors($connection, $userRow['twitterid'], "users/:id/mentions");
            if ($twitterResponseStatus->httpCode != TwitterResponseStatus::HTTP_QUERY_OK || $twitterResponseStatus->twitterCode != TwitterResponseStatus::NFTCRYPTOBLOCKER_QUERY_OK) {
                break;
            }
            if (!isset($response->data)) {
                break;
            }
            $mentions = $response->data;
            $totalMentions = count($mentions);
            if ($totalMentions == 0) {
                break;
            }
            $includesUsers = $response->includes->users;
            $metaInfo = $response->meta;
            $includesUsersMap = [];
            foreach ($includesUsers as $includesUser) {
                $includesUsersMap[$includesUser->id] = $includesUser;
            }

            for ($i = 0; $i < $totalMentions; $i++) {
                $mention = $mentions[$i];
                $mentionID = $mention->id;
                if (!is_null($userRow['mentionstimelinesinceid']) && ($mentionID <= $userRow['mentionstimelinesinceid'])) {
                    $foundAllNewResults = true;
                    break;
                }
                $authorID = $mention->author_id;
                if (!array_key_exists($authorID, $includesUsersMap)) {
                    TwitterTimelines::$logger->error("Author ID $authorID wasn't found in the includes users map.");
                    $tweetID = $mention->id;
                    if ($tweetID > $highestSinceID) {
                        $highestSinceID = $tweetID;
                    }
                    continue;
                }
                $mention->mention_author = $includesUsersMap[$mention->author_id];
                // check description, profile picture: add block to entries to process if match found
                $filtersMatched = Core::checkUserFiltersMentionTimeline($mention, $userRow, $phrases, $urls, $regexes);
                if ($filtersMatched) {
                    TwitterTimelines::$logger->info("Filters matched for mentions timeline, filters array:");
                    TwitterTimelines::$logger->info(print_r($filtersMatched, true));
                    $mentionAuthorID = $mention->author_id;
                    TwitterTimelines::$logger->info("Filters matched for mentions timeline, object user ID: $mentionAuthorID");
                    if ($filtersMatched['operation'] == "Block") {
                        $insertParams[] = [$userRow['usertwitterid'], $mentionAuthorID, "Block", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y", "users/:id/mentions"];
                        // Add to entries to process along with reason information
                    } else if ($filtersMatched['operation'] == "Mute") {
                        $insertParams[] = [$userRow['usertwitterid'], $mentionAuthorID, "Mute", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y", "users/:id/mentions"];
                        // Add to entries to process along with reason information
                    } else {
                        $userOp = $filtersMatched['operation'];
                        TwitterTimelines::$logger->error("Unrecognised user automation operation, text was: $userOp");
                    }
                }
                $tweetID = $mention->id;
                if ($tweetID > $highestSinceID) {
                    $highestSinceID = $tweetID;
                }
            }
            if (!isset($metaInfo->next_token) && ($userRow['mentionstimelineendreached'] == "N")) {
                $endReached = true;
                break;
            }
            if ($userRow['mentionstimelineendreached'] == "Y") {
                $queryParams['since_id'] = $highestSinceID;
            } else {
                $queryParams['pagination_token'] = $metaInfo->next_token;
            }
            if ($foundAllNewResults === true) {
                $endReached = true;
                break;
            }
        }
        $transactionErrors = 0;
        while ($transactionErrors < CoreDB::$maxTransactionRetries) {
            try {
                CoreDB::$databaseConnection->beginTransaction();
                if ($endReached && ($userRow['mentionstimelineendreached'] == "N")) {
                    if (!isset($queryParams['pagination_token'])) {
                        $queryParams['pagination_token'] = null;
                    }
                    $updateQuery = "UPDATE users SET mentionstimelineendreached=?, mentionstimelinepaginationtoken=? WHERE twitterid=?";
                    $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
                    $updateStmt->execute(["Y", $queryParams['pagination_token'], $userRow['usertwitterid']]);
                } else if ($userRow['mentionstimelineendreached'] == "N") {
                    $updateQuery = "UPDATE users SET mentionstimelinepaginationtoken=? WHERE twitterid=?";
                    $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
                    $updateStmt->execute([$queryParams['pagination_token'], $userRow['usertwitterid']]);
                } else if (isset($highestSinceID)) {
                    $updateQuery = "UPDATE users SET mentionstimelinesinceid=? WHERE twitterid=?";
                    $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
                    $updateStmt->execute([$highestSinceID, $userRow['usertwitterid']]);
                }
                $insertQuery = "INSERT IGNORE INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,"
                        . "matchedfiltertype,matchedfiltercontent,addtocentraldb,addedfrom) VALUES (?,?,?,?,?,?,?)";

                foreach ($insertParams as $insertParamsForUser) {
                    $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
                    $insertStmt->execute($insertParamsForUser);
                }
                CoreDB::$databaseConnection->commit();
                break;
            } catch (\Exception $e) {
                CoreDB::$databaseConnection->rollback();
                $transactionErrors++;
            }
        }
        if ($transactionErrors === CoreDB::$maxTransactionRetries) {
            TwitterTimelines::$logger->critical("Failed to commit entries to process to DB: " . print_r($e, true));
        }
        return $endpointInvocationCount;
    }

}

TwitterTimelines::$logger = LogManager::getLogger("TwitterTimelines");
