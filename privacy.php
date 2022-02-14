<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\Core;
?>
<html>
    <script src="coreajax.js"></script>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:site" content="@antsstyle" />
        <meta name="twitter:title" content="NFT Artist & Cryptobro Blocker" />
        <meta name="twitter:description" content="Auto-blocks or auto-mutes NFT artists and cryptobros on your timeline and/or in your mentions." />
        <meta name="twitter:image" content="<?php echo Config::CARD_IMAGE_URL; ?>" />
    </head>
    <title>
        NFT Artist & Cryptobro Blocker
    </title>
    <body>
        <div class="main">
            <?php Core::echoSideBar(); ?>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            <h2>What information does this app collect?</h2>
            The only information it keeps is as follows:
            <ul>
                <li>Your Twitter ID (all apps need this)</li>
                <li>The settings you have saved</li>
            </ul>
            That's it. The app doesn't need any other data; it reads the tweets on your timelines (not your own tweets), in order to check them for NFT or 
            crypto content, but it doesn't save any information about the tweet (as it is not needed).
            <h2>Is the data shared with any other applications or in any other way?</h2>
            No. 
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>