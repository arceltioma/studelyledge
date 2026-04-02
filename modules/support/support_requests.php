<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceAccess($pdo, 'support_view_page');

$requests = tableExists($pdo, 'support_requests')
    ? $pdo->query("
        SELECT *
        FROM support_requests
        ORDER BY created_at DESC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Support';
$pageSubtitle = 'Historique, suivi et mise à jour des demandes support.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="timeline">
            <?php if (!$requests): ?>
                <div class="dashboard-note">Aucune demande support enregistrée.</div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>

                        <div class="timeline-content">
                            <div class="timeline-meta">
                                <div>
                                    <h3><?= e($request['subject'] ?? 'Sans objet') ?></h3>
                                    <div class="muted"><?= e($request['created_at'] ?? '') ?></div>
                                </div>

                                <div class="timeline-badges">
                                    <span class="status-pill status-info"><?= e($request['request_type'] ?? '') ?></span>
                                    <span class="status-pill status-warning"><?= e($request['priority'] ?? '') ?></span>
                                    <span class="status-pill status-success"><?= e($request['status'] ?? '') ?></span>
                                </div>
                            </div>

                            <div class="timeline-text">
                                <?= nl2br(e($request['message'] ?? '')) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>