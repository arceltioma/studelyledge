<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/pending_debits_engine.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'pending_debits_execute');
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

if (!function_exists('sl_pending_execute_badge_class')) {
    function sl_pending_execute_badge_class(string $status): string
    {
        $status = strtolower(trim($status));

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

$pendingDebitId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($pendingDebitId <= 0) {
    exit('Débit dû invalide.');
}

if (!tableExists($pdo, 'pending_client_debits')) {
    exit('Table pending_client_debits introuvable.');
}

$select = ['pd.*'];
$joinClient = '';

if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
    $joinClient = 'LEFT JOIN clients c ON c.id = pd.client_id';
    $select[] = 'c.client_code';
    $select[] = 'c.full_name';
    $select[] = 'c.generated_client_account';
    $select[] = 'c.currency AS client_currency';
    $select[] = 'COALESCE(c.is_active,1) AS client_is_active';
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

$status = (string)($pendingDebit['status'] ?? '');
if (in_array($status, ['resolved', 'cancelled'], true)) {
    exit('Ce débit dû ne peut plus être exécuté.');
}

$errorMessage = '';
$successMessage = '';

$remainingAmount = (float)($pendingDebit['remaining_amount'] ?? 0);
$executedAmount = (float)($pendingDebit['executed_amount'] ?? 0);
$initialAmount = (float)($pendingDebit['initial_amount'] ?? 0);
$currencyCode = (string)($pendingDebit['currency_code'] ?? $pendingDebit['client_currency'] ?? 'EUR');

$formData = [
    'execution_amount' => (string)number_format($remainingAmount, 2, '.', ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'execution_amount' => trim((string)($_POST['execution_amount'] ?? '')),
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $clientId = (int)($pendingDebit['client_id'] ?? 0);
        if ($clientId > 0) {
            sl_assert_client_operation_allowed($pdo, $clientId);
        }

        if (isset($pendingDebit['client_is_active']) && (int)$pendingDebit['client_is_active'] !== 1) {
            throw new RuntimeException('Le client lié à ce débit dû est archivé ou inactif.');
        }

        $requestedAmount = (float)str_replace(',', '.', $formData['execution_amount']);
        if ($requestedAmount <= 0) {
            throw new RuntimeException('Le montant à initier doit être supérieur à 0.');
        }

        $remainingBefore = (float)($pendingDebit['remaining_amount'] ?? 0);
        if ($requestedAmount > $remainingBefore) {
            throw new RuntimeException('Le montant demandé dépasse le restant dû.');
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        $result = sl_execute_pending_client_debit(
            $pdo,
            $pendingDebitId,
            $requestedAmount,
            $userId > 0 ? $userId : null
        );

        $executedAmountResult = (float)($result['executed_amount'] ?? 0);
        $remainingAfter = (float)($result['remaining_amount'] ?? max(0, $remainingBefore - $executedAmountResult));
        $newStatus = (string)($result['status'] ?? ($remainingAfter <= 0 ? 'resolved' : ($executedAmountResult > 0 ? 'partial' : $status)));
        $operationId = (int)($result['operation_id'] ?? 0);

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'execute_pending_debit',
                'pending_debits',
                'pending_client_debit',
                $pendingDebitId,
                'Exécution manuelle d’un débit dû #'
                . $pendingDebitId
                . ' | montant demandé: ' . number_format($requestedAmount, 2, '.', '')
                . ' | montant exécuté: ' . number_format($executedAmountResult, 2, '.', '')
                . ' | restant après exécution: ' . number_format($remainingAfter, 2, '.', '')
                . ($operationId > 0 ? ' | opération créée: #' . $operationId : '')
            );
        }

        $clientCode = (string)($pendingDebit['client_code'] ?? '');
        $clientName = (string)($pendingDebit['full_name'] ?? '');
        $label = (string)($pendingDebit['label'] ?? 'Débit dû');

        if ($executedAmountResult > 0) {
            sl_create_notification_if_possible(
                $pdo,
                $newStatus === 'resolved' ? 'pending_debit_resolved' : 'pending_debit_executed',
                'Débit dû exécuté'
                . ($clientCode !== '' ? ' pour le client ' . $clientCode : '')
                . ($clientName !== '' ? ' - ' . $clientName : '')
                . ' | exécuté : ' . number_format($executedAmountResult, 2, ',', ' ')
                . ' ' . $currencyCode
                . ' | restant : ' . number_format($remainingAfter, 2, ',', ' ')
                . ' ' . $currencyCode
                . ($label !== '' ? ' | ' . $label : ''),
                $newStatus === 'resolved' ? 'success' : 'info',
                APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId,
                'pending_client_debit',
                $pendingDebitId,
                $userId > 0 ? $userId : null
            );
        } else {
            sl_create_notification_if_possible(
                $pdo,
                'pending_debit_execution_failed',
                'Aucune exécution possible pour le débit dû'
                . ($clientCode !== '' ? ' du client ' . $clientCode : '')
                . ($clientName !== '' ? ' - ' . $clientName : '')
                . ' | demandé : ' . number_format($requestedAmount, 2, ',', ' ')
                . ' ' . $currencyCode
                . ' | restant : ' . number_format($remainingBefore, 2, ',', ' ')
                . ' ' . $currencyCode,
                'warning',
                APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId,
                'pending_client_debit',
                $pendingDebitId,
                $userId > 0 ? $userId : null
            );
        }

        $_SESSION['success_message'] = $executedAmountResult > 0
            ? (
                $newStatus === 'resolved'
                    ? 'Le débit dû a été entièrement exécuté.'
                    : 'Le débit dû a été exécuté partiellement avec succès.'
            )
            : 'Aucun montant n’a pu être exécuté pour ce débit dû.';

        header('Location: ' . APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId . '&executed=1');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Initier un débit dû';
$pageSubtitle = 'Exécution manuelle d’un débit dû client 411';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .spde-shell {
                display: grid;
                gap: 20px;
            }

            .spde-toolbar,
            .spde-panel,
            .spde-kpi-card {
                border-radius: 20px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            }

            .spde-toolbar,
            .spde-panel {
                padding: 18px;
            }

            .spde-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
            }

            .spde-toolbar__titles h2 {
                margin: 0 0 6px;
                font-size: 24px;
                font-weight: 900;
                color: #0f172a;
            }

            .spde-toolbar__titles p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
            }

            .spde-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: center;
            }

            .spde-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 1100px) {
                .spde-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .spde-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .spde-kpi-card {
                padding: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .spde-kpi-card__label {
                display: block;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .spde-kpi-card__value {
                display: block;
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }

            .spde-kpi-card__meta {
                display: block;
                margin-top: 8px;
                font-size: 12px;
                color: #64748b;
            }

            .spde-panel-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 14px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }

            .spde-panel-head h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 900;
                color: #0f172a;
            }

            .spde-panel-head p {
                margin: 4px 0 0;
                font-size: 13px;
                color: #64748b;
            }

            .spde-note-box {
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.9);
                border: 1px dashed rgba(148, 163, 184, 0.35);
                color: #475569;
                line-height: 1.6;
            }

            .spde-highlight-box {
                padding: 14px 16px;
                border-radius: 16px;
                background: rgba(245, 158, 11, 0.08);
                border: 1px solid rgba(245, 158, 11, 0.20);
                color: #92400e;
                font-size: 14px;
                line-height: 1.55;
            }

            .spde-badge {
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

            .spde-form-help {
                margin-top: 8px;
                color: #64748b;
                font-size: 12px;
                line-height: 1.5;
            }
        </style>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="spde-shell">
            <section class="spde-toolbar">
                <div class="spde-toolbar__titles">
                    <h2>Initier le débit dû #<?= (int)$pendingDebitId ?></h2>
                    <p>Exécution manuelle pilotée d’un reliquat client 411, sans altérer la logique existante.</p>
                </div>

<div class="spde-actions">
    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-outline">Retour au détail</a>
    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_edit.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-outline">Modifier</a>
    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php" class="btn btn-outline">Liste</a>
</div>
            </section>

            <section class="spde-kpi-grid">
                <div class="spde-kpi-card">
                    <span class="spde-kpi-card__label">Montant initial</span>
                    <span class="spde-kpi-card__value"><?= e(number_format($initialAmount, 2, ',', ' ')) ?></span>
                    <span class="spde-kpi-card__meta"><?= e($currencyCode) ?></span>
                </div>

                <div class="spde-kpi-card">
                    <span class="spde-kpi-card__label">Déjà exécuté</span>
                    <span class="spde-kpi-card__value"><?= e(number_format($executedAmount, 2, ',', ' ')) ?></span>
                    <span class="spde-kpi-card__meta">Montant traité</span>
                </div>

                <div class="spde-kpi-card">
                    <span class="spde-kpi-card__label">Restant dû</span>
                    <span class="spde-kpi-card__value"><?= e(number_format($remainingAmount, 2, ',', ' ')) ?></span>
                    <span class="spde-kpi-card__meta">Montant ouvert</span>
                </div>

                <div class="spde-kpi-card">
                    <span class="spde-kpi-card__label">Statut actuel</span>
                    <span class="spde-kpi-card__value" style="font-size:20px; line-height:1.2;">
                        <span class="spde-badge <?= e(sl_pending_execute_badge_class((string)$pendingDebit['status'])) ?>">
                            <?= e((string)($pendingDebit['status'] ?? '—')) ?>
                        </span>
                    </span>
                    <span class="spde-kpi-card__meta">Priorité : <?= e((string)($pendingDebit['priority_level'] ?? '—')) ?></span>
                </div>
            </section>

            <div class="dashboard-grid-2">
                <section class="spde-panel">
                    <div class="spde-panel-head">
                        <div>
                            <h3>Exécuter le débit dû</h3>
                            <p>Le montant saisi sera initié dans la limite du solde exécutable sur le compte 411 client.</p>
                        </div>
                    </div>

                    <form method="POST">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= (int)$pendingDebitId ?>">

                        <div class="dashboard-grid-2">
                            <div>
                                <label>Client</label>
                                <input
                                    type="text"
                                    value="<?= e(trim((string)($pendingDebit['client_code'] ?? '') . ' - ' . (string)($pendingDebit['full_name'] ?? '')) ?: '—') ?>"
                                    disabled
                                >
                            </div>

                            <div>
                                <label>Compte 411</label>
                                <input
                                    type="text"
                                    value="<?= e((string)($pendingDebit['generated_client_account'] ?? '—')) ?>"
                                    disabled
                                >
                            </div>

                            <div>
                                <label>Montant restant dû</label>
                                <input
                                    type="text"
                                    value="<?= e(number_format($remainingAmount, 2, ',', ' ')) ?>"
                                    disabled
                                >
                            </div>

                            <div>
                                <label>Devise</label>
                                <input
                                    type="text"
                                    value="<?= e($currencyCode) ?>"
                                    disabled
                                >
                            </div>

                            <div style="grid-column:1 / -1;">
                                <label>Montant à initier maintenant</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max="<?= e(number_format($remainingAmount, 2, '.', '')) ?>"
                                    name="execution_amount"
                                    value="<?= e($formData['execution_amount']) ?>"
                                    required
                                >
                                <div class="spde-form-help">
                                    Tu peux initier un montant partiel ou total, dans la limite de <strong><?= e(number_format($remainingAmount, 2, ',', ' ')) ?> <?= e($currencyCode) ?></strong>.
                                </div>
                            </div>
                        </div>

                        <div class="btn-group" style="margin-top:20px;">
                            <button type="submit" class="btn btn-success">Initier maintenant</button>
                            <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-outline">Annuler</a>
                        </div>
                    </form>
                </section>

                <section class="spde-panel">
                    <div class="spde-panel-head">
                        <div>
                            <h3>Rappel</h3>
                            <p>Contexte utile avant exécution</p>
                        </div>
                    </div>

                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Statut actuel</span><strong><?= e((string)($pendingDebit['status'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Déjà exécuté</span><strong><?= e(number_format($executedAmount, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Montant initial</span><strong><?= e(number_format($initialAmount, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Restant dû</span><strong><?= e(number_format($remainingAmount, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($pendingDebit['label'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Déclencheur</span><strong><?= e((string)($pendingDebit['trigger_type'] ?? '—')) ?></strong></div>
                    </div>

                    <div class="spde-highlight-box" style="margin-top:16px;">
                        Si le solde réellement exécutable du compte 411 est insuffisant, seule la partie possible sera initiée.
                        Le moteur conserve la logique existante de traitement partiel ou complet.
                    </div>

                    <div class="spde-note-box" style="margin-top:16px;">
                        <strong>Bon à savoir :</strong><br>
                        l’exécution crée les écritures métier nécessaires via le moteur des débits dus,
                        journalise l’action et déclenche les notifications associées.
                    </div>
                </section>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>