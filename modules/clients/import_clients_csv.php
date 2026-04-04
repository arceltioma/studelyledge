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
$pageSubtitle = 'Import sécurisé de fiches clients avec contrôle des champs passeport';

$originCountries = function_exists('studely_origin_countries') ? studely_origin_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];

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
                    throw new RuntimeException('Nom / prénom manquants.');
                }

                if ($clientType === '' || !in_array($clientType, $clientTypes, true)) {
                    throw new RuntimeException('Type de client invalide.');
                }

                if ($countryCommercial === '' || !in_array($countryCommercial, $commercialCountries, true)) {
                    throw new RuntimeException('Pays commercial invalide.');
                }

                if ($countryOrigin !== '' && !in_array($countryOrigin, $originCountries, true)) {
                    throw new RuntimeException('Pays d’origine invalide.');
                }

                if ($countryDestination !== '' && !in_array($countryDestination, $destinationCountries, true)) {
                    throw new RuntimeException('Pays de destination invalide.');
                }

                if ($passportIssueCountry !== '' && !in_array($passportIssueCountry, $originCountries, true)) {
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

                $clientCode = function_exists('generateClientCode') ? generateClientCode($pdo) : (string)random_int(100000000, 999999999);
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
                    'currency' => $currency !== '' ? $currency : 'EUR',
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
                $inserted++;

                $report[] = [
                    'line' => (int)($row['_line_number'] ?? 0),
                    'status' => 'OK',
                    'message' => 'Client importé',
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
                    Colonnes supportées : first_name, last_name, email, phone, postal_address, passport_number,
                    passport_issue_country, passport_issue_date, passport_expiry_date, client_type, country_origin,
                    country_destination, country_commercial, currency, treasury_account_code
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
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>