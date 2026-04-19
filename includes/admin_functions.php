<?php

/*
|--------------------------------------------------------------------------
| Chargement additif LOT 2
|--------------------------------------------------------------------------
*/

$slRulesEnginePath = __DIR__ . '/rules_engine.php';
if (is_file($slRulesEnginePath)) {
    require_once $slRulesEnginePath;
}

$slAnomalyEnginePath = __DIR__ . '/anomaly_engine.php';
if (is_file($slAnomalyEnginePath)) {
    require_once $slAnomalyEnginePath;
}

$slImportMapperPath = __DIR__ . '/import_mapper.php';
if (is_file($slImportMapperPath)) {
    require_once $slImportMapperPath;
}

$slPendingDebitsEnginePath = __DIR__ . '/pending_debits_engine.php';
if (is_file($slPendingDebitsEnginePath)) {
    require_once $slPendingDebitsEnginePath;
}

/*
|--------------------------------------------------------------------------
| Helpers généraux
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('clean_input')) {
    function clean_input(?string $value): string
    {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$tableName]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('columnExists')) {
    function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$tableName, $columnName]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('render_app_header_bar')) {
    function render_app_header_bar(string $title, string $subtitle = ''): void
    {
        echo '<div class="page-hero">';
        echo '<div>';
        echo '<h1>' . e($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="muted">' . e($subtitle) . '</p>';
        }
        echo '</div>';
        echo '</div>';
    }
}

/*
|--------------------------------------------------------------------------
| Utilisateur courant / rôles / permissions
|--------------------------------------------------------------------------
*/

if (!function_exists('currentUserRecord')) {
    function currentUserRecord(PDO $pdo): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !tableExists($pdo, 'users')) {
            return null;
        }

        $roleJoin = '';
        $roleSelect = 'NULL AS role_code, NULL AS role_label, NULL AS legacy_role';

        if (tableExists($pdo, 'roles') && columnExists($pdo, 'users', 'role_id')) {
            $roleJoin = 'LEFT JOIN roles r ON r.id = u.role_id';
            $roleSelectParts = [];

            $roleSelectParts[] = columnExists($pdo, 'roles', 'code') ? 'r.code AS role_code' : 'NULL AS role_code';
            $roleSelectParts[] = columnExists($pdo, 'roles', 'label')
                ? 'r.label AS role_label'
                : (columnExists($pdo, 'roles', 'name') ? 'r.name AS role_label' : 'NULL AS role_label');
            $roleSelectParts[] = columnExists($pdo, 'users', 'role') ? 'u.role AS legacy_role' : 'NULL AS legacy_role';

            $roleSelect = implode(', ', $roleSelectParts);
        } elseif (columnExists($pdo, 'users', 'role')) {
            $roleSelect = 'NULL AS role_code, NULL AS role_label, u.role AS legacy_role';
        }

        $userColumns = ['u.id'];

        $optionalUserColumns = [
            'username',
            'email',
            'is_active',
            'role_id',
            'role',
            'created_at',
            'updated_at',
        ];

        foreach ($optionalUserColumns as $column) {
            if (columnExists($pdo, 'users', $column)) {
                $userColumns[] = 'u.' . $column;
            }
        }

        $stmt = $pdo->prepare("
            SELECT
                " . implode(', ', $userColumns) . ",
                {$roleSelect}
            FROM users u
            {$roleJoin}
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}

if (!function_exists('currentUserHasRoleCode')) {
    function currentUserHasRoleCode(PDO $pdo, array $roleCodes): bool
    {
        $user = currentUserRecord($pdo);
        if (!$user) {
            return false;
        }

        $currentValues = [
            strtolower(trim((string)($user['role_code'] ?? ''))),
            strtolower(trim((string)($user['legacy_role'] ?? ''))),
            strtolower(trim((string)($user['role_label'] ?? ''))),
            strtolower(trim((string)($_SESSION['role'] ?? ''))),
            strtolower(trim((string)($_SESSION['role_name'] ?? ''))),
        ];

        foreach ($roleCodes as $code) {
            $normalized = strtolower(trim((string)$code));
            if ($normalized !== '' && in_array($normalized, $currentValues, true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('currentUserIsAdminLike')) {
    function currentUserIsAdminLike(PDO $pdo): bool
    {
        return currentUserHasRoleCode($pdo, [
            'admin',
            'superadmin',
            'super_admin',
            'admin_tech',
            'admin-tech',
            'admin technique',
            'administrateur',
            'administrateur technique',
            'admin global',
        ]);
    }
}

if (!function_exists('permissionCodeExists')) {
    function permissionCodeExists(PDO $pdo, string $code): bool
    {
        if (!tableExists($pdo, 'permissions') || !columnExists($pdo, 'permissions', 'code')) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM permissions
            WHERE code = ?
        ");
        $stmt->execute([$code]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('currentUserCan')) {
    function currentUserCan(PDO $pdo, string $permissionCode): bool
    {
        $permissionCode = trim($permissionCode);
        if ($permissionCode === '') {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $roleId = (int)($_SESSION['role_id'] ?? 0);

        // Super admin total
        if ($roleId === 1) {
            return true;
        }

        if (!tableExists($pdo, 'permissions') || !tableExists($pdo, 'role_permissions')) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
              AND p.code = ?
            LIMIT 1
        ");
        $stmt->execute([$roleId, $permissionCode]);

        return (bool)$stmt->fetchColumn();
    }
}


if (!function_exists('currentUserCanAny')) {
    function currentUserCanAny(PDO $pdo, array $permissionCodes): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $roleId = (int)($_SESSION['role_id'] ?? 0);

        // Super admin total
        if ($roleId === 1) {
            return true;
        }

        foreach ($permissionCodes as $permissionCode) {
            if (!is_string($permissionCode)) {
                continue;
            }

            if (currentUserCan($pdo, $permissionCode)) {
                return true;
            }
        }

        return false;
    }
}


if (!function_exists('currentUserCanAll')) {
    function currentUserCanAll(PDO $pdo, array $permissionCodes): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $roleId = (int)($_SESSION['role_id'] ?? 0);

        // Super admin total
        if ($roleId === 1) {
            return true;
        }

        foreach ($permissionCodes as $permissionCode) {
            if (!is_string($permissionCode) || trim($permissionCode) === '') {
                continue;
            }

            if (!currentUserCan($pdo, $permissionCode)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(PDO $pdo, string $permissionCode): void
    {
        if (!currentUserCan($pdo, $permissionCode)) {
            http_response_code(403);
            exit('Accès refusé.');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Cartographie centralisée des accès
|--------------------------------------------------------------------------
*/

if (!function_exists('studelyAccessMap')) {
    function studelyAccessMap(): array
    {
        return [
            'dashboard_view_page' => ['dashboard_view', 'admin_manage'],
            'analytics_view_page' => ['analytics_view', 'dashboard_view', 'admin_manage'],

            'clients_view_page' => ['clients_view', 'clients_manage', 'admin_manage'],
            'clients_create_page' => ['clients_create', 'clients_manage', 'admin_manage'],
            'clients_edit_page' => ['clients_edit', 'clients_manage', 'admin_manage'],
            'clients_delete_page' => ['clients_delete', 'clients_manage', 'admin_manage'],
            'clients_archive_page' => ['clients_archive', 'clients_edit', 'clients_manage', 'admin_manage'],

            'operations_view_page' => ['operations_view', 'operations_manage', 'admin_manage'],
            'operations_create_page' => ['operations_create', 'operations_manage', 'admin_manage'],
            'operations_edit_page' => ['operations_edit', 'operations_manage', 'admin_manage'],
            'operations_delete_page' => ['operations_delete', 'operations_manage', 'admin_manage'],
            'operations_validate_page' => ['operations_validate', 'operations_manage', 'admin_manage'],

            'imports_upload_page' => ['imports_upload', 'imports_create', 'imports_manage', 'admin_manage'],
            'imports_preview_page' => ['imports_preview', 'imports_upload', 'imports_create', 'imports_manage', 'admin_manage'],
            'imports_validate_page' => ['imports_validate', 'imports_manage', 'admin_manage'],
            'imports_journal_page' => ['imports_journal', 'imports_manage', 'admin_manage'],
            'imports_rejected_page' => ['imports_rejected_manage', 'imports_manage', 'admin_manage'],

            'treasury_view_page' => ['treasury_view', 'treasury_manage', 'admin_manage'],
            'treasury_create_page' => ['treasury_create', 'treasury_manage', 'admin_manage'],
            'treasury_edit_page' => ['treasury_edit', 'treasury_manage', 'admin_manage'],
            'treasury_delete_page' => ['treasury_delete', 'treasury_manage', 'admin_manage'],
            'treasury_import_page' => ['treasury_import', 'treasury_manage', 'admin_manage'],

            'statements_view_page' => ['statements_view', 'clients_view', 'admin_manage'],
            'statements_export_page' => ['statements_export', 'statements_view', 'clients_view', 'admin_manage'],

            'support_view_page' => ['support_view', 'support_requests_view', 'support_manage', 'support_admin_manage', 'admin_manage'],
            'support_create_page' => ['support_create', 'support_view', 'support_requests_view', 'support_manage', 'support_admin_manage', 'admin_manage'],
            'support_admin_page' => ['support_admin_manage', 'support_manage', 'admin_manage'],

            'admin_functional_page' => ['admin_functional_view', 'admin_manage'],
            'services_manage_page' => ['services_manage', 'admin_functional_view', 'admin_manage'],
            'operation_types_manage_page' => ['operation_types_manage', 'admin_functional_view', 'admin_manage'],
            'service_accounts_manage_page' => ['service_accounts_view', 'admin_functional_view', 'admin_manage'],
            'statuses_manage_page' => ['statuses_manage', 'admin_functional_view', 'admin_manage'],

            'admin_dashboard_page' => ['admin_dashboard_view', 'admin_manage'],
            'users_manage_page' => ['users_manage', 'admin_manage'],
            'roles_manage_page' => ['roles_manage', 'admin_manage'],
            'permissions_manage_page' => ['permissions_manage', 'admin_manage'],
            'user_logs_view_page' => ['user_logs_view', 'admin_manage'],
            'settings_manage_page' => ['settings_manage', 'admin_manage'],

            'service_accounts_import_page' => ['service_accounts_view', 'service_accounts_manage', 'admin_functional_view', 'admin_manage'],

            'pending_debits_view_page' => ['pending_debits_view', 'pending_debits_manage', 'admin_manage'],
            'pending_debits_edit_page' => ['pending_debits_edit', 'pending_debits_manage', 'admin_manage'],
            'pending_debits_execute_page' => ['pending_debits_execute', 'pending_debits_manage', 'admin_manage'],
            'pending_debits_cancel_page' => ['pending_debits_cancel', 'pending_debits_manage', 'admin_manage'],

            'notifications_view_page' => ['dashboard_view', 'admin_manage'],
            'intelligence_center_page' => ['admin_dashboard_view', 'admin_manage'],
        ];
    }
}

if (!function_exists('studelyCanAccess')) {
    function studelyCanAccess(PDO $pdo, string $permissionCode): bool
    {
        return currentUserCan($pdo, $permissionCode);
    }
}

if (!function_exists('studelyEnforceAccess')) {
    function studelyEnforceAccess(PDO $pdo, string $permissionCode, bool $redirect = false): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Vérifier login
        if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
            header('Location: ' . (defined('APP_URL') ? APP_URL . 'login.php' : '/login.php'));
            exit;
        }

        $roleId = (int)($_SESSION['role_id'] ?? 0);

        // 2. Super admin bypass
        if ($roleId === 1) {
            return;
        }

        // 3. Vérifier permission
        if (!currentUserCan($pdo, $permissionCode)) {

            if ($redirect) {
                header('Location: ' . (defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php?error=access_denied' : '/'));
                exit;
            }

            http_response_code(403);
            exit('Accès refusé : permission "' . htmlspecialchars($permissionCode) . '" requise.');
        }
    }
}


if (!function_exists('studelyModulePermissions')) {
    function studelyModulePermissions(): array
    {
        return [
            'dashboard' => ['dashboard_view'],
            'analytics' => ['analytics_view'],

            'clients' => ['clients_view', 'clients_create', 'clients_edit', 'clients_delete', 'clients_archive', 'clients_manage'],
            'operations' => ['operations_view', 'operations_create', 'operations_edit', 'operations_delete', 'operations_validate', 'operations_manage'],
            'imports' => ['imports_upload', 'imports_create', 'imports_preview', 'imports_validate', 'imports_journal', 'imports_rejected_manage', 'imports_manage'],
            'treasury' => ['treasury_view', 'treasury_create', 'treasury_edit', 'treasury_delete', 'treasury_import', 'treasury_manage'],
            'statements' => ['statements_view', 'statements_export'],

            'support' => ['support_view', 'support_requests_view', 'support_create', 'support_manage', 'support_admin_manage'],

            'admin_functional' => [
                'admin_functional_view',
                'services_manage',
                'operation_types_manage',
                'service_accounts_view',
                'service_accounts_manage',
                'statuses_manage'
            ],

            'admin' => ['admin_dashboard_view', 'users_manage', 'roles_manage', 'permissions_manage', 'user_logs_view', 'settings_manage', 'admin_manage'],

            'pending_debits' => [
                'pending_debits_view',
                'pending_debits_manage',
                'pending_debits_execute',
                'pending_debits_edit',
                'pending_debits_cancel'
            ],

            'notifications' => ['dashboard_view', 'admin_manage'],
            'intelligence' => ['admin_dashboard_view', 'admin_manage'],
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Référentiels / options
|--------------------------------------------------------------------------
*/

if (!function_exists('fetchSelectOptions')) {
    function fetchSelectOptions(PDO $pdo, string $tableName, string $labelColumn = 'label', string $where = '1=1'): array
    {
        if (!tableExists($pdo, $tableName) || !columnExists($pdo, $tableName, 'id')) {
            return [];
        }

        if (!columnExists($pdo, $tableName, $labelColumn)) {
            if (columnExists($pdo, $tableName, 'name')) {
                $labelColumn = 'name';
            } elseif (columnExists($pdo, $tableName, 'account_label')) {
                $labelColumn = 'account_label';
            } elseif (columnExists($pdo, $tableName, 'full_name')) {
                $labelColumn = 'full_name';
            } else {
                return [];
            }
        }

        $stmt = $pdo->query("
            SELECT id, {$labelColumn} AS text
            FROM {$tableName}
            WHERE {$where}
            ORDER BY {$labelColumn} ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getRoleOptions')) {
    function getRoleOptions(PDO $pdo): array
    {
        if (!tableExists($pdo, 'roles')) {
            return [];
        }

        return $pdo->query("
            SELECT id, code, label
            FROM roles
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getPermissionOptions')) {
    function getPermissionOptions(PDO $pdo): array
    {
        if (!tableExists($pdo, 'permissions')) {
            return [];
        }

        return $pdo->query("
            SELECT id, code, label
            FROM permissions
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

/*
|--------------------------------------------------------------------------
| Journalisation
|--------------------------------------------------------------------------
*/

if (!function_exists('logUserAction')) {
    function logUserAction(
        PDO $pdo,
        int $userId,
        string $action,
        string $module,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $details = null
    ): ?int {
        if ($userId <= 0) {
            return null;
        }

        $action = trim($action);
        $module = trim($module);

        if ($action === '' || $module === '') {
            return null;
        }

        if (!tableExists($pdo, 'user_logs')) {
            return null;
        }

        $columns = [];
        $values = [];
        $params = [];

        $availableColumns = [
            'user_id' => columnExists($pdo, 'user_logs', 'user_id'),
            'action' => columnExists($pdo, 'user_logs', 'action'),
            'module' => columnExists($pdo, 'user_logs', 'module'),
            'entity_type' => columnExists($pdo, 'user_logs', 'entity_type'),
            'entity_id' => columnExists($pdo, 'user_logs', 'entity_id'),
            'details' => columnExists($pdo, 'user_logs', 'details'),
            'created_at' => columnExists($pdo, 'user_logs', 'created_at'),
        ];

        if ($availableColumns['user_id']) {
            $columns[] = 'user_id';
            $values[] = '?';
            $params[] = $userId;
        }

        if ($availableColumns['action']) {
            $columns[] = 'action';
            $values[] = '?';
            $params[] = $action;
        }

        if ($availableColumns['module']) {
            $columns[] = 'module';
            $values[] = '?';
            $params[] = $module;
        }

        if ($availableColumns['entity_type']) {
            $columns[] = 'entity_type';
            $values[] = '?';
            $params[] = $entityType;
        }

        if ($availableColumns['entity_id']) {
            $columns[] = 'entity_id';
            $values[] = '?';
            $params[] = $entityId;
        }

        if ($availableColumns['details']) {
            $columns[] = 'details';
            $values[] = '?';
            $params[] = $details;
        }

        if ($availableColumns['created_at']) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return null;
        }

        $sql = "
            INSERT INTO user_logs (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$pdo->lastInsertId();
    }
}

/*
|--------------------------------------------------------------------------
| Génération / catalogues métiers
|--------------------------------------------------------------------------
*/

if (!function_exists('generateClientCode')) {
    function generateClientCode(PDO $pdo): string
    {
        do {
            $code = str_pad((string)random_int(1, 999999999), 9, '0', STR_PAD_LEFT);

            if (!tableExists($pdo, 'clients') || !columnExists($pdo, 'clients', 'client_code')) {
                return $code;
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM clients
                WHERE client_code = ?
            ");
            $stmt->execute([$code]);
            $exists = (int)$stmt->fetchColumn() > 0;
        } while ($exists);

        return $code;
    }
}

if (!function_exists('studely_destination_countries')) {
    function studely_destination_countries(): array
    {
        return [
            'Allemagne',
            'Belgique',
            'France',
            'Espagne',
            'Italie',
            'Autres destinations',
        ];
    }
}

if (!function_exists('studely_commercial_countries')) {
    function studely_commercial_countries(): array
    {
        return [
            'France','Allemagne','Belgique','Cameroun','Sénégal','Côte d\'Ivoire','Benin',
            'Burkina Faso','Congo Brazzaville','Congo Kinshasa','Gabon','Tchad','Mali','Togo',
            'Mexique','Inde','Algérie','Guinée','Tunisie','Maroc','Niger','Afrique de l\'est','Autres pays',
        ];
    }
}

if (!function_exists('studely_origin_countries')) {
    function studely_origin_countries(): array
    {
        return [
            'Afghanistan','Afrique du Sud','Albanie','Algérie','Allemagne','Andorre','Angola','Antigua-et-Barbuda',
            'Arabie saoudite','Argentine','Arménie','Australie','Autriche','Azerbaïdjan','Bahamas','Bahreïn',
            'Bangladesh','Barbade','Belgique','Belize','Bénin','Bhoutan','Biélorussie','Birmanie','Bolivie',
            'Bosnie-Herzégovine','Botswana','Brésil','Brunei','Bulgarie','Burkina Faso','Burundi','Cap-Vert',
            'Cambodge','Cameroun','Canada','République centrafricaine','Chili','Chine','Chypre','Colombie',
            'Comores','Congo','République démocratique du Congo','Corée du Nord','Corée du Sud','Costa Rica',
            'Côte d’Ivoire','Croatie','Cuba','Danemark','Djibouti','Dominique','Égypte','Émirats arabes unis',
            'Équateur','Érythrée','Espagne','Estonie','Eswatini','États-Unis','Éthiopie','Fidji','Finlande',
            'France','Gabon','Gambie','Géorgie','Ghana','Grèce','Grenade','Guatemala','Guinée','Guinée-Bissau',
            'Guinée équatoriale','Guyana','Haïti','Honduras','Hongrie','Inde','Indonésie','Irak','Iran',
            'Irlande','Islande','Israël','Italie','Jamaïque','Japon','Jordanie','Kazakhstan','Kenya',
            'Kirghizistan','Kiribati','Koweït','Laos','Lesotho','Lettonie','Liban','Liberia','Libye',
            'Liechtenstein','Lituanie','Luxembourg','Macédoine du Nord','Madagascar','Malaisie','Malawi',
            'Maldives','Mali','Malte','Maroc','Îles Marshall','Maurice','Mauritanie','Mexique','Micronésie',
            'Moldavie','Monaco','Mongolie','Monténégro','Mozambique','Namibie','Nauru','Népal','Nicaragua',
            'Niger','Nigeria','Norvège','Nouvelle-Zélande','Oman','Ouganda','Ouzbékistan','Pakistan',
            'Palaos','Palestine','Panama','Papouasie-Nouvelle-Guinée','Paraguay','Pays-Bas','Pérou',
            'Philippines','Pologne','Portugal','Qatar','Roumanie','Royaume-Uni','Russie','Rwanda',
            'Saint-Christophe-et-Niévès','Saint-Marin','Saint-Vincent-et-les-Grenadines','Sainte-Lucie',
            'Salomon','Salvador','Samoa','Sao Tomé-et-Principe','Sénégal','Serbie','Seychelles',
            'Sierra Leone','Singapour','Slovaquie','Slovénie','Somalie','Soudan','Soudan du Sud',
            'Sri Lanka','Suède','Suisse','Suriname','Syrie','Tadjikistan','Tanzanie','Tchad','Tchéquie',
            'Thaïlande','Timor oriental','Togo','Tonga','Trinité-et-Tobago','Tunisie','Turkménistan',
            'Turquie','Tuvalu','Ukraine','Uruguay','Vanuatu','Vatican','Venezuela','Vietnam','Yémen',
            'Zambie','Zimbabwe',
        ];
    }
}

if (!function_exists('studely_client_types')) {
    function studely_client_types(): array
    {
        return [
            'Etudiant',
            'Particulier',
            'Entreprise',
            'Partenaire',
        ];
    }
}

if (!function_exists('sl_get_currency_options')) {
    function sl_get_currency_options(PDO $pdo): array
    {
        if (tableExists($pdo, 'currencies')) {
            return $pdo->query("
                SELECT code, label
                FROM currencies
                WHERE COALESCE(is_active,1) = 1
                ORDER BY code ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            ['code' => 'EUR', 'label' => 'Euro'],
            ['code' => 'USD', 'label' => 'Dollar US'],
            ['code' => 'GBP', 'label' => 'Livre Sterling'],
            ['code' => 'XAF', 'label' => 'Franc CFA BEAC'],
            ['code' => 'XOF', 'label' => 'Franc CFA BCEAO'],
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Comptes / contexte client
|--------------------------------------------------------------------------
*/

if (!function_exists('findPrimaryBankAccountForClient')) {
    function findPrimaryBankAccountForClient(PDO $pdo, int $clientId): ?array
    {
        if (!tableExists($pdo, 'client_bank_accounts') || !tableExists($pdo, 'bank_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT ba.*
            FROM client_bank_accounts cba
            INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
            WHERE cba.client_id = ?
            ORDER BY cba.id ASC
            LIMIT 1
        ");
        $stmt->execute([$clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('sl_find_client_bank_accounts')) {
    function sl_find_client_bank_accounts(PDO $pdo, int $clientId): array
    {
        if (!tableExists($pdo, 'client_bank_accounts') || !tableExists($pdo, 'bank_accounts')) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT ba.*
            FROM client_bank_accounts cba
            INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
            WHERE cba.client_id = ?
            ORDER BY ba.account_number ASC
        ");
        $stmt->execute([$clientId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('findTreasuryAccountById')) {
    function findTreasuryAccountById(PDO $pdo, int $treasuryId): ?array
    {
        if ($treasuryId <= 0 || !tableExists($pdo, 'treasury_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM treasury_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$treasuryId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('findTreasuryAccountByCode')) {
    function findTreasuryAccountByCode(PDO $pdo, string $accountCode): ?array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM treasury_accounts
            WHERE account_code = ?
            LIMIT 1
        ");
        $stmt->execute([$accountCode]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('findServiceAccountByCode')) {
    function findServiceAccountByCode(PDO $pdo, string $accountCode): ?array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM service_accounts
            WHERE account_code = ?
            LIMIT 1
        ");
        $stmt->execute([$accountCode]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getClientAccountingContext')) {
    function getClientAccountingContext(PDO $pdo, int $clientId): ?array
    {
        if (!tableExists($pdo, 'clients')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                c.*,
                ta.account_code AS treasury_account_code,
                ta.account_label AS treasury_account_label
            FROM clients c
            LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$clientId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('resolveServiceAccountFromServiceId')) {
    function resolveServiceAccountFromServiceId(PDO $pdo, ?int $serviceId): ?array
    {
        if ($serviceId === null || !tableExists($pdo, 'ref_services')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                rs.id,
                rs.code,
                rs.label,
                rs.service_account_id,
                sa.account_code,
                sa.account_label
            FROM ref_services rs
            LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
            WHERE rs.id = ?
            LIMIT 1
        ");
        $stmt->execute([$serviceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

/*
|--------------------------------------------------------------------------
| Moteur comptable - helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('sl_normalize_code')) {
    function sl_normalize_code(?string $value): string
    {
        $value = strtoupper(trim((string)$value));
        $value = str_replace(
            ['É','È','Ê','Ë','À','Â','Ä','Î','Ï','Ô','Ö','Ù','Û','Ü','Ç',' ', '-', '/', '\''],
            ['E','E','E','E','A','A','A','I','I','O','O','U','U','U','C','_','_','_',''],
            $value
        );
        $value = preg_replace('/[^A-Z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('sl_operation_service_map')) {
    function sl_operation_service_map(): array
    {
        return [
            'VERSEMENT' => ['VERSEMENT'],
            'VIREMENT' => ['INTERNE', 'MENSUEL', 'EXCEPTIONEL', 'REGULIER'],
            'REGULARISATION' => ['POSITIVE', 'NEGATIVE'],
            'FRAIS_SERVICE' => ['AVI', 'ATS'],
            'FRAIS_GESTION' => ['GESTION'],
            'COMMISSION_DE_TRANSFERT' => ['COMMISSION_DE_TRANSFERT'],
            'CA_PLACEMENT' => ['CA_PLACEMENT'],
            'CA_DIVERS' => ['CA_DIVERS'],
            'CA_DEBOURDS_ASSURANCE' => ['CA_DEBOURDS_ASSURANCE'],
            'CA_LOGEMENT' => ['CA_LOGEMENT'],
            'CA_COURTAGE_PRET' => ['CA_COURTAGE_PRET'],
            'FRAIS_DEBOURDS_MICROFINANCE' => ['FRAIS_DEBOURDS_MICROFINANCE'],
        ];
    }
}

if (!function_exists('sl_manual_accounting_cases')) {
    function sl_manual_accounting_cases(): array
    {
        return [
            'VIREMENT::INTERNE',
            'CA_DIVERS::CA_DIVERS',
            'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE',
            'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE',
            'CA_COURTAGE_PRET::CA_COURTAGE_PRET',
            'CA_LOGEMENT::CA_LOGEMENT',
        ];
    }
}

if (!function_exists('sl_service_allowed_for_type')) {
    function sl_service_allowed_for_type(?string $typeCode, ?string $serviceCode): bool
    {
        $map = sl_operation_service_map();
        $typeCode = sl_normalize_code($typeCode);
        $serviceCode = sl_normalize_code($serviceCode);

        if ($typeCode === '' || $serviceCode === '' || !isset($map[$typeCode])) {
            return false;
        }

        return in_array($serviceCode, $map[$typeCode], true);
    }
}

if (!function_exists('sl_is_manual_accounting_case')) {
    function sl_is_manual_accounting_case(?string $typeCode, ?string $serviceCode): bool
    {
        $key = sl_normalize_code($typeCode) . '::' . sl_normalize_code($serviceCode);
        return in_array($key, sl_manual_accounting_cases(), true);
    }
}

/*
|--------------------------------------------------------------------------
| Matching intelligent sur account_label
|--------------------------------------------------------------------------
*/

if (!function_exists('sl_normalize_match_text')) {
    function sl_normalize_match_text(?string $value): string
    {
        $value = (string)($value ?? '');
        $value = mb_strtoupper($value, 'UTF-8');

        $replaceFrom = [
            'À','Á','Â','Ã','Ä','Å',
            'È','É','Ê','Ë',
            'Ì','Í','Î','Ï',
            'Ò','Ó','Ô','Õ','Ö',
            'Ù','Ú','Û','Ü',
            'Ý',
            'Ç',
            'Œ','Æ',
            '’', "'", '`', '´',
            '-', '_', '/', '\\', '.', ',', ';', ':', '(', ')', '[', ']', '{', '}'
        ];

        $replaceTo = [
            'A','A','A','A','A','A',
            'E','E','E','E',
            'I','I','I','I',
            'O','O','O','O','O',
            'U','U','U','U',
            'Y',
            'C',
            'OE','AE',
            ' ', ' ', ' ', ' ',
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '
        ];

        $value = str_replace($replaceFrom, $replaceTo, $value);
        $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string)$value);
    }
}

if (!function_exists('sl_match_all_tokens_in_label')) {
    function sl_match_all_tokens_in_label(string $label, array $tokens): bool
    {
        $normalizedLabel = sl_normalize_match_text($label);

        foreach ($tokens as $token) {
            $normalizedToken = sl_normalize_match_text((string)$token);
            if ($normalizedToken === '') {
                continue;
            }

            if (mb_strpos($normalizedLabel, $normalizedToken) === false) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('sl_load_candidate_service_accounts')) {
    function sl_load_candidate_service_accounts(PDO $pdo, ?int $serviceId): array
    {
        if (!$serviceId || !tableExists($pdo, 'service_accounts')) {
            return [];
        }

        $rows = [];

        if (tableExists($pdo, 'ref_services')) {
            $stmt = $pdo->prepare("
                SELECT
                    sa.*,
                    rs.code AS ref_service_code,
                    rs.label AS ref_service_label
                FROM ref_services rs
                LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
                WHERE rs.id = ?
            ");
            $stmt->execute([$serviceId]);
            $linked = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($linked && !empty($linked['account_code'])) {
                $rows[] = $linked;
            }
        }

        $stmtAll = $pdo->prepare("
            SELECT *
            FROM service_accounts
            WHERE COALESCE(is_active,1) = 1
              AND COALESCE(is_postable,0) = 1
            ORDER BY account_code ASC
        ");
        $stmtAll->execute();
        $all = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all as $row) {
            $accountCode = (string)($row['account_code'] ?? '');
            $exists = false;
            foreach ($rows as $existing) {
                if ((string)($existing['account_code'] ?? '') === $accountCode) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('sl_find_service_account_by_label_rule')) {
    function sl_find_service_account_by_label_rule(PDO $pdo, ?int $serviceId, array $tokens): ?array
    {
        $candidates = sl_load_candidate_service_accounts($pdo, $serviceId);
        if (!$candidates) {
            return null;
        }

        foreach ($candidates as $row) {
            $label = (string)($row['account_label'] ?? '');
            if ($label !== '' && sl_match_all_tokens_in_label($label, $tokens)) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('sl_find_service_account_for_avi')) {
    function sl_find_service_account_for_avi(PDO $pdo, ?int $serviceId, string $destinationCountry, string $commercialCountry): ?array
    {
        return sl_find_service_account_by_label_rule($pdo, $serviceId, [
            'AVI',
            $destinationCountry,
            $commercialCountry,
        ]);
    }
}

if (!function_exists('sl_find_service_account_for_ats')) {
    function sl_find_service_account_for_ats(PDO $pdo, ?int $serviceId, string $commercialCountry): ?array
    {
        return sl_find_service_account_by_label_rule($pdo, $serviceId, [
            'ATS',
            $commercialCountry,
        ]);
    }
}

if (!function_exists('sl_find_service_account_for_management_by_label')) {
    function sl_find_service_account_for_management_by_label(PDO $pdo, ?int $serviceId, string $commercialCountry): ?array
    {
        return sl_find_service_account_by_label_rule($pdo, $serviceId, [
            'GESTION',
            $commercialCountry,
        ]);
    }
}

if (!function_exists('sl_find_service_account_for_transfer_by_label')) {
    function sl_find_service_account_for_transfer_by_label(PDO $pdo, ?int $serviceId, string $commercialCountry): ?array
    {
        return sl_find_service_account_by_label_rule($pdo, $serviceId, [
            'TRANSFERT',
            $commercialCountry,
        ]);
    }
}

if (!function_exists('sl_find_service_account_for_placement_by_label')) {
    function sl_find_service_account_for_placement_by_label(PDO $pdo, ?int $serviceId, string $commercialCountry): ?array
    {
        return sl_find_service_account_by_label_rule($pdo, $serviceId, [
            'CA PLACEMENT',
            $commercialCountry,
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| Ancien moteur comptable compatible
|--------------------------------------------------------------------------
*/

if (!function_exists('resolveAccountingOperation')) {
    function resolveAccountingOperation(PDO $pdo, array $payload): array
    {
        $operationTypeCode = strtoupper(trim((string)($payload['operation_type_code'] ?? '')));
        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : null;
        $sourceTreasuryCode = $payload['source_treasury_code'] ?? null;
        $targetTreasuryCode = $payload['target_treasury_code'] ?? null;

        if ($operationTypeCode === '') {
            throw new RuntimeException('Type d’opération manquant.');
        }

        $clientContext = null;
        if ($clientId) {
            $clientContext = getClientAccountingContext($pdo, $clientId);
            if (!$clientContext) {
                throw new RuntimeException('Client introuvable pour la résolution comptable.');
            }
        }

        $serviceInfo = $serviceId ? resolveServiceAccountFromServiceId($pdo, $serviceId) : null;

        $debit = null;
        $credit = null;
        $analytic = null;

        switch ($operationTypeCode) {
            case 'VERSEMENT':
            case 'REGULARISATION_POSITIVE':
            case 'CREDIT_CLIENT':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                $debit = $sourceTreasuryCode ?: ($clientContext['treasury_account_code'] ?? null);
                $credit = $clientContext['generated_client_account'] ?? null;
                break;

            case 'VIREMENT_MENSUEL':
            case 'VIREMENT_EXCEPTIONEL':
            case 'VIREMENT_REGULIER':
            case 'REGULARISATION_NEGATIVE':
            case 'FRAIS_BANCAIRES':
            case 'DEBIT_CLIENT':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                $debit = $clientContext['generated_client_account'] ?? null;
                $credit = $sourceTreasuryCode ?: ($clientContext['treasury_account_code'] ?? null);
                break;

            case 'FRAIS_DE_SERVICE':
            case 'FRAIS_SERVICE':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                if (!$serviceInfo || empty($serviceInfo['account_code'])) {
                    throw new RuntimeException('Le service choisi n’a pas de compte 706 associé.');
                }
                $debit = $clientContext['generated_client_account'] ?? null;
                $credit = $serviceInfo['account_code'];
                $analytic = [
                    'account_code' => $serviceInfo['account_code'],
                    'account_label' => $serviceInfo['account_label'] ?? null,
                ];
                break;

            case 'VIREMENT_INTERNE':
                if (!$sourceTreasuryCode || !$targetTreasuryCode) {
                    throw new RuntimeException('Les comptes source et cible sont obligatoires.');
                }
                $debit = $sourceTreasuryCode;
                $credit = $targetTreasuryCode;
                break;

            case 'MANUAL':
            case 'IMPORT_RELEVE':
            case 'REGULARISATION':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                $debit = $clientContext['generated_client_account'] ?? null;
                $credit = $sourceTreasuryCode ?: ($clientContext['treasury_account_code'] ?? null);
                break;

            default:
                throw new RuntimeException('Type d’opération non géré par le moteur.');
        }

        if (!$debit || !$credit) {
            throw new RuntimeException('Impossible de résoudre les comptes débit/crédit.');
        }

        return [
            'debit_account_code' => $debit,
            'credit_account_code' => $credit,
            'analytic_account' => $analytic,
            'client_context' => $clientContext,
            'service_info' => $serviceInfo,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Moteur comptable V2
|--------------------------------------------------------------------------
*/

if (!function_exists('sl_build_operation_hash')) {
    function sl_build_operation_hash(array $payload, string $debitAccountCode, string $creditAccountCode): string
    {
        $parts = [
            (string)($payload['operation_date'] ?? ''),
            (string)round((float)($payload['amount'] ?? 0), 2),
            sl_normalize_code((string)($payload['currency_code'] ?? '')),
            (string)($payload['client_id'] ?? ''),
            sl_normalize_code((string)($payload['operation_type_code'] ?? '')),
            (string)($payload['service_id'] ?? ''),
            trim((string)($payload['reference'] ?? '')),
            trim((string)$debitAccountCode),
            trim((string)$creditAccountCode),
        ];

        return hash('sha256', implode('|', $parts));
    }
}

if (!function_exists('sl_find_duplicate_operation')) {
    function sl_find_duplicate_operation(PDO $pdo, string $operationHash, ?int $excludeOperationId = null): ?array
    {
        if (!tableExists($pdo, 'operations') || !columnExists($pdo, 'operations', 'operation_hash') || $operationHash === '') {
            return null;
        }

        $sql = "
            SELECT *
            FROM operations
            WHERE operation_hash = ?
        ";
        $params = [$operationHash];

        if ($excludeOperationId !== null && $excludeOperationId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $excludeOperationId;
        }

        $sql .= " ORDER BY id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
if (!function_exists('sl_resolve_selected_treasury_account')) {
    function sl_resolve_selected_treasury_account(PDO $pdo, array $payload, ?array $clientContext = null): ?array
    {
        $linkedId = isset($payload['linked_bank_account_id']) ? (int)$payload['linked_bank_account_id'] : 0;

        // 1) Priorité au compte 512 explicitement sélectionné dans l'écran
        if ($linkedId > 0) {
            $treasury = findTreasuryAccountById($pdo, $linkedId);
            if ($treasury) {
                return $treasury;
            }
        }

        // 2) Fallback sur un code 512 éventuellement transmis directement
        $linkedTreasuryCode = trim((string)($payload['linked_treasury_account_code'] ?? ''));
        if ($linkedTreasuryCode !== '') {
            $treasury = findTreasuryAccountByCode($pdo, $linkedTreasuryCode);
            if ($treasury) {
                return $treasury;
            }
        }

        // 3) Fallback sur le 512 principal du client
        if ($clientContext && !empty($clientContext['initial_treasury_account_id'])) {
            $treasury = findTreasuryAccountById($pdo, (int)$clientContext['initial_treasury_account_id']);
            if ($treasury) {
                return $treasury;
            }
        }

        if ($clientContext && !empty($clientContext['treasury_account_code'])) {
            $treasury = findTreasuryAccountByCode($pdo, (string)$clientContext['treasury_account_code']);
            if ($treasury) {
                return $treasury;
            }
        }

        return null;
    }
}

if (!function_exists('sl_operation_uses_selected_treasury')) {
    function sl_operation_uses_selected_treasury(?string $operationTypeCode, ?string $serviceCode): bool
    {
        $key = sl_normalize_code($operationTypeCode) . '::' . sl_normalize_code($serviceCode);

        return in_array($key, [
            'VERSEMENT::VERSEMENT',
            'VIREMENT::MENSUEL',
            'VIREMENT::REGULIER',
            'VIREMENT::EXCEPTIONEL',
            'REGULARISATION::POSITIVE',
            'REGULARISATION::NEGATIVE',
        ], true);
    }
}
if (!function_exists('resolveAccountingOperationV2')) {
    function resolveAccountingOperationV2(PDO $pdo, array $payload): array
    {
        $operationTypeCode = sl_normalize_code((string)($payload['operation_type_code'] ?? ''));
        $serviceCode = sl_normalize_code((string)($payload['service_code'] ?? ''));
        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : null;
        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $operationTypeId = isset($payload['operation_type_id']) ? (int)$payload['operation_type_id'] : 0;

        if ($operationTypeId > 0 && $serviceId > 0 && function_exists('sl_find_accounting_rule')) {
            $rule = sl_find_accounting_rule($pdo, $operationTypeId, $serviceId);
            if ($rule) {
                return sl_resolve_accounting_operation_from_rule($pdo, $payload, $rule);
            }
        }

        if ($operationTypeCode === '') {
            throw new RuntimeException('Type d’opération manquant.');
        }

        if ($serviceCode === '') {
            throw new RuntimeException('Type de service manquant.');
        }

        if (!sl_service_allowed_for_type($operationTypeCode, $serviceCode)) {
            throw new RuntimeException('Le type de service ne correspond pas au type d’opération.');
        }

        $clientContext = null;
        if ($clientId) {
            $clientContext = getClientAccountingContext($pdo, $clientId);
            if (!$clientContext) {
                throw new RuntimeException('Client introuvable.');
            }
        }

        $serviceInfo = null;
        if ($serviceId) {
            $serviceInfo = resolveServiceAccountFromServiceId($pdo, $serviceId);
        }

        // Compatibilité de structure de retour
        $linkedBankAccount = null;

        $clientAccount = (string)($clientContext['generated_client_account'] ?? '');
        $clientTreasury = (string)($clientContext['treasury_account_code'] ?? '');

        $selectedTreasury = null;
        if (sl_operation_uses_selected_treasury($operationTypeCode, $serviceCode)) {
            $selectedTreasury = sl_resolve_selected_treasury_account($pdo, $payload, $clientContext);
        }

        $manualDebit = trim((string)($payload['manual_debit_account_code'] ?? ''));
        $manualCredit = trim((string)($payload['manual_credit_account_code'] ?? ''));
        $manualMode = sl_is_manual_accounting_case($operationTypeCode, $serviceCode);

        $debit = null;
        $credit = null;
        $analytic = null;

        if ($manualMode) {
            if ($manualDebit === '' || $manualCredit === '') {
                throw new RuntimeException('Le compte source et le compte destination sont obligatoires pour ce cas.');
            }

            $debit = $manualDebit;
            $credit = $manualCredit;
        } else {
            switch ($operationTypeCode . '::' . $serviceCode) {
                case 'VERSEMENT::VERSEMENT':
                    if (!$clientContext) {
                        throw new RuntimeException('Client obligatoire pour un versement.');
                    }

                    $target512 = (string)($selectedTreasury['account_code'] ?? $clientTreasury);
                    if ($target512 === '') {
                        throw new RuntimeException('Compte 512 introuvable pour ce versement.');
                    }

                    $debit = $target512;
                    $credit = $clientAccount;
                    break;

                case 'VIREMENT::INTERNE':
                    $source = trim((string)($payload['source_treasury_code'] ?? ''));
                    $target = trim((string)($payload['target_treasury_code'] ?? ''));
                    if ($source === '' || $target === '') {
                        throw new RuntimeException('Les comptes 512 source et cible sont obligatoires.');
                    }
                    if ($source === $target) {
                        throw new RuntimeException('Les comptes 512 source et cible doivent être différents.');
                    }
                    $debit = $source;
                    $credit = $target;
                    break;

                case 'VIREMENT::MENSUEL':
                case 'VIREMENT::REGULIER':
                case 'VIREMENT::EXCEPTIONEL':
                    if (!$clientContext) {
                        throw new RuntimeException('Client obligatoire pour ce virement.');
                    }

                    $target512 = (string)($selectedTreasury['account_code'] ?? $clientTreasury);
                    if ($target512 === '') {
                        throw new RuntimeException('Compte 512 introuvable pour ce virement.');
                    }

                    $debit = $clientAccount;
                    $credit = $target512;
                    break;

                case 'REGULARISATION::POSITIVE':
                    if (!$clientContext) {
                        throw new RuntimeException('Client obligatoire pour une régularisation positive.');
                    }

                    $target512 = (string)($selectedTreasury['account_code'] ?? $clientTreasury);
                    if ($target512 === '') {
                        throw new RuntimeException('Compte 512 introuvable pour cette régularisation positive.');
                    }

                    $debit = $target512;
                    $credit = $clientAccount;
                    break;

                case 'REGULARISATION::NEGATIVE':
                    if (!$clientContext) {
                        throw new RuntimeException('Client obligatoire pour une régularisation négative.');
                    }

                    $target512 = (string)($selectedTreasury['account_code'] ?? $clientTreasury);
                    if ($target512 === '') {
                        throw new RuntimeException('Compte 512 introuvable pour cette régularisation négative.');
                    }

                    $debit = $clientAccount;
                    $credit = $target512;
                    break;

                case 'CA_PLACEMENT::CA_PLACEMENT':
                    if (!$clientContext || !$serviceId) {
                        throw new RuntimeException('Client et service obligatoires.');
                    }

                    $serviceAccount = sl_find_service_account_for_placement_by_label(
                        $pdo,
                        $serviceId,
                        (string)($clientContext['country_commercial'] ?? '')
                    );

                    if (!$serviceAccount) {
                        throw new RuntimeException('Aucun compte 706 trouvé pour CA PLACEMENT + pays commercial.');
                    }

                    $debit = $clientAccount;
                    $credit = (string)$serviceAccount['account_code'];
                    $analytic = [
                        'account_code' => $serviceAccount['account_code'],
                        'account_label' => $serviceAccount['account_label'] ?? null,
                    ];
                    break;

                case 'FRAIS_GESTION::GESTION':
                    if (!$clientContext || !$serviceId) {
                        throw new RuntimeException('Client et service obligatoires.');
                    }

                    $serviceAccount = sl_find_service_account_for_management_by_label(
                        $pdo,
                        $serviceId,
                        (string)($clientContext['country_commercial'] ?? '')
                    );

                    if (!$serviceAccount) {
                        throw new RuntimeException('Aucun compte 706 trouvé pour FRAIS DE GESTION + pays commercial.');
                    }

                    $debit = $clientAccount;
                    $credit = (string)$serviceAccount['account_code'];
                    $analytic = [
                        'account_code' => $serviceAccount['account_code'],
                        'account_label' => $serviceAccount['account_label'] ?? null,
                    ];
                    break;

                case 'FRAIS_SERVICE::AVI':
                    if (!$clientContext || !$serviceId) {
                        throw new RuntimeException('Client et service obligatoires.');
                    }

                    $serviceAccount = sl_find_service_account_for_avi(
                        $pdo,
                        $serviceId,
                        (string)($clientContext['country_destination'] ?? ''),
                        (string)($clientContext['country_commercial'] ?? '')
                    );

                    if (!$serviceAccount) {
                        throw new RuntimeException('Aucun compte 706 trouvé pour AVI + pays destination + pays commercial.');
                    }

                    $debit = $clientAccount;
                    $credit = (string)$serviceAccount['account_code'];
                    $analytic = [
                        'account_code' => $serviceAccount['account_code'],
                        'account_label' => $serviceAccount['account_label'] ?? null,
                    ];
                    break;

                case 'FRAIS_SERVICE::ATS':
                    if (!$clientContext || !$serviceId) {
                        throw new RuntimeException('Client et service obligatoires.');
                    }

                    $serviceAccount = sl_find_service_account_for_ats(
                        $pdo,
                        $serviceId,
                        (string)($clientContext['country_commercial'] ?? '')
                    );

                    if (!$serviceAccount) {
                        throw new RuntimeException('Aucun compte 706 trouvé pour ATS + pays commercial.');
                    }

                    $debit = $clientAccount;
                    $credit = (string)$serviceAccount['account_code'];
                    $analytic = [
                        'account_code' => $serviceAccount['account_code'],
                        'account_label' => $serviceAccount['account_label'] ?? null,
                    ];
                    break;

                case 'COMMISSION_DE_TRANSFERT::COMMISSION_DE_TRANSFERT':
                    if (!$clientContext || !$serviceId) {
                        throw new RuntimeException('Client et service obligatoires.');
                    }

                    $serviceAccount = sl_find_service_account_for_transfer_by_label(
                        $pdo,
                        $serviceId,
                        (string)($clientContext['country_commercial'] ?? '')
                    );

                    if (!$serviceAccount) {
                        throw new RuntimeException('Aucun compte 706 trouvé pour TRANSFERT + pays commercial.');
                    }

                    $debit = $clientAccount;
                    $credit = (string)$serviceAccount['account_code'];
                    $analytic = [
                        'account_code' => $serviceAccount['account_code'],
                        'account_label' => $serviceAccount['account_label'] ?? null,
                    ];
                    break;

                default:
                    throw new RuntimeException('Règle comptable non définie pour ce couple type/service.');
            }
        }

        if ($debit === '' || $credit === '' || $debit === null || $credit === null) {
            throw new RuntimeException('Impossible de déterminer les comptes débit/crédit.');
        }

        $operationHash = sl_build_operation_hash($payload, $debit, $credit);

        return [
            'debit_account_code' => $debit,
            'credit_account_code' => $credit,
            'analytic_account' => $analytic,
            'client_context' => $clientContext,
            'service_info' => $serviceInfo,
            'linked_bank_account' => $linkedBankAccount,
            'selected_treasury_account' => $selectedTreasury,
            'is_manual_accounting' => $manualMode ? 1 : 0,
            'operation_hash' => $operationHash,
            'preview_lines' => [
                ['side' => 'DEBIT', 'account' => $debit],
                ['side' => 'CREDIT', 'account' => $credit],
            ],
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Effets comptables / soldes
|--------------------------------------------------------------------------
*/

if (!function_exists('updateBankAccountBalanceDelta')) {
    function updateBankAccountBalanceDelta(PDO $pdo, int $bankAccountId, float $delta): void
    {
        if (!tableExists($pdo, 'bank_accounts') || !columnExists($pdo, 'bank_accounts', 'balance')) {
            return;
        }

        $sql = "UPDATE bank_accounts SET balance = COALESCE(balance,0) + ?";
        if (columnExists($pdo, 'bank_accounts', 'updated_at')) {
            $sql .= ", updated_at = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$delta, $bankAccountId]);
    }
}

if (!function_exists('updateTreasuryBalanceDelta')) {
    function updateTreasuryBalanceDelta(PDO $pdo, int $treasuryId, float $delta): void
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return;
        }

        $sets = [];

        if (columnExists($pdo, 'treasury_accounts', 'current_balance')) {
            $sets[] = 'current_balance = COALESCE(current_balance,0) + ?';
        }

        if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        if (!$sets) {
            return;
        }

        $params = [$delta, $treasuryId];

        $stmt = $pdo->prepare("
            UPDATE treasury_accounts
            SET " . implode(', ', $sets) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
    }
}

if (!function_exists('updateServiceAccountBalanceDelta')) {
    function updateServiceAccountBalanceDelta(PDO $pdo, int $serviceAccountId, float $delta): void
    {
        if (!tableExists($pdo, 'service_accounts') || !columnExists($pdo, 'service_accounts', 'current_balance')) {
            return;
        }

        $sql = "UPDATE service_accounts SET current_balance = COALESCE(current_balance,0) + ?";
        if (columnExists($pdo, 'service_accounts', 'updated_at')) {
            $sql .= ", updated_at = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$delta, $serviceAccountId]);
    }
}

if (!function_exists('applyAccountingBalanceEffects')) {
    function applyAccountingBalanceEffects(PDO $pdo, array $payload, array $resolved, int $bankAccountId = 0): void
    {
        $operationTypeCode = strtoupper(trim((string)($payload['operation_type_code'] ?? '')));
        $amount = (float)($payload['amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        if ($bankAccountId > 0) {
            if (in_array($operationTypeCode, ['VERSEMENT', 'REGULARISATION_POSITIVE', 'CREDIT_CLIENT'], true)) {
                updateBankAccountBalanceDelta($pdo, $bankAccountId, +$amount);
            } elseif (in_array($operationTypeCode, [
                'FRAIS_DE_SERVICE',
                'FRAIS_SERVICE',
                'VIREMENT_MENSUEL',
                'VIREMENT_EXCEPTIONEL',
                'VIREMENT_REGULIER',
                'REGULARISATION_NEGATIVE',
                'FRAIS_BANCAIRES',
                'DEBIT_CLIENT',
                'MANUAL',
                'IMPORT_RELEVE',
                'REGULARISATION'
            ], true)) {
                updateBankAccountBalanceDelta($pdo, $bankAccountId, -$amount);
            }
        }

        $debitCode = (string)($resolved['debit_account_code'] ?? '');
        $creditCode = (string)($resolved['credit_account_code'] ?? '');

        $debitTreasury = findTreasuryAccountByCode($pdo, $debitCode);
        if ($debitTreasury) {
            updateTreasuryBalanceDelta($pdo, (int)$debitTreasury['id'], -$amount);
        }

        $creditTreasury = findTreasuryAccountByCode($pdo, $creditCode);
        if ($creditTreasury) {
            updateTreasuryBalanceDelta($pdo, (int)$creditTreasury['id'], +$amount);
        }

        $debitService = findServiceAccountByCode($pdo, $debitCode);
        if ($debitService) {
            updateServiceAccountBalanceDelta($pdo, (int)$debitService['id'], -$amount);
        }

        $creditService = findServiceAccountByCode($pdo, $creditCode);
        if ($creditService) {
            updateServiceAccountBalanceDelta($pdo, (int)$creditService['id'], +$amount);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Création / mise à jour opération
|--------------------------------------------------------------------------
*/

if (!function_exists('createOperationWithAccounting')) {
    function createOperationWithAccounting(PDO $pdo, array $payload): int
    {
        $resolved = resolveAccountingOperation($pdo, $payload);

        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $bankAccountId = null;

        if ($clientId) {
            $bankAccount = findPrimaryBankAccountForClient($pdo, $clientId);
            $bankAccountId = $bankAccount['id'] ?? null;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_id' => $clientId,
            'bank_account_id' => $bankAccountId,
            'operation_date' => $payload['operation_date'] ?? date('Y-m-d'),
            'amount' => (float)($payload['amount'] ?? 0),
            'operation_type_code' => $payload['operation_type_code'] ?? null,
            'operation_kind' => $payload['operation_kind'] ?? null,
            'label' => $payload['label'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'source_type' => $payload['source_type'] ?? null,
            'debit_account_code' => $resolved['debit_account_code'],
            'credit_account_code' => $resolved['credit_account_code'],
            'service_account_code' => $resolved['analytic_account']['account_code'] ?? null,
            'created_by' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'operations', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'operations', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        $stmt = $pdo->prepare("
            INSERT INTO operations (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        $operationId = (int)$pdo->lastInsertId();

        applyAccountingBalanceEffects($pdo, $payload, $resolved, (int)($bankAccountId ?? 0));

        return $operationId;
    }
}

if (!function_exists('createOperationWithAccountingV2')) {
    function createOperationWithAccountingV2(PDO $pdo, array $payload, ?int $excludeOperationId = null): int
    {
        $resolved = resolveAccountingOperationV2($pdo, $payload);

        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : null;
        $operationTypeId = isset($payload['operation_type_id']) ? (int)$payload['operation_type_id'] : null;
        $createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        $guard = [
            'mode' => 'normal',
            'allowed_amount' => (float)($payload['amount'] ?? 0),
            'remaining_amount' => 0,
            'bank_account_id' => 0,
            'pending_debit_id' => 0,
        ];

        if (function_exists('sl_handle_client_411_non_overdraft_guard')) {
            $guard = sl_handle_client_411_non_overdraft_guard($pdo, $payload, $resolved, $createdBy);
        }

        if ($guard['mode'] === 'pending_only') {
            if (function_exists('logUserAction') && $clientId && $createdBy) {
                logUserAction(
                    $pdo,
                    $createdBy,
                    'create_pending_debit',
                    'pending_debits',
                    'client',
                    $clientId,
                    'Création d’un débit dû sans exécution immédiate'
                );
            }

            throw new RuntimeException(
                'Solde 411 insuffisant : aucun débit immédiat exécuté. Un débit dû a été créé et tracé.'
            );
        }

        $effectivePayload = $payload;
        if ($guard['mode'] === 'partial') {
            $effectivePayload['amount'] = (float)$guard['allowed_amount'];
        }

        $effectiveResolved = $resolved;
        $effectiveResolved['operation_hash'] = sl_build_operation_hash(
            $effectivePayload,
            (string)$resolved['debit_account_code'],
            (string)$resolved['credit_account_code']
        );

        $duplicate = sl_find_duplicate_operation($pdo, (string)$effectiveResolved['operation_hash'], $excludeOperationId);
        if ($duplicate) {
            throw new RuntimeException('Doublon détecté : une opération strictement identique existe déjà.');
        }

        $bankAccountId = null;

        if (!empty($resolved['linked_bank_account']['id'])) {
            $bankAccountId = (int)$resolved['linked_bank_account']['id'];
        } elseif (!empty($guard['bank_account_id'])) {
            $bankAccountId = (int)$guard['bank_account_id'];
        } elseif ($clientId) {
            $bankAccount = findPrimaryBankAccountForClient($pdo, $clientId);
            $bankAccountId = $bankAccount['id'] ?? null;
        }

        if ($excludeOperationId !== null && $excludeOperationId > 0) {
            $updateMap = [
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'operation_type_id' => $operationTypeId,
                'linked_bank_account_id' => $bankAccountId,
                'bank_account_id' => $bankAccountId,
                'operation_date' => $effectivePayload['operation_date'] ?? date('Y-m-d'),
                'amount' => (float)($effectivePayload['amount'] ?? 0),
                'currency_code' => $effectivePayload['currency_code'] ?? null,
                'operation_type_code' => $effectivePayload['operation_type_code'] ?? null,
                'label' => $effectivePayload['label'] ?? null,
                'reference' => $effectivePayload['reference'] ?? null,
                'notes' => $effectivePayload['notes'] ?? null,
                'source_type' => $effectivePayload['source_type'] ?? null,
                'debit_account_code' => $effectiveResolved['debit_account_code'],
                'credit_account_code' => $effectiveResolved['credit_account_code'],
                'service_account_code' => $effectiveResolved['analytic_account']['account_code'] ?? null,
                'operation_hash' => $effectiveResolved['operation_hash'],
                'is_manual_accounting' => (int)$effectiveResolved['is_manual_accounting'],
            ];

            $fields = [];
            $params = [];
            foreach ($updateMap as $column => $value) {
                if (tableExists($pdo, 'operations') && columnExists($pdo, 'operations', $column)) {
                    $fields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'operations', 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            if (!$fields) {
                throw new RuntimeException('Aucune colonne disponible pour mettre à jour l’opération.');
            }

            $params[] = $excludeOperationId;

            $stmt = $pdo->prepare("
                UPDATE operations
                SET " . implode(', ', $fields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);

            if (function_exists('recomputeAllBalances')) {
                recomputeAllBalances($pdo);
            }

            if ($clientId && function_exists('sl_refresh_client_pending_debits_readiness')) {
                sl_refresh_client_pending_debits_readiness($pdo, $clientId, $createdBy);
            }

            return $excludeOperationId;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'operation_type_id' => $operationTypeId,
            'linked_bank_account_id' => $bankAccountId,
            'bank_account_id' => $bankAccountId,
            'operation_date' => $effectivePayload['operation_date'] ?? date('Y-m-d'),
            'amount' => (float)($effectivePayload['amount'] ?? 0),
            'currency_code' => $effectivePayload['currency_code'] ?? null,
            'operation_type_code' => $effectivePayload['operation_type_code'] ?? null,
            'operation_kind' => $effectivePayload['operation_kind'] ?? null,
            'label' => $effectivePayload['label'] ?? null,
            'reference' => $effectivePayload['reference'] ?? null,
            'notes' => $effectivePayload['notes'] ?? null,
            'source_type' => $effectivePayload['source_type'] ?? null,
            'debit_account_code' => $effectiveResolved['debit_account_code'],
            'credit_account_code' => $effectiveResolved['credit_account_code'],
            'service_account_code' => $effectiveResolved['analytic_account']['account_code'] ?? null,
            'operation_hash' => $effectiveResolved['operation_hash'],
            'is_manual_accounting' => (int)$effectiveResolved['is_manual_accounting'],
            'created_by' => $createdBy,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'operations', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'operations', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (columnExists($pdo, 'operations', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            throw new RuntimeException('Aucune colonne disponible pour créer l’opération.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO operations (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        $operationId = (int)$pdo->lastInsertId();

        if (
            $guard['mode'] === 'partial'
            && !empty($guard['pending_debit_id'])
            && function_exists('sl_attach_operation_to_pending_debit')
        ) {
            sl_attach_operation_to_pending_debit($pdo, (int)$guard['pending_debit_id'], $operationId, $createdBy);
        }

        if (
            $guard['mode'] === 'partial'
            && function_exists('createNotification')
            && $clientId
        ) {
            createNotification(
                $pdo,
                'pending_debit_partial_execution',
                'Débit partiel exécuté sur 411 client. Reliquat placé en débit dû.',
                'warning',
                defined('APP_URL') ? APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . (int)$guard['pending_debit_id'] : null,
                'pending_client_debit',
                (int)$guard['pending_debit_id'],
                $createdBy
            );
        }

        if (function_exists('recomputeAllBalances')) {
            recomputeAllBalances($pdo);
        }

        if ($clientId && function_exists('sl_refresh_client_pending_debits_readiness')) {
            sl_refresh_client_pending_debits_readiness($pdo, $clientId, $createdBy);
        }

        return $operationId;
    }
}

/*
|--------------------------------------------------------------------------
| Virement interne / recalcul global
|--------------------------------------------------------------------------
*/

if (!function_exists('createInternalTreasuryMovement')) {
    function createInternalTreasuryMovement(PDO $pdo, array $payload): int
    {
        if (!tableExists($pdo, 'treasury_movements')) {
            throw new RuntimeException('La table treasury_movements est absente.');
        }

        $sourceId = (int)($payload['source_treasury_account_id'] ?? 0);
        $targetId = (int)($payload['target_treasury_account_id'] ?? 0);
        $amount = (float)($payload['amount'] ?? 0);

        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            throw new RuntimeException('Virement interne invalide.');
        }

        $stmtSource = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
        $stmtSource->execute([$sourceId]);
        $source = $stmtSource->fetch(PDO::FETCH_ASSOC);

        $stmtTarget = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
        $stmtTarget->execute([$targetId]);
        $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);

        if (!$source || !$target) {
            throw new RuntimeException('Comptes internes introuvables.');
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'source_treasury_account_id' => $sourceId,
            'target_treasury_account_id' => $targetId,
            'amount' => $amount,
            'operation_date' => $payload['operation_date'] ?? date('Y-m-d'),
            'reference' => $payload['reference'] ?? null,
            'label' => $payload['label'] ?? 'Virement interne',
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'treasury_movements', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'treasury_movements', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        $stmt = $pdo->prepare("
            INSERT INTO treasury_movements (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        updateTreasuryBalanceDelta($pdo, $sourceId, -$amount);
        updateTreasuryBalanceDelta($pdo, $targetId, +$amount);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('recomputeAllBalances')) {
    function recomputeAllBalances(PDO $pdo): array
    {
        $report = [
            'bank_accounts' => 0,
            'treasury_accounts' => 0,
            'service_accounts' => 0,
        ];

        if (tableExists($pdo, 'bank_accounts') && tableExists($pdo, 'operations')) {
            $bankAccounts = $pdo->query("
                SELECT id, account_number, COALESCE(initial_balance, 0) AS initial_balance
                FROM bank_accounts
            ")->fetchAll(PDO::FETCH_ASSOC);

            $stmtBank = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
                FROM operations
            ");

            $stmtUpdateBank = $pdo->prepare("
                UPDATE bank_accounts
                SET balance = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($bankAccounts as $account) {
                $stmtBank->execute([$account['account_number'], $account['account_number']]);
                $totals = $stmtBank->fetch(PDO::FETCH_ASSOC) ?: [];

                $newBalance = (float)$account['initial_balance']
                    + (float)($totals['total_credit'] ?? 0)
                    - (float)($totals['total_debit'] ?? 0);

                $stmtUpdateBank->execute([$newBalance, (int)$account['id']]);
                $report['bank_accounts']++;
            }
        }

        if (tableExists($pdo, 'treasury_accounts')) {
            $treasuryAccounts = $pdo->query("
                SELECT id, account_code, COALESCE(opening_balance,0) AS opening_balance
                FROM treasury_accounts
            ")->fetchAll(PDO::FETCH_ASSOC);

            $stmtTreasuryOps = tableExists($pdo, 'operations')
                ? $pdo->prepare("
                    SELECT
                        COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                        COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
                    FROM operations
                ")
                : null;

            $stmtTreasuryMov = tableExists($pdo, 'treasury_movements')
                ? $pdo->prepare("
                    SELECT
                        COALESCE(SUM(CASE WHEN target_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_in,
                        COALESCE(SUM(CASE WHEN source_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_out
                    FROM treasury_movements
                ")
                : null;

            $stmtUpdateTreasury = $pdo->prepare("
                UPDATE treasury_accounts
                SET current_balance = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($treasuryAccounts as $account) {
                $opsDebit = 0.0;
                $opsCredit = 0.0;
                $movIn = 0.0;
                $movOut = 0.0;

                if ($stmtTreasuryOps) {
                    $stmtTreasuryOps->execute([$account['account_code'], $account['account_code']]);
                    $ops = $stmtTreasuryOps->fetch(PDO::FETCH_ASSOC) ?: [];
                    $opsDebit = (float)($ops['total_debit'] ?? 0);
                    $opsCredit = (float)($ops['total_credit'] ?? 0);
                }

                if ($stmtTreasuryMov) {
                    $stmtTreasuryMov->execute([(int)$account['id'], (int)$account['id']]);
                    $mov = $stmtTreasuryMov->fetch(PDO::FETCH_ASSOC) ?: [];
                    $movIn = (float)($mov['total_in'] ?? 0);
                    $movOut = (float)($mov['total_out'] ?? 0);
                }

                $newBalance = (float)$account['opening_balance'] - $opsDebit + $opsCredit + $movIn - $movOut;
                $stmtUpdateTreasury->execute([$newBalance, (int)$account['id']]);
                $report['treasury_accounts']++;
            }
        }

        if (tableExists($pdo, 'service_accounts') && tableExists($pdo, 'operations')) {
            $serviceAccounts = $pdo->query("
                SELECT id, account_code
                FROM service_accounts
            ")->fetchAll(PDO::FETCH_ASSOC);

            $stmtService = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit,
                    COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit
                FROM operations
            ");

            $stmtUpdateService = $pdo->prepare("
                UPDATE service_accounts
                SET current_balance = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($serviceAccounts as $account) {
                $stmtService->execute([$account['account_code'], $account['account_code']]);
                $totals = $stmtService->fetch(PDO::FETCH_ASSOC) ?: [];

                $newBalance = (float)($totals['total_credit'] ?? 0) - (float)($totals['total_debit'] ?? 0);
                $stmtUpdateService->execute([$newBalance, (int)$account['id']]);
                $report['service_accounts']++;
            }
        }

        return $report;
    }
}

if (!function_exists('sl_table_has_columns')) {
    function sl_table_has_columns(PDO $pdo, string $table, array $columns): bool
    {
        if (!tableExists($pdo, $table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!columnExists($pdo, $table, $column)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('createNotification')) {
    function createNotification(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $link = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $createdBy = null,
        ?int $targetUserId = null
    ): ?int {
        $type = trim($type);
        $message = trim($message);
        $level = trim($level) !== '' ? trim($level) : 'info';

        if ($type === '' || $message === '') {
            return null;
        }

        if (!tableExists($pdo, 'notifications')) {
            return null;
        }

        $columns = [];
        $values = [];
        $params = [];

        $availableColumns = [
            'type' => columnExists($pdo, 'notifications', 'type'),
            'message' => columnExists($pdo, 'notifications', 'message'),
            'level' => columnExists($pdo, 'notifications', 'level'),
            'link' => columnExists($pdo, 'notifications', 'link'),
            'entity_type' => columnExists($pdo, 'notifications', 'entity_type'),
            'entity_id' => columnExists($pdo, 'notifications', 'entity_id'),
            'user_id' => columnExists($pdo, 'notifications', 'user_id'),
            'created_by' => columnExists($pdo, 'notifications', 'created_by'),
            'is_read' => columnExists($pdo, 'notifications', 'is_read'),
            'created_at' => columnExists($pdo, 'notifications', 'created_at'),
        ];

        if ($availableColumns['type']) {
            $columns[] = 'type';
            $values[] = '?';
            $params[] = $type;
        }

        if ($availableColumns['message']) {
            $columns[] = 'message';
            $values[] = '?';
            $params[] = $message;
        }

        if ($availableColumns['level']) {
            $columns[] = 'level';
            $values[] = '?';
            $params[] = $level;
        }

        if ($availableColumns['link']) {
            $columns[] = 'link';
            $values[] = '?';
            $params[] = $link;
        }

        if ($availableColumns['entity_type']) {
            $columns[] = 'entity_type';
            $values[] = '?';
            $params[] = $entityType;
        }

        if ($availableColumns['entity_id']) {
            $columns[] = 'entity_id';
            $values[] = '?';
            $params[] = $entityId;
        }

        if ($availableColumns['user_id']) {
            $columns[] = 'user_id';
            $values[] = '?';
            $params[] = $targetUserId;
        }

        if ($availableColumns['created_by']) {
            $columns[] = 'created_by';
            $values[] = '?';
            $params[] = $createdBy;
        }

        if ($availableColumns['is_read']) {
            $columns[] = 'is_read';
            $values[] = '?';
            $params[] = 0;
        }

        if ($availableColumns['created_at']) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return null;
        }

        $sql = "
            INSERT INTO notifications (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$pdo->lastInsertId();
    }
}



if (!function_exists('getUnreadNotifications')) {
    function getUnreadNotifications(PDO $pdo, int $limit = 8): array
    {
        if (!tableExists($pdo, 'notifications')) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        $orderBy = columnExists($pdo, 'notifications', 'created_at') ? 'created_at DESC' : 'id DESC';

        $stmt = $pdo->prepare("
            SELECT *
            FROM notifications
            WHERE COALESCE(is_read, 0) = 0
            ORDER BY {$orderBy}
            LIMIT {$limit}
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('countUnreadNotifications')) {
    function countUnreadNotifications(PDO $pdo): int
    {
        if (!tableExists($pdo, 'notifications')) {
            return 0;
        }

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM notifications
            WHERE COALESCE(is_read, 0) = 0
        ");

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('markNotificationRead')) {
    function markNotificationRead(PDO $pdo, int $notificationId): void
    {
        if ($notificationId <= 0 || !tableExists($pdo, 'notifications')) {
            return;
        }

        $sql = "UPDATE notifications SET is_read = 1";
        if (columnExists($pdo, 'notifications', 'updated_at')) {
            $sql .= ", updated_at = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$notificationId]);
    }
}

if (!function_exists('markAllNotificationsRead')) {
    function markAllNotificationsRead(PDO $pdo): void
    {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $sql = "UPDATE notifications SET is_read = 1 WHERE COALESCE(is_read, 0) = 0";
        if (columnExists($pdo, 'notifications', 'updated_at')) {
            $sql = "UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE COALESCE(is_read, 0) = 0";
        }

        $pdo->exec($sql);
    }
}

if (!function_exists('createAuditTrail')) {
    function createAuditTrail(
        PDO $pdo,
        string $entityType,
        int $entityId,
        string $fieldName,
        $oldValue,
        $newValue,
        ?int $userId = null
    ): void {
        if (!tableExists($pdo, 'audit_trail')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_name' => $fieldName,
            'old_value' => $oldValue !== null ? (string)$oldValue : null,
            'new_value' => $newValue !== null ? (string)$newValue : null,
            'user_id' => $userId,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'audit_trail', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'audit_trail', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_trail (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);
    }
}

if (!function_exists('auditEntityChanges')) {
    function auditEntityChanges(PDO $pdo, string $entityType, int $entityId, array $before, array $after, ?int $userId = null): void
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if ((string)$oldValue !== (string)$newValue) {
                createAuditTrail($pdo, $entityType, $entityId, (string)$key, $oldValue, $newValue, $userId);
            }
        }
    }
}

if (!function_exists('getEntityTimeline')) {
    function getEntityTimeline(PDO $pdo, string $entityType, int $entityId, int $limit = 50): array
    {
        $items = [];
        $limit = max(1, min($limit, 200));

        if (tableExists($pdo, 'audit_trail')) {
            $stmt = $pdo->prepare("
                SELECT
                    'audit' AS source_type,
                    created_at,
                    field_name AS title,
                    old_value,
                    new_value,
                    user_id,
                    NULL AS details
                FROM audit_trail
                WHERE entity_type = ?
                  AND entity_id = ?
                ORDER BY created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$entityType, $entityId]);
            $items = array_merge($items, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        if (tableExists($pdo, 'user_logs') && sl_table_has_columns($pdo, 'user_logs', ['entity_type', 'entity_id'])) {
            $stmt = $pdo->prepare("
                SELECT
                    'log' AS source_type,
                    created_at,
                    action AS title,
                    NULL AS old_value,
                    NULL AS new_value,
                    user_id,
                    details
                FROM user_logs
                WHERE entity_type = ?
                  AND entity_id = ?
                ORDER BY created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$entityType, $entityId]);
            $items = array_merge($items, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        usort($items, static function ($a, $b) {
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });

        return array_slice($items, 0, $limit);
    }
}

if (!function_exists('globalSearch')) {
    function globalSearch(PDO $pdo, string $query, int $limitPerType = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [
                'clients' => [],
                'operations' => [],
                'treasury' => [],
                'services' => [],
            ];
        }

        $like = '%' . $query . '%';
        $limitPerType = max(1, min($limitPerType, 20));

        $results = [
            'clients' => [],
            'operations' => [],
            'treasury' => [],
            'services' => [],
        ];

        if (tableExists($pdo, 'clients')) {
            $conditions = [];
            if (columnExists($pdo, 'clients', 'client_code')) {
                $conditions[] = 'client_code LIKE ?';
            }
            if (columnExists($pdo, 'clients', 'full_name')) {
                $conditions[] = 'full_name LIKE ?';
            }
            if (columnExists($pdo, 'clients', 'email')) {
                $conditions[] = 'email LIKE ?';
            }

            if ($conditions) {
                $params = array_fill(0, count($conditions), $like);
                $stmt = $pdo->prepare("
                    SELECT id,
                           " . (columnExists($pdo, 'clients', 'client_code') ? 'client_code' : "'' AS client_code") . ",
                           " . (columnExists($pdo, 'clients', 'full_name') ? 'full_name' : "'' AS full_name") . "
                    FROM clients
                    WHERE " . implode(' OR ', $conditions) . "
                    ORDER BY id DESC
                    LIMIT {$limitPerType}
                ");
                $stmt->execute($params);
                $results['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        if (tableExists($pdo, 'operations')) {
            $conditions = [];
            if (columnExists($pdo, 'operations', 'reference')) {
                $conditions[] = 'reference LIKE ?';
            }
            if (columnExists($pdo, 'operations', 'label')) {
                $conditions[] = 'label LIKE ?';
            }
            if (columnExists($pdo, 'operations', 'operation_type_code')) {
                $conditions[] = 'operation_type_code LIKE ?';
            }

            if ($conditions) {
                $params = array_fill(0, count($conditions), $like);
                $stmt = $pdo->prepare("
                    SELECT id,
                           " . (columnExists($pdo, 'operations', 'reference') ? 'reference' : "'' AS reference") . ",
                           " . (columnExists($pdo, 'operations', 'label') ? 'label' : "'' AS label") . ",
                           " . (columnExists($pdo, 'operations', 'operation_date') ? 'operation_date' : "NULL AS operation_date") . "
                    FROM operations
                    WHERE " . implode(' OR ', $conditions) . "
                    ORDER BY id DESC
                    LIMIT {$limitPerType}
                ");
                $stmt->execute($params);
                $results['operations'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        if (tableExists($pdo, 'treasury_accounts')) {
            $conditions = [];
            if (columnExists($pdo, 'treasury_accounts', 'account_code')) {
                $conditions[] = 'account_code LIKE ?';
            }
            if (columnExists($pdo, 'treasury_accounts', 'account_label')) {
                $conditions[] = 'account_label LIKE ?';
            }

            if ($conditions) {
                $params = array_fill(0, count($conditions), $like);
                $stmt = $pdo->prepare("
                    SELECT id,
                           " . (columnExists($pdo, 'treasury_accounts', 'account_code') ? 'account_code' : "'' AS account_code") . ",
                           " . (columnExists($pdo, 'treasury_accounts', 'account_label') ? 'account_label' : "'' AS account_label") . "
                    FROM treasury_accounts
                    WHERE " . implode(' OR ', $conditions) . "
                    ORDER BY id DESC
                    LIMIT {$limitPerType}
                ");
                $stmt->execute($params);
                $results['treasury'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        if (tableExists($pdo, 'ref_services')) {
            $conditions = [];
            if (columnExists($pdo, 'ref_services', 'code')) {
                $conditions[] = 'code LIKE ?';
            }
            if (columnExists($pdo, 'ref_services', 'label')) {
                $conditions[] = 'label LIKE ?';
            }

            if ($conditions) {
                $params = array_fill(0, count($conditions), $like);
                $stmt = $pdo->prepare("
                    SELECT id,
                           " . (columnExists($pdo, 'ref_services', 'code') ? 'code' : "'' AS code") . ",
                           " . (columnExists($pdo, 'ref_services', 'label') ? 'label' : "'' AS label") . "
                    FROM ref_services
                    WHERE " . implode(' OR ', $conditions) . "
                    ORDER BY id DESC
                    LIMIT {$limitPerType}
                ");
                $stmt->execute($params);
                $results['services'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        return $results;
    }
}

/*
|--------------------------------------------------------------------------
| LOT 2 - helpers intelligents additifs
|--------------------------------------------------------------------------
*/

if (!function_exists('sl_get_operation_rules_summary')) {
    function sl_get_operation_rules_summary(
        ?string $operationTypeCode,
        ?string $serviceCode,
        ?string $countryCommercial = null,
        ?string $countryDestination = null
    ): array {
        if (function_exists('sl_rules_build_summary')) {
            return sl_rules_build_summary(
                $operationTypeCode,
                $serviceCode,
                $countryCommercial,
                $countryDestination
            );
        }

        return [
            'requires_client' => !($operationTypeCode === 'VIREMENT' && $serviceCode === 'INTERNE'),
            'requires_linked_bank' => false,
            'requires_manual_accounts' => sl_is_manual_accounting_case($operationTypeCode, $serviceCode),
            'service_account_tokens' => [],
            'service_account_search_text' => '',
        ];
    }
}

if (!function_exists('sl_get_operation_anomalies')) {
    function sl_get_operation_anomalies(array $payload): array
    {
        if (function_exists('sl_detect_operation_anomalies')) {
            return sl_detect_operation_anomalies($payload);
        }

        $fallback = [];

        if ((float)($payload['amount'] ?? 0) <= 0) {
            $fallback[] = [
                'level' => 'danger',
                'code' => 'INVALID_AMOUNT',
                'message' => 'Le montant est invalide ou nul.',
            ];
        }

        return $fallback;
    }
}

if (!function_exists('sl_get_import_mapping_suggestions')) {
    function sl_get_import_mapping_suggestions(array $headers): array
    {
        if (function_exists('sl_import_mapper_suggest_mapping')) {
            return sl_import_mapper_suggest_mapping($headers);
        }

        return [];
    }
}

if (!function_exists('sl_build_notification_link_for_entity')) {
    function sl_build_notification_link_for_entity(?string $entityType, ?int $entityId): ?string
    {
        if (!defined('APP_URL') || !$entityType || !$entityId || $entityId <= 0) {
            return null;
        }

        $entityType = strtolower(trim($entityType));

        return match ($entityType) {
            'client' => APP_URL . 'modules/clients/client_view.php?id=' . $entityId,
            'operation' => APP_URL . 'modules/operations/operation_view.php?id=' . $entityId,
            'treasury_account' => APP_URL . 'modules/treasury/treasury_view.php?id=' . $entityId,
            'import' => APP_URL . 'modules/imports/import_journal.php',
            default => null,
        };
    }
}

if (!function_exists('sl_create_entity_notification')) {
    function sl_create_entity_notification(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $createdBy = null,
        ?string $linkUrl = null
    ): void {
        if ($linkUrl === null) {
            $linkUrl = sl_build_notification_link_for_entity($entityType, $entityId);
        }

        createNotification(
            $pdo,
            $type,
            $message,
            $level,
            $linkUrl,
            $entityType,
            $entityId,
            $createdBy
        );
    }
}

if (!function_exists('renderPostableBadge')) {
    function renderPostableBadge(?int $isPostable): string
    {
        $isPostable = (int)$isPostable === 1;

        $class = $isPostable ? 'badge badge-success' : 'badge badge-outline';
        $label = $isPostable ? 'Postable' : 'Structure';

        return '<span class="' . $class . '">' . e($label) . '</span>';
    }
}

if (!function_exists('sl_fetch_postable_treasury_accounts')) {
    function sl_fetch_postable_treasury_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        $where = ["COALESCE(is_active,1)=1"];

        if (columnExists($pdo, 'treasury_accounts', 'is_postable')) {
            $where[] = "COALESCE(is_postable,0)=1";
        }

        $stmt = $pdo->query("
            SELECT *
            FROM treasury_accounts
            WHERE " . implode(' AND ', $where) . "
            ORDER BY account_code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_fetch_postable_service_accounts')) {
    function sl_fetch_postable_service_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return [];
        }

        $where = ["COALESCE(is_active,1)=1"];

        if (columnExists($pdo, 'service_accounts', 'is_postable')) {
            $where[] = "COALESCE(is_postable,0)=1";
        }

        $stmt = $pdo->query("
            SELECT *
            FROM service_accounts
            WHERE " . implode(' AND ', $where) . "
            ORDER BY account_code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_find_accounting_rule')) {
    function sl_find_accounting_rule(PDO $pdo, int $operationTypeId, int $serviceId): ?array
    {
        if ($operationTypeId <= 0 || $serviceId <= 0 || !tableExists($pdo, 'accounting_rules')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM accounting_rules
            WHERE operation_type_id = ?
              AND service_id = ?
              AND COALESCE(is_active,1) = 1
            LIMIT 1
        ");
        $stmt->execute([$operationTypeId, $serviceId]);

        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rule ?: null;
    }
}

if (!function_exists('sl_resolve_rule_account_code')) {
    function sl_resolve_rule_account_code(PDO $pdo, string $mode, array $payload, ?array $clientContext, ?array $matchedServiceAccount): ?string
    {
        $mode = strtoupper(trim($mode));

        return match ($mode) {
            'CLIENT_411' => (string)($clientContext['generated_client_account'] ?? ''),
            'CLIENT_512' => (string)($clientContext['treasury_account_code'] ?? ''),
            'SERVICE_706' => (string)($matchedServiceAccount['account_code'] ?? ''),
            'MANUAL_DEBIT' => trim((string)($payload['manual_debit_account_code'] ?? '')),
            'MANUAL_CREDIT' => trim((string)($payload['manual_credit_account_code'] ?? '')),
            'SOURCE_512' => trim((string)($payload['source_treasury_code'] ?? '')),
            'TARGET_512' => trim((string)($payload['target_treasury_code'] ?? '')),
            default => null,
        };
    }
}

if (!function_exists('sl_resolve_accounting_operation_from_rule')) {
    function sl_resolve_accounting_operation_from_rule(PDO $pdo, array $payload, array $rule): array
    {
        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $clientContext = null;

        if ($clientId > 0) {
            $clientContext = getClientAccountingContext($pdo, $clientId);
        }

        if ((int)($rule['requires_client'] ?? 0) === 1 && !$clientContext) {
            throw new RuntimeException('Cette règle comptable exige un client.');
        }

        if ((int)($rule['requires_manual_accounts'] ?? 0) === 1) {
            $manualDebit = trim((string)($payload['manual_debit_account_code'] ?? ''));
            $manualCredit = trim((string)($payload['manual_credit_account_code'] ?? ''));
            if ($manualDebit === '' || $manualCredit === '') {
                throw new RuntimeException('Cette règle exige des comptes manuels source et destination.');
            }
        }

        $matchedServiceAccount = null;
        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : 0;
        $labelPattern = trim((string)($rule['label_pattern'] ?? ''));

        if ($labelPattern !== '') {
            $tokens = [];
            $tokens[] = $labelPattern;

            if (!empty($clientContext['country_destination'])) {
                $tokens[] = (string)$clientContext['country_destination'];
            }
            if (!empty($clientContext['country_commercial'])) {
                $tokens[] = (string)$clientContext['country_commercial'];
            }

            $matchedServiceAccount = sl_find_service_account_by_label_rule($pdo, $serviceId, $tokens);
        }

        $debitMode = strtoupper(trim((string)($rule['debit_mode'] ?? '')));
        $creditMode = strtoupper(trim((string)($rule['credit_mode'] ?? '')));

        $debit = null;
        $credit = null;

        if ($debitMode === 'FIXED_ACCOUNT') {
            $debit = trim((string)($rule['debit_fixed_account_code'] ?? ''));
        } else {
            $debit = sl_resolve_rule_account_code($pdo, $debitMode, $payload, $clientContext, $matchedServiceAccount);
        }

        if ($creditMode === 'FIXED_ACCOUNT') {
            $credit = trim((string)($rule['credit_fixed_account_code'] ?? ''));
        } else {
            $credit = sl_resolve_rule_account_code($pdo, $creditMode, $payload, $clientContext, $matchedServiceAccount);
        }

        if ($debit === '' || $credit === '' || $debit === null || $credit === null) {
            throw new RuntimeException('La règle comptable paramétrée ne permet pas de résoudre les comptes.');
        }

        $operationHash = sl_build_operation_hash($payload, $debit, $credit);

        return [
            'debit_account_code' => $debit,
            'credit_account_code' => $credit,
            'analytic_account' => $matchedServiceAccount
                ? [
                    'account_code' => $matchedServiceAccount['account_code'] ?? null,
                    'account_label' => $matchedServiceAccount['account_label'] ?? null,
                ]
                : null,
            'client_context' => $clientContext,
            'service_info' => null,
            'linked_bank_account' => null,
            'is_manual_accounting' => (int)($rule['requires_manual_accounts'] ?? 0),
            'operation_hash' => $operationHash,
            'preview_lines' => [
                ['side' => 'DEBIT', 'account' => $debit],
                ['side' => 'CREDIT', 'account' => $credit],
            ],
            'resolved_by_rule' => 1,
            'rule_id' => (int)($rule['id'] ?? 0),
            'rule_code' => (string)($rule['rule_code'] ?? ''),
        ];
    }
}

if (!function_exists('slPreviewSessionKey')) {
    function slPreviewSessionKey(string $scope): string
    {
        return 'studely_preview_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $scope);
    }
}

if (!function_exists('slSetPreviewPayload')) {
    function slSetPreviewPayload(string $scope, array $payload): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[slPreviewSessionKey($scope)] = $payload;
    }
}

if (!function_exists('slGetPreviewPayload')) {
    function slGetPreviewPayload(string $scope): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $value = $_SESSION[slPreviewSessionKey($scope)] ?? [];
        return is_array($value) ? $value : [];
    }
}

if (!function_exists('slClearPreviewPayload')) {
    function slClearPreviewPayload(string $scope): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION[slPreviewSessionKey($scope)]);
    }
}

if (!function_exists('slRenderPreviewCard')) {
    function slRenderPreviewCard(string $title, array $rows = [], string $emptyMessage = 'Aucun aperçu disponible.'): string
    {
        ob_start();
        ?>
        <div class="sl-card sl-stable-block">
            <div class="sl-card-head">
                <div>
                    <h3><?= e($title) ?></h3>
                    <p class="sl-card-head-subtitle">Contrôle avant validation</p>
                </div>
            </div>

            <?php if ($rows): ?>
                <div class="sl-data-list">
                    <?php foreach ($rows as $label => $value): ?>
                        <div class="sl-data-list__row">
                            <span><?= e((string)$label) ?></span>
                            <strong><?= e(is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dashboard-note"><?= e($emptyMessage) ?></div>
            <?php endif; ?>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('sl_get_monthly_treasury_options')) {
    function sl_get_monthly_treasury_options(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        $sql = "
            SELECT id, account_code, account_label, currency_code, country_label
            FROM treasury_accounts
            WHERE COALESCE(is_active,1) = 1
            ORDER BY account_code ASC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_get_client_monthly_config')) {
    function sl_get_client_monthly_config(PDO $pdo, int $clientId): ?array
    {
        if ($clientId <= 0 || !tableExists($pdo, 'clients')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.client_code,
                c.full_name,
                c.generated_client_account,
                c.monthly_amount,
                c.monthly_treasury_account_id,
                c.monthly_day,
                c.monthly_enabled,
                c.monthly_last_generated_at,
                ta.account_code AS monthly_treasury_account_code,
                ta.account_label AS monthly_treasury_account_label
            FROM clients c
            LEFT JOIN treasury_accounts ta ON ta.id = c.monthly_treasury_account_id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$clientId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('sl_client_has_monthly_due_for_date')) {
    function sl_client_has_monthly_due_for_date(array $clientRow, string $runDate): bool
    {
        $runDateObj = DateTime::createFromFormat('Y-m-d', $runDate);
        if (!$runDateObj) {
            return false;
        }

        $enabled = (int)($clientRow['monthly_enabled'] ?? 0) === 1;
        $amount = (float)($clientRow['monthly_amount'] ?? 0);
        $day = (int)($clientRow['monthly_day'] ?? 26);
        $target512 = (int)($clientRow['monthly_treasury_account_id'] ?? 0);

        if (!$enabled || $amount <= 0 || $target512 <= 0) {
            return false;
        }

        $expectedDay = max(1, min(28, $day));
        if ((int)$runDateObj->format('d') !== $expectedDay) {
            return false;
        }

        $lastGeneratedAt = trim((string)($clientRow['monthly_last_generated_at'] ?? ''));
        if ($lastGeneratedAt !== '') {
            $lastTs = strtotime($lastGeneratedAt);
            if ($lastTs !== false) {
                $lastYearMonth = date('Y-m', $lastTs);
                $runYearMonth = $runDateObj->format('Y-m');
                if ($lastYearMonth === $runYearMonth) {
                    return false;
                }
            }
        }

        return true;
    }
}

if (!function_exists('sl_monthly_operation_reference')) {
    function sl_monthly_operation_reference(array $clientRow, string $runDate): string
    {
        $clientCode = trim((string)($clientRow['client_code'] ?? 'CLIENT'));
        return 'MENS-' . strtoupper($clientCode) . '-' . date('Ym', strtotime($runDate));
    }
}

if (!function_exists('sl_create_monthly_client_operation')) {
    function sl_create_monthly_client_operation(PDO $pdo, array $clientRow, string $runDate, ?int $userId = null): int
    {
        if (!tableExists($pdo, 'operations')) {
            throw new RuntimeException('Table operations introuvable.');
        }

        $clientId = (int)($clientRow['id'] ?? 0);
        $amount = (float)($clientRow['monthly_amount'] ?? 0);
        $targetTreasuryId = (int)($clientRow['monthly_treasury_account_id'] ?? 0);
        $clientAccountCode = trim((string)($clientRow['generated_client_account'] ?? ''));

        if ($clientId <= 0 || $amount <= 0 || $targetTreasuryId <= 0 || $clientAccountCode === '') {
            throw new RuntimeException('Configuration mensualité client invalide.');
        }

        $targetTreasury = findTreasuryAccountById($pdo, $targetTreasuryId);
        if (!$targetTreasury) {
            throw new RuntimeException('Compte 512 mensualité introuvable.');
        }

        $creditCode = trim((string)($targetTreasury['account_code'] ?? ''));
        if ($creditCode === '') {
            throw new RuntimeException('Code du compte 512 mensualité introuvable.');
        }

        $reference = sl_monthly_operation_reference($clientRow, $runDate);

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM operations
            WHERE client_id = ?
              AND reference = ?
            LIMIT 1
        ");
        $stmtDup->execute([$clientId, $reference]);

        $existingId = (int)($stmtDup->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $existingId;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_id' => $clientId,
            'service_id' => null,
            'operation_type_id' => null,
            'bank_account_id' => null,
            'linked_bank_account_id' => null,
            'operation_date' => $runDate,
            'operation_type_code' => 'MENSUALITE_CLIENT',
            'operation_kind' => 'auto_monthly',
            'label' => 'Mensualité client - ' . ($clientRow['full_name'] ?? $clientRow['client_code'] ?? 'Client'),
            'amount' => $amount,
            'currency_code' => $clientRow['currency'] ?? null,
            'reference' => $reference,
            'source_type' => 'system_monthly',
            'debit_account_code' => $clientAccountCode,
            'credit_account_code' => $creditCode,
            'service_account_code' => null,
            'operation_hash' => hash('sha256', implode('|', [
                $clientId,
                $runDate,
                $amount,
                $clientAccountCode,
                $creditCode,
                $reference
            ])),
            'is_manual_accounting' => 0,
            'notes' => 'Mensualité automatique générée par le système',
            'created_by' => $userId,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'operations', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'operations', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (columnExists($pdo, 'operations', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO operations (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmtInsert->execute($params);

        $operationId = (int)$pdo->lastInsertId();

        if (function_exists('recomputeAllBalances')) {
            recomputeAllBalances($pdo);
        }

        $stmtUpdateClient = $pdo->prepare("
            UPDATE clients
            SET monthly_last_generated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdateClient->execute([$clientId]);

        if (function_exists('logUserAction') && $userId !== null && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'create_monthly_operation',
                'operations',
                'operation',
                $operationId,
                'Génération automatique mensualité client ' . ($clientRow['client_code'] ?? '')
            );
        }

        return $operationId;
    }
}

if (!function_exists('sl_run_monthly_client_operations')) {
    function sl_run_monthly_client_operations(PDO $pdo, string $runDate, ?int $userId = null): array
    {
        if (!tableExists($pdo, 'clients')) {
            throw new RuntimeException('Table clients introuvable.');
        }

        $stmt = $pdo->query("
            SELECT
                c.*,
                ta.account_code AS monthly_treasury_account_code,
                ta.account_label AS monthly_treasury_account_label
            FROM clients c
            LEFT JOIN treasury_accounts ta ON ta.id = c.monthly_treasury_account_id
            WHERE COALESCE(c.is_active,1) = 1
              AND COALESCE(c.monthly_enabled,0) = 1
        ");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $report = [
            'run_date' => $runDate,
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($clients as $clientRow) {
            $report['processed']++;

            try {
                if (!sl_client_has_monthly_due_for_date($clientRow, $runDate)) {
                    $report['skipped']++;
                    continue;
                }

                sl_create_monthly_client_operation($pdo, $clientRow, $runDate, $userId);
                $report['created']++;
            } catch (Throwable $e) {
                $report['errors'][] = [
                    'client_id' => (int)($clientRow['id'] ?? 0),
                    'client_code' => (string)($clientRow['client_code'] ?? ''),
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $report;
    }
}

if (!function_exists('sl_monthly_payment_parse_csv')) {
    function sl_monthly_payment_parse_csv(string $filePath, string $delimiter = ';'): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Fichier import introuvable.');
        }

        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible d’ouvrir le fichier CSV.');
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            throw new RuntimeException('Le fichier CSV est vide.');
        }

        $header = array_map(static function ($value) {
            $value = trim((string)$value);
            $value = mb_strtolower($value);
            $value = str_replace(['é', 'è', 'ê', 'à', 'â', 'î', 'ï', 'ô', 'ù', 'û', 'ç'], ['e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'], $value);
            return $value;
        }, $header);

        $lineNumber = 1;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            if (count(array_filter($data, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($header as $index => $key) {
                $assoc[$key] = trim((string)($data[$index] ?? ''));
            }

            $rows[] = [
                'row_number' => $lineNumber,
                'client_code' => $assoc['client_code'] ?? $assoc['code_client'] ?? '',
                'monthly_amount' => $assoc['monthly_amount'] ?? $assoc['mensualite'] ?? $assoc['montant'] ?? '',
                'treasury_account_code' => $assoc['treasury_account_code'] ?? $assoc['compte_512'] ?? '',
                'monthly_day' => $assoc['monthly_day'] ?? $assoc['jour'] ?? '26',
                'label' => $assoc['label'] ?? $assoc['libelle'] ?? '',
                'raw' => $assoc,
            ];
        }

        fclose($handle);

        return $rows;
    }
}

if (!function_exists('sl_monthly_payment_find_client_by_code')) {
    function sl_monthly_payment_find_client_by_code(PDO $pdo, string $clientCode): ?array
    {
        $stmt = $pdo->prepare("
            SELECT *
            FROM clients
            WHERE client_code = ?
            LIMIT 1
        ");
        $stmt->execute([$clientCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('sl_monthly_payment_find_treasury_by_code')) {
    function sl_monthly_payment_find_treasury_by_code(PDO $pdo, string $accountCode): ?array
    {
        $stmt = $pdo->prepare("
            SELECT *
            FROM treasury_accounts
            WHERE account_code = ?
              AND COALESCE(is_active,1)=1
            LIMIT 1
        ");
        $stmt->execute([$accountCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('sl_monthly_payment_validate_row')) {
    function sl_monthly_payment_validate_row(PDO $pdo, array $row): array
    {
        $errors = [];

        $clientCode = trim((string)($row['client_code'] ?? ''));
        $amount = (float)str_replace(',', '.', (string)($row['monthly_amount'] ?? '0'));
        $treasuryCode = trim((string)($row['treasury_account_code'] ?? ''));
        $monthlyDay = (int)($row['monthly_day'] ?? 26);

        $client = null;
        $treasury = null;

        if ($clientCode === '') {
            $errors[] = 'Code client manquant.';
        } else {
            $client = sl_monthly_payment_find_client_by_code($pdo, $clientCode);
            if (!$client) {
                $errors[] = 'Client introuvable.';
            }
        }

        if ($amount <= 0) {
            $errors[] = 'Montant de mensualité invalide.';
        }

        if ($treasuryCode === '') {
            $errors[] = 'Compte 512 manquant.';
        } else {
            $treasury = sl_monthly_payment_find_treasury_by_code($pdo, $treasuryCode);
            if (!$treasury) {
                $errors[] = 'Compte 512 introuvable ou inactif.';
            }
        }

        if ($monthlyDay < 1 || $monthlyDay > 31) {
            $errors[] = 'Jour de mensualité invalide.';
        }

        return [
            'is_valid' => count($errors) === 0,
            'errors' => $errors,
            'client' => $client,
            'treasury' => $treasury,
            'amount' => $amount,
            'monthly_day' => $monthlyDay,
        ];
    }
}

if (!function_exists('sl_monthly_payment_default_label')) {
    function sl_monthly_payment_default_label(array $client): string
    {
        return 'Mensualité client - ' . (($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? ''));
    }
}

if (!function_exists('sl_monthly_payment_operation_exists')) {
    function sl_monthly_payment_operation_exists(PDO $pdo, int $clientId, string $operationDate, float $amount, string $reference): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM operations
            WHERE client_id = ?
              AND operation_date = ?
              AND amount = ?
              AND reference = ?
        ");
        $stmt->execute([$clientId, $operationDate, $amount, $reference]);

        return ((int)$stmt->fetchColumn()) > 0;
    }
}

if (!function_exists('sl_monthly_payment_create_operation')) {
    function sl_monthly_payment_create_operation(
        PDO $pdo,
        array $client,
        array $treasury,
        float $amount,
        int $scheduledDay,
        string $runDate,
        ?int $userId = null,
        string $label = ''
    ): int {
        $clientId = (int)($client['id'] ?? 0);
        $clientAccount = (string)($client['generated_client_account'] ?? '');
        $clientCurrency = (string)($client['currency'] ?? 'EUR');
        $treasuryCode = (string)($treasury['account_code'] ?? '');
        $clientCode = (string)($client['client_code'] ?? '');

        if ($clientId <= 0 || $clientAccount === '' || $treasuryCode === '') {
            throw new RuntimeException('Données insuffisantes pour créer l’opération mensuelle.');
        }

        $reference = 'MENS-' . $clientCode . '-' . str_replace('-', '', $runDate);
        $finalLabel = trim($label) !== '' ? trim($label) : sl_monthly_payment_default_label($client);

        if (sl_monthly_payment_operation_exists($pdo, $clientId, $runDate, $amount, $reference)) {
            throw new RuntimeException('Opération mensuelle déjà générée pour ce client à cette date.');
        }

        $bankAccountId = null;
        if (function_exists('findPrimaryBankAccountForClient')) {
            $bankAccount = findPrimaryBankAccountForClient($pdo, $clientId);
            if ($bankAccount && !empty($bankAccount['id'])) {
                $bankAccountId = (int)$bankAccount['id'];
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO operations (
                client_id,
                service_id,
                operation_type_id,
                bank_account_id,
                linked_bank_account_id,
                operation_date,
                operation_type_code,
                operation_kind,
                label,
                amount,
                currency_code,
                reference,
                source_type,
                debit_account_code,
                credit_account_code,
                service_account_code,
                operation_hash,
                is_manual_accounting,
                notes,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :client_id,
                NULL,
                NULL,
                :bank_account_id,
                :linked_bank_account_id,
                :operation_date,
                'VIREMENT_MENSUEL',
                'monthly_run',
                :label,
                :amount,
                :currency_code,
                :reference,
                'monthly_import',
                :debit_account_code,
                :credit_account_code,
                NULL,
                :operation_hash,
                0,
                :notes,
                :created_by,
                NOW(),
                NOW()
            )
        ");

        $operationHash = hash(
            'sha256',
            implode('|', [
                'monthly_run',
                $clientId,
                $runDate,
                number_format($amount, 2, '.', ''),
                $reference,
                $treasuryCode,
                $clientAccount
            ])
        );

        $stmt->execute([
            ':client_id' => $clientId,
            ':bank_account_id' => $bankAccountId,
            ':linked_bank_account_id' => $bankAccountId,
            ':operation_date' => $runDate,
            ':label' => $finalLabel,
            ':amount' => $amount,
            ':currency_code' => $clientCurrency !== '' ? $clientCurrency : 'EUR',
            ':reference' => $reference,
            ':debit_account_code' => $clientAccount,
            ':credit_account_code' => $treasuryCode,
            ':operation_hash' => $operationHash,
            ':notes' => 'Mensualité générée automatiquement - jour planifié: ' . $scheduledDay,
            ':created_by' => $userId,
        ]);

        $operationId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("
            UPDATE clients
            SET monthly_last_generated_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$clientId]);

        if (function_exists('recomputeAllBalances')) {
            recomputeAllBalances($pdo);
        }

        if (function_exists('logUserAction') && $userId) {
            logUserAction(
                $pdo,
                $userId,
                'create_operation',
                'monthly_payments',
                'operation',
                $operationId,
                'Génération d’une mensualité'
            );
        }

        return $operationId;
    }
}

if (!function_exists('sl_monthly_payment_create_run_item')) {
    function sl_monthly_payment_create_run_item(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO monthly_payment_run_items (
                run_id,
                client_id,
                client_code,
                operation_id,
                status,
                amount,
                treasury_account_id,
                treasury_account_code,
                reference,
                label,
                message,
                created_at,
                updated_at
            ) VALUES (
                :run_id,
                :client_id,
                :client_code,
                :operation_id,
                :status,
                :amount,
                :treasury_account_id,
                :treasury_account_code,
                :reference,
                :label,
                :message,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':run_id' => (int)($data['run_id'] ?? 0),
            ':client_id' => $data['client_id'] !== null ? (int)$data['client_id'] : null,
            ':client_code' => $data['client_code'] ?? null,
            ':operation_id' => $data['operation_id'] !== null ? (int)$data['operation_id'] : null,
            ':status' => $data['status'] ?? 'pending',
            ':amount' => (float)($data['amount'] ?? 0),
            ':treasury_account_id' => $data['treasury_account_id'] !== null ? (int)$data['treasury_account_id'] : null,
            ':treasury_account_code' => $data['treasury_account_code'] ?? null,
            ':reference' => $data['reference'] ?? null,
            ':label' => $data['label'] ?? null,
            ':message' => $data['message'] ?? null,
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('sl_monthly_payment_build_reference')) {
    function sl_monthly_payment_build_reference(array $client, string $runDate): string
    {
        return 'MENS-' . ((string)($client['client_code'] ?? '')) . '-' . str_replace('-', '', $runDate);
    }
}

if (!function_exists('sl_monthly_payment_mark_operation_with_run')) {
    function sl_monthly_payment_mark_operation_with_run(PDO $pdo, int $operationId, int $runId): void
    {
        if ($operationId <= 0 || $runId <= 0) {
            return;
        }

        if (!tableExists($pdo, 'operations') || !columnExists($pdo, 'operations', 'monthly_run_id')) {
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE operations
            SET monthly_run_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$runId, $operationId]);
    }
}

if (!function_exists('sl_monthly_payment_create_operation_with_run')) {
    function sl_monthly_payment_create_operation_with_run(
        PDO $pdo,
        array $client,
        array $treasury,
        float $amount,
        int $scheduledDay,
        string $runDate,
        int $runId,
        ?int $userId = null,
        string $label = ''
    ): int {
        $operationId = sl_monthly_payment_create_operation(
            $pdo,
            $client,
            $treasury,
            $amount,
            $scheduledDay,
            $runDate,
            $userId,
            $label
        );

        sl_monthly_payment_mark_operation_with_run($pdo, $operationId, $runId);

        return $operationId;
    }
}

if (!function_exists('sl_monthly_payment_get_run_totals')) {
    function sl_monthly_payment_get_run_totals(PDO $pdo, int $runId): array
    {
        if ($runId <= 0 || !tableExists($pdo, 'monthly_payment_run_items')) {
            return [
                'total_items' => 0,
                'success_count' => 0,
                'skipped_count' => 0,
                'error_count' => 0,
                'total_amount_created' => 0,
            ];
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_items,
                COALESCE(SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END), 0) AS success_count,
                COALESCE(SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END), 0) AS skipped_count,
                COALESCE(SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END), 0) AS error_count,
                COALESCE(SUM(CASE WHEN status = 'created' THEN amount ELSE 0 END), 0) AS total_amount_created
            FROM monthly_payment_run_items
            WHERE run_id = ?
        ");
        $stmt->execute([$runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'total_items' => 0,
            'success_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'total_amount_created' => 0,
        ];
    }
}

if (!function_exists('sl_monthly_payment_cancel_run')) {
    function sl_monthly_payment_cancel_run(PDO $pdo, int $runId, ?int $userId = null): array
    {
        if ($runId <= 0) {
            throw new RuntimeException('Run invalide.');
        }

        $stmt = $pdo->prepare("SELECT * FROM monthly_payment_runs WHERE id = ?");
        $stmt->execute([$runId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$run) {
            throw new RuntimeException('Run introuvable.');
        }

        if (($run['status'] ?? '') === 'cancelled') {
            throw new RuntimeException('Ce run est déjà annulé.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT *
            FROM monthly_payment_run_items
            WHERE run_id = ?
        ");
        $stmt->execute([$runId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deleted = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $operationId = (int)($item['operation_id'] ?? 0);

            if ($operationId > 0) {
                $stmtDelete = $pdo->prepare("DELETE FROM operations WHERE id = ?");
                $stmtDelete->execute([$operationId]);
                $deleted++;
            } else {
                $skipped++;
            }

            if (columnExists($pdo, 'monthly_payment_run_items', 'is_cancelled')) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE monthly_payment_run_items
                    SET is_cancelled = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$item['id']]);
            }
        }

        $stmtRun = $pdo->prepare("
            UPDATE monthly_payment_runs
            SET status = 'cancelled'
            WHERE id = ?
        ");
        $stmtRun->execute([$runId]);

        if (function_exists('logUserAction') && $userId) {
            logUserAction(
                $pdo,
                $userId,
                'cancel_monthly_run',
                'monthly_payments',
                'monthly_payment_run',
                $runId,
                'Annulation complète du run'
            );
        }

        $pdo->commit();

        return [
            'deleted_operations' => $deleted,
            'skipped_items' => $skipped
        ];
    }
}

if (!function_exists('sl_dashboard_safe_date')) {
    function sl_dashboard_safe_date(?string $value, string $default): string
    {
        $value = trim((string)$value);
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return $default;
    }
}

if (!function_exists('sl_dashboard_get_overview_kpis')) {
    function sl_dashboard_get_overview_kpis(PDO $pdo, array $filters = []): array
    {
        $global = function_exists('sl_dashboard_get_global_kpis')
            ? sl_dashboard_get_global_kpis($pdo, $filters)
            : [];

        return [
            'global_411_balance' => (float)($global['balances']['accounts_411'] ?? 0),
            'global_512_balance' => (float)($global['balances']['accounts_512'] ?? 0),
            'global_706_balance' => (float)($global['balances']['accounts_706'] ?? 0),
            'students_active' => (int)($global['students']['active'] ?? 0),
            'students_inactive' => (int)($global['students']['inactive'] ?? 0),
            'monthly_remaining_current_month' => (float)($global['monthly']['remaining_current_month'] ?? 0),
            'monthly_done_current_month' => (float)($global['monthly']['done_current_month'] ?? 0),
        ];
    }
}

if (!function_exists('sl_dashboard_get_operations_summary')) {
    function sl_dashboard_get_operations_summary(PDO $pdo, array $filters = []): array
    {
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $periodStart = sl_dashboard_safe_date($filters['period_start'] ?? '', $monthStart);
        $periodEnd = sl_dashboard_safe_date($filters['period_end'] ?? '', $monthEnd);

        $data = [
            'operations_count' => 0,
            'operations_amount' => 0,
        ];

        if (!tableExists($pdo, 'operations')) {
            return $data;
        }

        if (!columnExists($pdo, 'operations', 'operation_date') || !columnExists($pdo, 'operations', 'amount')) {
            return $data;
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(amount), 0) AS total_amount
            FROM operations
            WHERE operation_date BETWEEN ? AND ?
        ");
        $stmt->execute([$periodStart, $periodEnd]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $data['operations_count'] = (int)($row['total_count'] ?? 0);
        $data['operations_amount'] = (float)($row['total_amount'] ?? 0);

        return $data;
    }
}

if (!function_exists('sl_dashboard_get_low_balance_clients')) {
    function sl_dashboard_get_low_balance_clients(PDO $pdo, float $threshold = 1000, int $limit = 10): array
    {
        if (!tableExists($pdo, 'clients')) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        if (tableExists($pdo, 'client_bank_accounts') && tableExists($pdo, 'bank_accounts')) {
            $stmt = $pdo->prepare("
                SELECT
                    c.id,
                    c.client_code,
                    c.full_name,
                    c.generated_client_account,
                    ba.balance
                FROM clients c
                INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
                INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
                WHERE COALESCE(c.is_active,1)=1
                  AND ba.account_number LIKE '411%'
                  AND COALESCE(ba.balance,0) < ?
                ORDER BY ba.balance ASC, c.client_code ASC
                LIMIT {$limit}
            ");
            $stmt->execute([$threshold]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (tableExists($pdo, 'bank_accounts')) {
            $stmt = $pdo->prepare("
                SELECT
                    c.id,
                    c.client_code,
                    c.full_name,
                    c.generated_client_account,
                    ba.balance
                FROM clients c
                INNER JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
                WHERE COALESCE(c.is_active,1)=1
                  AND COALESCE(ba.balance,0) < ?
                ORDER BY ba.balance ASC, c.client_code ASC
                LIMIT {$limit}
            ");
            $stmt->execute([$threshold]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [];
    }
}

if (!function_exists('sl_dashboard_get_operation_types_breakdown')) {
    function sl_dashboard_get_operation_types_breakdown(PDO $pdo, array $filters = []): array
    {
        if (!tableExists($pdo, 'operations') || !columnExists($pdo, 'operations', 'amount')) {
            return [];
        }

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $periodStart = sl_dashboard_safe_date($filters['period_start'] ?? '', $monthStart);
        $periodEnd = sl_dashboard_safe_date($filters['period_end'] ?? '', $monthEnd);

        $typeExpr = columnExists($pdo, 'operations', 'operation_type_code')
            ? 'COALESCE(operation_type_code, "N/A")'
            : '"N/A"';

        $sql = "
            SELECT
                {$typeExpr} AS operation_type,
                COUNT(*) AS total_count,
                COALESCE(SUM(amount), 0) AS total_amount
            FROM operations
        ";

        $where = [];
        $params = [];

        if (columnExists($pdo, 'operations', 'operation_date')) {
            $where[] = "operation_date BETWEEN ? AND ?";
            $params[] = $periodStart;
            $params[] = $periodEnd;
        }

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY {$typeExpr} ORDER BY total_amount DESC, total_count DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_dashboard_get_services_breakdown')) {
    function sl_dashboard_get_services_breakdown(PDO $pdo, array $filters = []): array
    {
        if (!tableExists($pdo, 'operations') || !columnExists($pdo, 'operations', 'amount')) {
            return [];
        }

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $periodStart = sl_dashboard_safe_date($filters['period_start'] ?? '', $monthStart);
        $periodEnd = sl_dashboard_safe_date($filters['period_end'] ?? '', $monthEnd);

        $serviceLabelExpr = "COALESCE(rs.label, rs.code, 'N/A')";
        $serviceCodeExpr = "COALESCE(rs.code, 'N/A')";

        if (columnExists($pdo, 'operations', 'service_code')) {
            $serviceLabelExpr = "COALESCE(rs.label, o.service_code, rs.code, 'N/A')";
            $serviceCodeExpr = "COALESCE(o.service_code, rs.code, 'N/A')";
        }

        $sql = "
            SELECT
                {$serviceLabelExpr} AS service_label,
                {$serviceCodeExpr} AS service_code,
                COUNT(*) AS total_count,
                COALESCE(SUM(o.amount), 0) AS total_amount
            FROM operations o
            LEFT JOIN ref_services rs ON rs.id = o.service_id
        ";

        $where = [];
        $params = [];

        if (columnExists($pdo, 'operations', 'operation_date')) {
            $where[] = "o.operation_date BETWEEN ? AND ?";
            $params[] = $periodStart;
            $params[] = $periodEnd;
        }

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY {$serviceLabelExpr}, {$serviceCodeExpr}
            ORDER BY total_amount DESC, total_count DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


if (!function_exists('sl_dashboard_get_commercial_countries_breakdown')) {
    function sl_dashboard_get_commercial_countries_breakdown(PDO $pdo, array $filters = []): array
    {
        if (!tableExists($pdo, 'clients')) {
            return [];
        }

        $sql = "
            SELECT
                COALESCE(country_commercial, 'N/A') AS country_commercial,
                COUNT(*) AS clients_count,
                COALESCE(SUM(monthly_amount), 0) AS monthly_amount_total,
                0 AS balance_total
            FROM clients
            GROUP BY COALESCE(country_commercial, 'N/A')
            ORDER BY clients_count DESC, monthly_amount_total DESC
        ";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (tableExists($pdo, 'bank_accounts')) {
            foreach ($rows as &$row) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(ba.balance), 0)
                    FROM clients c
                    LEFT JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
                    WHERE COALESCE(c.country_commercial, 'N/A') = ?
                ");
                $stmt->execute([(string)$row['country_commercial']]);
                $row['balance_total'] = (float)$stmt->fetchColumn();
            }
            unset($row);
        }

        return $rows;
    }
}

if (!function_exists('sl_dashboard_get_accounting_indicators')) {
    function sl_dashboard_get_accounting_indicators(PDO $pdo, array $filters = []): array
    {
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $periodStart = sl_dashboard_safe_date($filters['period_start'] ?? '', $monthStart);
        $periodEnd = sl_dashboard_safe_date($filters['period_end'] ?? '', $monthEnd);

        $data = [
            'Mouvements débit 411' => 0,
            'Mouvements crédit 411' => 0,
            'Mouvements débit 512' => 0,
            'Mouvements crédit 512' => 0,
            'Mouvements crédit 706' => 0,
        ];

        if (!tableExists($pdo, 'operations') || !columnExists($pdo, 'operations', 'amount')) {
            return $data;
        }

        $hasDate = columnExists($pdo, 'operations', 'operation_date');
        $params = [];
        $dateSql = '';

        if ($hasDate) {
            $dateSql = " AND operation_date BETWEEN ? AND ? ";
            $params = [$periodStart, $periodEnd];
        }

        if (columnExists($pdo, 'operations', 'debit_account_code')) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM operations
                WHERE debit_account_code LIKE '411%'
                {$dateSql}
            ");
            $stmt->execute($params);
            $data['Mouvements débit 411'] = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM operations
                WHERE debit_account_code LIKE '512%'
                {$dateSql}
            ");
            $stmt->execute($params);
            $data['Mouvements débit 512'] = (float)$stmt->fetchColumn();
        }

        if (columnExists($pdo, 'operations', 'credit_account_code')) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM operations
                WHERE credit_account_code LIKE '411%'
                {$dateSql}
            ");
            $stmt->execute($params);
            $data['Mouvements crédit 411'] = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM operations
                WHERE credit_account_code LIKE '512%'
                {$dateSql}
            ");
            $stmt->execute($params);
            $data['Mouvements crédit 512'] = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM operations
                WHERE credit_account_code LIKE '706%'
                {$dateSql}
            ");
            $stmt->execute($params);
            $data['Mouvements crédit 706'] = (float)$stmt->fetchColumn();
        }

        return $data;
    }
}

if (!function_exists('sl_parse_common_list_filters')) {
    function sl_parse_common_list_filters(array $input): array
    {
        return [
            'q' => trim((string)($input['q'] ?? '')),

            'status' => trim((string)($input['status'] ?? '')),
            'client_type' => trim((string)($input['client_type'] ?? '')),
            'country_commercial' => trim((string)($input['country_commercial'] ?? '')),

            'date_from' => trim((string)($input['date_from'] ?? '')),
            'date_to' => trim((string)($input['date_to'] ?? '')),
            'operation_type_code' => trim((string)($input['operation_type_code'] ?? '')),
            'service_id' => trim((string)($input['service_id'] ?? '')),

            'page' => max(1, (int)($input['page'] ?? 1)),
            'per_page' => max(1, min(200, (int)($input['per_page'] ?? 20))),
        ];
    }
}

if (!function_exists('sl_clients_list_get_kpis')) {
    function sl_clients_list_get_kpis(PDO $pdo, array $filters = []): array
    {
        $filters = array_merge([
            'q' => '',
            'status' => '',
            'client_type' => '',
            'country_commercial' => '',
        ], $filters);

        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = "(
                COALESCE(c.client_code, '') LIKE ?
                OR COALESCE(c.full_name, '') LIKE ?
                OR COALESCE(c.email, '') LIKE ?
                OR COALESCE(c.generated_client_account, '') LIKE ?
            )";
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($filters['status'] === 'active') {
            $where[] = "COALESCE(c.is_active, 1) = 1";
        } elseif ($filters['status'] === 'inactive') {
            $where[] = "COALESCE(c.is_active, 1) = 0";
        }

        if ($filters['client_type'] !== '' && columnExists($pdo, 'clients', 'client_type')) {
            $where[] = "COALESCE(c.client_type, '') = ?";
            $params[] = $filters['client_type'];
        }

        if ($filters['country_commercial'] !== '' && columnExists($pdo, 'clients', 'country_commercial')) {
            $where[] = "COALESCE(c.country_commercial, '') = ?";
            $params[] = $filters['country_commercial'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $joinBankAccounts = '';
        $selectCurrentBalance = '0 AS current_balance_411';
        $selectInitialBalance = '0 AS initial_balance_411';

        if (tableExists($pdo, 'bank_accounts')) {
            $joinBankAccounts = "
                LEFT JOIN bank_accounts ba
                    ON ba.account_number = c.generated_client_account
            ";
            $selectCurrentBalance = 'COALESCE(ba.balance, 0) AS current_balance_411';
            $selectInitialBalance = 'COALESCE(ba.initial_balance, 0) AS initial_balance_411';
        }

        $joinMonthly = '';
        $selectMonthly = '0 AS monthly_amount';

        if (tableExists($pdo, 'monthly_payments')) {
            $joinMonthly = "
                LEFT JOIN (
                    SELECT
                        mp.client_id,
                        SUM(CASE WHEN COALESCE(mp.is_active, 1) = 1 THEN COALESCE(mp.monthly_amount, 0) ELSE 0 END) AS monthly_amount
                    FROM monthly_payments mp
                    GROUP BY mp.client_id
                ) mp ON mp.client_id = c.id
            ";
            $selectMonthly = 'COALESCE(mp.monthly_amount, 0) AS monthly_amount';
        }

        $sql = "
            SELECT
                COUNT(DISTINCT c.id) AS total_clients,
                SUM(CASE WHEN COALESCE(c.is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_clients,
                SUM(CASE WHEN COALESCE(c.is_active, 1) = 0 THEN 1 ELSE 0 END) AS inactive_clients,
                COALESCE(SUM(src.initial_balance_411), 0) AS initial_balance_total,
                COALESCE(SUM(src.current_balance_411), 0) AS current_balance_total,
                COALESCE(SUM(src.monthly_amount), 0) AS monthly_amount_total
            FROM (
                SELECT
                    c.id,
                    COALESCE(c.is_active, 1) AS is_active,
                    {$selectInitialBalance},
                    {$selectCurrentBalance},
                    {$selectMonthly}
                FROM clients c
                {$joinBankAccounts}
                {$joinMonthly}
                {$whereSql}
            ) src
            RIGHT JOIN clients c ON c.id = src.id
        ";

        // Simpler and safer alternative for compatibility
        $sql = "
            SELECT
                COUNT(*) AS total_clients,
                SUM(CASE WHEN COALESCE(x.is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_clients,
                SUM(CASE WHEN COALESCE(x.is_active, 1) = 0 THEN 1 ELSE 0 END) AS inactive_clients,
                COALESCE(SUM(x.initial_balance_411), 0) AS initial_balance_total,
                COALESCE(SUM(x.current_balance_411), 0) AS current_balance_total,
                COALESCE(SUM(x.monthly_amount), 0) AS monthly_amount_total
            FROM (
                SELECT
                    c.id,
                    COALESCE(c.is_active, 1) AS is_active,
                    {$selectInitialBalance},
                    {$selectCurrentBalance},
                    {$selectMonthly}
                FROM clients c
                {$joinBankAccounts}
                {$joinMonthly}
                {$whereSql}
            ) x
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_clients' => (int)($row['total_clients'] ?? 0),
            'active_clients' => (int)($row['active_clients'] ?? 0),
            'inactive_clients' => (int)($row['inactive_clients'] ?? 0),
            'initial_balance_total' => (float)($row['initial_balance_total'] ?? 0),
            'current_balance_total' => (float)($row['current_balance_total'] ?? 0),
            'monthly_amount_total' => (float)($row['monthly_amount_total'] ?? 0),
        ];
    }
}

if (!function_exists('sl_clients_list_get_rows')) {
    function sl_clients_list_get_rows(PDO $pdo, array $filters = []): array
    {
        $filters = array_merge([
            'q' => '',
            'status' => '',
            'client_type' => '',
            'country_commercial' => '',
            'page' => 1,
            'per_page' => 20,
        ], $filters);

        $page = max(1, (int)$filters['page']);
        $perPage = (int)$filters['per_page'];

        if ($perPage <= 0) {
            $perPage = 20;
        }

        $allowedPerPage = [10, 20, 50, 100, 200];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }

        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = "(
                COALESCE(c.client_code, '') LIKE ?
                OR COALESCE(c.full_name, '') LIKE ?
                OR COALESCE(c.email, '') LIKE ?
                OR COALESCE(c.generated_client_account, '') LIKE ?
            )";
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($filters['status'] === 'active') {
            $where[] = "COALESCE(c.is_active, 1) = 1";
        } elseif ($filters['status'] === 'inactive') {
            $where[] = "COALESCE(c.is_active, 1) = 0";
        }

        if ($filters['client_type'] !== '' && columnExists($pdo, 'clients', 'client_type')) {
            $where[] = "COALESCE(c.client_type, '') = ?";
            $params[] = $filters['client_type'];
        }

        if ($filters['country_commercial'] !== '' && columnExists($pdo, 'clients', 'country_commercial')) {
            $where[] = "COALESCE(c.country_commercial, '') = ?";
            $params[] = $filters['country_commercial'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $joinBankAccounts = '';
        $selectCurrentBalance = '0 AS current_balance_411';
        $selectInitialBalance = '0 AS initial_balance_411';

        if (tableExists($pdo, 'bank_accounts')) {
            $joinBankAccounts = "
                LEFT JOIN bank_accounts ba
                    ON ba.account_number = c.generated_client_account
            ";
            $selectCurrentBalance = 'COALESCE(ba.balance, 0) AS current_balance_411';
            $selectInitialBalance = 'COALESCE(ba.initial_balance, 0) AS initial_balance_411';
        }

        $joinTreasury = '';
        $selectTreasuryCode = "'' AS treasury_account_code";
        $selectTreasuryLabel = "'' AS treasury_account_label";

        if (tableExists($pdo, 'treasury_accounts') && columnExists($pdo, 'clients', 'primary_treasury_account_id')) {
            $joinTreasury = "
                LEFT JOIN treasury_accounts ta
                    ON ta.id = c.primary_treasury_account_id
            ";
            $selectTreasuryCode = 'COALESCE(ta.account_code, "") AS treasury_account_code';
            $selectTreasuryLabel = 'COALESCE(ta.account_label, "") AS treasury_account_label';
        }

        $joinMonthly = '';
        $selectMonthly = '0 AS monthly_amount';

        if (tableExists($pdo, 'monthly_payments')) {
            $joinMonthly = "
                LEFT JOIN (
                    SELECT
                        mp.client_id,
                        SUM(CASE WHEN COALESCE(mp.is_active, 1) = 1 THEN COALESCE(mp.monthly_amount, 0) ELSE 0 END) AS monthly_amount
                    FROM monthly_payments mp
                    GROUP BY mp.client_id
                ) mp ON mp.client_id = c.id
            ";
            $selectMonthly = 'COALESCE(mp.monthly_amount, 0) AS monthly_amount';
        }

        $countSql = "
            SELECT COUNT(*) AS total
            FROM clients c
            {$whereSql}
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetchColumn() ?: 0);

        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                c.*,
                {$selectInitialBalance},
                {$selectCurrentBalance},
                {$selectTreasuryCode},
                {$selectTreasuryLabel},
                {$selectMonthly}
            FROM clients c
            {$joinBankAccounts}
            {$joinTreasury}
            {$joinMonthly}
            {$whereSql}
            ORDER BY COALESCE(c.is_active, 1) DESC, c.client_code ASC, c.full_name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }
}


if (!function_exists('sl_operations_list_get_kpis')) {
    function sl_operations_list_get_kpis(PDO $pdo, array $filters = []): array
    {
        $kpis = [
            'total_operations' => 0,
            'total_amount' => 0.0,
            'month_operations' => 0,
            'month_amount' => 0.0,
            'manual_count' => 0,
            'types_count' => 0,
        ];

        if (!tableExists($pdo, 'operations')) {
            return $kpis;
        }

        $currentMonth = date('Y-m');

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_operations,
                COALESCE(SUM(amount),0) AS total_amount,
                COALESCE(SUM(CASE WHEN DATE_FORMAT(operation_date, '%Y-%m') = ? THEN 1 ELSE 0 END),0) AS month_operations,
                COALESCE(SUM(CASE WHEN DATE_FORMAT(operation_date, '%Y-%m') = ? THEN amount ELSE 0 END),0) AS month_amount,
                COALESCE(SUM(CASE WHEN COALESCE(is_manual_accounting,0)=1 THEN 1 ELSE 0 END),0) AS manual_count,
                COUNT(DISTINCT COALESCE(operation_type_code,'')) AS types_count
            FROM operations
        ");
        $stmt->execute([$currentMonth, $currentMonth]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis['total_operations'] = (int)($row['total_operations'] ?? 0);
        $kpis['total_amount'] = (float)($row['total_amount'] ?? 0);
        $kpis['month_operations'] = (int)($row['month_operations'] ?? 0);
        $kpis['month_amount'] = (float)($row['month_amount'] ?? 0);
        $kpis['manual_count'] = (int)($row['manual_count'] ?? 0);
        $kpis['types_count'] = (int)($row['types_count'] ?? 0);

        return $kpis;
    }
}

if (!function_exists('sl_operations_list_get_rows')) {
    function sl_operations_list_get_rows(PDO $pdo, array $filters = []): array
    {
        $f = sl_parse_common_list_filters($filters);

        if (!tableExists($pdo, 'operations')) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => $f['page'],
                'per_page' => $f['per_page'],
                'pages' => 1,
            ];
        }

        $where = ['1=1'];
        $params = [];

        if ($f['q'] !== '') {
            $where[] = "(
                o.label LIKE ?
                OR o.reference LIKE ?
                OR o.operation_type_code LIKE ?
                OR c.client_code LIKE ?
                OR c.full_name LIKE ?
            )";
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if ($f['date_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_from'])) {
            $where[] = "o.operation_date >= ?";
            $params[] = $f['date_from'];
        }

        if ($f['date_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_to'])) {
            $where[] = "o.operation_date <= ?";
            $params[] = $f['date_to'];
        }

        if ($f['operation_type_code'] !== '') {
            $where[] = "o.operation_type_code = ?";
            $params[] = $f['operation_type_code'];
        }

        if ($f['service_id'] !== '') {
            $where[] = "o.service_id = ?";
            $params[] = (int)$f['service_id'];
        }

        $countSql = "
            SELECT COUNT(*)
            FROM operations o
            LEFT JOIN clients c ON c.id = o.client_id
            WHERE " . implode(' AND ', $where);
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = ($f['page'] - 1) * $f['per_page'];
        $pages = max(1, (int)ceil($total / $f['per_page']));

        $sql = "
            SELECT
                o.*,
                c.client_code,
                c.full_name AS client_full_name,
                rs.label AS service_label,
                lta.account_code AS linked_treasury_account_code,
                lta.account_label AS linked_treasury_account_label
            FROM operations o
            LEFT JOIN clients c ON c.id = o.client_id
            LEFT JOIN ref_services rs ON rs.id = o.service_id
            LEFT JOIN treasury_accounts lta ON lta.id = o.linked_bank_account_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.operation_date DESC, o.id DESC
            LIMIT {$f['per_page']} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $f['page'],
            'per_page' => $f['per_page'],
            'pages' => $pages,
        ];
    }
}

if (!function_exists('sl_client_accounts_get_kpis')) {
    function sl_client_accounts_get_kpis(PDO $pdo, array $filters = []): array
    {
        $kpis = [
            'accounts_count' => 0,
            'initial_balance_total' => 0.0,
            'current_balance_total' => 0.0,
            'negative_accounts_count' => 0,
        ];

        if (!tableExists($pdo, 'bank_accounts')) {
            return $kpis;
        }

        $stmt = $pdo->query("
            SELECT
                COUNT(*) AS accounts_count,
                COALESCE(SUM(CASE WHEN account_number LIKE '411%' THEN COALESCE(initial_balance,0) ELSE 0 END),0) AS initial_balance_total,
                COALESCE(SUM(CASE WHEN account_number LIKE '411%' THEN COALESCE(balance,0) ELSE 0 END),0) AS current_balance_total,
                COALESCE(SUM(CASE WHEN account_number LIKE '411%' AND COALESCE(balance,0) < 0 THEN 1 ELSE 0 END),0) AS negative_accounts_count
            FROM bank_accounts
            WHERE account_number LIKE '411%'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis['accounts_count'] = (int)($row['accounts_count'] ?? 0);
        $kpis['initial_balance_total'] = (float)($row['initial_balance_total'] ?? 0);
        $kpis['current_balance_total'] = (float)($row['current_balance_total'] ?? 0);
        $kpis['negative_accounts_count'] = (int)($row['negative_accounts_count'] ?? 0);

        return $kpis;
    }
}

if (!function_exists('sl_client_accounts_get_rows')) {
    function sl_client_accounts_get_rows(PDO $pdo, array $filters = []): array
    {
        if (!tableExists($pdo, 'bank_accounts')) {
            return [
                'rows' => [],
                'page' => 1,
                'pages' => 1,
                'per_page' => 20,
                'total' => 0,
                'from' => 0,
                'to' => 0,
            ];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, (int)($filters['per_page'] ?? 20));

        $where = ['1=1'];
        $params = [];

        $hasClients = tableExists($pdo, 'clients');

        if (!empty($filters['q'])) {
            $q = '%' . trim((string)$filters['q']) . '%';

            if ($hasClients) {
                $where[] = "(
                    COALESCE(ba.account_number,'') LIKE ?
                    OR COALESCE(ba.account_name,'') LIKE ?
                    OR COALESCE(c.client_code,'') LIKE ?
                    OR COALESCE(c.full_name,'') LIKE ?
                )";
                array_push($params, $q, $q, $q, $q);
            } else {
                $where[] = "(
                    COALESCE(ba.account_number,'') LIKE ?
                    OR COALESCE(ba.account_name,'') LIKE ?
                )";
                array_push($params, $q, $q);
            }
        }

        if ($hasClients && !empty($filters['client_status'])) {
            if ($filters['client_status'] === 'active') {
                $where[] = 'COALESCE(c.is_active,1) = 1';
            } elseif ($filters['client_status'] === 'archived') {
                $where[] = 'COALESCE(c.is_active,1) = 0';
            }
        }

        if (!empty($filters['balance_filter'])) {
            switch ($filters['balance_filter']) {
                case 'positive':
                    $where[] = 'COALESCE(ba.balance,0) > 0';
                    break;
                case 'negative':
                    $where[] = 'COALESCE(ba.balance,0) < 0';
                    break;
                case 'zero':
                    $where[] = 'COALESCE(ba.balance,0) = 0';
                    break;
                case 'non_zero':
                    $where[] = 'COALESCE(ba.balance,0) <> 0';
                    break;
            }
        }

        if (array_key_exists('balance_min', $filters) && $filters['balance_min'] !== null) {
            $where[] = 'COALESCE(ba.balance,0) >= ?';
            $params[] = (float)$filters['balance_min'];
        }

        if (array_key_exists('balance_max', $filters) && $filters['balance_max'] !== null) {
            $where[] = 'COALESCE(ba.balance,0) <= ?';
            $params[] = (float)$filters['balance_max'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(*)
            FROM bank_accounts ba
            " . ($hasClients ? "LEFT JOIN clients c ON c.generated_client_account = ba.account_number" : "") . "
            WHERE {$whereSql}
        ";

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetchColumn() ?: 0);

        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $perPage;

        $sortMap = [
            'account_number_asc' => 'ba.account_number ASC',
            'account_number_desc' => 'ba.account_number DESC',
            'client_name_asc' => 'c.full_name ASC, ba.account_number ASC',
            'client_name_desc' => 'c.full_name DESC, ba.account_number ASC',
            'balance_asc' => 'COALESCE(ba.balance,0) ASC, ba.account_number ASC',
            'balance_desc' => 'COALESCE(ba.balance,0) DESC, ba.account_number ASC',
            'initial_balance_desc' => 'COALESCE(ba.initial_balance,0) DESC, ba.account_number ASC',
        ];

        $sort = (string)($filters['sort'] ?? 'account_number_asc');
        $orderBy = $sortMap[$sort] ?? $sortMap['account_number_asc'];

        $sql = "
            SELECT
                ba.account_number,
                ba.account_name,
                ba.initial_balance,
                ba.balance,
                " . ($hasClients ? "
                c.id AS client_id,
                c.client_code,
                c.full_name,
                c.is_active AS client_is_active
                " : "
                NULL AS client_id,
                NULL AS client_code,
                NULL AS full_name,
                1 AS client_is_active
                ") . "
            FROM bank_accounts ba
            " . ($hasClients ? "LEFT JOIN clients c ON c.generated_client_account = ba.account_number" : "") . "
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $from = $total > 0 ? ($offset + 1) : 0;
        $to = min($offset + $perPage, $total);

        return [
            'rows' => $rows,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $from,
            'to' => $to,
        ];
    }
}
if (!function_exists('sl_dashboard_get_monthly_payments_done_amount')) {
    function sl_dashboard_get_monthly_payments_done_amount(PDO $pdo, ?string $month = null): float
    {
        if (!tableExists($pdo, 'clients')) {
            return 0.0;
        }

        $month = $month ?: date('Y-m');

        if (!columnExists($pdo, 'clients', 'monthly_amount')) {
            return 0.0;
        }

        if (!columnExists($pdo, 'clients', 'monthly_last_generated_at')) {
            return 0.0;
        }

        $where = [
            "COALESCE(is_active,1) = 1",
            "COALESCE(monthly_amount,0) > 0",
            "monthly_last_generated_at IS NOT NULL",
            "DATE_FORMAT(monthly_last_generated_at, '%Y-%m') = ?",
        ];

        if (columnExists($pdo, 'clients', 'monthly_enabled')) {
            $where[] = "COALESCE(monthly_enabled,0) = 1";
        }

        $sql = "
            SELECT COALESCE(SUM(monthly_amount), 0) AS total_done
            FROM clients
            WHERE " . implode(' AND ', $where);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$month]);

        return (float)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('sl_dashboard_get_monthly_payments_remaining_amount')) {
    function sl_dashboard_get_monthly_payments_remaining_amount(PDO $pdo, ?string $month = null): float
    {
        if (!tableExists($pdo, 'clients')) {
            return 0.0;
        }

        $month = $month ?: date('Y-m');

        if (!columnExists($pdo, 'clients', 'monthly_amount')) {
            return 0.0;
        }

        if (!columnExists($pdo, 'clients', 'monthly_last_generated_at')) {
            return 0.0;
        }

        $where = [
            "COALESCE(is_active,1) = 1",
            "COALESCE(monthly_amount,0) > 0",
            "(
                monthly_last_generated_at IS NULL
                OR DATE_FORMAT(monthly_last_generated_at, '%Y-%m') <> ?
            )",
        ];

        if (columnExists($pdo, 'clients', 'monthly_enabled')) {
            $where[] = "COALESCE(monthly_enabled,0) = 1";
        }

        $sql = "
            SELECT COALESCE(SUM(monthly_amount), 0) AS total_remaining
            FROM clients
            WHERE " . implode(' AND ', $where);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$month]);

        return (float)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('sl_dashboard_get_pending_debits_summary')) {
    function sl_dashboard_get_pending_debits_summary(PDO $pdo): array
    {
        $result = [
            'count' => 0,
            'total_amount' => 0.0,
        ];

        if (!tableExists($pdo, 'pending_client_debits')) {
            return $result;
        }

        $remainingColumn = null;

        if (columnExists($pdo, 'pending_client_debits', 'remaining_amount')) {
            $remainingColumn = 'remaining_amount';
        } elseif (columnExists($pdo, 'pending_client_debits', 'amount_due')) {
            $remainingColumn = 'amount_due';
        }

        if ($remainingColumn === null) {
            return $result;
        }

        $where = [
            "COALESCE({$remainingColumn}, 0) > 0"
        ];

        if (columnExists($pdo, 'pending_client_debits', 'status')) {
            $where[] = "LOWER(COALESCE(status, 'pending')) NOT IN ('settled', 'resolved', 'closed', 'cancelled')";
        }

        $sql = "
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM({$remainingColumn}), 0) AS total_amount
            FROM pending_client_debits
            WHERE " . implode(' AND ', $where);

        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        $result['count'] = (int)($row['total_count'] ?? 0);
        $result['total_amount'] = (float)($row['total_amount'] ?? 0);

        return $result;
    }
}

if (!function_exists('sl_dashboard_get_pending_debits_rows')) {
    function sl_dashboard_get_pending_debits_rows(PDO $pdo, int $limit = 10): array
    {
        if (!tableExists($pdo, 'pending_client_debits')) {
            return [];
        }

        $limit = max(1, min($limit, 100));

        $remainingColumn = null;
        if (columnExists($pdo, 'pending_client_debits', 'remaining_amount')) {
            $remainingColumn = 'remaining_amount';
        } elseif (columnExists($pdo, 'pending_client_debits', 'amount_due')) {
            $remainingColumn = 'amount_due';
        }

        if ($remainingColumn === null) {
            return [];
        }

        $joins = '';
        $selectClientCode = "NULL AS client_code";
        $selectFullName = "NULL AS full_name";

        if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
            $joins = "LEFT JOIN clients c ON c.id = pd.client_id";

            if (columnExists($pdo, 'clients', 'client_code')) {
                $selectClientCode = "c.client_code";
            }

            if (columnExists($pdo, 'clients', 'full_name')) {
                $selectFullName = "c.full_name";
            }
        }

        $labelSelect = columnExists($pdo, 'pending_client_debits', 'label')
            ? "pd.label"
            : "'Débit dû' AS label";

        $where = [
            "COALESCE(pd.{$remainingColumn}, 0) > 0"
        ];

        if (columnExists($pdo, 'pending_client_debits', 'status')) {
            $where[] = "LOWER(COALESCE(pd.status, 'pending')) NOT IN ('settled', 'resolved', 'closed', 'cancelled')";
        }

        $sql = "
            SELECT
                pd.id,
                " . (columnExists($pdo, 'pending_client_debits', 'client_id') ? "pd.client_id" : "NULL AS client_id") . ",
                {$selectClientCode},
                {$selectFullName},
                {$labelSelect},
                pd.{$remainingColumn} AS remaining_amount,
                " . (columnExists($pdo, 'pending_client_debits', 'status') ? "pd.status" : "'pending' AS status") . "
            FROM pending_client_debits pd
            {$joins}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY pd.{$remainingColumn} DESC, pd.id DESC
            LIMIT {$limit}
        ";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_dashboard_get_global_kpis')) {
    function sl_dashboard_get_global_kpis(PDO $pdo, array $filters = []): array
    {
        $periodStart = trim((string)($filters['period_start'] ?? ''));
        $periodEnd = trim((string)($filters['period_end'] ?? ''));
        $currentMonth = date('Y-m');

        $kpis = [
            'balances' => [
                'accounts_411' => 0.0,
                'accounts_512' => 0.0,
                'accounts_706' => 0.0,
                'global_total' => 0.0,
            ],
            'students' => [
                'active' => 0,
                'inactive' => 0,
            ],
            'monthly' => [
                'remaining_current_month' => 0.0,
                'done_current_month' => 0.0,
            ],
            'operations' => [
                'count' => 0,
                'amount' => 0.0,
            ],
            'low_balance_clients' => [
                'count' => 0,
                'threshold' => 1000.0,
            ],
            'pending_debits' => [
                'count' => 0,
                'amount' => 0.0,
                'rows' => [],
            ],
            'pending_debits_history_rows' => [],
            'types_rows' => [],
            'services_rows' => [],
            'commercial_countries_rows' => [],
            'accounting_indicators_rows' => [],
        ];

        if (tableExists($pdo, 'bank_accounts')) {
            $stmt = $pdo->query("
                SELECT
                    COALESCE(SUM(CASE WHEN account_number LIKE '411%' THEN COALESCE(balance,0) ELSE 0 END), 0) AS total_411
                FROM bank_accounts
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $kpis['balances']['accounts_411'] = (float)($row['total_411'] ?? 0);
        }

        if (tableExists($pdo, 'treasury_accounts')) {
            $balanceColumn = columnExists($pdo, 'treasury_accounts', 'current_balance')
                ? 'current_balance'
                : (columnExists($pdo, 'treasury_accounts', 'opening_balance') ? 'opening_balance' : null);

            if ($balanceColumn !== null) {
                $stmt = $pdo->query("
                    SELECT COALESCE(SUM(COALESCE({$balanceColumn},0)), 0) AS total_512
                    FROM treasury_accounts
                    WHERE COALESCE(is_active,1) = 1
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $kpis['balances']['accounts_512'] = (float)($row['total_512'] ?? 0);
            }
        }

        if (tableExists($pdo, 'service_accounts')) {
            $balanceColumn = columnExists($pdo, 'service_accounts', 'current_balance')
                ? 'current_balance'
                : null;

            if ($balanceColumn !== null) {
                $stmt = $pdo->query("
                    SELECT COALESCE(SUM(COALESCE({$balanceColumn},0)), 0) AS total_706
                    FROM service_accounts
                    WHERE COALESCE(is_active,1) = 1
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $kpis['balances']['accounts_706'] = (float)($row['total_706'] ?? 0);
            }
        }

        $kpis['balances']['global_total'] =
            (float)$kpis['balances']['accounts_411'] +
            (float)$kpis['balances']['accounts_512'] +
            (float)$kpis['balances']['accounts_706'];

        if (tableExists($pdo, 'clients')) {
            $typeStudentCondition = "LOWER(COALESCE(client_type,'')) IN ('etudiant', 'étudiant')";

            $stmt = $pdo->query("
                SELECT
                    COALESCE(SUM(CASE WHEN {$typeStudentCondition} AND COALESCE(is_active,1)=1 THEN 1 ELSE 0 END), 0) AS active_students,
                    COALESCE(SUM(CASE WHEN {$typeStudentCondition} AND COALESCE(is_active,1)=0 THEN 1 ELSE 0 END), 0) AS inactive_students
                FROM clients
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $kpis['students']['active'] = (int)($row['active_students'] ?? 0);
            $kpis['students']['inactive'] = (int)($row['inactive_students'] ?? 0);
        }

        $kpis['monthly']['done_current_month'] = function_exists('sl_dashboard_get_monthly_payments_done_amount')
            ? sl_dashboard_get_monthly_payments_done_amount($pdo, $currentMonth)
            : 0.0;

        $kpis['monthly']['remaining_current_month'] = function_exists('sl_dashboard_get_monthly_payments_remaining_amount')
            ? sl_dashboard_get_monthly_payments_remaining_amount($pdo, $currentMonth)
            : 0.0;

        if (tableExists($pdo, 'operations')) {
            $where = ["1=1"];
            $params = [];

            if ($periodStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart)) {
                $where[] = "operation_date >= ?";
                $params[] = $periodStart;
            }

            if ($periodEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
                $where[] = "operation_date <= ?";
                $params[] = $periodEnd;
            }

            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(COALESCE(amount,0)), 0) AS total_amount
                FROM operations
                WHERE " . implode(' AND ', $where)
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $kpis['operations']['count'] = (int)($row['total_count'] ?? 0);
            $kpis['operations']['amount'] = (float)($row['total_amount'] ?? 0);
        }

        if (tableExists($pdo, 'bank_accounts')) {
            $stmt = $pdo->query("
                SELECT COUNT(*) AS total_low
                FROM bank_accounts
                WHERE account_number LIKE '411%'
                  AND COALESCE(balance,0) < 1000
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $kpis['low_balance_clients']['count'] = (int)($row['total_low'] ?? 0);
        }

        if (function_exists('sl_dashboard_get_pending_debits_summary')) {
            $pendingSummary = sl_dashboard_get_pending_debits_summary($pdo);
            $kpis['pending_debits']['count'] = (int)($pendingSummary['count'] ?? 0);
            $kpis['pending_debits']['amount'] = (float)($pendingSummary['total_amount'] ?? 0);
        }

        if (function_exists('sl_dashboard_get_pending_debits_rows')) {
            $kpis['pending_debits']['rows'] = sl_dashboard_get_pending_debits_rows($pdo, 10);
        }

        if (function_exists('sl_dashboard_get_pending_debits_history_rows')) {
            $kpis['pending_debits_history_rows'] = sl_dashboard_get_pending_debits_history_rows($pdo, 25);
        }

        if (tableExists($pdo, 'operations') && columnExists($pdo, 'operations', 'operation_type_code')) {
            $where = ["1=1"];
            $params = [];

            if ($periodStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart)) {
                $where[] = "operation_date >= ?";
                $params[] = $periodStart;
            }

            if ($periodEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
                $where[] = "operation_date <= ?";
                $params[] = $periodEnd;
            }

            $stmt = $pdo->prepare("
                SELECT
                    operation_type_code,
                    COUNT(*) AS total_count,
                    COALESCE(SUM(amount),0) AS total_amount
                FROM operations
                WHERE " . implode(' AND ', $where) . "
                GROUP BY operation_type_code
                ORDER BY total_amount DESC, total_count DESC
            ");
            $stmt->execute($params);
            $kpis['types_rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (tableExists($pdo, 'operations') && columnExists($pdo, 'operations', 'service_id') && tableExists($pdo, 'ref_services')) {
            $where = ["1=1"];
            $params = [];

            if ($periodStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart)) {
                $where[] = "o.operation_date >= ?";
                $params[] = $periodStart;
            }

            if ($periodEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
                $where[] = "o.operation_date <= ?";
                $params[] = $periodEnd;
            }

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(rs.label, rs.code, CONCAT('Service #', o.service_id)) AS service_label,
                    COUNT(*) AS total_count,
                    COALESCE(SUM(o.amount),0) AS total_amount
                FROM operations o
                LEFT JOIN ref_services rs ON rs.id = o.service_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY o.service_id, rs.label, rs.code
                ORDER BY total_amount DESC, total_count DESC
            ");
            $stmt->execute($params);
            $kpis['services_rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (tableExists($pdo, 'clients') && columnExists($pdo, 'clients', 'country_commercial')) {
            $stmt = $pdo->query("
                SELECT
                    COALESCE(NULLIF(country_commercial, ''), 'Non renseigné') AS country_commercial,
                    COUNT(*) AS total_clients,
                    COALESCE(SUM(COALESCE(monthly_amount,0)),0) AS total_monthly_amount
                FROM clients
                GROUP BY COALESCE(NULLIF(country_commercial, ''), 'Non renseigné')
                ORDER BY total_clients DESC, total_monthly_amount DESC
            ");
            $kpis['commercial_countries_rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $kpis['accounting_indicators_rows'] = [
            [
                'label' => 'Total comptes 411',
                'amount' => (float)$kpis['balances']['accounts_411'],
            ],
            [
                'label' => 'Total comptes 512',
                'amount' => (float)$kpis['balances']['accounts_512'],
            ],
            [
                'label' => 'Total comptes 706',
                'amount' => (float)$kpis['balances']['accounts_706'],
            ],
            [
                'label' => 'Mensualités déjà effectuées',
                'amount' => (float)$kpis['monthly']['done_current_month'],
            ],
            [
                'label' => 'Mensualités restantes',
                'amount' => (float)$kpis['monthly']['remaining_current_month'],
            ],
            [
                'label' => 'Débits dus non réglés',
                'amount' => (float)$kpis['pending_debits']['amount'],
            ],
        ];

        return $kpis;
    }
}
if (!function_exists('sl_dashboard_get_extra_counters')) {
    function sl_dashboard_get_extra_counters(PDO $pdo): array
    {
        $data = [
            'monthly_active_count' => 0,
            'monthly_pending_count' => 0,
            'manual_operations_count' => 0,
        ];

        if (tableExists($pdo, 'clients')) {
            $monthlyWhere = [
                "COALESCE(is_active,1)=1",
            ];

            if (columnExists($pdo, 'clients', 'monthly_enabled')) {
                $monthlyWhere[] = "COALESCE(monthly_enabled,0)=1";
            }

            if (columnExists($pdo, 'clients', 'monthly_amount')) {
                $monthlyWhere[] = "COALESCE(monthly_amount,0) > 0";
            }

            $sqlMonthlyActive = "
                SELECT COUNT(*)
                FROM clients
                WHERE " . implode(' AND ', $monthlyWhere);

            $data['monthly_active_count'] = (int)$pdo->query($sqlMonthlyActive)->fetchColumn();

            if (columnExists($pdo, 'clients', 'monthly_last_generated_at')) {
                $sqlMonthlyPending = "
                    SELECT COUNT(*)
                    FROM clients
                    WHERE COALESCE(is_active,1)=1
                      AND COALESCE(monthly_enabled,0)=1
                      AND COALESCE(monthly_amount,0) > 0
                      AND (
                            monthly_last_generated_at IS NULL
                            OR DATE_FORMAT(monthly_last_generated_at, '%Y-%m') <> ?
                          )
                ";
                $stmtMonthlyPending = $pdo->prepare($sqlMonthlyPending);
                $stmtMonthlyPending->execute([date('Y-m')]);
                $data['monthly_pending_count'] = (int)$stmtMonthlyPending->fetchColumn();
            }
        }

        if (tableExists($pdo, 'operations') && columnExists($pdo, 'operations', 'is_manual_accounting')) {
            $data['manual_operations_count'] = (int)$pdo->query("
                SELECT COUNT(*)
                FROM operations
                WHERE COALESCE(is_manual_accounting,0)=1
            ")->fetchColumn();
        }

        return $data;
    }
}
if (!function_exists('sl_pending_debit_badge_class')) {
    function sl_pending_debit_badge_class(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'ready' => 'success',
            'partial' => 'warning',
            'pending' => 'info',
            'resolved', 'settled' => 'success',
            'cancelled', 'canceled' => 'danger',
            default => 'secondary',
        };
    }
}

if (!function_exists('sl_pending_debits_list_parse_filters')) {
    function sl_pending_debits_list_parse_filters(array $input = []): array
    {
        return [
            'q' => trim((string)($input['q'] ?? '')),
            'status' => trim((string)($input['status'] ?? '')),
            'client' => trim((string)($input['client'] ?? '')),
            'page' => max(1, (int)($input['page'] ?? 1)),
            'per_page' => max(1, min(100, (int)($input['per_page'] ?? 25))),
        ];
    }
}

if (!function_exists('sl_pending_debits_list_get_kpis')) {
    function sl_pending_debits_list_get_kpis(PDO $pdo, array $filters = []): array
    {
        $kpis = [
            'total_count' => 0,
            'pending_count' => 0,
            'ready_count' => 0,
            'partial_count' => 0,
            'resolved_count' => 0,
            'cancelled_count' => 0,
            'initial_amount_total' => 0.0,
            'executed_amount_total' => 0.0,
            'remaining_amount_total' => 0.0,
        ];

        if (!tableExists($pdo, 'pending_client_debits')) {
            return $kpis;
        }

        $remainingExpr = columnExists($pdo, 'pending_client_debits', 'remaining_amount')
            ? 'COALESCE(remaining_amount,0)'
            : (columnExists($pdo, 'pending_client_debits', 'amount_due') ? 'COALESCE(amount_due,0)' : '0');

        $initialExpr = columnExists($pdo, 'pending_client_debits', 'initial_amount')
            ? 'COALESCE(initial_amount,0)'
            : (columnExists($pdo, 'pending_client_debits', 'amount_due') ? 'COALESCE(amount_due,0)' : '0');

        $executedExpr = columnExists($pdo, 'pending_client_debits', 'executed_amount')
            ? 'COALESCE(executed_amount,0)'
            : '0';

        $stmt = $pdo->query("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,'')) = 'pending' THEN 1 ELSE 0 END),0) AS pending_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,'')) = 'ready' THEN 1 ELSE 0 END),0) AS ready_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,'')) = 'partial' THEN 1 ELSE 0 END),0) AS partial_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('resolved','settled') THEN 1 ELSE 0 END),0) AS resolved_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('cancelled','canceled') THEN 1 ELSE 0 END),0) AS cancelled_count,
                COALESCE(SUM({$initialExpr}),0) AS initial_amount_total,
                COALESCE(SUM({$executedExpr}),0) AS executed_amount_total,
                COALESCE(SUM({$remainingExpr}),0) AS remaining_amount_total
            FROM pending_client_debits
        ");

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis['total_count'] = (int)($row['total_count'] ?? 0);
        $kpis['pending_count'] = (int)($row['pending_count'] ?? 0);
        $kpis['ready_count'] = (int)($row['ready_count'] ?? 0);
        $kpis['partial_count'] = (int)($row['partial_count'] ?? 0);
        $kpis['resolved_count'] = (int)($row['resolved_count'] ?? 0);
        $kpis['cancelled_count'] = (int)($row['cancelled_count'] ?? 0);
        $kpis['initial_amount_total'] = (float)($row['initial_amount_total'] ?? 0);
        $kpis['executed_amount_total'] = (float)($row['executed_amount_total'] ?? 0);
        $kpis['remaining_amount_total'] = (float)($row['remaining_amount_total'] ?? 0);

        return $kpis;
    }
}

if (!function_exists('sl_pending_debits_list_get_clients')) {
    function sl_pending_debits_list_get_clients(PDO $pdo): array
    {
        if (!tableExists($pdo, 'clients')) {
            return [];
        }

        $stmt = $pdo->query("
            SELECT id, client_code, full_name
            FROM clients
            WHERE COALESCE(is_active, 1) = 1
            ORDER BY client_code ASC, full_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_pending_debits_list_get_rows')) {
    function sl_pending_debits_list_get_rows(PDO $pdo, array $filters = []): array
    {
        $f = sl_pending_debits_list_parse_filters($filters);

        if (!tableExists($pdo, 'pending_client_debits')) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => $f['page'],
                'per_page' => $f['per_page'],
                'pages' => 1,
            ];
        }

        $where = ['1=1'];
        $params = [];

        $joinClient = '';
        if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
            $joinClient = 'LEFT JOIN clients c ON c.id = pd.client_id';
        }

        if ($f['status'] !== '') {
            $where[] = 'pd.status = ?';
            $params[] = $f['status'];
        }

        if ($f['client'] !== '' && ctype_digit($f['client'])) {
            $where[] = 'pd.client_id = ?';
            $params[] = (int)$f['client'];
        }

        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';

            $searchParts = [
                "COALESCE(pd.label,'') LIKE ?",
                "COALESCE(pd.trigger_type,'') LIKE ?",
                "COALESCE(pd.notes,'') LIKE ?",
            ];
            $searchParams = [$like, $like, $like];

            if ($joinClient !== '') {
                $searchParts[] = "COALESCE(c.client_code,'') LIKE ?";
                $searchParts[] = "COALESCE(c.full_name,'') LIKE ?";
                $searchParts[] = "COALESCE(c.generated_client_account,'') LIKE ?";
                $searchParams[] = $like;
                $searchParams[] = $like;
                $searchParams[] = $like;
            }

            $where[] = '(' . implode(' OR ', $searchParts) . ')';
            $params = array_merge($params, $searchParams);
        }

        $countSql = "
            SELECT COUNT(*)
            FROM pending_client_debits pd
            {$joinClient}
            WHERE " . implode(' AND ', $where);

        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = ($f['page'] - 1) * $f['per_page'];
        $pages = max(1, (int)ceil($total / $f['per_page']));

        $selectParts = ['pd.*'];

        if ($joinClient !== '') {
            $selectParts[] = 'c.client_code';
            $selectParts[] = 'c.full_name';
            $selectParts[] = 'c.generated_client_account';
        } else {
            $selectParts[] = "NULL AS client_code";
            $selectParts[] = "NULL AS full_name";
            $selectParts[] = "NULL AS generated_client_account";
        }

        $sql = "
            SELECT " . implode(', ', $selectParts) . "
            FROM pending_client_debits pd
            {$joinClient}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE LOWER(COALESCE(pd.status,'')) 
                    WHEN 'ready' THEN 1
                    WHEN 'partial' THEN 2
                    WHEN 'pending' THEN 3
                    WHEN 'resolved' THEN 4
                    WHEN 'settled' THEN 4
                    WHEN 'cancelled' THEN 5
                    WHEN 'canceled' THEN 5
                    ELSE 6
                END,
                pd.id DESC
            LIMIT {$f['per_page']} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $f['page'],
            'per_page' => $f['per_page'],
            'pages' => $pages,
        ];
    }
}
if (!function_exists('sl_treasury_list_parse_filters')) {
    function sl_treasury_list_parse_filters(array $input = []): array
    {
        return [
            'search' => trim((string)($input['search'] ?? '')),
            'status' => trim((string)($input['status'] ?? '')),
            'type_view' => trim((string)($input['type_view'] ?? '')),
            'page' => max(1, (int)($input['page'] ?? 1)),
            'per_page' => max(1, min(100, (int)($input['per_page'] ?? 25))),
        ];
    }
}

if (!function_exists('sl_treasury_list_get_kpis')) {
    function sl_treasury_list_get_kpis(PDO $pdo, array $filters = []): array
    {
        $kpis = [
            'total_accounts' => 0,
            'active_accounts' => 0,
            'archived_accounts' => 0,
            'postable_accounts' => 0,
            'structure_accounts' => 0,
            'opening_balance_total' => 0.0,
            'current_balance_total' => 0.0,
        ];

        if (!tableExists($pdo, 'treasury_accounts')) {
            return $kpis;
        }

        $openingColumn = columnExists($pdo, 'treasury_accounts', 'opening_balance') ? 'opening_balance' : null;
        $currentColumn = columnExists($pdo, 'treasury_accounts', 'current_balance') ? 'current_balance' : null;
        $hasActive = columnExists($pdo, 'treasury_accounts', 'is_active');
        $hasPostable = columnExists($pdo, 'treasury_accounts', 'is_postable');

        $selects = [
            'COUNT(*) AS total_accounts',
            $hasActive
                ? "COALESCE(SUM(CASE WHEN COALESCE(is_active,1)=1 THEN 1 ELSE 0 END),0) AS active_accounts"
                : "COUNT(*) AS active_accounts",
            $hasActive
                ? "COALESCE(SUM(CASE WHEN COALESCE(is_active,1)=0 THEN 1 ELSE 0 END),0) AS archived_accounts"
                : "0 AS archived_accounts",
            $hasPostable
                ? "COALESCE(SUM(CASE WHEN COALESCE(is_postable,0)=1 THEN 1 ELSE 0 END),0) AS postable_accounts"
                : "0 AS postable_accounts",
            $hasPostable
                ? "COALESCE(SUM(CASE WHEN COALESCE(is_postable,0)=0 THEN 1 ELSE 0 END),0) AS structure_accounts"
                : "0 AS structure_accounts",
            $openingColumn !== null
                ? "COALESCE(SUM(COALESCE({$openingColumn},0)),0) AS opening_balance_total"
                : "0 AS opening_balance_total",
            $currentColumn !== null
                ? "COALESCE(SUM(COALESCE({$currentColumn},0)),0) AS current_balance_total"
                : "0 AS current_balance_total",
        ];

        $stmt = $pdo->query("
            SELECT " . implode(",\n", $selects) . "
            FROM treasury_accounts
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis['total_accounts'] = (int)($row['total_accounts'] ?? 0);
        $kpis['active_accounts'] = (int)($row['active_accounts'] ?? 0);
        $kpis['archived_accounts'] = (int)($row['archived_accounts'] ?? 0);
        $kpis['postable_accounts'] = (int)($row['postable_accounts'] ?? 0);
        $kpis['structure_accounts'] = (int)($row['structure_accounts'] ?? 0);
        $kpis['opening_balance_total'] = (float)($row['opening_balance_total'] ?? 0);
        $kpis['current_balance_total'] = (float)($row['current_balance_total'] ?? 0);

        return $kpis;
    }
}

if (!function_exists('sl_treasury_list_get_rows')) {
    function sl_treasury_list_get_rows(PDO $pdo, array $filters = []): array
    {
        $f = sl_treasury_list_parse_filters($filters);

        if (!tableExists($pdo, 'treasury_accounts')) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => $f['page'],
                'per_page' => $f['per_page'],
                'pages' => 1,
            ];
        }

        $where = ['1=1'];
        $params = [];

        if ($f['search'] !== '') {
            $where[] = "(
                COALESCE(account_code,'') LIKE ?
                OR COALESCE(account_label,'') LIKE ?
            )";
            $like = '%' . $f['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($f['status'] === 'active' && columnExists($pdo, 'treasury_accounts', 'is_active')) {
            $where[] = "COALESCE(is_active,1) = 1";
        } elseif ($f['status'] === 'archived' && columnExists($pdo, 'treasury_accounts', 'is_active')) {
            $where[] = "COALESCE(is_active,1) = 0";
        }

        if ($f['type_view'] === 'postable' && columnExists($pdo, 'treasury_accounts', 'is_postable')) {
            $where[] = "COALESCE(is_postable,0) = 1";
        } elseif ($f['type_view'] === 'structure' && columnExists($pdo, 'treasury_accounts', 'is_postable')) {
            $where[] = "COALESCE(is_postable,0) = 0";
        }

        $countSql = "
            SELECT COUNT(*)
            FROM treasury_accounts
            WHERE " . implode(' AND ', $where);

        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = ($f['page'] - 1) * $f['per_page'];
        $pages = max(1, (int)ceil($total / $f['per_page']));

        $orderParts = [];
        if (columnExists($pdo, 'treasury_accounts', 'is_active')) {
            $orderParts[] = 'COALESCE(is_active,1) DESC';
        }
        if (columnExists($pdo, 'treasury_accounts', 'account_code')) {
            $orderParts[] = 'account_code ASC';
        } else {
            $orderParts[] = 'id DESC';
        }

        $sql = "
            SELECT *
            FROM treasury_accounts
            WHERE " . implode(' AND ', $where) . "
            ORDER BY " . implode(', ', $orderParts) . "
            LIMIT {$f['per_page']} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $f['page'],
            'per_page' => $f['per_page'],
            'pages' => $pages,
        ];
    }
}
if (!function_exists('sl_imports_journal_parse_filters')) {
    function sl_imports_journal_parse_filters(array $input = []): array
    {
        return [
            'search' => trim((string)($input['search'] ?? '')),
            'module' => trim((string)($input['module'] ?? '')),
            'action' => trim((string)($input['action'] ?? '')),
            'from' => trim((string)($input['from'] ?? '')),
            'to' => trim((string)($input['to'] ?? '')),
            'page' => max(1, (int)($input['page'] ?? 1)),
            'per_page' => max(1, min(500, (int)($input['per_page'] ?? 50))),
        ];
    }
}

if (!function_exists('sl_imports_journal_get_kpis')) {
    function sl_imports_journal_get_kpis(PDO $pdo, array $filters = []): array
    {
        $kpis = [
            'total_logs' => 0,
            'imports_count' => 0,
            'distinct_modules' => 0,
            'distinct_actions' => 0,
            'today_logs' => 0,
        ];

        if (!tableExists($pdo, 'user_logs')) {
            return $kpis;
        }

        $hasModule = columnExists($pdo, 'user_logs', 'module');
        $hasAction = columnExists($pdo, 'user_logs', 'action');
        $hasCreatedAt = columnExists($pdo, 'user_logs', 'created_at');

        $selects = [
            'COUNT(*) AS total_logs',
            $hasModule ? "COUNT(DISTINCT COALESCE(module,'')) AS distinct_modules" : "0 AS distinct_modules",
            $hasAction ? "COUNT(DISTINCT COALESCE(action,'')) AS distinct_actions" : "0 AS distinct_actions",
            $hasCreatedAt ? "COALESCE(SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END),0) AS today_logs" : "0 AS today_logs",
            $hasModule ? "COALESCE(SUM(CASE WHEN module='imports' THEN 1 ELSE 0 END),0) AS imports_count" : "0 AS imports_count",
        ];

        $stmt = $pdo->query("
            SELECT " . implode(",\n", $selects) . "
            FROM user_logs
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis['total_logs'] = (int)($row['total_logs'] ?? 0);
        $kpis['imports_count'] = (int)($row['imports_count'] ?? 0);
        $kpis['distinct_modules'] = (int)($row['distinct_modules'] ?? 0);
        $kpis['distinct_actions'] = (int)($row['distinct_actions'] ?? 0);
        $kpis['today_logs'] = (int)($row['today_logs'] ?? 0);

        return $kpis;
    }
}

if (!function_exists('sl_imports_journal_get_rows')) {
    function sl_imports_journal_get_rows(PDO $pdo, array $filters = []): array
    {
        $f = sl_imports_journal_parse_filters($filters);

        if (!tableExists($pdo, 'user_logs')) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => $f['page'],
                'per_page' => $f['per_page'],
                'pages' => 1,
                'can_use_logs' => false,
            ];
        }

        $where = ['1=1'];
        $params = [];

        if ($f['search'] !== '') {
            $where[] = "(
                COALESCE(l.action,'') LIKE ?
                OR COALESCE(l.module,'') LIKE ?
                OR COALESCE(l.entity_type,'') LIKE ?
                OR COALESCE(l.details,'') LIKE ?
            )";
            $like = '%' . $f['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($f['module'] !== '' && columnExists($pdo, 'user_logs', 'module')) {
            $where[] = 'l.module = ?';
            $params[] = $f['module'];
        }

        if ($f['action'] !== '' && columnExists($pdo, 'user_logs', 'action')) {
            $where[] = 'l.action = ?';
            $params[] = $f['action'];
        }

        if ($f['from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from']) && columnExists($pdo, 'user_logs', 'created_at')) {
            $where[] = 'DATE(l.created_at) >= ?';
            $params[] = $f['from'];
        }

        if ($f['to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to']) && columnExists($pdo, 'user_logs', 'created_at')) {
            $where[] = 'DATE(l.created_at) <= ?';
            $params[] = $f['to'];
        }

        $countSql = "
            SELECT COUNT(*)
            FROM user_logs l
            WHERE " . implode(' AND ', $where);

        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = ($f['page'] - 1) * $f['per_page'];
        $pages = max(1, (int)ceil($total / $f['per_page']));

        $userJoin = '';
        $selectUser = 'NULL AS username';
        if (tableExists($pdo, 'users') && columnExists($pdo, 'user_logs', 'user_id')) {
            $userJoin = 'LEFT JOIN users u ON u.id = l.user_id';
            $selectUser = columnExists($pdo, 'users', 'username') ? 'u.username AS username' : 'NULL AS username';
        }

        $orderBy = columnExists($pdo, 'user_logs', 'created_at') ? 'l.created_at DESC' : 'l.id DESC';

        $sql = "
            SELECT
                l.*,
                {$selectUser}
            FROM user_logs l
            {$userJoin}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$f['per_page']} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $f['page'],
            'per_page' => $f['per_page'],
            'pages' => $pages,
            'can_use_logs' => true,
        ];
    }
}

if (!function_exists('sl_imports_journal_get_filter_options')) {
    function sl_imports_journal_get_filter_options(PDO $pdo): array
    {
        $result = [
            'modules' => [],
            'actions' => [],
        ];

        if (!tableExists($pdo, 'user_logs')) {
            return $result;
        }

        if (columnExists($pdo, 'user_logs', 'module')) {
            $result['modules'] = $pdo->query("
                SELECT DISTINCT module
                FROM user_logs
                WHERE COALESCE(module,'') <> ''
                ORDER BY module ASC
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        if (columnExists($pdo, 'user_logs', 'action')) {
            $result['actions'] = $pdo->query("
                SELECT DISTINCT action
                FROM user_logs
                WHERE COALESCE(action,'') <> ''
                ORDER BY action ASC
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        return $result;
    }
}
if (!function_exists('sl_admin_functional_dashboard_money')) {
    function sl_admin_functional_dashboard_money(float $value): string
    {
        return number_format($value, 2, ',', ' ') . ' €';
    }
}

if (!function_exists('sl_admin_functional_fetch_one')) {
    function sl_admin_functional_fetch_one(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_admin_functional_fetch_all')) {
    function sl_admin_functional_fetch_all(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_admin_functional_dashboard_get_stats')) {
    function sl_admin_functional_dashboard_get_stats(PDO $pdo): array
    {
        $stats = [
            'clients_total' => 0,
            'clients_active' => 0,
            'accounts_411_total' => 0,
            'accounts_411_balance_total' => 0.0,
            'accounts_512_total' => 0,
            'accounts_512_balance_total' => 0.0,
            'accounts_706_total' => 0,
            'accounts_706_balance_total' => 0.0,
            'operation_types_total' => 0,
            'services_total' => 0,
            'rules_total' => 0,
            'rules_active' => 0,
            'rules_missing' => 0,
        ];

        if (tableExists($pdo, 'clients')) {
            $row = sl_admin_functional_fetch_one($pdo, "
                SELECT
                    COUNT(*) AS clients_total,
                    COALESCE(SUM(CASE WHEN COALESCE(is_active,1)=1 THEN 1 ELSE 0 END), 0) AS clients_active
                FROM clients
            ");
            $stats['clients_total'] = (int)($row['clients_total'] ?? 0);
            $stats['clients_active'] = (int)($row['clients_active'] ?? 0);
        }

        if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts')) {
            $joinMode = null;

            if (columnExists($pdo, 'clients', 'generated_client_account') && columnExists($pdo, 'bank_accounts', 'account_number')) {
                $joinMode = 'account_number';
            } elseif (tableExists($pdo, 'client_bank_accounts') && columnExists($pdo, 'client_bank_accounts', 'client_id') && columnExists($pdo, 'client_bank_accounts', 'bank_account_id')) {
                $joinMode = 'pivot';
            }

            if ($joinMode === 'account_number') {
                $row = sl_admin_functional_fetch_one($pdo, "
                    SELECT
                        COUNT(*) AS accounts_411_total,
                        COALESCE(SUM(COALESCE(ba.balance, 0)), 0) AS accounts_411_balance_total
                    FROM clients c
                    LEFT JOIN bank_accounts ba
                        ON ba.account_number = c.generated_client_account
                    WHERE COALESCE(c.is_active,1)=1
                ");
                $stats['accounts_411_total'] = (int)($row['accounts_411_total'] ?? 0);
                $stats['accounts_411_balance_total'] = (float)($row['accounts_411_balance_total'] ?? 0);
            } elseif ($joinMode === 'pivot') {
                $row = sl_admin_functional_fetch_one($pdo, "
                    SELECT
                        COUNT(DISTINCT c.id) AS accounts_411_total,
                        COALESCE(SUM(COALESCE(ba.balance, 0)), 0) AS accounts_411_balance_total
                    FROM clients c
                    LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
                    LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
                    WHERE COALESCE(c.is_active,1)=1
                      AND COALESCE(ba.account_number, '') LIKE '411%'
                ");
                $stats['accounts_411_total'] = (int)($row['accounts_411_total'] ?? 0);
                $stats['accounts_411_balance_total'] = (float)($row['accounts_411_balance_total'] ?? 0);
            }
        }

        if (tableExists($pdo, 'treasury_accounts')) {
            $balanceColumn = columnExists($pdo, 'treasury_accounts', 'current_balance')
                ? 'current_balance'
                : (columnExists($pdo, 'treasury_accounts', 'opening_balance') ? 'opening_balance' : null);

            if ($balanceColumn !== null) {
                $row = sl_admin_functional_fetch_one($pdo, "
                    SELECT
                        COUNT(*) AS accounts_512_total,
                        COALESCE(SUM(COALESCE({$balanceColumn},0)), 0) AS accounts_512_balance_total
                    FROM treasury_accounts
                    WHERE COALESCE(is_active,1)=1
                ");
                $stats['accounts_512_total'] = (int)($row['accounts_512_total'] ?? 0);
                $stats['accounts_512_balance_total'] = (float)($row['accounts_512_balance_total'] ?? 0);
            }
        }

        if (tableExists($pdo, 'service_accounts')) {
            $balanceColumn = columnExists($pdo, 'service_accounts', 'current_balance') ? 'current_balance' : null;

            if ($balanceColumn !== null) {
                $where = ["COALESCE(is_active,1)=1"];
                if (columnExists($pdo, 'service_accounts', 'is_postable')) {
                    $where[] = "COALESCE(is_postable,0)=1";
                }

                $row = sl_admin_functional_fetch_one($pdo, "
                    SELECT
                        COUNT(*) AS accounts_706_total,
                        COALESCE(SUM(COALESCE({$balanceColumn},0)), 0) AS accounts_706_balance_total
                    FROM service_accounts
                    WHERE " . implode(' AND ', $where)
                );
                $stats['accounts_706_total'] = (int)($row['accounts_706_total'] ?? 0);
                $stats['accounts_706_balance_total'] = (float)($row['accounts_706_balance_total'] ?? 0);
            }
        }

        if (tableExists($pdo, 'ref_operation_types')) {
            $row = sl_admin_functional_fetch_one($pdo, "SELECT COUNT(*) AS total FROM ref_operation_types");
            $stats['operation_types_total'] = (int)($row['total'] ?? 0);
        } elseif (tableExists($pdo, 'operation_types')) {
            $row = sl_admin_functional_fetch_one($pdo, "SELECT COUNT(*) AS total FROM operation_types");
            $stats['operation_types_total'] = (int)($row['total'] ?? 0);
        }

        if (tableExists($pdo, 'ref_services')) {
            $row = sl_admin_functional_fetch_one($pdo, "SELECT COUNT(*) AS total FROM ref_services");
            $stats['services_total'] = (int)($row['total'] ?? 0);
        } elseif (tableExists($pdo, 'services')) {
            $row = sl_admin_functional_fetch_one($pdo, "SELECT COUNT(*) AS total FROM services");
            $stats['services_total'] = (int)($row['total'] ?? 0);
        }

        if (tableExists($pdo, 'accounting_rules')) {
            $row = sl_admin_functional_fetch_one($pdo, "
                SELECT
                    COUNT(*) AS rules_total,
                    COALESCE(SUM(CASE WHEN COALESCE(is_active,1)=1 THEN 1 ELSE 0 END), 0) AS rules_active
                FROM accounting_rules
            ");
            $stats['rules_total'] = (int)($row['rules_total'] ?? 0);
            $stats['rules_active'] = (int)($row['rules_active'] ?? 0);
        }

        $stats['rules_missing'] = count(sl_admin_functional_dashboard_get_services_without_rule($pdo, 999999));

        return $stats;
    }
}

if (!function_exists('sl_admin_functional_dashboard_get_services_without_rule')) {
    function sl_admin_functional_dashboard_get_services_without_rule(PDO $pdo, int $limit = 8): array
    {
        if (!tableExists($pdo, 'ref_services') || !tableExists($pdo, 'accounting_rules')) {
            return [];
        }

        $limit = max(1, min($limit, 1000));

        return sl_admin_functional_fetch_all($pdo, "
            SELECT
                rs.id,
                rs.code,
                rs.label,
                rs.operation_type_id,
                rot.code AS operation_type_code,
                rot.label AS operation_type_label
            FROM ref_services rs
            LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
            LEFT JOIN accounting_rules ar
                ON ar.service_id = rs.id
               AND ar.operation_type_id = rs.operation_type_id
               AND COALESCE(ar.is_active,1)=1
            WHERE ar.id IS NULL
            ORDER BY rot.label ASC, rs.label ASC
            LIMIT {$limit}
        ");
    }
}

if (!function_exists('sl_admin_functional_dashboard_get_top_411')) {
    function sl_admin_functional_dashboard_get_top_411(PDO $pdo, int $limit = 8): array
    {
        if (!tableExists($pdo, 'clients') || !tableExists($pdo, 'bank_accounts')) {
            return [];
        }

        $limit = max(1, min($limit, 100));

        if (columnExists($pdo, 'clients', 'generated_client_account') && columnExists($pdo, 'bank_accounts', 'account_number')) {
            return sl_admin_functional_fetch_all($pdo, "
                SELECT
                    c.id,
                    c.client_code,
                    c.full_name,
                    c.generated_client_account,
                    COALESCE(ba.balance, 0) AS balance,
                    COALESCE(ba.initial_balance, 0) AS initial_balance,
                    c.country_commercial
                FROM clients c
                LEFT JOIN bank_accounts ba
                    ON ba.account_number = c.generated_client_account
                WHERE COALESCE(c.is_active,1)=1
                ORDER BY COALESCE(ba.balance, 0) DESC, c.full_name ASC
                LIMIT {$limit}
            ");
        }

        if (tableExists($pdo, 'client_bank_accounts')) {
            return sl_admin_functional_fetch_all($pdo, "
                SELECT
                    c.id,
                    c.client_code,
                    c.full_name,
                    ba.account_number AS generated_client_account,
                    COALESCE(ba.balance, 0) AS balance,
                    COALESCE(ba.initial_balance, 0) AS initial_balance,
                    c.country_commercial
                FROM clients c
                INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
                INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
                WHERE COALESCE(c.is_active,1)=1
                  AND COALESCE(ba.account_number, '') LIKE '411%'
                ORDER BY COALESCE(ba.balance, 0) DESC, c.full_name ASC
                LIMIT {$limit}
            ");
        }

        return [];
    }
}

if (!function_exists('sl_admin_functional_dashboard_get_top_706')) {
    function sl_admin_functional_dashboard_get_top_706(PDO $pdo, int $limit = 8): array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return [];
        }

        $limit = max(1, min($limit, 100));

        $where = ["COALESCE(is_active,1)=1"];
        if (columnExists($pdo, 'service_accounts', 'is_postable')) {
            $where[] = "COALESCE(is_postable,0)=1";
        }

        return sl_admin_functional_fetch_all($pdo, "
            SELECT
                id,
                account_code,
                account_label,
                current_balance,
                commercial_country_label,
                destination_country_label
            FROM service_accounts
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(current_balance,0) DESC, account_code ASC
            LIMIT {$limit}
        ");
    }
}

if (!function_exists('sl_admin_functional_dashboard_get_recent_rules')) {
    function sl_admin_functional_dashboard_get_recent_rules(PDO $pdo, int $limit = 8): array
    {
        if (!tableExists($pdo, 'accounting_rules')) {
            return [];
        }

        $limit = max(1, min($limit, 100));

        return sl_admin_functional_fetch_all($pdo, "
            SELECT
                ar.id,
                ar.rule_code,
                ar.debit_mode,
                ar.credit_mode,
                ar.is_active,
                rot.label AS operation_type_label,
                rs.label AS service_label
            FROM accounting_rules ar
            LEFT JOIN ref_operation_types rot ON rot.id = ar.operation_type_id
            LEFT JOIN ref_services rs ON rs.id = ar.service_id
            ORDER BY COALESCE(ar.updated_at, ar.created_at) DESC, ar.id DESC
            LIMIT {$limit}
        ");
    }
}

if (!function_exists('sl_admin_functional_dashboard_get_data')) {
    function sl_admin_functional_dashboard_get_data(PDO $pdo): array
    {
        $data = [
            'stats' => [
                'clients_total' => 0,
                'clients_active' => 0,
                'accounts_411_total' => 0,
                'accounts_411_balance_total' => 0.0,
                'accounts_512_total' => 0,
                'accounts_512_balance_total' => 0.0,
                'accounts_706_total' => 0,
                'accounts_706_balance_total' => 0.0,
                'operation_types_total' => 0,
                'services_total' => 0,
                'rules_total' => 0,
                'rules_active' => 0,
                'rules_missing' => 0,
            ],
            'top411' => [],
            'top512' => [],
            'top706' => [],
            'recentRules' => [],
            'servicesWithoutRule' => [],
        ];

        if (!function_exists('af_dashboard_fetch_one')) {
            function af_dashboard_fetch_one(PDO $pdo, string $sql, array $params = []): array
            {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }
        }

        if (!function_exists('af_dashboard_fetch_all')) {
            function af_dashboard_fetch_all(PDO $pdo, string $sql, array $params = []): array
            {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        if (tableExists($pdo, 'clients')) {
            $row = af_dashboard_fetch_one($pdo, "
                SELECT
                    COUNT(*) AS clients_total,
                    SUM(CASE WHEN COALESCE(is_active,1)=1 THEN 1 ELSE 0 END) AS clients_active
                FROM clients
            ");
            $data['stats']['clients_total'] = (int)($row['clients_total'] ?? 0);
            $data['stats']['clients_active'] = (int)($row['clients_active'] ?? 0);
        }

        if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts')) {
            $row = af_dashboard_fetch_one($pdo, "
                SELECT
                    COUNT(*) AS accounts_411_total,
                    COALESCE(SUM(COALESCE(ba.balance, 0)), 0) AS accounts_411_balance_total
                FROM clients c
                LEFT JOIN bank_accounts ba
                    ON ba.account_number = c.generated_client_account
                WHERE COALESCE(c.is_active,1)=1
                  AND COALESCE(c.generated_client_account,'') LIKE '411%'
            ");
            $data['stats']['accounts_411_total'] = (int)($row['accounts_411_total'] ?? 0);
            $data['stats']['accounts_411_balance_total'] = (float)($row['accounts_411_balance_total'] ?? 0);
        }

        if (tableExists($pdo, 'treasury_accounts')) {
            $balanceColumn512 = columnExists($pdo, 'treasury_accounts', 'current_balance')
                ? 'current_balance'
                : (columnExists($pdo, 'treasury_accounts', 'opening_balance') ? 'opening_balance' : '0');

            $row = af_dashboard_fetch_one($pdo, "
                SELECT
                    COUNT(*) AS accounts_512_total,
                    COALESCE(SUM(COALESCE({$balanceColumn512},0)), 0) AS accounts_512_balance_total
                FROM treasury_accounts
                WHERE COALESCE(is_active,1)=1
            ");
            $data['stats']['accounts_512_total'] = (int)($row['accounts_512_total'] ?? 0);
            $data['stats']['accounts_512_balance_total'] = (float)($row['accounts_512_balance_total'] ?? 0);
        }

        if (tableExists($pdo, 'service_accounts')) {
            $balanceColumn706 = columnExists($pdo, 'service_accounts', 'current_balance')
                ? 'current_balance'
                : '0';

            $row = af_dashboard_fetch_one($pdo, "
                SELECT
                    COUNT(*) AS accounts_706_total,
                    COALESCE(SUM(COALESCE({$balanceColumn706},0)), 0) AS accounts_706_balance_total
                FROM service_accounts
                WHERE COALESCE(is_active,1)=1
                  AND (
                        COALESCE(is_postable,0)=1
                        OR account_code LIKE '706%'
                      )
            ");
            $data['stats']['accounts_706_total'] = (int)($row['accounts_706_total'] ?? 0);
            $data['stats']['accounts_706_balance_total'] = (float)($row['accounts_706_balance_total'] ?? 0);
        }

        if (tableExists($pdo, 'ref_operation_types')) {
            $row = af_dashboard_fetch_one($pdo, "
                SELECT COUNT(*) AS total
                FROM ref_operation_types
            ");
            $data['stats']['operation_types_total'] = (int)($row['total'] ?? 0);
        } elseif (tableExists($pdo, 'operation_types')) {
            $row = af_dashboard_fetch_one($pdo, "
                SELECT COUNT(*) AS total
                FROM operation_types
            ");
            $data['stats']['operation_types_total'] = (int)($row['total'] ?? 0);
        }

        if (tableExists($pdo, 'ref_services')) {
            $row = af_dashboard_fetch_one($pdo, "
                SELECT COUNT(*) AS total
                FROM ref_services
            ");
            $data['stats']['services_total'] = (int)($row['total'] ?? 0);
        } elseif (tableExists($pdo, 'services')) {
            $row = af_dashboard_fetch_one($pdo, "
                SELECT COUNT(*) AS total
                FROM services
            ");
            $data['stats']['services_total'] = (int)($row['total'] ?? 0);
        }

        if (tableExists($pdo, 'accounting_rules')) {
            $row = af_dashboard_fetch_one($pdo, "
                SELECT
                    COUNT(*) AS rules_total,
                    SUM(CASE WHEN COALESCE(is_active,1)=1 THEN 1 ELSE 0 END) AS rules_active
                FROM accounting_rules
            ");
            $data['stats']['rules_total'] = (int)($row['rules_total'] ?? 0);
            $data['stats']['rules_active'] = (int)($row['rules_active'] ?? 0);
        }

        if (
            tableExists($pdo, 'ref_services')
            && tableExists($pdo, 'accounting_rules')
            && tableExists($pdo, 'ref_operation_types')
        ) {
            $data['servicesWithoutRule'] = af_dashboard_fetch_all($pdo, "
                SELECT
                    rs.id,
                    rs.operation_type_id,
                    rs.code,
                    rs.label,
                    rot.code AS operation_type_code,
                    rot.label AS operation_type_label
                FROM ref_services rs
                LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
                LEFT JOIN accounting_rules ar
                    ON ar.service_id = rs.id
                   AND ar.operation_type_id = rs.operation_type_id
                   AND COALESCE(ar.is_active,1)=1
                WHERE ar.id IS NULL
                ORDER BY rot.label ASC, rs.label ASC
                LIMIT 8
            ");
            $data['stats']['rules_missing'] = count($data['servicesWithoutRule']);
        }

        /**
         * TOP 411 ROBUSTE
         * 1. priorite au lien client_bank_accounts
         * 2. fallback sur generated_client_account = account_number
         */
        if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts')) {
            if (tableExists($pdo, 'client_bank_accounts')) {
                $rows = af_dashboard_fetch_all($pdo, "
                    SELECT
                        c.id,
                        c.client_code,
                        c.full_name,
                        c.generated_client_account,
                        c.country_commercial,
                        COALESCE(ba.balance, 0) AS balance,
                        COALESCE(ba.initial_balance, 0) AS initial_balance
                    FROM clients c
                    LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
                    LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
                    WHERE COALESCE(c.is_active,1)=1
                      AND (
                            COALESCE(ba.account_number,'') LIKE '411%'
                            OR COALESCE(c.generated_client_account,'') LIKE '411%'
                          )
                    GROUP BY
                        c.id,
                        c.client_code,
                        c.full_name,
                        c.generated_client_account,
                        c.country_commercial,
                        ba.balance,
                        ba.initial_balance
                    ORDER BY COALESCE(ba.balance, 0) DESC, c.full_name ASC
                    LIMIT 8
                ");
                $data['top411'] = $rows;
            }

            if (!$data['top411']) {
                $data['top411'] = af_dashboard_fetch_all($pdo, "
                    SELECT
                        c.id,
                        c.client_code,
                        c.full_name,
                        c.generated_client_account,
                        c.country_commercial,
                        COALESCE(ba.balance, 0) AS balance,
                        COALESCE(ba.initial_balance, 0) AS initial_balance
                    FROM clients c
                    LEFT JOIN bank_accounts ba
                        ON ba.account_number = c.generated_client_account
                    WHERE COALESCE(c.is_active,1)=1
                      AND COALESCE(c.generated_client_account,'') LIKE '411%'
                    ORDER BY COALESCE(ba.balance, 0) DESC, c.full_name ASC
                    LIMIT 8
                ");
            }
        }

        /**
         * TOP 512
         */
        if (tableExists($pdo, 'treasury_accounts')) {
            $balanceColumn512 = columnExists($pdo, 'treasury_accounts', 'current_balance')
                ? 'current_balance'
                : (columnExists($pdo, 'treasury_accounts', 'opening_balance') ? 'opening_balance' : '0');

            $data['top512'] = af_dashboard_fetch_all($pdo, "
                SELECT
                    id,
                    account_code,
                    account_label,
                    COALESCE({$balanceColumn512}, 0) AS current_balance,
                    " . (columnExists($pdo, 'treasury_accounts', 'currency_code') ? "COALESCE(currency_code, 'EUR')" : "'EUR'") . " AS currency_code,
                    " . (columnExists($pdo, 'treasury_accounts', 'country_label') ? "COALESCE(country_label, '')" : "''") . " AS country_label
                FROM treasury_accounts
                WHERE COALESCE(is_active,1)=1
                ORDER BY COALESCE({$balanceColumn512}, 0) DESC, account_code ASC
                LIMIT 8
            ");
        }

        /**
         * TOP 706
         */
        if (tableExists($pdo, 'service_accounts')) {
            $balanceColumn706 = columnExists($pdo, 'service_accounts', 'current_balance')
                ? 'current_balance'
                : '0';

            $data['top706'] = af_dashboard_fetch_all($pdo, "
                SELECT
                    id,
                    account_code,
                    account_label,
                    COALESCE({$balanceColumn706}, 0) AS current_balance,
                    " . (columnExists($pdo, 'service_accounts', 'commercial_country_label') ? "COALESCE(commercial_country_label, '')" : "''") . " AS commercial_country_label,
                    " . (columnExists($pdo, 'service_accounts', 'destination_country_label') ? "COALESCE(destination_country_label, '')" : "''") . " AS destination_country_label
                FROM service_accounts
                WHERE COALESCE(is_active,1)=1
                  AND (
                        COALESCE(is_postable,0)=1
                        OR COALESCE(account_code,'') LIKE '706%'
                      )
                ORDER BY COALESCE({$balanceColumn706}, 0) DESC, account_code ASC
                LIMIT 8
            ");
        }

        if (
            tableExists($pdo, 'accounting_rules')
            && tableExists($pdo, 'ref_operation_types')
            && tableExists($pdo, 'ref_services')
        ) {
            $recentOrder = columnExists($pdo, 'accounting_rules', 'updated_at')
                ? 'COALESCE(ar.updated_at, ar.created_at) DESC, ar.id DESC'
                : 'ar.id DESC';

            $data['recentRules'] = af_dashboard_fetch_all($pdo, "
                SELECT
                    ar.id,
                    ar.rule_code,
                    ar.debit_mode,
                    ar.credit_mode,
                    ar.is_active,
                    rot.label AS operation_type_label,
                    rs.label AS service_label
                FROM accounting_rules ar
                LEFT JOIN ref_operation_types rot ON rot.id = ar.operation_type_id
                LEFT JOIN ref_services rs ON rs.id = ar.service_id
                ORDER BY {$recentOrder}
                LIMIT 8
            ");
        }

        return $data;
    }
}
if (!function_exists('sl_notifications_parse_filters')) {
    function sl_notifications_parse_filters(array $input = []): array
    {
        return [
            'q' => trim((string)($input['q'] ?? '')),
            'type' => trim((string)($input['type'] ?? '')),
            'level' => trim((string)($input['level'] ?? '')),
            'entity_type' => trim((string)($input['entity_type'] ?? '')),
            'client_id' => trim((string)($input['client_id'] ?? '')),
            'operation_type_code' => trim((string)($input['operation_type_code'] ?? '')),
            'service_id' => trim((string)($input['service_id'] ?? '')),
            'status' => trim((string)($input['status'] ?? '')),
            'date_from' => trim((string)($input['date_from'] ?? '')),
            'date_to' => trim((string)($input['date_to'] ?? '')),
            'page' => max(1, (int)($input['page'] ?? 1)),
            'per_page' => max(10, min(200, (int)($input['per_page'] ?? 25))),
        ];
    }
}

if (!function_exists('sl_notifications_get_filter_options')) {
    function sl_notifications_get_filter_options(PDO $pdo): array
    {
        $options = [
            'types' => [],
            'levels' => [],
            'entity_types' => [],
            'clients' => [],
            'operation_types' => [],
            'services' => [],
            'statuses' => [
                ['value' => 'unread', 'label' => 'Non lues'],
                ['value' => 'read', 'label' => 'Lues'],
            ],
        ];

        if (tableExists($pdo, 'notifications')) {
            if (columnExists($pdo, 'notifications', 'type')) {
                $options['types'] = $pdo->query("
                    SELECT DISTINCT type
                    FROM notifications
                    WHERE COALESCE(type, '') <> ''
                    ORDER BY type ASC
                ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }

            if (columnExists($pdo, 'notifications', 'level')) {
                $options['levels'] = $pdo->query("
                    SELECT DISTINCT level
                    FROM notifications
                    WHERE COALESCE(level, '') <> ''
                    ORDER BY level ASC
                ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }

            if (columnExists($pdo, 'notifications', 'entity_type')) {
                $options['entity_types'] = $pdo->query("
                    SELECT DISTINCT entity_type
                    FROM notifications
                    WHERE COALESCE(entity_type, '') <> ''
                    ORDER BY entity_type ASC
                ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }
        }

        if (tableExists($pdo, 'clients')) {
            $options['clients'] = $pdo->query("
                SELECT id, client_code, full_name
                FROM clients
                ORDER BY client_code ASC, full_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (tableExists($pdo, 'operations') && columnExists($pdo, 'operations', 'operation_type_code')) {
            $options['operation_types'] = $pdo->query("
                SELECT DISTINCT operation_type_code
                FROM operations
                WHERE COALESCE(operation_type_code, '') <> ''
                ORDER BY operation_type_code ASC
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        if (tableExists($pdo, 'ref_services')) {
            $options['services'] = $pdo->query("
                SELECT id, code, label
                FROM ref_services
                ORDER BY label ASC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return $options;
    }
}

if (!function_exists('sl_notifications_get_kpis')) {
    function sl_notifications_get_kpis(PDO $pdo): array
    {
        $kpis = [
            'total' => 0,
            'unread' => 0,
            'warning' => 0,
            'danger' => 0,
            'success' => 0,
        ];

        if (!tableExists($pdo, 'notifications')) {
            return $kpis;
        }

        $stmt = $pdo->query("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN COALESCE(is_read,0)=0 THEN 1 ELSE 0 END),0) AS unread_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(level,''))='warning' THEN 1 ELSE 0 END),0) AS warning_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(level,''))='danger' THEN 1 ELSE 0 END),0) AS danger_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(level,''))='success' THEN 1 ELSE 0 END),0) AS success_count
            FROM notifications
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis['total'] = (int)($row['total_count'] ?? 0);
        $kpis['unread'] = (int)($row['unread_count'] ?? 0);
        $kpis['warning'] = (int)($row['warning_count'] ?? 0);
        $kpis['danger'] = (int)($row['danger_count'] ?? 0);
        $kpis['success'] = (int)($row['success_count'] ?? 0);

        return $kpis;
    }
}

if (!function_exists('sl_notifications_get_rows')) {
    function sl_notifications_get_rows(PDO $pdo, array $filters = []): array
    {
        $f = sl_notifications_parse_filters($filters);

        $result = [
            'rows' => [],
            'total' => 0,
            'page' => $f['page'],
            'per_page' => $f['per_page'],
            'pages' => 1,
        ];

        if (!tableExists($pdo, 'notifications')) {
            return $result;
        }

        $joins = [];
        $selects = ['n.*'];
        $where = ['1=1'];
        $params = [];

        if (tableExists($pdo, 'clients')) {
            $joins[] = "LEFT JOIN clients c ON n.entity_type = 'client' AND n.entity_id = c.id";
            $selects[] = "c.client_code";
            $selects[] = "c.full_name";
            $selects[] = "c.generated_client_account";
        } else {
            $selects[] = "NULL AS client_code";
            $selects[] = "NULL AS full_name";
            $selects[] = "NULL AS generated_client_account";
        }

        if (tableExists($pdo, 'operations')) {
            $joins[] = "LEFT JOIN operations o ON n.entity_type = 'operation' AND n.entity_id = o.id";
            $selects[] = columnExists($pdo, 'operations', 'operation_type_code') ? "o.operation_type_code" : "NULL AS operation_type_code";
            $selects[] = columnExists($pdo, 'operations', 'service_id') ? "o.service_id AS operation_service_id" : "NULL AS operation_service_id";
        } else {
            $selects[] = "NULL AS operation_type_code";
            $selects[] = "NULL AS operation_service_id";
        }

        if (tableExists($pdo, 'ref_services') && tableExists($pdo, 'operations')) {
            $joins[] = "LEFT JOIN ref_services rs ON rs.id = o.service_id";
            $selects[] = "rs.label AS service_label";
        } else {
            $selects[] = "NULL AS service_label";
        }

        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $where[] = "(
                COALESCE(n.message,'') LIKE ?
                OR COALESCE(n.type,'') LIKE ?
                OR COALESCE(n.level,'') LIKE ?
                OR COALESCE(n.entity_type,'') LIKE ?
                OR COALESCE(c.client_code,'') LIKE ?
                OR COALESCE(c.full_name,'') LIKE ?
                OR COALESCE(o.operation_type_code,'') LIKE ?
                OR COALESCE(rs.label,'') LIKE ?
            )";
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        if ($f['type'] !== '') {
            $where[] = "n.type = ?";
            $params[] = $f['type'];
        }

        if ($f['level'] !== '') {
            $where[] = "LOWER(COALESCE(n.level,'')) = LOWER(?)";
            $params[] = $f['level'];
        }

        if ($f['entity_type'] !== '') {
            $where[] = "n.entity_type = ?";
            $params[] = $f['entity_type'];
        }

        if ($f['client_id'] !== '' && ctype_digit($f['client_id'])) {
            $where[] = "c.id = ?";
            $params[] = (int)$f['client_id'];
        }

        if ($f['operation_type_code'] !== '') {
            $where[] = "o.operation_type_code = ?";
            $params[] = $f['operation_type_code'];
        }

        if ($f['service_id'] !== '' && ctype_digit($f['service_id'])) {
            $where[] = "rs.id = ?";
            $params[] = (int)$f['service_id'];
        }

        if ($f['status'] === 'read' && columnExists($pdo, 'notifications', 'is_read')) {
            $where[] = "COALESCE(n.is_read,0) = 1";
        } elseif ($f['status'] === 'unread' && columnExists($pdo, 'notifications', 'is_read')) {
            $where[] = "COALESCE(n.is_read,0) = 0";
        }

        if ($f['date_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_from']) && columnExists($pdo, 'notifications', 'created_at')) {
            $where[] = "DATE(n.created_at) >= ?";
            $params[] = $f['date_from'];
        }

        if ($f['date_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_to']) && columnExists($pdo, 'notifications', 'created_at')) {
            $where[] = "DATE(n.created_at) <= ?";
            $params[] = $f['date_to'];
        }

        $countSql = "
            SELECT COUNT(*)
            FROM notifications n
            " . implode("\n", $joins) . "
            WHERE " . implode(' AND ', $where);

        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $pages = max(1, (int)ceil($total / $f['per_page']));
        $page = min($f['page'], $pages);
        $offset = ($page - 1) * $f['per_page'];

        $sql = "
            SELECT " . implode(",\n", $selects) . "
            FROM notifications n
            " . implode("\n", $joins) . "
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(n.created_at, NOW()) DESC, n.id DESC
            LIMIT {$f['per_page']} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $result['rows'] = $rows;
        $result['total'] = $total;
        $result['page'] = $page;
        $result['per_page'] = $f['per_page'];
        $result['pages'] = $pages;

        return $result;
    }
}
if (!function_exists('sl_dashboard_get_pending_debits_history_rows')) {
    function sl_dashboard_get_pending_debits_history_rows(PDO $pdo, int $limit = 25): array
    {
        $limit = max(1, min($limit, 200));

        if (!tableExists($pdo, 'pending_client_debits')) {
            return [];
        }

        if (!columnExists($pdo, 'pending_client_debits', 'client_id')) {
            return [];
        }

        $amountColumn = null;
        if (columnExists($pdo, 'pending_client_debits', 'initial_amount')) {
            $amountColumn = 'initial_amount';
        } elseif (columnExists($pdo, 'pending_client_debits', 'amount_due')) {
            $amountColumn = 'amount_due';
        } elseif (columnExists($pdo, 'pending_client_debits', 'remaining_amount')) {
            $amountColumn = 'remaining_amount';
        }

        if ($amountColumn === null) {
            return [];
        }

        $createdAtExpr = columnExists($pdo, 'pending_client_debits', 'created_at') ? 'pd.created_at' : 'NULL';
        $resolvedAtExpr = 'NULL';

        if (columnExists($pdo, 'pending_client_debits', 'resolved_at')) {
            $resolvedAtExpr = 'pd.resolved_at';
        } elseif (columnExists($pdo, 'pending_client_debits', 'settled_at')) {
            $resolvedAtExpr = 'pd.settled_at';
        } elseif (columnExists($pdo, 'pending_client_debits', 'updated_at') && columnExists($pdo, 'pending_client_debits', 'status')) {
            $resolvedAtExpr = "CASE
                WHEN LOWER(COALESCE(pd.status,'')) IN ('settled', 'resolved', 'closed')
                THEN pd.updated_at
                ELSE NULL
            END";
        }

        $sql = "
            SELECT
                pd.client_id,
                c.client_code,
                c.full_name,
                c.generated_client_account,
                COUNT(*) AS total_pending_debits,
                COALESCE(SUM(COALESCE(pd.{$amountColumn},0)),0) AS total_pending_amount,
                COALESCE(SUM(CASE
                    WHEN LOWER(COALESCE(pd.status,'')) IN ('settled', 'resolved', 'closed')
                    THEN 1 ELSE 0
                END),0) AS resolved_count,
                COALESCE(AVG(CASE
                    WHEN {$createdAtExpr} IS NOT NULL AND {$resolvedAtExpr} IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, {$createdAtExpr}, {$resolvedAtExpr})
                    ELSE NULL
                END),0) AS avg_resolution_hours,
                MAX(pd.created_at) AS last_pending_created_at
            FROM pending_client_debits pd
            LEFT JOIN clients c ON c.id = pd.client_id
            WHERE pd.client_id IS NOT NULL
            GROUP BY
                pd.client_id,
                c.client_code,
                c.full_name,
                c.generated_client_account
            ORDER BY total_pending_amount DESC, total_pending_debits DESC, c.full_name ASC
            LIMIT {$limit}
        ";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
if (!function_exists('sl_client_accounts_get_kpis')) {
    function sl_client_accounts_get_kpis(PDO $pdo, array $filters = []): array
    {
        if (!tableExists($pdo, 'bank_accounts')) {
            return [
                'accounts_count' => 0,
                'initial_balance_total' => 0,
                'current_balance_total' => 0,
                'negative_accounts_count' => 0,
            ];
        }

        $where = ['1=1'];
        $params = [];

        $hasClients = tableExists($pdo, 'clients');

        if (!empty($filters['q'])) {
            $q = '%' . trim((string)$filters['q']) . '%';

            if ($hasClients) {
                $where[] = "(
                    COALESCE(ba.account_number,'') LIKE ?
                    OR COALESCE(ba.account_name,'') LIKE ?
                    OR COALESCE(c.client_code,'') LIKE ?
                    OR COALESCE(c.full_name,'') LIKE ?
                )";
                array_push($params, $q, $q, $q, $q);
            } else {
                $where[] = "(
                    COALESCE(ba.account_number,'') LIKE ?
                    OR COALESCE(ba.account_name,'') LIKE ?
                )";
                array_push($params, $q, $q);
            }
        }

        if ($hasClients && !empty($filters['client_status'])) {
            if ($filters['client_status'] === 'active') {
                $where[] = 'COALESCE(c.is_active,1) = 1';
            } elseif ($filters['client_status'] === 'archived') {
                $where[] = 'COALESCE(c.is_active,1) = 0';
            }
        }

        if (!empty($filters['balance_filter'])) {
            switch ($filters['balance_filter']) {
                case 'positive':
                    $where[] = 'COALESCE(ba.balance,0) > 0';
                    break;
                case 'negative':
                    $where[] = 'COALESCE(ba.balance,0) < 0';
                    break;
                case 'zero':
                    $where[] = 'COALESCE(ba.balance,0) = 0';
                    break;
                case 'non_zero':
                    $where[] = 'COALESCE(ba.balance,0) <> 0';
                    break;
            }
        }

        if (array_key_exists('balance_min', $filters) && $filters['balance_min'] !== null) {
            $where[] = 'COALESCE(ba.balance,0) >= ?';
            $params[] = (float)$filters['balance_min'];
        }

        if (array_key_exists('balance_max', $filters) && $filters['balance_max'] !== null) {
            $where[] = 'COALESCE(ba.balance,0) <= ?';
            $params[] = (float)$filters['balance_max'];
        }

        $sql = "
            SELECT
                COUNT(*) AS accounts_count,
                COALESCE(SUM(COALESCE(ba.initial_balance, 0)), 0) AS initial_balance_total,
                COALESCE(SUM(COALESCE(ba.balance, 0)), 0) AS current_balance_total,
                COALESCE(SUM(CASE WHEN COALESCE(ba.balance, 0) < 0 THEN 1 ELSE 0 END), 0) AS negative_accounts_count
            FROM bank_accounts ba
            " . ($hasClients ? "LEFT JOIN clients c ON c.generated_client_account = ba.account_number" : "") . "
            WHERE " . implode(' AND ', $where);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'accounts_count' => (int)($row['accounts_count'] ?? 0),
            'initial_balance_total' => (float)($row['initial_balance_total'] ?? 0),
            'current_balance_total' => (float)($row['current_balance_total'] ?? 0),
            'negative_accounts_count' => (int)($row['negative_accounts_count'] ?? 0),
        ];
    }
}
if (!function_exists('sl_operations_list_get_rows')) {
    function sl_operations_list_get_rows(PDO $pdo, array $filters = []): array
    {
        $filters = array_merge([
            'q' => '',
            'date_from' => '',
            'date_to' => '',
            'operation_type_code' => '',
            'service_id' => '',
            'page' => 1,
            'per_page' => 20,
        ], $filters);

        $where = ['1=1'];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = "(
                COALESCE(o.reference,'') LIKE ?
                OR COALESCE(o.label,'') LIKE ?
                OR COALESCE(c.client_code,'') LIKE ?
                OR COALESCE(c.full_name,'') LIKE ?
                OR COALESCE(o.description,'') LIKE ?
            )";
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($filters['date_from'] !== '') {
            $where[] = 'DATE(o.operation_date) >= ?';
            $params[] = $filters['date_from'];
        }

        if ($filters['date_to'] !== '') {
            $where[] = 'DATE(o.operation_date) <= ?';
            $params[] = $filters['date_to'];
        }

        if ($filters['operation_type_code'] !== '') {
            $where[] = 'COALESCE(ot.code, \'\') LIKE ?';
            $params[] = '%' . $filters['operation_type_code'] . '%';
        }

        if ($filters['service_id'] !== '' && ctype_digit((string)$filters['service_id'])) {
            $where[] = 'o.service_id = ?';
            $params[] = (int)$filters['service_id'];
        }

        $fromSql = "
            FROM operations o
            LEFT JOIN clients c ON c.id = o.client_id
            LEFT JOIN ref_operation_types ot ON ot.id = o.operation_type_id
            LEFT JOIN ref_services s ON s.id = o.service_id
            LEFT JOIN treasury_accounts ta ON ta.id = o.linked_treasury_account_id
            WHERE " . implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) " . $fromSql;
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $perPage = (int)$filters['per_page'];
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min($perPage, 200);

        $pages = max(1, (int)ceil($total / $perPage));

        $page = (int)$filters['page'];
        if ($page <= 0) {
            $page = 1;
        }
        if ($page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                o.*,
                c.client_code,
                c.full_name AS client_full_name,
                ot.code AS operation_type_code,
                s.label AS service_label,
                ta.account_code AS linked_treasury_account_code,
                ta.account_label AS linked_treasury_account_label
            " . $fromSql . "
            ORDER BY o.operation_date DESC, o.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
            'prev_page' => $page > 1 ? $page - 1 : 1,
            'next_page' => $page < $pages ? $page + 1 : $pages,
        ];
    }
}
if (!function_exists('sl_manage_accounts_money')) {
    function sl_manage_accounts_money(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}

if (!function_exists('sl_manage_accounts_valid_date')) {
    function sl_manage_accounts_valid_date(string $value): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}

if (!function_exists('sl_manage_accounts_like')) {
    function sl_manage_accounts_like(string $needle, array $haystack): bool
    {
        $needle = mb_strtolower(trim($needle));
        if ($needle === '') {
            return true;
        }

        foreach ($haystack as $value) {
            if (mb_stripos((string)$value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sl_manage_accounts_paginate_array')) {
    function sl_manage_accounts_paginate_array(array $rows, int $page, int $perPage): array
    {
        $total = count($rows);
        $pages = max(1, (int)ceil($total / max(1, $perPage)));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($rows, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }
}

if (!function_exists('sl_manage_accounts_parse_filters')) {
    function sl_manage_accounts_parse_filters(array $input): array
    {
        $from = trim((string)($input['from'] ?? date('Y-m-01')));
        $to = trim((string)($input['to'] ?? date('Y-m-t')));

        if (!sl_manage_accounts_valid_date($from)) {
            $from = date('Y-m-01');
        }
        if (!sl_manage_accounts_valid_date($to)) {
            $to = date('Y-m-t');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $perPage = (int)($input['per_page'] ?? 15);
        $allowedPerPage = [10, 15, 20, 30, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 15;
        }

        return [
            'from' => $from,
            'to' => $to,
            'q' => trim((string)($input['q'] ?? '')),
            'family' => trim((string)($input['family'] ?? '')),
            'client_status' => trim((string)($input['client_status'] ?? '')),
            'client_type' => trim((string)($input['client_type'] ?? '')),
            'country_commercial' => trim((string)($input['country_commercial'] ?? '')),
            'country_destination' => trim((string)($input['country_destination'] ?? '')),
            'postable' => trim((string)($input['postable'] ?? '')),
            'active' => trim((string)($input['active'] ?? '')),
            'currency' => trim((string)($input['currency'] ?? '')),
            'bank' => trim((string)($input['bank'] ?? '')),
            'per_page' => $perPage,
            'client_page' => max(1, (int)($input['client_page'] ?? 1)),
            'treasury_page' => max(1, (int)($input['treasury_page'] ?? 1)),
            'service_page' => max(1, (int)($input['service_page'] ?? 1)),
            'allowed_per_page' => $allowedPerPage,
        ];
    }
}

if (!function_exists('sl_manage_accounts_load_base_data')) {
    function sl_manage_accounts_load_base_data(PDO $pdo): array
    {
        $serviceAccounts = tableExists($pdo, 'service_accounts')
            ? $pdo->query("
                SELECT *
                FROM service_accounts
                ORDER BY account_code ASC
            ")->fetchAll(PDO::FETCH_ASSOC)
            : [];

        $treasuryAccounts = tableExists($pdo, 'treasury_accounts')
            ? $pdo->query("
                SELECT *
                FROM treasury_accounts
                ORDER BY account_code ASC
            ")->fetchAll(PDO::FETCH_ASSOC)
            : [];

        $clientAccounts = [];
        if (tableExists($pdo, 'bank_accounts')) {
            $sql411 = "
                SELECT
                    ba.*,
                    c.id AS client_id,
                    c.client_code,
                    c.full_name,
                    c.country_commercial,
                    c.client_type,
                    c.is_active AS client_is_active
                FROM bank_accounts ba
                LEFT JOIN clients c ON c.generated_client_account = ba.account_number
                WHERE ba.account_number LIKE '411%'
                ORDER BY ba.account_number ASC
            ";
            $clientAccounts = $pdo->query($sql411)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [
            'service_accounts' => $serviceAccounts,
            'treasury_accounts' => $treasuryAccounts,
            'client_accounts' => $clientAccounts,
        ];
    }
}

if (!function_exists('sl_manage_accounts_load_movement_maps')) {
    function sl_manage_accounts_load_movement_maps(PDO $pdo, string $from, string $to, array $treasuryAccounts): array
    {
        $serviceMovementMap = [];
        $treasuryMovementMap = [];
        $clientMovementMap = [];

        if (tableExists($pdo, 'operations')) {
            $stmt706 = $pdo->prepare("
                SELECT
                    account_code,
                    SUM(total_credit) AS total_credit,
                    SUM(total_debit) AS total_debit
                FROM (
                    SELECT
                        credit_account_code AS account_code,
                        SUM(amount) AS total_credit,
                        0 AS total_debit
                    FROM operations
                    WHERE operation_date BETWEEN ? AND ?
                      AND COALESCE(credit_account_code, '') LIKE '706%'
                    GROUP BY credit_account_code

                    UNION ALL

                    SELECT
                        debit_account_code AS account_code,
                        0 AS total_credit,
                        SUM(amount) AS total_debit
                    FROM operations
                    WHERE operation_date BETWEEN ? AND ?
                      AND COALESCE(debit_account_code, '') LIKE '706%'
                    GROUP BY debit_account_code
                ) t
                GROUP BY account_code
            ");
            $stmt706->execute([$from, $to, $from, $to]);

            foreach ($stmt706->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $serviceMovementMap[(string)$row['account_code']] = [
                    'credit' => (float)($row['total_credit'] ?? 0),
                    'debit' => (float)($row['total_debit'] ?? 0),
                    'net' => (float)($row['total_credit'] ?? 0) - (float)($row['total_debit'] ?? 0),
                ];
            }

            $stmt411 = $pdo->prepare("
                SELECT
                    account_code,
                    SUM(total_credit) AS total_credit,
                    SUM(total_debit) AS total_debit
                FROM (
                    SELECT
                        credit_account_code AS account_code,
                        SUM(amount) AS total_credit,
                        0 AS total_debit
                    FROM operations
                    WHERE operation_date BETWEEN ? AND ?
                      AND COALESCE(credit_account_code, '') LIKE '411%'
                    GROUP BY credit_account_code

                    UNION ALL

                    SELECT
                        debit_account_code AS account_code,
                        0 AS total_credit,
                        SUM(amount) AS total_debit
                    FROM operations
                    WHERE operation_date BETWEEN ? AND ?
                      AND COALESCE(debit_account_code, '') LIKE '411%'
                    GROUP BY debit_account_code
                ) t
                GROUP BY account_code
            ");
            $stmt411->execute([$from, $to, $from, $to]);

            foreach ($stmt411->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $clientMovementMap[(string)$row['account_code']] = [
                    'credit' => (float)($row['total_credit'] ?? 0),
                    'debit' => (float)($row['total_debit'] ?? 0),
                    'net' => (float)($row['total_credit'] ?? 0) - (float)($row['total_debit'] ?? 0),
                ];
            }
        }

        if (tableExists($pdo, 'operations') || tableExists($pdo, 'treasury_movements')) {
            foreach ($treasuryAccounts as $account) {
                $code = (string)($account['account_code'] ?? '');
                $id = (int)($account['id'] ?? 0);

                $opsCredit = 0.0;
                $opsDebit = 0.0;
                $tmIn = 0.0;
                $tmOut = 0.0;

                if (tableExists($pdo, 'operations') && $code !== '') {
                    $stmtOps512 = $pdo->prepare("
                        SELECT
                            COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit,
                            COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit
                        FROM operations
                        WHERE operation_date BETWEEN ? AND ?
                    ");
                    $stmtOps512->execute([$code, $code, $from, $to]);
                    $ops = $stmtOps512->fetch(PDO::FETCH_ASSOC) ?: [];
                    $opsCredit = (float)($ops['total_credit'] ?? 0);
                    $opsDebit = (float)($ops['total_debit'] ?? 0);
                }

                if (tableExists($pdo, 'treasury_movements') && $id > 0) {
                    $stmtTm = $pdo->prepare("
                        SELECT
                            COALESCE(SUM(CASE WHEN target_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_in,
                            COALESCE(SUM(CASE WHEN source_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_out
                        FROM treasury_movements
                        WHERE operation_date BETWEEN ? AND ?
                    ");
                    $stmtTm->execute([$id, $id, $from, $to]);
                    $tm = $stmtTm->fetch(PDO::FETCH_ASSOC) ?: [];
                    $tmIn = (float)($tm['total_in'] ?? 0);
                    $tmOut = (float)($tm['total_out'] ?? 0);
                }

                $credit = $opsCredit + $tmIn;
                $debit = $opsDebit + $tmOut;

                $treasuryMovementMap[$code] = [
                    'credit' => $credit,
                    'debit' => $debit,
                    'net' => $credit - $debit,
                ];
            }
        }

        return [
            'service' => $serviceMovementMap,
            'treasury' => $treasuryMovementMap,
            'client' => $clientMovementMap,
        ];
    }
}

if (!function_exists('sl_manage_accounts_filter_client_rows')) {
    function sl_manage_accounts_filter_client_rows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if ($filters['family'] !== '' && $filters['family'] !== '411') {
                return false;
            }

            $isActive = (int)($row['client_is_active'] ?? 1) === 1;

            if ($filters['client_status'] === 'active' && !$isActive) {
                return false;
            }
            if ($filters['client_status'] === 'inactive' && $isActive) {
                return false;
            }

            if ($filters['active'] === 'active' && !$isActive) {
                return false;
            }
            if ($filters['active'] === 'inactive' && $isActive) {
                return false;
            }

            if ($filters['client_type'] !== '' && (string)($row['client_type'] ?? '') !== $filters['client_type']) {
                return false;
            }

            if ($filters['country_commercial'] !== '' && (string)($row['country_commercial'] ?? '') !== $filters['country_commercial']) {
                return false;
            }

            return sl_manage_accounts_like($filters['q'], [
                $row['account_number'] ?? '',
                $row['account_name'] ?? '',
                $row['client_code'] ?? '',
                $row['full_name'] ?? '',
                $row['country_commercial'] ?? '',
                $row['client_type'] ?? '',
            ]);
        }));
    }
}

if (!function_exists('sl_manage_accounts_filter_treasury_rows')) {
    function sl_manage_accounts_filter_treasury_rows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if ($filters['family'] !== '' && $filters['family'] !== '512') {
                return false;
            }

            $isActive = (int)($row['is_active'] ?? 1) === 1;

            if ($filters['active'] === 'active' && !$isActive) {
                return false;
            }
            if ($filters['active'] === 'inactive' && $isActive) {
                return false;
            }

            if ($filters['currency'] !== '' && (string)($row['currency_code'] ?? '') !== $filters['currency']) {
                return false;
            }

            if ($filters['bank'] !== '' && (string)($row['bank_name'] ?? '') !== $filters['bank']) {
                return false;
            }

            return sl_manage_accounts_like($filters['q'], [
                $row['account_code'] ?? '',
                $row['account_label'] ?? '',
                $row['bank_name'] ?? '',
                $row['country_label'] ?? '',
                $row['currency_code'] ?? '',
            ]);
        }));
    }
}

if (!function_exists('sl_manage_accounts_filter_service_rows')) {
    function sl_manage_accounts_filter_service_rows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if ($filters['family'] !== '' && $filters['family'] !== '706') {
                return false;
            }

            $isActive = (int)($row['is_active'] ?? 1) === 1;
            $isPostable = (int)($row['is_postable'] ?? 0) === 1;

            if ($filters['active'] === 'active' && !$isActive) {
                return false;
            }
            if ($filters['active'] === 'inactive' && $isActive) {
                return false;
            }

            if ($filters['postable'] === 'postable' && !$isPostable) {
                return false;
            }
            if ($filters['postable'] === 'structure' && $isPostable) {
                return false;
            }

            if ($filters['country_commercial'] !== '' && (string)($row['commercial_country_label'] ?? '') !== $filters['country_commercial']) {
                return false;
            }

            if ($filters['country_destination'] !== '' && (string)($row['destination_country_label'] ?? '') !== $filters['country_destination']) {
                return false;
            }

            return sl_manage_accounts_like($filters['q'], [
                $row['account_code'] ?? '',
                $row['account_label'] ?? '',
                $row['operation_type_label'] ?? '',
                $row['destination_country_label'] ?? '',
                $row['commercial_country_label'] ?? '',
            ]);
        }));
    }
}

if (!function_exists('sl_manage_accounts_build_summary')) {
    function sl_manage_accounts_build_summary(array $clientRows, array $treasuryRows, array $serviceRows, array $movementMaps): array
    {
        $summary = [
            '411' => ['count' => 0, 'current_balance' => 0.0, 'period_credit' => 0.0, 'period_debit' => 0.0, 'period_net' => 0.0],
            '512' => ['count' => 0, 'current_balance' => 0.0, 'period_credit' => 0.0, 'period_debit' => 0.0, 'period_net' => 0.0],
            '706' => ['count' => 0, 'current_balance' => 0.0, 'period_credit' => 0.0, 'period_debit' => 0.0, 'period_net' => 0.0],
        ];

        foreach ($clientRows as $row) {
            $code = (string)($row['account_number'] ?? '');
            $mv = $movementMaps['client'][$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

            $summary['411']['count']++;
            $summary['411']['current_balance'] += (float)($row['balance'] ?? 0);
            $summary['411']['period_credit'] += (float)$mv['credit'];
            $summary['411']['period_debit'] += (float)$mv['debit'];
            $summary['411']['period_net'] += (float)$mv['net'];
        }

        foreach ($treasuryRows as $row) {
            $code = (string)($row['account_code'] ?? '');
            $mv = $movementMaps['treasury'][$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

            $summary['512']['count']++;
            $summary['512']['current_balance'] += (float)($row['current_balance'] ?? 0);
            $summary['512']['period_credit'] += (float)$mv['credit'];
            $summary['512']['period_debit'] += (float)$mv['debit'];
            $summary['512']['period_net'] += (float)$mv['net'];
        }

        foreach ($serviceRows as $row) {
            $code = (string)($row['account_code'] ?? '');
            $mv = $movementMaps['service'][$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

            $summary['706']['count']++;
            $summary['706']['current_balance'] += (float)($row['current_balance'] ?? 0);
            $summary['706']['period_credit'] += (float)$mv['credit'];
            $summary['706']['period_debit'] += (float)$mv['debit'];
            $summary['706']['period_net'] += (float)$mv['net'];
        }

        return $summary;
    }
}

if (!function_exists('sl_manage_accounts_build_filter_options')) {
    function sl_manage_accounts_build_filter_options(array $clientAccounts, array $treasuryAccounts, array $serviceAccounts): array
    {
        $clientTypes = [];
        $commercialCountries = [];
        $destinationCountries = [];
        $currencies = [];
        $banks = [];

        foreach ($clientAccounts as $row) {
            $value = trim((string)($row['client_type'] ?? ''));
            if ($value !== '') {
                $clientTypes[$value] = $value;
            }

            $value = trim((string)($row['country_commercial'] ?? ''));
            if ($value !== '') {
                $commercialCountries[$value] = $value;
            }
        }

        foreach ($serviceAccounts as $row) {
            $value = trim((string)($row['commercial_country_label'] ?? ''));
            if ($value !== '') {
                $commercialCountries[$value] = $value;
            }

            $value = trim((string)($row['destination_country_label'] ?? ''));
            if ($value !== '') {
                $destinationCountries[$value] = $value;
            }
        }

        foreach ($treasuryAccounts as $row) {
            $value = trim((string)($row['currency_code'] ?? ''));
            if ($value !== '') {
                $currencies[$value] = $value;
            }

            $value = trim((string)($row['bank_name'] ?? ''));
            if ($value !== '') {
                $banks[$value] = $value;
            }
        }

        ksort($clientTypes);
        ksort($commercialCountries);
        ksort($destinationCountries);
        ksort($currencies);
        ksort($banks);

        return [
            'client_types' => $clientTypes,
            'commercial_countries' => $commercialCountries,
            'destination_countries' => $destinationCountries,
            'currencies' => $currencies,
            'banks' => $banks,
        ];
    }
}
if (!function_exists('sl_manage_accounts_build_page_query')) {
    function sl_manage_accounts_build_page_query(array $query, string $pageKey, int $page): array
    {
        $query[$pageKey] = $page;

        if ($pageKey !== 'client_page') {
            $query['client_page'] = $query['client_page'] ?? 1;
        }
        if ($pageKey !== 'service_page') {
            $query['service_page'] = $query['service_page'] ?? 1;
        }
        if ($pageKey !== 'treasury_page') {
            $query['treasury_page'] = $query['treasury_page'] ?? 1;
        }

        return $query;
    }
}
if (!function_exists('sl_safe_int')) {
    function sl_safe_int($value, int $default = 0): int
    {
        return is_numeric($value) ? (int)$value : $default;
    }
}

if (!function_exists('sl_valid_date')) {
    function sl_valid_date(?string $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}

if (!function_exists('sl_normalize_per_page')) {
    function sl_normalize_per_page($value, array $allowed = [10, 25, 50, 100], int $default = 25): int
    {
        $value = sl_safe_int($value, $default);
        return in_array($value, $allowed, true) ? $value : $default;
    }
}

if (!function_exists('sl_paginate_meta')) {
    function sl_paginate_meta(int $total, int $page, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'offset' => $offset,
        ];
    }
}

if (!function_exists('sl_paginated_query')) {
    function sl_paginated_query(
        PDO $pdo,
        string $countSql,
        array $countParams,
        string $rowsSql,
        array $rowsParams,
        int $page,
        int $perPage
    ): array {
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($countParams);
        $total = (int)$stmtCount->fetchColumn();

        $meta = sl_paginate_meta($total, $page, $perPage);

        $rowsSql .= " LIMIT " . (int)$meta['per_page'] . " OFFSET " . (int)$meta['offset'];
        $stmtRows = $pdo->prepare($rowsSql);
        $stmtRows->execute($rowsParams);

        return [
            'rows' => $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $meta['total'],
            'page' => $meta['page'],
            'pages' => $meta['pages'],
            'per_page' => $meta['per_page'],
        ];
    }
}

if (!function_exists('sl_build_pagination_query')) {
    function sl_build_pagination_query(array $query, int $page): string
    {
        $query['page'] = $page;
        return http_build_query($query);
    }
}

if (!function_exists('sl_render_pagination')) {
    function sl_render_pagination(string $baseUrl, array $query, int $page, int $pages, int $total): string
    {
        if ($pages <= 1) {
            return '';
        }

        $start = max(1, $page - 2);
        $end = min($pages, $page + 2);

        ob_start();
        ?>
        <div class="btn-group" style="justify-content:space-between;align-items:center;width:100%;margin-top:18px;">
            <div class="muted">
                Page <?= (int)$page ?> / <?= (int)$pages ?> — <?= (int)$total ?> résultat(s)
            </div>

            <div class="btn-group">
                <?php if ($page > 1): ?>
                    <a href="<?= e($baseUrl) ?>?<?= e(sl_build_pagination_query($query, 1)) ?>" class="btn btn-outline">«</a>
                    <a href="<?= e($baseUrl) ?>?<?= e(sl_build_pagination_query($query, $page - 1)) ?>" class="btn btn-outline">‹</a>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a
                        href="<?= e($baseUrl) ?>?<?= e(sl_build_pagination_query($query, $i)) ?>"
                        class="btn <?= $i === $page ? 'btn-success' : 'btn-outline' ?>"
                    >
                        <?= (int)$i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a href="<?= e($baseUrl) ?>?<?= e(sl_build_pagination_query($query, $page + 1)) ?>" class="btn btn-outline">›</a>
                    <a href="<?= e($baseUrl) ?>?<?= e(sl_build_pagination_query($query, $pages)) ?>" class="btn btn-outline">»</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
if (!function_exists('sl_admin_user_logs_get_filter_options')) {
    function sl_admin_user_logs_get_filter_options(PDO $pdo): array
    {
        $users = [];
        if (tableExists($pdo, 'users')) {
            $userLabel = columnExists($pdo, 'users', 'username')
                ? 'username'
                : (columnExists($pdo, 'users', 'email') ? 'email' : 'id');

            $users = $pdo->query("
                SELECT id, {$userLabel} AS display_name
                FROM users
                ORDER BY {$userLabel} ASC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $entityTypes = [];
        if (tableExists($pdo, 'user_logs') && columnExists($pdo, 'user_logs', 'entity_type')) {
            $entityTypes = $pdo->query("
                SELECT DISTINCT entity_type
                FROM user_logs
                WHERE COALESCE(entity_type,'') <> ''
                ORDER BY entity_type ASC
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        $modules = [];
        if (tableExists($pdo, 'user_logs') && columnExists($pdo, 'user_logs', 'module')) {
            $modules = $pdo->query("
                SELECT DISTINCT module
                FROM user_logs
                WHERE COALESCE(module,'') <> ''
                ORDER BY module ASC
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        return [
            'users' => $users,
            'entity_types' => array_values(array_filter(array_map('strval', $entityTypes))),
            'modules' => array_values(array_filter(array_map('strval', $modules))),
        ];
    }
}

if (!function_exists('sl_admin_user_logs_parse_filters')) {
    function sl_admin_user_logs_parse_filters(array $input): array
    {
        return [
            'search' => trim((string)($input['search'] ?? '')),
            'entity_type' => trim((string)($input['entity_type'] ?? '')),
            'module' => trim((string)($input['module'] ?? '')),
            'user_id' => max(0, (int)($input['user_id'] ?? 0)),
            'from' => trim((string)($input['from'] ?? '')),
            'to' => trim((string)($input['to'] ?? '')),
            'page' => max(1, (int)($input['page'] ?? 1)),
            'per_page' => function_exists('sl_normalize_per_page')
                ? sl_normalize_per_page($input['per_page'] ?? 25)
                : (in_array((int)($input['per_page'] ?? 25), [10, 25, 50, 100], true) ? (int)$input['per_page'] : 25),
        ];
    }
}

if (!function_exists('sl_admin_user_logs_get_kpis')) {
    function sl_admin_user_logs_get_kpis(PDO $pdo, array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = "(
                COALESCE(l.action,'') LIKE ?
                OR COALESCE(l.module,'') LIKE ?
                OR COALESCE(l.entity_type,'') LIKE ?
                OR COALESCE(l.details,'') LIKE ?
            )";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (($filters['entity_type'] ?? '') !== '' && columnExists($pdo, 'user_logs', 'entity_type')) {
            $where[] = "l.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (($filters['module'] ?? '') !== '' && columnExists($pdo, 'user_logs', 'module')) {
            $where[] = "l.module = ?";
            $params[] = $filters['module'];
        }

        if ((int)($filters['user_id'] ?? 0) > 0 && columnExists($pdo, 'user_logs', 'user_id')) {
            $where[] = "l.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['from']) && function_exists('sl_valid_date') && sl_valid_date($filters['from']) && columnExists($pdo, 'user_logs', 'created_at')) {
            $where[] = "DATE(l.created_at) >= ?";
            $params[] = $filters['from'];
        }

        if (!empty($filters['to']) && function_exists('sl_valid_date') && sl_valid_date($filters['to']) && columnExists($pdo, 'user_logs', 'created_at')) {
            $where[] = "DATE(l.created_at) <= ?";
            $params[] = $filters['to'];
        }

        $totalLogs = 0;
        if (tableExists($pdo, 'user_logs')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM user_logs l
                WHERE " . implode(' AND ', $where)
            );
            $stmt->execute($params);
            $totalLogs = (int)$stmt->fetchColumn();
        }

        $filterOptions = sl_admin_user_logs_get_filter_options($pdo);

        return [
            'logs_found' => $totalLogs,
            'entity_types_count' => count($filterOptions['entity_types']),
            'modules_count' => count($filterOptions['modules']),
            'unread_notifications' => function_exists('countUnreadNotifications')
                ? (int)countUnreadNotifications($pdo)
                : 0,
        ];
    }
}

if (!function_exists('sl_admin_user_logs_get_rows')) {
    function sl_admin_user_logs_get_rows(PDO $pdo, array $filters): array
    {
        if (!tableExists($pdo, 'user_logs')) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'per_page' => (int)($filters['per_page'] ?? 25),
            ];
        }

        $where = ['1=1'];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = "(
                COALESCE(l.action,'') LIKE ?
                OR COALESCE(l.module,'') LIKE ?
                OR COALESCE(l.entity_type,'') LIKE ?
                OR COALESCE(l.details,'') LIKE ?
            )";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (($filters['entity_type'] ?? '') !== '' && columnExists($pdo, 'user_logs', 'entity_type')) {
            $where[] = "l.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (($filters['module'] ?? '') !== '' && columnExists($pdo, 'user_logs', 'module')) {
            $where[] = "l.module = ?";
            $params[] = $filters['module'];
        }

        if ((int)($filters['user_id'] ?? 0) > 0 && columnExists($pdo, 'user_logs', 'user_id')) {
            $where[] = "l.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['from']) && function_exists('sl_valid_date') && sl_valid_date($filters['from']) && columnExists($pdo, 'user_logs', 'created_at')) {
            $where[] = "DATE(l.created_at) >= ?";
            $params[] = $filters['from'];
        }

        if (!empty($filters['to']) && function_exists('sl_valid_date') && sl_valid_date($filters['to']) && columnExists($pdo, 'user_logs', 'created_at')) {
            $where[] = "DATE(l.created_at) <= ?";
            $params[] = $filters['to'];
        }

        $joinUser = '';
        $selectUser = "NULL AS username";

        if (tableExists($pdo, 'users') && columnExists($pdo, 'user_logs', 'user_id')) {
            if (columnExists($pdo, 'users', 'username')) {
                $selectUser = "u.username AS username";
            } elseif (columnExists($pdo, 'users', 'email')) {
                $selectUser = "u.email AS username";
            } else {
                $selectUser = "CAST(u.id AS CHAR) AS username";
            }

            $joinUser = "LEFT JOIN users u ON u.id = l.user_id";
        }

        $countSql = "
            SELECT COUNT(*)
            FROM user_logs l
            {$joinUser}
            WHERE " . implode(' AND ', $where);

        $rowsSql = "
            SELECT
                l.*,
                {$selectUser}
            FROM user_logs l
            {$joinUser}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY " . (columnExists($pdo, 'user_logs', 'created_at') ? 'l.created_at DESC' : 'l.id DESC');

        if (function_exists('sl_paginated_query')) {
            return sl_paginated_query(
                $pdo,
                $countSql,
                $params,
                $rowsSql,
                $params,
                max(1, (int)($filters['page'] ?? 1)),
                max(1, (int)($filters['per_page'] ?? 25))
            );
        }

        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $perPage = max(1, (int)($filters['per_page'] ?? 25));
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min((int)($filters['page'] ?? 1), $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare($rowsSql . " LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }
}
if (!function_exists('sl_get_available_treasury_accounts')) {
    function sl_get_available_treasury_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        $sql = "
            SELECT
                id,
                account_code,
                account_label,
                current_balance,
                COALESCE(is_active,1) AS is_active,
                COALESCE(is_primary,0) AS is_primary,
                COALESCE(is_secondary,0) AS is_secondary
            FROM treasury_accounts
            WHERE COALESCE(is_active,1) = 1
            ORDER BY
                COALESCE(is_primary,0) DESC,
                COALESCE(is_secondary,0) DESC,
                account_code ASC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_find_default_archive_treasury_account')) {
    function sl_find_default_archive_treasury_account(PDO $pdo): ?array
    {
        $accounts = sl_get_available_treasury_accounts($pdo);
        if (!$accounts) {
            return null;
        }

        foreach ($accounts as $account) {
            if ((int)($account['is_primary'] ?? 0) === 1) {
                return $account;
            }
        }

        foreach ($accounts as $account) {
            if ((int)($account['is_secondary'] ?? 0) === 1) {
                return $account;
            }
        }

        return $accounts[0] ?? null;
    }
}
if (!function_exists('sl_restore_client_balance_from_archive')) {
    function sl_restore_client_balance_from_archive(PDO $pdo, array $client, int $userId = 0): array
    {
        if (!tableExists($pdo, 'bank_accounts')) {
            throw new RuntimeException('Table bank_accounts introuvable.');
        }

        if (!tableExists($pdo, 'treasury_accounts')) {
            throw new RuntimeException('Table treasury_accounts introuvable.');
        }

        if (!tableExists($pdo, 'operations')) {
            throw new RuntimeException('Table operations introuvable.');
        }

        $clientId = (int)($client['id'] ?? 0);
        $client411 = trim((string)($client['generated_client_account'] ?? ''));
        if ($clientId <= 0 || $client411 === '') {
            throw new RuntimeException('Compte 411 client introuvable.');
        }

        $stmtLastArchive = $pdo->prepare("
            SELECT *
            FROM operations
            WHERE client_id = ?
              AND operation_type_code = 'ARCHIVE_CLIENT'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtLastArchive->execute([$clientId]);
        $archiveOp = $stmtLastArchive->fetch(PDO::FETCH_ASSOC);

        if (!$archiveOp) {
            return [
                'restored_amount' => 0.0,
                'treasury_account_id' => 0,
                'treasury_account_code' => null,
            ];
        }

        $amount = round((float)($archiveOp['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return [
                'restored_amount' => 0.0,
                'treasury_account_id' => 0,
                'treasury_account_code' => null,
            ];
        }

        $treasuryCode = trim((string)($archiveOp['debit_account_code'] ?? ''));
        if ($treasuryCode === '') {
            throw new RuntimeException('Compte 512 source introuvable sur l’archive précédente.');
        }

        $stmt512 = $pdo->prepare("
            SELECT id, account_code, account_label, current_balance
            FROM treasury_accounts
            WHERE account_code = ?
            LIMIT 1
        ");
        $stmt512->execute([$treasuryCode]);
        $treasury = $stmt512->fetch(PDO::FETCH_ASSOC);

        if (!$treasury) {
            throw new RuntimeException('Compte 512 source introuvable.');
        }

        $treasuryId = (int)($treasury['id'] ?? 0);
        $current512 = (float)($treasury['current_balance'] ?? 0);

        if ($current512 < $amount) {
            throw new RuntimeException('Le compte 512 source ne dispose pas d’un solde suffisant pour restaurer le client.');
        }

        $stmt411 = $pdo->prepare("
            SELECT id, account_number, account_name, initial_balance, balance
            FROM bank_accounts
            WHERE account_number = ?
            LIMIT 1
        ");
        $stmt411->execute([$client411]);
        $bankAccount = $stmt411->fetch(PDO::FETCH_ASSOC);

        if (!$bankAccount) {
            throw new RuntimeException('Compte bancaire 411 introuvable.');
        }

        $currencyCode = (string)($archiveOp['currency_code'] ?? 'EUR');

        $operationColumns = [];
        $operationValues = [];
        $operationParams = [];

        $operationMap = [
            'operation_date' => date('Y-m-d'),
            'client_id' => $clientId,
            'operation_type_code' => 'RESTORE_CLIENT',
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'debit_account_code' => $treasuryCode,
            'credit_account_code' => $client411,
            'linked_treasury_account_code' => $treasuryCode,
            'label' => 'Réactivation client - restitution 512 vers 411',
            'description' => 'Réactivation client avec restitution du solde archivé',
            'created_by' => $userId > 0 ? $userId : null,
        ];

        foreach ($operationMap as $column => $value) {
            if (columnExists($pdo, 'operations', $column)) {
                $operationColumns[] = $column;
                $operationValues[] = '?';
                $operationParams[] = $value;
            }
        }

        if (columnExists($pdo, 'operations', 'created_at')) {
            $operationColumns[] = 'created_at';
            $operationValues[] = 'NOW()';
        }

        if (columnExists($pdo, 'operations', 'updated_at')) {
            $operationColumns[] = 'updated_at';
            $operationValues[] = 'NOW()';
        }

        if ($operationColumns) {
            $sqlInsertOp = "
                INSERT INTO operations (" . implode(', ', $operationColumns) . ")
                VALUES (" . implode(', ', $operationValues) . ")
            ";
            $stmtInsertOp = $pdo->prepare($sqlInsertOp);
            $stmtInsertOp->execute($operationParams);
        }

        $stmtUpdate411 = $pdo->prepare("
            UPDATE bank_accounts
            SET balance = COALESCE(balance, 0) + ?
            WHERE account_number = ?
        ");
        $stmtUpdate411->execute([$amount, $client411]);

        $stmtUpdate512 = $pdo->prepare("
            UPDATE treasury_accounts
            SET current_balance = COALESCE(current_balance, 0) - ?
            WHERE id = ?
        ");
        $stmtUpdate512->execute([$amount, $treasuryId]);

        if (columnExists($pdo, 'clients', 'updated_at')) {
            $stmtClientUpdate = $pdo->prepare("UPDATE clients SET updated_at = NOW() WHERE id = ?");
            $stmtClientUpdate->execute([$clientId]);
        }

        return [
            'restored_amount' => $amount,
            'treasury_account_id' => $treasuryId,
            'treasury_account_code' => $treasuryCode,
        ];
    }
}
if (!function_exists('sl_rebuild_client_balance')) {
    function sl_rebuild_client_balance(PDO $pdo, int $clientId): void
    {
        if ($clientId <= 0 || !tableExists($pdo, 'clients') || !tableExists($pdo, 'bank_accounts')) {
            return;
        }

        $stmtClient = $pdo->prepare("
            SELECT generated_client_account
            FROM clients
            WHERE id = ?
            LIMIT 1
        ");
        $stmtClient->execute([$clientId]);
        $client = $stmtClient->fetch(PDO::FETCH_ASSOC);

        if (!$client || empty($client['generated_client_account'])) {
            return;
        }

        $accountNumber = (string)$client['generated_client_account'];

        $stmt = $pdo->prepare("
            SELECT balance
            FROM bank_accounts
            WHERE account_number = ?
            LIMIT 1
        ");
        $stmt->execute([$accountNumber]);
        $balance = $stmt->fetchColumn();

        if ($balance === false) {
            return;
        }

        if (columnExists($pdo, 'clients', 'current_balance_411')) {
            $stmtUpdate = $pdo->prepare("
                UPDATE clients
                SET current_balance_411 = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([(float)$balance, $clientId]);
        }
    }
}

if (!function_exists('sl_archive_client_balance_to_treasury')) {
    function sl_archive_client_balance_to_treasury(PDO $pdo, array $client, int $treasuryAccountId, int $userId = 0): array
    {
        if (!tableExists($pdo, 'bank_accounts')) {
            throw new RuntimeException('Table bank_accounts introuvable.');
        }

        if (!tableExists($pdo, 'treasury_accounts')) {
            throw new RuntimeException('Table treasury_accounts introuvable.');
        }

        if (!tableExists($pdo, 'operations')) {
            throw new RuntimeException('Table operations introuvable.');
        }

        $clientId = (int)($client['id'] ?? 0);
        $client411 = trim((string)($client['generated_client_account'] ?? ''));
        if ($clientId <= 0 || $client411 === '') {
            throw new RuntimeException('Compte 411 client introuvable.');
        }

        $stmt411 = $pdo->prepare("
            SELECT id, account_number, account_name, initial_balance, balance
            FROM bank_accounts
            WHERE account_number = ?
            LIMIT 1
        ");
        $stmt411->execute([$client411]);
        $bankAccount = $stmt411->fetch(PDO::FETCH_ASSOC);

        if (!$bankAccount) {
            throw new RuntimeException('Compte bancaire 411 introuvable.');
        }

        $current411 = (float)($bankAccount['balance'] ?? 0);
        if ($current411 <= 0) {
            return [
                'moved_amount' => 0.0,
                'treasury_account_id' => $treasuryAccountId,
                'treasury_account_code' => null,
            ];
        }

        $stmt512 = $pdo->prepare("
            SELECT id, account_code, account_label, current_balance
            FROM treasury_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt512->execute([$treasuryAccountId]);
        $treasury = $stmt512->fetch(PDO::FETCH_ASSOC);

        if (!$treasury) {
            throw new RuntimeException('Compte 512 de destination introuvable.');
        }

        $amount = round($current411, 2);
        $treasuryCode = trim((string)($treasury['account_code'] ?? ''));

        if ($treasuryCode === '') {
            throw new RuntimeException('Le compte 512 sélectionné n’a pas de code comptable.');
        }

        $currencyCode = 'EUR';
        if (tableExists($pdo, 'treasury_accounts') && columnExists($pdo, 'treasury_accounts', 'currency_code')) {
            $stmtCurrency = $pdo->prepare("SELECT currency_code FROM treasury_accounts WHERE id = ? LIMIT 1");
            $stmtCurrency->execute([$treasuryAccountId]);
            $currencyCode = (string)($stmtCurrency->fetchColumn() ?: 'EUR');
        }

        $operationColumns = [];
        $operationValues = [];
        $operationParams = [];

        $operationMap = [
            'operation_date' => date('Y-m-d'),
            'client_id' => $clientId,
            'operation_type_code' => 'ARCHIVE_CLIENT',
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'debit_account_code' => $client411,
            'credit_account_code' => $treasuryCode,
            'linked_treasury_account_code' => $treasuryCode,
            'label' => 'Archivage client - transfert 411 vers 512',
            'description' => 'Archivage client avec transfert du solde 411 vers 512',
            'created_by' => $userId > 0 ? $userId : null,
        ];

        foreach ($operationMap as $column => $value) {
            if (columnExists($pdo, 'operations', $column)) {
                $operationColumns[] = $column;
                $operationValues[] = '?';
                $operationParams[] = $value;
            }
        }

        if (columnExists($pdo, 'operations', 'created_at')) {
            $operationColumns[] = 'created_at';
            $operationValues[] = 'NOW()';
        }

        if (columnExists($pdo, 'operations', 'updated_at')) {
            $operationColumns[] = 'updated_at';
            $operationValues[] = 'NOW()';
        }

        if ($operationColumns) {
            $sqlInsertOp = "
                INSERT INTO operations (" . implode(', ', $operationColumns) . ")
                VALUES (" . implode(', ', $operationValues) . ")
            ";
            $stmtInsertOp = $pdo->prepare($sqlInsertOp);
            $stmtInsertOp->execute($operationParams);
        }

        $stmtUpdate411 = $pdo->prepare("
            UPDATE bank_accounts
            SET balance = 0
            WHERE account_number = ?
        ");
        $stmtUpdate411->execute([$client411]);

        $stmtUpdate512 = $pdo->prepare("
            UPDATE treasury_accounts
            SET current_balance = COALESCE(current_balance, 0) + ?
            WHERE id = ?
        ");
        $stmtUpdate512->execute([$amount, $treasuryAccountId]);

        if (columnExists($pdo, 'clients', 'updated_at')) {
            $stmtClientUpdate = $pdo->prepare("UPDATE clients SET updated_at = NOW() WHERE id = ?");
            $stmtClientUpdate->execute([$clientId]);
        }

        return [
            'moved_amount' => $amount,
            'treasury_account_id' => $treasuryAccountId,
            'treasury_account_code' => $treasuryCode,
        ];
    }
}
if (!function_exists('sl_assert_client_operation_allowed')) {
    function sl_assert_client_operation_allowed(PDO $pdo, int $clientId): void
    {
        if ($clientId <= 0) {
            throw new RuntimeException('Client invalide.');
        }

        if (!tableExists($pdo, 'clients')) {
            throw new RuntimeException('Table clients introuvable.');
        }

        $stmt = $pdo->prepare("
            SELECT id, full_name, client_code, COALESCE(is_active, 1) AS is_active
            FROM clients
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            throw new RuntimeException('Client introuvable.');
        }

        if ((int)($client['is_active'] ?? 1) !== 1) {
            $label = trim((string)($client['client_code'] ?? '') . ' - ' . (string)($client['full_name'] ?? ''));
            if ($label === '-') {
                $label = 'ce client';
            }

            throw new RuntimeException('Aucune opération n’est autorisée sur un client archivé : ' . $label . '.');
        }
    }
}
if (!function_exists('studely_defined_permissions')) {
    function studely_defined_permissions(): array
    {
        return [
            'dashboard_view_page' => 'Accès au dashboard principal',
            'analytics_view_page' => 'Accès aux analyses et tableaux de bord avancés',
            'global_search_view_page' => 'Accès à la recherche globale',

            'clients_view_page' => 'Accès à la liste des clients',
            'client_view_page' => 'Accès à la fiche client',
            'client_create_page' => 'Accès à la création client',
            'client_edit_page' => 'Accès à la modification client',
            'clients_archive_page' => 'Accès à l’archivage / réactivation client',
            'clients_delete_page' => 'Accès à la suppression client',
            'client_accounts_view_page' => 'Accès à la liste des comptes clients 411',
            'client_timeline_view_page' => 'Accès à la timeline client',
            'clients_import_page' => 'Accès à l’import des clients',

            'clients_create' => 'Créer un client',
            'clients_edit' => 'Modifier un client',
            'clients_archive' => 'Archiver / réactiver un client',
            'clients_delete' => 'Supprimer un client',
            'clients_import' => 'Importer des clients',

            'operations_view_page' => 'Accès à la liste des opérations',
            'operation_view_page' => 'Accès au détail d’une opération',
            'operation_create_page' => 'Accès à la création d’une opération',
            'operation_edit_page' => 'Accès à la modification d’une opération',
            'operation_delete_page' => 'Accès à la suppression d’une opération',
            'manual_actions_create_page' => 'Accès aux actions manuelles',
            'operations_monthly_run_page' => 'Accès au lancement des opérations mensuelles clients',

            'operations_view' => 'Consulter les opérations',
            'operations_create' => 'Créer une opération',
            'operations_edit' => 'Modifier une opération',
            'operations_delete' => 'Supprimer une opération',
            'manual_actions_create' => 'Créer une action manuelle',
            'operations_monthly_run' => 'Lancer un traitement mensuel des opérations',

            'imports_upload_page' => 'Accès à l’upload d’import',
            'imports_preview_page' => 'Accès à la prévisualisation d’import',
            'imports_validate_page' => 'Accès à la validation d’import',
            'imports_validate_batch_page' => 'Accès à la validation d’un batch import',
            'imports_journal_page' => 'Accès au journal des imports',
            'imports_mapping_page' => 'Accès au mapping des imports',
            'imports_rejected_rows_page' => 'Accès aux lignes rejetées',
            'imports_correct_rejected_row_page' => 'Accès à la correction d’une ligne rejetée',

            'imports_create' => 'Créer / charger un import',
            'imports_preview' => 'Prévisualiser un import',
            'imports_validate' => 'Valider un import',
            'imports_validate_batch' => 'Valider un batch import',
            'imports_mapping_manage' => 'Gérer le mapping des imports',
            'imports_correct_rejected_rows' => 'Corriger les lignes rejetées',

            'monthly_runs_list_page' => 'Accès à la liste des runs mensuels',
            'monthly_run_view_page' => 'Accès au détail d’un run mensuel',
            'monthly_run_execute_page' => 'Accès à l’exécution d’un run mensuel',
            'monthly_run_cancel_page' => 'Accès à l’annulation d’un run mensuel',
            'monthly_payments_import_page' => 'Accès à l’import des mensualités',
            'monthly_payments_preview_page' => 'Accès à la prévisualisation des mensualités',
            'monthly_payments_validate_page' => 'Accès à la validation des mensualités',

            'monthly_runs_view' => 'Consulter les runs mensuels',
            'monthly_run_execute' => 'Exécuter un run mensuel',
            'monthly_run_cancel' => 'Annuler un run mensuel',
            'monthly_payments_import' => 'Importer des mensualités',
            'monthly_payments_validate' => 'Valider des mensualités',

            'pending_debits_view_page' => 'Accès à la liste des débits dus',
            'pending_debit_view_page' => 'Accès au détail d’un débit dû',
            'pending_debit_edit_page' => 'Accès à la modification d’un débit dû',
            'pending_debit_execute_page' => 'Accès à l’exécution d’un débit dû',
            'pending_debit_cancel_page' => 'Accès à l’annulation d’un débit dû',

            'pending_debits_view' => 'Consulter les débits dus',
            'pending_debits_edit' => 'Modifier un débit dû',
            'pending_debits_execute' => 'Exécuter un débit dû',
            'pending_debits_cancel' => 'Annuler un débit dû',

            'treasury_view_page' => 'Accès à la liste des comptes de trésorerie',
            'treasury_create_page' => 'Accès à la création d’un compte 512',
            'treasury_edit_page' => 'Accès à la modification d’un compte 512',
            'treasury_view_detail_page' => 'Accès à la fiche d’un compte 512',
            'treasury_archive_page' => 'Accès à l’archivage / réactivation d’un compte 512',
            'treasury_import_page' => 'Accès à l’import des comptes 512',
            'bank_accounts_view_page' => 'Accès à la vue des comptes bancaires',
            'treasury_service_accounts_page' => 'Accès au lien trésorerie / comptes service',

            'treasury_view' => 'Consulter les comptes 512',
            'treasury_create' => 'Créer un compte 512',
            'treasury_edit' => 'Modifier un compte 512',
            'treasury_archive' => 'Archiver / réactiver un compte 512',
            'treasury_import' => 'Importer des comptes 512',

            'service_accounts_manage_page' => 'Accès à la gestion des comptes de service 706',
            'service_accounts_create_page' => 'Accès à la création d’un compte 706',
            'service_accounts_edit_page' => 'Accès à la modification d’un compte 706',
            'service_accounts_view_page' => 'Accès à la fiche d’un compte 706',
            'service_accounts_archive_page' => 'Accès à l’archivage d’un compte 706',
            'service_accounts_import_page' => 'Accès à l’import des comptes 706',

            'service_accounts_create' => 'Créer un compte 706',
            'service_accounts_edit' => 'Modifier un compte 706',
            'service_accounts_archive' => 'Archiver un compte 706',
            'service_accounts_import' => 'Importer des comptes 706',

            'statements_view_page' => 'Accès au module des relevés',
            'account_statements_view_page' => 'Accès aux relevés de comptes',
            'client_statement_view_page' => 'Accès au relevé client',
            'client_profiles_view_page' => 'Accès aux profils clients',
            'bulk_statement_export_page' => 'Accès à l’export groupé de relevés',
            'generate_statement_pdf_page' => 'Accès à la génération PDF de relevé',
            'generate_bulk_pdf_page' => 'Accès à la génération PDF en masse',

            'statements_view' => 'Consulter les relevés',
            'statements_export' => 'Exporter les relevés',
            'client_profiles_export' => 'Exporter les profils clients',
            'bulk_statement_export' => 'Exporter des relevés en masse',

            'notifications_view_page' => 'Accès aux notifications',
            'notifications_view' => 'Consulter les notifications',
            'support_requests_view_page' => 'Accès aux demandes support',
            'support_request_create_page' => 'Accès à la création d’une demande support',
            'support_manage_page' => 'Accès à la gestion des demandes support',
            'support_request_create' => 'Créer une demande support',
            'support_manage' => 'Gérer les demandes support',

            'admin_functional_dashboard_view_page' => 'Accès au dashboard fonctionnel',
            'manage_services_page' => 'Accès à la gestion des services',
            'edit_service_page' => 'Accès à la modification d’un service',
            'delete_service_page' => 'Accès à la suppression d’un service',
            'manage_operation_types_page' => 'Accès à la gestion des types d’opérations',
            'edit_operation_type_page' => 'Accès à la modification d’un type d’opération',
            'delete_operation_type_page' => 'Accès à la suppression d’un type d’opération',
            'manage_accounts_page' => 'Accès à la gestion fonctionnelle des comptes',
            'manage_accounting_rules_page' => 'Accès à la gestion des règles comptables',
            'accounting_rule_create_page' => 'Accès à la création d’une règle comptable',
            'accounting_rule_edit_page' => 'Accès à la modification d’une règle comptable',
            'accounting_rule_delete_page' => 'Accès à la suppression d’une règle comptable',
            'accounting_rule_view_page' => 'Accès à la fiche d’une règle comptable',
            'accounting_balance_audit_page' => 'Accès à l’audit des équilibres comptables',
            'catalogs_manage_page' => 'Accès à la gestion des catalogues',

            'services_manage' => 'Gérer les services',
            'services_edit' => 'Modifier un service',
            'services_delete' => 'Supprimer un service',
            'operation_types_manage' => 'Gérer les types d’opérations',
            'operation_types_edit' => 'Modifier un type d’opération',
            'operation_types_delete' => 'Supprimer un type d’opération',
            'accounts_manage' => 'Gérer les comptes fonctionnels',
            'accounting_rules_manage' => 'Gérer les règles comptables',
            'accounting_rules_create' => 'Créer une règle comptable',
            'accounting_rules_edit' => 'Modifier une règle comptable',
            'accounting_rules_delete' => 'Supprimer une règle comptable',
            'accounting_balance_audit_view' => 'Consulter l’audit comptable',
            'catalogs_manage' => 'Gérer les catalogues',

            'admin_dashboard_view_page' => 'Accès au dashboard administration',
            'admin_users_manage_page' => 'Accès à la gestion des utilisateurs',
            'user_create_page' => 'Accès à la création d’un utilisateur',
            'user_edit_page' => 'Accès à la modification d’un utilisateur',
            'user_delete_page' => 'Accès à la suppression d’un utilisateur',
            'admin_roles_manage_page' => 'Accès à la gestion des rôles',
            'roles_view_page' => 'Accès à la liste des rôles',
            'access_matrix_manage_page' => 'Accès à la matrice des accès',
            'user_logs_view_page' => 'Accès aux logs utilisateurs',
            'audit_logs_view_page' => 'Accès à l’audit détaillé',
            'intelligence_center_view_page' => 'Accès au centre d’intelligence',
            'settings_manage_page' => 'Accès aux paramètres',
            'statuses_manage_page' => 'Accès à la gestion des statuts',
            'categories_manage_page' => 'Accès à la gestion des catégories',

            'admin_users_manage' => 'Gérer les utilisateurs',
            'users_create' => 'Créer un utilisateur',
            'users_edit' => 'Modifier un utilisateur',
            'users_delete' => 'Supprimer un utilisateur',
            'admin_roles_manage' => 'Gérer les rôles',
            'roles_view' => 'Consulter les rôles',
            'access_matrix_manage' => 'Gérer la matrice des accès',
            'user_logs_view' => 'Consulter les logs utilisateurs',
            'audit_logs_view' => 'Consulter l’audit détaillé',
            'intelligence_center_view' => 'Consulter le centre d’intelligence',
            'settings_manage' => 'Gérer les paramètres',
            'statuses_manage' => 'Gérer les statuts',
            'categories_manage' => 'Gérer les catégories',
        ];
    }
}
if (!function_exists('studely_seed_permissions')) {
    function studely_seed_permissions(PDO $pdo, bool $updateLabels = true): array
    {
        $catalog = studely_defined_permissions();

        if (!$catalog) {
            return [
                'inserted' => 0,
                'updated' => 0,
                'total' => 0,
            ];
        }

        $inserted = 0;
        $updated = 0;

        $stmtSelect = $pdo->prepare("SELECT id, label FROM permissions WHERE code = ? LIMIT 1");
        $stmtInsert = $pdo->prepare("
            INSERT INTO permissions (code, label, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmtUpdate = $pdo->prepare("
            UPDATE permissions
            SET label = ?
            WHERE id = ?
        ");

        foreach ($catalog as $code => $label) {
            $stmtSelect->execute([$code]);
            $existing = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $stmtInsert->execute([$code, $label]);
                $inserted++;
                continue;
            }

            if ($updateLabels && (string)($existing['label'] ?? '') !== $label) {
                $stmtUpdate->execute([$label, (int)$existing['id']]);
                $updated++;
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => count($catalog),
        ];
    }
}
if (!function_exists('sl_audit_and_notify_action')) {
    function sl_audit_and_notify_action(
        PDO $pdo,
        int $userId,
        string $action,
        string $module,
        ?string $entityType = null,
        ?int $entityId = null,
        $details = null,
        ?string $notificationType = null,
        ?string $notificationMessage = null,
        ?string $notificationLevel = null,
        ?string $notificationLink = null,
        ?int $targetUserId = null,
        bool $forceNotify = false
    ): void {
        if ($userId <= 0) {
            return;
        }

        if (is_array($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE);
        } elseif ($details !== null) {
            $details = (string)$details;
        }

        if (function_exists('logUserAction')) {
            logUserAction(
                $pdo,
                $userId,
                $action,
                $module,
                $entityType,
                $entityId,
                $details
            );
        }

        $shouldNotify = $forceNotify || sl_is_write_action($action);

        if (!$shouldNotify) {
            return;
        }

        $notificationType = $notificationType !== null && trim($notificationType) !== ''
            ? trim($notificationType)
            : sl_default_notification_type_for_action($action);

        $notificationMessage = $notificationMessage !== null && trim($notificationMessage) !== ''
            ? trim($notificationMessage)
            : sl_default_notification_message($action, $module, $entityType, $entityId);

        $notificationLevel = $notificationLevel !== null && trim($notificationLevel) !== ''
            ? trim($notificationLevel)
            : sl_default_notification_level_for_action($action);

        if (function_exists('createNotification')) {
            createNotification(
                $pdo,
                $notificationType,
                $notificationMessage,
                $notificationLevel,
                $notificationLink,
                $entityType,
                $entityId,
                $userId,
                $targetUserId
            );
        }
    }
}

if (!function_exists('sl_is_write_action')) {
    function sl_is_write_action(string $action): bool
    {
        $action = strtolower(trim($action));

        if ($action === '') {
            return false;
        }

        $readPrefixes = [
            'view',
            'read',
            'list',
            'search',
            'preview',
            'open',
            'display',
            'show',
            'consult',
        ];

        foreach ($readPrefixes as $prefix) {
            if ($action === $prefix || str_starts_with($action, $prefix . '_')) {
                return false;
            }
        }

        return true;
    }
}
if (!function_exists('sl_default_notification_level_for_action')) {
    function sl_default_notification_level_for_action(string $action): string
    {
        $action = strtolower(trim($action));

        if (
            str_contains($action, 'delete')
            || str_contains($action, 'remove')
            || str_contains($action, 'archive')
            || str_contains($action, 'cancel')
            || str_contains($action, 'reject')
        ) {
            return 'warning';
        }

        if (
            str_contains($action, 'error')
            || str_contains($action, 'fail')
            || str_contains($action, 'forbidden')
        ) {
            return 'danger';
        }

        if (
            str_contains($action, 'create')
            || str_contains($action, 'import')
            || str_contains($action, 'execute')
            || str_contains($action, 'validate')
            || str_contains($action, 'approve')
            || str_contains($action, 'restore')
        ) {
            return 'success';
        }

        return 'info';
    }
}
if (!function_exists('sl_default_notification_type_for_action')) {
    function sl_default_notification_type_for_action(string $action): string
    {
        $action = trim($action);
        if ($action === '') {
            return 'system_action';
        }

        return $action;
    }
}
if (!function_exists('sl_default_notification_message')) {
    function sl_default_notification_message(
        string $action,
        string $module,
        ?string $entityType = null,
        ?int $entityId = null
    ): string {
        $action = trim($action);
        $module = trim($module);
        $entityType = trim((string)$entityType);

        $parts = [];

        if ($action !== '') {
            $parts[] = 'Action : ' . $action;
        }

        if ($module !== '') {
            $parts[] = 'Module : ' . $module;
        }

        if ($entityType !== '') {
            $parts[] = 'Entité : ' . $entityType;
        }

        if ($entityId !== null && $entityId > 0) {
            $parts[] = 'ID : ' . $entityId;
        }

        return implode(' | ', $parts);
    }
}
if (!function_exists('sl_audit_action_from_result')) {
    function sl_audit_action_from_result(
        PDO $pdo,
        string $action,
        string $module,
        ?string $entityType,
        ?int $entityId,
        array $context = [],
        ?string $link = null,
        ?int $targetUserId = null,
        bool $forceNotify = false
    ): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        sl_audit_and_notify_action(
            $pdo,
            $userId,
            $action,
            $module,
            $entityType,
            $entityId,
            $context,
            null,
            null,
            null,
            $link,
            $targetUserId,
            $forceNotify
        );
    }
}

