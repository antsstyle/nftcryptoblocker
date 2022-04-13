<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Core;
use Antsstyle\NFTCryptoBlocker\Core\Config;

$defaultMessage = "You are not logged in. Go back to the homepage to sign in.";
$errorMessage = filter_input(INPUT_POST, "errormsg", FILTER_SANITIZE_STRING);
?>

<html>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <link rel="stylesheet" href=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.css"; ?> type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <script src=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.js"; ?>></script>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            <?php
            $hp = Config::HOMEPAGE_URL;
            if ($errorMessage !== false && !is_null($errorMessage)) {
                echo $errorMessage;
            } else {
                echo $defaultMessage;
            }
            ?>
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>