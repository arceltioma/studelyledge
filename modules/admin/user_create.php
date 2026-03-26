<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/header.php';

requirePermission($pdo, 'admin_users_manage');

$roles = getRoleOptions($pdo);
$errorMessage = '';

$username = $_POST['username'] ?? '';
$roleId = (int)($_POST['role_id'] ?? 0);
$isActiveChecked = !isset($_POST['is_active']) ? true : ((int)($_POST['is_active'] ?? 0) === 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $roleId = (int)($_POST['role_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '' || $password === '' || $passwordConfirm === '' || $roleId <= 0) {
        $errorMessage = 'Tous les champs obligatoires doivent être renseignés.';
    } elseif ($password !== $passwordConfirm) {
        $errorMessage = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $check->execute([$username]);

        if ($check->fetch()) {
            $errorMessage = 'Ce nom d’utilisateur existe déjà.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username,
                    password,
                    role_id,
                    is_active,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $username,
                $hash,
                $roleId,
                $isActive
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'create_user',
                'admin',
                'user',
                $newUserId,
                "Création de l'utilisateur {$username}"
            );

            $_SESSION['admin_success'] = "L'utilisateur {$username} a été créé avec succès.";
            header('Location: ' . APP_URL . 'modules/admin/users.php');
            exit;
        }
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Créer un utilisateur', 'On façonne ici une clé d’accès, pas un simple formulaire.'); ?>

        <div class="page-title">

            <div class="btn-group">
                <a href="<?= APP_URL ?>modules/admin/users.php" class="btn btn-outline">Retour aux utilisateurs</a>
                <a href="<?= APP_URL ?>modules/admin/dashboard_admin.php" class="btn btn-secondary">Dashboard admin</a>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="card-grid">
            <div class="card">
                <h3>But</h3>
                <p class="muted">
                    Créer un compte propre, assigné à un rôle clair, sans semer le chaos dans les accès.
                </p>
            </div>

            <div class="card">
                <h3>Conseil</h3>
                <p class="muted">
                    Utilise un mot de passe solide et évite les rôles trop généreux si le besoin métier ne l’exige pas.
                </p>
            </div>
        </div>

        <div class="form-card">
            <form method="POST">
                <label for="username">Nom d’utilisateur</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    value="<?= e($username) ?>"
                    placeholder="Ex : manager02"
                    required
                >

                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Minimum 6 caractères"
                    required
                >

                <label for="password_confirm">Confirmer le mot de passe</label>
                <input
                    type="password"
                    name="password_confirm"
                    id="password_confirm"
                    placeholder="Répéter le mot de passe"
                    required
                >

                <label for="role_id">Rôle</label>
                <select name="role_id" id="role_id" required>
                    <option value="">Choisir un rôle</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= $roleId === (int)$role['id'] ? 'selected' : '' ?>>
                            <?= e($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= $isActiveChecked ? 'checked' : '' ?>
                        style="width:auto;margin:0;"
                    >
                    Compte actif dès sa création
                </label>

                <div class="btn-group">
                    <button type="submit" class="btn btn-success">Créer le compte</button>
                    <a href="<?= APP_URL ?>modules/admin/users.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <div class="table-card">
            <h3 class="section-title">Lecture rapide des rôles</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rôle</th>
                        <th>Usage recommandé</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="status-pill status-danger">Super Administrateur</span></td>
                        <td>Plein pouvoir sur l’application. À distribuer avec parcimonie.</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill status-success">Administrateur</span></td>
                        <td>Gestion opérationnelle avancée sans couronne absolue.</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill status-warning">Manager</span></td>
                        <td>Vision large, contrôle limité, supervision utile.</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill status-info">Lecture seule</span></td>
                        <td>Consultation sans modification. Idéal pour éviter les drames créatifs.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>