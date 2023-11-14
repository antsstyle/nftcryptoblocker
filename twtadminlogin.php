<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\Session;

Session::checkSession();

$error = htmlspecialchars($_GET['error']);

// Authenticate that user is on admin list before allowing access to this page - use PHP session
// + some kind of password login perhaps? Or just use twitter ID?
?>

<html>
    <head>
        <link rel="stylesheet" href="../src/css/artretweeter.css" type="text/css">
        <link rel="stylesheet" href="main.css" type="text/css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <title>
        NFT Artist & Cryptobro Blocker
    </title>
    <body>
        <div class="main">
            <script src=<?php echo "/website/main.js"; ?>></script>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            <div class="subtitle">
                <h2>Admin Login</h2>
            </div>
            <div class="loginerror">
                <?php
                switch ($error) {
                    case "":
                        break;
                    case "userlocked":
                        echo "Your admin account is temporarily locked due to too many login attempts.<br/><br/>";
                        break;
                    case "usernotfound":
                        echo "Login failed; admin account not found.<br/><br/>";
                        break;
                    case "dberror":
                        echo "Login failed due to a database error.<br/><br/>";
                        break;
                    case "invalidpassword":
                        echo "Login failed due to invalid password - passwords cannot contain restricted characters.<br/><br/>";
                        break;
                    case "incorrectpassword":
                        echo "Your password was incorrect, try again.<br/><br/>";
                        break;
                    default:
                        break;
                }
                ?>
            </div>
            <form action="processlogin.php" method="post">
                <label for="username"><b>Username:</b></label>
                <input type="text" placeholder="Username" name="username" required>
                <br/>
                <label for="password"><b>Password:</b></label>
                <input type="password" id="password" name="password">
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    <script src=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "collapsibles.js"; ?>></script>
</html>
