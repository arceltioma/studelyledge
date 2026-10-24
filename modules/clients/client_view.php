<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmt = $pdo->prepare("
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ta.currency_code AS treasury_currency_code,
        ta.is_active AS treasury_is_active,
        ta2.account_code AS monthly_treasury_account_code,
        ta2.account_label AS monthly_treasury_account_label,
        ta2.currency_code AS monthly_treasury_currency_code,
        ta2.is_active AS monthly_treasury_is_active
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    LEFT JOIN treasury_accounts ta2 ON ta2.id = c.monthly_treasury_account_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$pendingDebitsSummary = function_exists('sl_client_view_get_pending_debits_summary')
    ? sl_client_view_get_pending_debits_summary($pdo, $id)
    : ['count' => 0, 'amount' => 0.0];

if (!function_exists('sl_cv_has_column')) {
    function sl_cv_has_column(PDO $pdo, string $table, string $column): bool
    {
        return function_exists('columnExists') ? columnExists($pdo, $table, $column) : false;
    }
}

if (!function_exists('sl_cv_table_exists')) {
    function sl_cv_table_exists(PDO $pdo, string $table): bool
    {
        return function_exists('tableExists') ? tableExists($pdo, $table) : false;
    }
}

if (!function_exists('sl_cv_badge_class_for_balance')) {
    function sl_cv_badge_class_for_balance(float $balance): string
    {
        if ($balance < 0) {
            return 'sl-badge sl-badge--danger';
        }
        if ($balance > 0) {
            return 'sl-badge sl-badge--success';
        }
        return 'sl-badge sl-badge--neutral';
    }
}

if (!function_exists('sl_cv_status_label')) {
    function sl_cv_status_label(bool $active): string
    {
        return $active ? 'Actif' : 'Archivé';
    }
}

if (!function_exists('sl_cv_status_class')) {
    function sl_cv_status_class(bool $active): string
    {
        return $active ? 'sl-badge sl-badge--success' : 'sl-badge sl-badge--danger';
    }
}

$bankAccount = null;

if (sl_cv_table_exists($pdo, 'client_bank_accounts') && sl_cv_table_exists($pdo, 'bank_accounts')) {
    $stmt = $pdo->prepare("
        SELECT ba.*
        FROM client_bank_accounts cba
        INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        WHERE cba.client_id = ?
        ORDER BY cba.id ASC
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$bankAccount && sl_cv_table_exists($pdo, 'bank_accounts') && !empty($client['generated_client_account'])) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM bank_accounts
        WHERE account_number = ?
        LIMIT 1
    ");
    $stmt->execute([(string)$client['generated_client_account']]);
    $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$client411Code = (string)($client['generated_client_account'] ?? '');
$initialBalance = 0.0;
$currentBalance = 0.0;

if (is_array($bankAccount)) {
    $initialBalance = (float)($bankAccount['initial_balance'] ?? 0);
    if (array_key_exists('balance', $bankAccount)) {
        $currentBalance = (float)$bankAccount['balance'];
    } elseif (array_key_exists('current_balance', $bankAccount)) {
        $currentBalance = (float)$bankAccount['current_balance'];
    } else {
        $currentBalance = $initialBalance;
    }
}

$feeOperations = [];
$recentOperations = [];
$totalFeeAmount = 0.0;
$distinct706Accounts = [];

if ($client411Code !== '' && sl_cv_table_exists($pdo, 'operations')) {
    $hasOperationDate = sl_cv_has_column($pdo, 'operations', 'operation_date');
    $hasLabel = sl_cv_has_column($pdo, 'operations', 'label');
    $hasReference = sl_cv_has_column($pdo, 'operations', 'reference');
    $hasAmount = sl_cv_has_column($pdo, 'operations', 'amount');
    $hasDebit = sl_cv_has_column($pdo, 'operations', 'debit_account_code');
    $hasCredit = sl_cv_has_column($pdo, 'operations', 'credit_account_code');
    $hasSourceType = sl_cv_has_column($pdo, 'operations', 'source_type');
    $hasOperationKind = sl_cv_has_column($pdo, 'operations', 'operation_kind');
    $hasNotes = sl_cv_has_column($pdo, 'operations', 'notes');

    $dateExpr = $hasOperationDate ? 'o.operation_date' : 'NULL';
    $labelExpr = $hasLabel ? 'o.label' : 'NULL';
    $refExpr = $hasReference ? 'o.reference' : 'NULL';
    $amountExpr = $hasAmount ? 'o.amount' : '0';
    $debitExpr = $hasDebit ? 'o.debit_account_code' : 'NULL';
    $creditExpr = $hasCredit ? 'o.credit_account_code' : 'NULL';
    $sourceTypeExpr = $hasSourceType ? 'o.source_type' : 'NULL';
    $operationKindExpr = $hasOperationKind ? 'o.operation_kind' : 'NULL';
    $notesExpr = $hasNotes ? 'o.notes' : 'NULL';

    if ($hasDebit || $hasCredit) {
        $stmtRecent = $pdo->prepare("
            SELECT
                o.id,
                {$dateExpr} AS operation_date,
                {$labelExpr} AS label,
                {$refExpr} AS reference,
                {$amountExpr} AS amount,
                {$debitExpr} AS debit_account_code,
                {$creditExpr} AS credit_account_code,
                {$sourceTypeExpr} AS source_type,
                {$operationKindExpr} AS operation_kind,
                {$notesExpr} AS notes
            FROM operations o
            WHERE
                " . ($hasDebit ? 'o.debit_account_code = ?' : '1=0') . "
                OR
                " . ($hasCredit ? 'o.credit_account_code = ?' : '1=0') . "
            ORDER BY " . ($hasOperationDate ? 'o.operation_date DESC' : 'o.id DESC') . ", o.id DESC
            LIMIT 100
        ");
        $stmtRecent->execute([$client411Code, $client411Code]);
        $recentOperations = $stmtRecent->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($recentOperations as $op) {
            $isFee = false;
            $sourceType = (string)($op['source_type'] ?? '');
            $operationKind = (string)($op['operation_kind'] ?? '');
            $creditCode = (string)($op['credit_account_code'] ?? '');
            $debitCode = (string)($op['debit_account_code'] ?? '');

            if ($sourceType === 'client_fee' || $operationKind === 'fee') {
                $isFee = true;
            }

            if (!$isFee) {
                $label = mb_strtolower((string)($op['label'] ?? ''), 'UTF-8');
                $reference = mb_strtolower((string)($op['reference'] ?? ''), 'UTF-8');
                if (str_contains($label, 'frais') || str_contains($reference, 'fee-')) {
                    $isFee = true;
                }
            }

            if ($isFee) {
                $feeOperations[] = $op;
                $totalFeeAmount += (float)($op['amount'] ?? 0);

                if ($creditCode !== '' && str_starts_with($creditCode, '706')) {
                    $distinct706Accounts[$creditCode] = $creditCode;
                }
                if ($debitCode !== '' && str_starts_with($debitCode, '706')) {
                    $distinct706Accounts[$debitCode] = $debitCode;
                }
            }
        }
    }
}

$clientIsActive = ((int)($client['is_active'] ?? 1) === 1);
$treasuryMainMissing = empty($client['initial_treasury_account_id']);
$treasuryMonthlyMissing = empty($client['monthly_treasury_account_id']);
$treasuryMainArchived = !$treasuryMainMissing && ((int)($client['treasury_is_active'] ?? 1) !== 1);
$treasuryMonthlyArchived = !$treasuryMonthlyMissing && ((int)($client['monthly_treasury_is_active'] ?? 1) !== 1);

$pageTitle = 'Fiche client';
$pageSubtitle = 'Consultation premium du client, du compte 411, des comptes 512 rattachés, des frais appliqués et des débits dus ouverts.';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sl-hero-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: flex-end;
                margin-bottom: 20px;
            }
            .sl-kpi-grid-premium {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 16px;
                margin-bottom: 20px;
            }
            @media (max-width: 1100px) {
                .sl-kpi-grid-premium { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 640px) {
                .sl-kpi-grid-premium { grid-template-columns: 1fr; }
            }
            .sl-kpi-card-premium {
                border-radius: 20px;
                padding: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
            }
            .sl-kpi-card-premium__label {
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }
            .sl-kpi-card-premium__value {
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }
            .sl-kpi-card-premium__meta {
                margin-top: 8px;
                font-size: 12px;
                color: #64748b;
            }
            .sl-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
            }
            .sl-badge--success {
                background: rgba(16, 185, 129, 0.12);
                color: #047857;
            }
            .sl-badge--danger {
                background: rgba(239, 68, 68, 0.12);
                color: #b91c1c;
            }
            .sl-badge--warning {
                background: rgba(245, 158, 11, 0.14);
                color: #b45309;
            }
            .sl-badge--info {
                background: rgba(59, 130, 246, 0.12);
                color: #1d4ed8;
            }
            .sl-badge--neutral {
                background: rgba(148, 163, 184, 0.14);
                color: #475569;
            }
            .sl-card-premium {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
                overflow: hidden;
            }
            .sl-card-premium__head {
                padding: 18px 20px;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }
            .sl-card-premium__title {
                margin: 0;
                font-size: 18px;
                font-weight: 900;
                color: #0f172a;
            }
            .sl-card-premium__subtitle {
                margin-top: 6px;
                font-size: 13px;
                color: #64748b;
            }
            .sl-card-premium__body {
                padding: 18px 20px;
            }
            .sl-alert-stack {
                display: grid;
                gap: 12px;
                margin-bottom: 20px;
            }
            .sl-alert {
                border-radius: 16px;
                padding: 14px 16px;
                border: 1px solid transparent;
                font-size: 14px;
                line-height: 1.5;
            }
            .sl-alert--warning {
                background: rgba(245, 158, 11, 0.08);
                border-color: rgba(245, 158, 11, 0.22);
                color: #92400e;
            }
            .sl-alert--danger {
                background: rgba(239, 68, 68, 0.08);
                border-color: rgba(239, 68, 68, 0.22);
                color: #991b1b;
            }
            .sl-alert--info {
                background: rgba(59, 130, 246, 0.07);
                border-color: rgba(59, 130, 246, 0.20);
                color: #1e40af;
            }
            .sl-usage-box {
                margin-top: 14px;
                padding: 12px 14px;
                border-radius: 14px;
                background: #f8fafc;
                border: 1px dashed rgba(148, 163, 184, 0.28);
                color: #475569;
                font-size: 13px;
                line-height: 1.5;
            }
            .sl-mini-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
            @media (max-width: 700px) {
                .sl-mini-grid { grid-template-columns: 1fr; }
            }
            .sl-mini-stat {
                border-radius: 16px;
                padding: 14px;
                background: #f8fafc;
                border: 1px solid rgba(148, 163, 184, 0.14);
            }
            .sl-mini-stat__label {
                font-size: 12px;
                color: #64748b;
                font-weight: 700;
                margin-bottom: 6px;
            }
            .sl-mini-stat__value {
                font-size: 20px;
                font-weight: 900;
                color: #0f172a;
            }
            .sl-section-gap {
                margin-top: 20px;
            }
            .sl-muted-list {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 12px;
            }
            .sl-table-note {
                margin-top: 12px;
                font-size: 12px;
                color: #64748b;
            }
        </style>

        <div class="sl-hero-actions">
            <?php if ((int)($pendingDebitsSummary['count'] ?? 0) > 0): ?>
                <a class="btn btn-warning" href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php?client=<?= (int)$id ?>">
                    Voir les débits dus du client
                </a>
            <?php endif; ?>
            <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$id ?>">Modifier</a>
            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">Retour</a>
        </div>

        <section class="sl-kpi-grid-premium">
            <div class="sl-kpi-card-premium">
                <div class="sl-kpi-card-premium__label">Compte 411</div>
                <div class="sl-kpi-card-premium__value"><?= e((string)($client['generated_client_account'] ?? '—')) ?></div>
                <div class="sl-kpi-card-premium__meta">Compte client principal</div>
            </div>

            <div class="sl-kpi-card-premium">
                <div class="sl-kpi-card-premium__label">Solde courant</div>
                <div class="sl-kpi-card-premium__value"><?= e(number_format($currentBalance, 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card-premium__meta">Devise <?= e((string)($client['currency'] ?? 'EUR')) ?></div>
            </div>

            <div class="sl-kpi-card-premium">
                <div class="sl-kpi-card-premium__label">Frais appliqués</div>
                <div class="sl-kpi-card-premium__value"><?= e(number_format($totalFeeAmount, 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card-premium__meta"><?= count($feeOperations) ?> opération(s) détectée(s)</div>
            </div>

            <div class="sl-kpi-card-premium">
                <div class="sl-kpi-card-premium__label">Statut client</div>
                <div class="sl-kpi-card-premium__value"><?= $clientIsActive ? 'ACTIF' : 'ARCHIVÉ' ?></div>
                <div class="sl-kpi-card-premium__meta">Pilotage opérationnel du dossier</div>
            </div>

            <div class="sl-kpi-card-premium">
                <div class="sl-kpi-card-premium__label">Débits dus ouverts</div>
                <div class="sl-kpi-card-premium__value"><?= (int)($pendingDebitsSummary['count'] ?? 0) ?></div>
                <div class="sl-kpi-card-premium__meta">
                    Montant : <?= e(number_format((float)($pendingDebitsSummary['amount'] ?? 0), 2, ',', ' ')) ?>
                </div>
            </div>
        </section>

        <div class="sl-alert-stack">
            <?php if ($currentBalance < 0): ?>
                <div class="sl-alert sl-alert--danger">
                    Le compte 411 du client est actuellement en solde négatif : <strong><?= e(number_format($currentBalance, 2, ',', ' ')) ?></strong>.
                </div>
            <?php endif; ?>

            <?php if ($treasuryMainMissing): ?>
                <div class="sl-alert sl-alert--warning">
                    Aucun compte 512 principal n’est rattaché à ce client.
                </div>
            <?php elseif ($treasuryMainArchived): ?>
                <div class="sl-alert sl-alert--danger">
                    Le compte 512 principal rattaché à ce client est archivé.
                </div>
            <?php endif; ?>

            <?php if ((int)($client['monthly_enabled'] ?? 0) === 1 && $treasuryMonthlyMissing): ?>
                <div class="sl-alert sl-alert--warning">
                    La mensualité est active, mais aucun compte 512 mensualité n’est défini.
                </div>
            <?php elseif ((int)($client['monthly_enabled'] ?? 0) === 1 && $treasuryMonthlyArchived): ?>
                <div class="sl-alert sl-alert--danger">
                    La mensualité est active, mais le compte 512 mensualité rattaché est archivé.
                </div>
            <?php endif; ?>

            <?php if ((int)($pendingDebitsSummary['count'] ?? 0) > 0): ?>
                <div class="sl-alert sl-alert--warning">
                    Ce client a actuellement <strong><?= (int)($pendingDebitsSummary['count'] ?? 0) ?></strong>
                    débit(s) dû(s) ouvert(s) pour un total de
                    <strong><?= e(number_format((float)($pendingDebitsSummary['amount'] ?? 0), 2, ',', ' ')) ?></strong>.
                    <br><br>
                    En cas de suppression définitive, ces débits dus seront automatiquement annulés avant traitement du compte 411.
                </div>
            <?php endif; ?>

            <?php if (!$feeOperations): ?>
                <div class="sl-alert sl-alert--info">
                    Aucun frais historisé n’a été détecté pour ce client.
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid-2">
            <section class="sl-card-premium">
                <div class="sl-card-premium__head">
                    <h3 class="sl-card-premium__title">Identité client</h3>
                    <div class="sl-card-premium__subtitle">Informations principales du dossier client</div>
                </div>
                <div class="sl-card-premium__body">
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code client</span><strong><?= e((string)($client['client_code'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e((string)($client['full_name'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Prénom</span><strong><?= e((string)($client['first_name'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Nom</span><strong><?= e((string)($client['last_name'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Email</span><strong><?= e((string)($client['email'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Téléphone</span><strong><?= e((string)($client['phone'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Adresse</span><strong><?= e((string)($client['postal_address'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Type client</span><strong><?= e((string)($client['client_type'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row">
                            <span>Statut</span>
                            <strong><span class="<?= e(sl_cv_status_class($clientIsActive)) ?>"><?= e(sl_cv_status_label($clientIsActive)) ?></span></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Débits dus ouverts</span>
                            <strong>
                                <?= (int)($pendingDebitsSummary['count'] ?? 0) ?>
                                <?php if ((int)($pendingDebitsSummary['count'] ?? 0) > 0): ?>
                                    — <?= e(number_format((float)($pendingDebitsSummary['amount'] ?? 0), 2, ',', ' ')) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="sl-card-premium">
                <div class="sl-card-premium__head">
                    <h3 class="sl-card-premium__title">Compte client 411</h3>
                    <div class="sl-card-premium__subtitle">Base de calcul du solde client et rattachement bancaire interne</div>
                </div>
                <div class="sl-card-premium__body">
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Compte généré</span><strong><?= e((string)($client['generated_client_account'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte bancaire lié</span><strong><?= e((string)($bankAccount['account_number'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Nom du compte</span><strong><?= e((string)($bankAccount['account_name'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde initial</span><strong><?= e(number_format($initialBalance, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row">
                            <span>Solde courant</span>
                            <strong>
                                <?= e(number_format($currentBalance, 2, ',', ' ')) ?>
                                <span class="<?= e(sl_cv_badge_class_for_balance($currentBalance)) ?>" style="margin-left:8px;">
                                    <?= $currentBalance < 0 ? 'Négatif' : ($currentBalance > 0 ? 'Positif' : 'Neutre') ?>
                                </span>
                            </strong>
                        </div>
                        <div class="sl-data-list__row"><span>Devise</span><strong><?= e((string)($client['currency'] ?? 'EUR')) ?></strong></div>
                    </div>

                    <div class="sl-usage-box">
                        <strong>Usage métier :</strong> le compte 411 représente le solde client consolidé. Il sert de base aux débits, aux frais appliqués, aux écritures liées au dossier client et au contrôle préalable avant suppression définitive.
                    </div>
                </div>
            </section>
        </div>

        <div class="dashboard-grid-2 sl-section-gap">
            <section class="sl-card-premium">
                <div class="sl-card-premium__head">
                    <h3 class="sl-card-premium__title">512 principal</h3>
                    <div class="sl-card-premium__subtitle">Compte de trésorerie principal de rattachement du client</div>
                </div>
                <div class="sl-card-premium__body">
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Compte 512 principal</span>
                            <strong><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? '')) ?: '—') ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Statut</span>
                            <strong>
                                <?php if ($treasuryMainMissing): ?>
                                    <span class="sl-badge sl-badge--warning">Manquant</span>
                                <?php else: ?>
                                    <span class="<?= e(sl_cv_status_class(((int)($client['treasury_is_active'] ?? 1) === 1))) ?>">
                                        <?= ((int)($client['treasury_is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?>
                                    </span>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Devise</span>
                            <strong><?= e((string)($client['treasury_currency_code'] ?? '—')) ?></strong>
                        </div>
                    </div>

                    <div class="sl-usage-box">
                        <strong>Usage métier :</strong> ce compte 512 principal représente le compte de trésorerie de référence du client pour les flux principaux.
                    </div>
                </div>
            </section>

            <section class="sl-card-premium">
                <div class="sl-card-premium__head">
                    <h3 class="sl-card-premium__title">512 mensualité</h3>
                    <div class="sl-card-premium__subtitle">Compte spécifique utilisé pour la logique de mensualité</div>
                </div>
                <div class="sl-card-premium__body">
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Compte 512 mensualité</span>
                            <strong><?= e(trim((string)($client['monthly_treasury_account_code'] ?? '') . ' - ' . (string)($client['monthly_treasury_account_label'] ?? '')) ?: '—') ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Mensualité</span>
                            <strong><?= e(number_format((float)($client['monthly_amount'] ?? 0), 2, ',', ' ')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Jour de mensualité</span>
                            <strong><?= e((string)($client['monthly_day'] ?? '26')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Mensualité active</span>
                            <strong>
                                <span class="<?= (int)($client['monthly_enabled'] ?? 0) === 1 ? 'sl-badge sl-badge--success' : 'sl-badge sl-badge--neutral' ?>">
                                    <?= (int)($client['monthly_enabled'] ?? 0) === 1 ? 'Oui' : 'Non' ?>
                                </span>
                            </strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Statut du 512 mensualité</span>
                            <strong>
                                <?php if ($treasuryMonthlyMissing): ?>
                                    <span class="sl-badge sl-badge--warning">Manquant</span>
                                <?php else: ?>
                                    <span class="<?= e(sl_cv_status_class(((int)($client['monthly_treasury_is_active'] ?? 1) === 1))) ?>">
                                        <?= ((int)($client['monthly_treasury_is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?>
                                    </span>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Dernière génération</span>
                            <strong><?= e((string)($client['monthly_last_generated_at'] ?? '—')) ?></strong>
                        </div>
                    </div>

                    <div class="sl-usage-box">
                        <strong>Usage métier :</strong> ce compte 512 est dédié aux écritures périodiques de mensualité.
                    </div>
                </div>
            </section>
        </div>

        <div class="dashboard-grid-2 sl-section-gap">
            <section class="sl-card-premium">
                <div class="sl-card-premium__head">
                    <h3 class="sl-card-premium__title">Synthèse frais & comptes 706</h3>
                    <div class="sl-card-premium__subtitle">Lecture rapide des frais détectés dans les opérations du client</div>
                </div>
                <div class="sl-card-premium__body">
                    <div class="sl-mini-grid">
                        <div class="sl-mini-stat">
                            <div class="sl-mini-stat__label">Nombre de frais</div>
                            <div class="sl-mini-stat__value"><?= count($feeOperations) ?></div>
                        </div>
                        <div class="sl-mini-stat">
                            <div class="sl-mini-stat__label">Montant total des frais</div>
                            <div class="sl-mini-stat__value"><?= e(number_format($totalFeeAmount, 2, ',', ' ')) ?></div>
                        </div>
                    </div>

                    <div class="sl-muted-list">
                        <?php if ($distinct706Accounts): ?>
                            <?php foreach ($distinct706Accounts as $account706): ?>
                                <span class="sl-badge sl-badge--info"><?= e($account706) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="sl-badge sl-badge--neutral">Aucun compte 706 détecté</span>
                        <?php endif; ?>
                    </div>

                    <div class="sl-usage-box">
                        <strong>Usage métier :</strong> les frais appliqués au client sont historisés dans les opérations. Ils débitent généralement le 411 du client et créditent un ou plusieurs comptes 706.
                    </div>
                </div>
            </section>

            <section class="sl-card-premium">
                <div class="sl-card-premium__head">
                    <h3 class="sl-card-premium__title">Passeport & zones</h3>
                    <div class="sl-card-premium__subtitle">Contexte administratif et géographique du client</div>
                </div>
                <div class="sl-card-premium__body">
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Numéro passeport</span><strong><?= e((string)($client['passport_number'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays de délivrance</span><strong><?= e((string)($client['passport_issue_country'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Date de délivrance</span><strong><?= e((string)($client['passport_issue_date'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Date d’expiration</span><strong><?= e((string)($client['passport_expiry_date'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays d'origine</span><strong><?= e((string)($client['country_origin'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays de destination</span><strong><?= e((string)($client['country_destination'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays commercial</span><strong><?= e((string)($client['country_commercial'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Créé le</span><strong><?= e((string)($client['created_at'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Mis à jour</span><strong><?= e((string)($client['updated_at'] ?? '—')) ?></strong></div>
                    </div>
                </div>
            </section>
        </div>

        <section class="sl-card-premium sl-section-gap">
            <div class="sl-card-premium__head">
                <h3 class="sl-card-premium__title">Frais appliqués au client</h3>
                <div class="sl-card-premium__subtitle">Historique des opérations identifiées comme frais</div>
            </div>
            <div class="sl-card-premium__body">
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Libellé</th>
                                <th>Référence</th>
                                <th>Montant</th>
                                <th>Débit</th>
                                <th>Crédit</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($feeOperations): ?>
                                <?php foreach ($feeOperations as $row): ?>
                                    <tr>
                                        <td><?= e((string)($row['operation_date'] ?? '')) ?></td>
                                        <td><?= e((string)($row['label'] ?? '')) ?></td>
                                        <td><?= e((string)($row['reference'] ?? '')) ?></td>
                                        <td><?= e(number_format((float)($row['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                        <td><?= e((string)($row['debit_account_code'] ?? '')) ?></td>
                                        <td><?= e((string)($row['credit_account_code'] ?? '')) ?></td>
                                        <td><?= e((string)($row['source_type'] ?? $row['operation_kind'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">Aucun frais trouvé pour ce client.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="sl-table-note">
                    Cette table isole les opérations reconnues comme frais via <strong>source_type</strong>, <strong>operation_kind</strong>, ou une détection de secours sur le libellé / la référence.
                </div>
            </div>
        </section>

        <section class="sl-card-premium sl-section-gap">
            <div class="sl-card-premium__head">
                <h3 class="sl-card-premium__title">Mouvements récents liés au compte 411</h3>
                <div class="sl-card-premium__subtitle">Vision consolidée des opérations récentes du client</div>
            </div>
            <div class="sl-card-premium__body">
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Libellé</th>
                                <th>Référence</th>
                                <th>Montant</th>
                                <th>Débit</th>
                                <th>Crédit</th>
                                <th>Nature</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentOperations): ?>
                                <?php foreach ($recentOperations as $row): ?>
                                    <tr>
                                        <td><?= e((string)($row['operation_date'] ?? '')) ?></td>
                                        <td><?= e((string)($row['label'] ?? '')) ?></td>
                                        <td><?= e((string)($row['reference'] ?? '')) ?></td>
                                        <td><?= e(number_format((float)($row['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                        <td><?= e((string)($row['debit_account_code'] ?? '')) ?></td>
                                        <td><?= e((string)($row['credit_account_code'] ?? '')) ?></td>
                                        <td><?= e((string)($row['operation_kind'] ?? $row['source_type'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">Aucun mouvement trouvé pour ce compte client.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>