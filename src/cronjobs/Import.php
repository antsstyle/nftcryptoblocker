<?php

namespace Antsstyle\NFTCryptoBlocker\Cronjobs;

chdir(dirname(__DIR__, 2));

$dir = getcwd();

require $dir . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

/*
 * This expects a CSV with "Name", "Twitter handle" as the first two columns -
 * all other columns will be ignored. Do not include a header row.
 */
function importBannedUsers() {
    if (($handle = fopen("bannedusers.csv", "r")) !== false) {
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $columnCount = count($data);
            if ($columnCount !== 2) {
                error_log("Invalid banned users CSV.");
                return;
            }
            $name = $data[0];
            $twitterHandle = $data[1];
            $screenName = substr($twitterHandle, 1);
            $users[$screenName] = $name;
        }
        fclose($handle);
        CoreDB::insertBannedUsers($users);
    } else {       
        error_log("Failed to open banned users file.");
    }
}

function insertBanned() {
    
}