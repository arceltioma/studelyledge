<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceCurrentPageAccess')) {
    studelyEnforceCurrentPageAccess($pdo);
} else {
    if (function_exists('studelyEnforceAccess')) {
        studelyEnforceAccess($pdo, 'access_matrix_manage_page');
    } else {
        enforcePagePermission($pdo, 'access_matrix_manage_page');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('studelyEnforceActionAccess')) {
        studelyEnforceActionAccess($pdo, 'access_matrix_manage');
    } else {
        if (function_exists('studelyEnforceAccess')) {
            studelyEnforceAccess($pdo, 'access_matrix_manage');
        } else {
            enforcePagePermission($pdo, 'access_matrix_manage');
        }
    }
}

if (!function_exists('aam_fetch_roles')) {
    function aam_fetch_roles(PDO $pdo): array
    {
        if (!tableExists($pdo, 'roles')) {
            return [];
        }

        return $pdo->query("
            SELECT id, code, label
            FROM roles
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('aam_fetch_permissions')) {
    function aam_fetch_permissions(PDO $pdo): array
    {
        if (!tableExists($pdo, 'permissions')) {
            return [];
        }

        return $pdo->query("
            SELECT id, code, label
            FROM permissions
            ORDER BY code ASC, label ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('aam_find_role')) {
    function aam_find_role(PDO $pdo, int $roleId): ?array
    {
        if ($roleId <= 0 || !tableExists($pdo, 'roles')) {
            return null;
        }

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
        if ($roleId <= 0 || !tableExists($pdo, 'role_permissions')) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT permission_id
            FROM role_permissions
            WHERE role_id = ?
        ");
        $stmt->execute([$roleId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

if (!function_exists('aam_permission_group_key')) {
    function aam_permission_group_key(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return 'other';
        }

        $parts = explode('_', $code);
        return $parts[0] !== '' ? $parts[0] : 'other';
    }
}

if (!function_exists('aam_group_label')) {
    function aam_group_label(string $group): string
    {
        $map = [
            'dashboard' => 'Dashboard',
            'analytics' => 'Analytics',
            'global' => 'Recherche globale',
            'clients' => 'Clients',
            'client' => 'Clients',
            'operations' => 'Opérations',
            'operation' => 'Opérations',
            'manual' => 'Actions manuelles',
            'imports' => 'Imports',
            'monthly' => 'Mensualités',
            'pending' => 'Débits dus',
            'treasury' => 'Trésorerie',
            'bank' => 'Banques',
            'service' => 'Comptes 706 / Services',
            'statements' => 'Relevés / Exports',
            'account' => 'Comptes / États',
            'bulk' => 'Exports de masse',
            'generate' => 'Génération PDF',
            'notifications' => 'Notifications',
            'support' => 'Support',
            'admin' => 'Administration',
            'roles' => 'Rôles',
            'users' => 'Utilisateurs',
            'user' => 'Utilisateurs / Logs',
            'audit' => 'Audit',
            'intelligence' => 'Centre d’intelligence',
            'settings' => 'Paramètres',
            'statuses' => 'Statuts',
            'categories' => 'Catégories',
            'services' => 'Services',
            'catalogs' => 'Catalogues',
            'accounts' => 'Comptes fonctionnels',
            'accounting' => 'Règles comptables',
            'access' => 'Matrice d’accès',
            'other' => 'Autres',
        ];

        return $map[$group] ?? ucfirst($group);
    }
}

if (!function_exists('aam_group_permissions')) {
    function aam_group_permissions(array $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $code = (string)($permission['code'] ?? '');
            $group = aam_permission_group_key($code);

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = $permission;
        }

        uksort($grouped, function (string $a, string $b): int {
            return strcmp(aam_group_label($a), aam_group_label($b));
        });

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

if (!function_exists('aam_sort_permissions_for_display')) {
    function aam_sort_permissions_for_display(array $items, array $checkedIds): array
    {
        usort($items, function (array $a, array $b) use ($checkedIds): int {
            $aChecked = in_array((int)($a['id'] ?? 0), $checkedIds, true) ? 1 : 0;
            $bChecked = in_array((int)($b['id'] ?? 0), $checkedIds, true) ? 1 : 0;

            if ($aChecked !== $bChecked) {
                return $bChecked <=> $aChecked;
            }

            return strcmp(
                (string)($a['code'] ?? ''),
                (string)($b['code'] ?? '')
            );
        });

        return $items;
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
$showOnlyVisibleGroupsOpen = trim((string)($_GET['expanded'] ?? $_POST['expanded'] ?? '1'));

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

        $formPermissionIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $_POST['permission_ids'] ?? []),
                    fn ($v) => $v > 0
                )
            )
        );

        $action = (string)($_POST['form_action'] ?? '');

        if ($action === 'preview') {
            $selectedPermissions = array_values(array_filter($permissions, function (array $permission) use ($formPermissionIds): bool {
                return in_array((int)($permission['id'] ?? 0), $formPermissionIds, true);
            }));

            $previewMode = true;
            $previewData = [
                'role' => $selectedRole,
                'permission_count' => count($formPermissionIds),
                'permissions' => $selectedPermissions,
                'grouped' => aam_group_permissions($selectedPermissions),
            ];
        } elseif ($action === 'save_matrix') {
            if (!tableExists($pdo, 'role_permissions')) {
                throw new RuntimeException('Table role_permissions introuvable.');
            }

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

            $successMessage = 'Matrice mise à jour avec succès.';
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
        $filteredGroupedPermissions[$group] = aam_sort_permissions_for_display($kept, $formPermissionIds);
    }
}

$totalPermissions = count($permissions);
$checkedPermissions = count($formPermissionIds);
$uncheckedPermissions = max(0, $totalPermissions - $checkedPermissions);
$visiblePermissions = 0;
foreach ($filteredGroupedPermissions as $items) {
    $visiblePermissions += count($items);
}

$dashboard = [
    'total_permissions' => $totalPermissions,
    'checked_permissions' => $checkedPermissions,
    'unchecked_permissions' => $uncheckedPermissions,
    'groups_count' => count($groupedPermissions),
    'visible_groups_count' => count($filteredGroupedPermissions),
    'visible_permissions' => $visiblePermissions,
];

$pageTitle = 'Matrice d’accès';
$pageSubtitle = 'Affectation dense, filtrable et pilotable des permissions par rôle.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .aam-toolbar,
            .aam-filters,
            .aam-panel,
            .aam-group-card,
            .aam-preview-card {
                border-radius: 18px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.18);
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            }

            .aam-filters,
            .aam-panel,
            .aam-preview-card,
            .aam-toolbar {
                padding: 18px;
            }

            .aam-toolbar {
                margin-bottom: 20px;
            }

            .aam-role-line {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: center;
                justify-content: space-between;
            }

            .aam-role-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                border-radius: 999px;
                background: rgba(79, 70, 229, 0.08);
                color: #4338ca;
                font-weight: 700;
                font-size: 13px;
            }

            .aam-top-grid {
                display: grid;
                grid-template-columns: 1.05fr .95fr;
                gap: 18px;
                margin-bottom: 20px;
            }

            @media (max-width: 1100px) {
                .aam-top-grid {
                    grid-template-columns: 1fr;
                }
            }

            .aam-kpi-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 900px) {
                .aam-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .aam-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .aam-kpi {
                border-radius: 18px;
                padding: 16px;
                background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(241,245,249,1) 100%);
                border: 1px solid rgba(148, 163, 184, 0.14);
            }

            .aam-kpi__label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .aam-kpi__value {
                font-size: 28px;
                font-weight: 800;
                line-height: 1;
                color: #0f172a;
                display: block;
            }

            .aam-kpi__meta {
                display: block;
                margin-top: 8px;
                color: #64748b;
                font-size: 12px;
            }

            .aam-filters {
                margin-bottom: 20px;
            }

            .aam-grid-filters {
                display: grid;
                grid-template-columns: 1.3fr 1fr 1fr .7fr;
                gap: 14px;
            }

            @media (max-width: 980px) {
                .aam-grid-filters {
                    grid-template-columns: 1fr 1fr;
                }
            }

            @media (max-width: 640px) {
                .aam-grid-filters {
                    grid-template-columns: 1fr;
                }
            }

            .aam-panels-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .aam-group-card {
                overflow: hidden;
            }

            .aam-group-head {
                padding: 14px 18px;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            }

            .aam-group-title-wrap {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .aam-group-title {
                margin: 0;
                font-size: 16px;
                font-weight: 800;
                color: #0f172a;
            }

            .aam-count-pill {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 34px;
                padding: 4px 10px;
                border-radius: 999px;
                background: rgba(79, 70, 229, 0.08);
                color: #4338ca;
                font-size: 12px;
                font-weight: 800;
            }

            .aam-group-tools {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .aam-group-body {
                padding: 16px;
            }

            .aam-permissions-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 10px;
            }

            .aam-permission-item {
                position: relative;
            }

            .aam-permission-label {
                min-height: 72px;
                display: flex;
                gap: 12px;
                align-items: flex-start;
                padding: 12px 14px;
                border-radius: 14px;
                border: 1px solid rgba(148, 163, 184, 0.18);
                background: #fff;
                cursor: pointer;
                transition: all .18s ease;
            }

            .aam-permission-label:hover {
                border-color: rgba(79, 70, 229, 0.35);
                box-shadow: 0 8px 18px rgba(79, 70, 229, 0.08);
                transform: translateY(-1px);
            }

            .aam-permission-item input[type="checkbox"] {
                margin-top: 3px;
                width: 17px;
                height: 17px;
                flex: 0 0 17px;
                accent-color: #4f46e5;
            }

            .aam-permission-text {
                min-width: 0;
            }

            .aam-permission-name {
                display: block;
                font-size: 13px;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.35;
            }

            .aam-permission-code {
                display: inline-block;
                margin-top: 4px;
                font-size: 11px;
                color: #64748b;
                word-break: break-word;
            }

            .aam-permission-item.is-checked .aam-permission-label {
                background: rgba(16, 185, 129, 0.05);
                border-color: rgba(16, 185, 129, 0.30);
            }

            .aam-permission-item.is-checked .aam-permission-name {
                color: #065f46;
            }

            .aam-actions-sticky {
                position: sticky;
                bottom: 16px;
                z-index: 10;
                margin-top: 20px;
                padding: 14px 16px;
                border-radius: 18px;
                background: rgba(255,255,255,0.94);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(148, 163, 184, 0.18);
                box-shadow: 0 18px 36px rgba(15, 23, 42, 0.10);
            }

            .aam-note {
                color: #64748b;
                font-size: 13px;
                line-height: 1.5;
            }

            .aam-preview-list {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 12px;
                margin-top: 14px;
            }

            .aam-preview-subcard {
                border-radius: 14px;
                border: 1px solid rgba(148, 163, 184, 0.16);
                background: #fff;
                padding: 12px;
            }

            .aam-preview-subcard h4 {
                margin: 0 0 8px;
                font-size: 13px;
                font-weight: 800;
                color: #334155;
            }

            .aam-preview-subcard ul {
                margin: 0;
                padding-left: 18px;
            }

            .aam-preview-subcard li {
                margin-bottom: 4px;
                font-size: 13px;
                color: #0f172a;
            }

            .aam-empty {
                padding: 18px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.8);
                border: 1px dashed rgba(148, 163, 184, 0.35);
                color: #64748b;
            }
        </style>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <section class="aam-toolbar">
            <div class="aam-role-line">
                <div>
                    <h2 style="margin:0 0 6px;">Pilotage de la matrice d’accès</h2>
                    <div class="aam-note">
                        Gestion centralisée des accès par rôle, avec filtres, densité renforcée et pilotage compact.
                    </div>
                </div>

                <?php if ($selectedRole): ?>
                    <span class="aam-role-badge">
                        Rôle actif :
                        <?= e((string)$selectedRole['label']) ?>
                        (<?= e((string)$selectedRole['code']) ?>)
                    </span>
                <?php endif; ?>
            </div>
        </section>

        <div class="aam-top-grid">
            <section class="aam-panel">
                <h3 class="section-title" style="margin-top:0;">Vue synthétique</h3>

                <div class="aam-kpi-grid">
                    <div class="aam-kpi">
                        <span class="aam-kpi__label">Permissions totales</span>
                        <span class="aam-kpi__value"><?= (int)$dashboard['total_permissions'] ?></span>
                        <span class="aam-kpi__meta">Référentiel complet</span>
                    </div>

                    <div class="aam-kpi">
                        <span class="aam-kpi__label">Cochées</span>
                        <span class="aam-kpi__value"><?= (int)$dashboard['checked_permissions'] ?></span>
                        <span class="aam-kpi__meta">Pour le rôle courant</span>
                    </div>

                    <div class="aam-kpi">
                        <span class="aam-kpi__label">Non cochées</span>
                        <span class="aam-kpi__value"><?= (int)$dashboard['unchecked_permissions'] ?></span>
                        <span class="aam-kpi__meta">À arbitrer si besoin</span>
                    </div>

                    <div class="aam-kpi">
                        <span class="aam-kpi__label">Groupes</span>
                        <span class="aam-kpi__value"><?= (int)$dashboard['groups_count'] ?></span>
                        <span class="aam-kpi__meta">Tous groupes disponibles</span>
                    </div>

                    <div class="aam-kpi">
                        <span class="aam-kpi__label">Groupes visibles</span>
                        <span class="aam-kpi__value"><?= (int)$dashboard['visible_groups_count'] ?></span>
                        <span class="aam-kpi__meta">Après filtres</span>
                    </div>

                    <div class="aam-kpi">
                        <span class="aam-kpi__label">Permissions visibles</span>
                        <span class="aam-kpi__value"><?= (int)$dashboard['visible_permissions'] ?></span>
                        <span class="aam-kpi__meta">Éléments actuellement affichés</span>
                    </div>
                </div>
            </section>

            <section class="aam-preview-card">
                <h3 class="section-title" style="margin-top:0;">Prévisualisation</h3>

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
                        <div class="aam-preview-list">
                            <?php foreach ($previewData['grouped'] as $group => $items): ?>
                                <div class="aam-preview-subcard">
                                    <h4><?= e(aam_group_label((string)$group)) ?> (<?= count($items) ?>)</h4>
                                    <ul>
                                        <?php foreach (array_slice($items, 0, 8) as $permission): ?>
                                            <li>
                                                <?= e((string)($permission['label'] ?? '')) ?>
                                                <span class="muted">(<?= e((string)($permission['code'] ?? '')) ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (count($items) > 8): ?>
                                        <div class="muted" style="margin-top:8px;">+<?= count($items) - 8 ?> autre(s)</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="aam-empty" style="margin-top:14px;">Aucune permission sélectionnée.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="aam-note">
                        Cette matrice pilote les permissions réellement attribuées aux rôles.  
                        Utilise les filtres pour cibler rapidement un domaine, puis prévisualise avant enregistrement.
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="aam-filters">
            <form method="GET">
                <div class="aam-grid-filters">
                    <div>
                        <label>Rôle</label>
                        <select name="role_id">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>" <?= $selectedRoleId === (int)$role['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$role['label']) ?> (<?= e((string)$role['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Recherche permission</label>
                        <input
                            type="text"
                            name="filter_permission_search"
                            value="<?= e($filterPermissionSearch) ?>"
                            placeholder="Ex: clients_edit, dashboard, import..."
                        >
                    </div>

                    <div>
                        <label>Groupe</label>
                        <select name="filter_group">
                            <option value="">Tous les groupes</option>
                            <?php foreach (array_keys($groupedPermissions) as $group): ?>
                                <option value="<?= e((string)$group) ?>" <?= $filterGroup === $group ? 'selected' : '' ?>>
                                    <?= e(aam_group_label((string)$group)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="filter_checked">
                            <option value="">Toutes</option>
                            <option value="checked" <?= $filterChecked === 'checked' ? 'selected' : '' ?>>Cochées</option>
                            <option value="unchecked" <?= $filterChecked === 'unchecked' ? 'selected' : '' ?>>Non cochées</option>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Charger / filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php?role_id=<?= (int)$selectedRoleId ?>" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </section>

        <section class="aam-panel">
            <form method="POST" id="access-matrix-form">
                <?= csrf_input() ?>

                <input type="hidden" name="role_id" value="<?= (int)$selectedRoleId ?>">
                <input type="hidden" name="filter_permission_search" value="<?= e($filterPermissionSearch) ?>">
                <input type="hidden" name="filter_group" value="<?= e($filterGroup) ?>">
                <input type="hidden" name="filter_checked" value="<?= e($filterChecked) ?>">
                <input type="hidden" name="expanded" value="<?= e($showOnlyVisibleGroupsOpen) ?>">

                <div class="btn-group" style="margin-bottom:18px;">
                    <button type="button" class="btn btn-outline" id="check-all-visible">Tout cocher (visible)</button>
                    <button type="button" class="btn btn-outline" id="uncheck-all-visible">Tout décocher (visible)</button>
                    <button type="button" class="btn btn-outline" id="expand-all-groups">Ouvrir tous les groupes</button>
                    <button type="button" class="btn btn-outline" id="collapse-all-groups">Fermer tous les groupes</button>
                </div>

                <?php if ($filteredGroupedPermissions): ?>
                    <div class="aam-panels-grid">
                        <?php foreach ($filteredGroupedPermissions as $group => $items): ?>
                            <?php
                            $groupCheckedCount = 0;
                            foreach ($items as $permission) {
                                if (in_array((int)($permission['id'] ?? 0), $formPermissionIds, true)) {
                                    $groupCheckedCount++;
                                }
                            }
                            $groupId = 'group_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$group);
                            ?>
                            <div class="aam-group-card" data-group-card="<?= e((string)$group) ?>">
                                <div class="aam-group-head">
                                    <div class="aam-group-title-wrap">
                                        <button type="button" class="btn btn-outline btn-sm aam-toggle-group" data-target="<?= e($groupId) ?>">
                                            Ouvrir / Fermer
                                        </button>
                                        <h4 class="aam-group-title"><?= e(aam_group_label((string)$group)) ?></h4>
                                        <span class="aam-count-pill"><?= $groupCheckedCount ?>/<?= count($items) ?></span>
                                    </div>

                                    <div class="aam-group-tools">
                                        <button type="button" class="btn btn-outline btn-sm aam-check-group" data-target="<?= e($groupId) ?>">Tout cocher</button>
                                        <button type="button" class="btn btn-outline btn-sm aam-uncheck-group" data-target="<?= e($groupId) ?>">Tout décocher</button>
                                    </div>
                                </div>

                                <div class="aam-group-body" id="<?= e($groupId) ?>">
                                    <div class="aam-permissions-grid">
                                        <?php foreach ($items as $permission): ?>
                                            <?php
                                            $permissionId = (int)($permission['id'] ?? 0);
                                            $isChecked = in_array($permissionId, $formPermissionIds, true);
                                            ?>
                                            <div class="aam-permission-item <?= $isChecked ? 'is-checked' : '' ?>">
                                                <label class="aam-permission-label">
                                                    <input
                                                        type="checkbox"
                                                        class="aam-permission-checkbox"
                                                        name="permission_ids[]"
                                                        value="<?= $permissionId ?>"
                                                        <?= $isChecked ? 'checked' : '' ?>
                                                    >
                                                    <span class="aam-permission-text">
                                                        <span class="aam-permission-name"><?= e((string)($permission['label'] ?? '')) ?></span>
                                                        <span class="aam-permission-code"><?= e((string)($permission['code'] ?? '')) ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="aam-empty">
                        Aucune permission ne correspond aux filtres actuels.
                    </div>
                <?php endif; ?>

                <div class="aam-actions-sticky">
                    <div class="btn-group">
                        <button type="submit" name="form_action" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="form_action" value="save_matrix" class="btn btn-success">Enregistrer la matrice</button>
                    </div>
                </div>
            </form>
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                function updateCheckedStateVisual() {
                    document.querySelectorAll('.aam-permission-item').forEach(function (item) {
                        const checkbox = item.querySelector('input[type="checkbox"]');
                        if (!checkbox) {
                            return;
                        }

                        if (checkbox.checked) {
                            item.classList.add('is-checked');
                        } else {
                            item.classList.remove('is-checked');
                        }
                    });
                }

                function setCheckboxes(container, checked) {
                    if (!container) {
                        return;
                    }

                    container.querySelectorAll('.aam-permission-checkbox').forEach(function (checkbox) {
                        checkbox.checked = checked;
                    });

                    updateCheckedStateVisual();
                }

                document.querySelectorAll('.aam-permission-checkbox').forEach(function (checkbox) {
                    checkbox.addEventListener('change', updateCheckedStateVisual);
                });

                const checkAllVisible = document.getElementById('check-all-visible');
                const uncheckAllVisible = document.getElementById('uncheck-all-visible');

                if (checkAllVisible) {
                    checkAllVisible.addEventListener('click', function () {
                        document.querySelectorAll('.aam-permission-checkbox').forEach(function (checkbox) {
                            checkbox.checked = true;
                        });
                        updateCheckedStateVisual();
                    });
                }

                if (uncheckAllVisible) {
                    uncheckAllVisible.addEventListener('click', function () {
                        document.querySelectorAll('.aam-permission-checkbox').forEach(function (checkbox) {
                            checkbox.checked = false;
                        });
                        updateCheckedStateVisual();
                    });
                }

                document.querySelectorAll('.aam-check-group').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const targetId = button.getAttribute('data-target');
                        setCheckboxes(document.getElementById(targetId), true);
                    });
                });

                document.querySelectorAll('.aam-uncheck-group').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const targetId = button.getAttribute('data-target');
                        setCheckboxes(document.getElementById(targetId), false);
                    });
                });

                document.querySelectorAll('.aam-toggle-group').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const targetId = button.getAttribute('data-target');
                        const target = document.getElementById(targetId);
                        if (!target) {
                            return;
                        }

                        target.style.display = target.style.display === 'none' ? '' : 'none';
                    });
                });

                const expandAll = document.getElementById('expand-all-groups');
                const collapseAll = document.getElementById('collapse-all-groups');

                if (expandAll) {
                    expandAll.addEventListener('click', function () {
                        document.querySelectorAll('.aam-group-body').forEach(function (el) {
                            el.style.display = '';
                        });
                    });
                }

                if (collapseAll) {
                    collapseAll.addEventListener('click', function () {
                        document.querySelectorAll('.aam-group-body').forEach(function (el) {
                            el.style.display = 'none';
                        });
                    });
                }

                updateCheckedStateVisual();
            });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>