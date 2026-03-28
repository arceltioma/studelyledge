<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'admin_users_manage');

$userId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($userId <= 0) {
    exit('Utilisateur invalide.');
}

$stmtUser = $pdo->prepare("
    SELECT id, username, role_id, is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit('Utilisateur introuvable.');
}

$roles = getRoleOptions($pdo);

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $roleId = ($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');

        if ($username === '') {
            throw new RuntimeException('Le nom utilisateur est obligatoire.');
        }

        if ($roleId <= 0) {
            throw new RuntimeException('Le rôle est obligatoire.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtDup->execute([$username, $userId]);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce nom utilisateur est déjà utilisé.');
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

        if ($password !== '') {
            $stmtUpdate = $pdo->prepare("
                UPDATE users
                SET
                    username = :username,
                    role = :role,
                    role_id = :role_id,
                    is_active = :is_active,
                    password = :password,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':username' => $username,
                ':role' => $roleCode,
                ':role_id' => $roleId,
                ':is_active' => $isActive,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => $userId,
            ]);
        } else {
            $stmtUpdate = $pdo->prepare("
                UPDATE users
                SET
                    username = :username,
                    role = :role,
                    role_id = :role_id,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':username' => $username,
                ':role' => $roleCode,
                ':role_id' => $roleId,
                ':is_active' => $isActive,
                ':id' => $userId,
            ]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_user',
                'admin',
                'user',
                $userId,
                "Modification de l'utilisateur {$username}"
            );
        }

        $successMessage = 'Utilisateur mis à jour avec succès.';

        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un utilisateur';
$pageSubtitle = 'Ajuster un compte sans casser les permissions.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$userId ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label for="username">Nom utilisateur</label>
                            <input type="text" id="username" name="username" value="<?= e($user['username'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label for="role_id">Rôle</label>
                            <select id="role_id" name="role_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['id'] ?>" <?= (int)($user['role_id'] ?? 0) === (int)$role['id'] ? 'selected' : '' ?>>
                                        <?= e($role['label']) ?> (<?= e($role['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="password">Nouveau mot de passe</label>
                            <input type="password" id="password" name="password" placeholder="Laisser vide pour conserver l’actuel">
                        </div>

                        <div style="display:flex;align-items:flex-end;">
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="is_active" <?= (int)($user['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                                Utilisateur actif
                            </label>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Le rôle sélectionné pilote les permissions via la matrice d’accès.
                    Le mot de passe n’est modifié que si un nouveau mot de passe est saisi.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>