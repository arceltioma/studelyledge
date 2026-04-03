<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_manage_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de service invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM service_accounts
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de service introuvable.');
}

$pageTitle = 'Fiche compte de service (706)';
$pageSubtitle = 'Consultation détaillée du compte de produit';

$timeline = function_exists('getEntityTimeline')
    ? getEntityTimeline($pdo, 'service_account', $id, 20)
    : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Informations générales</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>ID</span>
                        <strong><?= (int)$account['id'] ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Code compte</span>
                        <strong><?= e((string)($account['account_code'] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Intitulé</span>
                        <strong><?= e((string)($account['account_label'] ?? '')) ?></strong>
                    </div>

                    <?php if (array_key_exists('commercial_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays commercial</span>
                            <strong><?= e((string)($account['commercial_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('destination_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays destination</span>
                            <strong><?= e((string)($account['destination_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('current_balance', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Solde courant</span>
                            <strong><?= e(number_format((float)($account['current_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('is_postable', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Postable</span>
                            <strong><?= ((int)($account['is_postable'] ?? 0) === 1) ? 'Oui' : 'Non' ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('is_active', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Statut</span>
                            <strong><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('created_at', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Créé le</span>
                            <strong><?= e((string)($account['created_at'] ?? '')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('updated_at', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Mis à jour le</span>
                            <strong><?= e((string)($account['updated_at'] ?? '')) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/edit.php?id=<?= (int)$id ?>" class="btn btn-success">Modifier</a>
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Retour</a>
                </div>
            </div>

            <div class="card">
                <h3>Historique / Timeline</h3>

                <?php if ($timeline): ?>
                    <div class="sl-anomaly-list">
                        <?php foreach ($timeline as $item): ?>
                            <div class="sl-anomaly-list__item">
                                <span class="sl-anomaly-list__label">
                                    <?= e((string)($item['title'] ?? 'Événement')) ?>
                                    <?php if (!empty($item['details'])): ?>
                                        — <?= e((string)$item['details']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($item['old_value']) || !empty($item['new_value'])): ?>
                                        — <?= e((string)($item['old_value'] ?? '')) ?> → <?= e((string)($item['new_value'] ?? '')) ?>
                                    <?php endif; ?>
                                </span>
                                <strong class="sl-anomaly-list__value"><?= e((string)($item['created_at'] ?? '')) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucun historique disponible.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>