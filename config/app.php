<?php

if (!defined('APP_NAME')) {
    define('APP_NAME', 'StudelyLedger');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

/*
|--------------------------------------------------------------------------
| APP_URL
|--------------------------------------------------------------------------
| Mets ici l’URL racine de ton projet XAMPP.
| Exemple :
| http://localhost/StudelyLedge/
|--------------------------------------------------------------------------
*/
if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost/StudelyLedge/');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', 'local');
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}

date_default_timezone_set('Europe/Paris');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}