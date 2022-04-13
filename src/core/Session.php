<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

class Session {

    public static function regenerateSession($reload = false) {
        // This token is used by forms to prevent cross site forgery attempts
        if (!isset($_SESSION['nonce']) || $reload) {
            $_SESSION['nonce'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
        if (!isset($_SESSION['IPaddress']) || $reload) {
            $_SESSION['IPaddress'] = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        }
        if (!isset($_SESSION['userAgent']) || $reload) {
            $_SESSION['userAgent'] = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT');
        }

        // Set current session to expire in 1 minute
        $_SESSION['OBSOLETE'] = true;
        $_SESSION['EXPIRES'] = time() + 60;

        // Create new session without destroying the old one
        session_regenerate_id(false);

        // Grab current session ID and close both sessions to allow other scripts to use them
        $newSession = session_id();
        session_write_close();

        // Set session ID to the new one, and start it back up again
        session_id($newSession);
        session_start([
            'cookie_lifetime' => 86400,
            'gc_maxlifetime' => 86400,
            'use_strict_mode' => 1,
            'cookie_secure' => "On",
        ]);

        // Don't want this one to expire
        unset($_SESSION['OBSOLETE']);
        unset($_SESSION['EXPIRES']);
    }

    public static function checkSession() {
        session_start([
            'cookie_lifetime' => 86400,
            'gc_maxlifetime' => 86400,
            'use_strict_mode' => 1,
            'cookie_secure' => "On",
        ]);
        try {
            if ($_SESSION['OBSOLETE'] && ($_SESSION['EXPIRES'] < time())) {
                throw new \Exception('Attempt to use expired session.');
            }
            if (!is_numeric($_SESSION['user_id'])) {
                throw new \Exception('No session started.');
            }
            if ($_SESSION['IPaddress'] != filter_input(INPUT_SERVER, 'REMOTE_ADDR')) {
                throw new \Exception('IP Address mixmatch (possible session hijacking attempt).');
            }
            if ($_SESSION['userAgent'] != filter_input(INPUT_SERVER, 'HTTP_USER_AGENT')) {
                throw new \Exception('Useragent mixmatch (possible session hijacking attempt).');
            }
            if (!$_SESSION['OBSOLETE'] && mt_rand(1, 100) == 1) {
                Session::regenerateSession();
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}

