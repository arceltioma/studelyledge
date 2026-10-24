<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$pdo = getPDO();

$pageTitle = 'Contact';
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Session invalide, veuillez réessayer.');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '' || $email === '' || $subject === '' || $message === '') {
            throw new RuntimeException('Tous les champs sont obligatoires.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresse email invalide.');
        }

        if (tableExists($pdo, 'contact_messages')) {
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $email, $subject, $message]);
        }

        $successMessage = 'Votre message a été envoyé avec succès.';
        $_POST = [];

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/document_start.php';
?>

<div class="public-page-shell">
    <div class="public-page-container">

        <!-- HEADER PREMIUM -->
        <div class="studely-header">
            <div class="header-left">
                <div class="header-titles">
                    <span class="header-overline"><?= e(APP_NAME) ?></span>
                    <h1 class="header-title">Contact</h1>
                    <div class="header-subtitle">
                        Une question, un besoin, une suggestion ? Notre équipe vous répond rapidement.
                    </div>
                </div>
            </div>

            <div class="header-right">
                <div class="header-actions">
                    <a href="<?= e(APP_URL) ?>login.php" class="btn btn-primary">Connexion</a>
                </div>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">

            <!-- INFOS -->
            <div class="card">
                <h3>Coordonnées</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Email</span>
                        <strong>support@studelyledger.com</strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Téléphone</span>
                        <strong>+33 6 73 91 06 69</strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Disponibilité</span>
                        <strong>Lundi – Vendredi • 9h – 18h</strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Délai de réponse</span>
                        <strong>&lt; 24h ouvrées</strong>
                    </div>
                </div>

                <div class="dashboard-note" style="margin-top:16px;">
                    Pour toute demande technique, privilégie le formulaire ci-contre afin de garantir un suivi optimal.
                </div>
            </div>

            <!-- FORMULAIRE -->
            <div class="card">
                <h3>Envoyer un message</h3>

                <form method="POST" style="display:grid; gap:14px;">
                    <?= csrf_input() ?>

                    <div>
                        <label>Nom</label>
                        <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Sujet</label>
                        <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Message</label>
                        <textarea name="message" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <div class="btn-group" style="margin-top:10px;">
                        <button type="submit" class="btn btn-success">Envoyer</button>
                        <a href="<?= e(APP_URL) ?>login.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

        </div>

        <!-- BLOC AIDE -->
        <div class="card" style="margin-top:20px;">
            <h3>Types de demandes</h3>

            <div class="dashboard-grid-3">
                <div class="mini-card">
                    <strong>Support technique</strong>
                    <p class="muted">Incident, bug, problème d’accès</p>
                </div>

                <div class="mini-card">
                    <strong>Fonctionnel</strong>
                    <p class="muted">Questions métier, utilisation</p>
                </div>

                <div class="mini-card">
                    <strong>Amélioration</strong>
                    <p class="muted">Suggestions d’évolution</p>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>