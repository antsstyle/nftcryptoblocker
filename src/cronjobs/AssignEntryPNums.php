<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

try {
    CoreDB::assignEntryPNums();
} catch (\Exception $e) {
    error_log("Failed to assign entry pnums : " . print_r($e, true));
}