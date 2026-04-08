<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_manage_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de service invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM service_accounts
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de service introuvable.');
}

$pageTitle = 'Fiche compte de service (706)';
$pageSubtitle = 'Consultation détaillée du compte de produit';

if (!function_exists('slv_build_operation_link')) {
    function slv_build_operation_link(array $operation): string
    {
        $operationId = (int)($operation['id'] ?? 0);
        if ($operationId <= 0) {
            return '#';
        }

        $directPath = __DIR__ . '/../operations/view.php';
        if (is_file($directPath)) {
            return APP_URL . 'modules/operations/view.php?id=' . $operationId;
        }

        return APP_URL . 'modules/operations/operations_list.php';
    }
}

if (!function_exists('slv_operation_direction_badge')) {
    function slv_operation_direction_badge(array $operation, string $serviceAccountCode): array
    {
        $debit = trim((string)($operation['debit_account_code'] ?? ''));
        $credit = trim((string)($operation['credit_account_code'] ?? ''));
        $serviceCode = trim($serviceAccountCode);

        if ($serviceCode !== '') {
            if ($credit === $serviceCode) {
                return ['label' => 'Crédit', 'class' => 'success'];
            }
            if ($debit === $serviceCode) {
                return ['label' => 'Débit', 'class' => 'danger'];
            }
        }

        return ['label' => 'Mixte', 'class' => 'secondary'];
    }
}

if (!function_exists('slv_operation_type_options')) {
    function slv_operation_type_options(PDO $pdo, string $serviceAccountCode): array
    {
        if (!tableExists($pdo, 'operations')) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT operation_type_code
            FROM operations
            WHERE service_account_code = ?
              AND operation_type_code IS NOT NULL
              AND operation_type_code <> ''
            ORDER BY operation_type_code ASC
        ");
        $stmt->execute([$serviceAccountCode]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

$timeline = function_exists('getEntityTimeline')
    ? getEntityTimeline($pdo, 'service_account', $id, 20)
    : [];

$filterDateFrom = trim((string)($_GET['date_from'] ?? ''));
$filterDateTo = trim((string)($_GET['date_to'] ?? ''));
$filterType = trim((string)($_GET['operation_type_code'] ?? ''));
$filterDirection = trim((string)($_GET['direction'] ?? ''));

$allowedDirections = ['', 'credit', 'debit', 'mixed'];
if (!in_array($filterDirection, $allowedDirections, true)) {
    $filterDirection = '';
}

$operationTypeOptions = slv_operation_type_options($pdo, (string)($account['account_code'] ?? ''));

$operations = [];
$totalOperations = 0;
$totalAmount = 0.0;
$creditAmount = 0.0;
$debitAmount = 0.0;
$lastOperationDate = null;

if (tableExists($pdo, 'operations') && !empty($account['account_code'])) {
    $sql = "
        SELECT
            o.id,
            o.client_id,
            o.operation_date,
            o.operation_type_code,
            o.operation_kind,
            o.label,
            o.amount,
            o.currency_code,
            o.reference,
            o.source_type,
            o.debit_account_code,
            o.credit_account_code,
            o.service_account_code,
            o.notes,
            o.created_at,
            c.client_code,
            c.full_name AS client_name
        FROM operations o
        LEFT JOIN clients c ON c.id = o.client_id
        WHERE o.service_account_code = ?
    ";

    $params = [(string)$account['account_code']];

    if ($filterDateFrom !== '') {
        $sql .= " AND o.operation_date >= ? ";
        $params[] = $filterDateFrom;
    }

    if ($filterDateTo !== '') {
        $sql .= " AND o.operation_date <= ? ";
        $params[] = $filterDateTo;
    }

    if ($filterType !== '') {
        $sql .= " AND o.operation_type_code = ? ";
        $params[] = $filterType;
    }

    if ($filterDirection === 'credit') {
        $sql .= " AND o.credit_account_code = ? ";
        $params[] = (string)$account['account_code'];
    } elseif ($filterDirection === 'debit') {
        $sql .= " AND o.debit_account_code = ? ";
        $params[] = (string)$account['account_code'];
    } elseif ($filterDirection === 'mixed') {
        $sql .= " AND (
            (o.debit_account_code IS NULL OR o.debit_account_code <> ?)
            AND
            (o.credit_account_code IS NULL OR o.credit_account_code <> ?)
        ) ";
        $params[] = (string)$account['account_code'];
        $params[] = (string)$account['account_code'];
    }

    $sql .= " ORDER BY o.operation_date DESC, o.id DESC ";

    $stmtOperations = $pdo->prepare($sql);
    $stmtOperations->execute($params);
    $operations = $stmtOperations->fetchAll(PDO::FETCH_ASSOC);

    $totalOperations = count($operations);
    $lastOperationDate = $operations[0]['operation_date'] ?? null;

    foreach ($operations as $row) {
        $amount = (float)($row['amount'] ?? 0);
        $totalAmount += $amount;

        $directionInfo = slv_operation_direction_badge($row, (string)$account['account_code']);
        if ($directionInfo['label'] === 'Crédit') {
            $creditAmount += $amount;
        } elseif ($directionInfo['label'] === 'Débit') {
            $debitAmount += $amount;
        }
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Informations générales</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>ID</span>
                        <strong><?= (int)$account['id'] ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Code compte</span>
                        <strong><?= e((string)($account['account_code'] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Intitulé</span>
                        <strong><?= e((string)($account['account_label'] ?? '')) ?></strong>
                    </div>

                    <?php if (array_key_exists('operation_type_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Type d’opération</span>
                            <strong><?= e((string)($account['operation_type_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('commercial_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays commercial</span>
                            <strong><?= e((string)($account['commercial_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('destination_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays destination</span>
                            <strong><?= e((string)($account['destination_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('current_balance', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Solde courant</span>
                            <strong><?= e(number_format((float)($account['current_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="sl-data-list__row">
                        <span>Nb opérations</span>
                        <strong><?= (int)$totalOperations ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Total période</span>
                        <strong><?= e(number_format($totalAmount, 2, ',', ' ')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Total crédits</span>
                        <strong><?= e(number_format($creditAmount, 2, ',', ' ')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Total débits</span>
                        <strong><?= e(number_format($debitAmount, 2, ',', ' ')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Dernière opération</span>
                        <strong><?= e((string)($lastOperationDate ?: '—')) ?></strong>
                    </div>

                    <?php if (array_key_exists('is_postable', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Postable</span>
                            <strong><?= ((int)($account['is_postable'] ?? 0) === 1) ? 'Oui' : 'Non' ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('is_active', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Statut</span>
                            <strong><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/edit.php?id=<?= (int)$id ?>" class="btn btn-success">Modifier</a>
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Retour</a>
                </div>
            </div>

            <div class="card">
                <h3>Historique des opérations</h3>

                <form method="GET" class="form-card" style="padding:14px; margin-bottom:16px;">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Date début</label>
                            <input type="date" name="date_from" value="<?= e($filterDateFrom) ?>">
                        </div>

                        <div>
                            <label>Date fin</label>
                            <input type="date" name="date_to" value="<?= e($filterDateTo) ?>">
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_code">
                                <option value="">Tous</option>
                                <?php foreach ($operationTypeOptions as $typeCode): ?>
                                    <option value="<?= e((string)$typeCode) ?>" <?= $filterType === (string)$typeCode ? 'selected' : '' ?>>
                                        <?= e((string)$typeCode) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Sens</label>
                            <select name="direction">
                                <option value="" <?= $filterDirection === '' ? 'selected' : '' ?>>Tous</option>
                                <option value="credit" <?= $filterDirection === 'credit' ? 'selected' : '' ?>>Crédit</option>
                                <option value="debit" <?= $filterDirection === 'debit' ? 'selected' : '' ?>>Débit</option>
                                <option value="mixed" <?= $filterDirection === 'mixed' ? 'selected' : '' ?>>Mixte</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/view.php?id=<?= (int)$id ?>" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>

                <?php if ($operations): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Libellé</th>
                                    <th>Client</th>
                                    <th>Sens</th>
                                    <th>Montant</th>
                                    <th>Référence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($operations as $operation): ?>
                                    <?php $directionInfo = slv_operation_direction_badge($operation, (string)$account['account_code']); ?>
                                    <tr>
                                        <td><?= e((string)($operation['operation_date'] ?? '')) ?></td>
                                        <td>
                                            <?= e((string)($operation['operation_type_code'] ?? '')) ?>
                                            <?php if (!empty($operation['operation_kind'])): ?>
                                                <br><span class="muted"><?= e((string)$operation['operation_kind']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e((string)($operation['label'] ?? '')) ?>
                                            <?php if (!empty($operation['notes'])): ?>
                                                <br><span class="muted"><?= e((string)$operation['notes']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e(trim(((string)($operation['client_code'] ?? '')) . ' - ' . ((string)($operation['client_name'] ?? '')), ' -')) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= e($directionInfo['class']) ?>">
                                                <?= e($directionInfo['label']) ?>
                                            </span>
                                        </td>
                                        <td><?= e(number_format((float)($operation['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                        <td><?= e((string)($operation['reference'] ?? '—')) ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a class="btn btn-outline" href="<?= e(slv_build_operation_link($operation)) ?>">Voir</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucune opération liée à ce compte de service pour les filtres sélectionnés.</p>
                <?php endif; ?>

                <div style="margin-top:24px;">
                    <h3>Timeline technique</h3>

                    <?php if ($timeline): ?>
                        <div class="sl-anomaly-list">
                            <?php foreach ($timeline as $item): ?>
                                <div class="sl-anomaly-list__item">
                                    <span class="sl-anomaly-list__label">
                                        <?= e((string)($item['title'] ?? 'Événement')) ?>
                                        <?php if (!empty($item['details'])): ?>
                                            — <?= e((string)$item['details']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($item['old_value']) || !empty($item['new_value'])): ?>
                                            — <?= e((string)($item['old_value'] ?? '')) ?> → <?= e((string)($item['new_value'] ?? '')) ?>
                                        <?php endif; ?>
                                    </span>
                                    <strong class="sl-anomaly-list__value"><?= e((string)($item['created_at'] ?? '')) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">Aucun historique technique disponible.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>