<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'admin_roles_manage');

$successMessage = '';
$errorMessage = '';
$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $code = trim((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $sqlDup = "SELECT id FROM roles WHERE code = ?";
        $paramsDup = [$code];
        if ($editId > 0) {
            $sqlDup .= " AND id <> ?";
            $paramsDup[] = $editId;
        }
        $sqlDup .= " LIMIT 1";

        $stmtDup = $pdo->prepare($sqlDup);
        $stmtDup->execute($paramsDup);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code rôle existe déjà.');
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

$roles = $pdo->query("SELECT * FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Rôles';
$pageSubtitle = 'Gestion centralisée des rôles applicatifs.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title"><?= $editRole ? 'Modifier un rôle' : 'Créer un rôle' ?></h3>

                <form method="POST">
                    <?= csrf_input() ?>
                    <?php if ($editRole): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$editRole['id'] ?>">
                    <?php endif; ?>

                    <div>
                        <label>Code</label>
                        <input type="text" name="code" value="<?= e($editRole['code'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e($editRole['label'] ?? '') ?>" required>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_role" value="1" class="btn btn-success"><?= $editRole ? 'Enregistrer' : 'Créer' ?></button>
                        <?php if ($editRole): ?>
                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin/roles.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Les rôles structurent les droits. Les permissions réelles sont ensuite pilotées depuis la matrice d’accès.
                </div>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= e($role['code'] ?? '') ?></td>
                            <td><?= e($role['label'] ?? '') ?></td>
                            <td><?= e($role['created_at'] ?? '') ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin/roles.php?edit=<?= (int)$role['id'] ?>">Modifier</a>
                                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin/access_matrix.php?role_id=<?= (int)$role['id'] ?>">Permissions</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$roles): ?>
                        <tr><td colspan="4">Aucun rôle.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>