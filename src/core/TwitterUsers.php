<?php

namespace Antsstyle\NFTArtistBlocker\Core;

use Antsstyle\NFTArtistBlocker\Core\StatusCodes;
use Antsstyle\NFTArtistBlocker\Credentials\APIKeys;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;
use Abraham\TwitterOAuth\TwitterOAuth;

class TwitterUsers {

    public static function checkNFTFollowersForAllUsers() {
        $selectQuery = "SELECT * FROM users INNER JOIN userautomationsettings ON users.twitterid=userautomationsettings.usertwitterid"
                . " WHERE nftfollowersoperation=? OR nftfollowersoperation=?";
        $selectStmt = CoreDB::$databaseConnection->prepare($selectQuery);
        $success = $selectStmt->execute(["Block", "Mute"]);
        if (!$success) {
            error_log("Could not get users to check NFT followers for, returning.");
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
            self::checkNFTFollowersForUser($userRow, $phrases, $urls, $regexes);
        }
    }

    public static function checkNFTFollowersForUser($userRow, $phrases, $urls, $regexes) {
        $params['max_results'] = 1000;
        $params['user.fields'] = "entities,description,name,profile_image_url,url,username";
        $accessToken = $userRow['accesstoken'];
        $accessTokenSecret = $userRow['accesstokensecret'];
        $followersPaginationToken = $userRow['followerspaginationtoken'];
        if ($followersPaginationToken != null && $userRow['followersendreached'] == "N") {
            $params['pagination_token'] = $followersPaginationToken;
        }
        $insertParams = [];
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                $accessToken, $accessTokenSecret);
        $connection->setApiVersion('2');
        $connection->setRetries(1, 1);
        $returnedPages = 0;
        $highestCheckedUserID = 0;
        $noMorePages = false;
        if ($userRow['followersendreached'] == "Y") {
            $followerCache = CoreDB::getFollowerCacheForUser($userRow['usertwitterid']);
        }
        // Max of 15 pages due to both rate limits, and to avoid excessively long running times for cronjobs
        $returnedFollowerIDs = [];
        while ($returnedPages < 15) {
            $query = "users/" . $userRow['usertwitterid'] . "/followers";
            $response = $connection->get($query, $params);
            CoreDB::updateTwitterEndpointLogs("users/:id/followers", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode != StatusCodes::QUERY_OK) {
                break;
            }
            $returnedPages++;
            $users = $response->data;
            if (count($users) == 0) {
                $noMorePages = true;
                break;
            }

            foreach ($users as $objectUser) {
                if ($userRow['followersendreached'] == "Y") {
                    break;
                }

                if (count($returnedFollowerIDs) < 25) {
                    $returnedFollowerIDs[] = $objectUser->id;
                }

                // check if the tweet is one we want to examine
                // check description, profile picture: add block to entries to process if match found
                $filtersMatched = self::checkNFTFilters($userRow, $objectUser, $phrases, $urls, $regexes);
                if ($filtersMatched) {
                    error_log("Filters matched! Object user:");
                    error_log(print_r($objectUser, true));
                    error_log("Filter:");
                    error_log(print_r($filtersMatched, true));
                    if ($filtersMatched['operation'] == "Block") {
                        $insertParams[] = [$userRow['usertwitterid'], $objectUser->id, "Block", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y"];
                        // Add to entries to process along with reason information
                    } else if ($filtersMatched['operation'] == "Mute") {
                        $insertParams[] = [$userRow['usertwitterid'], $objectUser->id, "Mute", $filtersMatched['filtertype'],
                            $filtersMatched['filtercontent'], "Y"];
                        // Add to entries to process along with reason information
                    } else {
                        $userOp = $filtersMatched['operation'];
                        error_log("Unrecognised user automation operation, text was: $userOp");
                    }
                }
            }
            $meta = $response->meta;
            if (!isset($meta->next_token) && $userRow['followersendreached'] == "N") {
                $noMorePages = true;
                break;
            } else if (!isset($meta->next_token)) {
                break;
            }

            $params['pagination_token'] = $meta->next_token;

            error_log("Next cursor: " . $meta->next_token);
            if ($userRow['followersendreached'] == "Y") {
                if ($followerCache === false) {
                    $userTwitterID = $userRow['usertwitterid'];
                    error_log("Follower cache for user ID $userTwitterID could not be retrieved!");
                    break;
                }
                foreach ($returnedFollowerIDs as $returnedFollowerID) {
                    if (in_array($returnedFollowerID, $followerCache)) {
                        $encounteredCache = true;
                        break;
                    }
                }
                if ($encounteredCache) {
                    break;
                }
            }
        }

        if ($noMorePages) {
            if (!isset($params['pagination_token'])) {
                $params['pagination_token'] = null;
            }
            $updateQuery = "UPDATE users SET followerspaginationtoken=?, followersendreached=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$params['pagination_token'], "Y", $userRow['usertwitterid']]);
        } else {
            $updateQuery = "UPDATE users SET followerspaginationtoken=? WHERE twitterid=?";
            $updateStmt = CoreDB::$databaseConnection->prepare($updateQuery);
            $updateStmt->execute([$params['pagination_token'], $userRow['usertwitterid']]);
        }

        CoreDB::updateFollowerCacheForUser($userRow['usertwitterid'], $returnedFollowerIDs);

        $insertQuery = "INSERT IGNORE INTO entriestoprocess (subjectusertwitterid,objectusertwitterid,operation,"
                . "matchedfiltertype,matchedfiltercontent,addtocentraldb) VALUES (?,?,?,?,?,?)";
        CoreDB::$databaseConnection->beginTransaction();
        foreach ($insertParams as $insertParamsForUser) {
            $insertStmt = CoreDB::$databaseConnection->prepare($insertQuery);
            $insertStmt->execute($insertParamsForUser);
        }
        CoreDB::$databaseConnection->commit();
    }

    public static function checkNFTFilters($subjectUserInfo, $objectUser, $phrases, $urls, $regexes) {
        $userURLs = $objectUser->entities->url;
        if (is_array($userURLs) && count($userURLs) > 0) {
            $userURL = $userURLs[0]->expanded_url;
            $userURL = filter_var($userURL, FILTER_VALIDATE_URL);
            if ($userURL) {
                $userURLHost = strtolower(parse_url($userURL, PHP_URL_HOST));
            }
        }
        $userDescription = $objectUser->description;
        if ($subjectUserInfo['matchingphraseoperation'] == "Block" || $subjectUserInfo['matchingphraseoperation'] == "Mute") {
            foreach ($phrases as $phrase) {
                $lowerCasePhrase = strtolower($phrase['phrase']);
                if (strpos((String) $userDescription, (String) $lowerCasePhrase) !== false) {
                    return array("operation" => $subjectUserInfo['matchingphraseoperation'], "filtertype" => "matchingphrase",
                        "filtercontent" => $phrase['phrase']);
                }
            }
        }
        if ($subjectUserInfo['nftprofilepictureoperation'] == "Block" || $subjectUserInfo['nftprofilepictureoperation'] == "Mute") {
            if ($objectUser->ext_has_nft_avatar) {
                return array("operation" => $subjectUserInfo['nftprofilepictureoperation'], "filtertype" => "nftprofilepictures", "filtercontent" => null);
            }
        }
        if ($subjectUserInfo['profileurlsoperation'] == "Block" || $subjectUserInfo['profileurlsoperation'] == "Mute") {
            foreach ($urls as $url) {
                $urlHost = strtolower(parse_url($url['url'], PHP_URL_HOST));
                if (isset($userURLHost) && (strpos((String) $urlHost, (String) $userURLHost) !== false)) {
                    return array("operation" => $subjectUserInfo['profileurlsoperation'], "filtertype" => "profileurls", "filtercontent" => $url['url']);
                }
            }
        }
        if ($subjectUserInfo['cryptousernamesoperation'] == "Block" || $subjectUserInfo['cryptousernamesoperation'] == "Mute") {
            foreach ($regexes as $regex) {
                $userName = strtolower($objectUser->name);
                if (preg_match($regex['regex'], $userName)) {
                    return array("operation" => $subjectUserInfo['cryptousernamesoperation'], "filtertype" => "cryptousernames",
                        "filtercontent" => $regex['regex']);
                }
            }
        }
        return false;
    }

}
