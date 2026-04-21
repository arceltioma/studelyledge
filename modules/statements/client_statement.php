<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$clientId = (int)($_GET['client_id'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

if ($clientId <= 0) {
    exit('Client invalide.');
}

$selectFields = [
    'c.*',
    'ta.account_code AS treasury_account_code',
    'ta.account_label AS treasury_account_label'
];

$stmtClient = $pdo->prepare("
    SELECT " . implode(', ', $selectFields) . "
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    WHERE c.id = ?
    LIMIT 1
");
$stmtClient->execute([$clientId]);
$client = $stmtClient->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$clientBank = function_exists('findPrimaryBankAccountForClient')
    ? findPrimaryBankAccountForClient($pdo, $clientId)
    : null;

$operationsSql = "
    SELECT o.*
    FROM operations o
    WHERE o.client_id = ?
";
$params = [$clientId];

if ($dateFrom !== '') {
    $operationsSql .= " AND o.operation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $operationsSql .= " AND o.operation_date <= ?";
    $params[] = $dateTo;
}

$operationsSql .= " ORDER BY o.operation_date ASC, o.id ASC";

$stmtOps = $pdo->prepare($operationsSql);
$stmtOps->execute($params);
$operations = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

$clientAccountNumber = (string)($client['generated_client_account'] ?? ($clientBank['account_number'] ?? ''));
$initialBalance = (float)($clientBank['initial_balance'] ?? 0);
$openingBalance = $initialBalance;

if (function_exists('findClientOpeningBalanceBeforeDate') && $dateFrom !== '') {
    $openingBalance = findClientOpeningBalanceBeforeDate(
        $pdo,
        $clientId,
        $clientAccountNumber,
        $initialBalance,
        $dateFrom
    );
}

$totalDebit = 0.0;
$totalCredit = 0.0;
$runningBalance = $openingBalance;
$rows = [];

foreach ($operations as $operation) {
    $amount = (float)($operation['amount'] ?? 0);
    $debitCode = (string)($operation['debit_account_code'] ?? '');
    $creditCode = (string)($operation['credit_account_code'] ?? '');

    $debit = 0.0;
    $credit = 0.0;

    if ($debitCode === $clientAccountNumber) {
        $debit = $amount;
    } elseif ($creditCode === $clientAccountNumber) {
        $credit = $amount;
    }

    $runningBalance = $runningBalance - $debit + $credit;
    $totalDebit += $debit;
    $totalCredit += $credit;

    $rows[] = [
        'operation_date' => $operation['operation_date'] ?? '',
        'label' => $operation['label'] ?? '',
        'reference' => $operation['reference'] ?? '',
        'debit_account_code' => $debitCode,
        'credit_account_code' => $creditCode,
        'debit' => $debit,
        'credit' => $credit,
        'balance' => $runningBalance,
    ];
}

$pageTitle = 'Consultation relevé';
$pageSubtitle = 'Lecture détaillée du relevé client à l’écran.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h1><?= e($client['full_name'] ?? '') ?></h1>
                <p class="muted">
                    Code client : <?= e($client['client_code'] ?? '') ?> —
                    Compte 411 : <?= e($clientAccountNumber) ?>
                </p>
            </div>

            <div class="btn-group">
                <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php?single=1&document_kind=statement&client_id=<?= (int)$clientId ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                    PDF unitaire
                </a>
                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/statements/account_statements.php">Retour</a>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Informations client</h3>
                <div class="detail-grid">
                    <div class="detail-row"><span class="detail-label">Nom</span><span class="detail-value"><?= e($client['full_name'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?= e($client['email'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Téléphone</span><span class="detail-value"><?= e($client['phone'] ?? '') ?></span></div>
                    <?php if (columnExists($pdo, 'clients', 'postal_address')): ?>
                        <div class="detail-row"><span class="detail-label">Adresse postale</span><span class="detail-value"><?= nl2br(e($client['postal_address'] ?? '')) ?></span></div>
                    <?php endif; ?>
                    <div class="detail-row"><span class="detail-label">Pays commercial</span><span class="detail-value"><?= e($client['country_commercial'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Compte 512 lié</span><span class="detail-value"><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? ''))) ?></span></div>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Synthèse période</h3>
                <div class="detail-grid">
                    <div class="detail-row"><span class="detail-label">Date début</span><span class="detail-value"><?= e($dateFrom !== '' ? $dateFrom : 'Origine') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Date fin</span><span class="detail-value"><?= e($dateTo !== '' ? $dateTo : date('Y-m-d')) ?></span></div>
                    <div class="detail-row"><span class="detail-label">Solde début</span><span class="detail-value"><?= number_format($openingBalance, 2, ',', ' ') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Total débits</span><span class="detail-value"><?= number_format($totalDebit, 2, ',', ' ') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Total crédits</span><span class="detail-value"><?= number_format($totalCredit, 2, ',', ' ') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Solde fin</span><span class="detail-value"><?= number_format($runningBalance, 2, ',', ' ') ?></span></div>
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Mouvements</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th>Référence</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Solde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['operation_date']) ?></td>
                            <td>
                                <strong><?= e($row['label']) ?></strong><br>
                                <span class="muted">D: <?= e($row['debit_account_code']) ?> • C: <?= e($row['credit_account_code']) ?></span>
                            </td>
                            <td><?= e($row['reference']) ?></td>
                            <td><?= $row['debit'] > 0 ? number_format($row['debit'], 2, ',', ' ') : '—' ?></td>
                            <td><?= $row['credit'] > 0 ? number_format($row['credit'], 2, ',', ' ') : '—' ?></td>
                            <td><?= number_format($row['balance'], 2, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="6">Aucune opération sur la période sélectionnée.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>