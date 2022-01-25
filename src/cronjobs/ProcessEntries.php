<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;

$args = getopt("p:");
if ($args === false) {
    error_log("Unable to begin processentries - invalid process number argument.");
    return;
}
$pNumber = $args["p"];
if (!is_numeric($pNumber)) {
    error_log("Unable to begin processentries - invalid process number argument.");
    return;
}

$pName = "ProcessEntries.php." . $pNumber;

try {
    Core::processEntriesForAllUsers($args["p"]);
} catch (\Exception $e) {
    error_log("Error during process entries $pName - terminating. " . print_r($e, true));
}