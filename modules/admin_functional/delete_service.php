<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'delete_service_page');
}
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Service invalide.');
}

$stmt = $pdo->prepare("
    SELECT
        rs.*,
        rot.code AS operation_type_code,
        rot.label AS operation_type_label,
        sa.account_code AS service_account_code,
        sa.account_label AS service_account_label,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
    FROM ref_services rs
    LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
    LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
    LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
    WHERE rs.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Service introuvable.');
}

$errorMessage = '';
$warningMessage = '';

if (!empty($row['operation_type_id'])) {
    $warningMessage = 'Ce service est actuellement rattaché au type d’opération : '
        . ($row['operation_type_label'] ?? 'Type inconnu')
        . ' (' . ($row['operation_type_code'] ?? '—') . ').';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE ref_services
            SET is_active = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$id]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'archive_service',
                'admin_functional',
                'service',
                $id,
                'Archivage d’un service avec contrôle des liens métier'
            );
        }

        header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php?ok=archived');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Archiver un service';
$pageSubtitle = 'On désactive le service sans supprimer son historique ni casser le référentiel.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($warningMessage !== ''): ?>
            <div class="warning"><?= e($warningMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Confirmation</h3>
                <p>
                    Tu vas archiver le service
                    <strong><?= e($row['label'] ?? '') ?></strong>
                    (<?= e($row['code'] ?? '') ?>).
                </p>
                <p class="muted">
                    L’archivage désactive son usage futur, mais conserve l’historique et les liens existants.
                </p>

                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger">Confirmer l’archivage</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Contrôle des liens métier</h3>

                <div class="stat-row">
                    <span class="metric-label">Code service</span>
                    <span class="metric-value"><?= e($row['code'] ?? '') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Libellé</span>
                    <span class="metric-value"><?= e($row['label'] ?? '') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Type d’opération</span>
                    <span class="metric-value"><?= e(trim((string)($row['operation_type_label'] ?? '') . ' (' . (string)($row['operation_type_code'] ?? '—') . ')')) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Compte 706</span>
                    <span class="metric-value"><?= e(trim((string)($row['service_account_code'] ?? '') . ' - ' . (string)($row['service_account_label'] ?? ''))) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Compte 512</span>
                    <span class="metric-value"><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">État actuel</span>
                    <span class="metric-value"><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Déjà archivé' ?></span>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>