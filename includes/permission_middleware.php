<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/admin_functions.php';

if (!function_exists('enforcePagePermission')) {
    function enforcePagePermission(PDO $pdo, ?string $permissionCode): void
    {
        if ($permissionCode === null || trim($permissionCode) === '') {
            return;
        }

        requirePermission($pdo, $permissionCode);
    }
}

if (!function_exists('enforceAnyPagePermission')) {
    function enforceAnyPagePermission(PDO $pdo, array $permissionCodes): void
    {
        $permissionCodes = array_values(array_filter(array_map('trim', $permissionCodes)));

        if (empty($permissionCodes)) {
            return;
        }

        requireAnyPermission($pdo, $permissionCodes);
    }
}