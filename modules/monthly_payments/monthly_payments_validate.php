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

$importId = (int)($_GET['import_id'] ?? $_POST['import_id'] ?? 0);
if ($importId <= 0) {
    exit('Import invalide.');
}

$successMessage = '';
$errorMessage = '';

$stmtImport = $pdo->prepare("SELECT * FROM monthly_payment_imports WHERE id = ? LIMIT 1");
$stmtImport->execute([$importId]);
$import = $stmtImport->fetch(PDO::FETCH_ASSOC);

if (!$import) {
    exit('Import introuvable.');
}

$stmtRows = $pdo->prepare("
    SELECT *
    FROM monthly_payment_import_rows
    WHERE import_id = ?
    ORDER BY id ASC
");
$stmtRows->execute([$importId]);
$rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $pdo->beginTransaction();

        $stmtUpdateRow = $pdo->prepare("
            UPDATE monthly_payment_import_rows
            SET row_status = ?, row_message = ?, updated_at = NOW()
            WHERE id = ?
        ");

        foreach ($rows as $row) {
            $clientCode = trim((string)($row['client_code'] ?? ''));
            $amount = (float)($row['mensualite_amount'] ?? 0);
            $day = (int)($row['monthly_day'] ?? 26);
            $treasuryCode = trim((string)($row['treasury_account_code'] ?? ''));

            $status = 'validated';
            $message = [];

            $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_code = ? LIMIT 1");
            $stmt->execute([$clientCode]);
            $clientId = (int)($stmt->fetchColumn() ?: 0);

            $stmt2 = $pdo->prepare("SELECT id FROM treasury_accounts WHERE account_code = ? LIMIT 1");
            $stmt2->execute([$treasuryCode]);
            $treasuryId = (int)($stmt2->fetchColumn() ?: 0);

            if ($clientId <= 0) {
                $status = 'error';
                $message[] = 'Client introuvable';
            }

            if ($treasuryId <= 0) {
                $status = 'error';
                $message[] = 'Compte 512 introuvable';
            }

            if ($amount <= 0) {
                $status = 'error';
                $message[] = 'Montant invalide';
            }

            if ($day < 1 || $day > 31) {
                $status = 'error';
                $message[] = 'Jour invalide';
            }

            if ($status === 'validated') {
                $stmtClient = $pdo->prepare("
                    UPDATE clients
                    SET
                        mensualite_amount = ?,
                        mensualite_day = ?,
                        mensualite_treasury_account_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmtClient->execute([
                    $amount,
                    $day,
                    $treasuryId,
                    $clientId
                ]);
            }

            $stmtUpdateRow->execute([
                $status,
                implode(' | ', $message),
                (int)$row['id']
            ]);
        }

        $stmtImportStatus = $pdo->prepare("
            UPDATE monthly_payment_imports
            SET status = 'validated', updated_at = NOW()
            WHERE id = ?
        ");
        $stmtImportStatus->execute([$importId]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'validate_monthly_payments_import',
                'monthly_payments',
                'monthly_payment_import',
                $importId,
                'Validation manuelle de l’import des mensualités'
            );
        }

        $pdo->commit();
        $successMessage = 'Import validé et mensualités affectées aux clients.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }

    $stmtRows->execute([$importId]);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$pageTitle = 'Validation des mensualités';
$pageSubtitle = 'Application des mensualités validées sur les fiches clients';
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

        <div class="card">
            <h3>Validation manuelle de l’import #<?= (int)$importId ?></h3>

            <form method="POST" style="margin-bottom:20px;">
                <?= csrf_input() ?>
                <input type="hidden" name="import_id" value="<?= (int)$importId ?>">

                <div class="btn-group">
                    <button type="submit" class="btn btn-success">Valider cet import</button>
                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_payments_preview.php?import_id=<?= (int)$importId ?>" class="btn btn-outline">Retour preview</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Jour</th>
                            <th>Compte 512</th>
                            <th>Statut</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                                <td><?= e(number_format((float)($row['mensualite_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= (int)($row['monthly_day'] ?? 26) ?></td>
                                <td><?= e((string)($row['treasury_account_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['row_status'] ?? 'pending')) ?></td>
                                <td><?= e((string)($row['row_message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="6">Aucune ligne.</td>
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
