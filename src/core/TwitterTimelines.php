<?php

namespace Antsstyle\NFTArtistBlocker\Core;

use Antsstyle\NFTArtistBlocker\Credentials\APIKeys;
use Abraham\TwitterOAuth\TwitterOAuth;
use Antsstyle\NFTArtistBlocker\Core\Core;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;

class TwitterTimelines {

    public static function checkHomeTimelineForAllUsers() {
        $selectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            error_log("Could not get users to retrieve all home timeline entries for, returning.");
            return;
        }
        $phrases = CoreDB::getBlockablePhrases();
        $urls = CoreDB::getBlockableURLs();
        $regexes = CoreDB::getBlockableUsernameRegexes();
        if (!$phrases || !$urls || !$regexes) {
            error_log("Could not retrieve filters for user mentions, returning.");
            return;
        }
        while ($userRow = $selectStmt->fetch()) {
            self::checkHomeTimelineForUser($userRow, $phrases, $urls, $regexes);
        }
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
        $connection->setRetries(1, 1);
        $tweetCount = 1;
        $endReached = false;
        while ($tweetCount > 0) {
            $tweets = $connection->get("statuses/home_timeline", $params);
            CoreDB::updateTwitterEndpointLogs("statuses/home_timeline", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode != StatusCodes::QUERY_OK) {
                break;
            }
            $tweetCount = count($tweets);
            if ($tweetCount == 0) {
                $endReached = true;
                break;
            }
            foreach ($tweets as $tweet) {
                // no need to examine mentions by the user to themselves
                if ($tweet->user->id == $userRow['usertwitterid']) {
                    $tweetID = $tweet->id;
                    if ($tweetID > $highestSinceID) {
                        $highestSinceID = $tweetID;
                    }
                    if (($lowestMaxID == null) || ($tweetID < $lowestMaxID)) {
                        $lowestMaxID = $tweetID - 1;
                    }
                    continue;
                }
                $filtersMatched = Core::checkUserFiltersHomeTimeline($tweet, $userRow, $phrases, $urls, $regexes);
                if ($filtersMatched) {
                    error_log("Filters matched, filters array:");
                    error_log(print_r($filtersMatched, true));
                    error_log("Filters matches, object user: ");
                    error_log(print_r($tweet->user, true));
                    if ($filtersMatched['operation'] == "Block") {
                        $updateParams[] = [$userRow['usertwitterid'], $tweet->user->id, "Block", $filtersMatched['filtername'],
                            $filtersMatched['filtercontent']];
                    } else if ($filtersMatched['operation'] == "Mute") {
                        $updateParams[] = [$userRow['usertwitterid'], $tweet->user->id, "Mute", $filtersMatched['filtername'],
                            $filtersMatched['filtercontent']];
                    } else {
                        $userOp = $filtersMatched['operation'];
                        error_log("Unrecognised user automation operation, text was: $userOp");
                    }
                }
                $tweetID = $tweet->id;
                if ($tweetID > $highestSinceID) {
                    $highestSinceID = $tweetID;
                }
                if (($lowestMaxID == null) || ($tweetID < $lowestMaxID)) {
                    $lowestMaxID = $tweetID - 1;
                }
            }
            if ($userRow['hometimelineendreached'] == "Y") {
                $params['since_id'] = $highestSinceID;
            } else {
                $params['max_id'] = $lowestMaxID;
            }
        }
        // Update since_id in DB
        if ($endReached && ($userRow['hometimelineendreached'] == "N")) {
            $updateQuery = "UPDATE users SET hometimelinemaxid=?, hometimelineendreached=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$lowestMaxID, "Y", $userRow['usertwitterid']]);
        } else if ($userRow['hometimelineendreached'] == "N") {
            $updateQuery = "UPDATE users SET hometimelinemaxid=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$lowestMaxID, $userRow['usertwitterid']]);
        } else {
            $updateQuery = "UPDATE users SET hometimelinesinceid=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$highestSinceID, $userRow['usertwitterid']]);
        }
        $insertQuery = "INSERT IGNORE INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,"
                . "matchedfiltername,matchedfiltercontent) VALUES (?,?,?,?,?)";
        CoreDB::$databaseConnection->beginTransaction();
        foreach ($insertParams as $insertParamsForUser) {
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $insertStmt->execute($insertParamsForUser);
        }
        CoreDB::$databaseConnection->commit();
    }

    public static function checkMentionsTimelineForAllUsers() {
        $selectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute();
        if (!$success) {
            error_log("Could not get users to retrieve all mentions for, returning.");
            return;
        }
        $phrases = CoreDB::getBlockablePhrases();
        $urls = CoreDB::getBlockableURLs();
        $regexes = CoreDB::getBlockableUsernameRegexes();
        if (!$phrases || !$urls || !$regexes) {
            error_log("Could not retrieve filters for user mentions, returning.");
            return;
        }
        while ($userRow = $selectStmt->fetch()) {
            self::checkMentionsTimelineForUser($userRow, $phrases, $urls, $regexes);
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
        $queryParams['tweet.fields'] = "entities,text,in_reply_to_user_id";
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $insertParams = [];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setApiVersion('2');
        $connection->setRetries(1, 1);
        $mentionCount = 1;
        $endReached = false;
        while ($mentionCount > 0) {
            $query = "users/" . $userRow['usertwitterid'] . "/mentions";
            $response = $connection->get($query, $queryParams);
            CoreDB::updateTwitterEndpointLogs("users/:id/mentions", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode != StatusCodes::QUERY_OK) {
                break;
            }
            if (!isset($response->data)) {
                break;
            }
            $mentions = $response->data;
            $metaInfo = $response->meta;
            $userTwitterID = $userRow['usertwitterid'];
            error_log("User twitter ID: $userTwitterID");
            $mentionCount = count($mentions);
            error_log("Mention count: $mentionCount");
            foreach ($mentions as $mention) {
                // check description, profile picture: add block to entries to process if match found
                $filtersMatched = Core::checkUserFiltersMentionTimeline($mention, $userRow, $phrases, $urls, $regexes);
                if ($filtersMatched) {
                    error_log("Filters matched, filters array:");
                    error_log(print_r($filtersMatched, true));
                    error_log("Filters matches, object user: ");
                    error_log(print_r($mention->user, true));
                    if ($filtersMatched['operation'] == "Block") {
                        $insertParams[] = [$userRow['usertwitterid'], $mention->user->id, "Block", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y"];
                        // Add to entries to process along with reason information
                    } else if ($filtersMatched['operation'] == "Mute") {
                        $insertParams[] = [$userRow['usertwitterid'], $mention->user->id, "Mute", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y"];
                        // Add to entries to process along with reason information
                    } else {
                        $userOp = $filtersMatched['operation'];
                        error_log("Unrecognised user automation operation, text was: $userOp");
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
        }

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
                . "matchedfiltertype,matchedfiltercontent,addtocentraldb) VALUES (?,?,?,?,?,?)";
        CoreDB::$databaseConnection->beginTransaction();
        foreach ($insertParams as $insertParamsForUser) {
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $insertStmt->execute($insertParamsForUser);
        }
        CoreDB::$databaseConnection->commit();
    }

}
