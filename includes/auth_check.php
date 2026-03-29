<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/app.php';
}

if (!function_exists('isUserLoggedIn')) {
    function isUserLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('getAuthenticatedUserId')) {
    function getAuthenticatedUserId(): ?int
    {
        return isUserLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('getAuthenticatedUsername')) {
    function getAuthenticatedUsername(): string
    {
        return (string)($_SESSION['username'] ?? $_SESSION['user_name'] ?? '');
    }
}

if (!function_exists('getAuthenticatedRoleName')) {
    function getAuthenticatedRoleName(): string
    {
        return (string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? '');
    }
}

if (!function_exists('storeIntendedUrl')) {
    function storeIntendedUrl(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $_SESSION['intended_url'] = (string)$_SERVER['REQUEST_URI'];
    }
}

if (!function_exists('consumeIntendedUrl')) {
    function consumeIntendedUrl(?string $default = null): string
    {
        $fallback = $default ?? APP_URL . 'modules/dashboard/dashboard.php';
        $url = (string)($_SESSION['intended_url'] ?? $fallback);

        unset($_SESSION['intended_url']);

        return $url;
    }
}

if (!function_exists('redirectToLogin')) {
    function redirectToLogin(): void
    {
        header('Location: ' . APP_URL . 'login.php');
        exit;
    }
}

if (!function_exists('redirectIfAuthenticated')) {
    function redirectIfAuthenticated(?string $default = null): void
    {
        if (!isUserLoggedIn()) {
            return;
        }

        $target = $default ?? APP_URL . 'modules/dashboard/dashboard.php';
        header('Location: ' . $target);
        exit;
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        if (isUserLoggedIn()) {
            return;
        }

        storeIntendedUrl();
        redirectToLogin();
    }
}

/*
|--------------------------------------------------------------------------
| Comportement par défaut du fichier
|--------------------------------------------------------------------------
| Ce fichier peut être inclus directement dans les pages protégées.
| Dans ce cas, on impose simplement qu’un utilisateur soit connecté.
| Les permissions fines sont ensuite gérées par permission_middleware.php.
*/
requireLogin();