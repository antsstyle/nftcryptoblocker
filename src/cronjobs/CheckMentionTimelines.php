<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\TwitterTimelines;

try {
    TwitterTimelines::checkMentionsTimelineForAllUsers();
} catch (\Exception $e) {
    error_log("Exception during mention timelines cronjob: " . print_r($e, true));
}

