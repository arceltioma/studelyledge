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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Génération des mensualités clients';
$pageSubtitle = 'Lancement contrôlé des virements automatiques 411 vers 512';

$runDate = trim((string)($_POST['run_date'] ?? $_GET['run_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
    $runDate = date('Y-m-d');
}

$report = null;
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $report = sl_run_monthly_client_operations($pdo, $runDate, (int)($_SESSION['user_id'] ?? 0));
        $successMessage = 'Traitement terminé : ' . (int)$report['created'] . ' mensualité(s) créée(s).';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Lancer la génération</h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <div>
                        <label>Date de traitement</label>
                        <input type="date" name="run_date" value="<?= e($runDate) ?>" required>
                    </div>

                    <div class="dashboard-note" style="margin-top:16px;">
                        Les mensualités ne seront générées que pour les clients actifs, configurés, avec jour mensuel correspondant, et sans doublon déjà généré pour le mois.
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Lancer le traitement</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Résultat</h3>

                <?php if (is_array($report)): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Date</span><strong><?= e((string)$report['run_date']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Clients analysés</span><strong><?= (int)$report['processed'] ?></strong></div>
                        <div class="sl-data-list__row"><span>Mensualités créées</span><strong><?= (int)$report['created'] ?></strong></div>
                        <div class="sl-data-list__row"><span>Lignes ignorées</span><strong><?= (int)$report['skipped'] ?></strong></div>
                        <div class="sl-data-list__row"><span>Erreurs</span><strong><?= count($report['errors'] ?? []) ?></strong></div>
                    </div>

                    <?php if (!empty($report['errors'])): ?>
                        <div class="table-card" style="margin-top:16px;">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Erreur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report['errors'] as $item): ?>
                                        <tr>
                                            <td><?= e(($item['client_code'] ?? '') . ' #' . (int)($item['client_id'] ?? 0)) ?></td>
                                            <td><?= e((string)($item['message'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dashboard-note">
                        Lance le traitement pour générer les mensualités dues à la date choisie.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
