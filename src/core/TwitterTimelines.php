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
        while ($userRow = $selectStmt->fetch()) {
            self::checkHomeTimelineForUser($userRow);
        }
    }

    public static function checkHomeTimelineForUser($userRow) {
        $params['tweet_mode'] = "extended";
        $params['count'] = 200;
        $params['include_ext_has_nft_avatar'] = true;
        $highestSinceID = 0;
        if ($userRow['lasthometimelineid']) {
            $params['since_id'] = $userRow['lasthometimelineid'];
            $highestSinceID = $userRow['lasthometimelineid'];
        }
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $insertParams = [];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(1, 1);
        $tweetCount = 1;
        while ($tweetCount > 0) {
            $tweets = $connection->get("statuses/home_timeline", $params);
            CoreDB::updateTwitterEndpointLogs("statuses/home_timeline", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode != StatusCodes::QUERY_OK) {
                break;
            }
            $tweetCount = count($tweets);
            foreach ($tweets as $tweet) {
                // check if the tweet is one we want to examine
                if ($tweet->user->id == $userRow['usertwitterid']) {
                    $tweetID = $tweet->id;
                    if ($tweetID > $highestSinceID) {
                        $highestSinceID = $tweetID;
                    }
                    continue;
                }
                // check description, profile picture: add block to entries to process if match found
                $filtersMatched = Core::checkUserFilters($tweet, $userRow);
                if ($filtersMatched) {
                    if ($filtersMatched['operation'] == "Block") {
                        $updateParams[] = [$userRow['usertwitterid'], $tweet->user->id, "Block", $filtersMatched['filtername'],
                            $filtersMatched['filtercontent']];
                        // Add to entries to process along with reason information
                    } else if ($filtersMatched['operation'] == "Mute") {
                        $updateParams[] = [$userRow['usertwitterid'], $tweet->user->id, "Mute", $filtersMatched['filtername'],
                            $filtersMatched['filtercontent']];
                        // Add to entries to process along with reason information
                    } else {
                        $userOp = $filtersMatched['operation'];
                        error_log("Unrecognised user automation operation, text was: $userOp");
                    }
                }
                $tweetID = $tweet->id;
                if ($tweetID > $highestSinceID) {
                    $highestSinceID = $tweetID;
                }
            }
            $params['since_id'] = $highestSinceID;
        }
        // Update since_id in DB
        $updateQuery = "UPDATE users SET lasthometimelineid=? WHERE twitterid=?";
        $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
        $updateStmt->execute([$highestSinceID, $userRow['usertwitterid']]);
        $insertQuery = "INSERT IGNORE INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,"
                . "matchedfiltername,matchedfiltercontent) VALUES (?,?,?,?,?)";
        CoreDB::$databaseConnection->beginTransaction();
        foreach ($insertParams as $insertParamsForUser) {
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $insertStmt->execute($insertParamsForUser);
        }
        CoreDB::$databaseConnection->commit();
    }

}
