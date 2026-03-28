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

$roles = $pdo->query("SELECT id, code, label FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $pdo->query("SELECT id, code, label FROM permissions ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectedRoleId = (int)($_GET['role_id'] ?? $_POST['role_id'] ?? 0);
if ($selectedRoleId <= 0 && !empty($roles)) {
    $selectedRoleId = (int)$roles[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_matrix'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($selectedRoleId <= 0) {
            throw new RuntimeException('Rôle invalide.');
        }

        $permissionIds = array_map('intval', $_POST['permission_ids'] ?? []);

        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmtDelete->execute([$selectedRoleId]);

        if ($permissionIds) {
            $stmtInsert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $permissionId) {
                if ($permissionId > 0) {
                    $stmtInsert->execute([$selectedRoleId, $permissionId]);
                }
            }
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction($pdo, (int)$_SESSION['user_id'], 'update_access_matrix', 'admin', 'role', $selectedRoleId, 'Mise à jour de la matrice d’accès');
        }

        $pdo->commit();
        $successMessage = 'Matrice mise à jour.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$currentPermissionIds = [];
if ($selectedRoleId > 0) {
    $stmtCurrent = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmtCurrent->execute([$selectedRoleId]);
    $currentPermissionIds = array_map('intval', $stmtCurrent->fetchAll(PDO::FETCH_COLUMN));
}

$pageTitle = 'Matrice d’accès';
$pageSubtitle = 'Affectation des permissions par rôle.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <select name="role_id">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= $selectedRoleId === (int)$role['id'] ? 'selected' : '' ?>>
                            <?= e($role['label']) ?> (<?= e($role['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Charger</button>
            </form>
        </div>

        <div class="form-card">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="role_id" value="<?= (int)$selectedRoleId ?>">

                <h3 class="section-title">Permissions</h3>

                <div class="dashboard-grid-2">
                    <?php foreach ($permissions as $permission): ?>
                        <label style="display:flex;gap:10px;align-items:center;">
                            <input
                                type="checkbox"
                                name="permission_ids[]"
                                value="<?= (int)$permission['id'] ?>"
                                <?= in_array((int)$permission['id'], $currentPermissionIds, true) ? 'checked' : '' ?>
                            >
                            <?= e($permission['label']) ?> <span class="muted">(<?= e($permission['code']) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="btn-group">
                    <button type="submit" name="save_matrix" value="1" class="btn btn-success">Enregistrer la matrice</button>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>