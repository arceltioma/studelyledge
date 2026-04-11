<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_view_page');
} else {
    enforcePagePermission($pdo, 'operations_view');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Opération invalide.');
}

if (!tableExists($pdo, 'operations')) {
    exit('Table operations introuvable.');
}

$selectParts = ['o.*'];
$joinClients = '';
$joinServices = '';
$joinTypes = '';
$joinLinkedBank = '';
$joinMainBank = '';

if (tableExists($pdo, 'clients') && columnExists($pdo, 'operations', 'client_id')) {
    $joinClients = 'LEFT JOIN clients c ON c.id = o.client_id';
    $selectParts[] = columnExists($pdo, 'clients', 'client_code') ? 'c.client_code' : "NULL AS client_code";
    $selectParts[] = columnExists($pdo, 'clients', 'full_name') ? 'c.full_name AS client_full_name' : "NULL AS client_full_name";
    $selectParts[] = columnExists($pdo, 'clients', 'country_commercial') ? 'c.country_commercial' : "NULL AS country_commercial";
    $selectParts[] = columnExists($pdo, 'clients', 'country_destination') ? 'c.country_destination' : "NULL AS country_destination";
}

if (tableExists($pdo, 'ref_services') && columnExists($pdo, 'operations', 'service_id')) {
    $joinServices = 'LEFT JOIN ref_services rs ON rs.id = o.service_id';
    $selectParts[] = columnExists($pdo, 'ref_services', 'code') ? 'rs.code AS service_code_ref' : "NULL AS service_code_ref";
    $selectParts[] = columnExists($pdo, 'ref_services', 'label') ? 'rs.label AS service_label' : "NULL AS service_label";
}

if (tableExists($pdo, 'ref_operation_types') && columnExists($pdo, 'operations', 'operation_type_id')) {
    $joinTypes = 'LEFT JOIN ref_operation_types rot ON rot.id = o.operation_type_id';
    $selectParts[] = columnExists($pdo, 'ref_operation_types', 'code') ? 'rot.code AS operation_type_code_ref' : "NULL AS operation_type_code_ref";
    $selectParts[] = columnExists($pdo, 'ref_operation_types', 'label') ? 'rot.label AS operation_type_label' : "NULL AS operation_type_label";
}

if (tableExists($pdo, 'bank_accounts') && columnExists($pdo, 'operations', 'linked_bank_account_id')) {
    $joinLinkedBank = 'LEFT JOIN bank_accounts lba ON lba.id = o.linked_bank_account_id';
    $selectParts[] = 'lba.account_name AS linked_bank_account_name';
    $selectParts[] = 'lba.account_number AS linked_bank_account_number';
}

if (tableExists($pdo, 'bank_accounts') && columnExists($pdo, 'operations', 'bank_account_id')) {
    $joinMainBank = 'LEFT JOIN bank_accounts mba ON mba.id = o.bank_account_id';
    $selectParts[] = 'mba.account_name AS bank_account_name';
    $selectParts[] = 'mba.account_number AS bank_account_number';
}

$sql = "
    SELECT
        " . implode(",\n ", $selectParts) . "
    FROM operations o
    {$joinClients}
    {$joinServices}
    {$joinTypes}
    {$joinLinkedBank}
    {$joinMainBank}
    WHERE o.id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$operation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

$pageTitle = 'Voir une opération';
$pageSubtitle = 'Consultation détaillée et comptable de l’opération';

$canEdit = currentUserCan($pdo, 'operations_edit') || currentUserCan($pdo, 'operations_manage') || currentUserCan($pdo, 'admin_manage');
$canDelete = currentUserCan($pdo, 'operations_delete') || currentUserCan($pdo, 'operations_manage') || currentUserCan($pdo, 'admin_manage');

$clientDisplay = trim((string)(
    (($operation['client_code'] ?? '') !== '' ? ($operation['client_code'] . ' - ') : '') .
    ($operation['client_full_name'] ?? '')
));

$operationTypeDisplay = trim((string)($operation['operation_type_label'] ?? $operation['operation_type_code_ref'] ?? $operation['operation_type_code'] ?? ''));
$serviceDisplay = trim((string)($operation['service_label'] ?? $operation['service_code_ref'] ?? $operation['service_code'] ?? ''));

$linkedBankDisplay = trim((string)(
    (($operation['linked_bank_account_number'] ?? '') !== '' ? ($operation['linked_bank_account_number'] . ' - ') : '') .
    ($operation['linked_bank_account_name'] ?? '')
));

$mainBankDisplay = trim((string)(
    (($operation['bank_account_number'] ?? '') !== '' ? ($operation['bank_account_number'] . ' - ') : '') .
    ($operation['bank_account_name'] ?? '')
));

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Retour</a>

                <?php if ($canEdit): ?>
                    <a href="<?= e(APP_URL) ?>modules/operations/operation_edit.php?id=<?= (int)$id ?>" class="btn btn-success">Modifier</a>
                <?php endif; ?>

                <?php if ($canDelete): ?>
                    <a
                        href="<?= e(APP_URL) ?>modules/operations/operation_delete.php?id=<?= (int)$id ?>"
                        class="btn btn-danger"
                        onclick="return confirm('Confirmer la suppression ou l’archivage de cette opération ?');"
                    >
                        Supprimer
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Informations générales</h3>

                <div class="stat-row"><span class="metric-label">ID</span><span class="metric-value"><?= (int)($operation['id'] ?? 0) ?></span></div>
                <div class="stat-row"><span class="metric-label">Date</span><span class="metric-value"><?= e((string)($operation['operation_date'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Montant</span><span class="metric-value"><?= e(number_format((float)($operation['amount'] ?? 0), 2, ',', ' ')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Devise</span><span class="metric-value"><?= e((string)($operation['currency_code'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Type opération</span><span class="metric-value"><?= e($operationTypeDisplay !== '' ? $operationTypeDisplay : '—') ?></span></div>
                <div class="stat-row"><span class="metric-label">Service</span><span class="metric-value"><?= e($serviceDisplay !== '' ? $serviceDisplay : '—') ?></span></div>
                <div class="stat-row"><span class="metric-label">Client</span><span class="metric-value"><?= e($clientDisplay !== '' ? $clientDisplay : 'N/A') ?></span></div>

                <?php if (!empty($operation['country_commercial'])): ?>
                    <div class="stat-row"><span class="metric-label">Pays commercial</span><span class="metric-value"><?= e((string)$operation['country_commercial']) ?></span></div>
                <?php endif; ?>

                <?php if (!empty($operation['country_destination'])): ?>
                    <div class="stat-row"><span class="metric-label">Pays destination</span><span class="metric-value"><?= e((string)$operation['country_destination']) ?></span></div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Aperçu comptable</h3>

                <div class="stat-row"><span class="metric-label">Compte débité</span><span class="metric-value"><?= e((string)($operation['debit_account_code'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Compte crédité</span><span class="metric-value"><?= e((string)($operation['credit_account_code'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Compte de service</span><span class="metric-value"><?= e((string)($operation['service_account_code'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Mode manuel</span><span class="metric-value"><?= ((int)($operation['is_manual_accounting'] ?? 0) === 1) ? 'Oui' : 'Non' ?></span></div>
                <div class="stat-row"><span class="metric-label">Hash anti-doublon</span><span class="metric-value"><?= e((string)($operation['operation_hash'] ?? '')) ?></span></div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Libellés & contenu</h3>

                <div class="stat-row"><span class="metric-label">Libellé</span><span class="metric-value"><?= e((string)($operation['label'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Référence</span><span class="metric-value"><?= e((string)($operation['reference'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Notes / motif</span><span class="metric-value"><?= nl2br(e((string)($operation['notes'] ?? ''))) ?></span></div>
                <div class="stat-row"><span class="metric-label">Source</span><span class="metric-value"><?= e((string)($operation['source_type'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Nature</span><span class="metric-value"><?= e((string)($operation['operation_kind'] ?? '')) ?></span></div>
            </div>

            <div class="card">
                <h3>Métadonnées</h3>

                <div class="stat-row"><span class="metric-label">Créé par</span><span class="metric-value"><?= e((string)($operation['created_by'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Créé le</span><span class="metric-value"><?= e((string)($operation['created_at'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Mis à jour le</span><span class="metric-value"><?= e((string)($operation['updated_at'] ?? '')) ?></span></div>
                <div class="stat-row"><span class="metric-label">Compte bancaire lié</span><span class="metric-value"><?= e($linkedBankDisplay !== '' ? $linkedBankDisplay : ((string)($operation['linked_bank_account_id'] ?? '—'))) ?></span></div>
                <div class="stat-row"><span class="metric-label">Compte bancaire interne</span><span class="metric-value"><?= e($mainBankDisplay !== '' ? $mainBankDisplay : ((string)($operation['bank_account_id'] ?? '—'))) ?></span></div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
