<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

class CachedVariables {

    const LAST_BLOCKLIST_UPDATE_CHECK = "nftcryptoblocker.lastblocklistupdatecheck";
    const FOLLOWER_CHECK_TIME_INTERVAL_SECONDS = "nftcryptoblocker.followerchecktimeintervalseconds";
    
    const CACHED_TOTAL_COUNTS_LAST_RECHECK_DATE = "nftcryptoblocker.cachedtotalcountslastrecheckdate";
    const CACHED_TOTAL_BLOCKS_COUNT = "nftcryptoblocker.cachedtotalblockscount";
    const CACHED_TOTAL_MUTES_COUNT = "nftcryptoblocker.cachedtotalmutescount";
    
    const NEW_SIGNUPS_HALTED = "nftcryptoblocker.newsignupshalted";
    const NEW_SIGNUPS_NEXT_TIME = "nftcryptoblocker.newsignupsnexttime";
    
    const NUM_PROCESSENTRIES_THREADS = "nftcryptoblocker.numprocessentriesthreads";  
    
    const CENTRALISEDBLOCKLIST_MIN_MATCHCOUNT = "nftcryptoblocker.centralisedblocklistminmatchcount";
    const CENTRALISEDBLOCKLIST_MIN_FOLLOWERCOUNT = "nftcryptoblocker.centralisedblocklistminfollowercount";
    
    const MAX_TRANSACTION_RETRIES = "nftcryptoblocker.maxtransactionretries";   
    const NEXT_LOAD_BALANCE_REPORT_DATE = "nftcryptoblocker.nextloadbalancereportdate";
}
