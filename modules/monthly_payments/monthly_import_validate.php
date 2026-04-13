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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('sl_monthly_validate_redirect')) {
    function sl_monthly_validate_redirect(string $message): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . APP_URL . 'modules/monthly_payments/import_monthly_payments.php');
        exit;
    }
}

if (!function_exists('sl_monthly_validate_pick_import_id')) {
    function sl_monthly_validate_pick_import_id(PDO $pdo): int
    {
        $candidates = [
            (int)($_GET['import_id'] ?? 0),
            (int)($_POST['import_id'] ?? 0),
            (int)($_SESSION['monthly_payment_current_import_id'] ?? 0),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate > 0) {
                return $candidate;
            }
        }

        if (!tableExists($pdo, 'monthly_payment_imports')) {
            return 0;
        }

        $stmt = $pdo->query("
            SELECT id
            FROM monthly_payment_imports
            ORDER BY id DESC
            LIMIT 1
        ");

        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('sl_monthly_row_amount')) {
    function sl_monthly_row_amount(array $row): float
    {
        $keys = ['monthly_amount', 'mensualite_amount', 'amount', 'montant'];

        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return (float)str_replace(',', '.', (string)$row[$key]);
            }
        }

        return 0.0;
    }
}

if (!function_exists('sl_monthly_row_day')) {
    function sl_monthly_row_day(array $row): int
    {
        $keys = ['monthly_day', 'mensualite_day', 'jour'];

        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return (int)$row[$key];
            }
        }

        return 26;
    }
}

if (!function_exists('sl_update_client_monthly_config')) {
    function sl_update_client_monthly_config(PDO $pdo, int $clientId, float $amount, int $day, int $treasuryId): void
    {
        $fields = [];
        $params = [];

        $candidateMap = [
            'monthly_amount' => $amount,
            'mensualite_amount' => $amount,
            'monthly_day' => $day,
            'mensualite_day' => $day,
            'monthly_treasury_account_id' => $treasuryId,
            'mensualite_treasury_account_id' => $treasuryId,
            'monthly_enabled' => 1,
            'mensualite_enabled' => 1,
        ];

        foreach ($candidateMap as $column => $value) {
            if (columnExists($pdo, 'clients', $column)) {
                $fields[] = $column . ' = ?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'clients', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        if (!$fields) {
            throw new RuntimeException('Aucune colonne mensualité exploitable dans la table clients.');
        }

        $params[] = $clientId;

        $stmt = $pdo->prepare("
            UPDATE clients
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
    }
}

$importId = sl_monthly_validate_pick_import_id($pdo);
if ($importId <= 0) {
    sl_monthly_validate_redirect('Aucun import de mensualités à valider.');
}

$successMessage = '';
$errorMessage = '';

$stmtImport = $pdo->prepare("
    SELECT *
    FROM monthly_payment_imports
    WHERE id = ?
    LIMIT 1
");
$stmtImport->execute([$importId]);
$import = $stmtImport->fetch(PDO::FETCH_ASSOC);

if (!$import) {
    unset($_SESSION['monthly_payment_current_import_id']);
    sl_monthly_validate_redirect('Import introuvable.');
}

$_SESSION['monthly_payment_current_import_id'] = $importId;

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

        foreach ($rows as $row) {
            $clientCode = trim((string)($row['client_code'] ?? ''));
            $amount = sl_monthly_row_amount($row);
            $day = sl_monthly_row_day($row);
            $treasuryCode = trim((string)($row['treasury_account_code'] ?? ''));

            $status = 'validated';
            $message = [];

            $stmtClient = $pdo->prepare("
                SELECT id
                FROM clients
                WHERE client_code = ?
                LIMIT 1
            ");
            $stmtClient->execute([$clientCode]);
            $clientId = (int)($stmtClient->fetchColumn() ?: 0);

            $stmtTreasury = $pdo->prepare("
                SELECT id
                FROM treasury_accounts
                WHERE account_code = ?
                LIMIT 1
            ");
            $stmtTreasury->execute([$treasuryCode]);
            $treasuryId = (int)($stmtTreasury->fetchColumn() ?: 0);

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
                sl_update_client_monthly_config($pdo, $clientId, $amount, $day, $treasuryId);
            }

            $updateFields = [];
            $updateParams = [];

            if (columnExists($pdo, 'monthly_payment_import_rows', 'row_status')) {
                $updateFields[] = 'row_status = ?';
                $updateParams[] = $status;
            }

            if (columnExists($pdo, 'monthly_payment_import_rows', 'row_message')) {
                $updateFields[] = 'row_message = ?';
                $updateParams[] = implode(' | ', $message);
            }

            if (columnExists($pdo, 'monthly_payment_import_rows', 'updated_at')) {
                $updateFields[] = 'updated_at = NOW()';
            }

            if ($updateFields) {
                $updateParams[] = (int)$row['id'];

                $stmtUpdateRow = $pdo->prepare("
                    UPDATE monthly_payment_import_rows
                    SET " . implode(', ', $updateFields) . "
                    WHERE id = ?
                ");
                $stmtUpdateRow->execute($updateParams);
            }
        }

        $importUpdateFields = [];
        $importUpdateParams = [];

        if (columnExists($pdo, 'monthly_payment_imports', 'status')) {
            $importUpdateFields[] = 'status = ?';
            $importUpdateParams[] = 'validated';
        }

        if (columnExists($pdo, 'monthly_payment_imports', 'updated_at')) {
            $importUpdateFields[] = 'updated_at = NOW()';
        }

        if ($importUpdateFields) {
            $importUpdateParams[] = $importId;

            $stmtImportStatus = $pdo->prepare("
                UPDATE monthly_payment_imports
                SET " . implode(', ', $importUpdateFields) . "
                WHERE id = ?
            ");
            $stmtImportStatus->execute($importUpdateParams);
        }

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
                                <td><?= e(number_format((float)sl_monthly_row_amount($row), 2, ',', ' ')) ?></td>
                                <td><?= (int)sl_monthly_row_day($row) ?></td>
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