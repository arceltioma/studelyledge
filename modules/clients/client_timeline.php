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

$clientId = (int)($_GET['id'] ?? 0);
if ($clientId <= 0) {
    exit('Client invalide.');
}

$client = null;
if (tableExists($pdo, 'clients')) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM clients
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$client) {
    exit('Client introuvable.');
}

$pageTitle = 'Timeline client';
$pageSubtitle = 'Historique consolidé du client';

$timeline = getEntityTimeline($pdo, 'client', $clientId, 100);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-page-hero sl-stable-block">
            <div>
                <h1><?= e($pageTitle) ?></h1>
                <p><?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?></p>
            </div>
        </section>

        <section class="sl-card">
            <?php if ($timeline): ?>
                <?php foreach ($timeline as $item): ?>
                    <div class="sl-mini-list-item" style="padding:14px 0; border-bottom:1px solid rgba(148,163,184,0.12);">
                        <div class="sl-mini-list-item__top">
                            <strong><?= e((string)($item['title'] ?? 'Événement')) ?></strong>
                        </div>
                        <div class="sl-mini-list-item__bottom">
                            <span class="sl-muted"><?= e((string)($item['created_at'] ?? '')) ?></span>
                            <span class="sl-muted"><?= e((string)($item['source_type'] ?? '')) ?></span>
                        </div>

                        <?php if (($item['source_type'] ?? '') === 'audit'): ?>
                            <div class="muted" style="margin-top:6px;">
                                Ancienne valeur : <?= e((string)($item['old_value'] ?? '')) ?><br>
                                Nouvelle valeur : <?= e((string)($item['new_value'] ?? '')) ?>
                            </div>
                        <?php elseif (!empty($item['details'])): ?>
                            <div class="muted" style="margin-top:6px;">
                                <?= e((string)$item['details']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">Aucun historique disponible pour ce client.</p>
            <?php endif; ?>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>