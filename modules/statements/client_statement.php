<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'statements_view';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$clientId = (int)($_GET['client_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$clients = $pdo->query("
    SELECT id, client_code, first_name, last_name
    FROM clients
    WHERE is_active = 1
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
        SELECT
            c.*,
            s.name AS status_name,
            cat.name AS category_name
        FROM clients c
        LEFT JOIN statuses s ON s.id = c.status_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.id = ?
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
            if ($operation['operation_type'] === 'credit') {
                $totalCredit += (float)$operation['amount'];
            } else {
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
            $pdfUrl = APP_URL . 'modules/statements/generate_bulk_pdf.php?single=1&' . $query;
        }
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Relevé client', 'Lecture détaillée des opérations d’un client sur une période donnée.'); ?>

        <div class="page-title">

            <div class="btn-group">
                <?php if (currentUserCan($pdo, 'statements_export_bulk')): ?>
                    <a href="<?= APP_URL ?>modules/statements/bulk_statement_export.php" class="btn btn-secondary">Export en masse</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <select name="client_id" required>
                    <option value="0">Choisir un client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int)$client['id'] ?>" <?= $clientId === (int)$client['id'] ? 'selected' : '' ?>>
                            <?= e($client['client_code'] . ' — ' . $client['first_name'] . ' ' . $client['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                <input type="date" name="date_to" value="<?= e($dateTo) ?>">

                <button type="submit" class="btn btn-secondary">Afficher</button>
                <a href="<?= APP_URL ?>modules/statements/client_statement.php" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <?php if ($selectedClient): ?>
            <div class="card-grid">
                <div class="card">
                    <h3>Client</h3>
                    <div class="kpi" style="font-size:20px;"><?= e($selectedClient['client_code']) ?></div>
                    <p class="muted"><?= e($selectedClient['first_name'] . ' ' . $selectedClient['last_name']) ?></p>
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
                    <h3>Solde Net</h3>
                    <div class="kpi"><?= number_format($netTotal, 2, ',', ' ') ?> €</div>
                </div>
            </div>

            <div class="btn-group">
                <?php if ($pdfUrl !== ''): ?>
                    <a href="<?= e($pdfUrl) ?>" class="btn btn-danger" target="_blank">Télécharger le PDF</a>
                <?php endif; ?>
            </div>

            <div class="table-card">
                <h3 class="section-title">Détail des opérations</h3>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Compte</th>
                            <th>Type</th>
                            <th>Nature</th>
                            <th>Libellé</th>
                            <th>Référence</th>
                            <th>Source</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$operations): ?>
                            <tr>
                                <td colspan="8">Aucune opération trouvée pour cette période.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($operations as $operation): ?>
                                <tr>
                                    <td><?= e($operation['operation_date']) ?></td>
                                    <td><?= e(($operation['account_name'] ?? '—') . (!empty($operation['account_number']) ? ' — ' . $operation['account_number'] : '')) ?></td>
                                    <td>
                                        <?php if ($operation['operation_type'] === 'credit'): ?>
                                            <span class="status-pill status-success">Crédit</span>
                                        <?php else: ?>
                                            <span class="status-pill status-danger">Débit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($operation['operation_kind'] ?? '—') ?></td>
                                    <td><?= e($operation['label']) ?></td>
                                    <td><?= e($operation['reference'] ?? '—') ?></td>
                                    <td><?= e($operation['source_type']) ?></td>
                                    <td><?= number_format((float)$operation['amount'], 2, ',', ' ') ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>