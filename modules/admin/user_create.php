<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'admin_users_manage');

$roles = getRoleOptions($pdo);

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleId = ($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($username === '' || $password === '') {
            throw new RuntimeException('Le nom utilisateur et le mot de passe sont obligatoires.');
        }

        if ($roleId === null || $roleId <= 0) {
            throw new RuntimeException('Le rôle est obligatoire.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmtDup->execute([$username]);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce nom utilisateur existe déjà.');
        }

        $stmtRole = $pdo->prepare("
            SELECT code
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $stmtRole->execute([$roleId]);
        $roleCode = $stmtRole->fetchColumn();

        if (!$roleCode) {
            throw new RuntimeException('Rôle invalide.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (
                username,
                password,
                role,
                role_id,
                is_active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $roleCode,
            $roleId,
            $isActive
        ]);

        $newUserId = (int)$pdo->lastInsertId();

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'create_user',
                'admin',
                'user',
                $newUserId,
                'Création d’un utilisateur'
            );
        }

        header('Location: ' . APP_URL . 'modules/admin/users.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Créer un utilisateur';
$pageSubtitle = 'Création d’un nouveau compte applicatif.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Nom utilisateur</label>
                            <input type="text" name="username" required>
                        </div>

                        <div>
                            <label>Rôle</label>
                            <select name="role_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['id'] ?>">
                                        <?= e($role['label']) ?> (<?= e($role['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Mot de passe</label>
                            <input type="password" name="password" required>
                        </div>

                        <div style="display:flex;align-items:end;">
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="is_active" checked>
                                Utilisateur actif
                            </label>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Créer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Le compte créé héritera des permissions du rôle sélectionné via
                    la matrice d’accès. Le mot de passe est hashé à la création.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>