<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_upload');

function iu_normalize_header(string $value): string
{
    $value = trim($value);
    $value = mb_strtolower($value, 'UTF-8');
    $value = str_replace([' ', '-', '/'], '_', $value);
    return $value;
}

function iu_csv_cell(array $row, int|false $index): string
{
    if ($index === false) {
        return '';
    }
    return trim((string)($row[$index] ?? ''));
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Merci de sélectionner un fichier CSV valide.');
        }

        $file = $_FILES['import_file'];
        $originalName = trim((string)($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            throw new RuntimeException('Seuls les fichiers CSV sont acceptés pour cet import.');
        }

        $uploadDir = APP_ROOT . 'uploads/imports/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Impossible de créer le dossier d’upload.');
        }

        $storedName = 'import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.csv';
        $targetPath = $uploadDir . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Impossible d’enregistrer le fichier importé.');
        }

        $handle = fopen($targetPath, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier CSV.');
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header || count($header) < 5) {
            fclose($handle);
            throw new RuntimeException('Le fichier CSV doit contenir une ligne d’en-tête exploitable avec au moins 5 colonnes.');
        }

        $normalizedHeader = array_map(
            fn($value) => iu_normalize_header((string)$value),
            $header
        );

        $mapping = [
            'client_code'                => array_search('client_code', $normalizedHeader, true),
            'operation_date'             => array_search('operation_date', $normalizedHeader, true),
            'operation_type'             => array_search('operation_type', $normalizedHeader, true),
            'label'                      => array_search('label', $normalizedHeader, true),
            'amount'                     => array_search('amount', $normalizedHeader, true),
            'reference'                  => array_search('reference', $normalizedHeader, true),
            'source_type'                => array_search('source_type', $normalizedHeader, true),
            'service_code'               => array_search('service_code', $normalizedHeader, true),
            'treasury_account_code'      => array_search('treasury_account_code', $normalizedHeader, true),
            'target_treasury_account_code' => array_search('target_treasury_account_code', $normalizedHeader, true),
        ];

        foreach (['client_code', 'operation_date', 'operation_type', 'label', 'amount'] as $requiredField) {
            if ($mapping[$requiredField] === false) {
                fclose($handle);
                throw new RuntimeException("Colonne obligatoire absente dans le CSV : {$requiredField}");
            }
        }

        if (!tableExists($pdo, 'import_batches') || !tableExists($pdo, 'import_rows')) {
            fclose($handle);
            throw new RuntimeException('Les tables import_batches et import_rows sont requises pour cet import.');
        }

        $pdo->beginTransaction();

        $batchColumns = ['file_name', 'status'];
        $batchValues = ['?', '?'];
        $batchParams = [$originalName, 'pending'];

        if (columnExists($pdo, 'import_batches', 'stored_file_name')) {
            $batchColumns[] = 'stored_file_name';
            $batchValues[] = '?';
            $batchParams[] = $storedName;
        }

        if (columnExists($pdo, 'import_batches', 'stored_file_path')) {
            $batchColumns[] = 'stored_file_path';
            $batchValues[] = '?';
            $batchParams[] = $targetPath;
        }

        if (columnExists($pdo, 'import_batches', 'imported_at')) {
            $batchColumns[] = 'imported_at';
            $batchValues[] = 'NOW()';
        }

        if (columnExists($pdo, 'import_batches', 'created_at')) {
            $batchColumns[] = 'created_at';
            $batchValues[] = 'NOW()';
        }

        if (columnExists($pdo, 'import_batches', 'total_rows')) {
            $batchColumns[] = 'total_rows';
            $batchValues[] = '0';
        }

        if (columnExists($pdo, 'import_batches', 'ready_rows')) {
            $batchColumns[] = 'ready_rows';
            $batchValues[] = '0';
        }

        if (columnExists($pdo, 'import_batches', 'valid_rows')) {
            $batchColumns[] = 'valid_rows';
            $batchValues[] = '0';
        }

        if (columnExists($pdo, 'import_batches', 'rejected_rows')) {
            $batchColumns[] = 'rejected_rows';
            $batchValues[] = '0';
        }

        if (columnExists($pdo, 'import_batches', 'imported_rows')) {
            $batchColumns[] = 'imported_rows';
            $batchValues[] = '0';
        }

        $stmtBatch = $pdo->prepare("
            INSERT INTO import_batches (" . implode(', ', $batchColumns) . ")
            VALUES (" . implode(', ', $batchValues) . ")
        ");
        $stmtBatch->execute($batchParams);
        $batchId = (int)$pdo->lastInsertId();

        $rowColumns = [
            'batch_id',
            'row_number',
            'client_code',
            'operation_date',
            'operation_type',
            'label',
            'amount',
            'reference',
            'source_type',
            'status',
            'raw_data'
        ];
        $rowValues = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];

        if (columnExists($pdo, 'import_rows', 'created_at')) {
            $rowColumns[] = 'created_at';
            $rowValues[] = 'NOW()';
        }

        $stmtRow = $pdo->prepare("
            INSERT INTO import_rows (" . implode(', ', $rowColumns) . ")
            VALUES (" . implode(', ', $rowValues) . ")
        ");

        $rowNumber = 1;
        $insertedRows = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $clientCode = iu_csv_cell($row, $mapping['client_code']);
            $operationDate = iu_csv_cell($row, $mapping['operation_date']);
            $operationType = strtoupper(iu_csv_cell($row, $mapping['operation_type']));
            $label = iu_csv_cell($row, $mapping['label']);
            $amountRaw = str_replace(',', '.', iu_csv_cell($row, $mapping['amount']));
            $reference = iu_csv_cell($row, $mapping['reference']);
            $sourceType = iu_csv_cell($row, $mapping['source_type']);

            $serviceCode = iu_csv_cell($row, $mapping['service_code']);
            $treasuryCode = iu_csv_cell($row, $mapping['treasury_account_code']);
            $targetTreasuryCode = iu_csv_cell($row, $mapping['target_treasury_account_code']);

            $rawData = json_encode([
                'header' => $normalizedHeader,
                'row' => [
                    'client_code' => $clientCode,
                    'operation_date' => $operationDate,
                    'operation_type' => $operationType,
                    'label' => $label,
                    'amount' => $amountRaw,
                    'reference' => $reference,
                    'source_type' => $sourceType !== '' ? $sourceType : 'import',
                    'service_code' => $serviceCode,
                    'treasury_account_code' => $treasuryCode,
                    'target_treasury_account_code' => $targetTreasuryCode,
                ],
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
                'pending',
                $rawData
            ]);

            $insertedRows++;
        }

        fclose($handle);

        $batchUpdates = [];
        $batchParamsUpdate = [];

        if (columnExists($pdo, 'import_batches', 'total_rows')) {
            $batchUpdates[] = 'total_rows = ?';
            $batchParamsUpdate[] = $insertedRows;
        }
        if (columnExists($pdo, 'import_batches', 'ready_rows')) {
            $batchUpdates[] = 'ready_rows = 0';
        }
        if (columnExists($pdo, 'import_batches', 'valid_rows')) {
            $batchUpdates[] = 'valid_rows = 0';
        }
        if (columnExists($pdo, 'import_batches', 'rejected_rows')) {
            $batchUpdates[] = 'rejected_rows = 0';
        }
        if (columnExists($pdo, 'import_batches', 'imported_rows')) {
            $batchUpdates[] = 'imported_rows = 0';
        }
        if (columnExists($pdo, 'import_batches', 'updated_at')) {
            $batchUpdates[] = 'updated_at = NOW()';
        }

        if ($batchUpdates) {
            $batchParamsUpdate[] = $batchId;
            $stmtUpdateBatch = $pdo->prepare("
                UPDATE import_batches
                SET " . implode(', ', $batchUpdates) . "
                WHERE id = ?
            ");
            $stmtUpdateBatch->execute($batchParamsUpdate);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'upload_import_file',
                'imports',
                'import_batch',
                $batchId,
                "Upload du fichier {$originalName} avec {$insertedRows} ligne(s)"
            );
        }

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

$pageTitle = 'Importer un fichier';
$pageSubtitle = 'Déposer un CSV source avant prévisualisation, contrôle, correction éventuelle et validation finale.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="page-title">
            <div class="btn-group">
                <?php if (function_exists('currentUserCan') && currentUserCan($pdo, 'imports_journal')): ?>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Journal des imports</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Déposer un CSV</h3>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <label for="import_file">Fichier CSV</label>
                    <input type="file" name="import_file" id="import_file" accept=".csv" required>

                    <div class="dashboard-note">
                        Colonnes minimales attendues :
                        <code>client_code</code>,
                        <code>operation_date</code>,
                        <code>operation_type</code>,
                        <code>label</code>,
                        <code>amount</code>.
                        <br>
                        Colonnes optionnelles recommandées :
                        <code>reference</code>,
                        <code>source_type</code>,
                        <code>service_code</code>,
                        <code>treasury_account_code</code>,
                        <code>target_treasury_account_code</code>.
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
                    <span class="metric-label">Flux</span>
                    <span class="metric-value">upload → preview → rejets/corrections → validation</span>
                </div>

                <div class="dashboard-note">
                    L’upload ne crée aucune écriture comptable. Il enregistre seulement un batch et ses lignes pour que la prévisualisation applique ensuite le moteur métier central avant toute validation.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>