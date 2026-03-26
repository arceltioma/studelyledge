<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/header.php';

requirePermission($pdo, 'admin_roles_manage');

$successMessage = $_SESSION['admin_success'] ?? '';
$errorMessage = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$systemRoleCodes = ['super_admin', 'admin', 'manager', 'viewer'];

/*
|--------------------------------------------------------------------------
| Traitement création rôle
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'create_role') {
    $roleCode = clean_input($_POST['role_code'] ?? '');
    $roleName = clean_input($_POST['role_name'] ?? '');

    if ($roleCode === '' || $roleName === '') {
        $errorMessage = 'Le code rôle et le nom rôle sont obligatoires.';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $roleCode)) {
        $errorMessage = 'Le code rôle doit contenir uniquement des lettres minuscules, chiffres et underscores.';
    } else {
        $check = $pdo->prepare("
            SELECT id
            FROM roles
            WHERE role_code = ? OR role_name = ?
            LIMIT 1
        ");
        $check->execute([$roleCode, $roleName]);

        if ($check->fetch()) {
            $errorMessage = 'Un rôle avec ce code ou ce nom existe déjà.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO roles (role_code, role_name, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$roleCode, $roleName]);

            $newRoleId = (int)$pdo->lastInsertId();

            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'create_role',
                'admin',
                'role',
                $newRoleId,
                "Création du rôle {$roleCode} ({$roleName})"
            );

            $_SESSION['admin_success'] = "Le rôle {$roleName} a été créé.";
            header('Location: ' . APP_URL . 'modules/admin/roles.php');
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Traitement modification rôle
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'edit_role') {
    $roleId = (int)($_POST['role_id'] ?? 0);
    $roleName = clean_input($_POST['role_name'] ?? '');

    if ($roleId <= 0 || $roleName === '') {
        $errorMessage = 'Informations de modification invalides.';
    } else {
        $roleStmt = $pdo->prepare("
            SELECT *
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $roleStmt->execute([$roleId]);
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            $errorMessage = 'Rôle introuvable.';
        } else {
            $checkName = $pdo->prepare("
                SELECT id
                FROM roles
                WHERE role_name = ?
                  AND id != ?
                LIMIT 1
            ");
            $checkName->execute([$roleName, $roleId]);

            if ($checkName->fetch()) {
                $errorMessage = 'Un autre rôle porte déjà ce nom.';
            } else {
                $update = $pdo->prepare("
                    UPDATE roles
                    SET role_name = ?
                    WHERE id = ?
                ");
                $update->execute([$roleName, $roleId]);

                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_role',
                    'admin',
                    'role',
                    $roleId,
                    "Modification du rôle {$role['role_code']} vers le nom {$roleName}"
                );

                $_SESSION['admin_success'] = "Le rôle {$role['role_code']} a été mis à jour.";
                header('Location: ' . APP_URL . 'modules/admin/roles.php');
                exit;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Chargement des rôles
|--------------------------------------------------------------------------
*/
$roles = $pdo->query("
    SELECT
        r.*,
        COUNT(DISTINCT u.id) AS total_users,
        COUNT(DISTINCT rp.permission_id) AS total_permissions
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.id
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    GROUP BY r.id, r.role_code, r.role_name, r.created_at
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Permissions disponibles
|--------------------------------------------------------------------------
*/
$permissionsByRole = [];
$permStmt = $pdo->query("
    SELECT
        r.id AS role_id,
        p.permission_code,
        p.permission_name,
        p.module_name
    FROM roles r
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    LEFT JOIN permissions p ON p.id = rp.permission_id
    ORDER BY r.id ASC, p.module_name ASC, p.permission_code ASC
");

while ($row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
    $roleId = (int)$row['role_id'];
    if (!isset($permissionsByRole[$roleId])) {
        $permissionsByRole[$roleId] = [];
    }

    if (!empty($row['permission_code'])) {
        $permissionsByRole[$roleId][] = $row;
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Gestion des rôles', 'Ici, on dessine les contours du pouvoir. Un rôle trop large devient vite une catastrophe bien habillée.'); ?>

        <div class="page-title">

            <div class="btn-group">
                <a href="<?= APP_URL ?>modules/admin/dashboard_admin.php" class="btn btn-outline">Retour admin</a>
                <a href="<?= APP_URL ?>modules/admin/users.php" class="btn btn-secondary">Utilisateurs</a>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="success auto-hide"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error auto-hide"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Créer un rôle</h3>

                <form method="POST">
                    <input type="hidden" name="form_type" value="create_role">

                    <label for="role_code">Code rôle</label>
                    <input
                        type="text"
                        name="role_code"
                        id="role_code"
                        placeholder="Ex : auditor"
                        required
                    >

                    <label for="role_name">Nom lisible</label>
                    <input
                        type="text"
                        name="role_name"
                        id="role_name"
                        placeholder="Ex : Auditeur"
                        required
                    >

                    <div class="warning">
                        Le code rôle doit être stable, technique, et écrit en minuscules avec underscores si besoin.
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Créer le rôle</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Repères utiles</h3>
                <div class="stat-row">
                    <span class="metric-label">Rôles système</span>
                    <span class="metric-value">Protégés</span>
                </div>
                <div class="stat-row">
                    <span class="metric-label">Modification autorisée</span>
                    <span class="metric-value">Nom lisible</span>
                </div>
                <div class="stat-row">
                    <span class="metric-label">Code rôle</span>
                    <span class="metric-value">Stable</span>
                </div>
                <div class="stat-row">
                    <span class="metric-label">Permissions fines</span>
                    <span class="metric-value">Étape suivante</span>
                </div>

                <div class="dashboard-note">
                    Cette page pose la colonne vertébrale des accès. Les permissions détaillées pourront ensuite être pilotées dans une matrice dédiée.
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="page-title" style="margin-bottom:12px;">
                <div>
                    <h3 class="section-title">Rôles existants</h3>
                    <p class="muted">Lecture, édition du nom, visibilité sur l’usage et les permissions.</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code rôle</th>
                        <th>Nom rôle</th>
                        <th>Utilisateurs</th>
                        <th>Permissions</th>
                        <th>Nature</th>
                        <th>Créé le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$roles): ?>
                        <tr>
                            <td colspan="7">Aucun rôle trouvé.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td><?= (int)$role['id'] ?></td>
                                <td><strong><?= e($role['role_code']) ?></strong></td>
                                <td><?= e($role['role_name']) ?></td>
                                <td><?= (int)$role['total_users'] ?></td>
                                <td><?= (int)$role['total_permissions'] ?></td>
                                <td>
                                    <?php if (in_array($role['role_code'], $systemRoleCodes, true)): ?>
                                        <span class="status-pill status-warning">Système</span>
                                    <?php else: ?>
                                        <span class="status-pill status-info">Custom</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($role['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="page-title" style="margin-bottom:12px;">
                <div>
                    <h3 class="section-title">Modifier les noms des rôles</h3>
                    <p class="muted">Le code reste stable ; le libellé peut évoluer pour mieux parler métier.</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Code rôle</th>
                        <th>Nom actuel</th>
                        <th>Modification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td>
                                <strong><?= e($role['role_code']) ?></strong><br>
                                <?php if (in_array($role['role_code'], $systemRoleCodes, true)): ?>
                                    <span class="status-pill status-warning">Rôle système</span>
                                <?php else: ?>
                                    <span class="status-pill status-info">Rôle personnalisé</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($role['role_name']) ?></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="form_type" value="edit_role">
                                    <input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>">

                                    <input
                                        type="text"
                                        name="role_name"
                                        value="<?= e($role['role_name']) ?>"
                                        required
                                    >

                                    <button type="submit" class="btn btn-success">Enregistrer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="page-title" style="margin-bottom:12px;">
                <div>
                    <h3 class="section-title">Permissions par rôle</h3>
                    <p class="muted">Lecture utile avant la future matrice d’accès détaillée.</p>
                </div>
            </div>

            <?php if (!$roles): ?>
                <div class="warning">Aucun rôle disponible.</div>
            <?php else: ?>
                <?php foreach ($roles as $role): ?>
                    <div style="margin-bottom:20px;">
                        <h4>
                            <?= e($role['role_name']) ?>
                            <span class="muted">(<?= e($role['role_code']) ?>)</span>
                        </h4>

                        <?php if (empty($permissionsByRole[(int)$role['id']])): ?>
                            <div class="warning">Aucune permission attribuée à ce rôle.</div>
                        <?php else: ?>
                            <div class="suggestion-list">
                                <?php foreach ($permissionsByRole[(int)$role['id']] as $permission): ?>
                                    <div class="suggestion-chip">
                                        <?= e($permission['module_name']) ?> · <?= e($permission['permission_code']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>