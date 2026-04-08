<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_import_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$pageTitle = 'Import comptes de service CSV';
$pageSubtitle = 'Prévisualisation avant validation, contrôle des doublons et alignement BDD';

const SL_SERVICE_ACCOUNTS_IMPORT_SESSION_KEY = 'studelyledger_service_accounts_import_preview_v2';

$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$operationTypeLabels = array_map(
    static fn(array $row): string => (string)($row['label'] ?? ''),
    $operationTypes
);

$successMessage = '';
$errorMessage = '';
$previewRows = [];
$summary = [
    'total' => 0,
    'ok' => 0,
    'duplicate' => 0,
    'error' => 0,
];

if (!function_exists('sl_service_import_norm')) {
    function sl_service_import_norm(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = str_replace(
            ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','ù','û','ü','ç','/','\\','-','.'],
            ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c',' ',' ',' ',' '],
            $value
        );
        $value = preg_replace('/\s+/', '_', $value);
        return preg_replace('/[^a-z0-9_]/', '', $value);
    }
}

if (!function_exists('sl_service_import_detect_delimiter')) {
    function sl_service_import_detect_delimiter(string $line): string
    {
        $delimiters = [';', ',', '|', "\t"];
        $best = ';';
        $max = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $max) {
                $max = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }
}

if (!function_exists('sl_service_import_parse_amount')) {
    function sl_service_import_parse_amount(string $value): float
    {
        $value = trim($value);
        $value = str_replace(["\xc2\xa0", ' '], '', $value);

        if ($value === '') {
            return 0.0;
        }

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return (float)$value;
    }
}

if (!function_exists('sl_service_import_read_csv')) {
    function sl_service_import_read_csv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new RuntimeException('Le fichier est vide.');
        }

        $delimiter = sl_service_import_detect_delimiter($firstLine);
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Entêtes CSV introuvables.');
        }

        $headers = array_map(static fn($h) => trim((string)$h), $headers);
        $rows = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            if ($row === [null] || count(array_filter($row, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = trim((string)($row[$index] ?? ''));
            }
            $assoc['_line_number'] = $lineNumber;
            $rows[] = $assoc;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}

if (!function_exists('sl_service_import_extract_row')) {
    function sl_service_import_extract_row(array $rawRow): array
    {
        $normalized = [];
        foreach ($rawRow as $key => $value) {
            if ($key === '_line_number') {
                continue;
            }
            $normalized[sl_service_import_norm((string)$key)] = trim((string)$value);
        }

        return [
            'account_code' => strtoupper((string)($normalized['account_code'] ?? $normalized['code'] ?? '')),
            'account_label' => (string)($normalized['account_label'] ?? $normalized['label'] ?? ''),
            'operation_type_label' => (string)($normalized['operation_type_label'] ?? $normalized['type_operation'] ?? $normalized['operation_type'] ?? ''),
            'commercial_country_label' => (string)($normalized['commercial_country_label'] ?? $normalized['country_commercial'] ?? $normalized['pays_commercial'] ?? ''),
            'destination_country_label' => (string)($normalized['destination_country_label'] ?? $normalized['country_destination'] ?? $normalized['pays_destination'] ?? ''),
            'current_balance' => (string)($normalized['current_balance'] ?? $normalized['balance'] ?? $normalized['solde_courant'] ?? '0'),
            'is_postable' => (string)($normalized['is_postable'] ?? '1'),
            'is_active' => (string)($normalized['is_active'] ?? '1'),
        ];
    }
}

if (!function_exists('sl_service_import_prepare_preview')) {
    function sl_service_import_prepare_preview(
        PDO $pdo,
        array $rows,
        array $operationTypeLabels,
        array $commercialCountries,
        array $destinationCountries
    ): array {
        $prepared = [];
        $summary = [
            'total' => 0,
            'ok' => 0,
            'duplicate' => 0,
            'error' => 0,
        ];

        foreach ($rows as $row) {
            $summary['total']++;
            $line = (int)($row['_line_number'] ?? 0);
            $messages = [];
            $status = 'ok';
            $payload = null;

            try {
                $extracted = sl_service_import_extract_row($row);

                $code = trim((string)$extracted['account_code']);
                $label = trim((string)$extracted['account_label']);
                $operationTypeLabel = trim((string)$extracted['operation_type_label']);
                $commercialCountry = trim((string)$extracted['commercial_country_label']);
                $destinationCountry = trim((string)$extracted['destination_country_label']);
                $currentBalance = sl_service_import_parse_amount((string)$extracted['current_balance']);
                $isPostable = in_array((string)$extracted['is_postable'], ['1', 'true', 'TRUE', 'yes', 'oui'], true) ? 1 : 0;
                $isActive = in_array((string)$extracted['is_active'], ['1', 'true', 'TRUE', 'yes', 'oui'], true) ? 1 : 0;

                if ($code === '') {
                    throw new RuntimeException('Code compte manquant.');
                }

                if ($label === '') {
                    throw new RuntimeException('Intitulé manquant.');
                }

                if ($operationTypeLabel !== '' && $operationTypeLabels && !in_array($operationTypeLabel, $operationTypeLabels, true)) {
                    throw new RuntimeException('Type d’opération inconnu : ' . $operationTypeLabel);
                }

                if ($commercialCountry !== '' && $commercialCountries && !in_array($commercialCountry, $commercialCountries, true)) {
                    throw new RuntimeException('Pays commercial invalide : ' . $commercialCountry);
                }

                if ($destinationCountry !== '' && $destinationCountries && !in_array($destinationCountry, $destinationCountries, true)) {
                    throw new RuntimeException('Pays destination invalide : ' . $destinationCountry);
                }

                $stmt = $pdo->prepare("SELECT id FROM service_accounts WHERE account_code = ? LIMIT 1");
                $stmt->execute([$code]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $status = 'duplicate';
                    $messages[] = 'Compte déjà existant.';
                }

                $payload = [
                    'account_code' => $code,
                    'account_label' => $label,
                    'operation_type_label' => $operationTypeLabel,
                    'commercial_country_label' => $commercialCountry,
                    'destination_country_label' => $destinationCountry,
                    'current_balance' => $currentBalance,
                    'is_postable' => $isPostable,
                    'is_active' => $isActive,
                ];

                if ($status === 'ok') {
                    $summary['ok']++;
                } elseif ($status === 'duplicate') {
                    $summary['duplicate']++;
                }
            } catch (Throwable $e) {
                $status = 'error';
                $messages[] = $e->getMessage();
                $summary['error']++;
            }

            $prepared[] = [
                'line' => $line,
                'status' => $status,
                'messages' => $messages,
                'payload' => $payload,
            ];
        }

        return [$prepared, $summary];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['import_action'] ?? 'upload'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($action === 'confirm_import') {
            $sessionData = $_SESSION[SL_SERVICE_ACCOUNTS_IMPORT_SESSION_KEY] ?? null;
            if (!$sessionData || empty($sessionData['prepared_rows'])) {
                throw new RuntimeException('Aucune prévisualisation disponible.');
            }

            $preparedRows = $sessionData['prepared_rows'];
            $inserted = 0;

            $pdo->beginTransaction();

            foreach ($preparedRows as $row) {
                if (($row['status'] ?? '') !== 'ok' || empty($row['payload'])) {
                    continue;
                }

                $payload = $row['payload'];

                $columns = [];
                $values = [];
                $params = [];

                $map = [
                    'account_code' => $payload['account_code'],
                    'account_label' => $payload['account_label'],
                    'operation_type_label' => $payload['operation_type_label'] !== '' ? $payload['operation_type_label'] : null,
                    'commercial_country_label' => $payload['commercial_country_label'] !== '' ? $payload['commercial_country_label'] : null,
                    'destination_country_label' => $payload['destination_country_label'] !== '' ? $payload['destination_country_label'] : null,
                    'current_balance' => (float)$payload['current_balance'],
                    'is_postable' => (int)$payload['is_postable'],
                    'is_active' => (int)$payload['is_active'],
                ];

                foreach ($map as $column => $value) {
                    if (columnExists($pdo, 'service_accounts', $column)) {
                        $columns[] = $column;
                        $values[] = '?';
                        $params[] = $value;
                    }
                }

                if (columnExists($pdo, 'service_accounts', 'created_at')) {
                    $columns[] = 'created_at';
                    $values[] = 'NOW()';
                }

                if (columnExists($pdo, 'service_accounts', 'updated_at')) {
                    $columns[] = 'updated_at';
                    $values[] = 'NOW()';
                }

                $stmtInsert = $pdo->prepare("
                    INSERT INTO service_accounts (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $values) . ")
                ");
                $stmtInsert->execute($params);
                $inserted++;
            }

            $pdo->commit();

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'service_accounts_import',
                    'service_accounts',
                    'import',
                    null,
                    'Import CSV comptes de service : ' . $inserted . ' créés'
                );
            }

            unset($_SESSION[SL_SERVICE_ACCOUNTS_IMPORT_SESSION_KEY]);
            $successMessage = $inserted . ' compte(s) de service importé(s) avec succès.';
        } else {
            if (empty($_FILES['csv_file']['tmp_name'])) {
                throw new RuntimeException('Fichier CSV manquant.');
            }

            $tmp = (string)$_FILES['csv_file']['tmp_name'];
            if (!is_uploaded_file($tmp)) {
                throw new RuntimeException('Fichier uploadé invalide.');
            }

            $parsed = sl_service_import_read_csv($tmp);
            if (empty($parsed['rows'])) {
                throw new RuntimeException('Aucune ligne exploitable trouvée.');
            }

            [$previewRows, $summary] = sl_service_import_prepare_preview(
                $pdo,
                $parsed['rows'],
                $operationTypeLabels,
                $commercialCountries,
                $destinationCountries
            );

            $_SESSION[SL_SERVICE_ACCOUNTS_IMPORT_SESSION_KEY] = [
                'file_name' => (string)($_FILES['csv_file']['name'] ?? 'import_service_accounts.csv'),
                'prepared_rows' => $previewRows,
                'summary' => $summary,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

if (!$previewRows && !empty($_SESSION[SL_SERVICE_ACCOUNTS_IMPORT_SESSION_KEY]['prepared_rows'])) {
    $previewRows = $_SESSION[SL_SERVICE_ACCOUNTS_IMPORT_SESSION_KEY]['prepared_rows'];
    $summary = $_SESSION[SL_SERVICE_ACCOUNTS_IMPORT_SESSION_KEY]['summary'] ?? $summary;
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <div>
                        <label>Fichier CSV</label>
                        <input type="file" name="csv_file" accept=".csv,text/csv" <?= $previewRows ? '' : 'required' ?>>
                    </div>

                    <div class="dashboard-note" style="margin-top:14px;">
                        Colonnes recommandées :
                        <strong>account_code</strong>,
                        <strong>account_label</strong>,
                        <strong>operation_type_label</strong>,
                        <strong>commercial_country_label</strong>,
                        <strong>destination_country_label</strong>,
                        <strong>current_balance</strong>,
                        <strong>is_postable</strong>,
                        <strong>is_active</strong>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <?php if ($previewRows): ?>
                            <button type="submit" name="import_action" value="confirm_import" class="btn btn-success" <?= ($summary['ok'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                                Valider l’import
                            </button>
                        <?php else: ?>
                            <button type="submit" name="import_action" value="upload" class="btn btn-secondary">Prévisualiser</button>
                        <?php endif; ?>

                        <a href="<?= e(APP_URL) ?>modules/imports/index.php" class="btn btn-outline">Hub Import</a>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-secondary">Comptes 706</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation</h3>

                <?php if ($previewRows): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Total lignes</span><strong><?= (int)($summary['total'] ?? 0) ?></strong></div>
                        <div class="sl-data-list__row"><span>Importables</span><strong><?= (int)($summary['ok'] ?? 0) ?></strong></div>
                        <div class="sl-data-list__row"><span>Doublons</span><strong><?= (int)($summary['duplicate'] ?? 0) ?></strong></div>
                        <div class="sl-data-list__row"><span>Erreurs</span><strong><?= (int)($summary['error'] ?? 0) ?></strong></div>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucun import prévisualisé pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($previewRows): ?>
            <div class="card" style="margin-top:20px;">
                <h3>Détail du contrôle</h3>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Ligne</th>
                                <th>Statut</th>
                                <th>Code</th>
                                <th>Intitulé</th>
                                <th>Type opération</th>
                                <th>Pays commercial</th>
                                <th>Pays destination</th>
                                <th>Solde courant</th>
                                <th>Postable</th>
                                <th>Actif</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewRows as $row): ?>
                                <?php $payload = $row['payload'] ?? []; ?>
                                <tr>
                                    <td><?= (int)($row['line'] ?? 0) ?></td>
                                    <td>
                                        <?php if (($row['status'] ?? '') === 'ok'): ?>
                                            <span class="badge badge-success">OK</span>
                                        <?php elseif (($row['status'] ?? '') === 'duplicate'): ?>
                                            <span class="badge badge-warning">Doublon</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Erreur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string)($payload['account_code'] ?? '')) ?></td>
                                    <td><?= e((string)($payload['account_label'] ?? '')) ?></td>
                                    <td><?= e((string)($payload['operation_type_label'] ?? '')) ?></td>
                                    <td><?= e((string)($payload['commercial_country_label'] ?? '')) ?></td>
                                    <td><?= e((string)($payload['destination_country_label'] ?? '')) ?></td>
                                    <td><?= e(isset($payload['current_balance']) ? number_format((float)$payload['current_balance'], 2, ',', ' ') : '') ?></td>
                                    <td><?= !empty($payload['is_postable']) ? 'Oui' : 'Non' ?></td>
                                    <td><?= !empty($payload['is_active']) ? 'Oui' : 'Non' ?></td>
                                    <td><?= e(!empty($row['messages']) ? implode(' | ', $row['messages']) : 'Prêt à importer') ?></td>
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