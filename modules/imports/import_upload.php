<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'imports_upload';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Merci de sélectionner un fichier CSV valide.');
        }

        $file = $_FILES['import_file'];
        $originalName = trim($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            throw new Exception('Seuls les fichiers CSV sont acceptés pour cet import.');
        }

        $uploadDir = APP_ROOT . 'uploads/imports/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new Exception('Impossible de créer le dossier d’upload.');
        }

        $storedName = 'import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.csv';
        $targetPath = $uploadDir . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Impossible d’enregistrer le fichier importé.');
        }

        $handle = fopen($targetPath, 'r');
        if (!$handle) {
            throw new Exception('Impossible de lire le fichier CSV.');
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header || count($header) < 4) {
            fclose($handle);
            throw new Exception('Le fichier CSV doit contenir une ligne d’en-tête exploitable.');
        }

        $normalizedHeader = array_map(function ($value) {
            return strtolower(trim((string)$value));
        }, $header);

        $mapping = [
            'client_code'    => array_search('client_code', $normalizedHeader, true),
            'operation_date' => array_search('operation_date', $normalizedHeader, true),
            'operation_type' => array_search('operation_type', $normalizedHeader, true),
            'label'          => array_search('label', $normalizedHeader, true),
            'amount'         => array_search('amount', $normalizedHeader, true),
            'reference'      => array_search('reference', $normalizedHeader, true),
            'source_type'    => array_search('source_type', $normalizedHeader, true),
        ];

        foreach (['client_code', 'operation_date', 'operation_type', 'label', 'amount'] as $requiredField) {
            if ($mapping[$requiredField] === false) {
                fclose($handle);
                throw new Exception("Colonne obligatoire absente dans le CSV : {$requiredField}");
            }
        }

        $pdo->beginTransaction();

        $stmtBatch = $pdo->prepare("
            INSERT INTO import_batches (
                file_name,
                status,
                imported_at,
                total_rows,
                valid_rows,
                rejected_rows
            ) VALUES (?, 'pending', NOW(), 0, 0, 0)
        ");
        $stmtBatch->execute([$originalName]);
        $batchId = (int)$pdo->lastInsertId();

        $stmtRow = $pdo->prepare("
            INSERT INTO import_rows (
                batch_id,
                row_number,
                client_code,
                operation_date,
                operation_type,
                label,
                amount,
                reference,
                source_type,
                status,
                raw_data,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");

        $rowNumber = 1;
        $insertedRows = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $clientCode = trim((string)($row[$mapping['client_code']] ?? ''));
            $operationDate = trim((string)($row[$mapping['operation_date']] ?? ''));
            $operationType = strtolower(trim((string)($row[$mapping['operation_type']] ?? '')));
            $label = trim((string)($row[$mapping['label']] ?? ''));
            $amountRaw = str_replace(',', '.', trim((string)($row[$mapping['amount']] ?? '')));
            $reference = $mapping['reference'] !== false ? trim((string)($row[$mapping['reference']] ?? '')) : null;
            $sourceType = $mapping['source_type'] !== false ? trim((string)($row[$mapping['source_type']] ?? '')) : 'import';

            $rawData = json_encode([
                'header' => $normalizedHeader,
                'row' => $row,
            ], JSON_UNESCAPED_UNICODE);

            $stmtRow->execute([
                $batchId,
                $rowNumber,
                $clientCode !== '' ? $clientCode : null,
                $operationDate !== '' ? $operationDate : null,
                $operationType !== '' ? $operationType : null,
                $label !== '' ? $label : null,
                is_numeric($amountRaw) ? (float)$amountRaw : null,
                $reference !== '' ? $reference : null,
                $sourceType !== '' ? $sourceType : 'import',
                $rawData
            ]);

            $insertedRows++;
        }

        fclose($handle);

        $stmtUpdateBatch = $pdo->prepare("
            UPDATE import_batches
            SET total_rows = ?
            WHERE id = ?
        ");
        $stmtUpdateBatch->execute([$insertedRows, $batchId]);

        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'upload_import_file',
            'imports',
            'import_batch',
            $batchId,
            "Upload du fichier {$originalName} avec {$insertedRows} ligne(s)"
        );

        $pdo->commit();

        header('Location: ' . APP_URL . 'modules/imports/import_preview.php?batch_id=' . $batchId);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar('Importer un fichier', 'Déposer un fichier source avant prévisualisation et validation.'); ?>

        <div class="page-title">

            <div class="btn-group">
                <?php if (currentUserCan($pdo, 'imports_journal')): ?>
                    <a href="<?= APP_URL ?>modules/imports/import_journal.php" class="btn btn-outline">Journal des imports</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Déposer un CSV</h3>

                <form method="POST" enctype="multipart/form-data">
                    <label for="import_file">Fichier CSV</label>
                    <input type="file" name="import_file" id="import_file" accept=".csv" required>

                    <div class="dashboard-note">
                        Le fichier doit contenir au minimum les colonnes suivantes :
                        <code>client_code</code>, <code>operation_date</code>, <code>operation_type</code>, <code>label</code>, <code>amount</code>.
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Prévisualiser l’import</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Format attendu</h3>

                <div class="stat-row">
                    <span class="metric-label">Type</span>
                    <span class="metric-value">CSV</span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Séparateur</span>
                    <span class="metric-value">;</span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Colonnes obligatoires</span>
                    <span class="metric-value">5 minimum</span>
                </div>

                <div class="dashboard-note">
                    Merci de vous assurer que le fichier importé respecte bien toutes les exigences structurelles énoncées.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>