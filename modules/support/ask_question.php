<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../config/security.php';

$successMessage = '';
$errorMessage = '';

if (tableExists($pdo, 'support_requests') === false) {
    $pdo->exec("
        CREATE TABLE support_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(100) NULL,
            request_type VARCHAR(50) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            status VARCHAR(50) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            CONSTRAINT fk_support_requests_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$priority = trim((string)($_POST['priority'] ?? 'normal'));

$allowedPriorities = ['low', 'normal', 'high', 'urgent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($subject === '' || $message === '') {
            throw new RuntimeException('Le sujet et le message sont obligatoires.');
        }

        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'normal';
        }

        $stmt = $pdo->prepare("
            INSERT INTO support_requests (
                user_id,
                username,
                request_type,
                subject,
                message,
                priority,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, 'question', ?, ?, ?, 'open', NOW(), NOW())
        ");
        $stmt->execute([
            (int)($_SESSION['user_id'] ?? 0) ?: null,
            $_SESSION['username'] ?? null,
            $subject,
            $message,
            $priority
        ]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'support_question_create',
                'support',
                'support_request',
                (int)$pdo->lastInsertId(),
                'Création d’une question support'
            );
        }

        $successMessage = 'Question envoyée.';
        $subject = '';
        $message = '';
        $priority = 'normal';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Poser une question';
$pageSubtitle = 'Demander une précision fonctionnelle ou technique sur la plateforme.';
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
                <h3 class="section-title">Nouvelle question</h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <label for="subject">Sujet</label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        required
                        value="<?= e($subject) ?>"
                        placeholder="Exemple : différence entre compte 512 et 706"
                    >

                    <label for="priority">Priorité</label>
                    <select name="priority" id="priority">
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Basse</option>
                        <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>Normale</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>Haute</option>
                        <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                    </select>

                    <label for="message">Question</label>
                    <textarea
                        name="message"
                        id="message"
                        rows="7"
                        required
                        placeholder="Décrivez votre question aussi précisément que possible."
                    ><?= e($message) ?></textarea>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Envoyer la question</button>
                        <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Astuce</h3>
                <div class="dashboard-note">
                    Plus la question est contextualisée, plus la réponse pourra être rapide,
                    utile et directement exploitable.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>