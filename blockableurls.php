<?php

namespace Antsstyle\NFTCryptoBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Session;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

Session::checkSession();

$blockableURLs = CoreDB::getBlockableURLs();
?>

<html>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <title>
        NFT Artist & Cryptobro Blocker
    </title>
    <body>
        <h2> Blockable URLs </h2>
        <p>
            These are the URLs for which matching users will be auto-blocked or auto-muted if they post them in your timeline, in your mentions,
            or have them in their user profile. <br/><br/>
            They are case-insensitive and only match against the hostname of the URL.
        </p>
        <table>
            <tr>
                <th>Blockable URL</th>
            </tr>
            <?php
            if (!$blockableURLs) {
                echo "Error: could not load list of blockable URLs.";
            } else {
                foreach ($blockableURLs as $blockableURL) {
                    echo "<tr><td>" . $blockableURL['url'] . "</td></tr>";
                }
            }
            ?>
        </table>
    </body>
</html>
