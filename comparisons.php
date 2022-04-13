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
            <h2>What's the difference between this app and others?</h2>
            There are two primary differences between this app and others that exist (such as <a href="https://twitter.com/Ottomated_" target="_blank">
                @Ottomated_</a>'s NoFT app):
            <ul>
                <li>This app performs blocks and mutes via the official Twitter API, rather 
                    than unofficially by using your browser to scrape Twitter webpages.</li>
                <li>This app works by using a server to perform blocks and mutes for you, rather than your browser doing it.</li>
            </ul>
            There are a couple of reasons why this is important.
            <br/><br/>
            Firstly, most websites - including Twitter - do not like apps that 'scrape' their web content, or more applicably to this case, perform actions 
            outside of their official APIs, 
            because it creates a lot of unnecessary web traffic and circumvents website limits on request frequency. This means browser apps are more likely 
            to get users locked or suspended. (This is explained in detail at the bottom of the page).
            <br/><br/>
            Secondly, because they only work in the browser you add them to, they can't pre-emptively block posts by reading your Twitter feed without you 
            browsing through it yourself; you have to see the NFTs before it can block them. In addition, it's not as effective as if you e.g. browse on your 
            phone and it doesn't have the browser extension, it won't do any good there.
            <h2>Detailed explanations</h2>
            <button class="collapsible">Why does a browser app mean a user is more likely to be locked or suspended?</button>
            <div class="content">
                <p>
                    Any browser app that performs write activity on your Twitter account (e.g. tweeting, blocking) automatically will usually be doing so 
                    outside of the Twitter API. If it hasn't asked you to authorise via Twitter, it isn't using the API (you can see which apps you are using 
                    that use the API by going to your <a href="https://twitter.com/settings/apps_and_sessions">Apps and sessions</a> page in your Twitter 
                    settings).
                    <br/><br/>
                    In order to limit website traffic, and control how many requests particular applications make, they are required to use the Twitter API 
                    to make those requests. Apps which e.g. block users for you automatically, but don't use the API, are circumventing those limits and 
                    also tend to use a lot more bandwidth (not important for the user, but important to Twitter) to make those requests, since APIs are 
                    optimised for efficiency and don't e.g. send graphics or unnecessary information across requests. As such, 
                    Twitter and most websites don't like apps that do this.
                    <br/><br/>
                    Since the browser app is not registered with the API, Twitter doesn't know you are using the app; it only knows that your 
                    browser seems to be 
                    doing a lot of stuff of its own accord. Twitter can easily detect this kind of automated activity and will thus often restrict 
                    accounts that are 
                    seen doing this, to prevent abuse of their systems. Even for well-intentioned apps, there is no real way around this other than using 
                    the provided APIs.
                </p>
            </div>
            <button class="collapsible">Are there any advantages to a browser app?</button>
            <div class="content">
                <p>
                    There are some advantages, yes - but largely only for the developer.
                    <br/><br/>
                    The first advantage is mostly for the developer: because you're letting the user's browser do all the work of blocking and so forth, you 
                    don't need a server and you don't need to worry about making your app scale if it has many users; it'll scale on its own. That also 
                    means less costs. This also comes with the advantage that a browser app can't really get overwhelmed by a large number of users, 
                    unlike server-side applications which can sometimes struggle under unexpected workloads.
                    <br/><br/>
                    The second advantage is purely for the developer: using a browser extension lets you avoid using the official API, which means you 
                    don't have to worry about your app eating up too many requests. While well-written browser extensions won't spam requests for you 
                    (in an attempt to try and stop Twitter locking your account for automated activity), most API-using applications have 
                    specific rate limits on how many requests they can make in a given period, both for a given user and overall for the entire app's user base. 
                    Browser apps not using the API can effectively ignore this and do whatever they like, though it comes at the user's risk.
                </p>
            </div>
            <button class="collapsible">Isn't it possible to make a browser app that uses the API, then?</button>
            <div class="content">
                <p>
                    Yes and no; it can be done, but the browser app would have to use a server to perform requests, so it'd just be a server-side app 
                    with a browser extension for convenience.
                    <br/><br/>
                    The reason for this is security: any app that wants to perform requests on your behalf via the API must acquire a set of API keys. 
                    These keys are used to identify the app to Twitter when making a request, and must be kept secret at all times. The only way to 
                    prevent users - or other entities - discovering the API keys is to use the client-server model, where the server performs requests 
                    on behalf of the client. For example, if you wanted to block a Twitter user, you would send a request to the server through whatever 
                    app you're using, and the app's server would send the official API request to Twitter to block that user, and then tell you the result. 
                    In this way, all of the interaction with Twitter is done by the server, and so the API keys are never exposed.
                    <br/><br/>
                    Purely browser-based apps can't do this, because they do all of their work in your browser - and as a result, none of their internal 
                    workings are secret from your browser (and therefore, from you). It would have to store the API keys on your computer in order to 
                    perform any requests, so a knowledgeable user could easily discover them. If someone other than the app developer gets access to the 
                    API keys, they can perform malicious requests on behalf of the app as Twitter will assume they are genuine requests (bear in mind this 
                    doesn't necessarily mean they could e.g. post tweets to your account - API keys and user access tokens are separate things, and they 
                    would need both.)
                </p>
            </div>
        </div>
    </body>
    <script src="Collapsibles.js"></script>
</html>