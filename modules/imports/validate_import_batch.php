<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_create');

$batchId = (int)($_GET['batch_id'] ?? 0);
if ($batchId <= 0) {
    exit('Batch invalide.');
}

$stmtBatch = $pdo->prepare("
    SELECT *
    FROM import_batches
    WHERE id = ?
    LIMIT 1
");
$stmtBatch->execute([$batchId]);
$batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    exit('Batch introuvable.');
}

$stmtRejected = $pdo->prepare("
    SELECT COUNT(*)
    FROM import_rows
    WHERE batch_id = ?
      AND status = 'rejected'
");
$stmtRejected->execute([$batchId]);
$rejectedCount = (int)$stmtRejected->fetchColumn();

if ($rejectedCount > 0) {
    $_SESSION['flash_error'] = 'Validation impossible : des lignes rejetées restent à corriger.';
    header('Location: ' . APP_URL . 'modules/imports/import_preview.php?batch_id=' . $batchId);
    exit;
}

$stmtReady = $pdo->prepare("
    SELECT *
    FROM import_rows
    WHERE batch_id = ?
      AND status = 'ready'
    ORDER BY row_number ASC
");
$stmtReady->execute([$batchId]);
$rows = $stmtReady->fetchAll(PDO::FETCH_ASSOC);

$inserted = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    foreach ($rows as $row) {
        $raw = json_decode((string)($row['raw_data'] ?? ''), true);
        $rawRow = $raw['row'] ?? [];

        $client = null;
        if (!empty($row['client_code'])) {
            $stmtClient = $pdo->prepare("
                SELECT *
                FROM clients
                WHERE client_code = ?
                LIMIT 1
            ");
            $stmtClient->execute([$row['client_code']]);
            $client = $stmtClient->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $service = null;
        $serviceCode = trim((string)($rawRow['service_code'] ?? $rawRow['code_service'] ?? ''));
        if ($serviceCode !== '') {
            $stmtService = $pdo->prepare("
                SELECT *
                FROM ref_services
                WHERE code = ?
                LIMIT 1
            ");
            $stmtService->execute([$serviceCode]);
            $service = $stmtService->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $payload = [
            'operation_type_code' => strtoupper(trim((string)($row['operation_type'] ?? ''))),
            'client_id' => $client['id'] ?? null,
            'service_id' => $service['id'] ?? null,
            'amount' => (float)($row['amount'] ?? 0),
            'operation_date' => $row['operation_date'] ?? date('Y-m-d'),
            'reference' => !empty($row['reference']) ? $row['reference'] : null,
            'label' => !empty($row['label']) ? $row['label'] : null,
            'source_type' => !empty($row['source_type']) ? $row['source_type'] : 'import',
            'operation_kind' => 'import',
            'source_treasury_code' => trim((string)($rawRow['treasury_account_code'] ?? $rawRow['compte_512'] ?? '')) ?: null,
            'target_treasury_code' => trim((string)($rawRow['target_treasury_account_code'] ?? $rawRow['compte_512_cible'] ?? '')) ?: null,
        ];

        try {
            createOperationWithAccounting($pdo, $payload);

            $stmtDone = $pdo->prepare("
                UPDATE import_rows
                SET status = 'imported',
                    error_message = NULL
                WHERE id = ?
            ");
            $stmtDone->execute([(int)$row['id']]);

            $inserted++;
        } catch (Throwable $e) {
            $stmtFail = $pdo->prepare("
                UPDATE import_rows
                SET status = 'rejected',
                    error_message = ?
                WHERE id = ?
            ");
            $stmtFail->execute([$e->getMessage(), (int)$row['id']]);

            $errors[] = 'Ligne ' . (int)($row['row_number'] ?? 0) . ' : ' . $e->getMessage();
        }
    }

    $finalStatus = count($errors) > 0 ? 'validated_with_rejections' : 'imported';

    $stmtBatchEnd = $pdo->prepare("
        UPDATE import_batches
        SET status = ?,
            imported_rows = ?,
            rejected_rows = ?,
            validated_at = NOW()
        WHERE id = ?
    ");
    $stmtBatchEnd->execute([
        $finalStatus,
        $inserted,
        count($errors),
        $batchId
    ]);

    if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'validate_import_batch',
            'imports',
            'import_batch',
            $batchId,
            'Validation batch. Importées : ' . $inserted . ', erreurs : ' . count($errors)
        );
    }

    $pdo->commit();

    $_SESSION['flash_success'] = 'Validation terminée. Lignes importées : ' . $inserted . '.';
    $_SESSION['flash_details'] = $errors;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . APP_URL . 'modules/imports/import_journal.php');
exit;