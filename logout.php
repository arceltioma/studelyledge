<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/admin_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username = $_SESSION['username'] ?? null;

if ($userId) {
    try {
        logUserAction(
            $pdo,
            $userId,
            'logout',
            'auth',
            'user',
            $userId,
            'Déconnexion de l’utilisateur ' . ($username ?: ('#' . $userId))
        );
    } catch (Throwable $e) {
        // On n'interrompt pas la déconnexion si le log échoue.
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? true)
    );
}

session_destroy();

header('Location: ' . APP_URL . 'login.php');
exit;