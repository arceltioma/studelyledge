<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

$pagePermission = 'support_admin_manage';
require_once __DIR__ . '/../../includes/permission_middleware.php';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';

$type = trim($_GET['type'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedTypes = ['access', 'bug', 'question'];
$allowedStatuses = ['open', 'in_progress', 'closed'];
$allowedPriorities = ['low', 'normal', 'high', 'urgent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        if ($requestId <= 0) {
            throw new Exception('Demande invalide.');
        }

        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new Exception('Statut invalide.');
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE support_requests
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$newStatus, $requestId]);

        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'update_support_request',
            'support',
            'support_request',
            $requestId,
            'Mise à jour du statut support vers ' . $newStatus
        );

        $successMessage = 'Le statut de la demande a été mis à jour.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$sql = "
    SELECT
        sr.*,
        u.username
    FROM support_requests sr
    LEFT JOIN users u ON u.id = sr.user_id
    WHERE 1=1
";
$params = [];

if ($type !== '' && in_array($type, $allowedTypes, true)) {
    $sql .= " AND sr.request_type = ?";
    $params[] = $type;
}

if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $sql .= " AND sr.status = ?";
    $params[] = $status;
}

if ($priority !== '' && in_array($priority, $allowedPriorities, true)) {
    $sql .= " AND sr.priority = ?";
    $params[] = $priority;
}

if ($search !== '') {
    $sql .= " AND (
        sr.subject LIKE ?
        OR sr.message LIKE ?
        OR u.username LIKE ?
    )";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql .= " ORDER BY sr.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

function supportStatusBadge(string $status): string
{
    return match ($status) {
        'open' => 'status-danger',
        'in_progress' => 'status-warning',
        'closed' => 'status-success',
        default => 'status-info',
    };
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Demandes support', 'Traitement centralisé des accès, bugs et questions.'); ?>

        <?php if ($successMessage): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <select name="type">
                    <option value="">Tous les types</option>
                    <option value="access" <?= $type === 'access' ? 'selected' : '' ?>>Accès</option>
                    <option value="bug" <?= $type === 'bug' ? 'selected' : '' ?>>Bug</option>
                    <option value="question" <?= $type === 'question' ? 'selected' : '' ?>>Question</option>
                </select>

                <select name="status">
                    <option value="">Tous les statuts</option>
                    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>

                <select name="priority">
                    <option value="">Toutes les priorités</option>
                    <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                </select>

                <input type="text" name="search" placeholder="Sujet, message, utilisateur..." value="<?= e($search) ?>">

                <button type="submit" class="btn btn-secondary">Filtrer</button>
                <a href="<?= APP_URL ?>modules/admin/support_requests.php" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <div class="timeline">
            <?php if (!$requests): ?>
                <div class="warning">Aucune demande support trouvée.</div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-meta">
                                <div>
                                    <strong>#<?= (int)$request['id'] ?> — <?= e($request['subject']) ?></strong><br>
                                    <span class="muted">
                                        <?= e($request['username'] ?? 'Utilisateur inconnu') ?> — <?= e($request['created_at']) ?>
                                    </span>
                                </div>

                                <div class="timeline-badges">
                                    <span class="status-pill status-info"><?= e($request['request_type']) ?></span>
                                    <span class="status-pill status-warning"><?= e($request['priority']) ?></span>
                                    <span class="status-pill <?= e(supportStatusBadge((string)$request['status'])) ?>">
                                        <?= e($request['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="timeline-text">
                                <?= nl2br(e($request['message'])) ?>
                            </div>

                            <form method="POST" class="inline-form" style="margin-top:12px;">
                                <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">

                                <select name="new_status" required>
                                    <option value="open" <?= $request['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="in_progress" <?= $request['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                                    <option value="closed" <?= $request['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>

                                <button type="submit" class="btn btn-success">Mettre à jour</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>