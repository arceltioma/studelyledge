<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'dashboard_view_page');
} else {
    enforcePagePermission($pdo, 'dashboard_view');
}

$q = trim((string)($_GET['q'] ?? ''));
$pageTitle = 'Recherche globale';
$pageSubtitle = 'Recherche transversale sur clients, opérations, trésorerie et services';

$results = globalSearch($pdo, $q, 12);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-page-hero sl-stable-block">
            <div>
                <h1><?= e($pageTitle) ?></h1>
                <p><?= e($pageSubtitle) ?></p>
            </div>
        </section>

        <section class="sl-card" style="margin-bottom:20px;">
            <form method="GET">
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Client, référence, compte, service..." style="flex:1; min-width:280px;">
                    <button type="submit" class="btn btn-success">Rechercher</button>
                </div>
            </form>
        </section>

        <?php if ($q === ''): ?>
            <div class="card">
                <p class="muted">Saisis un mot-clé pour lancer une recherche globale.</p>
            </div>
        <?php else: ?>

            <div class="dashboard-grid-2">
                <div class="card">
                    <h3>Clients</h3>
                    <?php if ($results['clients']): ?>
                        <?php foreach ($results['clients'] as $row): ?>
                            <div class="stat-row">
                                <span class="metric-label"><?= e(($row['client_code'] ?? '') . ' - ' . ($row['full_name'] ?? '')) ?></span>
                                <a class="btn btn-sm btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">Aucun résultat.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Opérations</h3>
                    <?php if ($results['operations']): ?>
                        <?php foreach ($results['operations'] as $row): ?>
                            <div class="stat-row">
                                <span class="metric-label"><?= e(($row['operation_date'] ?? '') . ' - ' . (($row['reference'] ?? '') !== '' ? $row['reference'] : ($row['label'] ?? ''))) ?></span>
                                <a class="btn btn-sm btn-secondary" href="<?= e(APP_URL) ?>modules/operations/operation_view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">Aucun résultat.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Trésorerie</h3>
                    <?php if ($results['treasury']): ?>
                        <?php foreach ($results['treasury'] as $row): ?>
                            <div class="stat-row">
                                <span class="metric-label"><?= e(($row['account_code'] ?? '') . ' - ' . ($row['account_label'] ?? '')) ?></span>
                                <a class="btn btn-sm btn-secondary" href="<?= e(APP_URL) ?>modules/treasury/treasury_view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">Aucun résultat.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Services</h3>
                    <?php if ($results['services']): ?>
                        <?php foreach ($results['services'] as $row): ?>
                            <div class="stat-row">
                                <span class="metric-label"><?= e(($row['code'] ?? '') . ' - ' . ($row['label'] ?? '')) ?></span>
                                <a class="btn btn-sm btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">Ouvrir</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">Aucun résultat.</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>