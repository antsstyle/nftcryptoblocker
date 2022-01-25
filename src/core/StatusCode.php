<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

class StatusCode {
    const HTTP_SERVICE_UNAVAILABLE = 503;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_NOT_FOUND = 404;
    const HTTP_FORBIDDEN = 403;
    const HTTP_BAD_CREDENTIALS = 401;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_TOO_MANY_REQUESTS = 429;
    const HTTP_QUERY_OK = 200;
    
    const TWITTER_NO_USER_MATCHES_FOR_TERMS = 17;
    const TWITTER_CREDENTIAL_ACCESS_DENIED = 37;
    const TWITTER_COULD_NOT_AUTHENTICATE = 32;
    const TWITTER_PAGE_DOES_NOT_EXIST = 34;
    const TWITTER_USER_NOT_FOUND = 50;
    const TWITTER_USER_SUSPENDED = 63;
    const TWITTER_ACCOUNT_SUSPENDED = 64;
    const TWITTER_INVALID_ACCESS_TOKEN = 89;
    const TWITTER_OAUTH_CREDENTIALS_ERROR = 99;
    const TWITTER_OVER_CAPACITY = 130;
    const TWITTER_UNKNOWN_ERROR = 131;
    const TWITTER_AUTOMATED_REQUEST_ERROR = 226;
    const TWITTER_USER_ALREADY_UNMUTED = 272;
    const TWITTER_USER_ACCOUNT_LOCKED = 326;
    
    const NFTCRYPTOBLOCKER_RATE_LIMIT_ZERO = -1;
    const NFTCRYPTOBLOCKER_QUERY_OK = 0;
    
    public $httpCode;
    public $twitterCode;
    
    public function __construct($httpCode, $twitterCode) {
        $this->httpCode = $httpCode;
        $this->twitterCode = $twitterCode;
    }
}

