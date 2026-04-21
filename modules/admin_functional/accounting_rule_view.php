<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if (!function_exists('arv_table_name')) {
    function arv_table_name(PDO $pdo, string $preferred, string $fallback): string
    {
        if (tableExists($pdo, $preferred)) {
            return $preferred;
        }
        return $fallback;
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Règle comptable invalide.');
}

$operationTypeTable = arv_table_name($pdo, 'ref_operation_types', 'operation_types');
$serviceTable = arv_table_name($pdo, 'ref_services', 'services');

$stmt = $pdo->prepare("
    SELECT
        ar.*,
        ot.code AS operation_type_code,
        ot.label AS operation_type_label,
        ot.direction AS operation_type_direction,
        s.code AS service_code,
        s.label AS service_label,
        s.service_account_id,
        s.treasury_account_id,
        sa.account_code AS service_account_code,
        sa.account_label AS service_account_label,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
    FROM accounting_rules ar
    LEFT JOIN {$operationTypeTable} ot ON ot.id = ar.operation_type_id
    LEFT JOIN {$serviceTable} s ON s.id = ar.service_id
    LEFT JOIN service_accounts sa ON sa.id = s.service_account_id
    LEFT JOIN treasury_accounts ta ON ta.id = s.treasury_account_id
    WHERE ar.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$rule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rule) {
    exit('Règle comptable introuvable.');
}

$pageTitle = 'Détail de la règle comptable';
$pageSubtitle = 'Lecture complète du paramétrage débit / crédit, des exigences et des rattachements liés.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="sl-card sl-premium-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Identité de la règle</h3>
                        <p class="sl-card-head-subtitle">Informations principales de la règle comptable</p>
                    </div>
                    <span class="sl-pill <?= (int)($rule['is_active'] ?? 1) === 1 ? 'audit-badge-success' : 'audit-badge-danger' ?>">
                        <?= (int)($rule['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                    </span>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$rule['id'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Code règle</span><strong><?= e((string)($rule['rule_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($rule['rule_label'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Type opération</span><strong><?= e(trim((string)($rule['operation_type_label'] ?? '') . ' (' . (string)($rule['operation_type_code'] ?? '') . ')')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Service</span><strong><?= e(trim((string)($rule['service_label'] ?? '') . ' (' . (string)($rule['service_code'] ?? '') . ')')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Direction type</span><strong><?= e((string)($rule['operation_type_direction'] ?? 'mixed')) ?></strong></div>
                </div>
            </div>

            <div class="sl-card sl-premium-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Résolution comptable</h3>
                        <p class="sl-card-head-subtitle">Modes de calcul des comptes débit / crédit</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Débit / Crédit</span>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Mode débit</span><strong><?= e((string)($rule['debit_mode'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Mode crédit</span><strong><?= e((string)($rule['credit_mode'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte fixe débit</span><strong><?= e((string)($rule['debit_fixed_account_code'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte fixe crédit</span><strong><?= e((string)($rule['credit_fixed_account_code'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Motif libellé</span><strong><?= e((string)($rule['label_pattern'] ?? '—')) ?></strong></div>
                </div>

                <div class="sl-rule-flow" style="margin-top:16px;">
                    <div class="sl-rule-flow__item">
                        <span class="sl-rule-flow__label">Débit</span>
                        <strong><?= e((string)($rule['debit_mode'] ?? '')) ?></strong>
                    </div>
                    <div class="sl-rule-flow__arrow">→</div>
                    <div class="sl-rule-flow__item">
                        <span class="sl-rule-flow__label">Crédit</span>
                        <strong><?= e((string)($rule['credit_mode'] ?? '')) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="sl-card sl-premium-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Contraintes métier</h3>
                        <p class="sl-card-head-subtitle">Exigences d’exécution de la règle</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Contrôle</span>
                </div>

                <div class="sl-metric-stack">
                    <div class="sl-metric-tile">
                        <span class="sl-metric-tile__label">Client requis</span>
                        <strong class="sl-metric-tile__value"><?= (int)($rule['requires_client'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong>
                    </div>

                    <div class="sl-metric-tile">
                        <span class="sl-metric-tile__label">Compte bancaire lié requis</span>
                        <strong class="sl-metric-tile__value"><?= (int)($rule['requires_linked_bank'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong>
                    </div>

                    <div class="sl-metric-tile">
                        <span class="sl-metric-tile__label">Comptes manuels requis</span>
                        <strong class="sl-metric-tile__value"><?= (int)($rule['requires_manual_accounts'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong>
                    </div>
                </div>
            </div>

            <div class="sl-card sl-premium-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Rattachements du service</h3>
                        <p class="sl-card-head-subtitle">Comptes liés au service référentiel</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Référentiel</span>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Compte 706 du service</span><strong><?= e(trim((string)($rule['service_account_code'] ?? '') . ' - ' . (string)($rule['service_account_label'] ?? '')) ?: '—') ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte 512 du service</span><strong><?= e(trim((string)($rule['treasury_account_code'] ?? '') . ' - ' . (string)($rule['treasury_account_label'] ?? '')) ?: '—') ?></strong></div>
                </div>
            </div>
        </div>

        <div class="sl-card sl-premium-card" style="margin-top:20px;">
            <div class="sl-card-head">
                <div>
                    <h3>Actions</h3>
                    <p class="sl-card-head-subtitle">Navigation et maintenance de la règle</p>
                </div>
            </div>

            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php" class="btn btn-outline">Retour liste</a>
                <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_edit.php?id=<?= (int)$rule['id'] ?>" class="btn btn-secondary">Modifier</a>
                <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_delete.php?id=<?= (int)$rule['id'] ?>" class="btn btn-danger">Supprimer</a>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>