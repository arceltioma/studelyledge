<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'imports_validate');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}

if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
    exit('Jeton CSRF invalide.');
}

if (!function_exists('sl_import_validate_create_notification')) {
    function sl_import_validate_create_notification(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $linkUrl = null,
        ?string $entityType = 'import',
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
            'is_read' => 0,
            'created_by' => $createdBy,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (columnExists($pdo, 'notifications', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NULL';
        }

        if (!$columns) {
            return;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if (!function_exists('sl_import_find_by_code')) {
    function sl_import_find_by_code(array $rows, string $code, string $key = 'code'): ?array
    {
        $needle = sl_normalize_code($code);
        foreach ($rows as $row) {
            if (sl_normalize_code((string)($row[$key] ?? '')) === $needle) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('sl_import_find_service_by_code_and_type')) {
    function sl_import_find_service_by_code_and_type(array $services, string $serviceCode, string $typeCode): ?array
    {
        $serviceNeedle = sl_normalize_code($serviceCode);
        $typeNeedle = sl_normalize_code($typeCode);

        foreach ($services as $service) {
            if (
                sl_normalize_code((string)($service['code'] ?? '')) === $serviceNeedle
                && sl_normalize_code((string)($service['operation_type_code'] ?? '')) === $typeNeedle
            ) {
                return $service;
            }
        }

        return null;
    }
}

if (!function_exists('sl_import_find_treasury_code_by_id')) {
    function sl_import_find_treasury_code_by_id(array $treasuryAccounts, ?int $id): string
    {
        if (!$id) {
            return '';
        }

        foreach ($treasuryAccounts as $row) {
            if ((int)($row['id'] ?? 0) === (int)$id) {
                return (string)($row['account_code'] ?? '');
            }
        }

        return '';
    }
}

$previewRows = $_SESSION['statement_import_preview']['rows'] ?? [];
if (!$previewRows) {
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}

$selectedRows = array_map('intval', $_POST['selected_rows'] ?? []);
if (!$selectedRows) {
    $_SESSION['import_validate_flash'] = [
        'type' => 'error',
        'message' => 'Aucune ligne sélectionnée pour validation.'
    ];
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1) = 1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT
            rs.*,
            rot.code AS operation_type_code,
            rot.label AS operation_type_label
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        WHERE COALESCE(rs.is_active,1) = 1
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1) = 1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name, generated_client_account, initial_treasury_account_id
        FROM clients
        WHERE COALESCE(is_active,1) = 1
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$importedCount = 0;
$rejectedCount = 0;
$report = [];
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $pdo->beginTransaction();

    foreach ($selectedRows as $rowIndex) {
        $row = $previewRows[$rowIndex] ?? null;

        if (!$row || !is_array($row)) {
            $rejectedCount++;
            $report[] = [
                'row_no' => $rowIndex,
                'status' => 'rejected',
                'reason' => 'Ligne de prévisualisation introuvable.'
            ];
            continue;
        }

        try {
            $operationDate = trim((string)($_POST['row_operation_date'][$rowIndex] ?? ($row['operation_date'] ?? '')));
            $amount = (float)($_POST['row_amount'][$rowIndex] ?? ($row['amount'] ?? 0));
            $currencyCode = trim((string)($_POST['row_currency_code'][$rowIndex] ?? ($row['currency_code'] ?? 'EUR')));
            $clientId = ($_POST['row_client_id'][$rowIndex] ?? '') !== '' ? (int)$_POST['row_client_id'][$rowIndex] : null;
            $operationTypeCodeRaw = trim((string)($_POST['row_operation_type_code'][$rowIndex] ?? ($row['operation_type_code'] ?? '')));
            $serviceCodeRaw = trim((string)($_POST['row_service_code'][$rowIndex] ?? ($row['service_code'] ?? '')));
            $linkedBankAccountId = ($_POST['row_linked_bank_account_id'][$rowIndex] ?? '') !== '' ? (int)$_POST['row_linked_bank_account_id'][$rowIndex] : null;
            $reference = trim((string)($_POST['row_reference'][$rowIndex] ?? ($row['reference'] ?? '')));
            $notes = trim((string)($_POST['row_notes'][$rowIndex] ?? ($row['notes'] ?? '')));
            $manualDebit = trim((string)($_POST['row_manual_debit_account_code'][$rowIndex] ?? ($row['manual_debit_account_code'] ?? '')));
            $manualCredit = trim((string)($_POST['row_manual_credit_account_code'][$rowIndex] ?? ($row['manual_credit_account_code'] ?? '')));
            $sourceTreasuryId = ($_POST['row_source_treasury_account_id'][$rowIndex] ?? '') !== '' ? (int)$_POST['row_source_treasury_account_id'][$rowIndex] : null;
            $targetTreasuryId = ($_POST['row_target_treasury_account_id'][$rowIndex] ?? '') !== '' ? (int)$_POST['row_target_treasury_account_id'][$rowIndex] : null;

            if ($operationDate === '') {
                throw new RuntimeException('Date manquante.');
            }

            if ($amount <= 0) {
                throw new RuntimeException('Montant invalide.');
            }

            if ($clientId !== null && $clientId > 0) {
                sl_assert_client_operation_allowed($pdo, $clientId);
            }

            $selectedType = sl_import_find_by_code($operationTypes, $operationTypeCodeRaw, 'code');
            if (!$selectedType) {
                throw new RuntimeException('Type d’opération introuvable.');
            }

            $typeCode = sl_normalize_code((string)($selectedType['code'] ?? ''));
            $selectedService = sl_import_find_service_by_code_and_type($services, $serviceCodeRaw, $typeCode);
            if (!$selectedService) {
                throw new RuntimeException('Service introuvable ou incohérent avec le type.');
            }

            $serviceCode = sl_normalize_code((string)($selectedService['code'] ?? ''));

            if (!sl_service_allowed_for_type($typeCode, $serviceCode)) {
                throw new RuntimeException('Service interdit pour le type choisi.');
            }

            $sourceTreasuryCode = sl_import_find_treasury_code_by_id($treasuryAccounts, $sourceTreasuryId);
            $targetTreasuryCode = sl_import_find_treasury_code_by_id($treasuryAccounts, $targetTreasuryId);

            $defaultLabel = trim(
                (string)($row['label'] ?? '') !== ''
                    ? (string)$row['label']
                    : ((string)($selectedType['label'] ?? '') . ' - ' . (string)($selectedService['label'] ?? ''))
            );

            $payload = [
                'operation_date' => $operationDate,
                'amount' => $amount,
                'currency_code' => $currencyCode !== '' ? $currencyCode : 'EUR',
                'client_id' => $clientId,
                'service_id' => (int)($selectedService['id'] ?? 0),
                'service_code' => $serviceCode,
                'operation_type_id' => (int)($selectedType['id'] ?? 0),
                'operation_type_code' => $typeCode,
                'linked_bank_account_id' => $linkedBankAccountId,
                'reference' => $reference !== '' ? $reference : null,
                'label' => $defaultLabel,
                'notes' => $notes !== '' ? $notes : null,
                'source_type' => 'import_statement',
                'operation_kind' => 'import',
                'source_treasury_code' => $sourceTreasuryCode,
                'target_treasury_code' => $targetTreasuryCode,
                'manual_debit_account_code' => $manualDebit,
                'manual_credit_account_code' => $manualCredit,
            ];

            $operationId = createOperationWithAccountingV2($pdo, $payload);

            if (function_exists('logUserAction') && $userId > 0) {
                logUserAction(
                    $pdo,
                    $userId,
                    'import_operation',
                    'imports',
                    'operation',
                    $operationId,
                    'Validation d’une ligne importée depuis import_preview'
                );
            }

            $importedCount++;
            $report[] = [
                'row_no' => $row['row_no'] ?? $rowIndex,
                'status' => 'imported',
                'reason' => 'Importée avec succès.',
                'operation_id' => $operationId,
            ];
        } catch (Throwable $rowError) {
            $rejectedCount++;
            $report[] = [
                'row_no' => $row['row_no'] ?? $rowIndex,
                'status' => 'rejected',
                'reason' => $rowError->getMessage(),
            ];
        }
    }

    if (function_exists('logUserAction') && $userId > 0) {
        logUserAction(
            $pdo,
            $userId,
            'validate_import_batch',
            'imports',
            'import',
            null,
            sprintf(
                'Validation import terminée : %d ligne(s) importée(s), %d rejetée(s), %d sélectionnée(s)',
                $importedCount,
                $rejectedCount,
                count($selectedRows)
            )
        );
    }

    $pdo->commit();

    sl_import_validate_create_notification(
        $pdo,
        'import_validation',
        sprintf(
            'Validation import terminée : %d ligne(s) importée(s), %d rejetée(s)',
            $importedCount,
            $rejectedCount
        ),
        $rejectedCount > 0 ? 'warning' : 'success',
        APP_URL . 'modules/imports/import_journal.php',
        'import',
        null,
        $userId > 0 ? $userId : null
    );

    $_SESSION['import_validate_flash'] = [
        'type' => $rejectedCount > 0 ? 'warning' : 'success',
        'message' => "Import terminé. {$importedCount} ligne(s) importée(s), {$rejectedCount} rejetée(s)."
    ];

    $_SESSION['import_validate_report'] = $report;

    unset($_SESSION['statement_import_preview']);

    header('Location: ' . APP_URL . 'modules/imports/import_journal.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (function_exists('logUserAction') && $userId > 0) {
        logUserAction(
            $pdo,
            $userId,
            'validate_import_batch_failed',
            'imports',
            'import',
            null,
            'Erreur pendant la validation de l’import : ' . $e->getMessage()
        );
    }

    sl_import_validate_create_notification(
        $pdo,
        'import_validation_failed',
        'Erreur pendant la validation de l’import : ' . $e->getMessage(),
        'danger',
        APP_URL . 'modules/imports/import_preview.php',
        'import',
        null,
        $userId > 0 ? $userId : null
    );

    $_SESSION['import_validate_flash'] = [
        'type' => 'error',
        'message' => 'Erreur pendant la validation de l’import : ' . $e->getMessage()
    ];

    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}