<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/admin_functions.php';

/*
|--------------------------------------------------------------------------
| Log de déconnexion (si utilisateur connecté)
|--------------------------------------------------------------------------
*/
try {
    if (isset($_SESSION['user_id']) && function_exists('logUserAction')) {
        $pdo = getPDO();

        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'logout',
            'auth',
            'user',
            (int)$_SESSION['user_id'],
            'Déconnexion utilisateur'
        );
    }
} catch (Throwable $e) {
    // On ne bloque jamais une déconnexion pour un problème de log
}

/*
|--------------------------------------------------------------------------
| Destruction propre de la session
|--------------------------------------------------------------------------
*/
$_SESSION = [];

// Suppression du cookie de session si présent
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruction session
session_destroy();

/*
|--------------------------------------------------------------------------
| Redirection vers login
|--------------------------------------------------------------------------
*/
header('Location: ' . APP_URL . 'login.php?logout=1');
exit;