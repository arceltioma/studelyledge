<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_create');

function csv_normalize_header(string $value): string
{
    $value = trim($value);
    $value = mb_strtolower($value, 'UTF-8');
    $value = str_replace([' ', '-', '/'], '_', $value);
    return $value;
}

function csv_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return '';
}

$successMessage = '';
$errorMessage = '';
$report = [];

$originCountries = studely_origin_countries();
$destinationCountries = studely_destination_countries();
$commercialCountries = studely_commercial_countries();
$clientTypes = studely_client_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

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

        $headers = array_map(fn($h) => csv_normalize_header((string)$h), $headers);

        $created = 0;
        $updated = 0;
        $rejected = 0;
        $lineNo = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNo++;

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = trim((string)($data[$i] ?? ''));
            }

            try {
                $providedClientCode = csv_value($row, ['client_code', 'code_client']);
                $firstName = csv_value($row, ['first_name', 'prenom']);
                $lastName = csv_value($row, ['last_name', 'nom']);
                $fullName = csv_value($row, ['full_name', 'nom_complet']);
                $email = csv_value($row, ['email']);
                $phone = csv_value($row, ['phone', 'telephone']);
                $countryOrigin = csv_value($row, ['country_origin', 'pays_origine']);
                $countryDestination = csv_value($row, ['country_destination', 'pays_destination']);
                $countryCommercial = csv_value($row, ['country_commercial', 'pays_commercial']);
                $clientType = csv_value($row, ['client_type', 'type_client']);
                $clientStatus = csv_value($row, ['client_status', 'statut_client']);
                $currency = csv_value($row, ['currency', 'devise']);
                $providedTreasuryCode = csv_value($row, ['treasury_account_code', 'compte_512']);

                if ($firstName === '' || $lastName === '') {
                    throw new RuntimeException('Prénom ou nom manquant.');
                }

                if ($fullName === '') {
                    $fullName = trim($firstName . ' ' . $lastName);
                }

                if ($clientType === '' || !in_array($clientType, $clientTypes, true)) {
                    throw new RuntimeException('Type client invalide.');
                }

                if ($countryOrigin === '' || !in_array($countryOrigin, $originCountries, true)) {
                    throw new RuntimeException('Pays d’origine invalide.');
                }

                if ($countryDestination === '' || !in_array($countryDestination, $destinationCountries, true)) {
                    throw new RuntimeException('Pays de destination invalide.');
                }

                if ($countryCommercial === '' || !in_array($countryCommercial, $commercialCountries, true)) {
                    throw new RuntimeException('Pays commercial invalide.');
                }

                if ($currency === '') {
                    $currency = 'EUR';
                }

                if ($email === '') {
                    $email = strtolower(
                        preg_replace('/\s+/', '', $firstName) . '.' . preg_replace('/\s+/', '', $lastName) . '@studelyledger.com'
                    );
                }

                $treasury = studely_resolve_default_treasury_account($pdo, $countryCommercial, $providedTreasuryCode !== '' ? $providedTreasuryCode : null);
                if (!$treasury) {
                    throw new RuntimeException('Impossible de résoudre un compte 512 actif pour ce pays commercial.');
                }

                if ($providedTreasuryCode !== '' && ($treasury['account_code'] ?? '') !== $providedTreasuryCode) {
                    throw new RuntimeException('Le compte 512 fourni ne correspond pas au compte autorisé pour ce pays commercial.');
                }

                $clientCode = $providedClientCode;
                if ($clientCode !== '') {
                    if (!preg_match('/^[0-9]{9}$/', $clientCode)) {
                        throw new RuntimeException('Code client invalide (9 chiffres requis).');
                    }
                }

                $pdo->beginTransaction();

                $existingId = null;
                if ($clientCode !== '') {
                    $stmtCheck = $pdo->prepare("
                        SELECT id
                        FROM clients
                        WHERE client_code = ?
                        LIMIT 1
                    ");
                    $stmtCheck->execute([$clientCode]);
                    $existingId = $stmtCheck->fetchColumn();
                }

                if ($existingId) {
                    $generatedClientAccount = '411' . $clientCode;

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
                            generated_client_account = ?,
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
                        $countryOrigin,
                        $countryDestination,
                        $countryCommercial,
                        $clientType,
                        $clientStatus !== '' ? $clientStatus : null,
                        $currency,
                        $generatedClientAccount,
                        (int)$treasury['id'],
                        (int)$existingId,
                    ]);

                    studely_create_or_link_client_bank_account(
                        $pdo,
                        (int)$existingId,
                        $generatedClientAccount,
                        $countryCommercial,
                        'Compte client ' . $generatedClientAccount
                    );

                    $updated++;
                } else {
                    if ($clientCode === '') {
                        $clientCode = studely_generate_next_client_code($pdo);
                    }

                    $generatedClientAccount = '411' . $clientCode;

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO clients (
                            client_code,
                            first_name,
                            last_name,
                            full_name,
                            email,
                            phone,
                            country_origin,
                            country_destination,
                            country_commercial,
                            client_type,
                            client_status,
                            currency,
                            generated_client_account,
                            initial_treasury_account_id,
                            is_active,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmtInsert->execute([
                        $clientCode,
                        $firstName,
                        $lastName,
                        $fullName,
                        $email !== '' ? $email : null,
                        $phone !== '' ? $phone : null,
                        $countryOrigin,
                        $countryDestination,
                        $countryCommercial,
                        $clientType,
                        $clientStatus !== '' ? $clientStatus : null,
                        $currency,
                        $generatedClientAccount,
                        (int)$treasury['id'],
                    ]);

                    $clientId = (int)$pdo->lastInsertId();

                    studely_create_or_link_client_bank_account(
                        $pdo,
                        $clientId,
                        $generatedClientAccount,
                        $countryCommercial,
                        'Compte client ' . $generatedClientAccount
                    );

                    $created++;
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

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'import_clients_csv',
                'clients',
                'import',
                null,
                "Import clients terminé. Créés : {$created}, mis à jour : {$updated}, rejetés : {$rejected}."
            );
        }

        $successMessage = "Import terminé. Créés : {$created}, mis à jour : {$updated}, rejetés : {$rejected}.";
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Import CSV clients';
$pageSubtitle = 'Le code client, le compte 411 et le compte 512 sont alignés sur les règles métier.';
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
                <h3 class="section-title">Importer</h3>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <label>Fichier CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Importer les clients</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Colonnes acceptées</h3>
                <div class="dashboard-note">
                    client_code (optionnel pour un nouveau client) ; first_name ; last_name ; full_name ; email ; phone ;
                    country_origin ; country_destination ; country_commercial ; client_type ; client_status ; currency ; treasury_account_code (optionnel, doit rester cohérent avec le pays commercial)
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

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>