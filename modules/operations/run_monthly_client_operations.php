<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'operations_monthly_run');
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

$pageTitle = 'Génération des mensualités clients';
$pageSubtitle = 'Lancement contrôlé des virements automatiques 411 vers 512';

$runDate = trim((string)($_POST['run_date'] ?? $_GET['run_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
    $runDate = date('Y-m-d');
}

$report = null;
$successMessage = '';
$errorMessage = '';

if (isset($_SESSION['success_message']) && $_SESSION['success_message'] !== '') {
    $successMessage = (string)$_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message']) && $_SESSION['error_message'] !== '') {
    $errorMessage = (string)$_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);

        $report = sl_run_monthly_client_operations($pdo, $runDate, $userId);

        $createdCount = (int)($report['created'] ?? 0);
        $processedCount = (int)($report['processed'] ?? 0);
        $skippedCount = (int)($report['skipped'] ?? 0);
        $errorsCount = count($report['errors'] ?? []);

        $successMessage = 'Traitement terminé : ' . $createdCount . ' mensualité(s) créée(s).';

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'execute_monthly_run',
                'monthly_payments',
                'monthly_payment_run',
                isset($report['run_id']) ? (int)$report['run_id'] : null,
                'Lancement manuel des mensualités clients'
                . ' | date: ' . $runDate
                . ' | analysés: ' . $processedCount
                . ' | créées: ' . $createdCount
                . ' | ignorées: ' . $skippedCount
                . ' | erreurs: ' . $errorsCount
            );
        }

        sl_create_notification_if_possible(
            $pdo,
            $errorsCount > 0 ? 'monthly_run_executed_with_errors' : 'monthly_run_executed',
            'Traitement des mensualités clients exécuté'
            . ' | date : ' . $runDate
            . ' | analysés : ' . $processedCount
            . ' | créées : ' . $createdCount
            . ' | ignorées : ' . $skippedCount
            . ' | erreurs : ' . $errorsCount,
            $errorsCount > 0 ? 'warning' : 'success',
            APP_URL . 'modules/operations/run_monthly_client_operations.php?run_date=' . urlencode($runDate),
            'monthly_payment_run',
            isset($report['run_id']) ? (int)$report['run_id'] : null,
            $userId > 0 ? $userId : null
        );

        if ($errorsCount > 0 && !empty($report['errors'])) {
            foreach ($report['errors'] as $item) {
                $clientCode = (string)($item['client_code'] ?? '');
                $clientId = (int)($item['client_id'] ?? 0);
                $message = (string)($item['message'] ?? 'Erreur inconnue');

                sl_create_notification_if_possible(
                    $pdo,
                    'monthly_run_item_error',
                    'Erreur génération mensualité'
                    . ($clientCode !== '' ? ' | client : ' . $clientCode : '')
                    . ($clientId > 0 ? ' (#' . $clientId . ')' : '')
                    . ' | date : ' . $runDate
                    . ' | ' . $message,
                    'warning',
                    APP_URL . 'modules/operations/run_monthly_client_operations.php?run_date=' . urlencode($runDate),
                    'monthly_payment_run',
                    isset($report['run_id']) ? (int)$report['run_id'] : null,
                    $userId > 0 ? $userId : null
                );
            }
        }

        $_SESSION['success_message'] = $successMessage;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'execute_monthly_run_failed',
                'monthly_payments',
                'monthly_payment_run',
                null,
                'Échec du lancement manuel des mensualités clients'
                . ' | date: ' . $runDate
                . ' | erreur: ' . $errorMessage
            );
        }

        sl_create_notification_if_possible(
            $pdo,
            'monthly_run_execution_failed',
            'Échec du traitement des mensualités clients'
            . ' | date : ' . $runDate
            . ' | erreur : ' . $errorMessage,
            'danger',
            APP_URL . 'modules/operations/run_monthly_client_operations.php?run_date=' . urlencode($runDate),
            'monthly_payment_run',
            null,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
        );

        $_SESSION['error_message'] = $errorMessage;
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Lancer la génération</h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <div>
                        <label>Date de traitement</label>
                        <input type="date" name="run_date" value="<?= e($runDate) ?>" required>
                    </div>

                    <div class="dashboard-note" style="margin-top:16px;">
                        Les mensualités ne seront générées que pour les clients actifs, configurés, avec jour mensuel correspondant, et sans doublon déjà généré pour le mois.
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Lancer le traitement</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Résultat</h3>

                <?php if (is_array($report)): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Date</span><strong><?= e((string)($report['run_date'] ?? $runDate)) ?></strong></div>
                        <div class="sl-data-list__row"><span>Clients analysés</span><strong><?= (int)($report['processed'] ?? 0) ?></strong></div>
                        <div class="sl-data-list__row"><span>Mensualités créées</span><strong><?= (int)($report['created'] ?? 0) ?></strong></div>
                        <div class="sl-data-list__row"><span>Lignes ignorées</span><strong><?= (int)($report['skipped'] ?? 0) ?></strong></div>
                        <div class="sl-data-list__row"><span>Erreurs</span><strong><?= count($report['errors'] ?? []) ?></strong></div>
                    </div>

                    <?php if (!empty($report['errors'])): ?>
                        <div class="table-card" style="margin-top:16px;">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Erreur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report['errors'] as $item): ?>
                                        <tr>
                                            <td><?= e(($item['client_code'] ?? '') . ' #' . (int)($item['client_id'] ?? 0)) ?></td>
                                            <td><?= e((string)($item['message'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dashboard-note">
                        Lance le traitement pour générer les mensualités dues à la date choisie.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>