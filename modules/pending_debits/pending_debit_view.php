<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$pendingDebitId = (int)($_GET['id'] ?? 0);
if ($pendingDebitId <= 0) {
    exit('Débit dû invalide.');
}

if (!tableExists($pdo, 'pending_client_debits')) {
    exit('Table pending_client_debits introuvable.');
}

$select = [
    'pd.*'
];

$joinClient = '';
if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
    $joinClient = 'LEFT JOIN clients c ON c.id = pd.client_id';
    $select[] = 'c.client_code';
    $select[] = 'c.full_name';
    $select[] = 'c.generated_client_account';
    $select[] = 'c.currency AS client_currency';
}

$sql = "
    SELECT " . implode(', ', $select) . "
    FROM pending_client_debits pd
    {$joinClient}
    WHERE pd.id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pendingDebitId]);
$pendingDebit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pendingDebit) {
    exit('Débit dû introuvable.');
}

$logs = [];
if (tableExists($pdo, 'pending_client_debit_logs')) {
    $stmtLogs = $pdo->prepare("
        SELECT *
        FROM pending_client_debit_logs
        WHERE pending_debit_id = ?
        ORDER BY id DESC
    ");
    $stmtLogs->execute([$pendingDebitId]);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$pageTitle = 'Voir un débit dû';
$pageSubtitle = 'Consultation détaillée du débit dû client 411';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php" class="btn btn-outline">Retour</a>

                <?php if (in_array((string)($pendingDebit['status'] ?? ''), ['pending', 'partial', 'ready'], true)): ?>
                    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_execute.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-success">
                        Initier le débit
                    </a>
                <?php endif; ?>

                <?php if (in_array((string)($pendingDebit['status'] ?? ''), ['pending', 'partial', 'ready'], true)): ?>
                    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_cancel.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-danger">
                        Annuler
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Informations générales</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$pendingDebitId ?></strong></div>
                    <div class="sl-data-list__row"><span>Client</span><strong><?= e(trim((string)($pendingDebit['client_code'] ?? '') . ' - ' . (string)($pendingDebit['full_name'] ?? '')) ?: '—') ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e((string)($pendingDebit['generated_client_account'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Déclencheur</span><strong><?= e((string)($pendingDebit['trigger_type'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($pendingDebit['label'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Statut</span><strong><?= e((string)($pendingDebit['status'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Priorité</span><strong><?= e((string)($pendingDebit['priority_level'] ?? '—')) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Montants</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Montant initial</span><strong><?= e(number_format((float)($pendingDebit['initial_amount'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Déjà exécuté</span><strong><?= e(number_format((float)($pendingDebit['executed_amount'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Restant dû</span><strong><?= e(number_format((float)($pendingDebit['remaining_amount'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Devise</span><strong><?= e((string)($pendingDebit['currency_code'] ?? $pendingDebit['client_currency'] ?? 'EUR')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Dernière notification</span><strong><?= e((string)($pendingDebit['last_notification_at'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Résolu le</span><strong><?= e((string)($pendingDebit['resolved_at'] ?? '—')) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Notes</h3>
            <div class="dashboard-note">
                <?= nl2br(e((string)($pendingDebit['notes'] ?? '—'))) ?>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Historique</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Ancien statut</th>
                            <th>Nouveau statut</th>
                            <th>Montant</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e((string)($log['created_at'] ?? '')) ?></td>
                                <td><?= e((string)($log['action_type'] ?? '')) ?></td>
                                <td><?= e((string)($log['old_status'] ?? '')) ?></td>
                                <td><?= e((string)($log['new_status'] ?? '')) ?></td>
                                <td><?= e(number_format((float)($log['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e((string)($log['message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$logs): ?>
                            <tr>
                                <td colspan="6">Aucun historique.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>