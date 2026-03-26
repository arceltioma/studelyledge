<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$clientId = (int)($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
$dateFrom = trim((string)($_POST['date_from'] ?? $_GET['date_from'] ?? ''));
$dateTo = trim((string)($_POST['date_to'] ?? $_GET['date_to'] ?? ''));

if ($clientId <= 0) {
    exit('Client invalide.');
}

$stmtClient = $pdo->prepare("
    SELECT *
    FROM clients
    WHERE id = ?
    LIMIT 1
");
$stmtClient->execute([$clientId]);
$client = $stmtClient->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$sql = "
    SELECT *
    FROM operations
    WHERE client_id = ?
";
$params = [$clientId];

if ($dateFrom !== '') {
    $sql .= " AND operation_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND operation_date <= ?";
    $params[] = $dateTo;
}
$sql .= " ORDER BY operation_date ASC, id ASC";

$stmtOps = $pdo->prepare($sql);
$stmtOps->execute($params);
$operations = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

$clientBank = findPrimaryBankAccountForClient($pdo, $clientId);
$initialBalance = (float)($clientBank['initial_balance'] ?? 0);
$currentBalance = (float)($clientBank['balance'] ?? 0);

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1, h2 { color: #1d2549; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; }
        th { background: #eef2ff; }
        .meta td:first-child { width: 220px; font-weight: bold; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Relevé de compte client</h1>
    <table class="meta">
        <tr><td>Code client</td><td><?= e($client['client_code'] ?? '') ?></td></tr>
        <tr><td>Nom complet</td><td><?= e($client['full_name'] ?? '') ?></td></tr>
        <tr><td>Compte client</td><td><?= e($client['generated_client_account'] ?? '') ?></td></tr>
        <tr><td>Période</td><td><?= e(($dateFrom ?: 'origine') . ' → ' . ($dateTo ?: 'aujourd’hui')) ?></td></tr>
        <tr><td>Solde initial</td><td><?= number_format($initialBalance, 2, ',', ' ') ?></td></tr>
        <tr><td>Solde courant</td><td><?= number_format($currentBalance, 2, ',', ' ') ?></td></tr>
    </table>

    <h2>Opérations</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Libellé</th>
                <th>Référence</th>
                <th>Débit</th>
                <th>Crédit</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$operations): ?>
                <tr><td colspan="6">Aucune opération sur la période.</td></tr>
            <?php else: ?>
                <?php foreach ($operations as $op): ?>
                    <tr>
                        <td><?= e($op['operation_date'] ?? '') ?></td>
                        <td><?= e($op['label'] ?? '') ?></td>
                        <td><?= e($op['reference'] ?? '') ?></td>
                        <td><?= e($op['debit_account_code'] ?? '') ?></td>
                        <td><?= e($op['credit_account_code'] ?? '') ?></td>
                        <td class="right"><?= number_format((float)($op['amount'] ?? 0), 2, ',', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'releve_client_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($client['client_code'] ?? 'client')) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $dompdf->output();
exit;