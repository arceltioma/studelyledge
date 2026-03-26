<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Gestion simple de session
|--------------------------------------------------------------------------
| - si l’utilisateur n’est pas connecté -> retour login
| - si session inactive trop longtemps -> déconnexion
|--------------------------------------------------------------------------
*/
$maxInactivity = 60 * 60 * 4; // 4 heures

if (empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . 'login.php?error=' . urlencode('Merci de vous connecter.'));
    exit;
}

if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $maxInactivity) {
    session_unset();
    session_destroy();

    header('Location: ' . APP_URL . 'login.php?error=' . urlencode('Session expirée.'));
    exit;
}

$_SESSION['last_activity'] = time();