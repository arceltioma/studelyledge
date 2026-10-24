<?php

require_once __DIR__ . '/admin_functions.php';

if (!function_exists('pmw_is_logged_in')) {
    function pmw_is_logged_in(): bool
    {
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('pmw_redirect')) {
    function pmw_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('pmw_forbidden')) {
    function pmw_forbidden(string $message = 'Accès refusé.'): void
    {
        http_response_code(403);
        exit($message);
    }
}

if (!function_exists('pmw_store_intended_url')) {
    function pmw_store_intended_url(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['intended_url'] = (string)$_SERVER['REQUEST_URI'];
    }
}

if (!function_exists('pmw_consume_intended_url')) {
    function pmw_consume_intended_url(?string $default = null): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $url = $_SESSION['intended_url']
            ?? $default
            ?? (defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php' : '/');

        unset($_SESSION['intended_url']);

        $fallback = $default ?? (defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php' : '/');

        return safe_redirect_target((string)$url, $fallback);
    }
}

if (!function_exists('ensureAuthenticated')) {
    function ensureAuthenticated(): void
    {
        if (pmw_is_logged_in()) {
            return;
        }

        pmw_store_intended_url();

        $loginUrl = defined('APP_URL') ? APP_URL . 'login.php' : '/login.php';
        pmw_redirect($loginUrl);
    }
}

if (!function_exists('ensureGuestOnly')) {
    function ensureGuestOnly(): void
    {
        if (!pmw_is_logged_in()) {
            return;
        }

        $defaultUrl = defined('APP_URL') ? APP_URL . 'modules/dashboard/dashboard.php' : '/';
        pmw_redirect($defaultUrl);
    }
}

if (!function_exists('enforcePagePermission')) {
    function enforcePagePermission(PDO $pdo, string $permissionCode, bool $redirectIfUnauthorized = false): void
    {
        ensureAuthenticated();

        if (function_exists('studelyCanAccess') && studelyCanAccess($pdo, $permissionCode)) {
            return;
        }

        if ($redirectIfUnauthorized) {
            $target = defined('APP_URL')
                ? APP_URL . 'modules/dashboard/dashboard.php?error=access_denied'
                : '/';
            pmw_redirect($target);
        }

        pmw_forbidden('Accès refusé : permission insuffisante pour cette page.');
    }
}

if (!function_exists('enforceAnyPermission')) {
    function enforceAnyPermission(PDO $pdo, array $permissionCodes, bool $redirectIfUnauthorized = false): void
    {
        ensureAuthenticated();

        foreach ($permissionCodes as $permissionCode) {
            if (!is_string($permissionCode) || trim($permissionCode) === '') {
                continue;
            }

            if (function_exists('studelyCanAccess') && studelyCanAccess($pdo, $permissionCode)) {
                return;
            }
        }

        if ($redirectIfUnauthorized) {
            $target = defined('APP_URL')
                ? APP_URL . 'modules/dashboard/dashboard.php?error=access_denied'
                : '/';
            pmw_redirect($target);
        }

        pmw_forbidden('Accès refusé : aucune des permissions requises n’est disponible.');
    }
}

if (!function_exists('enforceAllPermissions')) {
    function enforceAllPermissions(PDO $pdo, array $permissionCodes, bool $redirectIfUnauthorized = false): void
    {
        ensureAuthenticated();

        foreach ($permissionCodes as $permissionCode) {
            if (!is_string($permissionCode) || trim($permissionCode) === '') {
                continue;
            }

            if (!(function_exists('studelyCanAccess') && studelyCanAccess($pdo, $permissionCode))) {
                if ($redirectIfUnauthorized) {
                    $target = defined('APP_URL')
                        ? APP_URL . 'modules/dashboard/dashboard.php?error=access_denied'
                        : '/';
                    pmw_redirect($target);
                }

                pmw_forbidden('Accès refusé : toutes les permissions requises ne sont pas réunies.');
            }
        }
    }
}

if (!function_exists('middlewareRequireAuthAndPermission')) {
    function middlewareRequireAuthAndPermission(PDO $pdo, string $permissionCode): void
    {
        enforcePagePermission($pdo, $permissionCode);
    }
}

if (!function_exists('middlewareRequireAuthOnly')) {
    function middlewareRequireAuthOnly(): void
    {
        ensureAuthenticated();
    }
}

if (!function_exists('middlewareGuestOnly')) {
    function middlewareGuestOnly(): void
    {
        ensureGuestOnly();
    }
}

if (!function_exists('studely_get_current_relative_script')) {
    function studely_get_current_relative_script(): string
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($script === '') {
            $script = str_replace('\\', '/', (string)($_SERVER['PHP_SELF'] ?? ''));
        }

        $script = ltrim($script, '/');

        $projectRoot = '';
        if (defined('APP_URL')) {
            $projectRoot = trim((string)parse_url(APP_URL, PHP_URL_PATH), '/');
        }

        if ($projectRoot !== '' && str_starts_with($script, $projectRoot . '/')) {
            $script = substr($script, strlen($projectRoot) + 1);
        }

        return $script;
    }
}

if (!function_exists('studely_page_permission_map')) {
    function studely_page_permission_map(): array
    {
        return [

            // Dashboard / analytics / search
            'modules/dashboard/dashboard.php' => 'dashboard_view_page',
            'modules/dashboard/accounting_control_dashboard.php' => 'dashboard_view_page',
            'modules/dashboard/rebuild_balances.php' => 'accounting_balance_audit_page',
            'modules/analytics/revenue_analysis.php' => 'analytics_view_page',
            'modules/search/global_search.php' => 'global_search_view_page',

            // Clients
            'modules/clients/clients_list.php' => 'clients_view_page',
            'modules/clients/client_view.php' => 'client_view_page',
            'modules/clients/client_create.php' => 'client_create_page',
            'modules/clients/client_edit.php' => 'client_edit_page',
            'modules/clients/clients_archive.php' => 'clients_archive_page',
            'modules/clients/client_delete.php' => 'clients_delete_page',
            'modules/clients/clients_delete.php' => 'clients_delete_page',
            'modules/clients/client_accounts.php' => 'client_accounts_view_page',
            'modules/clients/client_account_view.php' => 'client_account_view_page',
            'modules/clients/client_timeline.php' => 'client_timeline_view_page',
            'modules/clients/import_clients_csv.php' => 'clients_import_page',

            // Operations
            'modules/operations/operations_list.php' => 'operations_view_page',
            'modules/operations/operation_view.php' => 'operation_view_page',
            'modules/operations/operation_create.php' => 'operation_create_page',
            'modules/operations/operation_edit.php' => 'operation_edit_page',
            'modules/operations/operation_delete.php' => 'operation_delete_page',
            'modules/operations/run_monthly_client_operations.php' => 'operations_monthly_run_page',

            // Manual actions
            'modules/manual_actions/manual_operation.php' => 'manual_actions_create_page',
            'modules/manual_actions/bulk_fees.php' => 'manual_actions_create_page',

            // Imports
            'modules/imports/index.php' => 'imports_upload_page',
            'modules/imports/import_upload.php' => 'imports_upload_page',
            'modules/imports/import_preview.php' => 'imports_preview_page',
            'modules/imports/import_validate.php' => 'imports_validate_page',
            'modules/imports/import_journal.php' => 'imports_journal_page',
            'modules/imports/import_mapping.php' => 'imports_mapping_page',
            'modules/imports/rejected_rows.php' => 'imports_rejected_rows_page',
            'modules/imports/correct_rejected_row.php' => 'imports_correct_rejected_row_page',

            // Monthly payments
            'modules/monthly_payments/monthly_runs_list.php' => 'monthly_runs_list_page',
            'modules/monthly_payments/monthly_run_view.php' => 'monthly_run_view_page',
            'modules/monthly_payments/monthly_run_execute.php' => 'monthly_run_execute_page',
            'modules/monthly_payments/monthly_run_cancel.php' => 'monthly_run_cancel_page',
            'modules/monthly_payments/monthly_payments_import.php' => 'monthly_payments_import_page',
            'modules/monthly_payments/monthly_payments_preview.php' => 'monthly_payments_preview_page',
            'modules/monthly_payments/monthly_payments_validate.php' => 'monthly_payments_validate_page',
            'modules/monthly_payments/import_monthly_payments.php' => 'monthly_payments_import_page',
            'modules/monthly_payments/monthly_import_create.php' => 'monthly_payments_import_page',
            'modules/monthly_payments/monthly_import_preview.php' => 'monthly_payments_preview_page',
            'modules/monthly_payments/monthly_import_validate.php' => 'monthly_payments_validate_page',
            'modules/monthly_payments/monthly_payments_run.php' => 'monthly_run_execute_page',

            // Pending debits
            'modules/pending_debits/pending_debits_list.php' => 'pending_debits_view_page',
            'modules/pending_debits/pending_debit_view.php' => 'pending_debit_view_page',
            'modules/pending_debits/pending_debit_edit.php' => 'pending_debit_edit_page',
            'modules/pending_debits/pending_debit_execute.php' => 'pending_debit_execute_page',
            'modules/pending_debits/pending_debit_cancel.php' => 'pending_debit_cancel_page',

            // Treasury
            'modules/treasury/index.php' => 'treasury_view_page',
            'modules/treasury/treasury_view.php' => 'treasury_view_detail_page',
            'modules/treasury/treasury_create.php' => 'treasury_create_page',
            'modules/treasury/treasury_edit.php' => 'treasury_edit_page',
            'modules/treasury/treasury_archive.php' => 'treasury_archive_page',
            'modules/treasury/import_treasury_csv.php' => 'treasury_import_page',
            'modules/treasury/bank_accounts.php' => 'bank_accounts_view_page',
            'modules/treasury/service_accounts.php' => 'treasury_service_accounts_page',

            // Service accounts
            'modules/service_accounts/index.php' => 'service_accounts_manage_page',
            'modules/service_accounts/view.php' => 'service_accounts_view_page',
            'modules/service_accounts/create.php' => 'service_accounts_create_page',
            'modules/service_accounts/edit.php' => 'service_accounts_edit_page',
            'modules/service_accounts/archive.php' => 'service_accounts_archive_page',
            'modules/service_accounts/import_service_accounts_csv.php' => 'service_accounts_import_page',

            // Statements
            'modules/statements/index.php' => 'statements_view_page',
            'modules/statements/account_statements.php' => 'account_statements_view_page',
            'modules/statements/client_statement.php' => 'client_statement_view_page',
            'modules/statements/client_profiles.php' => 'client_profiles_view_page',
            'modules/statements/bulk_statement_export.php' => 'bulk_statement_export_page',
            'modules/statements/generate_statement_pdf.php' => 'generate_statement_pdf_page',
            'modules/statements/generate_bulk_pdf.php' => 'generate_bulk_pdf_page',
            'modules/statements/export_file.php' => 'statements_view_page',

            // Notifications / support
            'modules/notifications/notifications.php' => 'notifications_view_page',
            'modules/support/support_requests.php' => 'support_requests_view_page',
            'modules/support/ask_question.php' => 'support_request_create_page',
            'modules/support/report_bug.php' => 'support_request_create_page',
            'modules/support/request_access.php' => 'support_request_create_page',

            // Admin functional
            'modules/admin_functional/dashboard.php' => 'admin_functional_dashboard_view_page',
            'modules/admin_functional/manage_services.php' => 'manage_services_page',
            'modules/admin_functional/create_service.php' => 'create_service_page',
            'modules/admin_functional/edit_service.php' => 'edit_service_page',
            'modules/admin_functional/delete_service.php' => 'delete_service_page',
            'modules/admin_functional/manage_operation_types.php' => 'manage_operation_types_page',
            'modules/admin_functional/create_operation_type.php' => 'create_operation_type_page',
            'modules/admin_functional/edit_operation_type.php' => 'edit_operation_type_page',
            'modules/admin_functional/delete_operation_type.php' => 'delete_operation_type_page',
            'modules/admin_functional/manage_accounts.php' => 'manage_accounts_page',
            'modules/admin_functional/manage_accounting_rules.php' => 'manage_accounting_rules_page',
            'modules/admin_functional/accounting_rule_create.php' => 'accounting_rule_create_page',
            'modules/admin_functional/accounting_rule_edit.php' => 'accounting_rule_edit_page',
            'modules/admin_functional/accounting_rule_delete.php' => 'accounting_rule_delete_page',
            'modules/admin_functional/accounting_rule_view.php' => 'accounting_rule_view_page',
            'modules/admin_functional/accounting_balance_audit.php' => 'accounting_balance_audit_page',
            'modules/admin_functional/catalogs.php' => 'catalogs_manage_page',
            'modules/admin_functional/archive_client.php' => 'clients_archive_page',

            // Admin technique
            'modules/admin/dashboard_admin.php' => 'admin_dashboard_view_page',
            'modules/admin/access_matrix.php' => 'access_matrix_manage_page',
            'modules/admin/admin_roles.php' => 'admin_roles_manage_page',
            'modules/admin/roles.php' => 'roles_view_page',
            'modules/admin/users.php' => 'admin_users_manage_page',
            'modules/admin/user_create.php' => 'user_create_page',
            'modules/admin/user_edit.php' => 'user_edit_page',
            'modules/admin/user_delete.php' => 'user_delete_page',
            'modules/admin/user_logs.php' => 'user_logs_view_page',
            'modules/admin/audit_logs.php' => 'audit_logs_view_page',
            'modules/admin/intelligence_center.php' => 'intelligence_center_view_page',
            'modules/admin/settings.php' => 'settings_manage_page',
            'modules/admin/statuses.php' => 'statuses_manage_page',
            'modules/admin/categories.php' => 'categories_manage_page',
            'modules/admin/seed_permissions.php' => 'access_matrix_manage_page',
        ];
    }
}

if (!function_exists('studely_get_page_permission_for_current_script')) {
    function studely_get_page_permission_for_current_script(): ?string
    {
        $relativeScript = studely_get_current_relative_script();
        $map = studely_page_permission_map();

        return $map[$relativeScript] ?? null;
    }
}

if (!function_exists('studelyRefreshAuthenticatedUser')) {
    /**
     * Revalide l'utilisateur connecté à chaque requête : un compte désactivé ou supprimé
     * est déconnecté immédiatement, un changement de rôle est pris en compte sans reconnexion.
     */
    function studelyRefreshAuthenticatedUser(PDO $pdo): void
    {
        static $done = false;
        if ($done || !pmw_is_logged_in()) {
            return;
        }
        $done = true;

        $stmt = $pdo->prepare("SELECT id, username, role_id, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            $_SESSION = [];
            session_regenerate_id(true);
            pmw_redirect((defined('APP_URL') ? APP_URL : '/') . 'login.php?disabled=1');
        }

        $roleId = isset($user['role_id']) ? (int)$user['role_id'] : 0;
        if ($roleId !== (int)($_SESSION['role_id'] ?? 0)) {
            $_SESSION['role_id'] = $roleId;

            $stmtRole = $pdo->prepare("SELECT code, label FROM roles WHERE id = ? LIMIT 1");
            $stmtRole->execute([$roleId]);
            $role = $stmtRole->fetch(PDO::FETCH_ASSOC) ?: [];

            $_SESSION['role_name'] = (string)($role['label'] ?? '');
            $_SESSION['role'] = (string)($role['code'] ?? '');
        }

        $_SESSION['username'] = (string)($user['username'] ?? '');
    }
}

if (!function_exists('studelyIsSuperAdmin')) {
    function studelyIsSuperAdmin(): bool
    {
        return pmw_is_logged_in() && (int)($_SESSION['role_id'] ?? 0) === 1;
    }
}

if (!function_exists('studelyGuardUserRoleChange')) {
    /**
     * Empêche l'escalade de privilèges : seul un super administrateur peut attribuer le rôle
     * super administrateur ou modifier / archiver un compte super administrateur.
     */
    function studelyGuardUserRoleChange(?int $targetRoleId, ?int $currentTargetRoleId = null): void
    {
        if (studelyIsSuperAdmin()) {
            return;
        }

        if ($targetRoleId === 1 || $currentTargetRoleId === 1) {
            throw new RuntimeException('Seul un super administrateur peut gérer un compte super administrateur.');
        }
    }
}

if (!function_exists('studelyValidatePasswordStrength')) {
    function studelyValidatePasswordStrength(string $password): void
    {
        if (mb_strlen($password, 'UTF-8') < 10) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 10 caractères.');
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new RuntimeException('Le mot de passe doit contenir au moins une lettre et un chiffre.');
        }
    }
}

if (!function_exists('studelyEnforceCurrentPageAccess')) {
    function studelyEnforceCurrentPageAccess(PDO $pdo): void
    {
        ensureAuthenticated();
        studelyRefreshAuthenticatedUser($pdo);

        $permissionCode = studely_get_page_permission_for_current_script();

        if ($permissionCode === null || $permissionCode === '') {
            return;
        }

        if (function_exists('studelyEnforceAccess')) {
            studelyEnforceAccess($pdo, $permissionCode);
            return;
        }

        if (function_exists('enforcePagePermission')) {
            enforcePagePermission($pdo, $permissionCode);
        }
    }
}

if (!function_exists('studelyEnforceActionAccess')) {
    function studelyEnforceActionAccess(PDO $pdo, string $permissionCode): void
    {
        $permissionCode = trim($permissionCode);
        if ($permissionCode === '') {
            return;
        }

        if (function_exists('studelyEnforceAccess')) {
            studelyEnforceAccess($pdo, $permissionCode);
            return;
        }

        if (function_exists('enforcePagePermission')) {
            enforcePagePermission($pdo, $permissionCode);
        }
    }
}