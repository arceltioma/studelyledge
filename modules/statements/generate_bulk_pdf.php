<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'statements_export');

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['_csrf_token'] ?? null)) {
    exit('Jeton CSRF invalide.');
}

$documentKind = trim((string)($_POST['document_kind'] ?? 'statement'));
$dateFrom = trim((string)($_POST['date_from'] ?? ''));
$dateTo = trim((string)($_POST['date_to'] ?? ''));
$fields = $_POST['fields'] ?? [];

$clientIds = [];

if (!empty($_POST['client_ids']) && is_array($_POST['client_ids'])) {
    $clientIds = array_values(array_filter(array_map('intval', $_POST['client_ids']), fn($v) => $v > 0));
} else {
    $mode = trim((string)($_POST['mode'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $statusId = (int)($_POST['status_id'] ?? 0);

    $sql = "SELECT id FROM clients WHERE COALESCE(is_active,1)=1";
    $params = [];

    if ($mode === 'country' && $country !== '') {
        $sql .= " AND country_destination = ?";
        $params[] = $country;
    } elseif ($mode === 'status' && $statusId > 0) {
        $sql .= " AND status_id = ?";
        $params[] = $statusId;
    } elseif ($mode === 'selection') {
        $sql .= " AND 1=0";
    }

    $stmtIds = $pdo->prepare($sql);
    $stmtIds->execute($params);
    $clientIds = array_map('intval', $stmtIds->fetchAll(PDO::FETCH_COLUMN));
}

if (!$clientIds) {
    exit('Aucun client sélectionné.');
}

$placeholders = implode(',', array_fill(0, count($clientIds), '?'));
$stmtClients = $pdo->prepare("
    SELECT *
    FROM clients
    WHERE id IN ({$placeholders})
    ORDER BY client_code ASC
");
$stmtClients->execute($clientIds);
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

if (!$clients) {
    exit('Aucun client trouvé.');
}

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'studelyledger_export_' . uniqid('', true);
if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
    exit('Impossible de créer le répertoire temporaire.');
}

foreach ($clients as $client) {
    $clientId = (int)$client['id'];
    $clientBank = findPrimaryBankAccountForClient($pdo, $clientId);

    $operations = [];
    if ($documentKind === 'statement' || in_array('operations', $fields, true) || in_array('all', $fields, true)) {
        $sqlOps = "SELECT * FROM operations WHERE client_id = ?";
        $params = [$clientId];

        if ($dateFrom !== '') {
            $sqlOps .= " AND operation_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sqlOps .= " AND operation_date <= ?";
            $params[] = $dateTo;
        }
        $sqlOps .= " ORDER BY operation_date ASC, id ASC";

        $stmtOps = $pdo->prepare($sqlOps);
        $stmtOps->execute($params);
        $operations = $stmtOps->fetchAll(PDO::FETCH_ASSOC);
    }

    $totalCredit = 0.0;
    $totalDebit = 0.0;
    $clientAccountCode = (string)($client['generated_client_account'] ?? '');

    foreach ($operations as $op) {
        if (($op['credit_account_code'] ?? '') === $clientAccountCode) {
            $totalCredit += (float)($op['amount'] ?? 0);
        }
        if (($op['debit_account_code'] ?? '') === $clientAccountCode) {
            $totalDebit += (float)($op['amount'] ?? 0);
        }
    }

    $initialBalance = (float)($clientBank['initial_balance'] ?? 0);
    $finalBalance = $initialBalance + $totalCredit - $totalDebit;

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
        <?php if ($documentKind === 'statement'): ?>
            <h1>Relevé de compte client</h1>
            <table class="meta">
                <tr><td>Code client</td><td><?= e($client['client_code'] ?? '') ?></td></tr>
                <tr><td>Nom complet</td><td><?= e($client['full_name'] ?? '') ?></td></tr>
                <tr><td>Compte client</td><td><?= e($client['generated_client_account'] ?? '') ?></td></tr>
                <tr><td>Solde initial</td><td><?= number_format($initialBalance, 2, ',', ' ') ?></td></tr>
                <tr><td>Total crédits</td><td><?= number_format($totalCredit, 2, ',', ' ') ?></td></tr>
                <tr><td>Total débits</td><td><?= number_format($totalDebit, 2, ',', ' ') ?></td></tr>
                <tr><td>Solde final</td><td><?= number_format($finalBalance, 2, ',', ' ') ?></td></tr>
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
                        <tr><td colspan="6">Aucune opération.</td></tr>
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
        <?php else: ?>
            <h1>Fiche client</h1>

            <table class="meta">
                <?php if (in_array('all', $fields, true) || in_array('identity', $fields, true)): ?>
                    <tr><td>Code client</td><td><?= e($client['client_code'] ?? '') ?></td></tr>
                    <tr><td>Nom complet</td><td><?= e($client['full_name'] ?? '') ?></td></tr>
                    <tr><td>Prénom</td><td><?= e($client['first_name'] ?? '') ?></td></tr>
                    <tr><td>Nom</td><td><?= e($client['last_name'] ?? '') ?></td></tr>
                <?php endif; ?>

                <?php if (in_array('all', $fields, true) || in_array('contact', $fields, true)): ?>
                    <tr><td>Email</td><td><?= e($client['email'] ?? '') ?></td></tr>
                    <tr><td>Téléphone</td><td><?= e($client['phone'] ?? '') ?></td></tr>
                <?php endif; ?>

                <?php if (in_array('all', $fields, true) || in_array('countries', $fields, true)): ?>
                    <tr><td>Pays origine</td><td><?= e($client['country_origin'] ?? '') ?></td></tr>
                    <tr><td>Pays destination</td><td><?= e($client['country_destination'] ?? '') ?></td></tr>
                    <tr><td>Pays commercial</td><td><?= e($client['country_commercial'] ?? '') ?></td></tr>
                <?php endif; ?>

                <?php if (in_array('all', $fields, true) || in_array('finance', $fields, true)): ?>
                    <tr><td>Type client</td><td><?= e($client['client_type'] ?? '') ?></td></tr>
                    <tr><td>Statut client</td><td><?= e($client['client_status'] ?? '') ?></td></tr>
                    <tr><td>Devise</td><td><?= e($client['currency'] ?? '') ?></td></tr>
                    <tr><td>Compte interne lié</td><td><?= e((string)($client['initial_treasury_account_id'] ?? '')) ?></td></tr>
                <?php endif; ?>

                <?php if (in_array('all', $fields, true) || in_array('accounts', $fields, true)): ?>
                    <tr><td>Compte client généré</td><td><?= e($client['generated_client_account'] ?? '') ?></td></tr>
                    <tr><td>Compte bancaire lié</td><td><?= e($clientBank['account_number'] ?? '') ?></td></tr>
                    <tr><td>Banque</td><td><?= e($clientBank['bank_name'] ?? '') ?></td></tr>
                    <tr><td>Solde initial</td><td><?= number_format((float)($clientBank['initial_balance'] ?? 0), 2, ',', ' ') ?></td></tr>
                    <tr><td>Solde courant</td><td><?= number_format((float)($clientBank['balance'] ?? 0), 2, ',', ' ') ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if (in_array('all', $fields, true) || in_array('operations', $fields, true)): ?>
                <h2>Historique opérations</h2>
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
                            <tr><td colspan="6">Aucune opération.</td></tr>
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
            <?php endif; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $prefix = $documentKind === 'statement' ? 'releve_' : 'fiche_';
    $filename = $prefix . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($client['client_code'] ?? 'client')) . '.pdf';

    file_put_contents($tmpDir . DIRECTORY_SEPARATOR . $filename, $dompdf->output());
}

$zipPath = $tmpDir . DIRECTORY_SEPARATOR . 'exports_clients.zip';

if (!class_exists('ZipArchive')) {
    $pdfFiles = glob($tmpDir . DIRECTORY_SEPARATOR . '*.pdf');

    if (count($pdfFiles) === 1) {
        $singlePdf = $pdfFiles[0];
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($singlePdf) . '"');
        header('Content-Length: ' . filesize($singlePdf));
        readfile($singlePdf);

        foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*') as $file) {
            @unlink($file);
        }
        @rmdir($tmpDir);
        exit;
    }

    exit("L'extension PHP ZipArchive n'est pas disponible. Active l'extension zip dans php.ini pour exporter plusieurs PDF en archive.");
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit('Impossible de créer l’archive ZIP.');
}

foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*.pdf') as $pdfFile) {
    $zip->addFile($pdfFile, basename($pdfFile));
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="exports_clients.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);

foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*') as $file) {
    @unlink($file);
}
@rmdir($tmpDir);
exit;