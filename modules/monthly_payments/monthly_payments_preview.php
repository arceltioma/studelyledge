<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_create_page');
} else {
    enforcePagePermission($pdo, 'operations_create');
}

$importId = (int)($_GET['import_id'] ?? $_POST['import_id'] ?? 0);
if ($importId <= 0) {
    exit('Import invalide.');
}

$stmtImport = $pdo->prepare("SELECT * FROM monthly_payment_imports WHERE id = ? LIMIT 1");
$stmtImport->execute([$importId]);
$import = $stmtImport->fetch(PDO::FETCH_ASSOC);

if (!$import) {
    exit('Import introuvable.');
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'validate') {
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM monthly_payment_import_rows
                WHERE import_id = ?
                  AND status = 'error'
            ");
            $stmtCheck->execute([$importId]);

            if ((int)$stmtCheck->fetchColumn() > 0) {
                throw new RuntimeException('Des lignes en erreur empêchent la validation.');
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE monthly_payment_imports
                SET status = 'validated', updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$importId]);

            $successMessage = 'Import validé manuellement avec succès.';
            $import['status'] = 'validated';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$stmtRows = $pdo->prepare("
    SELECT *
    FROM monthly_payment_import_rows
    WHERE import_id = ?
    ORDER BY row_number ASC
");
$stmtRows->execute([$importId]);
$rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="card">
            <h3>Aperçu import mensualités</h3>

            <div class="sl-data-list">
                <div class="sl-data-list__row"><span>Fichier</span><strong><?= e((string)($import['file_name'] ?? '')) ?></strong></div>
                <div class="sl-data-list__row"><span>Statut</span><strong><?= e((string)($import['status'] ?? 'draft')) ?></strong></div>
                <div class="sl-data-list__row"><span>Nombre de lignes</span><strong><?= count($rows) ?></strong></div>
            </div>

            <div class="btn-group" style="margin:20px 0;">
                <?php if (($import['status'] ?? 'draft') !== 'validated'): ?>
                    <form method="POST" style="display:inline-block;">
                        <?= csrf_input() ?>
                        <input type="hidden" name="import_id" value="<?= (int)$importId ?>">
                        <button type="submit" name="action" value="validate" class="btn btn-success">Valider manuellement</button>
                    </form>
                <?php endif; ?>

                <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_payments_import.php" class="btn btn-outline">Nouvel import</a>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Compte mensualité</th>
                            <th>Jour</th>
                            <th>Libellé</th>
                            <th>Statut</th>
                            <th>Erreur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int)$row['row_number'] ?></td>
                                <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                                <td><?= number_format((float)($row['monthly_amount'] ?? 0), 2, ',', ' ') ?></td>
                                <td><?= e((string)($row['treasury_account_code'] ?? '')) ?></td>
                                <td><?= (int)($row['monthly_day'] ?? 26) ?></td>
                                <td><?= e((string)($row['label'] ?? '')) ?></td>
                                <td><?= e((string)($row['status'] ?? 'pending')) ?></td>
                                <td><?= e((string)($row['error_message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="8">Aucune ligne importée.</td>
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
