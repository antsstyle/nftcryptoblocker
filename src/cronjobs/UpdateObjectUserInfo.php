<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

try {
    CoreDB::updateObjectUserInfo();
} catch (\Exception $e) {
    error_log("Failed to update object user info: " . print_r($e, true));
}