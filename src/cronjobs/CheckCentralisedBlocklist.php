<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

try {
    CoreDB::checkCentralisedBlockListForUsers();
} catch (\Exception $e) {
    error_log("Exception during centralised blocklist check cronjob: " . print_r($e, true));
}

