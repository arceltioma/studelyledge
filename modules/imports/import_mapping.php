<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'imports_preview');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Mapping manuel assisté';
$pageSubtitle = 'Vérifie et corrige le rapprochement des colonnes avant la prévisualisation';

const SL_IMPORT_SESSION_KEY = 'studelyledger_operations_import_preview_v3';

if (!function_exists('sl_import_mapping_create_notification')) {
    function sl_import_mapping_create_notification(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $linkUrl = null,
        ?string $entityType = 'import',
        ?int $entityId = null,
        ?int $createdBy = null
    ): void {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $allowedLevels = ['info', 'success', 'warning', 'danger'];
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'info';
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => 0,
            'created_by' => $createdBy,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (columnExists($pdo, 'notifications', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NULL';
        }

        if (!$columns) {
            return;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if (empty($_SESSION[SL_IMPORT_SESSION_KEY]['raw_rows']) || !is_array($_SESSION[SL_IMPORT_SESSION_KEY]['raw_rows'])) {
    $_SESSION['error_message'] = 'Aucun import en attente.';
    header('Location: ' . APP_URL . 'modules/imports/import_upload.php');
    exit;
}

$importSession = &$_SESSION[SL_IMPORT_SESSION_KEY];
$fileName = (string)($importSession['file_name'] ?? 'import.csv');
$rawHeaders = $importSession['raw_headers'] ?? [];
$suggestedMapping = $importSession['suggested_mapping'] ?? [];
$userId = (int)($_SESSION['user_id'] ?? 0);
$rawRows = $importSession['raw_rows'] ?? [];

$targetFields = [
    '' => '— Ignorer —',
    'operation_date' => 'Date opération',
    'amount' => 'Montant',
    'currency_code' => 'Devise',
    'client_code' => 'Client',
    'operation_type' => 'Type opération',
    'service' => 'Service',
    'reference' => 'Référence',
    'label' => 'Libellé',
    'notes' => 'Notes',
    'source_account_code' => 'Compte source',
    'destination_account_code' => 'Compte destination',
    'linked_bank_account_id' => 'Compte bancaire lié',
];

$targetFieldMeta = [
    'operation_date' => [
        'required' => true,
        'description' => 'Date comptable de l’opération',
    ],
    'amount' => [
        'required' => true,
        'description' => 'Montant de l’opération',
    ],
    'currency_code' => [
        'required' => false,
        'description' => 'Devise, ex. EUR',
    ],
    'client_code' => [
        'required' => false,
        'description' => 'Code client Studely Ledger',
    ],
    'operation_type' => [
        'required' => true,
        'description' => 'Nature métier de l’opération',
    ],
    'service' => [
        'required' => true,
        'description' => 'Service lié à l’opération',
    ],
    'reference' => [
        'required' => false,
        'description' => 'Référence externe ou interne',
    ],
    'label' => [
        'required' => false,
        'description' => 'Libellé lisible',
    ],
    'notes' => [
        'required' => false,
        'description' => 'Commentaire libre',
    ],
    'source_account_code' => [
        'required' => false,
        'description' => 'Compte débité si fourni',
    ],
    'destination_account_code' => [
        'required' => false,
        'description' => 'Compte crédité si fourni',
    ],
    'linked_bank_account_id' => [
        'required' => false,
        'description' => 'Compte bancaire lié si disponible',
    ],
];

$errorMessage = '';

$currentMapping = [];
foreach ($rawHeaders as $header) {
    $currentMapping[$header] = (string)($suggestedMapping[$header] ?? '');
}

if (!empty($importSession['final_mapping']) && is_array($importSession['final_mapping'])) {
    foreach ($rawHeaders as $header) {
        if (array_key_exists($header, $importSession['final_mapping'])) {
            $currentMapping[$header] = (string)$importSession['final_mapping'][$header];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $mapping = $_POST['mapping'] ?? [];
        if (!is_array($mapping)) {
            throw new RuntimeException('Mapping invalide.');
        }

        $sanitized = [];
        foreach ($rawHeaders as $header) {
            $value = (string)($mapping[$header] ?? '');
            if (!array_key_exists($value, $targetFields)) {
                $value = '';
            }
            $sanitized[$header] = $value;
        }

        $required = ['operation_date', 'amount', 'operation_type', 'service'];
        $mappedTargets = array_values(array_filter($sanitized, static fn($v) => $v !== ''));

        foreach ($required as $requiredField) {
            if (!in_array($requiredField, $mappedTargets, true)) {
                throw new RuntimeException('Le champ obligatoire "' . $targetFields[$requiredField] . '" doit être mappé.');
            }
        }

        $mappedRows = [];
        foreach ($importSession['raw_rows'] as $rawRow) {
            $mappedRow = ['_line_number' => $rawRow['_line_number'] ?? null];
            foreach ($sanitized as $sourceHeader => $targetField) {
                if ($targetField === '') {
                    continue;
                }
                $mappedRow[$targetField] = trim((string)($rawRow[$sourceHeader] ?? ''));
            }
            $mappedRows[] = $mappedRow;
        }

        $importSession['final_mapping'] = $sanitized;
        $importSession['rows'] = $mappedRows;

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'validate_import_mapping',
                'imports',
                'import',
                null,
                sprintf(
                    'Validation du mapping import pour %s : %d colonne(s) source, %d champ(s) mappé(s), %d ligne(s) préparée(s)',
                    $fileName,
                    count($rawHeaders),
                    count(array_filter($sanitized, static fn($v) => $v !== '')),
                    count($mappedRows)
                )
            );
        }

        sl_import_mapping_create_notification(
            $pdo,
            'import_mapping_validated',
            sprintf(
                'Mapping import validé pour %s : %d colonne(s) mappée(s), %d ligne(s) prêtes pour prévisualisation.',
                $fileName,
                count(array_filter($sanitized, static fn($v) => $v !== '')),
                count($mappedRows)
            ),
            'success',
            APP_URL . 'modules/imports/import_preview.php',
            'import',
            null,
            $userId > 0 ? $userId : null
        );

        header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'validate_import_mapping_failed',
                'imports',
                'import',
                null,
                'Échec validation mapping import pour ' . $fileName . ' : ' . $errorMessage
            );
        }

        sl_import_mapping_create_notification(
            $pdo,
            'import_mapping_failed',
            'Échec du mapping import pour ' . $fileName . ' : ' . $errorMessage,
            'danger',
            APP_URL . 'modules/imports/import_mapping.php',
            'import',
            null,
            $userId > 0 ? $userId : null
        );
    }
}

$requiredFields = ['operation_date', 'amount', 'operation_type', 'service'];
$mappedCount = count(array_filter($currentMapping, static fn($v) => $v !== ''));
$requiredCovered = 0;
foreach ($requiredFields as $requiredField) {
    if (in_array($requiredField, $currentMapping, true)) {
        $requiredCovered++;
    }
}

$sampleRows = array_slice($rawRows, 0, 3);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sim-shell {
                display: grid;
                gap: 20px;
            }

            .sim-toolbar,
            .sim-panel,
            .sim-kpi-card,
            .sim-map-card {
                border-radius: 20px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            }

            .sim-toolbar,
            .sim-panel {
                padding: 18px;
            }

            .sim-toolbar h3,
            .sim-panel h3 {
                margin: 0 0 8px;
            }

            .sim-subtitle {
                color: #64748b;
                margin: 0;
                line-height: 1.5;
            }

            .sim-help-box {
                margin-top: 14px;
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(99, 102, 241, 0.07);
                border: 1px solid rgba(99, 102, 241, 0.16);
                color: #3730a3;
                line-height: 1.55;
                font-size: 13px;
            }

            .sim-kpi-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 900px) {
                .sim-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sim-kpi-card {
                padding: 16px;
                background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            }

            .sim-kpi-label {
                display: block;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .sim-kpi-value {
                display: block;
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }

            .sim-kpi-meta {
                display: block;
                margin-top: 8px;
                color: #64748b;
                font-size: 12px;
            }

            .sim-main-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
                gap: 20px;
                align-items: start;
            }

            @media (max-width: 1180px) {
                .sim-main-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sim-map-list {
                display: grid;
                gap: 12px;
                margin-top: 14px;
            }

            .sim-map-card {
                padding: 14px;
                background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            }

            .sim-map-top {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(220px, 260px);
                gap: 14px;
                align-items: start;
            }

            @media (max-width: 760px) {
                .sim-map-top {
                    grid-template-columns: 1fr;
                }
            }

            .sim-source-name {
                font-weight: 900;
                color: #0f172a;
                word-break: break-word;
            }

            .sim-source-sub {
                display: block;
                margin-top: 4px;
                font-size: 12px;
                color: #64748b;
                word-break: break-word;
            }

            .sim-map-select select {
                width: 100%;
            }

            .sim-map-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 12px;
            }

            .sim-chip {
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

            .sim-chip--required {
                background: rgba(239, 68, 68, 0.10);
                color: #b91c1c;
            }

            .sim-chip--optional {
                background: rgba(148, 163, 184, 0.14);
                color: #475569;
            }

            .sim-chip--ok {
                background: rgba(16, 185, 129, 0.12);
                color: #047857;
            }

            .sim-chip--warn {
                background: rgba(245, 158, 11, 0.12);
                color: #92400e;
            }

            .sim-chip--ignore {
                background: rgba(226, 232, 240, 0.95);
                color: #475569;
            }

            .sim-model-list {
                display: grid;
                gap: 10px;
                margin-top: 14px;
            }

            .sim-model-item {
                border: 1px solid rgba(148, 163, 184, 0.16);
                border-radius: 14px;
                padding: 12px;
                background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            }

            .sim-model-item__top {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
                margin-bottom: 6px;
            }

            .sim-model-code {
                font-weight: 900;
                color: #0f172a;
                word-break: break-word;
            }

            .sim-model-label {
                font-size: 13px;
                color: #334155;
                font-weight: 700;
            }

            .sim-model-desc {
                font-size: 12px;
                color: #64748b;
                line-height: 1.5;
            }

            .sim-mini-preview {
                margin-top: 14px;
                display: grid;
                gap: 10px;
            }

            .sim-mini-preview-item {
                padding: 12px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.95);
                border: 1px solid rgba(226, 232, 240, 0.9);
            }

            .sim-mini-preview-title {
                font-size: 12px;
                font-weight: 900;
                color: #0f172a;
                margin-bottom: 8px;
            }

            .sim-mini-preview-line {
                font-size: 12px;
                color: #475569;
                line-height: 1.45;
                word-break: break-word;
            }
        </style>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="sim-shell">
            <div class="sim-toolbar">
                <h3>Fichier : <?= e($fileName) ?></h3>
                <p class="sim-subtitle">
                    Associe chaque colonne source du fichier à un champ métier Studely Ledger.  
                    Le rendu ci-dessous est volontairement compact et vertical pour garder tout visible sans scroll horizontal.
                </p>

                <div class="sim-help-box">
                    Les champs obligatoires pour passer à l’étape suivante sont :
                    <strong>Date opération</strong>, <strong>Montant</strong>, <strong>Type opération</strong> et <strong>Service</strong>.
                </div>
            </div>

            <div class="sim-kpi-grid">
                <div class="sim-kpi-card">
                    <span class="sim-kpi-label">Colonnes source</span>
                    <span class="sim-kpi-value"><?= (int)count($rawHeaders) ?></span>
                    <span class="sim-kpi-meta">Entêtes détectées</span>
                </div>

                <div class="sim-kpi-card">
                    <span class="sim-kpi-label">Champs mappés</span>
                    <span class="sim-kpi-value"><?= (int)$mappedCount ?></span>
                    <span class="sim-kpi-meta">Affectations actives</span>
                </div>

                <div class="sim-kpi-card">
                    <span class="sim-kpi-label">Obligatoires couverts</span>
                    <span class="sim-kpi-value"><?= (int)$requiredCovered ?>/<?= (int)count($requiredFields) ?></span>
                    <span class="sim-kpi-meta">Couverture minimale requise</span>
                </div>
            </div>

            <div class="sim-main-grid">
                <div class="sim-panel">
                    <h3>Mapping des colonnes</h3>
                    <p class="sim-subtitle">
                        Chaque colonne est affichée dans une carte compacte avec sa suggestion, son statut et son champ cible.
                    </p>

                    <form method="POST">
                        <?= csrf_input() ?>

                        <div class="sim-map-list">
                            <?php foreach ($rawHeaders as $header): ?>
                                <?php
                                $current = (string)($currentMapping[$header] ?? '');
                                $suggested = (string)($suggestedMapping[$header] ?? '');
                                $isRequired = $current !== '' && !empty($targetFieldMeta[$current]['required']);
                                $statusLabel = 'Ignoré';
                                $statusClass = 'sim-chip--ignore';

                                if ($current !== '') {
                                    $statusLabel = $isRequired ? 'Obligatoire couvert' : 'Mappé';
                                    $statusClass = $isRequired ? 'sim-chip--ok' : 'sim-chip--warn';
                                }
                                ?>
                                <div class="sim-map-card">
                                    <div class="sim-map-top">
                                        <div>
                                            <div class="sim-source-name"><?= e($header) ?></div>
                                            <span class="sim-source-sub">
                                                Suggestion : <?= e($targetFields[$suggested] ?? 'Aucune') ?>
                                            </span>
                                        </div>

                                        <div class="sim-map-select">
                                            <label>Champ cible</label>
                                            <select name="mapping[<?= e($header) ?>]">
                                                <?php foreach ($targetFields as $value => $label): ?>
                                                    <option value="<?= e($value) ?>" <?= $current === $value ? 'selected' : '' ?>>
                                                        <?= e($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="sim-map-meta">
                                        <span class="sim-chip <?= e($statusClass) ?>">
                                            <?= e($statusLabel) ?>
                                        </span>

                                        <?php if ($suggested !== ''): ?>
                                            <span class="sim-chip sim-chip--ok">
                                                Auto : <?= e($targetFields[$suggested] ?? $suggested) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="sim-chip sim-chip--ignore">Aucune suggestion</span>
                                        <?php endif; ?>

                                        <?php if ($current !== '' && !empty($targetFieldMeta[$current]['required'])): ?>
                                            <span class="sim-chip sim-chip--required">Champ obligatoire</span>
                                        <?php elseif ($current !== ''): ?>
                                            <span class="sim-chip sim-chip--optional">Champ optionnel</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="btn-group" style="margin-top:20px;">
                            <button type="submit" class="btn btn-success">Valider le mapping</button>
                            <a href="<?= e(APP_URL) ?>modules/imports/import_upload.php" class="btn btn-outline">Recommencer</a>
                        </div>
                    </form>
                </div>

                <div class="sim-panel">
                    <h3>Modèle attendu</h3>
                    <p class="sim-subtitle">
                        Référence rapide des champs reconnus par le pipeline d’import.
                    </p>

                    <div class="sim-model-list">
                        <?php foreach ($targetFields as $fieldCode => $fieldLabel): ?>
                            <?php if ($fieldCode === '') continue; ?>
                            <?php
                            $isRequired = !empty($targetFieldMeta[$fieldCode]['required']);
                            $desc = (string)($targetFieldMeta[$fieldCode]['description'] ?? '');
                            ?>
                            <div class="sim-model-item">
                                <div class="sim-model-item__top">
                                    <div>
                                        <div class="sim-model-code"><?= e($fieldCode) ?></div>
                                        <div class="sim-model-label"><?= e($fieldLabel) ?></div>
                                    </div>

                                    <span class="sim-chip <?= $isRequired ? 'sim-chip--required' : 'sim-chip--optional' ?>">
                                        <?= $isRequired ? 'Obligatoire' : 'Optionnel' ?>
                                    </span>
                                </div>

                                <div class="sim-model-desc"><?= e($desc) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($sampleRows)): ?>
                        <div class="sim-mini-preview">
                            <h3 style="margin:10px 0 0;">Extrait source</h3>

                            <?php foreach ($sampleRows as $idx => $row): ?>
                                <div class="sim-mini-preview-item">
                                    <div class="sim-mini-preview-title">
                                        Ligne <?= (int)($row['_line_number'] ?? ($idx + 2)) ?>
                                    </div>

                                    <?php foreach ($rawHeaders as $header): ?>
                                        <div class="sim-mini-preview-line">
                                            <strong><?= e($header) ?> :</strong>
                                            <?= e((string)($row[$header] ?? '')) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>