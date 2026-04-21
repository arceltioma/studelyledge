<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'users_delete');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('sl_create_notification_if_possible')) {
    function sl_create_notification_if_possible(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $linkUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $createdBy = null
    ): void {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_by' => $createdBy > 0 ? $createdBy : null,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

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

if (isset($_SESSION['success_message']) && $_SESSION['success_message'] !== '') {
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message']) && $_SESSION['error_message'] !== '') {
    unset($_SESSION['error_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        if ((int)($user['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Cet utilisateur est déjà inactif.');
        }

        if ($currentUserId > 0 && $currentUserId === $userId) {
            throw new RuntimeException('Tu ne peux pas archiver ton propre compte connecté.');
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                is_active = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        if (function_exists('logUserAction') && $currentUserId > 0) {
            logUserAction(
                $pdo,
                $currentUserId,
                'archive_user',
                'admin',
                'user',
                $userId,
                "Archivage d'un utilisateur : " . ($user['username'] ?? '—')
            );
        }

        sl_create_notification_if_possible(
            $pdo,
            'user_archived',
            "Utilisateur archivé : " . ($user['username'] ?? '—'),
            'warning',
            APP_URL . 'modules/admin/users.php',
            'user',
            $userId,
            $currentUserId > 0 ? $currentUserId : null
        );

        $_SESSION['success_message'] = "L'utilisateur " . ($user['username'] ?? '—') . " a été archivé avec succès.";

        header('Location: ' . APP_URL . 'modules/admin/users.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'archive_user_failed',
                'admin',
                'user',
                $userId,
                "Échec archivage utilisateur : " . ($user['username'] ?? '—') . " | erreur : " . $errorMessage
            );
        }

        sl_create_notification_if_possible(
            $pdo,
            'user_archive_failed',
            "Échec archivage utilisateur : " . ($user['username'] ?? '—') . " | erreur : " . $errorMessage,
            'danger',
            APP_URL . 'modules/admin/user_delete.php?id=' . $userId,
            'user',
            $userId,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
        );
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