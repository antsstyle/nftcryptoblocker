<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(600);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

try {
    Core::updateCentralDBEntriesUserInfo(1000);
} catch (\Exception $e) {
    LogManager::$cronLogger->error("Failed to get central DB entries user info: " . print_r($e, true));
}