<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_validate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['statement_import_preview']['rows'])) {
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}

if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
    $_SESSION['flash_error'] = 'Jeton CSRF invalide.';
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}

$rows = $_SESSION['statement_import_preview']['rows'];
$selectedRows = array_map('intval', $_POST['selected_rows'] ?? []);
$rowClientIds = $_POST['row_client_id'] ?? [];
$rowOperationTypeCodes = $_POST['row_operation_type_code'] ?? [];
$rowServiceCodes = $_POST['row_service_code'] ?? [];
$rowTreasuryIds = $_POST['row_treasury_account_id'] ?? [];

$imported = 0;
$rejected = 0;
$messages = [];

try {
    $pdo->beginTransaction();

    $importId = null;
    if (tableExists($pdo, 'imports')) {
        $stmtImport = $pdo->prepare("
            INSERT INTO imports (file_name, status, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmtImport->execute([
            $_SESSION['statement_import_preview']['file_name'] ?? 'statement.csv',
            'processing'
        ]);
        $importId = (int)$pdo->lastInsertId();
    }

    foreach ($selectedRows as $index) {
        if (!isset($rows[$index])) {
            continue;
        }

        $row = $rows[$index];

        try {
            $clientId = isset($rowClientIds[$index]) && $rowClientIds[$index] !== '' ? (int)$rowClientIds[$index] : null;
            $operationTypeCode = trim((string)($rowOperationTypeCodes[$index] ?? $row['operation_type_code'] ?? ''));
            $serviceCode = trim((string)($rowServiceCodes[$index] ?? $row['service_code'] ?? ''));
            $treasuryId = isset($rowTreasuryIds[$index]) && $rowTreasuryIds[$index] !== '' ? (int)$rowTreasuryIds[$index] : (isset($row['treasury_account_id']) ? (int)$row['treasury_account_id'] : null);

            if ($operationTypeCode === '') {
                throw new RuntimeException('Type d’opération vide.');
            }

            $amount = null;
            if (!empty($row['credit']) && (float)$row['credit'] > 0) {
                $amount = (float)$row['credit'];
            } elseif (!empty($row['debit']) && (float)$row['debit'] > 0) {
                $amount = (float)$row['debit'];
            }

            if ($amount === null || $amount <= 0) {
                throw new RuntimeException('Montant invalide.');
            }

            $serviceId = null;
            if ($serviceCode !== '' && tableExists($pdo, 'ref_services')) {
                $stmtService = $pdo->prepare("SELECT id FROM ref_services WHERE code = ? LIMIT 1");
                $stmtService->execute([$serviceCode]);
                $serviceId = $stmtService->fetchColumn() ?: null;
            }

            $sourceTreasuryCode = null;
            if ($treasuryId) {
                $stmtTreasury = $pdo->prepare("SELECT account_code FROM treasury_accounts WHERE id = ? LIMIT 1");
                $stmtTreasury->execute([$treasuryId]);
                $sourceTreasuryCode = $stmtTreasury->fetchColumn() ?: null;
            }

            if ($operationTypeCode === 'VIREMENT_INTERNE') {
                if (!$treasuryId) {
                    throw new RuntimeException('Compte interne requis pour un virement interne.');
                }

                createInternalTreasuryMovement($pdo, [
                    'source_treasury_account_id' => $treasuryId,
                    'target_treasury_account_id' => $treasuryId,
                    'amount' => $amount,
                    'operation_date' => $row['operation_date'] ?? date('Y-m-d'),
                    'reference' => $row['reference'] ?? null,
                    'label' => $row['label'] ?? 'Virement interne importé',
                ]);
            } else {
                if (!$clientId) {
                    throw new RuntimeException('Client obligatoire.');
                }

                createOperationWithAccounting($pdo, [
                    'operation_type_code' => $operationTypeCode,
                    'service_id' => $serviceId,
                    'client_id' => $clientId,
                    'amount' => $amount,
                    'operation_date' => $row['operation_date'] ?? date('Y-m-d'),
                    'reference' => $row['reference'] ?? null,
                    'label' => $row['label'] ?? 'Opération importée',
                    'notes' => 'Import relevé bancaire',
                    'source_treasury_code' => $sourceTreasuryCode,
                ]);
            }

            if ($importId && tableExists($pdo, 'import_rows')) {
                $stmtRow = $pdo->prepare("
                    INSERT INTO import_rows (import_id, raw_data, status, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmtRow->execute([
                    $importId,
                    json_encode($row, JSON_UNESCAPED_UNICODE),
                    'validated'
                ]);
            }

            $imported++;
        } catch (Throwable $rowError) {
            $rejected++;
            $messages[] = 'Ligne ' . (int)($row['row_no'] ?? 0) . ' rejetée : ' . $rowError->getMessage();

            if ($importId && tableExists($pdo, 'import_rows')) {
                $stmtRow = $pdo->prepare("
                    INSERT INTO import_rows (import_id, raw_data, status, error_message, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmtRow->execute([
                    $importId,
                    json_encode($row, JSON_UNESCAPED_UNICODE),
                    'rejected',
                    $rowError->getMessage()
                ]);
            }
        }
    }

    if ($importId) {
        $stmtUpdateImport = $pdo->prepare("
            UPDATE imports
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdateImport->execute([
            $rejected > 0 ? 'validated_with_rejections' : 'validated',
            $importId
        ]);
    }

    if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'validate_import',
            'imports',
            'import',
            $importId,
            "Import validé. Lignes importées : {$imported}, rejetées : {$rejected}."
        );
    }

    $pdo->commit();

    unset($_SESSION['statement_import_preview']);
    $_SESSION['flash_success'] = "Import terminé. Validées : {$imported}, rejetées : {$rejected}.";
    $_SESSION['flash_details'] = $messages;

    header('Location: ' . APP_URL . 'modules/imports/import_journal.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
    exit;
}