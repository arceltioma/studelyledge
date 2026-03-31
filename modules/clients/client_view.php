<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_view_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$selectFields = [
    'c.*',
    'ta.account_code AS treasury_account_code',
    'ta.account_label AS treasury_account_label'
];

$stmt = $pdo->prepare("
    SELECT " . implode(', ', $selectFields) . "
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$pageTitle = 'Fiche client';
$pageSubtitle = 'Vue détaillée du client, de ses coordonnées et de ses rattachements.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h1><?= e($client['full_name'] ?? '') ?></h1>
                <p class="muted">Code client : <?= e($client['client_code'] ?? '') ?></p>
            </div>

            <div class="btn-group">
                <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_edit_page') : currentUserCan($pdo, 'clients_edit')): ?>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$id ?>" class="btn btn-success">Modifier</a>
                <?php endif; ?>

                <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Identité & coordonnées</h3>

                <div class="detail-grid">
                    <div class="detail-row"><span class="detail-label">Prénom</span><span class="detail-value"><?= e($client['first_name'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Nom</span><span class="detail-value"><?= e($client['last_name'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?= e($client['email'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Téléphone</span><span class="detail-value"><?= e($client['phone'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Type client</span><span class="detail-value"><?= e($client['client_type'] ?? '') ?></span></div>

                    <?php if (columnExists($pdo, 'clients', 'postal_address')): ?>
                        <div class="detail-row">
                            <span class="detail-label">Adresse postale</span>
                            <span class="detail-value"><?= nl2br(e($client['postal_address'] ?? '')) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Rattachement financier</h3>

                <div class="detail-grid">
                    <div class="detail-row"><span class="detail-label">Compte 411</span><span class="detail-value"><?= e($client['generated_client_account'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Compte 512</span><span class="detail-value"><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? ''))) ?></span></div>
                    <div class="detail-row"><span class="detail-label">Devise</span><span class="detail-value"><?= e($client['currency'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Pays d'origine</span><span class="detail-value"><?= e($client['country_origin'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Pays de destination</span><span class="detail-value"><?= e($client['country_destination'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Pays commercial</span><span class="detail-value"><?= e($client['country_commercial'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">État</span><span class="detail-value"><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></span></div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>