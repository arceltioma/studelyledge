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
| Redirection si déjà connecté
|--------------------------------------------------------------------------
*/
redirectIfAuthenticated();

$pdo = getPDO();

$errorMessage = '';
$infoMessage = '';

if (!empty($_SESSION['session_expired'])) {
    unset($_SESSION['session_expired']);
    $infoMessage = 'Votre session a expiré après une période d’inactivité. Merci de vous reconnecter.';
} elseif (isset($_GET['disabled'])) {
    $infoMessage = 'Votre session a été fermée : ce compte n’est plus actif.';
}

/*
|--------------------------------------------------------------------------
| Limitation des tentatives de connexion
|--------------------------------------------------------------------------
*/
const SL_LOGIN_WINDOW_MINUTES = 15;
const SL_LOGIN_MAX_FAILURES_PER_USER = 5;
const SL_LOGIN_MAX_FAILURES_PER_IP = 20;

if (!function_exists('sl_login_client_ip')) {
    function sl_login_client_ip(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
}

if (!function_exists('sl_login_is_throttled')) {
    function sl_login_is_throttled(PDO $pdo, string $username, string $ip): bool
    {
        if (!tableExists($pdo, 'login_attempts')) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN username = ? THEN 1 ELSE 0 END) AS user_failures,
                SUM(CASE WHEN ip_address = ? THEN 1 ELSE 0 END) AS ip_failures
            FROM login_attempts
            WHERE success = 0
              AND attempted_at >= (NOW() - INTERVAL " . SL_LOGIN_WINDOW_MINUTES . " MINUTE)
              AND (username = ? OR ip_address = ?)
        ");
        $stmt->execute([$username, $ip, $username, $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return (int)($row['user_failures'] ?? 0) >= SL_LOGIN_MAX_FAILURES_PER_USER
            || (int)($row['ip_failures'] ?? 0) >= SL_LOGIN_MAX_FAILURES_PER_IP;
    }
}

if (!function_exists('sl_login_record_attempt')) {
    function sl_login_record_attempt(PDO $pdo, string $username, string $ip, bool $success): void
    {
        if (!tableExists($pdo, 'login_attempts')) {
            return;
        }

        if ($success) {
            $pdo->prepare("DELETE FROM login_attempts WHERE username = ? AND success = 0")->execute([$username]);
        }

        $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, success, attempted_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([mb_substr($username, 0, 100, 'UTF-8'), $ip, $success ? 1 : 0]);

        // Purge légère de l'historique
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)");
        }
    }
}

if (!function_exists('sl_login_is_password_hash')) {
    function sl_login_is_password_hash(string $value): bool
    {
        $info = password_get_info($value);
        return !empty($info['algo']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $clientIp = sl_login_client_ip();

        if ($username === '' || $password === '') {
            throw new RuntimeException('Veuillez renseigner tous les champs.');
        }

        if (!tableExists($pdo, 'users')) {
            throw new RuntimeException('La table users est introuvable.');
        }

        if (sl_login_is_throttled($pdo, $username, $clientIp)) {
            throw new RuntimeException('Trop de tentatives de connexion. Réessayez dans ' . SL_LOGIN_WINDOW_MINUTES . ' minutes.');
        }

        $selectParts = [
            'u.id',
            columnExists($pdo, 'users', 'username') ? 'u.username' : 'NULL AS username',
            columnExists($pdo, 'users', 'password') ? 'u.password' : 'NULL AS password',
            columnExists($pdo, 'users', 'is_active') ? 'u.is_active' : '1 AS is_active',
            'r.id AS role_id',
        ];

        if (tableExists($pdo, 'roles')) {
            if (columnExists($pdo, 'roles', 'label')) {
                $selectParts[] = 'r.label AS role_name';
            } elseif (columnExists($pdo, 'roles', 'name')) {
                $selectParts[] = 'r.name AS role_name';
            } elseif (columnExists($pdo, 'roles', 'code')) {
                $selectParts[] = 'r.code AS role_name';
            } else {
                $selectParts[] = 'NULL AS role_name';
            }

            if (columnExists($pdo, 'roles', 'code')) {
                $selectParts[] = 'r.code AS role_code';
            } else {
                $selectParts[] = 'NULL AS role_code';
            }
        } else {
            $selectParts[] = 'NULL AS role_name';
            $selectParts[] = 'NULL AS role_code';
        }

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

        $storedHash = (string)($user['password'] ?? '');
        $valid = false;
        $legacyPlainText = false;

        if ($user && $storedHash !== '') {
            if (sl_login_is_password_hash($storedHash)) {
                $valid = password_verify($password, $storedHash);
            } elseif (hash_equals($storedHash, $password)) {
                // Ancien mot de passe stocké en clair : accepté une dernière fois puis haché immédiatement
                $valid = true;
                $legacyPlainText = true;
            }
        } else {
            // Temps de réponse homogène même si l'identifiant n'existe pas
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
        }

        if (!$valid || (int)($user['is_active'] ?? 1) !== 1) {
            sl_login_record_attempt($pdo, $username, $clientIp, false);
            throw new RuntimeException('Identifiants invalides ou compte inactif.');
        }

        if ($legacyPlainText || password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
        }

        if (columnExists($pdo, 'users', 'last_login_at')) {
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([(int)$user['id']]);
        }

        sl_login_record_attempt($pdo, $username, $clientIp, true);

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)($user['username'] ?? '');
        $_SESSION['role_id'] = isset($user['role_id']) ? (int)$user['role_id'] : null;
        $_SESSION['role_name'] = (string)($user['role_name'] ?? '');
        $_SESSION['role'] = (string)($user['role_code'] ?? $user['role_name'] ?? '');
        $_SESSION['last_activity'] = time();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

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
        $errorMessage = ($e instanceof RuntimeException && !$e instanceof PDOException)
            ? $e->getMessage()
            : 'Connexion momentanément impossible. Réessayez plus tard.';

        if (!($e instanceof RuntimeException) || $e instanceof PDOException) {
            error_log('[login] ' . $e->getMessage());
        }
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
        <img src="<?= e(APP_URL) ?>assets/img/logo.png" class="login-logo" alt="Logo">

        <h2>Connexion</h2>

        <?php if (isset($_GET['logout'])): ?>
            <div class="success">Vous êtes déconnecté avec succès.</div>
        <?php endif; ?>

        <?php if ($infoMessage !== ''): ?>
            <div class="warning"><?= e($infoMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <?= csrf_input() ?>

            <label for="username">Identifiant</label>
            <input type="text" id="username" name="username" value="<?= e(is_string($_POST['username'] ?? null) ? $_POST['username'] : '') ?>" autocomplete="username" required>

            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>
