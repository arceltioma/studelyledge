<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'treasury_view');

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';
$report = [];

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
                $accountCode = trim((string)($row['account_code'] ?? ''));
                $accountLabel = trim((string)($row['account_label'] ?? ''));
                $bankName = trim((string)($row['bank_name'] ?? $accountLabel));
                $subsidiaryName = trim((string)($row['subsidiary_name'] ?? ''));
                $zoneCode = trim((string)($row['zone_code'] ?? ''));
                $countryLabel = trim((string)($row['country_label'] ?? 'France'));
                $countryType = trim((string)($row['country_type'] ?? 'Filiale'));
                $paymentPlace = trim((string)($row['payment_place'] ?? 'Local'));
                $currencyCode = trim((string)($row['currency_code'] ?? 'EUR'));

                if ($accountCode === '' || $accountLabel === '') {
                    throw new RuntimeException('Code ou libellé manquant.');
                }

                $stmtCheck = $pdo->prepare("SELECT id FROM treasury_accounts WHERE account_code = ? LIMIT 1");
                $stmtCheck->execute([$accountCode]);
                $existingId = $stmtCheck->fetchColumn();

                if ($existingId) {
                    $stmt = $pdo->prepare("
                        UPDATE treasury_accounts
                        SET
                            account_label = ?,
                            bank_name = ?,
                            subsidiary_name = ?,
                            zone_code = ?,
                            country_label = ?,
                            country_type = ?,
                            payment_place = ?,
                            currency_code = ?,
                            opening_balance = 1000000.00,
                            current_balance = 1000000.00,
                            is_active = 1
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $accountLabel,
                        $bankName,
                        $subsidiaryName !== '' ? $subsidiaryName : null,
                        $zoneCode !== '' ? $zoneCode : null,
                        $countryLabel,
                        $countryType,
                        $paymentPlace,
                        $currencyCode,
                        $existingId
                    ]);
                    $updated++;
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO treasury_accounts (
                            account_code, account_label, bank_name, subsidiary_name,
                            zone_code, country_label, country_type, payment_place,
                            currency_code, opening_balance, current_balance, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1000000.00, 1000000.00, 1, NOW())
                    ");
                    $stmt->execute([
                        $accountCode,
                        $accountLabel,
                        $bankName,
                        $subsidiaryName !== '' ? $subsidiaryName : null,
                        $zoneCode !== '' ? $zoneCode : null,
                        $countryLabel,
                        $countryType,
                        $paymentPlace,
                        $currencyCode
                    ]);
                    $created++;
                }
            } catch (Throwable $rowError) {
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
            'Import CSV comptes internes',
            'Chargement en masse des comptes 512.'
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
                        <button type="submit" class="btn btn-success">Importer</button>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Colonnes attendues</h3>
                <div class="dashboard-note">
                    account_code ; account_label ; bank_name ; subsidiary_name ; zone_code ; country_label ; country_type ; payment_place ; currency_code
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