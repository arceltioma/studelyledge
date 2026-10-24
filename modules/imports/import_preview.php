<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

require_once __DIR__ . '/../../includes/rules_engine.php';
require_once __DIR__ . '/../../includes/anomaly_engine.php';
require_once __DIR__ . '/../../includes/import_mapper.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'imports_preview');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Prévisualisation import intelligent';
$pageSubtitle = 'Validation métier et contrôle avant insertion définitive';

const SL_IMPORT_SESSION_KEY = 'studelyledger_operations_import_preview_v3';

if (empty($_SESSION[SL_IMPORT_SESSION_KEY]['rows']) || !is_array($_SESSION[SL_IMPORT_SESSION_KEY]['rows'])) {
    $_SESSION['error_message'] = 'Aucun import en attente.';
    header('Location: ' . APP_URL . 'modules/imports/import_upload.php');
    exit;
}

$importSession = $_SESSION[SL_IMPORT_SESSION_KEY];
$rawRows = $importSession['rows'];
$fileName = (string)($importSession['file_name'] ?? 'import.csv');
$finalMapping = $importSession['final_mapping'] ?? [];
$rawHeaders = $importSession['raw_headers'] ?? [];
$suggestedMapping = [];

if ($rawHeaders && function_exists('sl_import_mapper_suggest_mapping')) {
    $suggestedMapping = sl_import_mapper_suggest_mapping($rawHeaders);
}

if (!function_exists('sl_preview_find_operation_type')) {
    function sl_preview_find_operation_type(PDO $pdo, string $value): ?array
    {
        if ($value === '' || !tableExists($pdo, 'ref_operation_types')) {
            return null;
        }

        $normalized = sl_normalize_code($value);
        $rows = $pdo->query("
            SELECT id, code, label
            FROM ref_operation_types
            WHERE COALESCE(is_active,1)=1
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if ($normalized === sl_normalize_code((string)($row['code'] ?? ''))) {
                return $row;
            }
            if ($normalized === sl_normalize_code((string)($row['label'] ?? ''))) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('sl_preview_find_service')) {
    function sl_preview_find_service(PDO $pdo, string $value, ?int $operationTypeId = null): ?array
    {
        if ($value === '' || !tableExists($pdo, 'ref_services')) {
            return null;
        }

        $normalized = sl_normalize_code($value);
        $rows = $pdo->query("
            SELECT id, code, label, operation_type_id
            FROM ref_services
            WHERE COALESCE(is_active,1)=1
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if ($operationTypeId !== null && (int)($row['operation_type_id'] ?? 0) !== $operationTypeId) {
                continue;
            }

            if ($normalized === sl_normalize_code((string)($row['code'] ?? ''))) {
                return $row;
            }
            if ($normalized === sl_normalize_code((string)($row['label'] ?? ''))) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('sl_preview_find_client')) {
    function sl_preview_find_client(PDO $pdo, string $value): ?array
    {
        if ($value === '' || !tableExists($pdo, 'clients')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM clients
            WHERE COALESCE(client_code,'') = ?
               OR COALESCE(full_name,'') = ?
            LIMIT 1
        ");
        $stmt->execute([$value, $value]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($found) {
            return $found;
        }

        $all = $pdo->query("SELECT * FROM clients WHERE COALESCE(is_active,1)=1")->fetchAll(PDO::FETCH_ASSOC);
        $normalized = sl_normalize_code($value);

        foreach ($all as $client) {
            if ($normalized === sl_normalize_code((string)($client['client_code'] ?? ''))) {
                return $client;
            }
            if ($normalized === sl_normalize_code((string)($client['full_name'] ?? ''))) {
                return $client;
            }
        }

        return null;
    }
}

if (!function_exists('sl_preview_parse_amount')) {
    function sl_preview_parse_amount(string $value): float
    {
        $value = trim($value);
        $value = str_replace(["\xc2\xa0", ' '], '', $value);

        if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d{1,3}(,\d{3})*\.\d+$/', $value)) {
            $value = str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return (float)$value;
    }
}

if (!function_exists('sl_preview_render_rules_summary')) {
    function sl_preview_render_rules_summary(array $summary): string
    {
        $parts = [];

        if (!empty($summary['requires_client'])) {
            $parts[] = 'Client requis';
        }
        if (!empty($summary['requires_linked_bank'])) {
            $parts[] = 'Compte lié requis';
        }
        if (!empty($summary['requires_manual_accounts'])) {
            $parts[] = 'Comptes manuels requis';
        }
        if (!empty($summary['service_account_search_text'])) {
            $parts[] = 'Recherche 706: ' . $summary['service_account_search_text'];
        }

        return $parts ? implode(' | ', $parts) : '—';
    }
}

if (!function_exists('sl_preview_status_badge_class')) {
    function sl_preview_status_badge_class(string $status): string
    {
        return match ($status) {
            'ok' => 'badge-success',
            'duplicate' => 'badge-warning',
            'error' => 'badge-danger',
            default => 'badge-secondary',
        };
    }
}

$preparedRows = [];
$importableCount = 0;
$errorCount = 0;
$duplicateCount = 0;
$warningCount = 0;
$infoCount = 0;
$dangerCount = 0;
$rowsWithRulesCount = 0;

foreach ($rawRows as $index => $row) {
    $line = (int)($row['_line_number'] ?? ($index + 2));

    $status = 'ok';
    $messages = [];
    $preview = null;
    $payload = null;
    $rulesSummary = [];
    $anomalies = [];
    $client = null;
    $type = null;
    $service = null;

    try {
        $operationDate = trim((string)($row['operation_date'] ?? ''));
        $amountRaw = trim((string)($row['amount'] ?? ''));
        $currencyCode = trim((string)($row['currency_code'] ?? 'EUR'));
        $clientCode = trim((string)($row['client_code'] ?? ''));
        $operationTypeRaw = trim((string)($row['operation_type'] ?? ''));
        $serviceRaw = trim((string)($row['service'] ?? ''));
        $reference = trim((string)($row['reference'] ?? ''));
        $label = trim((string)($row['label'] ?? ''));
        $notes = trim((string)($row['notes'] ?? ''));
        $sourceAccountCode = trim((string)($row['source_account_code'] ?? ''));
        $destinationAccountCode = trim((string)($row['destination_account_code'] ?? ''));
        $linkedBankAccountId = trim((string)($row['linked_bank_account_id'] ?? ''));

        if ($operationDate === '') {
            throw new RuntimeException('Date absente.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $operationDate)) {
            throw new RuntimeException('Date invalide.');
        }

        $amount = sl_preview_parse_amount($amountRaw);
        if ($amount <= 0) {
            throw new RuntimeException('Montant invalide.');
        }

        $type = sl_preview_find_operation_type($pdo, $operationTypeRaw);
        if (!$type) {
            throw new RuntimeException('Type opération introuvable.');
        }

        $service = sl_preview_find_service($pdo, $serviceRaw, (int)$type['id']);
        if (!$service) {
            throw new RuntimeException('Service introuvable ou non lié au type.');
        }

        $typeCode = sl_normalize_code((string)($type['code'] ?? ''));
        $serviceCode = sl_normalize_code((string)($service['code'] ?? ''));

        if (!sl_service_allowed_for_type($typeCode, $serviceCode)) {
            throw new RuntimeException('Service incompatible avec le type.');
        }

        $requiresClient = !($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE');
        if ($requiresClient) {
            $client = sl_preview_find_client($pdo, $clientCode);
            if (!$client) {
                throw new RuntimeException('Client introuvable.');
            }
        }

        $manualCases = [
            'VIREMENT::INTERNE',
            'CA_DIVERS::CA_DIVERS',
            'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE',
            'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE',
            'CA_COURTAGE_PRET::CA_COURTAGE_PRET',
            'CA_LOGEMENT::CA_LOGEMENT'
        ];

        $manualKey = $typeCode . '::' . $serviceCode;
        $isManualCase = in_array($manualKey, $manualCases, true);

        if ($isManualCase && ($sourceAccountCode === '' || $destinationAccountCode === '')) {
            throw new RuntimeException('Compte source / destination obligatoire pour ce cas.');
        }

        $payload = [
            'operation_date' => $operationDate,
            'amount' => $amount,
            'currency_code' => $currencyCode !== '' ? $currencyCode : 'EUR',
            'client_id' => $client ? (int)$client['id'] : null,
            'service_id' => (int)$service['id'],
            'service_code' => $serviceCode,
            'operation_type_id' => (int)$type['id'],
            'operation_type_code' => $typeCode,
            'linked_bank_account_id' => $linkedBankAccountId !== '' ? (int)$linkedBankAccountId : null,
            'reference' => $reference !== '' ? $reference : null,
            'label' => $label !== '' ? $label : trim(($type['label'] ?? '') . ' - ' . ($service['label'] ?? '')),
            'notes' => $notes !== '' ? $notes : null,
            'source_type' => 'import',
            'operation_kind' => 'import',
            'source_treasury_code' => ($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE') ? $sourceAccountCode : '',
            'target_treasury_code' => ($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE') ? $destinationAccountCode : '',
            'manual_debit_account_code' => $isManualCase ? $sourceAccountCode : '',
            'manual_credit_account_code' => $isManualCase ? $destinationAccountCode : '',
        ];

        $rulesSummary = function_exists('sl_rules_build_summary')
            ? sl_rules_build_summary(
                $typeCode,
                $serviceCode,
                (string)($client['country_commercial'] ?? ''),
                (string)($client['country_destination'] ?? '')
            )
            : [];

        if (
            !empty($rulesSummary['service_account_search_text']) ||
            !empty($rulesSummary['requires_client']) ||
            !empty($rulesSummary['requires_linked_bank']) ||
            !empty($rulesSummary['requires_manual_accounts'])
        ) {
            $rowsWithRulesCount++;
        }

        $anomalies = function_exists('sl_detect_operation_anomalies')
            ? sl_detect_operation_anomalies(array_merge($payload, [
                'country_commercial' => (string)($client['country_commercial'] ?? ''),
                'country_destination' => (string)($client['country_destination'] ?? ''),
            ]))
            : [];

        foreach ($anomalies as $anomaly) {
            $level = strtolower((string)($anomaly['level'] ?? 'info'));
            $message = (string)($anomaly['message'] ?? '');

            if ($message !== '') {
                $messages[] = '[LOT2 ' . strtoupper($level) . '] ' . $message;
            }

            if ($level === 'warning') {
                $warningCount++;
            } elseif ($level === 'danger') {
                $dangerCount++;
            } elseif ($level === 'info') {
                $infoCount++;
            }
        }

        $preview = resolveAccountingOperationV2($pdo, $payload);

        if (!empty($preview['operation_hash'])) {
            $duplicate = sl_find_duplicate_operation($pdo, (string)$preview['operation_hash']);
            if ($duplicate) {
                $status = 'duplicate';
                $messages[] = 'Doublon détecté en base.';
                $duplicateCount++;
            }
        }

        if ($status === 'ok') {
            $importableCount++;
        }
    } catch (Throwable $e) {
        $status = 'error';
        $messages[] = $e->getMessage();
        $errorCount++;
    }

    $preparedRows[] = [
        'line' => $line,
        'raw' => $row,
        'payload' => $payload,
        'preview' => $preview,
        'status' => $status,
        'messages' => $messages,
        'rules_summary' => $rulesSummary,
        'anomalies' => $anomalies,
        'client_row' => $client,
        'type_row' => $type,
        'service_row' => $service,
    ];
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $action = trim((string)($_POST['import_action'] ?? ''));
        if ($action === 'cancel') {
            unset($_SESSION[SL_IMPORT_SESSION_KEY]);
            $_SESSION['success_message'] = 'Import annulé.';
            header('Location: ' . APP_URL . 'modules/imports/import_upload.php');
            exit;
        }

        if ($action !== 'confirm_import') {
            throw new RuntimeException('Action import inconnue.');
        }

        if ($importableCount <= 0) {
            throw new RuntimeException('Aucune ligne importable.');
        }

        $pdo->beginTransaction();
        $inserted = 0;

        foreach ($preparedRows as $row) {
            if ($row['status'] !== 'ok' || empty($row['payload'])) {
                continue;
            }

            $newId = createOperationWithAccountingV2($pdo, $row['payload']);
            $inserted++;

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'import_operation',
                    'imports',
                    'operation',
                    $newId,
                    'Import intelligent d’une opération'
                );
            }
        }

        if (function_exists('createNotification')) {
            createNotification(
                $pdo,
                'import_success',
                $inserted . ' opération(s) ont été importée(s) avec succès.',
                'success',
                APP_URL . 'modules/imports/import_journal.php',
                'import',
                null,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );
        }

        $pdo->commit();

        unset($_SESSION[SL_IMPORT_SESSION_KEY]);
        $_SESSION['success_message'] = $inserted . ' opération(s) importée(s) avec succès.';
        header('Location: ' . APP_URL . 'modules/operations/operations_list.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (function_exists('createNotification')) {
            createNotification(
                $pdo,
                'import_error',
                'Un import d’opérations a échoué : ' . $e->getMessage(),
                'warning',
                APP_URL . 'modules/imports/import_journal.php',
                'import',
                null,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );
        }

        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sip-shell {
                display: grid;
                gap: 20px;
            }

            .sip-grid-2 {
                display: grid;
                grid-template-columns: 1.05fr 0.95fr;
                gap: 20px;
            }

            @media (max-width: 1100px) {
                .sip-grid-2 {
                    grid-template-columns: 1fr;
                }
            }

            .sip-panel,
            .sip-kpi-card,
            .sip-toolbar {
                border-radius: 20px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            }

            .sip-panel,
            .sip-toolbar {
                padding: 18px;
            }

            .sip-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 1200px) {
                .sip-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .sip-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sip-kpi-card {
                padding: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .sip-kpi-label {
                display: block;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .sip-kpi-value {
                display: block;
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }

            .sip-kpi-meta {
                display: block;
                margin-top: 8px;
                color: #64748b;
                font-size: 12px;
            }

            .sip-note {
                margin-top: 14px;
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(99, 102, 241, 0.07);
                border: 1px solid rgba(99, 102, 241, 0.16);
                color: #3730a3;
                line-height: 1.55;
                font-size: 13px;
            }

            .sip-filter-bar {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                align-items: center;
                margin-top: 12px;
            }

            .sip-filter-bar input,
            .sip-filter-bar select {
                min-width: 180px;
            }

            .sip-chip {
                display: inline-flex;
                align-items: center;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                white-space: nowrap;
            }

            .sip-chip--ok {
                background: rgba(16, 185, 129, 0.12);
                color: #047857;
            }

            .sip-chip--dup {
                background: rgba(245, 158, 11, 0.12);
                color: #92400e;
            }

            .sip-chip--err {
                background: rgba(239, 68, 68, 0.10);
                color: #b91c1c;
            }

            .sip-chip--info {
                background: rgba(59, 130, 246, 0.10);
                color: #1d4ed8;
            }

            .sip-chip--neutral {
                background: rgba(148, 163, 184, 0.14);
                color: #475569;
            }

            .sip-table td,
            .sip-table th {
                vertical-align: top;
            }

            .sip-account {
                font-weight: 800;
                color: #0f172a;
            }

            .sip-sub {
                display: block;
                margin-top: 4px;
                font-size: 11px;
                color: #64748b;
            }

            .sip-msg {
                display: grid;
                gap: 6px;
            }

            .sip-msg-line {
                font-size: 12px;
                line-height: 1.45;
                color: #334155;
                padding: 6px 8px;
                border-radius: 10px;
                background: rgba(248, 250, 252, 0.95);
                border: 1px solid rgba(226, 232, 240, 0.9);
            }

            .sip-summary-list {
                display: grid;
                gap: 10px;
            }

            .sip-summary-item {
                display: flex;
                justify-content: space-between;
                gap: 14px;
                padding: 10px 12px;
                border-radius: 14px;
                background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
                border: 1px solid rgba(148, 163, 184, 0.14);
            }

            .sip-summary-item strong {
                color: #0f172a;
            }

            .sip-table-compact th,
            .sip-table-compact td {
                padding-top: 8px;
                padding-bottom: 8px;
            }
        </style>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="sip-shell">
            <div class="sip-toolbar">
                <h3>Prévisualisation intelligente : <?= e($fileName) ?></h3>
                <div class="sip-note">
                    Cette étape simule la résolution métier et comptable de chaque ligne importée.
                    Elle détecte les erreurs, doublons, règles spécifiques et anomalies avant insertion définitive.
                </div>

                <div class="sip-filter-bar">
                    <input type="text" id="sip-search" placeholder="Rechercher client, type, service, message...">
                    <select id="sip-status-filter">
                        <option value="">Tous les statuts</option>
                        <option value="ok">OK</option>
                        <option value="duplicate">Doublon</option>
                        <option value="error">Erreur</option>
                    </select>
                </div>
            </div>

            <div class="sip-kpi-grid">
                <div class="sip-kpi-card">
                    <span class="sip-kpi-label">Lignes importables</span>
                    <span class="sip-kpi-value"><?= (int)$importableCount ?></span>
                    <span class="sip-kpi-meta">Prêtes à être insérées</span>
                </div>

                <div class="sip-kpi-card">
                    <span class="sip-kpi-label">Lignes bloquées</span>
                    <span class="sip-kpi-value"><?= (int)($errorCount + $duplicateCount) ?></span>
                    <span class="sip-kpi-meta">Erreurs + doublons</span>
                </div>

                <div class="sip-kpi-card">
                    <span class="sip-kpi-label">Warnings</span>
                    <span class="sip-kpi-value"><?= (int)$warningCount ?></span>
                    <span class="sip-kpi-meta">Alertes métier</span>
                </div>

                <div class="sip-kpi-card">
                    <span class="sip-kpi-label">Règles activées</span>
                    <span class="sip-kpi-value"><?= (int)$rowsWithRulesCount ?></span>
                    <span class="sip-kpi-meta">Lignes avec logique avancée</span>
                </div>
            </div>

            <div class="sip-grid-2">
                <div class="form-card">
                    <h3>Validation de l’import</h3>
                    <p class="muted">Contrôle final avant insertion des lignes valides.</p>

                    <form method="POST">
                        <?= csrf_input() ?>

                        <div class="sl-data-list">
                            <div class="sl-data-list__row">
                                <span>Fichier</span>
                                <strong><?= e($fileName) ?></strong>
                            </div>
                            <div class="sl-data-list__row">
                                <span>Champs mappés</span>
                                <strong><?= count(array_filter($finalMapping)) ?></strong>
                            </div>
                            <div class="sl-data-list__row">
                                <span>Lignes importables</span>
                                <strong><?= (int)$importableCount ?></strong>
                            </div>
                            <div class="sl-data-list__row">
                                <span>Lignes bloquées</span>
                                <strong><?= (int)($errorCount + $duplicateCount) ?></strong>
                            </div>
                        </div>

                        <div class="btn-group" style="margin-top:20px;">
                            <button type="submit" name="import_action" value="confirm_import" class="btn btn-success" <?= $importableCount <= 0 ? 'disabled' : '' ?>>
                                Confirmer l’import
                            </button>
                            <button type="submit" name="import_action" value="cancel" class="btn btn-outline">
                                Annuler
                            </button>
                            <a href="<?= e(APP_URL) ?>modules/imports/import_mapping.php" class="btn btn-secondary">Revoir le mapping</a>
                        </div>
                    </form>
                </div>

                <div class="sip-panel">
                    <h3>Résumé de contrôle</h3>

                    <div class="sip-summary-list">
                        <div class="sip-summary-item">
                            <span>Fichier analysé</span>
                            <strong><?= e($fileName) ?></strong>
                        </div>
                        <div class="sip-summary-item">
                            <span>Suggestions de mapping</span>
                            <strong><?= count($suggestedMapping) ?></strong>
                        </div>
                        <div class="sip-summary-item">
                            <span>Warnings</span>
                            <strong><?= (int)$warningCount ?></strong>
                        </div>
                        <div class="sip-summary-item">
                            <span>Infos</span>
                            <strong><?= (int)$infoCount ?></strong>
                        </div>
                        <div class="sip-summary-item">
                            <span>Dangers</span>
                            <strong><?= (int)$dangerCount ?></strong>
                        </div>
                        <div class="sip-summary-item">
                            <span>Headers détectés</span>
                            <strong><?= count($rawHeaders) ?></strong>
                        </div>
                    </div>

                    <div class="sip-note">
                        Vérifie particulièrement les comptes débit / crédit proposés,
                        les doublons détectés, les cas manuels et les lignes avec messages LOT2.
                    </div>
                </div>
            </div>

            <?php if ($suggestedMapping): ?>
                <div class="sip-panel">
                    <h3>Suggestions intelligentes de mapping</h3>

                    <div class="table-responsive">
                        <table class="modern-table sip-table-compact">
                            <thead>
                                <tr>
                                    <th>Colonne source</th>
                                    <th>Champ suggéré</th>
                                    <th>Score</th>
                                    <th>Match</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suggestedMapping as $sourceHeader => $guess): ?>
                                    <tr>
                                        <td><?= e((string)$sourceHeader) ?></td>
                                        <td><?= e((string)($guess['field'] ?? '')) ?></td>
                                        <td><?= e((string)($guess['score'] ?? '0')) ?>%</td>
                                        <td><?= e((string)($guess['matched_on'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="sip-panel">
                <h3>Prévisualisation détaillée</h3>

                <div class="table-responsive">
                    <table class="modern-table sip-table" id="sip-preview-table">
                        <thead>
                            <tr>
                                <th>Ligne</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Type / Service</th>
                                <th>Montant</th>
                                <th>Débit / Crédit</th>
                                <th>Règles</th>
                                <th>Messages</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preparedRows as $row): ?>
                                <?php
                                $status = (string)($row['status'] ?? 'error');
                                $searchBlob = mb_strtolower(trim(
                                    (string)($row['raw']['client_code'] ?? '') . ' ' .
                                    (string)($row['raw']['operation_type'] ?? '') . ' ' .
                                    (string)($row['raw']['service'] ?? '') . ' ' .
                                    implode(' ', $row['messages'] ?? [])
                                ), 'UTF-8');
                                ?>
                                <tr
                                    data-status="<?= e($status) ?>"
                                    data-search="<?= e($searchBlob) ?>"
                                >
                                    <td><?= (int)$row['line'] ?></td>

                                    <td>
                                        <?php if ($status === 'ok'): ?>
                                            <span class="sip-chip sip-chip--ok">OK</span>
                                        <?php elseif ($status === 'duplicate'): ?>
                                            <span class="sip-chip sip-chip--dup">Doublon</span>
                                        <?php else: ?>
                                            <span class="sip-chip sip-chip--err">Erreur</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= e((string)($row['raw']['operation_date'] ?? '')) ?></td>

                                    <td>
                                        <span class="sip-account"><?= e((string)($row['raw']['client_code'] ?? '')) ?></span>
                                        <span class="sip-sub"><?= e((string)($row['client_row']['full_name'] ?? '')) ?></span>
                                    </td>

                                    <td>
                                        <span class="sip-account"><?= e((string)($row['raw']['operation_type'] ?? '')) ?></span>
                                        <span class="sip-sub"><?= e((string)($row['raw']['service'] ?? '')) ?></span>
                                    </td>

                                    <td>
                                        <span class="sip-account"><?= e((string)($row['raw']['amount'] ?? '')) ?></span>
                                        <span class="sip-sub"><?= e((string)($row['raw']['currency_code'] ?? 'EUR')) ?></span>
                                    </td>

                                    <td>
                                        <span class="sip-account"><?= e((string)($row['preview']['debit_account_code'] ?? '')) ?></span>
                                        <span class="sip-sub">→ <?= e((string)($row['preview']['credit_account_code'] ?? '')) ?></span>
                                    </td>

                                    <td><?= e(sl_preview_render_rules_summary($row['rules_summary'] ?? [])) ?></td>

                                    <td>
                                        <?php if (!empty($row['messages'])): ?>
                                            <div class="sip-msg">
                                                <?php foreach ($row['messages'] as $message): ?>
                                                    <div class="sip-msg-line"><?= e((string)$message) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="sip-chip sip-chip--neutral">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('sip-search');
            const statusFilter = document.getElementById('sip-status-filter');
            const table = document.getElementById('sip-preview-table');

            if (!table) {
                return;
            }

            const rows = Array.from(table.querySelectorAll('tbody tr'));

            function normalize(value) {
                return (value || '')
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            }

            function applyFilters() {
                const searchValue = normalize(searchInput ? searchInput.value : '');
                const statusValue = normalize(statusFilter ? statusFilter.value : '');

                rows.forEach(function (row) {
                    const rowSearch = normalize(row.getAttribute('data-search') || '');
                    const rowStatus = normalize(row.getAttribute('data-status') || '');

                    let visible = true;

                    if (searchValue !== '' && !rowSearch.includes(searchValue)) {
                        visible = false;
                    }

                    if (statusValue !== '' && rowStatus !== statusValue) {
                        visible = false;
                    }

                    row.style.display = visible ? '' : 'none';
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            if (statusFilter) {
                statusFilter.addEventListener('change', applyFilters);
            }
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>