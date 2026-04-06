<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_create_page');
} else {
    enforcePagePermission($pdo, 'clients_create');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Import clients CSV';
$pageSubtitle = 'Prévisualisation et validation avant import définitif';

const SL_CLIENTS_IMPORT_SESSION_KEY = 'studelyledger_clients_import_preview_v1';

$originCountries = function_exists('studely_origin_countries') ? studely_origin_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [['code' => 'EUR', 'label' => 'Euro']];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryMap = [];
foreach ($treasuryAccounts as $ta) {
    $treasuryMap[(string)$ta['account_code']] = [
        'id' => (int)$ta['id'],
        'label' => trim((string)($ta['account_code'] ?? '') . ' - ' . (string)($ta['account_label'] ?? '')),
    ];
}

$errorMessage = '';
$successMessage = '';
$report = [];
$previewRows = [];
$summary = [
    'total' => 0,
    'ok' => 0,
    'error' => 0,
];

if (!function_exists('sl_parse_csv_file')) {
    function sl_parse_csv_file(string $path): array
    {
        $rows = [];
        if (!is_readable($path)) {
            return $rows;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            rewind($handle);
            $headers = fgetcsv($handle, 0, ',');
            if (!$headers) {
                fclose($handle);
                return [];
            }
            rewind($handle);
            $delimiter = ',';
        } else {
            $delimiter = ';';
        }

        fclose($handle);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return [];
        }

        $normalizedHeaders = array_map(static fn($h) => trim((string)$h), $headers);

        $line = 1;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($data, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($normalizedHeaders as $index => $header) {
                $row[$header] = trim((string)($data[$index] ?? ''));
            }
            $row['_line_number'] = $line;
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}

function sl_client_csv_preview_build(
    PDO $pdo,
    array $rows,
    array $originCountries,
    array $destinationCountries,
    array $commercialCountries,
    array $clientTypes,
    array $treasuryMap,
    array $currencies
): array {
    $currencyCodes = array_map(static fn($c) => (string)($c['code'] ?? ''), $currencies);

    $previewRows = [];
    $summary = ['total' => 0, 'ok' => 0, 'error' => 0];

    foreach ($rows as $row) {
        $summary['total']++;

        $line = (int)($row['_line_number'] ?? 0);
        $messages = [];
        $status = 'OK';

        $firstName = trim((string)($row['first_name'] ?? $row['prenom'] ?? ''));
        $lastName = trim((string)($row['last_name'] ?? $row['nom'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        $phone = trim((string)($row['phone'] ?? $row['telephone'] ?? ''));
        $postalAddress = trim((string)($row['postal_address'] ?? $row['adresse'] ?? ''));
        $passportNumber = trim((string)($row['passport_number'] ?? ''));
        $passportIssueCountry = trim((string)($row['passport_issue_country'] ?? ''));
        $passportIssueDate = trim((string)($row['passport_issue_date'] ?? ''));
        $passportExpiryDate = trim((string)($row['passport_expiry_date'] ?? ''));
        $clientType = trim((string)($row['client_type'] ?? ''));
        $countryOrigin = trim((string)($row['country_origin'] ?? ''));
        $countryDestination = trim((string)($row['country_destination'] ?? ''));
        $countryCommercial = trim((string)($row['country_commercial'] ?? ''));
        $currency = trim((string)($row['currency'] ?? 'EUR'));
        $treasuryCode = trim((string)($row['treasury_account_code'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            $messages[] = 'Nom / prénom manquants.';
        }

        if ($clientType === '' || !in_array($clientType, $clientTypes, true)) {
            $messages[] = 'Type de client invalide.';
        }

        if ($countryCommercial === '' || !in_array($countryCommercial, $commercialCountries, true)) {
            $messages[] = 'Pays commercial invalide.';
        }

        if ($countryOrigin !== '' && !in_array($countryOrigin, $originCountries, true)) {
            $messages[] = 'Pays d’origine invalide.';
        }

        if ($countryDestination !== '' && !in_array($countryDestination, $destinationCountries, true)) {
            $messages[] = 'Pays de destination invalide.';
        }

        if ($passportIssueCountry !== '' && !in_array($passportIssueCountry, $originCountries, true)) {
            $messages[] = 'Lieu de délivrance du passeport invalide.';
        }

        if ($passportIssueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $passportIssueDate)) {
            $messages[] = 'Date de délivrance du passeport invalide.';
        }

        if ($passportExpiryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $passportExpiryDate)) {
            $messages[] = 'Date d’expiration du passeport invalide.';
        }

        if ($passportIssueDate !== '' && $passportExpiryDate !== '' && $passportExpiryDate < $passportIssueDate) {
            $messages[] = 'Date d’expiration du passeport incohérente.';
        }

        if ($currency === '' || !in_array($currency, $currencyCodes, true)) {
            $messages[] = 'Devise invalide.';
        }

        if ($treasuryCode !== '' && !isset($treasuryMap[$treasuryCode])) {
            $messages[] = 'Compte 512 introuvable.';
        }

        $fullName = trim($firstName . ' ' . $lastName);
        $generatedClientCode = function_exists('generateClientCode') ? generateClientCode($pdo) : (string)random_int(100000000, 999999999);
        $generated411 = '411' . $generatedClientCode;

        if ($messages) {
            $status = 'ERREUR';
            $summary['error']++;
        } else {
            $summary['ok']++;
        }

        $previewRows[] = [
            'line' => $line,
            'status' => $status,
            'messages' => $messages,
            'raw' => $row,
            'normalized' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'postal_address' => $postalAddress !== '' ? $postalAddress : null,
                'passport_number' => $passportNumber !== '' ? $passportNumber : null,
                'passport_issue_country' => $passportIssueCountry !== '' ? $passportIssueCountry : null,
                'passport_issue_date' => $passportIssueDate !== '' ? $passportIssueDate : null,
                'passport_expiry_date' => $passportExpiryDate !== '' ? $passportExpiryDate : null,
                'client_type' => $clientType,
                'country_origin' => $countryOrigin !== '' ? $countryOrigin : null,
                'country_destination' => $countryDestination !== '' ? $countryDestination : null,
                'country_commercial' => $countryCommercial,
                'currency' => $currency !== '' ? $currency : 'EUR',
                'initial_treasury_account_id' => ($treasuryCode !== '' && isset($treasuryMap[$treasuryCode])) ? (int)$treasuryMap[$treasuryCode]['id'] : null,
                'treasury_account_label' => ($treasuryCode !== '' && isset($treasuryMap[$treasuryCode])) ? $treasuryMap[$treasuryCode]['label'] : '',
                'generated_client_code' => $generatedClientCode,
                'generated_client_account' => $generated411,
                'is_active' => 1,
            ],
        ];
    }

    return [$previewRows, $summary];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $action = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($action === 'preview') {
            if (empty($_FILES['csv_file']['tmp_name'])) {
                throw new RuntimeException('Aucun fichier CSV envoyé.');
            }

            $tmpPath = (string)$_FILES['csv_file']['tmp_name'];
            $rows = sl_parse_csv_file($tmpPath);

            if (!$rows) {
                throw new RuntimeException('Le fichier est vide ou illisible.');
            }

            [$previewRows, $summary] = sl_client_csv_preview_build(
                $pdo,
                $rows,
                $originCountries,
                $destinationCountries,
                $commercialCountries,
                $clientTypes,
                $treasuryMap,
                $currencies
            );

            $_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY] = [
                'rows' => $previewRows,
                'summary' => $summary,
                'file_name' => (string)($_FILES['csv_file']['name'] ?? 'import.csv'),
            ];
        }

        if ($action === 'confirm_import') {
            if (empty($_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]['rows']) || !is_array($_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]['rows'])) {
                throw new RuntimeException('Aucune prévisualisation disponible. Lance d’abord une prévisualisation.');
            }

            $previewRows = $_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]['rows'];
            $summary = $_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]['summary'] ?? $summary;

            $pdo->beginTransaction();

            $inserted = 0;
            $rejected = 0;
            $report = [];

            foreach ($previewRows as $item) {
                if (($item['status'] ?? '') !== 'OK') {
                    $rejected++;
                    $report[] = [
                        'line' => (int)($item['line'] ?? 0),
                        'status' => 'ERREUR',
                        'client' => trim((string)(($item['normalized']['full_name'] ?? ''))),
                        'message' => implode(' | ', (array)($item['messages'] ?? ['Ligne rejetée.'])),
                    ];
                    continue;
                }

                $data = $item['normalized'] ?? [];
                $clientCode = (string)($data['generated_client_code'] ?? '');
                $generated411 = (string)($data['generated_client_account'] ?? '');
                $fullName = trim((string)($data['full_name'] ?? ''));

                $columns = [];
                $values = [];
                $params = [];

                $map = [
                    'client_code' => $clientCode,
                    'generated_client_account' => $generated411,
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'full_name' => $fullName,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'postal_address' => $data['postal_address'] ?? null,
                    'passport_number' => $data['passport_number'] ?? null,
                    'passport_issue_country' => $data['passport_issue_country'] ?? null,
                    'passport_issue_date' => $data['passport_issue_date'] ?? null,
                    'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
                    'client_type' => $data['client_type'] ?? null,
                    'country_origin' => $data['country_origin'] ?? null,
                    'country_destination' => $data['country_destination'] ?? null,
                    'country_commercial' => $data['country_commercial'] ?? null,
                    'currency' => $data['currency'] ?? 'EUR',
                    'initial_treasury_account_id' => $data['initial_treasury_account_id'] ?? null,
                    'is_active' => 1,
                ];

                foreach ($map as $column => $value) {
                    if (columnExists($pdo, 'clients', $column)) {
                        $columns[] = $column;
                        $values[] = '?';
                        $params[] = $value;
                    }
                }

                if (columnExists($pdo, 'clients', 'created_at')) {
                    $columns[] = 'created_at';
                    $values[] = 'NOW()';
                }
                if (columnExists($pdo, 'clients', 'updated_at')) {
                    $columns[] = 'updated_at';
                    $values[] = 'NOW()';
                }

                $stmt = $pdo->prepare("
                    INSERT INTO clients (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $values) . ")
                ");
                $stmt->execute($params);

                $newId = (int)$pdo->lastInsertId();
                $inserted++;

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'client_import',
                        'clients',
                        'client',
                        $newId,
                        'Import CSV client : ' . $clientCode
                    );
                }

                $report[] = [
                    'line' => (int)($item['line'] ?? 0),
                    'status' => 'OK',
                    'client' => $clientCode . ' - ' . $fullName,
                    'message' => 'Client importé avec succès.',
                ];
            }

            $pdo->commit();

            unset($_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]);

            $successMessage = $inserted . ' client(s) importé(s), ' . $rejected . ' rejeté(s).';
        }

        if ($action === 'cancel_preview') {
            unset($_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]);
            $previewRows = [];
            $summary = ['total' => 0, 'ok' => 0, 'error' => 0];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

if (!$previewRows && !empty($_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]['rows'])) {
    $previewRows = $_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]['rows'];
    $summary = $_SESSION[SL_CLIENTS_IMPORT_SESSION_KEY]['summary'] ?? $summary;
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
                <h3>Chargement et validation CSV</h3>
                <p class="muted">
                    Colonnes supportées : first_name, last_name, email, phone, postal_address, passport_number,
                    passport_issue_country, passport_issue_date, passport_expiry_date, client_type, country_origin,
                    country_destination, country_commercial, currency, treasury_account_code
                </p>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <div>
                        <label>Fichier CSV</label>
                        <input type="file" name="csv_file" accept=".csv" <?= $previewRows ? '' : 'required' ?>>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>

                        <?php if ($previewRows): ?>
                            <button type="submit" name="action_mode" value="confirm_import" class="btn btn-success">Importer</button>
                            <button type="submit" name="action_mode" value="cancel_preview" class="btn btn-outline">Annuler</button>
                        <?php else: ?>
                            <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation avant validation</h3>

                <?php if ($previewRows): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Lignes analysées</span>
                            <strong><?= (int)$summary['total'] ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Lignes valides</span>
                            <strong><?= (int)$summary['ok'] ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Lignes en erreur</span>
                            <strong><?= (int)$summary['error'] ?></strong>
                        </div>
                    </div>

                    <div class="dashboard-note" style="margin-top:18px;">
                        Vérifie les lignes dans le tableau ci-dessous avant de confirmer l’import.
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Charge un fichier puis clique sur <strong>Prévisualiser</strong>.  
                        Le résumé de contrôle et les lignes analysées apparaîtront ici.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Détail des lignes</h3>

            <div class="sl-table-wrap">
                <table class="sl-table">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Statut</th>
                            <th>Client</th>
                            <th>Code généré</th>
                            <th>Compte 411 généré</th>
                            <th>Devise</th>
                            <th>Compte 512</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($previewRows): ?>
                            <?php foreach ($previewRows as $item): ?>
                                <?php $data = $item['normalized'] ?? []; ?>
                                <tr>
                                    <td><?= (int)($item['line'] ?? 0) ?></td>
                                    <td><?= e((string)($item['status'] ?? '')) ?></td>
                                    <td><?= e((string)($data['full_name'] ?? '')) ?></td>
                                    <td><?= e((string)($data['generated_client_code'] ?? '')) ?></td>
                                    <td><?= e((string)($data['generated_client_account'] ?? '')) ?></td>
                                    <td><?= e((string)($data['currency'] ?? '')) ?></td>
                                    <td><?= e((string)($data['treasury_account_label'] ?? '')) ?></td>
                                    <td><?= e(!empty($item['messages']) ? implode(' | ', (array)$item['messages']) : 'OK') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif ($report): ?>
                            <?php foreach ($report as $item): ?>
                                <tr>
                                    <td><?= (int)$item['line'] ?></td>
                                    <td><?= e($item['status']) ?></td>
                                    <td><?= e($item['client']) ?></td>
                                    <td colspan="4">—</td>
                                    <td><?= e($item['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">Aucune prévisualisation disponible.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>