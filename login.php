<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/admin_functions.php';

/*
|--------------------------------------------------------------------------
| Si déjà connecté, on ne laisse pas revenir sur la page login
|--------------------------------------------------------------------------
*/
redirectIfAuthenticated();

$pdo = getPDO();

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('Merci de renseigner votre identifiant et votre mot de passe.');
        }

        if (!tableExists($pdo, 'users')) {
            throw new RuntimeException('La table des utilisateurs est introuvable.');
        }

        $selectParts = [
            'u.id',
            columnExists($pdo, 'users', 'username') ? 'u.username' : 'NULL AS username',
            columnExists($pdo, 'users', 'password') ? 'u.password' : 'NULL AS password_hash',
            columnExists($pdo, 'users', 'is_active') ? 'u.is_active' : '1 AS is_active',
            'r.id AS role_id',
            columnExists($pdo, 'roles', 'label') ? 'r.label AS role_name' : (
                columnExists($pdo, 'roles', 'name') ? 'r.name AS role_name' : 'NULL AS role_name'
            ),
            columnExists($pdo, 'roles', 'code') ? 'r.code AS role_code' : 'NULL AS role_code',
        ];

        $sql = "
            SELECT " . implode(",\n", $selectParts) . "
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.username = ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('Identifiants invalides.');
        }

        if ((int)($user['is_active'] ?? 1) !== 1) {
            throw new RuntimeException('Votre compte est désactivé.');
        }

        $storedHash = (string)($user['password_hash'] ?? $user['password'] ?? '');
        $isPasswordValid = false;

        if ($storedHash !== '') {
            if (password_verify($password, $storedHash)) {
                $isPasswordValid = true;
            } elseif ($password === $storedHash) {
                // Compatibilité temporaire si certains comptes sont encore en mot de passe non hashé
                $isPasswordValid = true;
            }
        }

        if (!$isPasswordValid) {
            throw new RuntimeException('Identifiants invalides.');
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)($user['username'] ?? '');
        $_SESSION['role_id'] = isset($user['role_id']) ? (int)$user['role_id'] : null;
        $_SESSION['role_name'] = (string)($user['role_name'] ?? '');
        $_SESSION['role'] = (string)($user['role_code'] ?? $user['role_name'] ?? '');

        if (function_exists('logUserAction')) {
            logUserAction(
                $pdo,
                (int)$user['id'],
                'login',
                'auth',
                'user',
                (int)$user['id'],
                'Connexion utilisateur'
            );
        }

        $target = consumeIntendedUrl(APP_URL . 'modules/dashboard/dashboard.php');
        header('Location: ' . $target);
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - Connexion</title>
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>assets/css/dashboard.css">
</head>
<body class="login-page">
    <div class="login-box">
        <img src="<?= e(APP_URL) ?>assets/img/logo.png" alt="Studely Ledger" class="login-logo">

        <h2>Connexion</h2>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_input() ?>

            <div>
                <label for="username">Identifiant</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e($_POST['username'] ?? '') ?>"
                    autocomplete="username"
                    required
                >
            </div>

            <div>
                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>