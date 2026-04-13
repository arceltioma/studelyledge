<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_delete_page');
} else {
    enforcePagePermission($pdo, 'operations_delete');
}

$operationId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($operationId <= 0) {
    exit('Opération invalide.');
}

if (!tableExists($pdo, 'operations')) {
    exit('Table operations introuvable.');
}

$stmt = $pdo->prepare("
    SELECT o.*
    FROM operations o
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$operationId]);
$operation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $deleteMode = trim((string)($_POST['delete_mode'] ?? 'soft'));

        $pdo->beginTransaction();

        $before = $operation;

        if ($deleteMode === 'hard') {
            $stmtDelete = $pdo->prepare("DELETE FROM operations WHERE id = ?");
            $stmtDelete->execute([$operationId]);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'delete_operation_hard',
                    'operations',
                    'operation',
                    $operationId,
                    'Suppression définitive de l’opération #' . $operationId
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'operation_delete',
                    'Opération supprimée définitivement : ' . ((string)($before['label'] ?? ('#' . $operationId))),
                    'warning',
                    APP_URL . 'modules/operations/operations_list.php',
                    'operation',
                    $operationId,
                    (int)$_SESSION['user_id']
                );
            }

            $pdo->commit();
            header('Location: ' . APP_URL . 'modules/operations/operations_list.php?deleted=1');
            exit;
        }

        $updated = false;

        if (columnExists($pdo, 'operations', 'is_active')) {
            $stmtArchive = $pdo->prepare("
                UPDATE operations
                SET is_active = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtArchive->execute([$operationId]);
            $updated = true;
        } elseif (columnExists($pdo, 'operations', 'is_deleted')) {
            $stmtArchive = $pdo->prepare("
                UPDATE operations
                SET is_deleted = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtArchive->execute([$operationId]);
            $updated = true;
        } elseif (columnExists($pdo, 'operations', 'deleted_at')) {
            $stmtArchive = $pdo->prepare("
                UPDATE operations
                SET deleted_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtArchive->execute([$operationId]);
            $updated = true;
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM operations WHERE id = ?");
            $stmtDelete->execute([$operationId]);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'delete_operation_fallback',
                    'operations',
                    'operation',
                    $operationId,
                    'Suppression fallback de l’opération #' . $operationId
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'operation_delete',
                    'Opération supprimée : ' . ((string)($before['label'] ?? ('#' . $operationId))),
                    'warning',
                    APP_URL . 'modules/operations/operations_list.php',
                    'operation',
                    $operationId,
                    (int)$_SESSION['user_id']
                );
            }

            $pdo->commit();
            header('Location: ' . APP_URL . 'modules/operations/operations_list.php?deleted=1');
            exit;
        }

        if ($updated) {
            if (function_exists('auditEntityChanges') && isset($_SESSION['user_id'])) {
                $stmtAfter = $pdo->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
                $stmtAfter->execute([$operationId]);
                $after = $stmtAfter->fetch(PDO::FETCH_ASSOC) ?: [];

                auditEntityChanges($pdo, 'operation', $operationId, $before, $after, (int)$_SESSION['user_id']);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'archive_operation',
                    'operations',
                    'operation',
                    $operationId,
                    'Archivage de l’opération #' . $operationId
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'operation_archive',
                    'Opération archivée : ' . ((string)($before['label'] ?? ('#' . $operationId))),
                    'info',
                    APP_URL . 'modules/operations/operations_list.php',
                    'operation',
                    $operationId,
                    (int)$_SESSION['user_id']
                );
            }
        }

        $pdo->commit();
        header('Location: ' . APP_URL . 'modules/operations/operations_list.php?archived=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Supprimer une opération';
$pageSubtitle = 'Archivage ou suppression définitive selon la structure disponible';

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
                    <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$operationId ?></strong></div>
                    <div class="sl-data-list__row"><span>Date</span><strong><?= e((string)($operation['operation_date'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($operation['label'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Montant</span><strong><?= e(number_format((float)($operation['amount'] ?? 0), 2, ',', ' ')) ?> <?= e((string)($operation['currency_code'] ?? 'EUR')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte débité</span><strong><?= e((string)($operation['debit_account_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte crédité</span><strong><?= e((string)($operation['credit_account_code'] ?? '')) ?></strong></div>
                </div>

                <form method="POST" style="margin-top:20px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$operationId ?>">

                    <div style="display:grid; gap:12px;">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="delete_mode" value="soft" checked>
                            Archiver l’opération
                        </label>

                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="delete_mode" value="hard">
                            Supprimer définitivement
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-danger">Confirmer</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operation_view.php?id=<?= (int)$operationId ?>" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Attention</h3>
                <div class="dashboard-note">
                    L’archivage est à privilégier lorsqu’on veut conserver l’historique comptable et les références liées.  
                    La suppression définitive ne doit être utilisée qu’en cas d’erreur manifeste.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>