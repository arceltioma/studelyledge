<?php
require_once __DIR__ . '/app.php';

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}

if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'studelyledge');
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
}

if (!function_exists('getPDO')) {
    function getPDO(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        return $pdo;
    }
}