<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

use Antsstyle\NFTCryptoBlocker\Core\StatusCode;
use Antsstyle\NFTCryptoBlocker\Credentials\AdminUserAuth;
use Antsstyle\NFTCryptoBlocker\Credentials\APIKeys;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Abraham\TwitterOAuth\TwitterOAuth;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

class TwitterTweets {

    public static $logger;

    public static function testTweetSearch($query) {
        $params['query'] = $query;
        $params['max_results'] = 100;
        $params['expansions'] = "author_id";
        $params['user.fields'] = "entities,description,name,profile_image_url,url,username,id";
        $query = "tweets/search/recent";
        $connection = new TwitterOAuth(APIKeys::consumer_key, APIKeys::consumer_secret,
                AdminUserAuth::access_token, AdminUserAuth::access_token_secret);
        $connection->setApiVersion('2');
        $connection->setRetries(1, 1);
        $nextToken = 1;
        $phrases = CoreDB::getBlockablePhrases();
        $urls = CoreDB::getBlockableURLs();
        $regexes = CoreDB::getBlockableUsernameRegexes();
        $updateParams = [];
        while (!is_null($nextToken)) {
            $response = $connection->get($query, $params);
            CoreDB::updateTwitterEndpointLogs("tweets/search/recent", 1);
            $statusCode = Core::checkResponseHeadersForErrors($connection);
            if ($statusCode->httpCode != StatusCode::HTTP_QUERY_OK || $statusCode->twitterCode != StatusCode::NFTCRYPTOBLOCKER_QUERY_OK) {
                break;
            }
            $tweets = $response->data;
            $users = $response->includes->users;
            if (!isset($tweets) || count($tweets) == 0) {
                break;
            }
            $userInfo['matchingphraseoperation'] = "Block";
            $userInfo['nftprofilepictureoperation'] = "Block";
            $userInfo['urlsoperation'] = "Block";
            $userInfo['cryptousernamesoperation'] = "Block";
            $tweetCount = count($tweets);
            TwitterTweets::$logger->info("Tweet count: $tweetCount");
            for ($i = 0; $i < $tweetCount; $i++) {
                $user = $users[$i];
                $filtersMatched = Core::checkFiltersForTweetSearch($user, $phrases, $urls, $regexes);
                if ($filtersMatched) {
                    $updateParams[] = [$user->id, $filtersMatched['filtertype'],
                        $filtersMatched['filtercontent'], "tweets/search", 
                        $filtersMatched['filtertype'], $filtersMatched['filtercontent'], "tweets/search"];
                }
            }

            $meta = $response->meta;
            if (!isset($meta->next_token)) {
                break;
            } else if (!isset($meta->next_token)) {
                break;
            }
            $nextToken = $meta->next_token;
            $params['next_token'] = $nextToken;
        }
        if (count($updateParams) > 0) {
            CoreDB::insertCentralBlockListEntries($updateParams);
        }
    }

}

TwitterTweets::$logger = LogManager::getLogger("TwitterTweets");
