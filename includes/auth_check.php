<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();

    header('Location: ' . APP_URL . 'login.php?error=Veuillez%20vous%20connecter');
    exit;
}

$timeout = 3600;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();

    header('Location: ' . APP_URL . 'login.php?error=Session%20expir%C3%A9e');
    exit;
}

$_SESSION['last_activity'] = time();