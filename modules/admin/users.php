<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_users_manage');

require_once __DIR__ . '/../../includes/header.php';

if (tableExists($pdo, 'roles') === false) {
    $pdo->exec("
        CREATE TABLE roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(150) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

if (tableExists($pdo, 'users') && columnExists($pdo, 'users', 'role_id') === false) {
    $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT NULL AFTER role");
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL");
}

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

$roles = $pdo->query("SELECT * FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    try {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleId = ($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : null;

        if ($username === '') {
            throw new RuntimeException('Le nom d’utilisateur est obligatoire.');
        }

        if ($roleId === null) {
            throw new RuntimeException('Le rôle est obligatoire.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id FROM users
            WHERE username = ?
            " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $params = [$username];
        if ($editId > 0) $params[] = $editId;
        $stmtDup->execute($params);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce nom d’utilisateur existe déjà.');
        }

        if ($editId > 0) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role_id = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, $roleId, $hash, $editId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role_id = ? WHERE id = ?");
                $stmt->execute([$username, $roleId, $editId]);
            }
            $successMessage = 'Utilisateur mis à jour.';
        } else {
            if ($password === '') {
                throw new RuntimeException('Le mot de passe est obligatoire.');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, role_id, created_at) VALUES (?, ?, 'user', ?, NOW())");
            $stmt->execute([$username, $hash, $roleId]);
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

$rows = $pdo->query("
    SELECT u.*, r.label AS role_label
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Utilisateurs',
        'Création, modification et suppression des comptes avec affectation d’un rôle.'
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
                        <select name="role_id" required>
                            <option value="">Choisir</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>" <?= (string)($editUser['role_id'] ?? '') === (string)$role['id'] ? 'selected' : '' ?>>
                                    <?= e($role['label'] ?? '') ?>
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
                    <button type="submit" name="save_user" value="1" class="btn btn-success"><?= $editUser ? 'Enregistrer' : 'Créer' ?></button>
                    <?php if ($editUser): ?>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin/users.php">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="dashboard-panel">
            <h3 class="section-title">Rattachement</h3>
            <div class="dashboard-note">
                Chaque utilisateur reçoit un rôle. Ce rôle détermine ensuite ses accès possibles via la matrice.
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Rôle</th>
                    <th>Créé le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['username'] ?? '') ?></td>
                        <td><?= e($row['role_label'] ?? '') ?></td>
                        <td><?= e($row['created_at'] ?? '') ?></td>
                        <td>
                            <div class="btn-group">
                                <a class="btn btn-secondary" href="<?= APP_URL ?>modules/admin/users.php?edit=<?= (int)$row['id'] ?>">Modifier</a>
                                <a class="btn btn-danger" href="<?= APP_URL ?>modules/admin/users.php?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Supprimer cet utilisateur ?');">Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="4">Aucun utilisateur.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>