<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

$userId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($userId <= 0) {
    die('Utilisateur invalide.');
}

$stmtUser = $pdo->prepare("
    SELECT id, username, is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('Utilisateur introuvable.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                is_active = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'archive_user',
                'admin',
                'user',
                $userId,
                "Archivage d'un utilisateur : " . ($user['username'] ?? '—')
            );
        }

        header('Location: ' . APP_URL . 'modules/admin/users.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Archiver un utilisateur';
$pageSubtitle = 'Désactiver un compte sans supprimer son historique.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Confirmation</h3>
                <p>
                    Tu es sur le point d’archiver cet utilisateur.
                    Le compte ne pourra plus se connecter, mais son historique restera intact.
                </p>

                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$userId ?>">

                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger">Confirmer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Utilisateur concerné</h3>

                <div class="stat-row">
                    <span class="metric-label">Nom utilisateur</span>
                    <span class="metric-value"><?= e($user['username'] ?? '—') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">État actuel</span>
                    <span class="metric-value"><?= (int)($user['is_active'] ?? 0) === 1 ? 'Actif' : 'Inactif' ?></span>
                </div>

                <div class="dashboard-note">
                    Cette action est réversible via une réactivation ultérieure si tu ajoutes ensuite ce workflow.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>