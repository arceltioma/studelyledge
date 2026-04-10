<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'admin_functional_page');
} else {
    enforcePagePermission($pdo, 'admin_functional_view');
}

if (!tableExists($pdo, 'accounting_rules')) {
    exit('Table accounting_rules introuvable.');
}

$pageTitle = 'Règles comptables';
$pageSubtitle = 'Pilotage des règles débit / crédit par type d’opération et service';

if (!function_exists('mar_like')) {
    function mar_like(string $value): string
    {
        return '%' . $value . '%';
    }
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterOperationTypeId = trim((string)($_GET['filter_operation_type_id'] ?? ''));
$filterServiceId = trim((string)($_GET['filter_service_id'] ?? ''));
$filterStatus = trim((string)($_GET['filter_status'] ?? ''));
$filterDebitMode = trim((string)($_GET['filter_debit_mode'] ?? ''));
$filterCreditMode = trim((string)($_GET['filter_credit_mode'] ?? ''));

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

$availableModes = [
    'CLIENT_411' => 'CLIENT_411',
    'CLIENT_512' => 'CLIENT_512',
    'SERVICE_706' => 'SERVICE_706',
    'SOURCE_512' => 'SOURCE_512',
    'TARGET_512' => 'TARGET_512',
    'MANUAL_DEBIT' => 'MANUAL_DEBIT',
    'MANUAL_CREDIT' => 'MANUAL_CREDIT',
    'FIXED_ACCOUNT' => 'FIXED_ACCOUNT',
];

$sql = "
    SELECT
        ar.*,
        rot.code AS operation_type_code,
        rot.label AS operation_type_label,
        rs.code AS service_code,
        rs.label AS service_label
    FROM accounting_rules ar
    LEFT JOIN ref_operation_types rot ON rot.id = ar.operation_type_id
    LEFT JOIN ref_services rs ON rs.id = ar.service_id
    WHERE 1=1
";
$params = [];

if ($filterSearch !== '') {
    $sql .= "
        AND (
            COALESCE(ar.rule_code, '') LIKE ?
            OR COALESCE(ar.rule_label, '') LIKE ?
            OR COALESCE(rot.code, '') LIKE ?
            OR COALESCE(rot.label, '') LIKE ?
            OR COALESCE(rs.code, '') LIKE ?
            OR COALESCE(rs.label, '') LIKE ?
            OR COALESCE(ar.debit_mode, '') LIKE ?
            OR COALESCE(ar.credit_mode, '') LIKE ?
            OR COALESCE(ar.label_pattern, '') LIKE ?
            OR COALESCE(ar.debit_fixed_account_code, '') LIKE ?
            OR COALESCE(ar.credit_fixed_account_code, '') LIKE ?
        )
    ";
    for ($i = 0; $i < 11; $i++) {
        $params[] = mar_like($filterSearch);
    }
}

if ($filterOperationTypeId !== '') {
    $sql .= " AND ar.operation_type_id = ? ";
    $params[] = (int)$filterOperationTypeId;
}

if ($filterServiceId !== '') {
    $sql .= " AND ar.service_id = ? ";
    $params[] = (int)$filterServiceId;
}

if ($filterStatus === 'active') {
    $sql .= " AND COALESCE(ar.is_active,1) = 1 ";
} elseif ($filterStatus === 'inactive') {
    $sql .= " AND COALESCE(ar.is_active,1) = 0 ";
} elseif ($filterStatus === 'client_required') {
    $sql .= " AND COALESCE(ar.requires_client,0) = 1 ";
} elseif ($filterStatus === 'manual_required') {
    $sql .= " AND COALESCE(ar.requires_manual_accounts,0) = 1 ";
} elseif ($filterStatus === 'linked_bank_required') {
    $sql .= " AND COALESCE(ar.requires_linked_bank,0) = 1 ";
}

if ($filterDebitMode !== '') {
    $sql .= " AND COALESCE(ar.debit_mode, '') = ? ";
    $params[] = $filterDebitMode;
}

if ($filterCreditMode !== '') {
    $sql .= " AND COALESCE(ar.credit_mode, '') = ? ";
    $params[] = $filterCreditMode;
}

$sql .= " ORDER BY COALESCE(rot.label, ''), COALESCE(rs.label, ''), ar.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dashboard = [
    'total' => count($rules),
    'active' => count(array_filter($rules, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($rules, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'requires_client' => count(array_filter($rules, fn($r) => (int)($r['requires_client'] ?? 0) === 1)),
    'requires_manual' => count(array_filter($rules, fn($r) => (int)($r['requires_manual_accounts'] ?? 0) === 1)),
    'requires_linked_bank' => count(array_filter($rules, fn($r) => (int)($r['requires_linked_bank'] ?? 0) === 1)),
];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Dashboard règles comptables</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$dashboard['total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Actives</span><strong><?= (int)$dashboard['active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Inactives</span><strong><?= (int)$dashboard['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Client requis</span><strong><?= (int)$dashboard['requires_client'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Comptes manuels requis</span><strong><?= (int)$dashboard['requires_manual'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Banque liée requise</span><strong><?= (int)$dashboard['requires_linked_bank'] ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Actions rapides</h3>
                <div class="dashboard-note" style="margin-bottom:16px;">
                    Les règles comptables pilotent la résolution débit / crédit avant fallback sur le moteur historique.
                </div>

                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_create.php" class="btn btn-success">Nouvelle règle</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-outline">Types d’opérations</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Services</a>
                </div>
            </div>
        </div>

        <div class="form-card" style="margin-bottom:20px;">
            <h3 class="section-title">Filtres utiles</h3>

            <form method="GET">
                <div class="dashboard-grid-2">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="filter_search" value="<?= e($filterSearch) ?>" placeholder="Code règle, label, type, service, compte...">
                    </div>

                    <div>
                        <label>Type d’opération</label>
                        <select name="filter_operation_type_id">
                            <option value="">Tous</option>
                            <?php foreach ($operationTypes as $type): ?>
                                <option value="<?= (int)$type['id'] ?>" <?= $filterOperationTypeId === (string)$type['id'] ? 'selected' : '' ?>>
                                    <?= e(($type['label'] ?? '') . ' (' . ($type['code'] ?? '') . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Service</label>
                        <select name="filter_service_id">
                            <option value="">Tous</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= (int)$service['id'] ?>" <?= $filterServiceId === (string)$service['id'] ? 'selected' : '' ?>>
                                    <?= e(($service['label'] ?? '') . ' (' . ($service['code'] ?? '') . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Statut / contraintes</label>
                        <select name="filter_status">
                            <option value="">Tous</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actives</option>
                            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactives</option>
                            <option value="client_required" <?= $filterStatus === 'client_required' ? 'selected' : '' ?>>Client requis</option>
                            <option value="manual_required" <?= $filterStatus === 'manual_required' ? 'selected' : '' ?>>Comptes manuels requis</option>
                            <option value="linked_bank_required" <?= $filterStatus === 'linked_bank_required' ? 'selected' : '' ?>>Banque liée requise</option>
                        </select>
                    </div>

                    <div>
                        <label>Mode débit</label>
                        <select name="filter_debit_mode">
                            <option value="">Tous</option>
                            <?php foreach ($availableModes as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= $filterDebitMode === $mode ? 'selected' : '' ?>><?= e($mode) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Mode crédit</label>
                        <select name="filter_credit_mode">
                            <option value="">Tous</option>
                            <?php foreach ($availableModes as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= $filterCreditMode === $mode ? 'selected' : '' ?>><?= e($mode) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 class="section-title">Liste des règles comptables</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type d’opération</th>
                            <th>Service</th>
                            <th>Code règle</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Contraintes</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr>
                                <td><?= (int)($r['id'] ?? 0) ?></td>
                                <td><?= e(trim((string)($r['operation_type_label'] ?? '') . ' (' . (string)($r['operation_type_code'] ?? '') . ')')) ?></td>
                                <td><?= e(trim((string)($r['service_label'] ?? '') . ' (' . (string)($r['service_code'] ?? '') . ')')) ?></td>
                                <td>
                                    <strong><?= e((string)($r['rule_code'] ?? '')) ?></strong>
                                    <?php if (!empty($r['rule_label'])): ?>
                                        <div class="muted"><?= e((string)$r['rule_label']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e((string)($r['debit_mode'] ?? '')) ?>
                                    <?php if (!empty($r['debit_fixed_account_code'])): ?>
                                        <div class="muted">Fixe : <?= e((string)$r['debit_fixed_account_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e((string)($r['credit_mode'] ?? '')) ?>
                                    <?php if (!empty($r['credit_fixed_account_code'])): ?>
                                        <div class="muted">Fixe : <?= e((string)$r['credit_fixed_account_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="muted">
                                        Client : <?= (int)($r['requires_client'] ?? 0) === 1 ? 'Oui' : 'Non' ?><br>
                                        Banque liée : <?= (int)($r['requires_linked_bank'] ?? 0) === 1 ? 'Oui' : 'Non' ?><br>
                                        Manuel : <?= (int)($r['requires_manual_accounts'] ?? 0) === 1 ? 'Oui' : 'Non' ?>
                                        <?php if (!empty($r['label_pattern'])): ?>
                                            <br>Pattern : <?= e((string)$r['label_pattern']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= (int)($r['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-secondary">Modifier</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rules): ?>
                            <tr>
                                <td colspan="9">Aucune règle comptable trouvée.</td>
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