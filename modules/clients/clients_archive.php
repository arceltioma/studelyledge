<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_archive_page');
} else {
    enforcePagePermission($pdo, 'clients_archive');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($id <= 0) {
    exit('Client invalide.');
}

if (!in_array($action, ['archive', 'restore'], true)) {
    exit('Action invalide.');
}

$stmt = $pdo->prepare("
    SELECT
        c.*,
        ba.id AS bank_account_id,
        ba.account_number,
        ba.account_name,
        ba.initial_balance,
        ba.balance AS current_balance_411
    FROM clients c
    LEFT JOIN bank_accounts ba
        ON ba.account_number = c.generated_client_account
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$errorMessage = '';
$successMessage = '';

$accounts512 = function_exists('sl_get_available_treasury_accounts')
    ? sl_get_available_treasury_accounts($pdo)
    : [];

$default512 = function_exists('sl_find_default_archive_treasury_account')
    ? sl_find_default_archive_treasury_account($pdo)
    : null;

$currentBalance411 = (float)($client['current_balance_411'] ?? 0);
$selectedTreasuryId = (int)($_POST['treasury_account_id'] ?? $_GET['treasury_account_id'] ?? ($default512['id'] ?? 0));
$restoreBalance = (int)($_POST['restore_balance'] ?? 1) === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $pdo->beginTransaction();

        if ($action === 'archive') {
            if ((int)($client['is_active'] ?? 1) !== 1) {
                throw new RuntimeException('Ce client est déjà archivé.');
            }

            if ($currentBalance411 > 0) {
                if ($selectedTreasuryId <= 0) {
                    throw new RuntimeException('Veuillez sélectionner un compte 512 de destination.');
                }

                if (!function_exists('sl_archive_client_balance_to_treasury')) {
                    throw new RuntimeException('Helper sl_archive_client_balance_to_treasury introuvable.');
                }

                sl_archive_client_balance_to_treasury(
                    $pdo,
                    $client,
                    $selectedTreasuryId,
                    (int)($_SESSION['user_id'] ?? 0)
                );
            }

            $stmtArchive = $pdo->prepare("
                UPDATE clients
                SET is_active = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtArchive->execute([$id]);

            if (function_exists('sl_rebuild_client_balance')) {
                sl_rebuild_client_balance($pdo, $id);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'archive_client',
                    'clients',
                    'client',
                    $id,
                    'Archivage client ' . (string)($client['client_code'] ?? $id)
                );
            }
        }

        if ($action === 'restore') {
            if ((int)($client['is_active'] ?? 1) === 1) {
                throw new RuntimeException('Ce client est déjà actif.');
            }

            if ($restoreBalance) {
                if (!function_exists('sl_restore_client_balance_from_archive')) {
                    throw new RuntimeException('Helper sl_restore_client_balance_from_archive introuvable.');
                }

                sl_restore_client_balance_from_archive(
                    $pdo,
                    $client,
                    (int)($_SESSION['user_id'] ?? 0)
                );
            }

            $stmtRestore = $pdo->prepare("
                UPDATE clients
                SET is_active = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtRestore->execute([$id]);

            if (function_exists('sl_rebuild_client_balance')) {
                sl_rebuild_client_balance($pdo, $id);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    $restoreBalance ? 'restore_client_with_balance' : 'restore_client_without_balance',
                    'clients',
                    'client',
                    $id,
                    $restoreBalance
                        ? 'Réactivation client avec restitution du solde'
                        : 'Réactivation client sans restitution du solde'
                );
            }
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

$pageTitle = $action === 'archive' ? 'Archiver un client' : 'Réactiver un client';
$pageSubtitle = $action === 'archive'
    ? 'Le client sera désactivé et son solde 411 sera transféré si nécessaire.'
    : 'Le client redeviendra actif et son ancien solde pourra être restauré si nécessaire.';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Code client</div>
                <div class="sl-kpi-card__value"><?= e((string)($client['client_code'] ?? '')) ?></div>
                <div class="sl-kpi-card__meta"><span>Référence</span><strong>Client</strong></div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Compte 411</div>
                <div class="sl-kpi-card__value"><?= e((string)($client['generated_client_account'] ?? '')) ?></div>
                <div class="sl-kpi-card__meta"><span>Compte client</span><strong>411</strong></div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Solde actuel</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)($client['current_balance_411'] ?? 0), 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__meta"><span>Avant action</span><strong>411</strong></div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Statut</div>
                <div class="sl-kpi-card__value"><?= (int)($client['is_active'] ?? 1) === 1 ? 'Actif' : 'Archivé' ?></div>
                <div class="sl-kpi-card__meta"><span>État client</span><strong>Gestion</strong></div>
            </div>
        </section>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">
                    <?= $action === 'archive' ? 'Confirmation d’archivage' : 'Confirmation de réactivation' ?>
                </h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Nom</span>
                        <strong><?= e((string)($client['full_name'] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Libellé compte</span>
                        <strong><?= e((string)($client['account_name'] ?? '—')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Solde initial</span>
                        <strong><?= e(number_format((float)($client['initial_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Solde courant 411</span>
                        <strong><?= e(number_format((float)($client['current_balance_411'] ?? 0), 2, ',', ' ')) ?></strong>
                    </div>
                </div>

                <form method="POST" style="margin-top:20px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <input type="hidden" name="action" value="<?= e($action) ?>">

                    <?php if ($action === 'archive'): ?>
                        <?php if ($currentBalance411 > 0): ?>
                            <div style="margin-bottom:18px;">
                                <label>Compte 512 de destination</label>
                                <select name="treasury_account_id" required>
                                    <option value="">Sélectionner un compte 512</option>
                                    <?php foreach ($accounts512 as $account): ?>
                                        <option value="<?= (int)$account['id'] ?>" <?= $selectedTreasuryId === (int)$account['id'] ? 'selected' : '' ?>>
                                            <?= e((string)($account['account_code'] ?? '') . ' - ' . (string)($account['account_label'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="dashboard-note" style="margin-bottom:18px;">
                                Une opération sera générée avec :
                                <strong>Débit 512 / Crédit 411</strong>.
                                Le compte 411 passera à 0 et le compte 512 sélectionné sera augmenté du même montant.
                            </div>
                        <?php else: ?>
                            <div class="dashboard-note" style="margin-bottom:18px;">
                                Le compte 411 est déjà à zéro. L’archivage sera effectué sans transfert.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($action === 'restore'): ?>
                        <div style="margin-bottom:18px;">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" name="restore_balance" value="1" <?= $restoreBalance ? 'checked' : '' ?>>
                                Restaurer le solde depuis le compte 512 vers le compte 411
                            </label>
                        </div>

                        <div class="dashboard-note" style="margin-bottom:18px;">
                            Si cette case est cochée, une opération sera générée avec :
                            <strong>Débit 411 / Crédit 512</strong>.
                            Le compte 411 sera recrédité et le 512 correspondant sera diminué du même montant.
                        </div>
                    <?php endif; ?>

                    <div class="btn-group">
                        <?php if ($action === 'archive'): ?>
                            <button type="submit" class="btn btn-danger">Confirmer l’archivage</button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success">Confirmer la réactivation</button>
                        <?php endif; ?>

                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Effet attendu</h3>

                <div class="sl-data-list">
                    <?php if ($action === 'archive'): ?>
                        <div class="sl-data-list__row">
                            <span>Client</span>
                            <strong>Sera passé en archivé</strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Historique</span>
                            <strong>Une opération ARCHIVE_CLIENT sera créée</strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Compte 411</span>
                            <strong><?= $currentBalance411 > 0 ? 'Sera soldé à 0' : 'Restera à 0' ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Compte 512</span>
                            <strong><?= $currentBalance411 > 0 ? 'Sera augmenté' : 'Sans mouvement' ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="sl-data-list__row">
                            <span>Client</span>
                            <strong>Sera réactivé</strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Historique</span>
                            <strong><?= $restoreBalance ? 'Une opération RESTORE_CLIENT sera créée' : 'Pas de restitution comptable' ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Compte 411</span>
                            <strong><?= $restoreBalance ? 'Sera recrédité' : 'Restera inchangé' ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Compte 512</span>
                            <strong><?= $restoreBalance ? 'Sera diminué' : 'Restera inchangé' ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>