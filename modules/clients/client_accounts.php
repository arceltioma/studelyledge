<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_view_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

if (!tableExists($pdo, 'clients')) {
    exit('Table clients introuvable.');
}

if (!function_exists('sca_find_client')) {
    function sca_find_client(PDO $pdo, int $clientId): ?array
    {
        $sql = "
            SELECT
                c.*,
                ba.id AS bank_account_id,
                ba.account_name,
                ba.account_number,
                ba.initial_balance,
                ba.balance
            FROM clients c
            LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
            LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
            WHERE c.id = ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('sca_fetch_client_options')) {
    function sca_fetch_client_options(PDO $pdo): array
    {
        $sql = "
            SELECT
                c.id,
                c.client_code,
                c.full_name,
                c.generated_client_account,
                c.client_status,
                c.is_active
            FROM clients c
            ORDER BY c.full_name ASC, c.client_code ASC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('sca_fetch_operation_types')) {
    function sca_fetch_operation_types(PDO $pdo): array
    {
        if (!tableExists($pdo, 'operations')) {
            return [];
        }

        $stmt = $pdo->query("
            SELECT DISTINCT operation_type_code
            FROM operations
            WHERE operation_type_code IS NOT NULL
              AND operation_type_code <> ''
            ORDER BY operation_type_code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

if (!function_exists('sca_operation_direction_badge')) {
    function sca_operation_direction_badge(array $operation, string $clientAccountCode): array
    {
        $debit = trim((string)($operation['debit_account_code'] ?? ''));
        $credit = trim((string)($operation['credit_account_code'] ?? ''));
        $clientCode = trim($clientAccountCode);

        if ($clientCode !== '') {
            if ($credit === $clientCode) {
                return ['label' => 'Crédit', 'class' => 'success'];
            }
            if ($debit === $clientCode) {
                return ['label' => 'Débit', 'class' => 'danger'];
            }
        }

        return ['label' => 'Mixte', 'class' => 'secondary'];
    }
}

if (!function_exists('sca_build_operation_link')) {
    function sca_build_operation_link(array $operation): string
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

$clientOptions = sca_fetch_client_options($pdo);
$operationTypeOptions = sca_fetch_operation_types($pdo);

$selectedClientId = (int)($_GET['client_id'] ?? 0);
if ($selectedClientId <= 0 && !empty($clientOptions)) {
    $selectedClientId = (int)$clientOptions[0]['id'];
}

$filterDateFrom = trim((string)($_GET['date_from'] ?? ''));
$filterDateTo = trim((string)($_GET['date_to'] ?? ''));
$filterType = trim((string)($_GET['operation_type_code'] ?? ''));
$filterDirection = trim((string)($_GET['direction'] ?? ''));

$allowedDirections = ['', 'credit', 'debit', 'mixed'];
if (!in_array($filterDirection, $allowedDirections, true)) {
    $filterDirection = '';
}

$client = $selectedClientId > 0 ? sca_find_client($pdo, $selectedClientId) : null;
$operations = [];
$totalOperations = 0;
$totalAmount = 0.0;
$totalCredits = 0.0;
$totalDebits = 0.0;

if ($client && tableExists($pdo, 'operations')) {
    $sql = "
        SELECT
            o.*,
            rs.label AS service_label,
            rot.label AS operation_type_label
        FROM operations o
        LEFT JOIN ref_services rs ON rs.id = o.service_id
        LEFT JOIN ref_operation_types rot ON rot.id = o.operation_type_id
        WHERE o.client_id = ?
    ";

    $params = [(int)$client['id']];

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

    $clientAccountCode = (string)($client['generated_client_account'] ?? '');

    if ($filterDirection === 'credit') {
        $sql .= " AND o.credit_account_code = ? ";
        $params[] = $clientAccountCode;
    } elseif ($filterDirection === 'debit') {
        $sql .= " AND o.debit_account_code = ? ";
        $params[] = $clientAccountCode;
    } elseif ($filterDirection === 'mixed') {
        $sql .= " AND (
            (o.debit_account_code IS NULL OR o.debit_account_code <> ?)
            AND
            (o.credit_account_code IS NULL OR o.credit_account_code <> ?)
        ) ";
        $params[] = $clientAccountCode;
        $params[] = $clientAccountCode;
    }

    $sql .= " ORDER BY o.operation_date DESC, o.id DESC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $operations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalOperations = count($operations);

    foreach ($operations as $operation) {
        $amount = (float)($operation['amount'] ?? 0);
        $totalAmount += $amount;

        $directionInfo = sca_operation_direction_badge($operation, $clientAccountCode);
        if ($directionInfo['label'] === 'Crédit') {
            $totalCredits += $amount;
        } elseif ($directionInfo['label'] === 'Débit') {
            $totalDebits += $amount;
        }
    }
}

$statementPreviewUrl = '#';
$profilePreviewUrl = '#';

if ($client) {
    $queryBase = [
        'prefill_client_id' => (int)$client['id'],
        'source_client_accounts' => 1,
    ];

    if ($filterDateFrom !== '') {
        $queryBase['date_from'] = $filterDateFrom;
    }
    if ($filterDateTo !== '') {
        $queryBase['date_to'] = $filterDateTo;
    }

    $statementPreviewUrl = APP_URL . 'modules/statements/account_statements.php?' . http_build_query($queryBase);
    $profilePreviewUrl = APP_URL . 'modules/statements/client_profiles.php?' . http_build_query($queryBase);
}

$pageTitle = 'Comptes clients 411';
$pageSubtitle = 'Historique des opérations par compte client avec filtres et exports.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Compte client</h3>

                <form method="GET" class="form-card" style="padding:14px; margin-bottom:16px;">
                    <div class="dashboard-grid-2">
                        <div style="grid-column:1 / -1;">
                            <label>Client / compte 411</label>
                            <select name="client_id" required>
                                <?php foreach ($clientOptions as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $selectedClientId === (int)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(trim(($item['client_code'] ?? '') . ' - ' . ($item['full_name'] ?? '') . ' - ' . ($item['generated_client_account'] ?? ''), ' -')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

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
                        <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php<?= $selectedClientId > 0 ? '?client_id=' . (int)$selectedClientId : '' ?>" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>

                <?php if ($client): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Client</span>
                            <strong><?= e((string)($client['full_name'] ?? '')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Code client</span>
                            <strong><?= e((string)($client['client_code'] ?? '')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Compte 411</span>
                            <strong><?= e((string)($client['generated_client_account'] ?? '')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Compte bancaire lié</span>
                            <strong><?= e(trim(((string)($client['account_number'] ?? '')) . ' - ' . ((string)($client['account_name'] ?? '')), ' -')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Solde initial</span>
                            <strong><?= e(number_format((float)($client['initial_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Solde courant</span>
                            <strong><?= e(number_format((float)($client['balance'] ?? 0), 2, ',', ' ')) ?></strong>
                        </div>
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
                            <strong><?= e(number_format($totalCredits, 2, ',', ' ')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Total débits</span>
                            <strong><?= e(number_format($totalDebits, 2, ',', ' ')) ?></strong>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$client['id'] ?>">Voir le client</a>
                        <a class="btn btn-outline" href="<?= e($statementPreviewUrl) ?>">Exporter le relevé</a>
                        <a class="btn btn-outline" href="<?= e($profilePreviewUrl) ?>">Exporter la fiche client</a>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucun client sélectionné.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Historique des opérations</h3>

                <?php if ($operations): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Service</th>
                                    <th>Libellé</th>
                                    <th>Sens</th>
                                    <th>Montant</th>
                                    <th>Référence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($operations as $operation): ?>
                                    <?php $directionInfo = sca_operation_direction_badge($operation, (string)($client['generated_client_account'] ?? '')); ?>
                                    <tr>
                                        <td><?= e((string)($operation['operation_date'] ?? '')) ?></td>
                                        <td><?= e((string)($operation['operation_type_code'] ?? '')) ?></td>
                                        <td><?= e((string)($operation['service_label'] ?? '—')) ?></td>
                                        <td><?= e((string)($operation['label'] ?? '')) ?></td>
                                        <td>
                                            <span class="badge badge-<?= e($directionInfo['class']) ?>">
                                                <?= e($directionInfo['label']) ?>
                                            </span>
                                        </td>
                                        <td><?= e(number_format((float)($operation['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                        <td><?= e((string)($operation['reference'] ?? '—')) ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a class="btn btn-outline" href="<?= e(sca_build_operation_link($operation)) ?>">Voir</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucune opération trouvée pour ce compte client avec les filtres sélectionnés.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>