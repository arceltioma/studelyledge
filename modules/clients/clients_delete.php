<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_delete');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM clients
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$bankAccount = findPrimaryBankAccountForClient($pdo, $id);

$linkedOperationsCount = 0;
if (tableExists($pdo, 'operations') && columnExists($pdo, 'operations', 'client_id')) {
    $stmtOps = $pdo->prepare("
        SELECT COUNT(*)
        FROM operations
        WHERE client_id = ?
    ");
    $stmtOps->execute([$id]);
    $linkedOperationsCount = (int)$stmtOps->fetchColumn();
}

$hasBlockingLinks = $linkedOperationsCount > 0;

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($hasBlockingLinks) {
            throw new RuntimeException('Suppression impossible : ce client possède déjà des opérations liées. Archive-le à la place.');
        }

        $pdo->beginTransaction();

        if (tableExists($pdo, 'client_bank_accounts')) {
            $stmtDeleteLink = $pdo->prepare("
                DELETE FROM client_bank_accounts
                WHERE client_id = ?
            ");
            $stmtDeleteLink->execute([$id]);
        }

        if ($bankAccount && tableExists($pdo, 'bank_accounts')) {
            $stmtDeleteBank = $pdo->prepare("
                DELETE FROM bank_accounts
                WHERE id = ?
            ");
            $stmtDeleteBank->execute([(int)$bankAccount['id']]);
        }

        $stmtDeleteClient = $pdo->prepare("
            DELETE FROM clients
            WHERE id = ?
        ");
        $stmtDeleteClient->execute([$id]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'delete_client',
                'clients',
                'client',
                $id,
                'Suppression physique du client ' . ($client['client_code'] ?? '')
            );
        }

        $pdo->commit();

        header('Location: ' . APP_URL . 'modules/clients/clients_list.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Supprimer un client';
$pageSubtitle = 'Suppression physique réservée aux clients sans opérations liées.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php render_app_header_bar($pageTitle, $pageSubtitle); ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Contrôle avant suppression</h3>

                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="detail-label">Code client</span>
                        <span class="detail-value"><?= e($client['client_code'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Nom</span>
                        <span class="detail-value"><?= e($client['full_name'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Compte 411</span>
                        <span class="detail-value"><?= e($client['generated_client_account'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Solde courant</span>
                        <span class="detail-value"><?= number_format((float)($bankAccount['balance'] ?? 0), 2, ',', ' ') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Opérations liées</span>
                        <span class="detail-value"><?= (int)$linkedOperationsCount ?></span>
                    </div>
                </div>

                <?php if ($hasBlockingLinks): ?>
                    <div class="warning" style="margin-top:20px;">
                        Ce client ne peut pas être supprimé physiquement car des opérations lui sont déjà rattachées.
                        Utilise l’archivage / désactivation à la place.
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_archive.php?id=<?= (int)$id ?>&action=archive" class="btn btn-secondary">
                            Archiver à la place
                        </a>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">
                            Retour
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" style="margin-top:20px;">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">

                        <div class="btn-group">
                            <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                            <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Annuler</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Règle de gestion</h3>
                <div class="dashboard-note">
                    La suppression physique est réservée aux clients sans historique opérationnel.
                    Dès qu’un client possède des opérations, il doit être archivé et non supprimé.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>