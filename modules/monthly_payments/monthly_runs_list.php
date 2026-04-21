<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);
$pageTitle = 'Historique mensualités';
$pageSubtitle = 'Imports, validations et runs de génération mensuelle';

$imports = [];
$runs = [];

if (tableExists($pdo, 'monthly_payment_imports')) {
    $stmt = $pdo->query("
        SELECT *
        FROM monthly_payment_imports
        ORDER BY id DESC
        LIMIT 50
    ");
    $imports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (tableExists($pdo, 'monthly_payment_runs')) {
    $stmt = $pdo->query("
        SELECT *
        FROM monthly_payment_runs
        ORDER BY id DESC
        LIMIT 50
    ");
    $runs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="btn-group" style="margin-bottom:20px;">
            <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_import_create.php" class="btn btn-success">Nouvel import</a>
            <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_run_execute.php" class="btn btn-primary">Exécuter un run</a>
        </div>

        <div class="card">
            <h3>Imports de mensualités</h3>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fichier</th>
                            <th>Statut</th>
                            <th>Créé par</th>
                            <th>Créé le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imports as $import): ?>
                            <tr>
                                <td><?= (int)($import['id'] ?? 0) ?></td>
                                <td><?= e((string)($import['file_name'] ?? '')) ?></td>
                                <td><?= e((string)($import['status'] ?? '')) ?></td>
                                <td><?= e((string)($import['created_by'] ?? '')) ?></td>
                                <td><?= e((string)($import['created_at'] ?? '')) ?></td>
                                <td>
                                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_import_preview.php?id=<?= (int)$import['id'] ?>" class="btn btn-sm">Voir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$imports): ?>
                            <tr><td colspan="6">Aucun import trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Runs de génération</h3>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date run</th>
                            <th>Jour planifié</th>
                            <th>Total clients</th>
                            <th>Créées</th>
                            <th>Ignorées</th>
                            <th>Erreurs</th>
                            <th>Exécuté par</th>
                            <th>Créé le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runs as $run): ?>
                            <tr>
                                <td><?= (int)($run['id'] ?? 0) ?></td>
                                <td><?= e((string)($run['run_date'] ?? '')) ?></td>
                                <td><?= e((string)($run['scheduled_day'] ?? '')) ?></td>
                                <td><?= (int)($run['total_clients'] ?? 0) ?></td>
                                <td><?= (int)($run['total_created'] ?? 0) ?></td>
                                <td><?= (int)($run['total_skipped'] ?? 0) ?></td>
                                <td><?= (int)($run['total_errors'] ?? 0) ?></td>
                                <td><?= e((string)($run['executed_by'] ?? '')) ?></td>
                                <td><?= e((string)($run['created_at'] ?? '')) ?></td>
                                <td>
                                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_run_view.php?id=<?= (int)$run['id'] ?>" class="btn btn-sm">Voir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$runs): ?>
                            <tr><td colspan="10">Aucun run trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>