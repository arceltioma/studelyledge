<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_roles_manage');

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

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ? LIMIT 1");
        $stmt->execute([$deleteId]);
        header('Location: ' . APP_URL . 'modules/admin/roles.php?ok=deleted');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role'])) {
    try {
        $code = trim((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id FROM roles
            WHERE code = ?
            " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $params = [$code];
        if ($editId > 0) $params[] = $editId;
        $stmtDup->execute($params);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce rôle existe déjà.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare("UPDATE roles SET code = ?, label = ? WHERE id = ?");
            $stmt->execute([$code, $label, $editId]);
            $successMessage = 'Rôle mis à jour.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO roles (code, label, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$code, $label]);
            $successMessage = 'Rôle créé.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editRole = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editRole = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = $pdo->query("SELECT * FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Rôles',
        'Création, modification et suppression des rôles applicatifs.'
    ); ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'deleted'): ?>
        <div class="success">Rôle supprimé.</div>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

    <div class="dashboard-grid-2">
        <div class="form-card">
            <h3 class="section-title"><?= $editRole ? 'Modifier un rôle' : 'Créer un rôle' ?></h3>
            <form method="POST">
                <?php if ($editRole): ?>
                    <input type="hidden" name="edit_id" value="<?= (int)$editRole['id'] ?>">
                <?php endif; ?>

                <div class="dashboard-grid-2">
                    <div>
                        <label>Code</label>
                        <input type="text" name="code" value="<?= e($editRole['code'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e($editRole['label'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" name="save_role" value="1" class="btn btn-success"><?= $editRole ? 'Enregistrer' : 'Créer' ?></button>
                    <?php if ($editRole): ?>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin/roles.php">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="dashboard-panel">
            <h3 class="section-title">Principe</h3>
            <div class="dashboard-note">
                Les rôles servent de conteneurs d’accès. La matrice d’accès permet ensuite de cocher précisément les permissions attribuées.
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['code'] ?? '') ?></td>
                        <td><?= e($row['label'] ?? '') ?></td>
                        <td>
                            <div class="btn-group">
                                <a class="btn btn-secondary" href="<?= APP_URL ?>modules/admin/roles.php?edit=<?= (int)$row['id'] ?>">Modifier</a>
                                <a class="btn btn-danger" href="<?= APP_URL ?>modules/admin/roles.php?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Supprimer ce rôle ?');">Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="3">Aucun rôle.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>