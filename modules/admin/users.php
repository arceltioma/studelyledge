<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_users_manage');

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$deleteId]);
        header('Location: ' . APP_URL . 'modules/admin/users.php?ok=deleted');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    try {
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'user'));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '') {
            throw new RuntimeException('Le nom d’utilisateur est obligatoire.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $params = [$username];
        if ($editId > 0) {
            $params[] = $editId;
        }
        $stmtDup->execute($params);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce nom d’utilisateur existe déjà.');
        }

        if ($editId > 0) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, role = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $role, $hash, $editId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, role = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $role, $editId]);
            }

            $successMessage = 'Utilisateur mis à jour.';
        } else {
            if ($password === '') {
                throw new RuntimeException('Le mot de passe est obligatoire à la création.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, role, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $hash, $role]);

            $successMessage = 'Utilisateur créé.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editUser = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$users = tableExists($pdo, 'users')
    ? $pdo->query("
        SELECT *
        FROM users
        ORDER BY id DESC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Utilisateurs',
            'Administration technique des accès à la plateforme.'
        ); ?>

        <?php if (isset($_GET['ok']) && $_GET['ok'] === 'deleted'): ?>
            <div class="success">Utilisateur supprimé.</div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title"><?= $editUser ? 'Modifier un utilisateur' : 'Créer un utilisateur' ?></h3>

                <form method="POST">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$editUser['id'] ?>">
                    <?php endif; ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Nom d’utilisateur</label>
                            <input type="text" name="username" value="<?= e($editUser['username'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Rôle</label>
                            <select name="role">
                                <?php foreach (['admin', 'user'] as $role): ?>
                                    <option value="<?= e($role) ?>" <?= (($editUser['role'] ?? 'user') === $role) ? 'selected' : '' ?>>
                                        <?= e($role) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="grid-column:1 / span 2;">
                            <label><?= $editUser ? 'Nouveau mot de passe (laisser vide pour conserver)' : 'Mot de passe' ?></label>
                            <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="save_user" value="1" class="btn btn-success">
                            <?= $editUser ? 'Enregistrer' : 'Créer' ?>
                        </button>

                        <?php if ($editUser): ?>
                            <a href="<?= APP_URL ?>modules/admin/users.php" class="btn btn-outline">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Cette page pilote les comptes applicatifs. Les rôles restent simples et lisibles tant que la matrice fine n’est pas réintroduite.
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Liste des utilisateurs</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int)$user['id'] ?></td>
                            <td><?= e($user['username'] ?? '') ?></td>
                            <td><?= e($user['role'] ?? '') ?></td>
                            <td><?= e($user['created_at'] ?? '') ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= APP_URL ?>modules/admin/users.php?edit=<?= (int)$user['id'] ?>">Modifier</a>
                                    <a class="btn btn-danger" href="<?= APP_URL ?>modules/admin/users.php?delete=<?= (int)$user['id'] ?>" onclick="return confirm('Supprimer cet utilisateur ?');">Supprimer</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$users): ?>
                        <tr><td colspan="5">Aucun utilisateur.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>