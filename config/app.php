<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'StudelyLedge');
define('APP_URL', 'http://localhost/studelyledge/');
define('APP_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);

function app_asset(string $path): string
{
    return APP_URL . ltrim($path, '/');
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}