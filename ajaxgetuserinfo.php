<?php

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\Session;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;

Session::checkSession();

$userTwitterID = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userTwitterID !== $_SESSION['usertwitterid']) {
    return;
}

$automationSettings = CoreDB::getUserAutomationSettings($userTwitterID);
$blocklistSettings = CoreDB::getUserBlocklistAutomationSettings($userTwitterID);
if (!$automationSettings) {
    echo "";
} else {
    $rows['automationsettings'] = $automationSettings;
    $rows['blocklistsettings'] = $blocklistSettings;
    echo json_encode($rows);
}