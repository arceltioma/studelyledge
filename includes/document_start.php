<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

$pageTitleValue = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitleValue) ?></title>

    <link rel="stylesheet" href="<?= e(app_asset('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_asset('assets/css/dashboard.css')) ?>">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= e(app_asset('assets/js/app.js')) ?>" defer></script>
    <script src="<?= e(app_asset('assets/js/charts.js')) ?>" defer></script>
</head>
<body class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/modules/dashboard/dashboard.php') ? 'dashboard-no-jump' : '' ?>">    
<div class="app-shell">