<?php
require __DIR__ . '/vendor/autoload.php';

use Antsstyle\NFTCryptoBlocker\Core\Config;
use Antsstyle\NFTCryptoBlocker\Core\Core;
?>
<html>
    <script src="coreajax.js"></script>
    <head>
        <link rel="stylesheet" href="main.css" type="text/css">
        <link rel="stylesheet" href=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.css"; ?> type="text/css">
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
            <script src=<?php echo Config::WEBSITE_STYLE_DIRECTORY . "sidebar.js"; ?>></script>
            <h1>NFT Artist & Cryptobro Blocker</h1>
            This page contains information about how the NFT Artist & Cryptobro Blocker works, why some features are not implemented, and more.
            <h2>How it works</h2>
            When you sign in and authorise the app, it is given an access token to perform actions on behalf of your account. 
            It will then continually monitor a few things for you:
            <ul>
                <li>
                    Your <b>home timeline</b> (the 'main page' of your Twitter, the same as going to 
                    <a href="https://twitter.com/home" target="_blank">https://twitter.com/home</a>). It looks for tweets that contain 
                    certain phrases or URLs, or made by users who have certain URLs or phrases in their description or website address, or who have a 
                    crypto username (e.g. .eth) or 'verified' NFT profile picture.
                </li>
                <br/>
                <li>
                    Your <b>mentions timeline</b> (the 'mentions' page of your Twitter, the same as going to 
                    <a href="https://twitter.com/notifications/mentions" target="_blank">https://twitter.com/notifications/mentions</a>). 
                    The search criteria are the same as for the home timeline.
                </li>
                <br/>
                <li>
                    Your followers - anyone with a matching website address, description, crypto username, or 'verified' NFT profile picture.
                </li>
            </ul>
            When the app finds something that matches, it queues the NFT/crypto user for blocking or muting (or does nothing, if you so choose)
            depending on your settings preferences.
            <br/><br/>
            In addition, when the app detects an NFT/crypto user, it stores a record of that user in its central database. When that user has been 
            detected by enough app users, it is added to the central blocklist, allowing the app to build up a list of "known NFT/crypto people" that 
            it can block or mute on your behalf (this is an optional feature; it can be enabled or disabled in the settings page).
            <h2>Questions</h2>
            <button class="collapsible">Does this app's blocks/mutes apply on all of my devices, or only in my browser?</button>
            <div class="content">
                <p>
                    On all of your devices.
                </p>
                <p>
                    This app performs its blocking and muting directly on your Twitter account, and therefore when it blocks or mutes a user for you, this 
                    applies to all devices and browsers you use. This is one of the reasons I did not make the app as a browser plugin.
                </p>
            </div>
            <button class="collapsible">Why not make a browser plugin?</button>
            <div class="content">
                <p>
                    There's a few reasons for this.
                    <br/><br/>
                    The only way a browser plugin would work any differently is if it doesn't make requests through a server (i.e. does all the work on your 
                    side). It's not possible to use the Twitter API this way; this is because when you want to make a Twitter application, you must register it and 
                    get API keys, which you use to perform actions on behalf of your application. Those API keys have to be kept secure at all times to prevent 
                    a malicious actor using them for dodgy requests; as such, apps always operate on a client-server model, where the server (this app) performs 
                    requests on behalf of clients (you).
                    <br/><br/>
                    In a browser plugin, in order to make requests for you the plugin itself would have to have access to the API keys, which would make them easy 
                    to see and misuse. Therefore, many browser plugins for Twitter are 'unofficial' apps that don't use the Twitter API itself, but rather 
                    manipulate the page in your browser (by e.g. removing certain elements of the page when it loads). This can work, but is also a difficult 
                    approach to do, not least because Twitter doesn't like it, and also because doing this depends on the exact manner in which Twitter renders 
                    web pages. If Twitter changes its format, the plugin breaks and has to be updated. All other browser plugins use client-server models, and 
                    are basically just for convenience purposes, as some users prefer it to having to visit a website.
                    <br/><br/>
                    In addition to this, a browser plugin that doesn't utilise a client-server model can only work in the browser it's in - if you open 
                    another browser or look at Twitter on your phone, 
                    all the content that is hidden in your plugin-using browser will be visible in those places.
                </p>
            </div>
            <button class="collapsible">Why has the central blocklist reduced in size?</button>
            <div class="content">
                <p>
                    This is (for the moment) an unsolvable scaling problem.
                    <br/><br/>
                    The main problem is that the Twitter API only lets you perform *one block at a time*. As a result, blocking many users takes a while; 
                    there's only so much traffic that can be handled at once as a result of this. The central blocklist made for an exponentially growing, huge 
                    amount of block/mute requests; if you have say, 1000 users and 10 new central blocklist entries, that 10,000 new requests to process.
                    <br/><br/>
                    However, if you've got 10,000 users and 100 new entries, that's a million new requests to process. Since central blocklist entries are found 
                    more and more as user count increases, it quickly starts adding so many block and mute requests that it would require exorbitant amounts of 
                    time to handle and the app would never be able to keep up.
                    <br/><br/>
                    I'm currently looking into possible solutions to this; I've detailed a few on my Patreon, but as yet I'm still investigating if any of them 
                    are plausible. For now, the app blocks all entries that match specific criteria instead of just *all* entries (at present, the criteria 
                    are to have a minimum of 1000 followers and to have matched at least twice).
                </p>
            </div>
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>