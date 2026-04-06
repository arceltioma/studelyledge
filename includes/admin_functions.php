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

            /* LOT 2 additif */
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

            /* LOT 2 additif */
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

if (!function_exists('resolveAccountingOperationV2')) {
    function resolveAccountingOperationV2(PDO $pdo, array $payload): array
    {
        $operationTypeCode = sl_normalize_code((string)($payload['operation_type_code'] ?? ''));
        $serviceCode = sl_normalize_code((string)($payload['service_code'] ?? ''));
        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : null;
        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $linkedBankAccountId = isset($payload['linked_bank_account_id']) ? (int)$payload['linked_bank_account_id'] : null;
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

        $linkedBankAccount = null;
        if ($linkedBankAccountId > 0 && tableExists($pdo, 'bank_accounts')) {
            $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ? LIMIT 1");
            $stmt->execute([$linkedBankAccountId]);
            $linkedBankAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $clientAccount = (string)($clientContext['generated_client_account'] ?? '');
        $clientTreasury = (string)($clientContext['treasury_account_code'] ?? '');

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
                    $debit = $clientTreasury;
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
                    $debit = $clientAccount;
                    $credit = $clientTreasury;
                    break;

                case 'REGULARISATION::POSITIVE':
                    if (!$clientContext) {
                        throw new RuntimeException('Client obligatoire pour une régularisation positive.');
                    }
                    $debit = $clientTreasury;
                    $credit = $clientAccount;
                    break;

                case 'REGULARISATION::NEGATIVE':
                    if (!$clientContext) {
                        throw new RuntimeException('Client obligatoire pour une régularisation négative.');
                    }
                    $debit = $clientAccount;
                    $credit = $clientTreasury;
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

        if ($debit === '' || $credit === '') {
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

        $duplicate = sl_find_duplicate_operation($pdo, (string)$resolved['operation_hash'], $excludeOperationId);
        if ($duplicate) {
            throw new RuntimeException('Doublon détecté : une opération strictement identique existe déjà.');
        }

        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $bankAccountId = null;

        if (!empty($resolved['linked_bank_account']['id'])) {
            $bankAccountId = (int)$resolved['linked_bank_account']['id'];
        } elseif ($clientId) {
            $bankAccount = findPrimaryBankAccountForClient($pdo, $clientId);
            $bankAccountId = $bankAccount['id'] ?? null;
        }

        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : null;
        $operationTypeId = isset($payload['operation_type_id']) ? (int)$payload['operation_type_id'] : null;

        if ($excludeOperationId !== null && $excludeOperationId > 0) {
            $updateMap = [
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'operation_type_id' => $operationTypeId,
                'linked_bank_account_id' => $bankAccountId,
                'bank_account_id' => $bankAccountId,
                'operation_date' => $payload['operation_date'] ?? date('Y-m-d'),
                'amount' => (float)($payload['amount'] ?? 0),
                'currency_code' => $payload['currency_code'] ?? null,
                'operation_type_code' => $payload['operation_type_code'] ?? null,
                'label' => $payload['label'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'source_type' => $payload['source_type'] ?? null,
                'debit_account_code' => $resolved['debit_account_code'],
                'credit_account_code' => $resolved['credit_account_code'],
                'service_account_code' => $resolved['analytic_account']['account_code'] ?? null,
                'operation_hash' => $resolved['operation_hash'],
                'is_manual_accounting' => (int)$resolved['is_manual_accounting'],
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
            'operation_date' => $payload['operation_date'] ?? date('Y-m-d'),
            'amount' => (float)($payload['amount'] ?? 0),
            'currency_code' => $payload['currency_code'] ?? null,
            'operation_type_code' => $payload['operation_type_code'] ?? null,
            'operation_kind' => $payload['operation_kind'] ?? null,
            'label' => $payload['label'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'source_type' => $payload['source_type'] ?? null,
            'debit_account_code' => $resolved['debit_account_code'],
            'credit_account_code' => $resolved['credit_account_code'],
            'service_account_code' => $resolved['analytic_account']['account_code'] ?? null,
            'operation_hash' => $resolved['operation_hash'],
            'is_manual_accounting' => (int)$resolved['is_manual_accounting'],
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
        if (columnExists($pdo, 'operations', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        $stmt = $pdo->prepare("
            INSERT INTO operations (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        $operationId = (int)$pdo->lastInsertId();

        if (function_exists('recomputeAllBalances')) {
            recomputeAllBalances($pdo);
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

if (!function_exists('sl_build_notification_link_for_entity')) {
    function sl_build_notification_link_for_entity(string $entityType, int $entityId): ?string
    {
        $entityType = strtolower(trim($entityType));
        $entityId = (int)$entityId;

        if ($entityId <= 0 || !defined('APP_URL')) {
            return null;
        }

        return match ($entityType) {
            'client' => APP_URL . 'modules/clients/client_view.php?id=' . $entityId,
            'operation' => APP_URL . 'modules/operations/operation_view.php?id=' . $entityId,
            'treasury_account' => APP_URL . 'modules/treasury/treasury_view.php?id=' . $entityId,
            'service_account' => APP_URL . 'modules/service_accounts/view.php?id=' . $entityId,
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
        ?int $createdBy = null
    ): void {
        if (!function_exists('createNotification')) {
            return;
        }

        $linkUrl = null;
        if ($entityType !== null && $entityId !== null && function_exists('sl_build_notification_link_for_entity')) {
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

if (!function_exists('sl_get_operation_rules_summary')) {
    function sl_get_operation_rules_summary(
        string $operationTypeCode,
        string $serviceCode,
        ?string $commercialCountry = null,
        ?string $destinationCountry = null
    ): array {
        $operationTypeCode = sl_normalize_code($operationTypeCode);
        $serviceCode = sl_normalize_code($serviceCode);

        $manual = sl_is_manual_accounting_case($operationTypeCode, $serviceCode);
        $isInternal = ($operationTypeCode === 'VIREMENT' && $serviceCode === 'INTERNE');

        $requiresClient = !$isInternal;
        $requiresLinkedBank =
            ($operationTypeCode === 'VERSEMENT')
            || ($operationTypeCode === 'REGULARISATION')
            || ($operationTypeCode === 'VIREMENT' && $serviceCode !== 'INTERNE');

        $searchText = '—';

        if ($operationTypeCode === 'FRAIS_SERVICE' && $serviceCode === 'AVI') {
            $searchText = 'AVI + ' . ($destinationCountry ?: '?') . ' + ' . ($commercialCountry ?: '?');
        } elseif ($operationTypeCode === 'FRAIS_SERVICE' && $serviceCode === 'ATS') {
            $searchText = 'ATS + ' . ($commercialCountry ?: '?');
        } elseif ($operationTypeCode === 'FRAIS_GESTION' && $serviceCode === 'GESTION') {
            $searchText = 'GESTION + ' . ($commercialCountry ?: '?');
        } elseif ($operationTypeCode === 'COMMISSION_DE_TRANSFERT' && $serviceCode === 'COMMISSION_DE_TRANSFERT') {
            $searchText = 'TRANSFERT + ' . ($commercialCountry ?: '?');
        } elseif ($operationTypeCode === 'CA_PLACEMENT' && $serviceCode === 'CA_PLACEMENT') {
            $searchText = 'CA PLACEMENT + ' . ($commercialCountry ?: '?');
        }

        return [
            'operation_type_code' => $operationTypeCode,
            'service_code' => $serviceCode,
            'requires_client' => $requiresClient,
            'requires_linked_bank' => $requiresLinkedBank,
            'requires_manual_accounts' => $manual,
            'service_account_search_text' => $searchText,
        ];
    }
}

if (!function_exists('sl_get_operation_anomalies')) {
    function sl_get_operation_anomalies(array $payload): array
    {
        $issues = [];

        $amount = (float)($payload['amount'] ?? 0);
        $currency = trim((string)($payload['currency_code'] ?? ''));
        $clientId = (int)($payload['client_id'] ?? 0);
        $typeCode = sl_normalize_code((string)($payload['operation_type_code'] ?? ''));
        $serviceCode = sl_normalize_code((string)($payload['service_code'] ?? ''));
        $manualDebit = trim((string)($payload['manual_debit_account_code'] ?? ''));
        $manualCredit = trim((string)($payload['manual_credit_account_code'] ?? ''));

        if ($amount <= 0) {
            $issues[] = ['level' => 'danger', 'message' => 'Montant nul ou négatif.'];
        }

        if ($currency === '') {
            $issues[] = ['level' => 'warning', 'message' => 'Devise absente.'];
        }

        if (!($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE') && $clientId <= 0) {
            $issues[] = ['level' => 'danger', 'message' => 'Client obligatoire manquant.'];
        }

        if ($typeCode === '' || $serviceCode === '') {
            $issues[] = ['level' => 'danger', 'message' => 'Type opération ou service manquant.'];
        }

        if ($typeCode !== '' && $serviceCode !== '' && !sl_service_allowed_for_type($typeCode, $serviceCode)) {
            $issues[] = ['level' => 'danger', 'message' => 'Service incompatible avec le type d’opération.'];
        }

        if (sl_is_manual_accounting_case($typeCode, $serviceCode)) {
            if ($manualDebit === '' || $manualCredit === '') {
                $issues[] = ['level' => 'danger', 'message' => 'Comptes source / destination manquants pour un cas manuel.'];
            }
        }

        if ($manualDebit !== '' && $manualCredit !== '' && $manualDebit === $manualCredit) {
            $issues[] = ['level' => 'warning', 'message' => 'Compte débité et crédité identiques.'];
        }

        if (empty($issues)) {
            $issues[] = ['level' => 'success', 'message' => 'Aucune anomalie bloquante détectée.'];
        }

        return $issues;
    }
}

if (!function_exists('sl_get_import_mapping_suggestions')) {
    function sl_get_import_mapping_suggestions(array $headers): array
    {
        $map = [];
        $normalizedHeaders = [];

        foreach ($headers as $header) {
            $normalizedHeaders[(string)$header] = sl_normalize_match_text((string)$header);
        }

        $dictionary = [
            'operation_date' => ['DATE', 'DATE OPERATION', 'DATE_OPERATION'],
            'amount' => ['MONTANT', 'AMOUNT', 'SOMME', 'VALEUR'],
            'currency_code' => ['DEVISE', 'CURRENCY', 'CURRENCY CODE'],
            'client_code' => ['CLIENT', 'CODE CLIENT', 'CLIENT CODE'],
            'operation_type' => ['TYPE OPERATION', 'TYPE', 'OPERATION TYPE'],
            'service' => ['SERVICE', 'TYPE SERVICE'],
            'reference' => ['REFERENCE', 'LIBELLE', 'INTITULE'],
            'label' => ['LABEL', 'LIBELLE OPERATION'],
            'notes' => ['NOTE', 'MOTIF', 'COMMENTAIRE'],
            'source_account_code' => ['COMPTE SOURCE', 'SOURCE ACCOUNT', 'DEBIT'],
            'destination_account_code' => ['COMPTE DESTINATION', 'DESTINATION ACCOUNT', 'CREDIT'],
            'linked_bank_account_id' => ['COMPTE BANCAIRE LIE', 'BANK ACCOUNT', 'LINKED BANK'],
            'account_code' => ['CODE COMPTE', 'ACCOUNT CODE'],
            'account_label' => ['INTITULE COMPTE', 'ACCOUNT LABEL'],
            'commercial_country_label' => ['PAYS COMMERCIAL', 'COMMERCIAL COUNTRY'],
            'destination_country_label' => ['PAYS DESTINATION', 'DESTINATION COUNTRY'],
        ];

        foreach ($dictionary as $target => $aliases) {
            foreach ($normalizedHeaders as $original => $normalized) {
                foreach ($aliases as $alias) {
                    if ($normalized === sl_normalize_match_text($alias)) {
                        $map[$target] = $original;
                        continue 3;
                    }
                }
            }
        }

        return $map;
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