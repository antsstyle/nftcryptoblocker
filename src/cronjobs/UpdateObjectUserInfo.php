<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CoreDB;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

try {
    CoreDB::updateObjectUserInfo();
} catch (\Exception $e) {
    LogManager::$cronLogger->error("Failed to update object user info: " . print_r($e, true));
}