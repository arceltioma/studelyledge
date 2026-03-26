<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'admin_users_manage';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

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

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (array_key_exists('is_active', $user)) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET is_active = 0
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'delete_user',
                'admin',
                'user',
                $userId,
                "Archivage ou suppression d'un utilisateur : " . ($user['username'] ?? '—')
            );
        }

        $pdo->commit();
        header('Location: ' . APP_URL . 'modules/admin/users.php?success=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Supprimer ou archiver un utilisateur',
            'Une opération simple, mais qui mérite d’être faite sans faux pas de syntaxe.'
        ); ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h3 class="section-title">Utilisateur concerné</h3>

            <div class="stat-row">
                <span class="metric-label">Nom utilisateur</span>
                <span class="metric-value"><?= e($user['username'] ?? '—') ?></span>
            </div>

            <div class="stat-row">
                <span class="metric-label">État</span>
                <span class="metric-value"><?= (int)($user['is_active'] ?? 0) === 1 ? 'Actif' : 'Inactif' ?></span>
            </div>

            <form method="POST" style="margin-top:24px;">
                <input type="hidden" name="id" value="<?= (int)$userId ?>">

                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">Confirmer</button>
                    <a href="<?= APP_URL ?>modules/admin/users.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>