<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'statements_view';
enforcePagePermission($pdo, $pagePermission);

$clientId = (int)($_GET['client_id'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$clients = $pdo->query("
    SELECT id, client_code, full_name
    FROM clients
    WHERE COALESCE(is_active,1) = 1
    ORDER BY client_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedClient = null;
$operations = [];
$totalCredit = 0.0;
$totalDebit = 0.0;
$netTotal = 0.0;
$pdfUrl = '';

if ($clientId > 0) {
    $stmtClient = $pdo->prepare("
        SELECT *
        FROM clients
        WHERE id = ?
        LIMIT 1
    ");
    $stmtClient->execute([$clientId]);
    $selectedClient = $stmtClient->fetch(PDO::FETCH_ASSOC);

    if ($selectedClient) {
        $sql = "
            SELECT
                o.*,
                ba.account_name,
                ba.account_number
            FROM operations o
            LEFT JOIN bank_accounts ba ON ba.id = o.bank_account_id
            WHERE o.client_id = ?
        ";
        $params = [$clientId];

        if ($dateFrom !== '') {
            $sql .= " AND o.operation_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND o.operation_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY o.operation_date DESC, o.id DESC";

        $stmtOps = $pdo->prepare($sql);
        $stmtOps->execute($params);
        $operations = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

        foreach ($operations as $operation) {
            $clientAccount = $selectedClient['generated_client_account'] ?? '';
            if (($operation['credit_account_code'] ?? '') === $clientAccount) {
                $totalCredit += (float)$operation['amount'];
            }
            if (($operation['debit_account_code'] ?? '') === $clientAccount) {
                $totalDebit += (float)$operation['amount'];
            }
        }

        $netTotal = $totalCredit - $totalDebit;

        if (currentUserCan($pdo, 'statements_export_single')) {
            $query = http_build_query([
                'client_id' => $clientId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
            $pdfUrl = APP_URL . 'modules/statements/generate_statement_pdf.php?' . $query;
        }
    }
}

$pageTitle = 'Relevé client';
$pageSubtitle = 'Lecture détaillée des opérations d’un client sur une période donnée.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div class="btn-group">
                <?php if (currentUserCan($pdo, 'statements_export_bulk')): ?>
                    <a href="<?= e(APP_URL) ?>modules/statements/bulk_statement_export.php" class="btn btn-secondary">Export en masse</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <select name="client_id" required>
                    <option value="0">Choisir un client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int)$client['id'] ?>" <?= $clientId === (int)$client['id'] ? 'selected' : '' ?>>
                            <?= e(($client['client_code'] ?? '') . ' — ' . ($client['full_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                <input type="date" name="date_to" value="<?= e($dateTo) ?>">

                <button type="submit" class="btn btn-secondary">Afficher</button>
                <a href="<?= e(APP_URL) ?>modules/statements/client_statement.php" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <?php if ($selectedClient): ?>
            <div class="card-grid">
                <div class="card">
                    <h3>Client</h3>
                    <div class="kpi"><?= e($selectedClient['client_code'] ?? '') ?></div>
                    <p class="muted"><?= e($selectedClient['full_name'] ?? '') ?></p>
                </div>

                <div class="card">
                    <h3>Total crédits</h3>
                    <div class="kpi"><?= number_format($totalCredit, 2, ',', ' ') ?> €</div>
                </div>

                <div class="card">
                    <h3>Total débits</h3>
                    <div class="kpi"><?= number_format($totalDebit, 2, ',', ' ') ?> €</div>
                </div>

                <div class="card">
                    <h3>Net</h3>
                    <div class="kpi"><?= number_format($netTotal, 2, ',', ' ') ?> €</div>
                </div>
            </div>

            <?php if ($pdfUrl !== ''): ?>
                <div class="btn-group">
                    <a href="<?= e($pdfUrl) ?>" class="btn btn-primary">Exporter le PDF</a>
                </div>
            <?php endif; ?>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Libellé</th>
                            <th>Référence</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operations as $operation): ?>
                            <tr>
                                <td><?= e($operation['operation_date'] ?? '') ?></td>
                                <td><?= e($operation['label'] ?? '') ?></td>
                                <td><?= e($operation['reference'] ?? '') ?></td>
                                <td><?= e($operation['debit_account_code'] ?? '') ?></td>
                                <td><?= e($operation['credit_account_code'] ?? '') ?></td>
                                <td><?= number_format((float)($operation['amount'] ?? 0), 2, ',', ' ') ?> €</td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$operations): ?>
                            <tr><td colspan="6">Aucune opération sur cette période.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>