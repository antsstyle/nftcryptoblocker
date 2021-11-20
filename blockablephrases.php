<?php

namespace Antsstyle\NFTArtistBlocker;

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\Session;
use Antsstyle\NFTArtistBlocker\Core\CoreDB;

Session::checkSession();

$blockablePhrases = CoreDB::getBlockablePhrases();

if (!$blockablePhrases) {
    echo "";
} else {
    echo "";
}
?>

<html>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <title>
        NFT Artist Blocker
    </title>
    <body>
        <h2> Blockable phrases </h2>
            <p>
                These are the phrases for which matching users will be auto-blocked or auto-muted if they post them in your timeline, in your mentions,
                or have them in their user profile. <br/><br/>
                They are case-insensitive.
            </p>
            <table>
                <tr>
                    <th>Blockable phrase</th>
                </tr>
                <?php
                foreach ($blockablePhrases as $blockablePhrase) {
                    echo "<tr><td>" . $blockablePhrase['phrase'] . "</td></tr>";
                }
                ?>
            </table>
    </body>
</html>
