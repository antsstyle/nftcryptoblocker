<?php

namespace Antsstyle\NFTCryptoBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Session;
use Antsstyle\NFTCryptoBlocker\Core\CoreDB;

Session::checkSession();

$blockablePhrases = CoreDB::getBlockablePhrases();
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
        <h2> Blockable phrases </h2>
        <p>
            These are the phrases for which matching users will be auto-blocked or auto-muted if their posts appear in your timeline, in your mentions,
            or have them in their user profile. <br/><br/>
            They are case-insensitive.
        </p>
        <table>
            <tr>
                <th>Blockable phrase</th>
            </tr>
            <?php
            if (!$blockablePhrases) {
                echo "Error: could not load list of blockable phrases.";
            } else {
                foreach ($blockablePhrases as $blockablePhrase) {
                    echo "<tr><td>" . $blockablePhrase['phrase'] . "</td></tr>";
                }
            }
            ?>
        </table>
    </body>
</html>
