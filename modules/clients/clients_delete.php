<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'clients_delete');
}

$clientId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($clientId <= 0) {
    exit('Client invalide.');
}

if (!tableExists($pdo, 'clients')) {
    exit('Table clients introuvable.');
}

$stmt = $pdo->prepare("
    SELECT c.*
    FROM clients c
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $deleteMode = trim((string)($_POST['delete_mode'] ?? 'soft'));

        $pdo->beginTransaction();

        $before = $client;

        if ($deleteMode === 'hard') {
            if (tableExists($pdo, 'client_bank_accounts')) {
                $stmtLinks = $pdo->prepare("DELETE FROM client_bank_accounts WHERE client_id = ?");
                $stmtLinks->execute([$clientId]);
            }

            $stmtDelete = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmtDelete->execute([$clientId]);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'delete_client_hard',
                    'clients',
                    'client',
                    $clientId,
                    'Suppression définitive du client ' . ((string)($before['client_code'] ?? ('#' . $clientId)))
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'client_delete',
                    'Client supprimé définitivement : ' . ((string)($before['client_code'] ?? '') . ' - ' . (string)($before['full_name'] ?? '')),
                    'warning',
                    APP_URL . 'modules/clients/clients_list.php',
                    'client',
                    $clientId,
                    (int)$_SESSION['user_id']
                );
            }

            $pdo->commit();
            header('Location: ' . APP_URL . 'modules/clients/clients_list.php?deleted=1');
            exit;
        }

        $updated = false;

        if (columnExists($pdo, 'clients', 'is_active')) {
            $stmtArchive = $pdo->prepare("
                UPDATE clients
                SET is_active = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtArchive->execute([$clientId]);
            $updated = true;
        } elseif (columnExists($pdo, 'clients', 'is_deleted')) {
            $stmtArchive = $pdo->prepare("
                UPDATE clients
                SET is_deleted = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtArchive->execute([$clientId]);
            $updated = true;
        } elseif (columnExists($pdo, 'clients', 'deleted_at')) {
            $stmtArchive = $pdo->prepare("
                UPDATE clients
                SET deleted_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtArchive->execute([$clientId]);
            $updated = true;
        } else {
            if (tableExists($pdo, 'client_bank_accounts')) {
                $stmtLinks = $pdo->prepare("DELETE FROM client_bank_accounts WHERE client_id = ?");
                $stmtLinks->execute([$clientId]);
            }

            $stmtDelete = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmtDelete->execute([$clientId]);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'delete_client_fallback',
                    'clients',
                    'client',
                    $clientId,
                    'Suppression fallback du client ' . ((string)($before['client_code'] ?? ('#' . $clientId)))
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'client_delete',
                    'Client supprimé : ' . ((string)($before['client_code'] ?? '') . ' - ' . (string)($before['full_name'] ?? '')),
                    'warning',
                    APP_URL . 'modules/clients/clients_list.php',
                    'client',
                    $clientId,
                    (int)$_SESSION['user_id']
                );
            }

            $pdo->commit();
            header('Location: ' . APP_URL . 'modules/clients/clients_list.php?deleted=1');
            exit;
        }

        if ($updated) {
            if (function_exists('auditEntityChanges') && isset($_SESSION['user_id'])) {
                $stmtAfter = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
                $stmtAfter->execute([$clientId]);
                $after = $stmtAfter->fetch(PDO::FETCH_ASSOC) ?: [];

                auditEntityChanges($pdo, 'client', $clientId, $before, $after, (int)$_SESSION['user_id']);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'archive_client',
                    'clients',
                    'client',
                    $clientId,
                    'Archivage du client ' . ((string)($before['client_code'] ?? ('#' . $clientId)))
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'client_archive',
                    'Client archivé : ' . ((string)($before['client_code'] ?? '') . ' - ' . (string)($before['full_name'] ?? '')),
                    'info',
                    APP_URL . 'modules/clients/clients_list.php',
                    'client',
                    $clientId,
                    (int)$_SESSION['user_id']
                );
            }
        }

        $pdo->commit();
        header('Location: ' . APP_URL . 'modules/clients/clients_list.php?archived=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Supprimer un client';
$pageSubtitle = 'Archivage ou suppression définitive du client';

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

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$clientId ?></strong></div>
                    <div class="sl-data-list__row"><span>Code client</span><strong><?= e((string)($client['client_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e((string)($client['full_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e((string)($client['generated_client_account'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Type</span><strong><?= e((string)($client['client_type'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Inactif' ?></strong></div>
                </div>

                <form method="POST" style="margin-top:20px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$clientId ?>">

                    <div style="display:grid; gap:12px;">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="delete_mode" value="soft" checked>
                            Archiver le client
                        </label>

                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="delete_mode" value="hard">
                            Supprimer définitivement
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-danger">Confirmer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$clientId ?>" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Attention</h3>
                <div class="dashboard-note">
                    L’archivage est recommandé pour conserver l’historique, les rattachements et la traçabilité.  
                    La suppression définitive est à réserver aux erreurs de création ou doublons avérés.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>