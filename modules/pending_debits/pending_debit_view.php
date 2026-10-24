<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$status = strtolower(trim((string)($pendingDebit['status'] ?? '')));
$canExecute = in_array($status, ['pending', 'partial', 'ready'], true);
$canCancel = in_array($status, ['pending', 'partial', 'ready'], true);

$initialAmount = (float)($pendingDebit['initial_amount'] ?? 0);
$executedAmount = (float)($pendingDebit['executed_amount'] ?? 0);
$remainingAmount = (float)($pendingDebit['remaining_amount'] ?? 0);
$currencyCode = (string)($pendingDebit['currency_code'] ?? $pendingDebit['client_currency'] ?? 'EUR');

$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['success_message'])) {
    $successMessage = (string)$_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (!empty($_SESSION['error_message'])) {
    $errorMessage = (string)$_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (!function_exists('sl_pending_view_badge_class')) {
    function sl_pending_view_badge_class(string $status): string
    {
        if (function_exists('sl_pending_debit_badge_class')) {
            return 'badge-' . sl_pending_debit_badge_class($status);
        }

        return match ($status) {
            'ready' => 'badge-success',
            'partial' => 'badge-warning',
            'pending' => 'badge-secondary',
            'resolved', 'settled' => 'badge-success',
            'cancelled' => 'badge-danger',
            default => 'badge-secondary',
        };
    }
}

$pageTitle = 'Voir un débit dû';
$pageSubtitle = 'Consultation détaillée du débit dû client 411';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .spdv-shell {
                display: grid;
                gap: 20px;
            }

            .spdv-toolbar,
            .spdv-kpi-card,
            .spdv-panel {
                border-radius: 20px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            }

            .spdv-toolbar,
            .spdv-panel {
                padding: 18px;
            }

            .spdv-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
            }

            .spdv-toolbar__titles h2 {
                margin: 0 0 6px;
                font-size: 24px;
                font-weight: 900;
                color: #0f172a;
            }

            .spdv-toolbar__titles p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
            }

            .spdv-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: center;
            }

            .spdv-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 1100px) {
                .spdv-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .spdv-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .spdv-kpi-card {
                padding: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .spdv-kpi-card__label {
                display: block;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .spdv-kpi-card__value {
                display: block;
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }

            .spdv-kpi-card__meta {
                display: block;
                margin-top: 8px;
                font-size: 12px;
                color: #64748b;
            }

            .spdv-panel-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 14px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }

            .spdv-panel-head h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 900;
                color: #0f172a;
            }

            .spdv-panel-head p {
                margin: 4px 0 0;
                font-size: 13px;
                color: #64748b;
            }

            .spdv-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
            }

            .spdv-note-box {
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.9);
                border: 1px dashed rgba(148, 163, 184, 0.35);
                color: #475569;
                line-height: 1.6;
            }

            .spdv-log-table {
                width: 100%;
                border-collapse: collapse;
            }

            .spdv-log-table th,
            .spdv-log-table td {
                padding: 10px 8px;
                border-bottom: 1px solid rgba(226, 232, 240, 0.9);
                font-size: 13px;
                vertical-align: top;
                text-align: left;
            }

            .spdv-log-table th {
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
            }

            .spdv-log-message {
                color: #334155;
                line-height: 1.5;
            }

            .spdv-muted {
                color: #64748b;
                font-size: 13px;
            }
        </style>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="spdv-shell">
            <section class="spdv-toolbar">
                <div class="spdv-toolbar__titles">
                    <h2>Débit dû #<?= (int)$pendingDebitId ?></h2>
                    <p>Vue détaillée, actions métier et historique du reliquat client.</p>
                </div>

<div class="spdv-actions">
    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php" class="btn btn-outline">Retour</a>

    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_edit.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-outline">
        Modifier
    </a>

    <?php if ($canExecute): ?>
                        <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_execute.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-success">
                            Initier le débit
                        </a>
                    <?php endif; ?>

                    <?php if ($canCancel): ?>
                        <form
                            method="POST"
                            action="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_cancel.php"
                            style="display:inline-block; margin:0;"
                            onsubmit="return confirm('Confirmer l’annulation de ce débit dû ?');"
                        >
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int)$pendingDebitId ?>">
                            <button type="submit" class="btn btn-danger">Annuler</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="spdv-kpi-grid">
                <div class="spdv-kpi-card">
                    <span class="spdv-kpi-card__label">Montant initial</span>
                    <span class="spdv-kpi-card__value"><?= e(number_format($initialAmount, 2, ',', ' ')) ?></span>
                    <span class="spdv-kpi-card__meta"><?= e($currencyCode) ?></span>
                </div>

                <div class="spdv-kpi-card">
                    <span class="spdv-kpi-card__label">Déjà exécuté</span>
                    <span class="spdv-kpi-card__value"><?= e(number_format($executedAmount, 2, ',', ' ')) ?></span>
                    <span class="spdv-kpi-card__meta">Montant traité</span>
                </div>

                <div class="spdv-kpi-card">
                    <span class="spdv-kpi-card__label">Restant dû</span>
                    <span class="spdv-kpi-card__value"><?= e(number_format($remainingAmount, 2, ',', ' ')) ?></span>
                    <span class="spdv-kpi-card__meta">Montant encore ouvert</span>
                </div>

                <div class="spdv-kpi-card">
                    <span class="spdv-kpi-card__label">Statut</span>
                    <span class="spdv-kpi-card__value" style="font-size:20px; line-height:1.2;">
                        <span class="spdv-badge <?= e(sl_pending_view_badge_class($status)) ?>">
                            <?= e($status !== '' ? $status : '—') ?>
                        </span>
                    </span>
                    <span class="spdv-kpi-card__meta">Priorité : <?= e((string)($pendingDebit['priority_level'] ?? '—')) ?></span>
                </div>
            </section>

            <div class="dashboard-grid-2">
                <section class="spdv-panel">
                    <div class="spdv-panel-head">
                        <div>
                            <h3>Informations générales</h3>
                            <p>Contexte fonctionnel du débit dû</p>
                        </div>
                    </div>

                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$pendingDebitId ?></strong></div>
                        <div class="sl-data-list__row"><span>Client</span><strong><?= e(trim((string)($pendingDebit['client_code'] ?? '') . ' - ' . (string)($pendingDebit['full_name'] ?? '')) ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e((string)($pendingDebit['generated_client_account'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Déclencheur</span><strong><?= e((string)($pendingDebit['trigger_type'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($pendingDebit['label'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row">
                            <span>Statut</span>
                            <strong>
                                <span class="spdv-badge <?= e(sl_pending_view_badge_class($status)) ?>">
                                    <?= e((string)($pendingDebit['status'] ?? '—')) ?>
                                </span>
                            </strong>
                        </div>
                        <div class="sl-data-list__row"><span>Priorité</span><strong><?= e((string)($pendingDebit['priority_level'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Créé le</span><strong><?= e((string)($pendingDebit['created_at'] ?? '—')) ?></strong></div>
                    </div>
                </section>

                <section class="spdv-panel">
                    <div class="spdv-panel-head">
                        <div>
                            <h3>Montants & suivi</h3>
                            <p>État financier actuel du reliquat</p>
                        </div>
                    </div>

                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Montant initial</span><strong><?= e(number_format($initialAmount, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Déjà exécuté</span><strong><?= e(number_format($executedAmount, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Restant dû</span><strong><?= e(number_format($remainingAmount, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Devise</span><strong><?= e($currencyCode) ?></strong></div>
                        <div class="sl-data-list__row"><span>Dernière notification</span><strong><?= e((string)($pendingDebit['last_notification_at'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Résolu le</span><strong><?= e((string)($pendingDebit['resolved_at'] ?? '—')) ?></strong></div>
                    </div>
                </section>
            </div>

            <section class="spdv-panel">
                <div class="spdv-panel-head">
                    <div>
                        <h3>Notes</h3>
                        <p>Commentaires et contexte libre</p>
                    </div>
                </div>

                <div class="spdv-note-box">
                    <?= nl2br(e((string)($pendingDebit['notes'] ?? '—'))) ?>
                </div>
            </section>

            <section class="spdv-panel">
                <div class="spdv-panel-head">
                    <div>
                        <h3>Historique</h3>
                        <p>Journal des changements de statut et actions métier</p>
                    </div>
                </div>

                <?php if ($logs): ?>
                    <div class="table-responsive">
                        <table class="spdv-log-table">
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
                                        <td class="spdv-log-message"><?= e((string)($log['message'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="spdv-muted">Aucun historique.</div>
                <?php endif; ?>
            </section>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>