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
$priority = trim((string)($_POST['priority'] ?? 'high'));

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
            $priority = 'high';
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
            ) VALUES (?, ?, 'bug', ?, ?, ?, 'open', NOW(), NOW())
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
                'support_bug_report_create',
                'support',
                'support_request',
                (int)$pdo->lastInsertId(),
                'Création d’un signalement de bug'
            );
        }

        $successMessage = 'Signalement de bug envoyé.';
        $subject = '';
        $message = '';
        $priority = 'high';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Signaler un bug';
$pageSubtitle = 'Décrire précisément un comportement anormal ou une erreur rencontrée.';
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
                <h3 class="section-title">Nouveau signalement</h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <label for="subject">Sujet</label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        required
                        value="<?= e($subject) ?>"
                        placeholder="Exemple : erreur lors de l’import CSV"
                    >

                    <label for="priority">Priorité</label>
                    <select name="priority" id="priority">
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Basse</option>
                        <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>Normale</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>Haute</option>
                        <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                    </select>

                    <label for="message">Description du bug</label>
                    <textarea
                        name="message"
                        id="message"
                        rows="8"
                        required
                        placeholder="Décrivez le chemin suivi, le résultat affiché, le résultat attendu, et si possible la page concernée."
                    ><?= e($message) ?></textarea>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger">Envoyer le signalement</button>
                        <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Bon réflexe</h3>
                <div class="dashboard-note">
                    Pour un traitement plus rapide, précise la page concernée,
                    ce que tu as cliqué juste avant, et le comportement attendu.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>