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
| URL racine du projet sous XAMPP
| Exemple : http://localhost/StudelyLedge/
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

/*
|--------------------------------------------------------------------------
| Debug PHP
|--------------------------------------------------------------------------
*/
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Helpers globaux
|--------------------------------------------------------------------------
*/
if (!function_exists('app_asset')) {
    function app_asset(string $path): string
    {
        return APP_URL . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = dirname(__DIR__);
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = app_path('uploads');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}

if (!function_exists('models_path')) {
    function models_path(string $path = ''): string
    {
        $base = app_path('modeles');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}

/*
|--------------------------------------------------------------------------
| Préparation minimale de l'arborescence utile
|--------------------------------------------------------------------------
*/
$requiredDirectories = [
    app_path('uploads'),
    app_path('uploads/imports'),
    app_path('uploads/exports'),
    app_path('modeles'),
];

foreach ($requiredDirectories as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
}