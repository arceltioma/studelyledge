<?php

require_once __DIR__ . '/admin_functions.php';

if (!function_exists('pmw_is_logged_in')) {
    function pmw_is_logged_in(): bool
    {
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('pmw_redirect')) {
    function pmw_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('pmw_forbidden')) {
    function pmw_forbidden(string $message = 'Accès refusé.'): void
    {
        http_response_code(403);
        exit($message);
    }
}

if (!function_exists('pmw_store_intended_url')) {
    function pmw_store_intended_url(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $_SESSION['intended_url'] = (string)$_SERVER['REQUEST_URI'];
    }
}

if (!function_exists('pmw_consume_intended_url')) {
    function pmw_consume_intended_url(?string $default = null): string
    {
        $url = $_SESSION['intended_url'] ?? $default ?? (defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php' : '/');
        unset($_SESSION['intended_url']);

        return (string)$url;
    }
}

if (!function_exists('ensureAuthenticated')) {
    function ensureAuthenticated(): void
    {
        if (pmw_is_logged_in()) {
            return;
        }

        pmw_store_intended_url();

        $loginUrl = defined('APP_URL') ? APP_URL . 'login.php' : '/login.php';
        pmw_redirect($loginUrl);
    }
}

if (!function_exists('ensureGuestOnly')) {
    function ensureGuestOnly(): void
    {
        if (!pmw_is_logged_in()) {
            return;
        }

        $defaultUrl = defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php' : '/';
        pmw_redirect($defaultUrl);
    }
}

if (!function_exists('enforcePagePermission')) {
    function enforcePagePermission(PDO $pdo, string $permissionCode, bool $redirectIfUnauthorized = false): void
    {
        ensureAuthenticated();

        if (currentUserCan($pdo, $permissionCode)) {
            return;
        }

        if ($redirectIfUnauthorized) {
            $target = defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php?error=access_denied' : '/';
            pmw_redirect($target);
        }

        pmw_forbidden('Accès refusé : permission insuffisante pour cette page.');
    }
}

if (!function_exists('enforceAnyPermission')) {
    function enforceAnyPermission(PDO $pdo, array $permissionCodes, bool $redirectIfUnauthorized = false): void
    {
        ensureAuthenticated();

        foreach ($permissionCodes as $permissionCode) {
            if (is_string($permissionCode) && $permissionCode !== '' && currentUserCan($pdo, $permissionCode)) {
                return;
            }
        }

        if ($redirectIfUnauthorized) {
            $target = defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php?error=access_denied' : '/';
            pmw_redirect($target);
        }

        pmw_forbidden('Accès refusé : aucune des permissions requises n’est disponible.');
    }
}

if (!function_exists('enforceAllPermissions')) {
    function enforceAllPermissions(PDO $pdo, array $permissionCodes, bool $redirectIfUnauthorized = false): void
    {
        ensureAuthenticated();

        foreach ($permissionCodes as $permissionCode) {
            if (!is_string($permissionCode) || $permissionCode === '') {
                continue;
            }

            if (!currentUserCan($pdo, $permissionCode)) {
                if ($redirectIfUnauthorized) {
                    $target = defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php?error=access_denied' : '/';
                    pmw_redirect($target);
                }

                pmw_forbidden('Accès refusé : toutes les permissions requises ne sont pas réunies.');
            }
        }
    }
}

if (!function_exists('middlewareRequireAuthAndPermission')) {
    function middlewareRequireAuthAndPermission(PDO $pdo, string $permissionCode): void
    {
        enforcePagePermission($pdo, $permissionCode);
    }
}

if (!function_exists('middlewareRequireAuthOnly')) {
    function middlewareRequireAuthOnly(): void
    {
        ensureAuthenticated();
    }
}

if (!function_exists('middlewareGuestOnly')) {
    function middlewareGuestOnly(): void
    {
        ensureGuestOnly();
    }
}