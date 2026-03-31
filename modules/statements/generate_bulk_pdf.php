<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'statements_export_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

if (!function_exists('pdfSafeText')) {
    function pdfSafeText(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pdfMoney')) {
    function pdfMoney(float $value, string $currency = 'EUR'): string
    {
        return number_format($value, 2, ',', ' ') . ' ' . $currency;
    }
}

if (!function_exists('pdfDateFr')) {
    function pdfDateFr(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        $ts = strtotime($date);
        if (!$ts) {
            return (string)$date;
        }

        return date('d/m/Y', $ts);
    }
}

if (!function_exists('pdfLoadLogoDataUri')) {
    function pdfLoadLogoDataUri(): string
    {
        $rootPath = defined('APP_ROOT')
            ? APP_ROOT
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        $candidates = [
            $rootPath . 'assets/img/logo.png',
            $rootPath . 'assets/img/logo-sidebar.png',
            '/mnt/data/logo.png',
            '/mnt/data/Logo.jpeg',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };

                $content = @file_get_contents($path);
                if ($content !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($content);
                }
            }
        }

        return '';
    }
}

if (!function_exists('pdfCleanOutputBuffers')) {
    function pdfCleanOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}

if (!function_exists('pdfSendBinaryFile')) {
    function pdfSendBinaryFile(string $filePath, string $contentType, string $downloadName): void
    {
        if (!is_file($filePath)) {
            http_response_code(500);
            exit('Fichier de sortie introuvable.');
        }

        pdfCleanOutputBuffers();

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . (string)filesize($filePath));

        readfile($filePath);
        exit;
    }
}

if (!function_exists('pdfFieldEnabled')) {
    function pdfFieldEnabled(array $fields, string $field): bool
    {
        return in_array('all', $fields, true) || in_array($field, $fields, true);
    }
}

if (!function_exists('findClientStatementOperations')) {
    function findClientStatementOperations(PDO $pdo, int $clientId, string $dateFrom = '', string $dateTo = ''): array
    {
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

        $sql .= " ORDER BY o.operation_date ASC, o.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('findClientOpeningBalanceBeforeDate')) {
    function findClientOpeningBalanceBeforeDate(PDO $pdo, int $clientId, string $accountNumber, float $initialBalance, string $dateFrom = ''): float
    {
        if ($dateFrom === '' || $accountNumber === '') {
            return $initialBalance;
        }

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
            FROM operations
            WHERE client_id = ?
              AND operation_date < ?
        ");
        $stmt->execute([$accountNumber, $accountNumber, $clientId, $dateFrom]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalDebit = (float)($row['total_debit'] ?? 0);
        $totalCredit = (float)($row['total_credit'] ?? 0);

        return $initialBalance - $totalDebit + $totalCredit;
    }
}

if (!function_exists('classifyClientOperationAmounts')) {
    function classifyClientOperationAmounts(array $operation, string $clientAccountNumber): array
    {
        $amount = (float)($operation['amount'] ?? 0);
        $debitCode = (string)($operation['debit_account_code'] ?? '');
        $creditCode = (string)($operation['credit_account_code'] ?? '');
        $legacyType = strtolower(trim((string)($operation['operation_type'] ?? '')));

        $debit = 0.0;
        $credit = 0.0;

        if ($debitCode !== '' || $creditCode !== '') {
            if ($debitCode === $clientAccountNumber) {
                $debit = $amount;
            } elseif ($creditCode === $clientAccountNumber) {
                $credit = $amount;
            }
        } else {
            if ($legacyType === 'debit') {
                $debit = $amount;
            } elseif ($legacyType === 'credit') {
                $credit = $amount;
            }
        }

        return [
            'debit' => $debit,
            'credit' => $credit,
        ];
    }
}

if (!function_exists('renderClientProfilePdfHtml')) {
    function renderClientProfilePdfHtml(array $client, ?array $clientBank, ?array $treasury, string $logoDataUri, array $fields): string
    {
        $currency = (string)($client['currency'] ?? 'EUR');
        $initialBalance = (float)($clientBank['initial_balance'] ?? 0);
        $currentBalance = (float)($clientBank['balance'] ?? 0);
        $postalAddress = $client['postal_address'] ?? '';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        .header { border-bottom: 3px solid #1d4ed8; padding-bottom: 14px; margin-bottom: 18px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { width: 120px; }
        .brand-title { font-size: 24px; font-weight: 700; color: #1d2549; margin: 0 0 4px 0; }
        .brand-subtitle { font-size: 12px; color: #64748b; margin: 0; }
        .doc-badge { display: inline-block; padding: 6px 12px; background: #eff6ff; color: #1d4ed8; border-radius: 999px; font-weight: 700; font-size: 11px; }
        .hero { background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%); border: 1px solid #dbeafe; border-radius: 16px; padding: 18px; margin-bottom: 18px; }
        .hero-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; }
        .hero-sub { font-size: 12px; color: #64748b; margin: 0; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 12px; margin: 0 -12px 8px -12px; }
        .card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; }
        .card-title { font-size: 13px; font-weight: 700; color: #1d2549; margin-bottom: 10px; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 6px 0; border-bottom: 1px solid #eef2f7; }
        .meta-table tr:last-child td { border-bottom: none; }
        .label { color: #64748b; width: 42%; font-weight: 600; }
        .value { color: #111827; font-weight: 700; text-align: right; }
        .kpi { font-size: 24px; font-weight: 800; color: #1d2549; margin: 2px 0; }
        .kpi-sub { font-size: 11px; color: #64748b; }
        .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #64748b; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:140px;">
                    <?php if ($logoDataUri !== ''): ?>
                        <img src="<?= $logoDataUri ?>" class="logo" alt="Logo">
                    <?php endif; ?>
                </td>
                <td>
                    <div class="brand-title">Studely Ledger</div>
                    <p class="brand-subtitle">Fiche client premium</p>
                </td>
                <td style="text-align:right;">
                    <span class="doc-badge">FICHE CLIENT</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="hero">
        <div class="hero-title"><?= pdfSafeText($client['full_name'] ?? trim((string)($client['first_name'] ?? '') . ' ' . (string)($client['last_name'] ?? ''))) ?></div>
        <p class="hero-sub">
            Code client : <strong><?= pdfSafeText($client['client_code'] ?? '') ?></strong>
            <?php if (pdfFieldEnabled($fields, 'client_account')): ?>
                • Compte client : <strong><?= pdfSafeText($client['generated_client_account'] ?? '') ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <table class="grid">
        <?php if (pdfFieldEnabled($fields, 'identity') || pdfFieldEnabled($fields, 'contact')): ?>
        <tr>
            <td class="card" style="width:50%;">
                <div class="card-title">Identité & contact</div>
                <table class="meta-table">
                    <?php if (pdfFieldEnabled($fields, 'identity')): ?>
                        <tr><td class="label">Prénom</td><td class="value"><?= pdfSafeText($client['first_name'] ?? '') ?></td></tr>
                        <tr><td class="label">Nom</td><td class="value"><?= pdfSafeText($client['last_name'] ?? '') ?></td></tr>
                        <tr><td class="label">Type client</td><td class="value"><?= pdfSafeText($client['client_type'] ?? '—') ?></td></tr>
                        <tr><td class="label">Statut client</td><td class="value"><?= pdfSafeText($client['client_status'] ?? '—') ?></td></tr>
                    <?php endif; ?>

                    <?php if (pdfFieldEnabled($fields, 'contact')): ?>
                        <tr><td class="label">Email</td><td class="value"><?= pdfSafeText($client['email'] ?? '—') ?></td></tr>
                        <tr><td class="label">Téléphone</td><td class="value"><?= pdfSafeText($client['phone'] ?? '—') ?></td></tr>
                        <tr><td class="label">Adresse postale</td><td class="value"><?= nl2br(pdfSafeText($postalAddress !== '' ? $postalAddress : '—')) ?></td></tr>
                    <?php endif; ?>
                </table>
            </td>

            <td class="card" style="width:50%;">
                <div class="card-title">Cycle & rattachement</div>
                <table class="meta-table">
                    <?php if (pdfFieldEnabled($fields, 'countries')): ?>
                        <tr><td class="label">Pays d’origine</td><td class="value"><?= pdfSafeText($client['country_origin'] ?? '—') ?></td></tr>
                        <tr><td class="label">Pays de destination</td><td class="value"><?= pdfSafeText($client['country_destination'] ?? '—') ?></td></tr>
                        <tr><td class="label">Pays commercial</td><td class="value"><?= pdfSafeText($client['country_commercial'] ?? '—') ?></td></tr>
                    <?php endif; ?>

                    <?php if (pdfFieldEnabled($fields, 'treasury_account')): ?>
                        <tr><td class="label">Compte 512 lié</td><td class="value"><?= pdfSafeText(trim((string)($treasury['account_code'] ?? '') . ' - ' . (string)($treasury['account_label'] ?? ''))) ?></td></tr>
                    <?php endif; ?>

                    <?php if (pdfFieldEnabled($fields, 'currency')): ?>
                        <tr><td class="label">Devise</td><td class="value"><?= pdfSafeText($currency) ?></td></tr>
                    <?php endif; ?>

                    <tr><td class="label">Date édition</td><td class="value"><?= pdfDateFr(date('Y-m-d')) ?></td></tr>
                </table>
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <?php if (pdfFieldEnabled($fields, 'balances')): ?>
    <table class="grid">
        <tr>
            <td class="card" style="width:50%; text-align:center;">
                <div class="card-title">Solde initial</div>
                <div class="kpi"><?= pdfMoney($initialBalance, $currency) ?></div>
                <div class="kpi-sub">Base d’ouverture du compte 411</div>
            </td>
            <td class="card" style="width:50%; text-align:center;">
                <div class="card-title">Solde courant</div>
                <div class="kpi"><?= pdfMoney($currentBalance, $currency) ?></div>
                <div class="kpi-sub">Valeur dynamique actualisée</div>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <div class="footer">
        Document généré le <?= pdfDateFr(date('Y-m-d')) ?> • Studely Ledger
    </div>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('renderClientStatementPdfHtml')) {
    function renderClientStatementPdfHtml(
        PDO $pdo,
        array $client,
        ?array $clientBank,
        array $operations,
        string $dateFrom,
        string $dateTo,
        string $logoDataUri
    ): string {
        $currency = (string)($client['currency'] ?? 'EUR');
        $clientAccountNumber = (string)($client['generated_client_account'] ?? ($clientBank['account_number'] ?? ''));
        $initialBalance = (float)($clientBank['initial_balance'] ?? 0);
        $openingBalance = findClientOpeningBalanceBeforeDate(
            $pdo,
            (int)$client['id'],
            $clientAccountNumber,
            $initialBalance,
            $dateFrom
        );

        $runningBalance = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $statementRows = [];

        foreach ($operations as $operation) {
            $amounts = classifyClientOperationAmounts($operation, $clientAccountNumber);
            $debit = (float)$amounts['debit'];
            $credit = (float)$amounts['credit'];

            $runningBalance = $runningBalance - $debit + $credit;
            $totalDebit += $debit;
            $totalCredit += $credit;

            $statementRows[] = [
                'operation_date' => $operation['operation_date'] ?? '',
                'label' => $operation['label'] ?? '',
                'reference' => $operation['reference'] ?? '',
                'debit_account_code' => $operation['debit_account_code'] ?? '',
                'credit_account_code' => $operation['credit_account_code'] ?? '',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        $closingBalance = $runningBalance;
        $periodText = ($dateFrom !== '' || $dateTo !== '')
            ? ('Période : ' . ($dateFrom !== '' ? pdfDateFr($dateFrom) : 'Origine') . ' au ' . ($dateTo !== '' ? pdfDateFr($dateTo) : pdfDateFr(date('Y-m-d'))))
            : 'Période : historique complet';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 22px 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; margin: 0; }
        .header { border-bottom: 3px solid #0f766e; padding-bottom: 12px; margin-bottom: 14px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { width: 118px; }
        .brand-title { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
        .brand-subtitle { font-size: 12px; color: #64748b; margin: 0; }
        .doc-badge { display: inline-block; padding: 6px 12px; background: #ecfeff; color: #0f766e; border-radius: 999px; font-weight: 700; font-size: 11px; }
        .hero { background: linear-gradient(135deg, #f0fdfa 0%, #eff6ff 100%); border: 1px solid #c7f9f1; border-radius: 14px; padding: 14px 16px; margin-bottom: 14px; }
        .hero-title { font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 6px 0; }
        .hero-sub { font-size: 11px; color: #64748b; margin: 0; }
        .mini-grid { width: 100%; border-collapse: separate; border-spacing: 10px; margin: 0 -10px 8px -10px; }
        .mini-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; text-align: center; }
        .mini-title { font-size: 11px; color: #64748b; margin-bottom: 6px; font-weight: 700; }
        .mini-kpi { font-size: 18px; font-weight: 800; color: #111827; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta-table td { padding: 5px 0; }
        .meta-left { width: 52%; color: #64748b; font-weight: 600; }
        .meta-right { text-align: right; color: #111827; font-weight: 700; }
        .statement-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .statement-table th { background: #eff6ff; color: #1d2549; border: 1px solid #dbeafe; padding: 8px 6px; font-size: 10px; text-transform: uppercase; }
        .statement-table td { border: 1px solid #e5e7eb; padding: 7px 6px; vertical-align: top; }
        .right { text-align: right; }
        .debit { color: #b42318; font-weight: 700; }
        .credit { color: #117a4f; font-weight: 700; }
        .balance { color: #1d2549; font-weight: 700; }
        .footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid #e5e7eb; color: #64748b; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:140px;">
                    <?php if ($logoDataUri !== ''): ?>
                        <img src="<?= $logoDataUri ?>" class="logo" alt="Logo">
                    <?php endif; ?>
                </td>
                <td>
                    <div class="brand-title">Studely Ledger</div>
                    <p class="brand-subtitle">Relevé de compte client</p>
                </td>
                <td style="text-align:right;">
                    <span class="doc-badge">RELEVÉ DE COMPTE</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="hero">
        <div class="hero-title"><?= pdfSafeText($client['full_name'] ?? trim((string)($client['first_name'] ?? '') . ' ' . (string)($client['last_name'] ?? ''))) ?></div>
        <p class="hero-sub">
            Client : <strong><?= pdfSafeText($client['client_code'] ?? '') ?></strong>
            • Compte : <strong><?= pdfSafeText($clientAccountNumber) ?></strong>
            • <?= pdfSafeText($periodText) ?>
        </p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-left">Pays commercial</td>
            <td class="meta-right"><?= pdfSafeText($client['country_commercial'] ?? '—') ?></td>
        </tr>
        <?php if (!empty($client['postal_address'])): ?>
        <tr>
            <td class="meta-left">Adresse postale</td>
            <td class="meta-right"><?= pdfSafeText($client['postal_address']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td class="meta-left">Devise</td>
            <td class="meta-right"><?= pdfSafeText($currency) ?></td>
        </tr>
        <tr>
            <td class="meta-left">Date d’édition</td>
            <td class="meta-right"><?= pdfDateFr(date('Y-m-d')) ?></td>
        </tr>
    </table>

    <table class="mini-grid">
        <tr>
            <td class="mini-card">
                <div class="mini-title">Solde initial période</div>
                <div class="mini-kpi"><?= pdfMoney($openingBalance, $currency) ?></div>
            </td>
            <td class="mini-card">
                <div class="mini-title">Total débits</div>
                <div class="mini-kpi debit"><?= pdfMoney($totalDebit, $currency) ?></div>
            </td>
            <td class="mini-card">
                <div class="mini-title">Total crédits</div>
                <div class="mini-kpi credit"><?= pdfMoney($totalCredit, $currency) ?></div>
            </td>
            <td class="mini-card">
                <div class="mini-title">Solde final</div>
                <div class="mini-kpi balance"><?= pdfMoney($closingBalance, $currency) ?></div>
            </td>
        </tr>
    </table>

    <table class="statement-table">
        <thead>
            <tr>
                <th style="width:62px;">Date</th>
                <th>Libellé</th>
                <th style="width:90px;">Référence</th>
                <th style="width:72px;">Débit</th>
                <th style="width:72px;">Crédit</th>
                <th style="width:82px;">Solde</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$statementRows): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Aucune opération sur la période sélectionnée.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($statementRows as $row): ?>
                    <tr>
                        <td><?= pdfDateFr($row['operation_date']) ?></td>
                        <td>
                            <strong><?= pdfSafeText($row['label'] ?: 'Opération') ?></strong><br>
                            <span style="color:#64748b; font-size:10px;">
                                D: <?= pdfSafeText($row['debit_account_code']) ?> • C: <?= pdfSafeText($row['credit_account_code']) ?>
                            </span>
                        </td>
                        <td><?= pdfSafeText($row['reference'] ?: '—') ?></td>
                        <td class="right debit"><?= $row['debit'] > 0 ? pdfMoney($row['debit'], $currency) : '—' ?></td>
                        <td class="right credit"><?= $row['credit'] > 0 ? pdfMoney($row['credit'], $currency) : '—' ?></td>
                        <td class="right balance"><?= pdfMoney($row['balance'], $currency) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Relevé généré automatiquement par Studely Ledger • <?= pdfDateFr(date('Y-m-d')) ?>
    </div>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }
}

$singleMode = isset($_GET['single']) && (int)($_GET['single']) === 1;

if ($singleMode) {
    $clientIds = isset($_GET['client_id']) ? [(int)$_GET['client_id']] : [];
    $documentKind = trim((string)($_GET['document_kind'] ?? 'statement'));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $fields = $_GET['fields'] ?? ['all'];
} else {
    $clientIds = $_POST['client_ids'] ?? $_POST['clients'] ?? [];
    $documentKind = trim((string)($_POST['document_kind'] ?? 'statement'));
    $dateFrom = trim((string)($_POST['date_from'] ?? ''));
    $dateTo = trim((string)($_POST['date_to'] ?? ''));
    $fields = $_POST['fields'] ?? ['all'];
}

if (!is_array($clientIds)) {
    $clientIds = [$clientIds];
}
if (!is_array($fields) || !$fields) {
    $fields = ['all'];
}

$clientIds = array_values(array_filter(array_map('intval', $clientIds), fn ($v) => $v > 0));

if (!$clientIds) {
    exit('Aucun client sélectionné.');
}

$allowedDocumentKinds = ['statement', 'profile'];
if (!in_array($documentKind, $allowedDocumentKinds, true)) {
    $documentKind = 'statement';
}

$placeholders = implode(',', array_fill(0, count($clientIds), '?'));

$clientSelect = [
    'c.*',
    'ta.account_code AS treasury_account_code',
    'ta.account_label AS treasury_account_label'
];

$stmtClients = $pdo->prepare("
    SELECT " . implode(', ', $clientSelect) . "
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    WHERE c.id IN ({$placeholders})
    ORDER BY c.client_code ASC
");
$stmtClients->execute($clientIds);
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

if (!$clients) {
    exit('Aucun client trouvé.');
}

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'studelyledger_export_' . uniqid('', true);
if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
    exit('Impossible de créer le répertoire temporaire.');
}

$logoDataUri = pdfLoadLogoDataUri();
$pdfFiles = [];

foreach ($clients as $client) {
    $clientId = (int)$client['id'];
    $clientBank = findPrimaryBankAccountForClient($pdo, $clientId);
    $treasury = !empty($client['initial_treasury_account_id'])
        ? findTreasuryAccountById($pdo, (int)$client['initial_treasury_account_id'])
        : null;

    $operations = [];
    if ($documentKind === 'statement') {
        $operations = findClientStatementOperations($pdo, $clientId, $dateFrom, $dateTo);
    }

    $dompdf = new Dompdf($options);

    if ($documentKind === 'profile') {
        $html = renderClientProfilePdfHtml($client, $clientBank, $treasury, $logoDataUri, $fields);
    } else {
        $html = renderClientStatementPdfHtml($pdo, $client, $clientBank, $operations, $dateFrom, $dateTo, $logoDataUri);
    }

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $prefix = $documentKind === 'statement' ? 'releve_' : 'fiche_';
    $filename = $prefix . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($client['client_code'] ?? 'client')) . '.pdf';
    $targetPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;

    file_put_contents($targetPath, $dompdf->output());
    $pdfFiles[] = $targetPath;
}

if (count($pdfFiles) === 1) {
    $singlePdf = $pdfFiles[0];
    pdfSendBinaryFile($singlePdf, 'application/pdf', basename($singlePdf));
}

if (!class_exists('ZipArchive')) {
    exit("L'extension PHP ZipArchive n'est pas disponible. Active l'extension zip dans php.ini pour exporter plusieurs PDF.");
}

$zipPath = $tmpDir . DIRECTORY_SEPARATOR . 'exports_clients.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit('Impossible de créer l’archive ZIP.');
}

foreach ($pdfFiles as $pdfFile) {
    $zip->addFile($pdfFile, basename($pdfFile));
}
$zip->close();

pdfSendBinaryFile($zipPath, 'application/zip', 'exports_clients.zip');