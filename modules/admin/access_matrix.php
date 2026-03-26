<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'admin_roles_manage';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$successMessage = $_SESSION['admin_success'] ?? '';
$errorMessage = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$roles = $pdo->query("
    SELECT id, role_code, role_name
    FROM roles
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$permissions = $pdo->query("
    SELECT id, permission_code, permission_name, module_name
    FROM permissions
    ORDER BY module_name ASC, permission_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$permissionsByModule = [];
foreach ($permissions as $permission) {
    $module = $permission['module_name'] ?: 'autre';
    $permissionsByModule[$module][] = $permission;
}

$currentMatrix = [];
$matrixRows = $pdo->query("
    SELECT role_id, permission_id
    FROM role_permissions
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($matrixRows as $row) {
    $currentMatrix[(int)$row['role_id']][(int)$row['permission_id']] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedMatrix = $_POST['matrix'] ?? [];

    try {
        $pdo->beginTransaction();

        $pdo->exec("DELETE FROM role_permissions");

        $insert = $pdo->prepare("
            INSERT INTO role_permissions (role_id, permission_id, created_at)
            VALUES (?, ?, NOW())
        ");

        foreach ($roles as $role) {
            $roleId = (int)$role['id'];

            if (!isset($postedMatrix[$roleId]) || !is_array($postedMatrix[$roleId])) {
                continue;
            }

            foreach ($postedMatrix[$roleId] as $permissionId => $value) {
                $permissionId = (int)$permissionId;
                if ($permissionId <= 0) {
                    continue;
                }

                $insert->execute([$roleId, $permissionId]);
            }
        }

        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'update_access_matrix',
            'admin',
            'role_permissions',
            null,
            'Sauvegarde complète de la matrice des accès'
        );

        $pdo->commit();

        $_SESSION['admin_success'] = 'La matrice des accès a été sauvegardée avec succès.';
        header('Location: ' . APP_URL . 'modules/admin/access_matrix.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Matrice des accès', 'Pilotage complet des permissions de l’application.'); ?>

        <?php if ($successMessage): ?>
            <div class="success auto-hide"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Permission</th>
                            <th>Code</th>
                            <?php foreach ($roles as $role): ?>
                                <th><?= e($role['role_name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissionsByModule as $moduleName => $modulePermissions): ?>
                            <?php foreach ($modulePermissions as $index => $permission): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td rowspan="<?= count($modulePermissions) ?>">
                                            <span class="status-pill status-info"><?= e($moduleName) ?></span>
                                        </td>
                                    <?php endif; ?>

                                    <td><?= e($permission['permission_name']) ?></td>
                                    <td><code><?= e($permission['permission_code']) ?></code></td>

                                    <?php foreach ($roles as $role): ?>
                                        <?php
                                            $roleId = (int)$role['id'];
                                            $permissionId = (int)$permission['id'];
                                            $isChecked = !empty($currentMatrix[$roleId][$permissionId]);
                                        ?>
                                        <td style="text-align:center;">
                                            <input
                                                type="checkbox"
                                                name="matrix[<?= $roleId ?>][<?= $permissionId ?>]"
                                                value="1"
                                                <?= $isChecked ? 'checked' : '' ?>
                                                style="width:auto;margin:0;transform:scale(1.15);"
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Sauvegarder les droits</button>
                </div>
            </div>
        </form>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>