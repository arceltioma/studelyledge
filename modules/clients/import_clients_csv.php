<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'clients_create');

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';
$report = [];

$treasuryLookup = [];
if (tableExists($pdo, 'treasury_accounts')) {
    $rows = $pdo->query("
        SELECT id, account_code
        FROM treasury_accounts
        WHERE COALESCE(is_active,1) = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $treasuryLookup[(string)$row['account_code']] = (int)$row['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        if (!is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            throw new RuntimeException('Aucun fichier CSV valide.');
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier.');
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('CSV vide.');
        }

        $headers = array_map(fn($h) => trim((string)$h), $headers);

        $created = 0;
        $updated = 0;
        $rejected = 0;
        $lineNo = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNo++;

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $data[$i] ?? null;
            }

            try {
                $clientCode = trim((string)($row['client_code'] ?? ''));
                $firstName = trim((string)($row['first_name'] ?? ''));
                $lastName = trim((string)($row['last_name'] ?? ''));
                $fullName = trim((string)($row['full_name'] ?? ''));
                $email = trim((string)($row['email'] ?? ''));
                $phone = trim((string)($row['phone'] ?? ''));
                $countryOrigin = trim((string)($row['country_origin'] ?? ''));
                $countryDestination = trim((string)($row['country_destination'] ?? ''));
                $countryCommercial = trim((string)($row['country_commercial'] ?? ''));
                $clientType = trim((string)($row['client_type'] ?? ''));
                $clientStatus = trim((string)($row['client_status'] ?? ''));
                $currency = trim((string)($row['currency'] ?? 'EUR'));
                $treasuryCode = trim((string)($row['treasury_account_code'] ?? ''));

                if (!preg_match('/^[0-9]{9}$/', $clientCode)) {
                    throw new RuntimeException('Code client invalide (9 chiffres requis).');
                }

                if ($firstName === '' || $lastName === '') {
                    throw new RuntimeException('Prénom ou nom manquant.');
                }

                if ($fullName === '') {
                    $fullName = trim($firstName . ' ' . $lastName);
                }

                if ($email === '') {
                    $email = strtolower(
                        preg_replace('/\s+/', '', $firstName) . '.' . preg_replace('/\s+/', '', $lastName) . '@studelyledger.com'
                    );
                }

                $treasuryId = null;
                if ($treasuryCode !== '') {
                    $treasuryId = $treasuryLookup[$treasuryCode] ?? null;
                }

                $generatedClientAccount = '411' . $clientCode;

                $stmtCheck = $pdo->prepare("
                    SELECT id
                    FROM clients
                    WHERE client_code = ?
                    LIMIT 1
                ");
                $stmtCheck->execute([$clientCode]);
                $existingId = $stmtCheck->fetchColumn();

                $pdo->beginTransaction();

                if ($existingId) {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE clients
                        SET
                            first_name = ?,
                            last_name = ?,
                            full_name = ?,
                            email = ?,
                            phone = ?,
                            country_origin = ?,
                            country_destination = ?,
                            country_commercial = ?,
                            client_type = ?,
                            client_status = ?,
                            currency = ?,
                            initial_treasury_account_id = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([
                        $firstName,
                        $lastName,
                        $fullName,
                        $email !== '' ? $email : null,
                        $phone !== '' ? $phone : null,
                        $countryOrigin !== '' ? $countryOrigin : null,
                        $countryDestination !== '' ? $countryDestination : null,
                        $countryCommercial !== '' ? $countryCommercial : null,
                        $clientType !== '' ? $clientType : null,
                        $clientStatus !== '' ? $clientStatus : null,
                        $currency !== '' ? $currency : 'EUR',
                        $treasuryId,
                        $existingId
                    ]);
                    $clientId = (int)$existingId;
                    $updated++;
                } else {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO clients (
                            client_code, first_name, last_name, full_name, email, phone,
                            country_origin, country_destination, country_commercial,
                            client_type, client_status, currency,
                            generated_client_account, initial_treasury_account_id,
                            is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmtInsert->execute([
                        $clientCode,
                        $firstName,
                        $lastName,
                        $fullName,
                        $email !== '' ? $email : null,
                        $phone !== '' ? $phone : null,
                        $countryOrigin !== '' ? $countryOrigin : null,
                        $countryDestination !== '' ? $countryDestination : null,
                        $countryCommercial !== '' ? $countryCommercial : null,
                        $clientType !== '' ? $clientType : null,
                        $clientStatus !== '' ? $clientStatus : null,
                        $currency !== '' ? $currency : 'EUR',
                        $generatedClientAccount,
                        $treasuryId
                    ]);
                    $clientId = (int)$pdo->lastInsertId();
                    $created++;
                }

                $bank = findPrimaryBankAccountForClient($pdo, $clientId);

                if (!$bank) {
                    $stmtBank = $pdo->prepare("
                        INSERT INTO bank_accounts (
                            account_number, bank_name, country, initial_balance, balance, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmtBank->execute([
                        $generatedClientAccount,
                        'Compte Client Interne',
                        'France',
                        15000.00,
                        15000.00
                    ]);

                    $bankAccountId = (int)$pdo->lastInsertId();

                    $stmtLink = $pdo->prepare("
                        INSERT INTO client_bank_accounts (client_id, bank_account_id, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    $stmtLink->execute([$clientId, $bankAccountId]);
                }

                $pdo->commit();
            } catch (Throwable $rowError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $rejected++;
                $report[] = 'Ligne ' . $lineNo . ' rejetée : ' . $rowError->getMessage();
            }
        }

        fclose($handle);

        $successMessage = "Import terminé. Créés : {$created}, mis à jour : {$updated}, rejetés : {$rejected}.";
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Import CSV clients',
            'Création ou mise à jour en masse des clients, avec génération automatique des comptes liés.'
        ); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Importer</h3>

                <form method="POST" enctype="multipart/form-data">
                    <label>Fichier CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Importer les clients</button>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Colonnes attendues</h3>
                <div class="dashboard-note">
                    client_code ; first_name ; last_name ; full_name ; email ; phone ; country_origin ; country_destination ; country_commercial ; client_type ; client_status ; currency ; treasury_account_code
                </div>
            </div>
        </div>

        <?php if ($report): ?>
            <div class="table-card" style="margin-top:20px;">
                <h3 class="section-title">Rejets</h3>
                <ul>
                    <?php foreach ($report as $line): ?>
                        <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>