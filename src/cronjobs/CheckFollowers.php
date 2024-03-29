<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\TwitterUsers;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

try {
    TwitterUsers::checkNFTFollowersForAllUsers();
} catch (\Exception $e) {
    LogManager::$cronLogger->error("Failed to check NFT followers for all users: " . print_r($e, true));
}