<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_validate_page');
} else {
    enforcePagePermission($pdo, 'imports_validate');
}

$importId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($importId <= 0) {
    exit('Import invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM monthly_payment_imports
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$importId]);
$import = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$import) {
    exit('Import introuvable.');
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $pdo->beginTransaction();

        $stmtRows = $pdo->prepare("
            SELECT *
            FROM monthly_payment_import_rows
            WHERE import_id = ?
            ORDER BY row_number ASC, id ASC
        ");
        $stmtRows->execute([$importId]);
        $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $validCount = 0;
        $errorCount = 0;

        $stmtUpdateRow = $pdo->prepare("
            UPDATE monthly_payment_import_rows
            SET
                status = ?,
                error_message = ?,
                resolved_client_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        foreach ($rows as $row) {
            $check = sl_monthly_payment_validate_row($pdo, $row);

            if ($check['is_valid']) {
                $client = $check['client'];
                $treasury = $check['treasury'];
                $label = trim((string) ($row['label'] ?? ''));

                $stmtClient = $pdo->prepare("
                    UPDATE clients
                    SET
                        monthly_amount = ?,
                        monthly_treasury_account_id = ?,
                        monthly_day = ?,
                        monthly_enabled = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtClient->execute([
                    $check['amount'],
                    (int) ($treasury['id'] ?? 0),
                    $check['monthly_day'],
                    (int) ($client['id'] ?? 0),
                ]);

                $stmtUpdateRow->execute([
                    'validated',
                    null,
                    (int) ($client['id'] ?? 0),
                    (int) $row['id'],
                ]);

                $validCount++;
            } else {
                $stmtUpdateRow->execute([
                    'error',
                    implode(' | ', $check['errors']),
                    null,
                    (int) $row['id'],
                ]);

                $errorCount++;
            }
        }

        $finalStatus = $errorCount > 0
            ? ($validCount > 0 ? 'partial' : 'error')
            : 'validated';

        $stmtImport = $pdo->prepare("
            UPDATE monthly_payment_imports
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtImport->execute([$finalStatus, $importId]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int) $_SESSION['user_id'],
                'validate_import',
                'monthly_payments',
                'monthly_payment_import',
                $importId,
                'Validation d’un import de mensualités'
            );
        }

        $pdo->commit();

        $successMessage = "Import validé. {$validCount} ligne(s) valide(s), {$errorCount} ligne(s) en erreur.";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$stmtRows = $pdo->prepare("
    SELECT *
    FROM monthly_payment_import_rows
    WHERE import_id = ?
    ORDER BY row_number ASC, id ASC
");
$stmtRows->execute([$importId]);
$rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = 'Valider import mensualités';
$pageSubtitle = 'Application des mensualités dans la fiche client';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="card">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int) $importId ?>">

                <div class="btn-group">
                    <button type="submit" class="btn btn-success">Lancer la validation</button>
                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_import_preview.php?id=<?= (int) $importId ?>" class="btn btn-outline">Retour preview</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Résultat des lignes</h3>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>512</th>
                            <th>Jour</th>
                            <th>Statut</th>
                            <th>Erreur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int) ($row['row_number'] ?? 0) ?></td>
                                <td><?= e((string) ($row['client_code'] ?? '')) ?></td>
                                <td><?= e(number_format((float) ($row['monthly_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e((string) ($row['treasury_account_code'] ?? '')) ?></td>
                                <td><?= e((string) ($row['monthly_day'] ?? '')) ?></td>
                                <td><?= e((string) ($row['status'] ?? 'pending')) ?></td>
                                <td><?= e((string) ($row['error_message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7">Aucune ligne.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>