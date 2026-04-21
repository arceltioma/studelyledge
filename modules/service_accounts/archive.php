<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'service_accounts_archive');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de service invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM service_accounts
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de service introuvable.');
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $before = $account;

        $fields = [];
        $params = [];

        if (columnExists($pdo, 'service_accounts', 'is_active')) {
            $fields[] = 'is_active = 0';
        }

        if (columnExists($pdo, 'service_accounts', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        if (!$fields) {
            throw new RuntimeException('Aucun champ d’archivage disponible.');
        }

        $params[] = $id;

        $stmtUpdate = $pdo->prepare("
            UPDATE service_accounts
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmtUpdate->execute($params);

        $stmt = $pdo->prepare("
            SELECT *
            FROM service_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: $account;

        if (function_exists('auditEntityChanges') && isset($_SESSION['user_id'])) {
            auditEntityChanges($pdo, 'service_account', $id, $before, $account, (int)$_SESSION['user_id']);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'archive_service_account',
                'service_accounts',
                'service_account',
                $id,
                'Archivage d’un compte de service 706'
            );
        }

        if (function_exists('sl_create_entity_notification') && isset($_SESSION['user_id'])) {
            sl_create_entity_notification(
                $pdo,
                'service_account_archive',
                'Compte de service archivé : ' . (string)($account['account_code'] ?? ''),
                'warning',
                'service_account',
                $id,
                (int)$_SESSION['user_id']
            );
        }

        $_SESSION['success_message'] = 'Compte de service archivé avec succès.';
        header('Location: ' . APP_URL . 'modules/service_accounts/index.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Archiver un compte de service (706)';
$pageSubtitle = 'Confirmation d’archivage du compte de service';
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
                <h3>Confirmation d’archivage</h3>

                <div class="dashboard-note" style="margin-bottom:16px;">
                    Tu es sur le point d’archiver ce compte de service. Il restera consultable, mais sera considéré comme inactif.
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Code</span>
                        <strong><?= e((string)($account['account_code'] ?? '')) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Intitulé</span>
                        <strong><?= e((string)($account['account_label'] ?? '')) ?></strong>
                    </div>
                    <?php if (array_key_exists('commercial_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays commercial</span>
                            <strong><?= e((string)($account['commercial_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if (array_key_exists('destination_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays destination</span>
                            <strong><?= e((string)($account['destination_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" style="margin-top:20px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger">Confirmer l’archivage</button>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/view.php?id=<?= (int)$id ?>" class="btn btn-secondary">Annuler</a>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>État actuel</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Statut</span>
                        <strong><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong>
                    </div>

                    <?php if (array_key_exists('is_postable', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Postable</span>
                            <strong><?= ((int)($account['is_postable'] ?? 0) === 1) ? 'Oui' : 'Non' ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('current_balance', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Solde courant</span>
                            <strong><?= e(number_format((float)($account['current_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('updated_at', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Dernière mise à jour</span>
                            <strong><?= e((string)($account['updated_at'] ?? '')) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>