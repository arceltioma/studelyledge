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

$expectedFields = [
    [
        'field' => 'operation_date',
        'label' => 'Date opération',
        'required' => 'Oui',
        'examples' => 'date, date_operation, operation_date',
        'description' => 'Date comptable ou date de l’opération.',
    ],
    [
        'field' => 'amount',
        'label' => 'Montant',
        'required' => 'Oui',
        'examples' => 'montant, amount, somme',
        'description' => 'Montant de l’opération, positif selon le flux importé.',
    ],
    [
        'field' => 'client_code',
        'label' => 'Code client',
        'required' => 'Oui',
        'examples' => 'client, code_client, client_code',
        'description' => 'Référence du client Studely Ledger.',
    ],
    [
        'field' => 'operation_type',
        'label' => 'Type d’opération',
        'required' => 'Oui',
        'examples' => 'type, type_operation, operation_type',
        'description' => 'Nature métier de l’opération.',
    ],
    [
        'field' => 'label',
        'label' => 'Libellé',
        'required' => 'Oui',
        'examples' => 'libelle, label, description',
        'description' => 'Libellé lisible de l’écriture.',
    ],
    [
        'field' => 'service',
        'label' => 'Service',
        'required' => 'Recommandé',
        'examples' => 'service, service_code, service_label',
        'description' => 'Service ou type de service lié à l’opération.',
    ],
    [
        'field' => 'currency_code',
        'label' => 'Devise',
        'required' => 'Recommandé',
        'examples' => 'devise, currency, currency_code',
        'description' => 'Devise de l’opération, par exemple EUR.',
    ],
    [
        'field' => 'reference',
        'label' => 'Référence',
        'required' => 'Optionnel',
        'examples' => 'reference, ref, numero_reference',
        'description' => 'Référence externe ou interne.',
    ],
    [
        'field' => 'notes',
        'label' => 'Notes',
        'required' => 'Optionnel',
        'examples' => 'notes, note, commentaire, motif',
        'description' => 'Commentaire libre ou justification.',
    ],
    [
        'field' => 'source_account_code',
        'label' => 'Compte source',
        'required' => 'Selon cas',
        'examples' => 'compte_source, source_account_code, debit_account',
        'description' => 'Compte débité si fourni par le fichier.',
    ],
    [
        'field' => 'destination_account_code',
        'label' => 'Compte destination',
        'required' => 'Selon cas',
        'examples' => 'compte_destination, destination_account_code, credit_account',
        'description' => 'Compte crédité si fourni par le fichier.',
    ],
    [
        'field' => 'linked_bank_account_id',
        'label' => 'Compte bancaire lié',
        'required' => 'Optionnel',
        'examples' => 'bank_account_id, linked_bank_account_id',
        'description' => 'Identifiant du compte bancaire lié si disponible.',
    ],
];

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

        <style>
            .slu-grid-2 {
                display: grid;
                grid-template-columns: 1.05fr 0.95fr;
                gap: 20px;
            }
            @media (max-width: 1100px) {
                .slu-grid-2 {
                    grid-template-columns: 1fr;
                }
            }
            .slu-panel {
                border-radius: 18px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
                padding: 18px;
            }
            .slu-note {
                margin-top: 14px;
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(99, 102, 241, 0.07);
                border: 1px solid rgba(99, 102, 241, 0.16);
                color: #3730a3;
                line-height: 1.55;
                font-size: 13px;
            }
            .slu-chip {
                display: inline-flex;
                align-items: center;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: .04em;
                text-transform: uppercase;
            }
            .slu-chip--req {
                background: rgba(239, 68, 68, 0.10);
                color: #b91c1c;
            }
            .slu-chip--rec {
                background: rgba(245, 158, 11, 0.12);
                color: #92400e;
            }
            .slu-chip--opt {
                background: rgba(148, 163, 184, 0.14);
                color: #475569;
            }
            .slu-chip--cond {
                background: rgba(16, 185, 129, 0.10);
                color: #047857;
            }
            .slu-subtitle {
                color: #64748b;
                margin-top: 4px;
            }
        </style>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="slu-grid-2">
            <div class="form-card">
                <h3>Uploader un fichier</h3>
                <div class="slu-subtitle">Formats acceptés : CSV, TXT, XLSX — taille max 10 Mo.</div>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <div>
                        <label for="import_file">Fichier CSV / TXT / XLSX</label>
                        <input type="file" id="import_file" name="import_file" accept=".csv,.txt,.xlsx" required>
                    </div>

                    <div class="slu-note">
                        Le moteur détecte automatiquement les entêtes, tente un pré-mapping intelligent,
                        puis prépare la session de travail avant l’étape de mapping métier.
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Continuer vers le mapping</button>
                        <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Journal imports</a>
                    </div>
                </form>
            </div>

            <div class="slu-panel">
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

        <div class="card" style="margin-top:20px;">
            <h3>Modèle CSV attendu</h3>
            <div class="slu-subtitle">
                Tu peux utiliser ces noms exacts ou des variantes proches : le pré-mapping tente de les reconnaître automatiquement.
            </div>

            <div class="table-responsive" style="margin-top:14px;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Champ cible</th>
                            <th>Libellé métier</th>
                            <th>Obligation</th>
                            <th>Exemples d’entêtes reconnus</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expectedFields as $field): ?>
                            <?php
                            $required = (string)$field['required'];
                            $chipClass = 'slu-chip--opt';

                            if ($required === 'Oui') {
                                $chipClass = 'slu-chip--req';
                            } elseif ($required === 'Recommandé') {
                                $chipClass = 'slu-chip--rec';
                            } elseif ($required === 'Selon cas') {
                                $chipClass = 'slu-chip--cond';
                            }
                            ?>
                            <tr>
                                <td><strong><?= e((string)$field['field']) ?></strong></td>
                                <td><?= e((string)$field['label']) ?></td>
                                <td><span class="slu-chip <?= e($chipClass) ?>"><?= e($required) ?></span></td>
                                <td><?= e((string)$field['examples']) ?></td>
                                <td><?= e((string)$field['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="slu-note">
                Minimum conseillé pour un import propre :
                <strong>operation_date</strong>,
                <strong>amount</strong>,
                <strong>client_code</strong>,
                <strong>operation_type</strong>,
                <strong>label</strong>.  
                Les comptes source / destination peuvent être fournis si le fichier les porte déjà, sinon ils pourront être complétés ou résolus au mapping selon les règles métier.
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