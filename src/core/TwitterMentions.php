<?php

namespace Antsstyle\NFTArtistBlocker\Core;

use Antsstyle\NFTArtistBlocker\Credentials\APIKeys;
use Antsstyle\NFTArtistBlocker\Core\Core;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;
use Abraham\TwitterOAuth\TwitterOAuth;

class TwitterMentions {

    public static function checkMentionsForAllUsers() {
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
            self::checkMentionsForUser($userRow, $phrases, $urls, $regexes);
        }
    }

    public static function checkMentionsForUser($userRow, $phrases, $urls, $regexes) {
        $params['tweet_mode'] = "extended";
        $params['include_ext_has_nft_avatar'] = true;
        $highestSinceID = 0;
        if ($userRow['lastmentiontimelineid']) {
            $params['since_id'] = $userRow['lastmentiontimelineid'];
            $highestSinceID = $userRow['lastmentiontimelineid'];
        }
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $insertParams = [];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setRetries(1, 1);
        $mentionCount = 1;
        while ($mentionCount > 0) {
            $mentions = $connection->get("statuses/mentions_timeline", $params);
            CoreDB::updateTwitterEndpointLogs("statuses/mentions_timeline", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode != StatusCodes::QUERY_OK) {
                break;
            }
            $mentionCount = count($mentions);
            error_log("Mention count: $mentionCount");
            foreach ($mentions as $mention) {
                // check description, profile picture: add block to entries to process if match found
                $filtersMatched = Core::checkUserFilters($mention, $userRow, $phrases, $urls, $regexes);
                if ($filtersMatched) {
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
            $params['since_id'] = $highestSinceID;
        }
        // Update since_id in DB
        $updateQuery = "UPDATE users SET lastmentiontimelineid=? WHERE twitterid=?";
        $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
        $updateStmt->execute([$highestSinceID, $userRow['usertwitterid']]);
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
