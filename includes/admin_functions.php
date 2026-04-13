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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = currentUserRecord($pdo);
        if (!$user) {
            return false;
        }

        if (currentUserIsAdminLike($pdo)) {
            return true;
        }

        if (
            !tableExists($pdo, 'permissions') ||
            !tableExists($pdo, 'role_permissions') ||
            !tableExists($pdo, 'roles') ||
            !columnExists($pdo, 'users', 'role_id')
        ) {
            return true;
        }

        if (!permissionCodeExists($pdo, $permissionCode)) {
            return false;
        }

        $roleId = (int)($user['role_id'] ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
              AND p.code = ?
        ");
        $stmt->execute([$roleId, $permissionCode]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('currentUserCanAny')) {
    function currentUserCanAny(PDO $pdo, array $permissionCodes): bool
    {
        foreach ($permissionCodes as $code) {
            if (is_string($code) && $code !== '' && currentUserCan($pdo, $code)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('currentUserCanAll')) {
    function currentUserCanAll(PDO $pdo, array $permissionCodes): bool
    {
        foreach ($permissionCodes as $code) {
            if (!is_string($code) || $code === '') {
                continue;
            }

            if (!currentUserCan($pdo, $code)) {
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
    function studelyCanAccess(PDO $pdo, string $accessKey): bool
    {
        $map = studelyAccessMap();
        $codes = $map[$accessKey] ?? [];

        if (!$codes) {
            return false;
        }

        if (currentUserIsAdminLike($pdo)) {
            return true;
        }

        return currentUserCanAny($pdo, $codes);
    }
}

if (!function_exists('studelyEnforceAccess')) {
    function studelyEnforceAccess(PDO $pdo, string $accessKey, ?string $fallbackPermission = null): void
    {
        if (studelyCanAccess($pdo, $accessKey)) {
            return;
        }

        $map = studelyAccessMap();
        $codes = $map[$accessKey] ?? [];
        $fallback = $fallbackPermission ?: (($codes[0] ?? '') ?: 'dashboard_view');

        if (!function_exists('enforcePagePermission')) {
            if (!currentUserCan($pdo, $fallback)) {
                http_response_code(403);
                exit('Accès refusé : permission insuffisante pour cette page.');
            }
            return;
        }

        enforcePagePermission($pdo, $fallback);
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
        ?string $module = null,
        ?string $entityType = null,
        $entityId = null,
        ?string $details = null
    ): void {
        if (!tableExists($pdo, 'user_logs')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'user_logs', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'user_logs', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_logs (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);
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
        ?string $linkUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $createdBy = null
    ): void {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $allowedLevels = ['info', 'success', 'warning', 'danger'];
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'info';
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_by' => $createdBy,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'is_read')) {
            $columns[] = 'is_read';
            $values[] = '?';
            $params[] = 0;
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO notifications (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);
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