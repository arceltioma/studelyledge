<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'treasury_import_page');
} else {
    enforcePagePermission($pdo, 'treasury_import');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Import comptes de trésorerie';
$pageSubtitle = 'Prévisualisation avant validation des comptes 512 à importer';

$successMessage = '';
$errorMessage = '';
$sessionKey = 'treasury_import_preview_v2';

$currencies = function_exists('sl_get_currency_options')
    ? sl_get_currency_options($pdo)
    : [['code' => 'EUR', 'label' => 'Euro']];

$currencyCodes = array_map(static fn($item) => (string)($item['code'] ?? ''), $currencies);

if (!function_exists('sl_treasury_import_norm')) {
    function sl_treasury_import_norm(string $v): string
    {
        $v = mb_strtolower(trim($v), 'UTF-8');
        $v = str_replace(['é','è','ê','à','ç','-','/','.'], ['e','e','e','a','c',' ',' ',' '], $v);
        $v = preg_replace('/\s+/', '_', $v);
        return preg_replace('/[^a-z0-9_]/', '', $v);
    }
}

if (!function_exists('sl_treasury_detect_delimiter')) {
    function sl_treasury_detect_delimiter(string $line): string
    {
        $delims = [',', ';', '|', "\t"];
        $best = ';';
        $max = -1;

        foreach ($delims as $d) {
            $count = substr_count($line, $d);
            if ($count > $max) {
                $max = $count;
                $best = $d;
            }
        }

        return $best;
    }
}

if (!function_exists('sl_treasury_parse_amount')) {
    function sl_treasury_parse_amount(string $v): float
    {
        $v = str_replace([' ', "\xc2\xa0"], '', trim($v));

        if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }

        return (float)$v;
    }
}

if (!function_exists('sl_treasury_parse_csv_preview')) {
    function sl_treasury_parse_csv_preview(string $tmpPath, array $currencyCodes, PDO $pdo): array
    {
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            throw new RuntimeException('Lecture impossible du fichier.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new RuntimeException('Le fichier est vide.');
        }

        $delimiter = sl_treasury_detect_delimiter($firstLine);
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Entêtes introuvables.');
        }

        $headers = array_map(static fn($h) => sl_treasury_import_norm((string)$h), $headers);

        $aliases = [
            'account_code' => 'account_code',
            'code' => 'account_code',
            'account_label' => 'account_label',
            'label' => 'account_label',
            'bank_name' => 'bank_name',
            'bank' => 'bank_name',
            'subsidiary_name' => 'subsidiary_name',
            'filiale' => 'subsidiary_name',
            'zone_code' => 'zone_code',
            'zone' => 'zone_code',
            'country_label' => 'country_label',
            'pays' => 'country_label',
            'country_type' => 'country_type',
            'type_pays' => 'country_type',
            'payment_place' => 'payment_place',
            'lieu_paiement' => 'payment_place',
            'opening_balance' => 'opening_balance',
            'solde_ouverture' => 'opening_balance',
            'current_balance' => 'current_balance',
            'solde_courant' => 'current_balance',
            'currency_code' => 'currency_code',
            'currency' => 'currency_code',
            'devise' => 'currency_code',
            'is_active' => 'is_active',
            'actif' => 'is_active',
        ];

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if (count(array_filter($data, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $header) {
                $mapped = $aliases[$header] ?? $header;
                $assoc[$mapped] = trim((string)($data[$index] ?? ''));
            }

            $status = 'ok';
            $message = 'Prêt à importer';

            $code = strtoupper((string)($assoc['account_code'] ?? ''));
            $label = (string)($assoc['account_label'] ?? '');
            $bankName = (string)($assoc['bank_name'] ?? '');
            $subsidiaryName = (string)($assoc['subsidiary_name'] ?? '');
            $zoneCode = (string)($assoc['zone_code'] ?? '');
            $countryLabel = (string)($assoc['country_label'] ?? '');
            $countryType = (string)($assoc['country_type'] ?? '');
            $paymentPlace = (string)($assoc['payment_place'] ?? '');
            $currencyCode = strtoupper((string)($assoc['currency_code'] ?? 'EUR'));
            $openingBalance = sl_treasury_parse_amount((string)($assoc['opening_balance'] ?? '0'));
            $currentBalance = sl_treasury_parse_amount((string)($assoc['current_balance'] ?? (string)$openingBalance));
            $isActiveRaw = strtolower((string)($assoc['is_active'] ?? '1'));
            $isActive = in_array($isActiveRaw, ['0', 'false', 'non', 'inactive'], true) ? 0 : 1;

            if ($code === '') {
                $status = 'error';
                $message = 'Code compte manquant';
            } elseif ($label === '') {
                $status = 'error';
                $message = 'Intitulé manquant';
            } elseif (!in_array($currencyCode, $currencyCodes, true)) {
                $status = 'error';
                $message = 'Devise inconnue';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM treasury_accounts WHERE account_code = ?");
                $stmt->execute([$code]);

                if ($stmt->fetch()) {
                    $status = 'duplicate';
                    $message = 'Compte déjà existant';
                }
            }

            $rows[] = [
                'line' => $line,
                'status' => $status,
                'message' => $message,
                'account_code' => $code,
                'account_label' => $label,
                'bank_name' => $bankName,
                'subsidiary_name' => $subsidiaryName,
                'zone_code' => $zoneCode,
                'country_label' => $countryLabel,
                'country_type' => $countryType,
                'payment_place' => $paymentPlace,
                'currency_code' => $currencyCode,
                'opening_balance' => $openingBalance,
                'current_balance' => $currentBalance,
                'is_active' => $isActive,
            ];
        }

        fclose($handle);

        return $rows;
    }
}

$previewRows = $_SESSION[$sessionKey]['rows'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('CSRF invalide');
        }

        if ($actionMode === 'preview') {
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
                throw new RuntimeException('Fichier manquant');
            }

            $tmp = (string)($_FILES['file']['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new RuntimeException('Upload invalide');
            }

            $previewRows = sl_treasury_parse_csv_preview($tmp, $currencyCodes, $pdo);

            if (!$previewRows) {
                throw new RuntimeException('Aucune ligne exploitable trouvée.');
            }

            $_SESSION[$sessionKey] = [
                'rows' => $previewRows,
                'file_name' => (string)($_FILES['file']['name'] ?? 'import.csv'),
            ];
        }

        if ($actionMode === 'confirm_import') {
            $previewRows = $_SESSION[$sessionKey]['rows'] ?? [];
            if (!$previewRows || !is_array($previewRows)) {
                throw new RuntimeException('Aucune prévisualisation en attente.');
            }

            $pdo->beginTransaction();

            $imported = 0;
            $duplicates = 0;
            $errors = 0;

            foreach ($previewRows as $row) {
                if (($row['status'] ?? '') === 'duplicate') {
                    $duplicates++;
                    continue;
                }

                if (($row['status'] ?? '') !== 'ok') {
                    $errors++;
                    continue;
                }

                $columns = [];
                $values = [];
                $params = [];

                $map = [
                    'account_code' => $row['account_code'],
                    'account_label' => $row['account_label'],
                    'bank_name' => $row['bank_name'] !== '' ? $row['bank_name'] : null,
                    'subsidiary_name' => $row['subsidiary_name'] !== '' ? $row['subsidiary_name'] : null,
                    'zone_code' => $row['zone_code'] !== '' ? $row['zone_code'] : null,
                    'country_label' => $row['country_label'] !== '' ? $row['country_label'] : null,
                    'country_type' => $row['country_type'] !== '' ? $row['country_type'] : null,
                    'payment_place' => $row['payment_place'] !== '' ? $row['payment_place'] : null,
                    'currency_code' => $row['currency_code'] !== '' ? $row['currency_code'] : 'EUR',
                    'opening_balance' => (float)$row['opening_balance'],
                    'current_balance' => (float)$row['current_balance'],
                    'is_active' => (int)$row['is_active'],
                ];

                foreach ($map as $column => $value) {
                    if (columnExists($pdo, 'treasury_accounts', $column)) {
                        $columns[] = $column;
                        $values[] = '?';
                        $params[] = $value;
                    }
                }

                if (columnExists($pdo, 'treasury_accounts', 'created_at')) {
                    $columns[] = 'created_at';
                    $values[] = 'NOW()';
                }
                if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
                    $columns[] = 'updated_at';
                    $values[] = 'NOW()';
                }

                $sql = "INSERT INTO treasury_accounts (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
                $pdo->prepare($sql)->execute($params);
                $imported++;
            }

            $pdo->commit();

            unset($_SESSION[$sessionKey]);
            $previewRows = [];

            $successMessage = $imported . ' compte(s) importé(s), ' . $duplicates . ' doublon(s), ' . $errors . ' ligne(s) ignorée(s).';
        }

        if ($actionMode === 'cancel_preview') {
            unset($_SESSION[$sessionKey]);
            $previewRows = [];
        }
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

<?php if ($successMessage): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

<div class="dashboard-grid-2">
    <div class="form-card">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_input() ?>

            <label>Fichier CSV</label>
            <input type="file" name="file" accept=".csv,.txt" <?= $previewRows ? '' : 'required' ?>>

            <div class="dashboard-note" style="margin-top:14px;">
                Format recommandé :
                <strong>account_code</strong>,
                <strong>account_label</strong>,
                <strong>bank_name</strong>,
                <strong>subsidiary_name</strong>,
                <strong>zone_code</strong>,
                <strong>country_label</strong>,
                <strong>country_type</strong>,
                <strong>payment_place</strong>,
                <strong>opening_balance</strong>,
                <strong>current_balance</strong>,
                <strong>currency_code</strong>,
                <strong>is_active</strong>
            </div>

            <div class="btn-group" style="margin-top:20px;">
                <?php if (!$previewRows): ?>
                    <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                <?php else: ?>
                    <button type="submit" name="action_mode" value="confirm_import" class="btn btn-success">Confirmer l’import</button>
                    <button type="submit" name="action_mode" value="cancel_preview" class="btn btn-outline">Annuler</button>
                <?php endif; ?>
                <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Retour</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3><?= $previewRows ? 'Prévisualisation import' : 'Format attendu' ?></h3>

        <?php if ($previewRows): ?>
            <?php
            $okCount = count(array_filter($previewRows, static fn($r) => ($r['status'] ?? '') === 'ok'));
            $duplicateCount = count(array_filter($previewRows, static fn($r) => ($r['status'] ?? '') === 'duplicate'));
            $errorCount = count(array_filter($previewRows, static fn($r) => ($r['status'] ?? '') === 'error'));
            ?>
            <div class="sl-data-list">
                <div class="sl-data-list__row"><span>Lignes prêtes</span><strong><?= (int)$okCount ?></strong></div>
                <div class="sl-data-list__row"><span>Doublons</span><strong><?= (int)$duplicateCount ?></strong></div>
                <div class="sl-data-list__row"><span>Erreurs</span><strong><?= (int)$errorCount ?></strong></div>
            </div>
            <div class="dashboard-note" style="margin-top:16px;">
                Vérifie les codes 512, les devises, et la cohérence des soldes avant de confirmer l’import.
            </div>
        <?php else: ?>
            <pre>account_code;account_label;bank_name;subsidiary_name;zone_code;country_label;country_type;payment_place;opening_balance;current_balance;currency_code;is_active</pre>
        <?php endif; ?>
    </div>
</div>

<?php if ($previewRows): ?>
<div class="card" style="margin-top:20px;">
    <h3>Résultat de prévisualisation</h3>

    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Ligne</th>
                    <th>Statut</th>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Ouverture</th>
                    <th>Courant</th>
                    <th>Devise</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previewRows as $r): ?>
                    <tr>
                        <td><?= (int)$r['line'] ?></td>
                        <td><?= e($r['status']) ?></td>
                        <td><?= e($r['account_code']) ?></td>
                        <td><?= e($r['account_label']) ?></td>
                        <td><?= number_format((float)$r['opening_balance'], 2, ',', ' ') ?></td>
                        <td><?= number_format((float)$r['current_balance'], 2, ',', ' ') ?></td>
                        <td><?= e($r['currency_code']) ?></td>
                        <td><?= e($r['message']) ?></td>
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