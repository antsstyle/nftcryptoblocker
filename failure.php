<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
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
        <div class="main">
            <?php Core::echoSideBar(); ?>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            Something went wrong authenticating your account via Twitter. <br/><br/>Please try again or contact 
            <a href="https://twitter.com/antsstyle">@antsstyle</a> if the problem persists.
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>