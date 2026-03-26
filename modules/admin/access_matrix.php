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

if (tableExists($pdo, 'permissions') === false) {
    $pdo->exec("
        CREATE TABLE permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(120) NOT NULL UNIQUE,
            label VARCHAR(180) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

if (tableExists($pdo, 'role_permissions') === false) {
    $pdo->exec("
        CREATE TABLE role_permissions (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY(role_id, permission_id),
            CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

$permissionSeeds = [
    ['dashboard_view', 'Voir dashboard'],
    ['clients_view', 'Voir clients'],
    ['clients_create', 'Créer / modifier clients'],
    ['operations_view', 'Voir opérations'],
    ['operations_create', 'Créer / modifier opérations'],
    ['treasury_view', 'Gérer comptes internes'],
    ['imports_preview', 'Prévisualiser imports'],
    ['imports_validate', 'Valider imports'],
    ['imports_journal', 'Voir journal imports'],
    ['statements_export', 'Exporter relevés et fiches'],
    ['admin_dashboard_view', 'Voir dashboard admin technique'],
    ['admin_users_manage', 'Gérer utilisateurs'],
    ['admin_roles_manage', 'Gérer rôles'],
    ['admin_logs_view', 'Voir logs'],
];

foreach ($permissionSeeds as [$code, $label]) {
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO permissions (code, label, created_at) VALUES (?, ?, NOW())");
        $insert->execute([$code, $label]);
    }
}

$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleId = (int)($_POST['role_id'] ?? 0);
    $permissionIds = array_map('intval', $_POST['permission_ids'] ?? []);

    if ($roleId > 0) {
        $pdo->beginTransaction();
        $stmtDelete = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmtDelete->execute([$roleId]);

        foreach ($permissionIds as $permissionId) {
            $stmtInsert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmtInsert->execute([$roleId, $permissionId]);
        }

        $pdo->commit();
        $successMessage = 'Matrice mise à jour.';
    }
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectedRoleId = (int)($_GET['role_id'] ?? $_POST['role_id'] ?? ($roles[0]['id'] ?? 0));

$assigned = [];
if ($selectedRoleId > 0) {
    $stmtAssigned = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmtAssigned->execute([$selectedRoleId]);
    $assigned = array_map('intval', $stmtAssigned->fetchAll(PDO::FETCH_COLUMN));
}
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Matrice d’accès',
        'Liste des accès possibles à cocher et affecter à chaque rôle.'
    ); ?>

    <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <div>
                <label>Rôle</label>
                <select name="role_id" onchange="this.form.submit()">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= $selectedRoleId === (int)$role['id'] ? 'selected' : '' ?>>
                            <?= e($role['label'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="table-card" style="margin-top:20px;padding:0;box-shadow:none;border:none;">
                <table>
                    <thead>
                        <tr>
                            <th>Accorder</th>
                            <th>Code</th>
                            <th>Libellé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions as $permission): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="permission_ids[]" value="<?= (int)$permission['id'] ?>" <?= in_array((int)$permission['id'], $assigned, true) ? 'checked' : '' ?>>
                                </td>
                                <td><?= e($permission['code'] ?? '') ?></td>
                                <td><?= e($permission['label'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$permissions): ?>
                            <tr><td colspan="3">Aucune permission.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="btn-group" style="margin-top:20px;">
                <button type="submit" class="btn btn-success">Enregistrer la matrice</button>
            </div>
        </form>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>