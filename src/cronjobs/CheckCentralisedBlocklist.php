<?php

namespace Antsstyle\NFTArtistBlocker\Cronjobs;

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\CoreDB;

CoreDB::checkCentralisedBlockListForAllUsers();