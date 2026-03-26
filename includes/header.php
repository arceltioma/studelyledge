<?php
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/app.php';
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$appName = defined('APP_NAME') ? APP_NAME : 'StudelyLedger';
$currentTitle = $pageTitle ?? $appName;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = $_SESSION['username'] ?? 'Utilisateur';
$role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($currentTitle) ?></title>
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <div class="topbar">
        <div class="topbar-left">
            <span class="topbar-badge">StudelyLedger</span>
        </div>

        <div class="topbar-right">
            <div class="topbar-user">
                <span class="topbar-user-name"><?= e($username) ?></span>
                <span class="topbar-user-role"><?= e($role) ?></span>
            </div>
        </div>
    </div>