<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);


$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de trésorerie invalide.');
}

if (!tableExists($pdo, 'treasury_accounts')) {
    exit('Table treasury_accounts introuvable.');
}

$stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de trésorerie introuvable.');
}

$pageTitle = 'Voir un compte de trésorerie';
$pageSubtitle = 'Consultation détaillée du compte interne';

$accountCode = $account['account_code'] ?? '';
$movements = [];

if ($accountCode !== '' && tableExists($pdo, 'operations')) {
    $hasOperationDate = columnExists($pdo, 'operations', 'operation_date');
    $dateExpr = $hasOperationDate ? 'o.operation_date' : 'NULL';

    $stmtMovements = $pdo->prepare("
        SELECT
            o.id,
            {$dateExpr} AS operation_date,
            " . (columnExists($pdo, 'operations', 'label') ? 'o.label' : 'NULL') . " AS label,
            " . (columnExists($pdo, 'operations', 'reference') ? 'o.reference' : 'NULL') . " AS reference,
            " . (columnExists($pdo, 'operations', 'amount') ? 'o.amount' : '0') . " AS amount,
            " . (columnExists($pdo, 'operations', 'debit_account_code') ? 'o.debit_account_code' : 'NULL') . " AS debit_account_code,
            " . (columnExists($pdo, 'operations', 'credit_account_code') ? 'o.credit_account_code' : 'NULL') . " AS credit_account_code
        FROM operations o
        WHERE
            " . (columnExists($pdo, 'operations', 'debit_account_code') ? 'o.debit_account_code = ?' : '1=0') . "
            OR
            " . (columnExists($pdo, 'operations', 'credit_account_code') ? 'o.credit_account_code = ?' : '1=0') . "
        ORDER BY " . ($hasOperationDate ? 'o.operation_date DESC' : 'o.id DESC') . ", o.id DESC
        LIMIT 100
    ");
    $stmtMovements->execute([$accountCode, $accountCode]);
    $movements = $stmtMovements->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">

            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Retour</a>
                <?php if (currentUserCan($pdo, 'treasury_edit') || currentUserCan($pdo, 'treasury_manage') || currentUserCan($pdo, 'admin_manage')): ?>
                    <a href="<?= e(APP_URL) ?>modules/treasury/treasury_edit.php?id=<?= (int)$account['id'] ?>" class="btn btn-success">Modifier</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Informations générales</h3>

                <div class="stat-row">
                    <span class="metric-label">Code compte</span>
                    <span class="metric-value"><?= e($account['account_code'] ?? '') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Intitulé</span>
                    <span class="metric-value"><?= e($account['account_label'] ?? '') ?></span>
                </div>

                <?php if (array_key_exists('opening_balance', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Solde ouverture</span>
                        <span class="metric-value"><?= e(number_format((float)($account['opening_balance'] ?? 0), 2, ',', ' ')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('current_balance', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Solde courant</span>
                        <span class="metric-value"><?= e(number_format((float)($account['current_balance'] ?? 0), 2, ',', ' ')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('currency_code', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Devise</span>
                        <span class="metric-value"><?= e($account['currency_code'] ?? '') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('is_active', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Statut</span>
                        <span class="metric-value"><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Métadonnées</h3>

                <?php if (array_key_exists('created_at', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Créé le</span>
                        <span class="metric-value"><?= e((string)($account['created_at'] ?? '')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('updated_at', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Mis à jour le</span>
                        <span class="metric-value"><?= e((string)($account['updated_at'] ?? '')) ?></span>
                    </div>
                <?php endif; ?>

                <?php
                $excluded = [
                    'id','account_code','account_label','opening_balance','current_balance',
                    'currency_code','is_active','created_at','updated_at'
                ];
                foreach ($account as $key => $value):
                    if (in_array($key, $excluded, true)) {
                        continue;
                    }
                ?>
                    <div class="stat-row">
                        <span class="metric-label"><?= e($key) ?></span>
                        <span class="metric-value"><?= e(is_scalar($value) || $value === null ? (string)$value : json_encode($value)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Mouvements récents liés au compte</h3>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Libellé</th>
                            <th>Référence</th>
                            <th>Montant</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($movements): ?>
                            <?php foreach ($movements as $row): ?>
                                <tr>
                                    <td><?= e((string)($row['operation_date'] ?? '')) ?></td>
                                    <td><?= e((string)($row['label'] ?? '')) ?></td>
                                    <td><?= e((string)($row['reference'] ?? '')) ?></td>
                                    <td><?= e(number_format((float)($row['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                    <td><?= e((string)($row['debit_account_code'] ?? '')) ?></td>
                                    <td><?= e((string)($row['credit_account_code'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">Aucun mouvement trouvé pour ce compte.</td>
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