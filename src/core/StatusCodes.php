<?php

namespace Antsstyle\NFTArtistBlocker\Core;

class StatusCodes {
    const SERVICE_UNAVAILABLE = 503;
    const INTERNAL_SERVER_ERROR = 500;
    const NOT_FOUND = 404;
    const FORBIDDEN = 403;
    const BAD_CREDENTIALS = 401;
    const BAD_REQUEST = 400;
    const RATE_LIMIT_EXCEEDED = 429;
    
    const QUERY_OK = 200;
    
    const NO_USER_MATCHES_FOR_TERMS = 17;
    const COULD_NOT_AUTHENTICATE = 32;
    const USER_NOT_FOUND = 50;
    const USER_SUSPENDED = 63;
    const ACCOUNT_SUSPENDED = 64;
    const INVALID_ACCESS_TOKEN = 89;
    const OAUTH_CREDENTIALS_ERROR = 99;
    const TWITTER_OVER_CAPACITY = 130;
    const TWITTER_UNKNOWN_ERROR = 131;
    const AUTOMATED_REQUEST_ERROR = 226;
    const USER_ALREADY_UNMUTED = 272;
    const USER_ACCOUNT_LOCKED = 326;
    
    const RATE_LIMIT_ZERO = -1;
}

