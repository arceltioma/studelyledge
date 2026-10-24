<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'pending_debits_edit');
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

if (!function_exists('sl_pending_edit_badge_class')) {
    function sl_pending_edit_badge_class(string $status): string
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

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Débit dû invalide.');
}

$select = ['pd.*'];
$joinClient = '';

if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
    $joinClient = 'LEFT JOIN clients c ON c.id = pd.client_id';

    if (columnExists($pdo, 'clients', 'client_code')) {
        $select[] = 'c.client_code AS client_code';
    }
    if (columnExists($pdo, 'clients', 'full_name')) {
        $select[] = 'c.full_name AS full_name';
    }
    if (columnExists($pdo, 'clients', 'generated_client_account')) {
        $select[] = 'c.generated_client_account AS generated_client_account';
    }
    if (columnExists($pdo, 'clients', 'currency')) {
        $select[] = 'c.currency AS client_currency';
    }
}

$stmt = $pdo->prepare("
    SELECT " . implode(', ', $select) . "
    FROM pending_client_debits pd
    {$joinClient}
    WHERE pd.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    exit('Débit dû introuvable.');
}

$error = '';
$successMessage = '';

$currentStatus = (string)($item['status'] ?? 'pending');
$initialAmount = (float)($item['initial_amount'] ?? 0);
$executedAmount = (float)($item['executed_amount'] ?? 0);
$remainingAmount = (float)($item['remaining_amount'] ?? 0);
$currencyCode = (string)($item['currency_code'] ?? $item['client_currency'] ?? 'EUR');
$clientCode = (string)($item['client_code'] ?? '');
$clientName = (string)($item['full_name'] ?? '');
$clientAccount = (string)($item['generated_client_account'] ?? '—');
$priorityLevelCurrent = (string)($item['priority_level'] ?? 'normal');
$labelCurrent = (string)($item['label'] ?? '—');
$notesCurrent = (string)($item['notes'] ?? '');

$formData = [
    'remaining_amount' => (string)number_format($remainingAmount, 2, '.', ''),
    'status' => $currentStatus !== '' ? $currentStatus : 'pending',
    'priority_level' => $priorityLevelCurrent !== '' ? $priorityLevelCurrent : 'normal',
    'notes' => $notesCurrent,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'remaining_amount' => trim((string)($_POST['remaining_amount'] ?? '0')),
        'status' => trim((string)($_POST['status'] ?? 'pending')),
        'priority_level' => trim((string)($_POST['priority_level'] ?? 'normal')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $remainingAmountPosted = (float)str_replace(',', '.', $formData['remaining_amount']);
        $priorityLevel = $formData['priority_level'];
        $notes = $formData['notes'];
        $status = $formData['status'];

        $allowedStatuses = ['pending', 'partial', 'ready', 'resolved', 'cancelled'];
        $allowedPriorities = ['normal', 'high'];

        if ($remainingAmountPosted < 0) {
            throw new RuntimeException('Le montant restant dû ne peut pas être négatif.');
        }

        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Statut invalide.');
        }

        if (!in_array($priorityLevel, $allowedPriorities, true)) {
            throw new RuntimeException('Niveau de priorité invalide.');
        }

        $oldStatus = (string)($item['status'] ?? 'pending');
        $initialAmountLocal = (float)($item['initial_amount'] ?? 0);
        $oldRemaining = (float)($item['remaining_amount'] ?? 0);
        $oldExecuted = (float)($item['executed_amount'] ?? 0);
        $label = (string)($item['label'] ?? 'Débit dû');
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $newExecuted = max(0, $initialAmountLocal - $remainingAmountPosted);
        $newStatus = $remainingAmountPosted <= 0 ? 'resolved' : $status;
        $resolvedAtSql = $remainingAmountPosted <= 0 ? 'NOW()' : 'NULL';

        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("
            UPDATE pending_client_debits
            SET executed_amount = ?,
                remaining_amount = ?,
                priority_level = ?,
                notes = ?,
                status = ?,
                resolved_at = {$resolvedAtSql},
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $newExecuted,
            $remainingAmountPosted,
            $priorityLevel,
            $notes,
            $newStatus,
            $id
        ]);

        if (function_exists('sl_create_pending_client_debit_log')) {
            sl_create_pending_client_debit_log(
                $pdo,
                $id,
                'edit',
                $oldStatus,
                $newStatus,
                $remainingAmountPosted,
                'Modification manuelle du débit dû',
                $userId
            );
        }

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'edit_pending_debit',
                'pending_debits',
                'pending_client_debit',
                $id,
                'Modification du débit dû #' . $id
                . ($clientCode !== '' ? ' | client: ' . $clientCode : '')
                . ' | ancien statut: ' . $oldStatus
                . ' | nouveau statut: ' . $newStatus
                . ' | ancien restant: ' . number_format($oldRemaining, 2, '.', '')
                . ' | nouveau restant: ' . number_format($remainingAmountPosted, 2, '.', '')
                . ' | ancien exécuté: ' . number_format($oldExecuted, 2, '.', '')
                . ' | nouvel exécuté: ' . number_format($newExecuted, 2, '.', '')
            );
        }

        $notificationMessage = 'Débit dû modifié'
            . ($clientCode !== '' ? ' pour le client ' . $clientCode : '')
            . ($clientName !== '' ? ' - ' . $clientName : '')
            . ' | statut : ' . $newStatus
            . ' | restant : ' . number_format($remainingAmountPosted, 2, ',', ' ')
            . ' | exécuté : ' . number_format($newExecuted, 2, ',', ' ')
            . ($label !== '' ? ' | ' . $label : '');

        sl_create_notification_if_possible(
            $pdo,
            $newStatus === 'resolved' ? 'pending_debit_resolved_manual' : 'pending_debit_updated',
            $notificationMessage,
            $newStatus === 'resolved' ? 'success' : 'info',
            APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id,
            'pending_client_debit',
            $id,
            $userId
        );

        $pdo->commit();

        $_SESSION['success_message'] = $newStatus === 'resolved'
            ? 'Le débit dû a été mis à jour et marqué comme résolu.'
            : 'Le débit dû a été mis à jour avec succès.';

        header('Location: ' . APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$pageTitle = 'Modifier débit dû 411';
$pageSubtitle = 'Ajustement manuel premium';

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

            .spde-highlight-box {
                padding: 14px 16px;
                border-radius: 16px;
                background: rgba(99, 102, 241, 0.08);
                border: 1px solid rgba(99, 102, 241, 0.18);
                color: #3730a3;
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

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <div class="spde-shell">
            <section class="spde-toolbar">
                <div class="spde-toolbar__titles">
                    <h2>Modifier le débit dû #<?= (int)$id ?></h2>
                    <p>Ajustement manuel du reliquat, du statut, de la priorité et des notes.</p>
                </div>

                <div class="spde-actions">
                    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Retour au détail</a>
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
                    <span class="spde-kpi-card__meta">Valeur modifiable</span>
                </div>

                <div class="spde-kpi-card">
                    <span class="spde-kpi-card__label">Statut actuel</span>
                    <span class="spde-kpi-card__value" style="font-size:20px; line-height:1.2;">
                        <span class="spde-badge <?= e(sl_pending_edit_badge_class($currentStatus)) ?>">
                            <?= e($currentStatus !== '' ? $currentStatus : '—') ?>
                        </span>
                    </span>
                    <span class="spde-kpi-card__meta">Priorité : <?= e($priorityLevelCurrent !== '' ? $priorityLevelCurrent : 'normal') ?></span>
                </div>
            </section>

            <div class="dashboard-grid-2">
                <section class="spde-panel">
                    <div class="spde-panel-head">
                        <div>
                            <h3>Modifier le débit dû</h3>
                            <p>Formulaire d’ajustement manuel</p>
                        </div>
                    </div>

                    <form method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">

                        <div class="dashboard-grid-2">
                            <div>
                                <label>Client</label>
                                <input
                                    type="text"
                                    value="<?= e(trim($clientCode . ' - ' . $clientName) ?: '—') ?>"
                                    disabled
                                >
                            </div>

                            <div>
                                <label>Compte 411</label>
                                <input
                                    type="text"
                                    value="<?= e($clientAccount) ?>"
                                    disabled
                                >
                            </div>

                            <div>
                                <label>Montant restant dû</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="remaining_amount"
                                    value="<?= e($formData['remaining_amount']) ?>"
                                    required
                                >
                                <div class="spde-form-help">
                                    Si tu mets <strong>0</strong>, le débit dû sera automatiquement marqué comme <strong>resolved</strong>.
                                </div>
                            </div>

                            <div>
                                <label>Statut</label>
                                <select name="status">
                                    <option value="pending" <?= $formData['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="partial" <?= $formData['status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                                    <option value="ready" <?= $formData['status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                                    <option value="resolved" <?= $formData['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="cancelled" <?= $formData['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>

                            <div>
                                <label>Priorité</label>
                                <select name="priority_level">
                                    <option value="normal" <?= $formData['priority_level'] === 'normal' ? 'selected' : '' ?>>Normale</option>
                                    <option value="high" <?= $formData['priority_level'] === 'high' ? 'selected' : '' ?>>Haute</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:20px;">
                            <label>Notes</label>
                            <textarea name="notes" rows="5"><?= e($formData['notes']) ?></textarea>
                        </div>

                        <div class="btn-group" style="margin-top:20px;">
                            <button type="submit" class="btn btn-success">Enregistrer</button>
                            <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Retour</a>
                        </div>
                    </form>
                </section>

                <section class="spde-panel">
                    <div class="spde-panel-head">
                        <div>
                            <h3>Rappel métier</h3>
                            <p>Impacts avant validation</p>
                        </div>
                    </div>

                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Client</span><strong><?= e(trim($clientCode . ' - ' . $clientName) ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($labelCurrent) ?></strong></div>
                        <div class="sl-data-list__row"><span>Ancien statut</span><strong><?= e($currentStatus !== '' ? $currentStatus : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Ancien restant dû</span><strong><?= e(number_format($remainingAmount, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Ancien exécuté</span><strong><?= e(number_format($executedAmount, 2, ',', ' ')) ?></strong></div>
                    </div>

                    <div class="spde-highlight-box" style="margin-top:16px;">
                        Toute modification est journalisée, historisée et peut déclencher une notification.
                        Le montant exécuté est recalculé automatiquement à partir du montant initial et du nouveau restant dû.
                    </div>
                </section>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>