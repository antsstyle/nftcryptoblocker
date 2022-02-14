<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\TwitterTimelines;

$start = microtime(true);

try {
    TwitterTimelines::checkMentionsTimelineForAllUsers();
} catch (\Exception $e) {
    error_log("Exception during mention timelines cronjob: " . print_r($e, true));
}

$executionTime = microtime(true) - $start;
$executionTimeHours = floor($executionTime / 3600);
$executionTimeMinutes = floor(($executionTime - $executionTimeHours * 3600) / 60);
$executionTimeSeconds = $executionTime - ($executionTimeMinutes * 60) - ($executionTimeHours * 3600);
if ($executionTimeHours > 0) {
    error_log("Mentions timeline cronjob took $executionTimeHours hours, $executionTimeMinutes minutes and $executionTimeSeconds seconds.");
} else if ($executionTimeMinutes > 0) {
    error_log("Mentions timeline cronjob took $executionTimeMinutes minutes and $executionTimeSeconds seconds.");
} else {
    error_log("Mentions timeline cronjob took $executionTimeSeconds seconds.");
}
