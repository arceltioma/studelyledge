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

$canEdit = function_exists('studelyCanAccess')
    ? studelyCanAccess($pdo, 'operations_edit_page')
    : currentUserCan($pdo, 'operations_create');

$canDelete = function_exists('studelyCanAccess')
    ? studelyCanAccess($pdo, 'operations_delete_page')
    : currentUserCan($pdo, 'operations_create');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Opération invalide.');
}

$stmt = $pdo->prepare("
    SELECT
        o.*,
        c.client_code,
        c.full_name
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$operation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

$pageTitle = 'Voir une opération';
$pageSubtitle = 'Lecture détaillée d’une écriture comptable.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php if (function_exists('render_app_header_bar')): ?>
            <?php render_app_header_bar($pageTitle, $pageSubtitle); ?>
        <?php endif; ?>

        <div class="page-title">
            <div>
                <h1>Opération #<?= (int)$operation['id'] ?></h1>
                <p class="muted"><?= e($operation['label'] ?? '') ?></p>
            </div>

            <div class="btn-group">
                <?php if ($canEdit): ?>
                    <a class="btn btn-success" href="<?= APP_URL ?>modules/operations/operation_edit.php?id=<?= (int)$operation['id'] ?>">Modifier</a>
                <?php endif; ?>

                <?php if ($canDelete): ?>
                    <a class="btn btn-danger" href="<?= APP_URL ?>modules/operations/operation_delete.php?id=<?= (int)$operation['id'] ?>">Supprimer</a>
                <?php endif; ?>

                <a class="btn btn-outline" href="<?= APP_URL ?>modules/operations/operations_list.php">Retour</a>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Informations générales</h3>

                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="detail-label">Date</span>
                        <span class="detail-value"><?= e($operation['operation_date'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Client</span>
                        <span class="detail-value"><?= e(trim((string)($operation['client_code'] ?? '') . ' - ' . (string)($operation['full_name'] ?? ''))) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Libellé</span>
                        <span class="detail-value"><?= e($operation['label'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Référence</span>
                        <span class="detail-value"><?= e($operation['reference'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Montant</span>
                        <span class="detail-value"><?= number_format((float)($operation['amount'] ?? 0), 2, ',', ' ') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Type opération</span>
                        <span class="detail-value"><?= e($operation['operation_type_code'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Écriture comptable</h3>

                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="detail-label">Compte débit</span>
                        <span class="detail-value"><?= e($operation['debit_account_code'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Compte crédit</span>
                        <span class="detail-value"><?= e($operation['credit_account_code'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Compte 706 analytique</span>
                        <span class="detail-value"><?= e($operation['service_account_code'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Notes</span>
                        <span class="detail-value"><?= nl2br(e($operation['notes'] ?? '')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>