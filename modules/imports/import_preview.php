<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'imports_preview');

require_once __DIR__ . '/../../includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function normalizeHeader(string $value): string
{
    $value = trim($value);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = strtolower((string)$value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function detectDelimiter(string $line): string
{
    $candidates = [';', ',', "\t", '|'];
    $best = ';';
    $bestCount = -1;

    foreach ($candidates as $delimiter) {
        $count = substr_count($line, $delimiter);
        if ($count > $bestCount) {
            $bestCount = $count;
            $best = $delimiter;
        }
    }

    return $best;
}

function normalizeFrenchAmount(?string $raw): ?float
{
    $value = trim((string)$raw);
    if ($value === '') {
        return null;
    }

    $value = str_replace(["\xc2\xa0", ' ', '€', "'"], '', $value);

    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (str_contains($value, ',')) {
        $value = str_replace(',', '.', $value);
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float)$value, 2);
}

function normalizeImportedDate(?string $raw): ?string
{
    $value = trim((string)$raw);
    if ($value === '') {
        return null;
    }

    $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

function detectImportedColumns(array $headers): array
{
    $map = [
        'date' => null,
        'value_date' => null,
        'label' => null,
        'debit' => null,
        'credit' => null,
        'amount' => null,
        'balance' => null,
        'reference' => null,
        'client_code' => null,
        'account_code' => null,
    ];

    $aliases = [
        'date' => ['date', 'date_operation', 'date_op', 'date_ecriture'],
        'value_date' => ['date_valeur', 'valeur', 'value_date'],
        'label' => ['libelle', 'label', 'intitule', 'description', 'operation'],
        'debit' => ['debit', 'sortie', 'montant_debit'],
        'credit' => ['credit', 'entree', 'montant_credit'],
        'amount' => ['montant', 'amount', 'somme'],
        'balance' => ['solde', 'balance'],
        'reference' => ['reference', 'ref', 'piece'],
        'client_code' => ['client_code', 'code_client'],
        'account_code' => ['account_code', 'compte', 'compte_interne', 'compte_tresorerie'],
    ];

    foreach ($headers as $original => $normalized) {
        foreach ($aliases as $target => $list) {
            if ($map[$target] === null && in_array($normalized, $list, true)) {
                $map[$target] = $original;
            }
        }
    }

    return $map;
}

function extractClientCodeFromLabel(string $label): ?string
{
    if (preg_match('/\b([0-9]{9})\b/', $label, $m)) {
        return $m[1];
    }
    return null;
}

function guessOperationTypeCode(string $label, ?float $debit, ?float $credit, ?string $clientCode): string
{
    $value = strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label));

    if (str_contains($value, 'virement interne')) {
        return 'VIREMENT_INTERNE';
    }
    if (str_contains($value, 'regularisation positive')) {
        return 'REGULARISATION_POSITIVE';
    }
    if (str_contains($value, 'regularisation negative')) {
        return 'REGULARISATION_NEGATIVE';
    }
    if (str_contains($value, 'frais bancaire')) {
        return 'FRAIS_BANCAIRES';
    }
    if (
        str_contains($value, 'frais') ||
        str_contains($value, 'commission') ||
        str_contains($value, 'avi') ||
        str_contains($value, 'ats')
    ) {
        return 'FRAIS_DE_SERVICE';
    }

    if ($credit !== null && $credit > 0 && $clientCode) {
        return 'VERSEMENT';
    }

    if ($debit !== null && $debit > 0 && $clientCode) {
        return 'VIREMENT_MENSUEL';
    }

    return 'VIREMENT_INTERNE';
}

function guessServiceCode(string $label): ?string
{
    $value = strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label));

    if (str_contains($value, 'avi')) {
        return 'AVI';
    }
    if (str_contains($value, 'gestion')) {
        return 'GESTION';
    }
    if (str_contains($value, 'transfert')) {
        return 'TRANSFERT';
    }
    if (str_contains($value, 'ats')) {
        return 'ATS';
    }
    if (str_contains($value, 'placement')) {
        return 'PLACEMENT';
    }
    if (str_contains($value, 'divers')) {
        return 'DIVERS';
    }

    return null;
}

function findClientByCodePreview(PDO $pdo, ?string $clientCode): ?array
{
    if (!$clientCode) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, client_code, full_name
        FROM clients
        WHERE client_code = ?
        LIMIT 1
    ");
    $stmt->execute([$clientCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function findTreasuryPreview(PDO $pdo, ?int $forcedTreasuryId, ?string $accountCode): ?array
{
    if ($forcedTreasuryId) {
        $stmt = $pdo->prepare("
            SELECT id, account_code, account_label
            FROM treasury_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$forcedTreasuryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    if ($accountCode) {
        $stmt = $pdo->prepare("
            SELECT id, account_code, account_label
            FROM treasury_accounts
            WHERE account_code = ?
            LIMIT 1
        ");
        $stmt->execute([$accountCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    return null;
}

$successMessage = '';
$errorMessage = '';
$previewRows = [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_services
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['statement_file'])) {
    try {
        if (!is_uploaded_file($_FILES['statement_file']['tmp_name'])) {
            throw new RuntimeException('Aucun fichier valide transmis.');
        }

        $forcedTreasuryId = ($_POST['forced_treasury_account_id'] ?? '') !== '' ? (int)$_POST['forced_treasury_account_id'] : null;
        $lines = file($_FILES['statement_file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines || empty($lines[0])) {
            throw new RuntimeException('Le fichier est vide.');
        }

        $delimiter = detectDelimiter($lines[0]);
        $handle = fopen($_FILES['statement_file']['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier.');
        }

        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (!$headerRow) {
            fclose($handle);
            throw new RuntimeException('Aucune ligne d’en-tête détectée.');
        }

        $headers = [];
        foreach ($headerRow as $header) {
            $headers[$header] = normalizeHeader((string)$header);
        }

        $detected = detectImportedColumns($headers);
        $rowNo = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNo++;
            $assoc = [];
            foreach (array_keys($headers) as $i => $originalHeader) {
                $assoc[$originalHeader] = $data[$i] ?? null;
            }

            $operationDate = normalizeImportedDate($assoc[$detected['date']] ?? null);
            $valueDate = normalizeImportedDate($assoc[$detected['value_date']] ?? null);
            $label = trim((string)($assoc[$detected['label']] ?? ''));
            $debit = normalizeFrenchAmount($assoc[$detected['debit']] ?? null);
            $credit = normalizeFrenchAmount($assoc[$detected['credit']] ?? null);
            $balance = normalizeFrenchAmount($assoc[$detected['balance']] ?? null);
            $amount = normalizeFrenchAmount($assoc[$detected['amount']] ?? null);
            $reference = trim((string)($assoc[$detected['reference']] ?? ''));
            $accountCode = trim((string)($assoc[$detected['account_code']] ?? ''));
            $clientCode = trim((string)($assoc[$detected['client_code']] ?? ''));

            if ($clientCode === '') {
                $clientCode = extractClientCodeFromLabel($label) ?? '';
            }

            if ($amount !== null && ($debit === null && $credit === null)) {
                if ($amount < 0) {
                    $debit = abs($amount);
                } else {
                    $credit = $amount;
                }
            }

            $client = findClientByCodePreview($pdo, $clientCode !== '' ? $clientCode : null);
            $treasury = findTreasuryPreview($pdo, $forcedTreasuryId, $accountCode !== '' ? $accountCode : null);

            $operationTypeCode = guessOperationTypeCode($label, $debit, $credit, $client['client_code'] ?? null);
            $serviceCode = guessServiceCode($label);

            $status = 'ok';
            $statusReason = '';

            if (!$operationDate) {
                $status = 'rejected';
                $statusReason = 'Date introuvable';
            } elseif (($debit === null || $debit == 0) && ($credit === null || $credit == 0)) {
                $status = 'rejected';
                $statusReason = 'Montant introuvable';
            } elseif ($operationTypeCode !== 'VIREMENT_INTERNE' && !$client) {
                $status = 'ambiguous';
                $statusReason = 'Client non identifié';
            } elseif (!$treasury) {
                $status = 'ambiguous';
                $statusReason = 'Compte interne non identifié';
            }

            $previewRows[] = [
                'row_no' => $rowNo,
                'operation_date' => $operationDate,
                'value_date' => $valueDate,
                'label' => $label,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'reference' => $reference,
                'client_id' => $client['id'] ?? null,
                'client_code' => $client['client_code'] ?? $clientCode,
                'client_name' => $client['full_name'] ?? null,
                'treasury_account_id' => $treasury['id'] ?? null,
                'treasury_account_code' => $treasury['account_code'] ?? null,
                'treasury_account_label' => $treasury['account_label'] ?? null,
                'operation_type_code' => $operationTypeCode,
                'service_code' => $serviceCode,
                'status' => $status,
                'status_reason' => $statusReason,
            ];
        }

        fclose($handle);

        $_SESSION['statement_import_preview'] = [
            'rows' => $previewRows,
            'file_name' => $_FILES['statement_file']['name'] ?? 'statement.csv',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $successMessage = 'Prévisualisation générée.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
} elseif (!empty($_SESSION['statement_import_preview']['rows'])) {
    $previewRows = $_SESSION['statement_import_preview']['rows'];
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Prévisualisation des relevés bancaires',
            'On détecte, on normalise, on rattache, puis seulement après on valide.'
        ); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Charger un relevé</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div>
                        <label>Fichier CSV / TXT</label>
                        <input type="file" name="statement_file" accept=".csv,.txt" required>
                    </div>

                    <div style="margin-top:16px;">
                        <label>Compte interne source (optionnel)</label>
                        <select name="forced_treasury_account_id">
                            <option value="">Détection automatique</option>
                            <?php foreach ($treasuryAccounts as $ta): ?>
                                <option value="<?= (int)$ta['id'] ?>">
                                    <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-primary">Analyser le fichier</button>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Ce que fait le parseur</h3>
                <div class="dashboard-note">
                    Détection souple des colonnes, gestion débit/crédit/solde, normalisation des montants français, reconnaissance client et compte interne.
                </div>
            </div>
        </div>

        <?php if ($previewRows): ?>
            <form method="POST" action="<?= APP_URL ?>modules/imports/import_validate.php">
                <div class="table-card" style="margin-top:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                        <h3 class="section-title">Prévisualisation</h3>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-success">Valider l’import</button>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Importer</th>
                                <th>Ligne</th>
                                <th>Date</th>
                                <th>Libellé</th>
                                <th>Débit</th>
                                <th>Crédit</th>
                                <th>Solde</th>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Service</th>
                                <th>Compte interne</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewRows as $idx => $row): ?>
                                <?php
                                $statusClass = $row['status'] === 'ok'
                                    ? 'status-success'
                                    : ($row['status'] === 'ambiguous' ? 'status-warning' : 'status-danger');
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_rows[]" value="<?= (int)$idx ?>" <?= $row['status'] === 'rejected' ? '' : 'checked' ?>>
                                    </td>
                                    <td><?= (int)$row['row_no'] ?></td>
                                    <td><?= e($row['operation_date'] ?? '') ?></td>
                                    <td><?= e($row['label'] ?? '') ?></td>
                                    <td><?= $row['debit'] !== null ? number_format((float)$row['debit'], 2, ',', ' ') : '—' ?></td>
                                    <td><?= $row['credit'] !== null ? number_format((float)$row['credit'], 2, ',', ' ') : '—' ?></td>
                                    <td><?= $row['balance'] !== null ? number_format((float)$row['balance'], 2, ',', ' ') : '—' ?></td>

                                    <td>
                                        <select name="row_client_id[<?= (int)$idx ?>]">
                                            <option value="">Aucun</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?= (int)$client['id'] ?>" <?= (string)($row['client_id'] ?? '') === (string)$client['id'] ? 'selected' : '' ?>>
                                                    <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="row_operation_type_code[<?= (int)$idx ?>]">
                                            <?php foreach ($operationTypes as $type): ?>
                                                <option value="<?= e($type['code']) ?>" <?= ($row['operation_type_code'] ?? '') === $type['code'] ? 'selected' : '' ?>>
                                                    <?= e($type['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="row_service_code[<?= (int)$idx ?>]">
                                            <option value="">Aucun</option>
                                            <?php foreach ($services as $service): ?>
                                                <option value="<?= e($service['code']) ?>" <?= ($row['service_code'] ?? '') === $service['code'] ? 'selected' : '' ?>>
                                                    <?= e($service['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="row_treasury_account_id[<?= (int)$idx ?>]">
                                            <option value="">Aucun</option>
                                            <?php foreach ($treasuryAccounts as $ta): ?>
                                                <option value="<?= (int)$ta['id'] ?>" <?= (string)($row['treasury_account_id'] ?? '') === (string)$ta['id'] ? 'selected' : '' ?>>
                                                    <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <span class="status-pill <?= $statusClass ?>"><?= e($row['status']) ?></span>
                                        <?php if (!empty($row['status_reason'])): ?>
                                            <div class="muted"><?= e($row['status_reason']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>