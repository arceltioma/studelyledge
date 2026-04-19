<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'accounting_rule_delete_page');
}

if (!function_exists('ard_table_name')) {
    function ard_table_name(PDO $pdo, string $preferred, string $fallback): string
    {
        if (tableExists($pdo, $preferred)) {
            return $preferred;
        }
        return $fallback;
    }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Règle comptable invalide.');
}

$operationTypeTable = ard_table_name($pdo, 'ref_operation_types', 'operation_types');
$serviceTable = ard_table_name($pdo, 'ref_services', 'services');

$stmt = $pdo->prepare("
    SELECT
        ar.*,
        ot.code AS operation_type_code,
        ot.label AS operation_type_label,
        s.code AS service_code,
        s.label AS service_label
    FROM accounting_rules ar
    LEFT JOIN {$operationTypeTable} ot ON ot.id = ar.operation_type_id
    LEFT JOIN {$serviceTable} s ON s.id = ar.service_id
    WHERE ar.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$rule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rule) {
    exit('Règle comptable introuvable.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $stmtDelete = $pdo->prepare("DELETE FROM accounting_rules WHERE id = ?");
        $stmtDelete->execute([$id]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'delete_accounting_rule',
                'admin_functional',
                'accounting_rule',
                $id,
                'Suppression d’une règle comptable depuis la page de confirmation'
            );
        }

        header('Location: ' . APP_URL . 'modules/admin_functional/manage_accounting_rules.php?deleted=1');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Supprimer une règle comptable';
$pageSubtitle = 'Confirmation de suppression de la règle.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="sl-card sl-premium-card sl-danger-card">
            <div class="sl-card-head">
                <div>
                    <h3>Confirmer la suppression</h3>
                    <p class="sl-card-head-subtitle">Cette action supprimera définitivement la règle comptable sélectionnée.</p>
                </div>
                <span class="sl-pill audit-badge-danger">Suppression</span>
            </div>

            <div class="sl-data-list">
                <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$rule['id'] ?></strong></div>
                <div class="sl-data-list__row"><span>Code</span><strong><?= e((string)($rule['rule_code'] ?? '')) ?></strong></div>
                <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($rule['rule_label'] ?? '')) ?></strong></div>
                <div class="sl-data-list__row"><span>Type d’opération</span><strong><?= e(trim((string)($rule['operation_type_label'] ?? '') . ' (' . (string)($rule['operation_type_code'] ?? '') . ')')) ?></strong></div>
                <div class="sl-data-list__row"><span>Service</span><strong><?= e(trim((string)($rule['service_label'] ?? '') . ' (' . (string)($rule['service_code'] ?? '') . ')')) ?></strong></div>
                <div class="sl-data-list__row"><span>Débit</span><strong><?= e((string)($rule['debit_mode'] ?? '')) ?></strong></div>
                <div class="sl-data-list__row"><span>Crédit</span><strong><?= e((string)($rule['credit_mode'] ?? '')) ?></strong></div>
            </div>

            <div class="dashboard-note" style="margin-top:18px;">
                La suppression retire la règle du moteur de résolution paramétrique. Les opérations déjà existantes ne sont pas supprimées.
            </div>

            <form method="POST" style="margin-top:22px;">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">

                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php" class="btn btn-outline">Annuler</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_view.php?id=<?= (int)$rule['id'] ?>" class="btn btn-secondary">Voir la règle</a>
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>