<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

if (!function_exists('pm_table_exists')) {
    function pm_table_exists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$tableName]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('pm_column_exists')) {
    function pm_column_exists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$tableName, $columnName]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('currentUserRecord')) {
    function currentUserRecord(PDO $pdo): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        if (!pm_table_exists($pdo, 'users')) {
            return null;
        }

        $roleJoin = '';
        $roleSelect = '';

        if (pm_table_exists($pdo, 'roles') && pm_column_exists($pdo, 'users', 'role_id')) {
            $roleJoin = " LEFT JOIN roles r ON r.id = u.role_id ";
            $roleSelect = ", r.code AS role_code, r.label AS role_label ";
        }

        $stmt = $pdo->prepare("
            SELECT u.* {$roleSelect}
            FROM users u
            {$roleJoin}
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}

if (!function_exists('currentUserCan')) {
    function currentUserCan(PDO $pdo, string $permissionCode): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = currentUserRecord($pdo);
        if (!$user) {
            return false;
        }

        $legacyRole = (string)($user['role'] ?? '');
        $roleCode = (string)($user['role_code'] ?? '');

        if (in_array($legacyRole, ['admin', 'superadmin'], true)) {
            return true;
        }

        if (in_array($roleCode, ['admin_tech', 'superadmin'], true)) {
            return true;
        }

        if (
            !pm_table_exists($pdo, 'permissions') ||
            !pm_table_exists($pdo, 'role_permissions') ||
            !pm_table_exists($pdo, 'roles') ||
            !pm_column_exists($pdo, 'users', 'role_id')
        ) {
            return true;
        }

        $roleId = (int)($user['role_id'] ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
              AND p.code = ?
        ");
        $stmt->execute([$roleId, $permissionCode]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(PDO $pdo, string $permissionCode): void
    {
        if (!currentUserCan($pdo, $permissionCode)) {
            http_response_code(403);
            exit('Accès refusé.');
        }
    }
}

if (!function_exists('enforcePagePermission')) {
    function enforcePagePermission(PDO $pdo, string $permissionCode): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . 'login.php?error=' . urlencode('Merci de vous connecter.'));
            exit;
        }

        if (!currentUserCan($pdo, $permissionCode)) {
            http_response_code(403);

            echo '<!DOCTYPE html>';
            echo '<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
            echo '<title>Accès refusé</title>';
            echo '<link rel="stylesheet" href="' . e(app_asset('assets/css/style.css')) . '">';
            echo '</head><body class="login-page">';
            echo '<div class="login-box">';
            echo '<h2>Accès refusé</h2>';
            echo '<p class="muted">Vous ne disposez pas de la permission requise : <strong>' . e($permissionCode) . '</strong>.</p>';
            echo '<div class="btn-group" style="justify-content:center;margin-top:18px;">';
            echo '<a class="btn btn-outline" href="' . e(APP_URL) . 'modules/dashboard/dashboard.php">Retour au dashboard</a>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }
    }
}