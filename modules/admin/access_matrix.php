<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'admin_roles_manage');

if (!function_exists('aam_fetch_roles')) {
    function aam_fetch_roles(PDO $pdo): array
    {
        return $pdo->query("
            SELECT id, code, label
            FROM roles
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('aam_fetch_permissions')) {
    function aam_fetch_permissions(PDO $pdo): array
    {
        return $pdo->query("
            SELECT id, code, label
            FROM permissions
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('aam_find_role')) {
    function aam_find_role(PDO $pdo, int $roleId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, code, label
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('aam_fetch_current_permission_ids')) {
    function aam_fetch_current_permission_ids(PDO $pdo, int $roleId): array
    {
        $stmt = $pdo->prepare("
            SELECT permission_id
            FROM role_permissions
            WHERE role_id = ?
        ");
        $stmt->execute([$roleId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (!function_exists('aam_group_permissions')) {
    function aam_group_permissions(array $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $code = (string)($permission['code'] ?? '');
            $parts = explode('_', $code);
            $group = $parts[0] ?? 'other';

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = $permission;
        }

        ksort($grouped);
        return $grouped;
    }
}

if (!function_exists('aam_permission_matches_search')) {
    function aam_permission_matches_search(array $permission, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = mb_strtolower(
            trim(
                (string)($permission['label'] ?? '')
                . ' '
                . (string)($permission['code'] ?? '')
            ),
            'UTF-8'
        );

        return mb_strpos($haystack, mb_strtolower($search, 'UTF-8')) !== false;
    }
}

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$roles = aam_fetch_roles($pdo);
$permissions = aam_fetch_permissions($pdo);

$selectedRoleId = (int)($_GET['role_id'] ?? $_POST['role_id'] ?? 0);
if ($selectedRoleId <= 0 && !empty($roles)) {
    $selectedRoleId = (int)$roles[0]['id'];
}

$filterPermissionSearch = trim((string)($_GET['filter_permission_search'] ?? $_POST['filter_permission_search'] ?? ''));
$filterGroup = trim((string)($_GET['filter_group'] ?? $_POST['filter_group'] ?? ''));
$filterChecked = trim((string)($_GET['filter_checked'] ?? $_POST['filter_checked'] ?? ''));

$currentPermissionIds = $selectedRoleId > 0 ? aam_fetch_current_permission_ids($pdo, $selectedRoleId) : [];
$formPermissionIds = $currentPermissionIds;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($selectedRoleId <= 0) {
            throw new RuntimeException('Rôle invalide.');
        }

        $selectedRole = aam_find_role($pdo, $selectedRoleId);
        if (!$selectedRole) {
            throw new RuntimeException('Rôle introuvable.');
        }

        $formPermissionIds = array_values(array_unique(array_filter(array_map('intval', $_POST['permission_ids'] ?? []), fn($v) => $v > 0)));
        $action = (string)$_POST['form_action'];

        if ($action === 'preview') {
            $selectedPermissions = array_values(array_filter($permissions, function (array $permission) use ($formPermissionIds): bool {
                return in_array((int)$permission['id'], $formPermissionIds, true);
            }));

            $previewMode = true;
            $previewData = [
                'role' => $selectedRole,
                'permission_count' => count($formPermissionIds),
                'permissions' => $selectedPermissions,
                'grouped' => aam_group_permissions($selectedPermissions),
            ];
        } elseif ($action === 'save_matrix') {
            $pdo->beginTransaction();

            $stmtDelete = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmtDelete->execute([$selectedRoleId]);

            if ($formPermissionIds) {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO role_permissions (role_id, permission_id)
                    VALUES (?, ?)
                ");

                foreach ($formPermissionIds as $permissionId) {
                    $stmtInsert->execute([$selectedRoleId, $permissionId]);
                }
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'update_access_matrix',
                    'admin',
                    'role',
                    $selectedRoleId,
                    'Mise à jour de la matrice d’accès'
                );
            }

            $pdo->commit();
            $successMessage = 'Matrice mise à jour.';
            $currentPermissionIds = aam_fetch_current_permission_ids($pdo, $selectedRoleId);
            $formPermissionIds = $currentPermissionIds;
            $previewMode = false;
            $previewData = null;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$selectedRole = $selectedRoleId > 0 ? aam_find_role($pdo, $selectedRoleId) : null;
$groupedPermissions = aam_group_permissions($permissions);

$filteredGroupedPermissions = [];
foreach ($groupedPermissions as $group => $items) {
    if ($filterGroup !== '' && $filterGroup !== $group) {
        continue;
    }

    $kept = [];
    foreach ($items as $permission) {
        $permissionId = (int)($permission['id'] ?? 0);
        $isChecked = in_array($permissionId, $formPermissionIds, true);

        if ($filterChecked === 'checked' && !$isChecked) {
            continue;
        }
        if ($filterChecked === 'unchecked' && $isChecked) {
            continue;
        }
        if (!aam_permission_matches_search($permission, $filterPermissionSearch)) {
            continue;
        }

        $kept[] = $permission;
    }

    if ($kept) {
        $filteredGroupedPermissions[$group] = $kept;
    }
}

$dashboard = [
    'total_permissions' => count($permissions),
    'checked_permissions' => count($formPermissionIds),
    'unchecked_permissions' => max(0, count($permissions) - count($formPermissionIds)),
    'groups_count' => count($groupedPermissions),
    'visible_groups_count' => count($filteredGroupedPermissions),
];

$pageTitle = 'Matrice d’accès';
$pageSubtitle = 'Affectation des permissions par rôle.';
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

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Dashboard matrice</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Permissions totales</span><strong><?= (int)$dashboard['total_permissions'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Cochées pour ce rôle</span><strong><?= (int)$dashboard['checked_permissions'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Non cochées</span><strong><?= (int)$dashboard['unchecked_permissions'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Groupes disponibles</span><strong><?= (int)$dashboard['groups_count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Groupes visibles</span><strong><?= (int)$dashboard['visible_groups_count'] ?></strong></div>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Prévisualisation</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Rôle</span>
                            <strong><?= e(($previewData['role']['label'] ?? '') . ' (' . ($previewData['role']['code'] ?? '') . ')') ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Nombre de permissions</span>
                            <strong><?= (int)$previewData['permission_count'] ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($previewData['grouped'])): ?>
                        <?php foreach ($previewData['grouped'] as $group => $items): ?>
                            <div class="table-card" style="margin-top:14px;">
                                <h4 class="section-title"><?= e(strtoupper($group)) ?></h4>
                                <ul style="margin:0; padding-left:18px;">
                                    <?php foreach ($items as $permission): ?>
                                        <li><?= e($permission['label']) ?> <span class="muted">(<?= e($permission['code']) ?>)</span></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dashboard-note" style="margin-top:14px;">Aucune permission sélectionnée.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dashboard-note">
                        Cette matrice permet d’affecter les permissions à un rôle. Les utilisateurs rattachés à ce rôle héritent ensuite de ces accès.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card" style="margin-bottom:20px;">
            <form method="GET" class="inline-form">
                <select name="role_id">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= $selectedRoleId === (int)$role['id'] ? 'selected' : '' ?>>
                            <?= e($role['label']) ?> (<?= e($role['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="filter_permission_search" value="<?= e($filterPermissionSearch) ?>" placeholder="Rechercher une permission">

                <select name="filter_group">
                    <option value="">Tous les groupes</option>
                    <?php foreach (array_keys($groupedPermissions) as $group): ?>
                        <option value="<?= e($group) ?>" <?= $filterGroup === $group ? 'selected' : '' ?>>
                            <?= e(strtoupper($group)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="filter_checked">
                    <option value="">Toutes</option>
                    <option value="checked" <?= $filterChecked === 'checked' ? 'selected' : '' ?>>Cochées</option>
                    <option value="unchecked" <?= $filterChecked === 'unchecked' ? 'selected' : '' ?>>Non cochées</option>
                </select>

                <button type="submit" class="btn btn-secondary">Charger / filtrer</button>
                <a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php?role_id=<?= (int)$selectedRoleId ?>" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <div class="form-card">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="role_id" value="<?= (int)$selectedRoleId ?>">
                <input type="hidden" name="filter_permission_search" value="<?= e($filterPermissionSearch) ?>">
                <input type="hidden" name="filter_group" value="<?= e($filterGroup) ?>">
                <input type="hidden" name="filter_checked" value="<?= e($filterChecked) ?>">

                <h3 class="section-title">
                    Permissions<?= $selectedRole ? ' - ' . e($selectedRole['label'] . ' (' . $selectedRole['code'] . ')') : '' ?>
                </h3>

                <?php foreach ($filteredGroupedPermissions as $group => $items): ?>
                    <div class="table-card" style="margin-top:14px;">
                        <h4 class="section-title" style="margin-bottom:10px;"><?= e(strtoupper($group)) ?></h4>

                        <div class="dashboard-grid-2">
                            <?php foreach ($items as $permission): ?>
                                <label style="display:flex;gap:10px;align-items:center;">
                                    <input
                                        type="checkbox"
                                        name="permission_ids[]"
                                        value="<?= (int)$permission['id'] ?>"
                                        <?= in_array((int)$permission['id'], $formPermissionIds, true) ? 'checked' : '' ?>
                                    >
                                    <?= e($permission['label']) ?>
                                    <span class="muted">(<?= e($permission['code']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$filteredGroupedPermissions): ?>
                    <div class="dashboard-note" style="margin-top:16px;">Aucune permission ne correspond aux filtres actuels.</div>
                <?php endif; ?>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" name="form_action" value="preview" class="btn btn-secondary">Prévisualiser</button>
                    <button type="submit" name="form_action" value="save_matrix" class="btn btn-success">Enregistrer la matrice</button>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>