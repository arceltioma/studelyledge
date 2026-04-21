<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'support_request_create');
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($subject === '' || $message === '') {
            throw new RuntimeException('Merci de compléter tous les champs.');
        }

        if (tableExists($pdo, 'support_requests')) {
            $stmt = $pdo->prepare("
                INSERT INTO support_requests (request_type, subject, message, status, created_at)
                VALUES ('question', ?, ?, 'open', NOW())
            ");
            $stmt->execute([$subject, $message]);
        }

        $successMessage = 'Question enregistrée avec succès.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Poser une question';
$pageSubtitle = 'Créer une demande de support de type question.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>
        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>

        <div class="form-card">

            <form method="POST">
                <?= csrf_input() ?>

                <div>
                    <label>Sujet</label>
                    <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" required>
                </div>

                <div>
                    <label>Message</label>
                    <textarea name="message" required><?= e($_POST['message'] ?? '') ?></textarea>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-secondary">Envoyer</button>
                    <a href="<?= e(APP_URL) ?>modules/admin/support_requests.php" class="btn btn-outline">Retour</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>