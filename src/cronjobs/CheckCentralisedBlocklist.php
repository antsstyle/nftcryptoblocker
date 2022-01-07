<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

set_time_limit(600);

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

CoreDB::checkCentralisedBlockListForAllUsers();