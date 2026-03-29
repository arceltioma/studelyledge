<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Type d’opération invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM ref_operation_types
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Type d’opération introuvable.');
}

$linkedServices = [];
$activeLinkedServices = [];

if (tableExists($pdo, 'ref_services')) {
    $stmtServices = $pdo->prepare("
        SELECT id, code, label, is_active
        FROM ref_services
        WHERE operation_type_id = ?
        ORDER BY label ASC
    ");
    $stmtServices->execute([$id]);
    $linkedServices = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

    foreach ($linkedServices as $service) {
        if ((int)($service['is_active'] ?? 1) === 1) {
            $activeLinkedServices[] = $service;
        }
    }
}

$errorMessage = '';
$warningMessage = '';

if ($linkedServices) {
    $warningMessage = count($linkedServices) . ' service(s) sont encore rattaché(s) à ce type d’opération.';
}

$canArchive = count($activeLinkedServices) === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!$canArchive) {
            throw new RuntimeException(
                'Impossible d’archiver ce type d’opération tant que des services actifs lui sont encore rattachés.'
            );
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE ref_operation_types
            SET is_active = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$id]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'archive_operation_type',
                'admin_functional',
                'operation_type',
                $id,
                'Archivage d’un type d’opération après contrôle des services liés'
            );
        }

        header('Location: ' . APP_URL . 'modules/admin_functional/manage_operation_types.php?ok=archived');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Archiver un type d’opération';
$pageSubtitle = 'On bloque l’archivage si des services actifs dépendent encore de ce type.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($warningMessage !== ''): ?>
            <div class="warning"><?= e($warningMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Confirmation</h3>
                <p>
                    Tu vas archiver le type d’opération
                    <strong><?= e($row['label'] ?? '') ?></strong>
                    (<?= e($row['code'] ?? '') ?>).
                </p>

                <?php if ($canArchive): ?>
                    <p class="muted">
                        Aucun service actif ne dépend de ce type. L’archivage peut être effectué.
                    </p>

                    <form method="POST">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">

                        <div class="btn-group">
                            <button type="submit" class="btn btn-danger">Confirmer l’archivage</button>
                            <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-outline">Annuler</a>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="muted">
                        L’archivage est bloqué tant que des services actifs dépendent encore de ce type.
                    </p>

                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-secondary">Gérer les services liés</a>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-outline">Retour</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Contrôle des liens métier</h3>

                <div class="stat-row">
                    <span class="metric-label">Code type</span>
                    <span class="metric-value"><?= e($row['code'] ?? '') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Libellé</span>
                    <span class="metric-value"><?= e($row['label'] ?? '') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Direction</span>
                    <span class="metric-value"><?= e($row['direction'] ?? '') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Services liés</span>
                    <span class="metric-value"><?= count($linkedServices) ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Services actifs liés</span>
                    <span class="metric-value"><?= count($activeLinkedServices) ?></span>
                </div>
            </div>
        </div>

        <?php if ($linkedServices): ?>
            <div class="table-card" style="margin-top:20px;">
                <h3 class="section-title">Services rattachés à ce type</h3>

                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linkedServices as $service): ?>
                            <tr>
                                <td><?= e($service['code'] ?? '') ?></td>
                                <td><?= e($service['label'] ?? '') ?></td>
                                <td><?= ((int)($service['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>