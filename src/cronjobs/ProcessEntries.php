<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(0);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\LogManager;

$args = getopt("p:");
if ($args === false) {
    LogManager::$cronLogger->error("Unable to begin processentries - invalid process number argument.");
    return;
}
$pNumber = $args["p"];
if (!is_numeric($pNumber)) {
    LogManager::$cronLogger->error("Unable to begin processentries - invalid process number argument.");
    return;
}

$pName = "ProcessEntries.php." . $pNumber;

try {
    Core::processEntriesForAllUsers($args["p"]);
} catch (\Exception $e) {
    LogManager::$cronLogger->error("Error during process entries $pName - terminating. " . print_r($e, true));
}