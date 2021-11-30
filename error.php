<?php

require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTArtistBlocker\Core\Config;

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
        You are not logged in. Go back to the homepage to sign in.
        <br/><br/>
        <a href="<?php echo Config::HOMEPAGE_URL; ?>">NFT Artist & Cryptobro Blocker homepage</a>
    </body>
</html>