<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\TwitterTimelines;

try {
    TwitterTimelines::checkHomeTimelineForAllUsers();
} catch (\Exception $e) {
    error_log("Error during home timelines process - terminating. " . print_r($e, true));
}