<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
$pdo = getPDO();
$pagePermission = 'support_request_access';
require_once __DIR__ . '/../../includes/permission_middleware.php';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';

$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$priority = trim($_POST['priority'] ?? 'normal');

$allowedPriorities = ['low', 'normal', 'high', 'urgent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($subject === '') {
            throw new Exception('Le sujet est obligatoire.');
        }

        if ($message === '') {
            throw new Exception('Le message est obligatoire.');
        }

        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'normal';
        }

        $stmt = $pdo->prepare("
            INSERT INTO support_requests (
                user_id,
                request_type,
                subject,
                message,
                priority,
                status,
                created_at
            ) VALUES (?, 'access', ?, ?, ?, 'open', NOW())
        ");

        $stmt->execute([
            (int)$_SESSION['user_id'],
            $subject,
            $message,
            $priority
        ]);

        $requestId = (int)$pdo->lastInsertId();

        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'create_support_request',
            'support',
            'support_request',
            $requestId,
            'Demande d’accès créée'
        );

        $successMessage = 'Ta demande d’accès a bien été enregistrée.';
        $subject = '';
        $message = '';
        $priority = 'normal';

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Demander un accès', 'Créer une demande claire de droit ou d’extension de périmètre.'); ?>

        <div class="page-title">

        <?php if ($successMessage): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Nouvelle demande</h3>

                <form method="POST">
                    <label for="subject">Sujet</label>
                    <input type="text" name="subject" id="subject" value="<?= e($subject) ?>" required>

                    <label for="priority">Priorité</label>
                    <select name="priority" id="priority" required>
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Faible</option>
                        <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>Normale</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>Haute</option>
                        <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                    </select>

                    <label for="message">Message</label>
                    <textarea name="message" id="message" required><?= e($message) ?></textarea>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Envoyer la demande</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Conseil utile</h3>

                <div class="dashboard-note">
                    Précise toujours :
                    le module concerné, le besoin métier, le niveau d’urgence et l’impact concret.
                    Une demande claire traverse l’admin comme une flèche propre.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>