<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Compte client invalide.');
}

if (!tableExists($pdo, 'bank_accounts')) {
    exit('Table bank_accounts introuvable.');
}

if (!function_exists('sl_client_account_view_has_negative_balance')) {
    function sl_client_account_view_has_negative_balance(array $account): bool
    {
        return (float)($account['balance'] ?? 0) < 0;
    }
}

if (!function_exists('sl_client_account_view_balance_delta')) {
    function sl_client_account_view_balance_delta(array $account): float
    {
        return (float)($account['balance'] ?? 0) - (float)($account['initial_balance'] ?? 0);
    }
}

if (!function_exists('sl_client_account_view_build_alerts')) {
    function sl_client_account_view_build_alerts(array $account): array
    {
        $alerts = [];

        $currentBalance = (float)($account['balance'] ?? 0);
        $initialBalance = (float)($account['initial_balance'] ?? 0);
        $clientIsActive = (int)($account['client_is_active'] ?? 1) === 1;
        $accountIsActive = (int)($account['is_active'] ?? 1) === 1;

        if (!$clientIsActive) {
            $alerts[] = [
                'level' => 'danger',
                'title' => 'Client archivé',
                'message' => 'Le client rattaché à ce compte 411 est archivé.',
            ];
        }

        if (!$accountIsActive) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Compte 411 inactif',
                'message' => 'Le compte bancaire 411 est marqué comme inactif.',
            ];
        }

        if ($currentBalance < 0) {
            $alerts[] = [
                'level' => 'danger',
                'title' => 'Solde négatif',
                'message' => 'Ce compte 411 présente un solde négatif et doit être vérifié rapidement.',
            ];
        } elseif ($currentBalance == 0.0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Solde à zéro',
                'message' => 'Le compte 411 est actuellement à zéro.',
            ];
        }

        if ($initialBalance > 0 && $currentBalance < ($initialBalance * 0.1)) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Solde très faible',
                'message' => 'Le solde courant est inférieur à 10 % du solde initial.',
            ];
        }

        if ($currentBalance > $initialBalance && $initialBalance > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Solde supérieur à l’ouverture',
                'message' => 'Le compte 411 a progressé au-delà de son solde initial.',
            ];
        }

        return $alerts;
    }
}

$select = [
    'ba.*'
];

$joins = [];

if (tableExists($pdo, 'client_bank_accounts') && tableExists($pdo, 'clients')) {
    $joins[] = "LEFT JOIN client_bank_accounts cba ON cba.bank_account_id = ba.id";
    $joins[] = "LEFT JOIN clients c ON c.id = cba.client_id";

    $select[] = "c.id AS client_id";
    $select[] = "c.client_code";
    $select[] = "c.full_name";
    $select[] = "c.generated_client_account";
    $select[] = "c.currency AS client_currency";
    $select[] = "COALESCE(c.is_active,1) AS client_is_active";

    if (columnExists($pdo, 'clients', 'client_status')) {
        $select[] = "c.client_status";
    }
    if (columnExists($pdo, 'clients', 'country_origin')) {
        $select[] = "c.country_origin";
    }
    if (columnExists($pdo, 'clients', 'country_destination')) {
        $select[] = "c.country_destination";
    }
    if (columnExists($pdo, 'clients', 'country_commercial')) {
        $select[] = "c.country_commercial";
    }
    if (columnExists($pdo, 'clients', 'monthly_amount')) {
        $select[] = "c.monthly_amount";
    }
    if (columnExists($pdo, 'clients', 'monthly_day')) {
        $select[] = "c.monthly_day";
    }
    if (columnExists($pdo, 'clients', 'monthly_enabled')) {
        $select[] = "c.monthly_enabled";
    }
    if (columnExists($pdo, 'clients', 'archived_balance_amount')) {
        $select[] = "c.archived_balance_amount";
    }
}

$sql = "
    SELECT " . implode(",\n           ", $select) . "
    FROM bank_accounts ba
    " . implode("\n    ", $joins) . "
    WHERE ba.id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte client introuvable.');
}

$pageTitle = 'Voir un compte client 411';
$pageSubtitle = 'Consultation détaillée et pilotage visuel du compte client';

$accountCode = (string)($account['account_number'] ?? '');
$movements = [];
$stats = [
    'movements_count' => 0,
    'total_debit' => 0.0,
    'total_credit' => 0.0,
    'last_movement_date' => '',
];

if ($accountCode !== '' && tableExists($pdo, 'operations')) {
    $hasOperationDate = columnExists($pdo, 'operations', 'operation_date');
    $hasLabel = columnExists($pdo, 'operations', 'label');
    $hasReference = columnExists($pdo, 'operations', 'reference');
    $hasAmount = columnExists($pdo, 'operations', 'amount');
    $hasDebit = columnExists($pdo, 'operations', 'debit_account_code');
    $hasCredit = columnExists($pdo, 'operations', 'credit_account_code');
    $hasCurrency = columnExists($pdo, 'operations', 'currency_code');
    $hasType = columnExists($pdo, 'operations', 'operation_type_code');

    $stmtMovements = $pdo->prepare("
        SELECT
            o.id,
            " . ($hasOperationDate ? 'o.operation_date' : 'NULL') . " AS operation_date,
            " . ($hasLabel ? 'o.label' : 'NULL') . " AS label,
            " . ($hasReference ? 'o.reference' : 'NULL') . " AS reference,
            " . ($hasAmount ? 'o.amount' : '0') . " AS amount,
            " . ($hasDebit ? 'o.debit_account_code' : 'NULL') . " AS debit_account_code,
            " . ($hasCredit ? 'o.credit_account_code' : 'NULL') . " AS credit_account_code,
            " . ($hasCurrency ? 'o.currency_code' : 'NULL') . " AS currency_code,
            " . ($hasType ? 'o.operation_type_code' : 'NULL') . " AS operation_type_code
        FROM operations o
        WHERE
            " . ($hasDebit ? 'o.debit_account_code = ?' : '1=0') . "
            OR
            " . ($hasCredit ? 'o.credit_account_code = ?' : '1=0') . "
        ORDER BY " . ($hasOperationDate ? 'o.operation_date DESC' : 'o.id DESC') . ", o.id DESC
        LIMIT 100
    ");
    $stmtMovements->execute([$accountCode, $accountCode]);
    $movements = $stmtMovements->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stats['movements_count'] = count($movements);

    foreach ($movements as $movement) {
        $amount = (float)($movement['amount'] ?? 0);
        $isDebit = (string)($movement['debit_account_code'] ?? '') === $accountCode;
        $isCredit = (string)($movement['credit_account_code'] ?? '') === $accountCode;

        if ($isDebit) {
            $stats['total_debit'] += $amount;
        }
        if ($isCredit) {
            $stats['total_credit'] += $amount;
        }

        if ($stats['last_movement_date'] === '' && !empty($movement['operation_date'])) {
            $stats['last_movement_date'] = (string)$movement['operation_date'];
        }
    }
}

$alerts = sl_client_account_view_build_alerts($account);
$balanceDelta = sl_client_account_view_balance_delta($account);
$isNegative = sl_client_account_view_has_negative_balance($account);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php" class="btn btn-outline">Retour</a>

                <?php if (!empty($account['client_id'])): ?>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$account['client_id'] ?>" class="btn btn-secondary">Voir le client</a>
                <?php endif; ?>

                <?php if (currentUserCan($pdo, 'manual_actions_create') || currentUserCan($pdo, 'operations_create') || currentUserCan($pdo, 'admin_manage')): ?>
                    <a
                        href="<?= e(APP_URL) ?>modules/manual_actions/manual_operation.php?debit_family=411&source_account_code=<?= urlencode($accountCode) ?>&credit_family=512"
                        class="btn btn-success"
                    >
                        Initier opération
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="sl-kpi-grid">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Compte 411</div>
                <div class="sl-kpi-card__value"><?= e($accountCode !== '' ? $accountCode : '—') ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Référence</span>
                    <strong>Client</strong>
                </div>
            </div>

            <div class="sl-kpi-card <?= $isNegative ? 'sl-kpi-card--rose' : 'sl-kpi-card--emerald' ?>">
                <div class="sl-kpi-card__label">Solde courant</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)($account['balance'] ?? 0), 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Situation</span>
                    <strong><?= $isNegative ? 'Négatif' : 'Stable' ?></strong>
                </div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Écart vs ouverture</div>
                <div class="sl-kpi-card__value"><?= e(number_format($balanceDelta, 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Ouverture / courant</span>
                    <strong><?= $balanceDelta >= 0 ? 'Hausse' : 'Baisse' ?></strong>
                </div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Mouvements</div>
                <div class="sl-kpi-card__value"><?= (int)$stats['movements_count'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Historique</span>
                    <strong>100 derniers</strong>
                </div>
            </div>
        </div>

        <?php if ($alerts): ?>
            <div style="margin-top:20px;">
                <?php foreach ($alerts as $alert): ?>
                    <div class="<?= $alert['level'] === 'danger' ? 'error' : ($alert['level'] === 'warning' ? 'warning' : 'dashboard-note') ?>" style="margin-bottom:12px;">
                        <strong><?= e((string)$alert['title']) ?></strong><br>
                        <?= e((string)$alert['message']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Informations générales</h3>

                <div class="stat-row">
                    <span class="metric-label">Code compte</span>
                    <span class="metric-value"><?= e((string)($account['account_number'] ?? '')) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Nom du compte</span>
                    <span class="metric-value"><?= e((string)($account['account_name'] ?? '')) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Solde initial</span>
                    <span class="metric-value"><?= e(number_format((float)($account['initial_balance'] ?? 0), 2, ',', ' ')) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Solde courant</span>
                    <span class="metric-value">
                        <?= e(number_format((float)($account['balance'] ?? 0), 2, ',', ' ')) ?>
                        <?php if ($isNegative): ?>
                            <span class="badge badge-danger" style="margin-left:8px;">Solde négatif</span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (array_key_exists('country', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Pays</span>
                        <span class="metric-value"><?= e((string)($account['country'] ?? '')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('bank_name', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Banque / origine</span>
                        <span class="metric-value"><?= e((string)($account['bank_name'] ?? '')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('is_active', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Statut compte</span>
                        <span class="metric-value"><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Inactif' ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Client rattaché</h3>

                <div class="stat-row">
                    <span class="metric-label">Client</span>
                    <span class="metric-value">
                        <?= e(trim((string)($account['client_code'] ?? '') . ' - ' . (string)($account['full_name'] ?? '')) ?: '—') ?>
                    </span>
                </div>

                <?php if (array_key_exists('client_status', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Statut métier</span>
                        <span class="metric-value"><?= e((string)($account['client_status'] ?? '—')) ?></span>
                    </div>
                <?php endif; ?>

                <div class="stat-row">
                    <span class="metric-label">Statut client</span>
                    <span class="metric-value">
                        <?= ((int)($account['client_is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?>
                    </span>
                </div>

                <?php if (array_key_exists('client_currency', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Devise client</span>
                        <span class="metric-value"><?= e((string)($account['client_currency'] ?? 'EUR')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('country_origin', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Pays d’origine</span>
                        <span class="metric-value"><?= e((string)($account['country_origin'] ?? '—')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('country_destination', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Pays destination</span>
                        <span class="metric-value"><?= e((string)($account['country_destination'] ?? '—')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('country_commercial', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Pays commercial</span>
                        <span class="metric-value"><?= e((string)($account['country_commercial'] ?? '—')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('monthly_enabled', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Mensualité active</span>
                        <span class="metric-value"><?= ((int)($account['monthly_enabled'] ?? 0) === 1) ? 'Oui' : 'Non' ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('monthly_amount', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Montant mensuel</span>
                        <span class="metric-value"><?= e(number_format((float)($account['monthly_amount'] ?? 0), 2, ',', ' ')) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Indicateurs d’activité</h3>

                <div class="stat-row">
                    <span class="metric-label">Total au débit</span>
                    <span class="metric-value"><?= e(number_format((float)$stats['total_debit'], 2, ',', ' ')) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Total au crédit</span>
                    <span class="metric-value"><?= e(number_format((float)$stats['total_credit'], 2, ',', ' ')) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Dernier mouvement</span>
                    <span class="metric-value"><?= e($stats['last_movement_date'] !== '' ? $stats['last_movement_date'] : '—') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Variation nette</span>
                    <span class="metric-value"><?= e(number_format((float)$stats['total_credit'] - (float)$stats['total_debit'], 2, ',', ' ')) ?></span>
                </div>
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

                <?php if (array_key_exists('account_type_id', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Type compte</span>
                        <span class="metric-value"><?= e((string)($account['account_type_id'] ?? '—')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('account_category_id', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Catégorie compte</span>
                        <span class="metric-value"><?= e((string)($account['account_category_id'] ?? '—')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('archived_balance_amount', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Solde archivé mémorisé</span>
                        <span class="metric-value"><?= e(number_format((float)($account['archived_balance_amount'] ?? 0), 2, ',', ' ')) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Mouvements récents liés au compte 411</h3>

            <div class="table-responsive">
                <table class="modern-table sl-modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Libellé</th>
                            <th>Référence</th>
                            <th>Montant</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Sens pour ce 411</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($movements): ?>
                            <?php foreach ($movements as $row): ?>
                                <?php
                                $isDebit = (string)($row['debit_account_code'] ?? '') === $accountCode;
                                $isCredit = (string)($row['credit_account_code'] ?? '') === $accountCode;
                                ?>
                                <tr>
                                    <td><?= e((string)($row['operation_date'] ?? '')) ?></td>
                                    <td><?= e((string)($row['operation_type_code'] ?? '')) ?></td>
                                    <td><?= e((string)($row['label'] ?? '')) ?></td>
                                    <td><?= e((string)($row['reference'] ?? '')) ?></td>
                                    <td><?= e(number_format((float)($row['amount'] ?? 0), 2, ',', ' ')) ?> <?= e((string)($row['currency_code'] ?? '')) ?></td>
                                    <td><?= e((string)($row['debit_account_code'] ?? '')) ?></td>
                                    <td><?= e((string)($row['credit_account_code'] ?? '')) ?></td>
                                    <td>
                                        <?php if ($isDebit): ?>
                                            <span class="badge badge-danger">Débité</span>
                                        <?php elseif ($isCredit): ?>
                                            <span class="badge badge-success">Crédité</span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">Aucun mouvement trouvé pour ce compte 411.</td>
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