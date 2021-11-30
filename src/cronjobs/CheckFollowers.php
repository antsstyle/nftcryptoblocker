<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\TwitterUsers;

TwitterUsers::checkNFTFollowersForAllUsers();