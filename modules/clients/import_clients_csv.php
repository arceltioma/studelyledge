<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'clients_import_page');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Import clients CSV';
$pageSubtitle = 'Import sécurisé de fiches clients avec création automatique du compte 411 et de sa liaison';

if (!function_exists('sl_client_fetch_currencies')) {
    function sl_client_fetch_currencies(PDO $pdo): array
    {
        if (function_exists('sl_get_currency_options')) {
            $currencies = sl_get_currency_options($pdo);
            if (is_array($currencies) && $currencies) {
                return $currencies;
            }
        }

        if (tableExists($pdo, 'currencies')) {
            $stmt = $pdo->query("
                SELECT code, label
                FROM currencies
                WHERE COALESCE(is_active,1)=1
                ORDER BY code ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [['code' => 'EUR', 'label' => 'Euro']];
    }
}

if (!function_exists('sl_import_generate_next_client_code')) {
    function sl_import_generate_next_client_code(PDO $pdo): string
    {
        if (function_exists('generateClientCode')) {
            return (string)generateClientCode($pdo);
        }

        $stmt = $pdo->query("
            SELECT client_code
            FROM clients
            WHERE client_code LIKE 'CLT%'
            ORDER BY id DESC
            LIMIT 1
        ");
        $lastCode = (string)($stmt->fetchColumn() ?: '');

        if (preg_match('/CLT(\d+)/', $lastCode, $m)) {
            $next = ((int)$m[1]) + 1;
            return 'CLT' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        }

        return 'CLT0001';
    }
}

if (!function_exists('sl_import_create_or_link_client_bank_account')) {
    function sl_import_create_or_link_client_bank_account(
        PDO $pdo,
        int $clientId,
        string $clientCode,
        string $fullName,
        string $accountNumber,
        string $country,
        float $initialBalance,
        float $balance,
        int $isActive
    ): int {
        $stmt = $pdo->prepare("
            SELECT *
            FROM bank_accounts
            WHERE account_number = ?
            LIMIT 1
        ");
        $stmt->execute([$accountNumber]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $fields = [];
            $params = [];

            $map = [
                'account_name' => 'Compte client ' . $clientCode . ' - ' . $fullName,
                'bank_name' => 'Compte client interne',
                'country' => $country !== '' ? $country : null,
                'initial_balance' => $initialBalance,
                'balance' => $balance,
                'is_active' => $isActive,
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'bank_accounts', $column)) {
                    $fields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'bank_accounts', 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            if ($fields) {
                $params[] = (int)$existing['id'];
                $stmt = $pdo->prepare("
                    UPDATE bank_accounts
                    SET " . implode(', ', $fields) . "
                    WHERE id = ?
                ");
                $stmt->execute($params);
            }

            $bankAccountId = (int)$existing['id'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO bank_accounts (
                    account_name,
                    account_number,
                    bank_name,
                    country,
                    initial_balance,
                    balance,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                'Compte client ' . $clientCode . ' - ' . $fullName,
                $accountNumber,
                'Compte client interne',
                $country !== '' ? $country : null,
                $initialBalance,
                $balance,
                $isActive
            ]);
            $bankAccountId = (int)$pdo->lastInsertId();
        }

        if (tableExists($pdo, 'client_bank_accounts')) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM client_bank_accounts
                WHERE client_id = ? AND bank_account_id = ?
                LIMIT 1
            ");
            $stmt->execute([$clientId, $bankAccountId]);
            $link = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$link) {
                $stmt = $pdo->prepare("
                    INSERT INTO client_bank_accounts (client_id, bank_account_id, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$clientId, $bankAccountId]);
            }
        }

        return $bankAccountId;
    }
}

$originCountries = function_exists('studely_origin_countries') ? studely_origin_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$currencyOptions = sl_client_fetch_currencies($pdo);
$allowedCurrencyCodes = array_map(static fn(array $row): string => (string)$row['code'], $currencyOptions);

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryMap = [];
foreach ($treasuryAccounts as $ta) {
    $treasuryMap[(string)$ta['account_code']] = (int)$ta['id'];
}

$report = [];
$errorMessage = '';
$successMessage = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            throw new RuntimeException('Aucun fichier CSV envoyé.');
        }

        $tmpPath = (string)$_FILES['csv_file']['tmp_name'];
        $rows = sl_parse_csv_file($tmpPath);

        if (!$rows) {
            throw new RuntimeException('Le fichier est vide ou illisible.');
        }

        $pdo->beginTransaction();

        $inserted = 0;
        $rejected = 0;

        foreach ($rows as $row) {
            try {
                $clientCode = trim((string)($row['client_code'] ?? ''));
                if ($clientCode === '') {
                    $clientCode = sl_import_generate_next_client_code($pdo);
                }

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
                $generatedClientAccount = trim((string)($row['generated_client_account'] ?? ''));
                $initialBalance = (float)str_replace(',', '.', (string)($row['initial_balance'] ?? '0'));
                $balance = (float)str_replace(',', '.', (string)($row['balance'] ?? (string)$initialBalance));

                if ($firstName === '' || $lastName === '') {
                    throw new RuntimeException('Nom / prénom manquants.');
                }

                if ($clientType === '' || ($clientTypes && !in_array($clientType, $clientTypes, true))) {
                    throw new RuntimeException('Type de client invalide.');
                }

                if ($countryCommercial === '' || ($commercialCountries && !in_array($countryCommercial, $commercialCountries, true))) {
                    throw new RuntimeException('Pays commercial invalide.');
                }

                if ($countryOrigin !== '' && $originCountries && !in_array($countryOrigin, $originCountries, true)) {
                    throw new RuntimeException('Pays d’origine invalide.');
                }

                if ($countryDestination !== '' && $destinationCountries && !in_array($countryDestination, $destinationCountries, true)) {
                    throw new RuntimeException('Pays de destination invalide.');
                }

                if ($passportIssueCountry !== '' && $originCountries && !in_array($passportIssueCountry, $originCountries, true)) {
                    throw new RuntimeException('Lieu de délivrance du passport invalide.');
                }

                if ($passportIssueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $passportIssueDate)) {
                    throw new RuntimeException('Date de délivrance du passport invalide.');
                }

                if ($passportExpiryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $passportExpiryDate)) {
                    throw new RuntimeException('Date d’expiration du passport invalide.');
                }

                if ($passportIssueDate !== '' && $passportExpiryDate !== '' && $passportExpiryDate < $passportIssueDate) {
                    throw new RuntimeException('Date d’expiration du passport incohérente.');
                }

                if ($currency === '' || !in_array($currency, $allowedCurrencyCodes, true)) {
                    throw new RuntimeException('Devise invalide.');
                }

                if ($generatedClientAccount === '') {
                    $generatedClientAccount = '411' . $clientCode;
                }

                if (!preg_match('/^411[A-Za-z0-9]+$/', $generatedClientAccount)) {
                    throw new RuntimeException('Compte 411 invalide.');
                }

                $stmtCheckCode = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE client_code = ?");
                $stmtCheckCode->execute([$clientCode]);
                if ((int)$stmtCheckCode->fetchColumn() > 0) {
                    throw new RuntimeException('Code client déjà existant.');
                }

                $stmtCheck411 = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE generated_client_account = ?");
                $stmtCheck411->execute([$generatedClientAccount]);
                if ((int)$stmtCheck411->fetchColumn() > 0) {
                    throw new RuntimeException('Compte 411 déjà existant.');
                }

                $fullName = trim($firstName . ' ' . $lastName);
                $initialTreasuryAccountId = $treasuryCode !== '' && isset($treasuryMap[$treasuryCode]) ? $treasuryMap[$treasuryCode] : null;

                $columns = [];
                $values = [];
                $params = [];

                $map = [
                    'client_code' => $clientCode,
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
                    'currency' => $currency,
                    'generated_client_account' => $generatedClientAccount,
                    'initial_treasury_account_id' => $initialTreasuryAccountId,
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

                sl_import_create_or_link_client_bank_account(
                    $pdo,
                    $newId,
                    $clientCode,
                    $fullName,
                    $generatedClientAccount,
                    $countryCommercial,
                    $initialBalance,
                    $balance,
                    1
                );

                $inserted++;

                $report[] = [
                    'line' => (int)($row['_line_number'] ?? 0),
                    'status' => 'OK',
                    'message' => 'Client importé avec compte 411 créé / lié',
                    'client_code' => $clientCode,
                    'name' => $fullName,
                ];

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
            } catch (Throwable $rowError) {
                $rejected++;
                $report[] = [
                    'line' => (int)($row['_line_number'] ?? 0),
                    'status' => 'ERREUR',
                    'message' => $rowError->getMessage(),
                    'client_code' => '',
                    'name' => trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))),
                ];
            }
        }

        $pdo->commit();
        $successMessage = $inserted . ' client(s) importé(s), ' . $rejected . ' rejeté(s).';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
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
                <h3>Importer un fichier CSV</h3>
                <p class="muted">
                    Colonnes supportées : client_code, first_name, last_name, email, phone, postal_address,
                    passport_number, passport_issue_country, passport_issue_date, passport_expiry_date,
                    client_type, country_origin, country_destination, country_commercial, currency,
                    treasury_account_code, generated_client_account, initial_balance, balance
                </p>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>
                    <div>
                        <label>Fichier CSV</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Importer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Résultat import</h3>
                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Ligne</th>
                                <th>Statut</th>
                                <th>Client</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($report): ?>
                                <?php foreach ($report as $item): ?>
                                    <tr>
                                        <td><?= (int)$item['line'] ?></td>
                                        <td><?= e($item['status']) ?></td>
                                        <td><?= e(trim(($item['client_code'] !== '' ? $item['client_code'] . ' - ' : '') . ($item['name'] ?? ''))) ?></td>
                                        <td><?= e($item['message']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">Aucun traitement effectué.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="dashboard-note" style="margin-top:16px;">
                    Chaque client importé crée aussi son compte 411 dans <code>bank_accounts</code> puis la liaison dans <code>client_bank_accounts</code>.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>