<?php
require_once __DIR__ . '/config/app.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . 'modules/dashboard/dashboard.php');
    exit;
}

header('Location: ' . APP_URL . 'login.php');
exit;