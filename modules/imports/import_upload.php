<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'imports_upload');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Import intelligent des opérations';
$pageSubtitle = 'Upload avec prévisualisation avant passage au mapping';

const SL_IMPORT_SESSION_KEY = 'studelyledger_operations_import_preview_v3';

$vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

if (!function_exists('sl_import_ai_normalize')) {
    function sl_import_ai_normalize(string $value): string
    {
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(
            ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','ù','û','ü','ç','œ','æ','/','\\','-','.'],
            ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c','oe','ae',' ',' ',' ',' '],
            $value
        );
        $value = preg_replace('/\s+/', '_', $value);
        $value = preg_replace('/[^a-z0-9_]/', '', $value);
        return trim($value, '_');
    }
}

if (!function_exists('sl_import_ai_guess_field')) {
    function sl_import_ai_guess_field(string $header): string
    {
        $normalized = sl_import_ai_normalize($header);

        $dictionary = [
            'date' => 'operation_date',
            'date_operation' => 'operation_date',
            'operation_date' => 'operation_date',
            'booking_date' => 'operation_date',
            'value_date' => 'operation_date',

            'montant' => 'amount',
            'amount' => 'amount',
            'somme' => 'amount',
            'valeur' => 'amount',

            'devise' => 'currency_code',
            'currency' => 'currency_code',
            'currency_code' => 'currency_code',

            'client' => 'client_code',
            'code_client' => 'client_code',
            'client_code' => 'client_code',
            'reference_client' => 'client_code',

            'type' => 'operation_type',
            'type_operation' => 'operation_type',
            'operation_type' => 'operation_type',
            'nature_operation' => 'operation_type',

            'service' => 'service',
            'type_service' => 'service',
            'service_code' => 'service',
            'service_label' => 'service',

            'reference' => 'reference',
            'numero_reference' => 'reference',
            'ref' => 'reference',

            'libelle' => 'label',
            'intitule' => 'label',
            'label' => 'label',
            'description' => 'label',

            'note' => 'notes',
            'notes' => 'notes',
            'motif' => 'notes',
            'commentaire' => 'notes',
            'comment' => 'notes',

            'compte_source' => 'source_account_code',
            'source_account' => 'source_account_code',
            'source_account_code' => 'source_account_code',
            'debit_account' => 'source_account_code',

            'compte_destination' => 'destination_account_code',
            'destination_account' => 'destination_account_code',
            'destination_account_code' => 'destination_account_code',
            'credit_account' => 'destination_account_code',

            'compte_bancaire_lie' => 'linked_bank_account_id',
            'linked_bank_account_id' => 'linked_bank_account_id',
            'bank_account_id' => 'linked_bank_account_id',
        ];

        return $dictionary[$normalized] ?? '';
    }
}

if (!function_exists('sl_import_ai_detect_delimiter')) {
    function sl_import_ai_detect_delimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ';';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }
}

if (!function_exists('sl_import_ai_read_csv_raw')) {
    function sl_import_ai_read_csv_raw(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier uploadé.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new RuntimeException('Le fichier est vide.');
        }

        $delimiter = sl_import_ai_detect_delimiter($firstLine);
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Entêtes CSV introuvables.');
        }

        $headers = array_map(static fn($h) => trim((string)$h), $headers);

        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            if ($data === [null] || count(array_filter($data, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string)$data[$index]) : '';
            }
            $row['_line_number'] = $lineNumber;
            $rows[] = $row;
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }
}

if (!function_exists('sl_import_ai_read_xlsx_raw')) {
    function sl_import_ai_read_xlsx_raw(string $filePath): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException('Le support XLSX nécessite PhpSpreadsheet. Installe-le via Composer.');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, true);

        if (!$raw || count($raw) < 2) {
            throw new RuntimeException('Le fichier XLSX est vide ou inexploitable.');
        }

        $headerRow = array_shift($raw);
        $headers = array_map(static fn($h) => trim((string)$h), array_values($headerRow));

        $rows = [];
        $lineNumber = 1;
        foreach ($raw as $line) {
            $lineNumber++;
            $values = array_values($line);

            if (count(array_filter($values, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($values[$index]) ? trim((string)$values[$index]) : '';
            }
            $row['_line_number'] = $lineNumber;
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}

if (!function_exists('sl_import_upload_create_notification')) {
    function sl_import_upload_create_notification(
        PDO $pdo,
        string $message,
        string $level = 'success',
        ?string $linkUrl = null,
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
            'type' => 'import_upload',
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => 'import',
            'entity_id' => null,
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

$errorMessage = '';
$previewData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!isset($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
            throw new RuntimeException('Aucun fichier reçu.');
        }

        $file = $_FILES['import_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erreur lors de l’upload du fichier.');
        }

        $originalName = (string)($file['name'] ?? 'import.csv');
        $tmpPath = (string)($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Fichier uploadé invalide.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            throw new RuntimeException('Formats supportés : CSV, TXT, XLSX.');
        }

        if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new RuntimeException('Le fichier dépasse 10 Mo.');
        }

        $parsed = $extension === 'xlsx'
            ? sl_import_ai_read_xlsx_raw($tmpPath)
            : sl_import_ai_read_csv_raw($tmpPath);

        if (empty($parsed['rows'])) {
            throw new RuntimeException('Aucune ligne exploitable trouvée.');
        }

        $suggestedMapping = [];
        foreach ($parsed['headers'] as $header) {
            $suggestedMapping[$header] = sl_import_ai_guess_field($header);
        }

        $_SESSION[SL_IMPORT_SESSION_KEY] = [
            'file_name' => $originalName,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'raw_headers' => $parsed['headers'],
            'raw_rows' => $parsed['rows'],
            'suggested_mapping' => $suggestedMapping,
            'import_rules' => [
                'auto_mapping' => true,
                'extension' => $extension,
            ],
        ];

        $previewData = [
            'file_name' => $originalName,
            'extension' => strtoupper($extension),
            'headers' => $parsed['headers'],
            'headers_count' => count($parsed['headers']),
            'rows_count' => count($parsed['rows']),
            'mapped_count' => count(array_filter($suggestedMapping)),
            'sample_rows' => array_slice($parsed['rows'], 0, 5),
        ];

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $details = sprintf(
            'Prévisualisation import : %s | format=%s | colonnes=%d | lignes=%d | mapping_auto=%d',
            $originalName,
            strtoupper($extension),
            count($parsed['headers']),
            count($parsed['rows']),
            count(array_filter($suggestedMapping))
        );

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'upload_import_preview',
                'imports',
                'import',
                null,
                $details
            );
        }

        sl_import_upload_create_notification(
            $pdo,
            sprintf(
                'Prévisualisation import prête : %s (%d lignes, %d colonnes)',
                $originalName,
                count($parsed['rows']),
                count($parsed['headers'])
            ),
            'success',
            APP_URL . 'modules/imports/import_mapping.php',
            $userId > 0 ? $userId : null
        );

        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));
        if ($actionMode === 'save') {
            header('Location: ' . APP_URL . 'modules/imports/import_mapping.php');
            exit;
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3>Uploader un fichier</h3>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <div>
                        <label for="import_file">Fichier CSV / TXT / XLSX</label>
                        <input type="file" id="import_file" name="import_file" accept=".csv,.txt,.xlsx" required>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Continuer vers le mapping</button>
                        <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Journal imports</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation</h3>

                <?php if ($previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Fichier</span><strong><?= e($previewData['file_name']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Format</span><strong><?= e($previewData['extension']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Colonnes détectées</span><strong><?= (int)$previewData['headers_count'] ?></strong></div>
                        <div class="sl-data-list__row"><span>Lignes détectées</span><strong><?= (int)$previewData['rows_count'] ?></strong></div>
                        <div class="sl-data-list__row"><span>Pré-mapping reconnu</span><strong><?= (int)$previewData['mapped_count'] ?></strong></div>
                    </div>

                    <div class="dashboard-note" style="margin-top:16px;">
                        <?= e(implode(' | ', $previewData['headers'])) ?>
                    </div>
                <?php else: ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>1. Upload</span><strong>Fichier brut</strong></div>
                        <div class="sl-data-list__row"><span>2. Mapping</span><strong>Correction colonnes</strong></div>
                        <div class="sl-data-list__row"><span>3. Prévisualisation</span><strong>Validation métier</strong></div>
                        <div class="sl-data-list__row"><span>4. Import</span><strong>Insertion transactionnelle</strong></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($previewData && !empty($previewData['sample_rows'])): ?>
            <div class="card" style="margin-top:20px;">
                <h3>Extrait du fichier</h3>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <?php foreach ($previewData['headers'] as $header): ?>
                                    <th><?= e((string)$header) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewData['sample_rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($previewData['headers'] as $header): ?>
                                        <td><?= e((string)($row[$header] ?? '')) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>