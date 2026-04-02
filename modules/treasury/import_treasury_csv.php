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
$pageSubtitle = 'Import sécurisé avec contrôle des doublons et cohérence comptable';

$successMessage = '';
$errorMessage = '';
$previewRows = [];

$imported = 0;
$duplicates = 0;
$errors = 0;

/* =========================
   Helpers
========================= */

function sl_norm($v)
{
    $v = mb_strtolower(trim($v), 'UTF-8');
    $v = str_replace(['é','è','ê','à','ç','-','/','.'], ['e','e','e','a','c',' ',' ',' '], $v);
    $v = preg_replace('/\s+/', '_', $v);
    return preg_replace('/[^a-z0-9_]/', '', $v);
}

function detectDelimiter($line)
{
    $delims = [',',';','|',"\t"];
    $best = ';';
    $max = 0;

    foreach ($delims as $d) {
        $c = substr_count($line, $d);
        if ($c > $max) {
            $max = $c;
            $best = $d;
        }
    }
    return $best;
}

function parseAmount($v)
{
    $v = str_replace([' ', "\xc2\xa0"], '', $v);

    if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '.', $v);
    }

    return (float)$v;
}

/* =========================
   IMPORT
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new Exception('CSRF invalide');
        }

        if (!isset($_FILES['file'])) {
            throw new Exception('Fichier manquant');
        }

        $tmp = $_FILES['file']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new Exception('Upload invalide');
        }

        $handle = fopen($tmp, 'r');
        if (!$handle) {
            throw new Exception('Lecture impossible');
        }

        $first = fgets($handle);
        $delimiter = detectDelimiter($first);
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        $headers = array_map('sl_norm', $headers);

        $map = [
            'account_code' => 'account_code',
            'code' => 'account_code',
            'account_label' => 'account_label',
            'label' => 'account_label',
            'solde' => 'opening_balance',
            'opening_balance' => 'opening_balance',
            'currency' => 'currency_code',
            'devise' => 'currency_code'
        ];

        $line = 1;

        $pdo->beginTransaction();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            try {
                $data = [];
                foreach ($headers as $i => $h) {
                    $key = $map[$h] ?? $h;
                    $data[$key] = trim($row[$i] ?? '');
                }

                $code = strtoupper($data['account_code'] ?? '');
                $label = $data['account_label'] ?? '';
                $balance = parseAmount($data['opening_balance'] ?? '0');
                $currency = $data['currency_code'] ?? 'EUR';

                if ($code === '') {
                    throw new Exception('Code manquant');
                }

                /* DUPLICATE */
                $stmt = $pdo->prepare("SELECT id FROM treasury_accounts WHERE account_code = ?");
                $stmt->execute([$code]);

                if ($stmt->fetch()) {
                    $duplicates++;
                    $previewRows[] = [
                        'line' => $line,
                        'status' => 'duplicate',
                        'code' => $code,
                        'message' => 'Compte déjà existant'
                    ];
                    continue;
                }

                /* INSERT SAFE */
                $cols = [];
                $vals = [];
                $params = [];

                $fields = [
                    'account_code' => $code,
                    'account_label' => $label,
                    'opening_balance' => $balance,
                    'current_balance' => $balance,
                    'currency_code' => $currency,
                    'is_active' => 1
                ];

                foreach ($fields as $k => $v) {
                    if (columnExists($pdo, 'treasury_accounts', $k)) {
                        $cols[] = $k;
                        $vals[] = '?';
                        $params[] = $v;
                    }
                }

                if (columnExists($pdo, 'treasury_accounts', 'created_at')) {
                    $cols[] = 'created_at';
                    $vals[] = 'NOW()';
                }

                $sql = "INSERT INTO treasury_accounts (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                $pdo->prepare($sql)->execute($params);

                $imported++;

                $previewRows[] = [
                    'line' => $line,
                    'status' => 'ok',
                    'code' => $code,
                    'message' => 'Importé'
                ];

            } catch (Throwable $e) {
                $errors++;
                $previewRows[] = [
                    'line' => $line,
                    'status' => 'error',
                    'code' => $data['account_code'] ?? '',
                    'message' => $e->getMessage()
                ];
            }
        }

        $pdo->commit();

        $successMessage = "$imported comptes importés, $duplicates doublons, $errors erreurs";

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
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
<input type="file" name="file" required>

<div class="btn-group">
<button class="btn btn-success">Importer</button>
<a href="index.php" class="btn btn-outline">Retour</a>
</div>

</form>
</div>

<div class="card">
<h3>Format attendu</h3>
<pre>account_code;account_label;opening_balance;currency_code</pre>
</div>

</div>

<?php if ($previewRows): ?>
<div class="card">
<h3>Résultat</h3>

<table class="modern-table">
<tr>
<th>Ligne</th>
<th>Statut</th>
<th>Code</th>
<th>Message</th>
</tr>

<?php foreach ($previewRows as $r): ?>
<tr>
<td><?= $r['line'] ?></td>
<td><?= $r['status'] ?></td>
<td><?= e($r['code']) ?></td>
<td><?= e($r['message']) ?></td>
</tr>
<?php endforeach; ?>

</table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>