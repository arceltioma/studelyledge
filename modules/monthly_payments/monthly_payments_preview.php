<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'monthly_payments_preview');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('sl_monthly_preview_redirect')) {
    function sl_monthly_preview_redirect(string $message): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . APP_URL . 'modules/monthly_payments/import_monthly_payments.php');
        exit;
    }
}

if (!function_exists('sl_monthly_preview_pick_import_id')) {
    function sl_monthly_preview_pick_import_id(PDO $pdo): int
    {
        $candidates = [
            (int)($_GET['import_id'] ?? 0),
            (int)($_POST['import_id'] ?? 0),
            (int)($_SESSION['monthly_payment_current_import_id'] ?? 0),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate > 0) {
                return $candidate;
            }
        }

        if (!tableExists($pdo, 'monthly_payment_imports')) {
            return 0;
        }

        $stmt = $pdo->query("
            SELECT id
            FROM monthly_payment_imports
            ORDER BY id DESC
            LIMIT 1
        ");

        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('sl_monthly_preview_row_amount')) {
    function sl_monthly_preview_row_amount(array $row): float
    {
        $keys = ['monthly_amount', 'mensualite_amount', 'amount', 'montant'];

        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return (float)str_replace(',', '.', (string)$row[$key]);
            }
        }

        return 0.0;
    }
}

if (!function_exists('sl_monthly_preview_row_day')) {
    function sl_monthly_preview_row_day(array $row): int
    {
        $keys = ['monthly_day', 'mensualite_day', 'jour'];

        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return (int)$row[$key];
            }
        }

        return 26;
    }
}

$importId = sl_monthly_preview_pick_import_id($pdo);
if ($importId <= 0) {
    sl_monthly_preview_redirect('Aucun import de mensualités à prévisualiser.');
}

$stmtImport = $pdo->prepare("
    SELECT *
    FROM monthly_payment_imports
    WHERE id = ?
    LIMIT 1
");
$stmtImport->execute([$importId]);
$import = $stmtImport->fetch(PDO::FETCH_ASSOC);

if (!$import) {
    unset($_SESSION['monthly_payment_current_import_id']);
    sl_monthly_preview_redirect('Import introuvable.');
}

$_SESSION['monthly_payment_current_import_id'] = $importId;

$stmtRows = $pdo->prepare("
    SELECT r.*
    FROM monthly_payment_import_rows r
    WHERE r.import_id = ?
    ORDER BY r.id ASC
");
$stmtRows->execute([$importId]);
$rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];

$validatedRows = [];
$totals = [
    'rows' => 0,
    'valid' => 0,
    'invalid' => 0,
    'amount' => 0.0,
];

foreach ($rows as $row) {
    $totals['rows']++;

    $clientCode = trim((string)($row['client_code'] ?? ''));
    $amount = sl_monthly_preview_row_amount($row);
    $day = sl_monthly_preview_row_day($row);
    $treasuryCode = trim((string)($row['treasury_account_code'] ?? ''));

    $message = [];
    $rowStatus = 'ready';
    $client = null;
    $treasury = null;

    if ($clientCode === '') {
        $rowStatus = 'error';
        $message[] = 'Code client absent';
    } else {
        $stmtClient = $pdo->prepare("
            SELECT *
            FROM clients
            WHERE client_code = ?
            LIMIT 1
        ");
        $stmtClient->execute([$clientCode]);
        $client = $stmtClient->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$client) {
            $rowStatus = 'error';
            $message[] = 'Client introuvable';
        }
    }

    if ($amount <= 0) {
        $rowStatus = 'error';
        $message[] = 'Montant invalide';
    }

    if ($day < 1 || $day > 31) {
        $rowStatus = 'error';
        $message[] = 'Jour mensuel invalide';
    }

    if ($treasuryCode === '') {
        $rowStatus = 'error';
        $message[] = 'Compte 512 absent';
    } else {
        $stmtTreasury = $pdo->prepare("
            SELECT *
            FROM treasury_accounts
            WHERE account_code = ?
            LIMIT 1
        ");
        $stmtTreasury->execute([$treasuryCode]);
        $treasury = $stmtTreasury->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$treasury) {
            $rowStatus = 'error';
            $message[] = 'Compte 512 introuvable';
        }
    }

    if ($rowStatus === 'ready') {
        $totals['valid']++;
        $totals['amount'] += $amount;
    } else {
        $totals['invalid']++;
    }

    $validatedRows[] = [
        'raw' => $row,
        'client' => $client,
        'treasury' => $treasury,
        'status' => $rowStatus,
        'message' => implode(' | ', $message),
        'amount' => $amount,
        'day' => $day,
    ];
}

$pageTitle = 'Prévisualisation des mensualités';
$pageSubtitle = 'Contrôle des lignes importées avant validation manuelle';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="card stat-card">
                <div class="stat-title">Lignes</div>
                <div class="stat-value"><?= (int)$totals['rows'] ?></div>
                <div class="stat-subtitle">Importées</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Valides</div>
                <div class="stat-value"><?= (int)$totals['valid'] ?></div>
                <div class="stat-subtitle">Prêtes à valider</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Invalides</div>
                <div class="stat-value"><?= (int)$totals['invalid'] ?></div>
                <div class="stat-subtitle">À corriger</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Montant total</div>
                <div class="stat-value" style="font-size:1.4rem;">
                    <?= e(number_format((float)$totals['amount'], 2, ',', ' ')) ?>
                </div>
                <div class="stat-subtitle">Mensualités valides</div>
            </div>
        </div>

        <div class="card">
            <div class="btn-group" style="margin-bottom:16px;">
                <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_payments_validate.php?import_id=<?= (int)$importId ?>" class="btn btn-success">
                    Valider cet import
                </a>
                <a href="<?= e(APP_URL) ?>modules/monthly_payments/import_monthly_payments.php" class="btn btn-outline">
                    Nouvel import
                </a>
            </div>

            <h3>Détail des lignes</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Jour</th>
                            <th>Compte 512</th>
                            <th>Statut</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($validatedRows as $item): ?>
                            <tr>
                                <td><?= e((string)($item['raw']['client_code'] ?? '')) ?></td>
                                <td><?= e(number_format((float)$item['amount'], 2, ',', ' ')) ?></td>
                                <td><?= (int)$item['day'] ?></td>
                                <td><?= e((string)($item['raw']['treasury_account_code'] ?? '')) ?></td>
                                <td>
                                    <span class="sl-pill <?= $item['status'] === 'ready' ? 'audit-badge-success' : 'audit-badge-danger' ?>">
                                        <?= $item['status'] === 'ready' ? 'Valide' : 'Erreur' ?>
                                    </span>
                                </td>
                                <td><?= e($item['message'] !== '' ? $item['message'] : 'OK') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$validatedRows): ?>
                            <tr>
                                <td colspan="6">Aucune ligne.</td>
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