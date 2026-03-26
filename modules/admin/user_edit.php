<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'admin_users_manage';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$userId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($userId <= 0) {
    die('Utilisateur invalide.');
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
    die('Utilisateur introuvable.');
}

$roles = [];
if ($pdo->query("SHOW TABLES LIKE 'roles'")->fetchColumn()) {
    $roles = $pdo->query("
        SELECT id, name
        FROM roles
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim((string)($_POST['username'] ?? ''));
        $roleId = ($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');

        if ($username === '') {
            throw new RuntimeException('Le nom utilisateur est obligatoire.');
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

        $pdo->beginTransaction();

        if ($password !== '') {
            $stmtUpdate = $pdo->prepare("
                UPDATE users
                SET
                    username = :username,
                    role_id = :role_id,
                    is_active = :is_active,
                    password = :password
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':username' => $username,
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
                    role_id = :role_id,
                    is_active = :is_active
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':username' => $username,
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

        $pdo->commit();
        $successMessage = 'Utilisateur mis à jour avec succès.';

        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Modifier un utilisateur',
            'Ajuster un compte sans froisser ni les permissions, ni la syntaxe.'
        ); ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="id" value="<?= (int)$userId ?>">

                <div class="dashboard-grid-2">
                    <div>
                        <label for="username">Nom utilisateur</label>
                        <input type="text" id="username" name="username" value="<?= e($user['username'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label for="role_id">Rôle</label>
                        <select id="role_id" name="role_id">
                            <option value="">Aucun</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>" <?= (int)($user['role_id'] ?? 0) === (int)$role['id'] ? 'selected' : '' ?>>
                                    <?= e($role['name']) ?>
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

                <div class="btn-group" style="margin-top:24px;">
                    <button type="submit" class="btn btn-success">Enregistrer</button>
                    <a href="<?= APP_URL ?>modules/admin/users.php" class="btn btn-outline">Retour</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>