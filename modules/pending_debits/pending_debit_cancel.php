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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'pending_debits_cancel');
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

if (!function_exists('sl_pending_cancel_badge_class')) {
    function sl_pending_cancel_badge_class(string $status): string
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
    $_SESSION['error_message'] = 'Débit dû invalide.';
    header('Location: ' . APP_URL . 'modules/pending_debits/pending_debits_list.php');
    exit;
}

if (!tableExists($pdo, 'pending_client_debits')) {
    $_SESSION['error_message'] = 'Table pending_client_debits introuvable.';
    header('Location: ' . APP_URL . 'modules/pending_debits/pending_debits_list.php');
    exit;
}

$select = ['pd.*'];
$joinClient = '';

if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
    $joinClient = 'LEFT JOIN clients c ON c.id = pd.client_id';
    $select[] = 'c.client_code';
    $select[] = 'c.full_name';
    $select[] = 'c.generated_client_account';
    $select[] = 'c.currency AS client_currency';
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
    $_SESSION['error_message'] = 'Débit dû introuvable.';
    header('Location: ' . APP_URL . 'modules/pending_debits/pending_debits_list.php');
    exit;
}

$currentStatus = (string)($item['status'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$clientCode = (string)($item['client_code'] ?? '');
$clientName = (string)($item['full_name'] ?? '');
$clientAccountCode = (string)($item['generated_client_account'] ?? $item['client_account_code'] ?? '');
$remainingAmount = (float)($item['remaining_amount'] ?? 0);
$initialAmount = (float)($item['initial_amount'] ?? 0);
$executedAmount = (float)($item['executed_amount'] ?? 0);
$label = trim((string)($item['label'] ?? 'Débit dû'));
$currencyCode = (string)($item['currency_code'] ?? $item['client_currency'] ?? 'EUR');
$redirectUrl = APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id;
$canCancel = !in_array($currentStatus, ['resolved', 'cancelled', 'settled'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!$canCancel) {
            $_SESSION['error_message'] = 'Ce débit dû ne peut plus être annulé car il est déjà ' . $currentStatus . '.';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $pdo->beginTransaction();

        $updateFields = ["status = 'cancelled'"];
        if (columnExists($pdo, 'pending_client_debits', 'updated_at')) {
            $updateFields[] = "updated_at = NOW()";
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE pending_client_debits
            SET " . implode(', ', $updateFields) . "
            WHERE id = ?
        ");
        $stmtUpdate->execute([$id]);

        if (function_exists('sl_create_pending_client_debit_log')) {
            sl_create_pending_client_debit_log(
                $pdo,
                $id,
                'cancel',
                $currentStatus,
                'cancelled',
                $remainingAmount,
                'Annulation manuelle du débit dû',
                $userId
            );
        }

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'cancel_pending_debit',
                'pending_debits',
                'pending_client_debit',
                $id,
                'Annulation du débit dû #' . $id
                . ($clientCode !== '' ? ' | client: ' . $clientCode : '')
                . ($clientAccountCode !== '' ? ' | compte: ' . $clientAccountCode : '')
                . ' | montant restant: ' . number_format($remainingAmount, 2, '.', '')
            );
        }

        sl_create_notification_if_possible(
            $pdo,
            'pending_debit_cancelled',
            'Débit dû annulé'
                . ($clientCode !== '' ? ' pour le client ' . $clientCode : '')
                . ($clientName !== '' ? ' - ' . $clientName : '')
                . ' | restant : ' . number_format($remainingAmount, 2, ',', ' ')
                . ' ' . $currencyCode
                . ($label !== '' ? ' | ' . $label : ''),
            'warning',
            $redirectUrl,
            'pending_client_debit',
            $id,
            $userId
        );

        $pdo->commit();

        $_SESSION['success_message'] = 'Le débit dû a été annulé avec succès.';
        header('Location: ' . $redirectUrl);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$pageTitle = 'Annuler un débit dû';
$pageSubtitle = 'Confirmation d’annulation du débit dû client 411';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .spdc-shell {
                display: grid;
                gap: 20px;
            }

            .spdc-toolbar,
            .spdc-panel,
            .spdc-kpi-card {
                border-radius: 20px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            }

            .spdc-toolbar,
            .spdc-panel {
                padding: 18px;
            }

            .spdc-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
            }

            .spdc-toolbar__titles h2 {
                margin: 0 0 6px;
                font-size: 24px;
                font-weight: 900;
                color: #0f172a;
            }

            .spdc-toolbar__titles p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
            }

            .spdc-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: center;
            }

            .spdc-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 1100px) {
                .spdc-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .spdc-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .spdc-kpi-card {
                padding: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .spdc-kpi-card__label {
                display: block;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .spdc-kpi-card__value {
                display: block;
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }

            .spdc-kpi-card__meta {
                display: block;
                margin-top: 8px;
                font-size: 12px;
                color: #64748b;
            }

            .spdc-panel-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 14px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }

            .spdc-panel-head h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 900;
                color: #0f172a;
            }

            .spdc-panel-head p {
                margin: 4px 0 0;
                font-size: 13px;
                color: #64748b;
            }

            .spdc-alert-danger {
                padding: 16px 18px;
                border-radius: 16px;
                background: rgba(239, 68, 68, 0.08);
                border: 1px solid rgba(239, 68, 68, 0.18);
                color: #991b1b;
                line-height: 1.6;
            }

            .spdc-alert-warning {
                padding: 16px 18px;
                border-radius: 16px;
                background: rgba(245, 158, 11, 0.08);
                border: 1px solid rgba(245, 158, 11, 0.18);
                color: #92400e;
                line-height: 1.6;
            }

            .spdc-note-box {
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.9);
                border: 1px dashed rgba(148, 163, 184, 0.35);
                color: #475569;
                line-height: 1.6;
            }

            .spdc-badge {
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
        </style>

        <div class="spdc-shell">
            <section class="spdc-toolbar">
                <div class="spdc-toolbar__titles">
                    <h2>Annuler le débit dû #<?= (int)$id ?></h2>
                    <p>Écran de confirmation avant passage du statut à <strong>cancelled</strong>.</p>
                </div>

<div class="spdc-actions">
    <a href="<?= e($redirectUrl) ?>" class="btn btn-outline">Retour au détail</a>
    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_edit.php?id=<?= (int)$id ?>" class="btn btn-outline">Modifier</a>
    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php" class="btn btn-outline">Liste</a>
</div>
            </section>

            <section class="spdc-kpi-grid">
                <div class="spdc-kpi-card">
                    <span class="spdc-kpi-card__label">Montant initial</span>
                    <span class="spdc-kpi-card__value"><?= e(number_format($initialAmount, 2, ',', ' ')) ?></span>
                    <span class="spdc-kpi-card__meta"><?= e($currencyCode) ?></span>
                </div>

                <div class="spdc-kpi-card">
                    <span class="spdc-kpi-card__label">Déjà exécuté</span>
                    <span class="spdc-kpi-card__value"><?= e(number_format($executedAmount, 2, ',', ' ')) ?></span>
                    <span class="spdc-kpi-card__meta">Montant traité</span>
                </div>

                <div class="spdc-kpi-card">
                    <span class="spdc-kpi-card__label">Restant dû</span>
                    <span class="spdc-kpi-card__value"><?= e(number_format($remainingAmount, 2, ',', ' ')) ?></span>
                    <span class="spdc-kpi-card__meta">Montant qui sera abandonné</span>
                </div>

                <div class="spdc-kpi-card">
                    <span class="spdc-kpi-card__label">Statut actuel</span>
                    <span class="spdc-kpi-card__value" style="font-size:20px; line-height:1.2;">
                        <span class="spdc-badge <?= e(sl_pending_cancel_badge_class($currentStatus)) ?>">
                            <?= e($currentStatus !== '' ? $currentStatus : '—') ?>
                        </span>
                    </span>
                    <span class="spdc-kpi-card__meta">Priorité : <?= e((string)($item['priority_level'] ?? '—')) ?></span>
                </div>
            </section>

            <div class="dashboard-grid-2">
                <section class="spdc-panel">
                    <div class="spdc-panel-head">
                        <div>
                            <h3>Informations du débit dû</h3>
                            <p>Contexte métier avant annulation</p>
                        </div>
                    </div>

                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$id ?></strong></div>
                        <div class="sl-data-list__row"><span>Client</span><strong><?= e(trim($clientCode . ' - ' . $clientName) ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e($clientAccountCode !== '' ? $clientAccountCode : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($label !== '' ? $label : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Déclencheur</span><strong><?= e((string)($item['trigger_type'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Créé le</span><strong><?= e((string)($item['created_at'] ?? '—')) ?></strong></div>
                    </div>
                </section>

                <section class="spdc-panel">
                    <div class="spdc-panel-head">
                        <div>
                            <h3>Confirmation</h3>
                            <p>Validation explicite avant action irréversible métier</p>
                        </div>
                    </div>

                    <?php if ($canCancel): ?>
                        <div class="spdc-alert-danger">
                            <strong>Attention :</strong> cette action va annuler ce débit dû en le passant au statut
                            <strong>cancelled</strong>.  
                            Le reliquat ne sera plus exécutable depuis le flux standard des débits dus.
                        </div>

                        <div class="spdc-note-box" style="margin-top:16px;">
                            L’annulation conserve l’historique, crée un log métier et déclenche une notification.
                            Aucune suppression physique n’est faite dans la base.
                        </div>

                        <form method="POST" style="margin-top:20px;">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int)$id ?>">

                            <div class="btn-group">
                                <button type="submit" class="btn btn-danger">Confirmer l’annulation</button>
                                <a href="<?= e($redirectUrl) ?>" class="btn btn-outline">Ne pas annuler</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="spdc-alert-warning">
                            Ce débit dû ne peut plus être annulé car son statut actuel est
                            <strong><?= e($currentStatus !== '' ? $currentStatus : '—') ?></strong>.
                        </div>

                        <div class="btn-group" style="margin-top:20px;">
                            <a href="<?= e($redirectUrl) ?>" class="btn btn-outline">Retour au détail</a>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>