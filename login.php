<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$pdo = getPDO();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . 'modules/dashboard/dashboard.php');
    exit;
}

$error = trim((string)($_GET['error'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, (string)$user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = (string)$user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['role_id'] = isset($user['role_id']) ? (int)$user['role_id'] : null;
            $_SESSION['last_activity'] = time();

            if (array_key_exists('last_login_at', $user)) {
                $stmtUpdateLogin = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $stmtUpdateLogin->execute([(int)$user['id']]);
            }

            header('Location: ' . APP_URL . 'modules/dashboard/dashboard.php');
            exit;
        }

        $error = 'Identifiants incorrects.';
    } catch (Throwable $e) {
        $error = APP_DEBUG ? ('Erreur de connexion : ' . $e->getMessage()) : 'Une erreur est survenue.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_asset('assets/css/style.css')) ?>">

    <style>
        .login-page{
            min-height:100vh;
            margin:0;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
            background:
                radial-gradient(circle at top left, rgba(93, 135, 255, 0.18), transparent 32%),
                radial-gradient(circle at bottom right, rgba(29, 37, 73, 0.20), transparent 28%),
                linear-gradient(135deg, #f6f8ff 0%, #eef3ff 100%);
        }

        .login-box{
            width:100%;
            max-width:430px;
            background:#ffffff;
            border-radius:28px;
            padding:34px 30px;
            box-shadow:0 22px 55px rgba(19,31,72,0.16);
            border:1px solid rgba(29,37,73,0.08);
        }

        .login-logo{
            display:block;
            max-width:190px;
            width:100%;
            height:auto;
            margin:0 auto 18px auto;
        }

        .login-box h2{
            margin:0 0 22px 0;
            text-align:center;
            color:#1d2549;
            font-size:30px;
        }

        .login-box form{
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .login-box input[type="text"],
        .login-box input[type="password"]{
            width:100%;
            box-sizing:border-box;
            border:1px solid #d6def5;
            border-radius:16px;
            padding:14px 16px;
            font-size:15px;
            outline:none;
            background:#fbfcff;
            transition:border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .login-box input[type="text"]:focus,
        .login-box input[type="password"]:focus{
            border-color:#5b7cff;
            box-shadow:0 0 0 4px rgba(91,124,255,0.12);
            background:#fff;
        }

        .login-box button[type="submit"]{
            margin-top:8px;
            border:none;
            border-radius:18px;
            padding:15px 18px;
            font-size:15px;
            font-weight:700;
            color:#fff;
            cursor:pointer;
            background:linear-gradient(135deg,#1d2549 0%,#2f4db8 100%);
            box-shadow:0 14px 26px rgba(47,77,184,0.28);
            transition:transform .18s ease, box-shadow .18s ease, opacity .18s ease;
        }

        .login-box button[type="submit"]:hover{
            transform:translateY(-1px);
            box-shadow:0 16px 30px rgba(47,77,184,0.34);
        }

        .login-box button[type="submit"]:active{
            transform:translateY(0);
            opacity:.96;
        }

        .error{
            margin-bottom:16px;
            border-radius:14px;
            padding:12px 14px;
            background:#fff1f2;
            border:1px solid #fecdd3;
            color:#b42318;
            font-size:14px;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <img src="<?= e(app_asset('assets/img/logo-sidebar.png')) ?>" alt="StudelyLedger" class="login-logo">
        <h2>Connexion</h2>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e(APP_URL) ?>login.php" novalidate>
            <input type="text" name="username" placeholder="Utilisateur" required autofocus>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Connexion</button>
        </form>
    </div>
</body>
</html>