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

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_preview_page');
} else {
    enforcePagePermission($pdo, 'imports_preview');
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

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
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

            <div class="card">
                <h3>Prévisualisation avant validation</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Règles intelligentes</span>
                        <strong><?= (int)$rowsWithRulesCount ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Warnings</span>
                        <strong><?= (int)$warningCount ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Infos</span>
                        <strong><?= (int)$infoCount ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Headers détectés</span>
                        <strong><?= count($rawHeaders) ?></strong>
                    </div>
                </div>

                <div class="dashboard-note" style="margin-top:18px;">
                    Vérifie les comptes débit/crédit, les règles et les messages de contrôle dans le tableau ci-dessous avant de confirmer.
                </div>
            </div>
        </div>

        <?php if ($suggestedMapping): ?>
            <div class="card" style="margin-top:20px;">
                <h3>Suggestions intelligentes de mapping</h3>
                <div class="sl-table-wrap">
                    <table class="sl-table">
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

        <div class="card" style="margin-top:20px;">
            <h3>Prévisualisation détaillée</h3>

            <div class="sl-table-wrap">
                <table class="sl-table">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Service</th>
                            <th>Montant</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Règles</th>
                            <th>Messages</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preparedRows as $row): ?>
                            <tr>
                                <td><?= (int)$row['line'] ?></td>
                                <td>
                                    <?php if ($row['status'] === 'ok'): ?>
                                        <span class="badge badge-success">OK</span>
                                    <?php elseif ($row['status'] === 'duplicate'): ?>
                                        <span class="badge badge-warning">Doublon</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Erreur</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string)($row['raw']['operation_date'] ?? '')) ?></td>
                                <td><?= e((string)($row['raw']['client_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['raw']['operation_type'] ?? '')) ?></td>
                                <td><?= e((string)($row['raw']['service'] ?? '')) ?></td>
                                <td><?= e((string)($row['raw']['amount'] ?? '')) ?></td>
                                <td><?= e((string)($row['preview']['debit_account_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['preview']['credit_account_code'] ?? '')) ?></td>
                                <td><?= e(sl_preview_render_rules_summary($row['rules_summary'] ?? [])) ?></td>
                                <td><?= e($row['messages'] ? implode(' | ', $row['messages']) : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>