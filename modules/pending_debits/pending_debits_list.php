<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!tableExists($pdo, 'pending_client_debits')) {
    exit('Table pending_client_debits introuvable.');
}

$filters = function_exists('sl_pending_debits_list_parse_filters')
    ? sl_pending_debits_list_parse_filters($_GET)
    : [
        'q' => trim((string)($_GET['q'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'client' => trim((string)($_GET['client'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => 100,
    ];

$listData = function_exists('sl_pending_debits_list_get_rows')
    ? sl_pending_debits_list_get_rows($pdo, $filters)
    : [
        'rows' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => 100,
        'pages' => 1,
    ];

$kpis = function_exists('sl_pending_debits_list_get_kpis')
    ? sl_pending_debits_list_get_kpis($pdo, $filters)
    : [
        'total_count' => 0,
        'pending_count' => 0,
        'ready_count' => 0,
        'partial_count' => 0,
        'resolved_count' => 0,
        'cancelled_count' => 0,
        'initial_amount_total' => 0.0,
        'executed_amount_total' => 0.0,
        'remaining_amount_total' => 0.0,
    ];

$clients = function_exists('sl_pending_debits_list_get_clients')
    ? sl_pending_debits_list_get_clients($pdo)
    : [];

$items = $listData['rows'] ?? [];
$total = (int)($listData['total'] ?? 0);

$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['success_message'])) {
    $successMessage = (string)$_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (!empty($_SESSION['error_message'])) {
    $errorMessage = (string)$_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$pageTitle = 'Débits dus';
$pageSubtitle = 'Suivi premium des débits clients 411 en attente, partiels, prêts, soldés ou annulés';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .spd-shell {
                display: grid;
                gap: 20px;
            }

            .spd-toolbar,
            .spd-panel,
            .spd-table-card {
                border-radius: 20px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            }

            .spd-toolbar,
            .spd-panel,
            .spd-table-card {
                padding: 18px;
            }

            .spd-toolbar-grid {
                display: grid;
                grid-template-columns: 1.4fr 1fr 1fr auto;
                gap: 14px;
                align-items: end;
            }

            @media (max-width: 1100px) {
                .spd-toolbar-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }

            @media (max-width: 700px) {
                .spd-toolbar-grid {
                    grid-template-columns: 1fr;
                }
            }

            .spd-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 1100px) {
                .spd-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .spd-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .spd-kpi {
                border-radius: 18px;
                padding: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                border: 1px solid rgba(148, 163, 184, 0.12);
            }

            .spd-kpi__label {
                display: block;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .spd-kpi__value {
                display: block;
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }

            .spd-kpi__meta {
                display: block;
                margin-top: 8px;
                font-size: 12px;
                color: #64748b;
            }

            .spd-table-head {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                align-items: center;
                margin-bottom: 16px;
                flex-wrap: wrap;
            }

            .spd-table-head h3 {
                margin: 0;
            }

            .spd-results-chip {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 52px;
                padding: 8px 12px;
                border-radius: 999px;
                background: rgba(79, 70, 229, 0.10);
                color: #4338ca;
                font-weight: 800;
                font-size: 12px;
            }

            .spd-table-wrap {
                overflow: visible;
            }

            .spd-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            .spd-table th,
            .spd-table td {
                padding: 8px 8px;
                vertical-align: middle;
                border-bottom: 1px solid rgba(226, 232, 240, 0.9);
                font-size: 13px;
            }

            .spd-table th {
                text-align: left;
                font-size: 12px;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: .04em;
                font-weight: 800;
            }

            .spd-id-col {
                width: 56px;
            }

            .spd-client-col {
                width: 19%;
            }

            .spd-label-col {
                width: 24%;
            }

            .spd-amount-col {
                width: 18%;
            }

            .spd-status-col {
                width: 11%;
            }

            .spd-priority-col {
                width: 10%;
            }

            .spd-date-col {
                width: 11%;
            }

            .spd-actions-col {
                width: 17%;
            }

            .spd-client-box,
            .spd-label-box,
            .spd-amount-box {
                display: flex;
                flex-direction: column;
                gap: 3px;
                min-width: 0;
            }

            .spd-main {
                font-weight: 800;
                color: #0f172a;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .spd-sub {
                font-size: 11px;
                color: #64748b;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .spd-amount-line {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                font-size: 12px;
            }

            .spd-amount-line strong {
                color: #0f172a;
            }

            .spd-amount-line--remaining strong {
                color: #b45309;
            }

            .spd-actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
            }

            .spd-btn {
                min-width: 34px;
                padding: 6px 8px;
                border-radius: 10px;
                font-size: 12px;
                font-weight: 700;
                line-height: 1;
                text-decoration: none;
                border: 1px solid transparent;
                cursor: pointer;
            }

            .spd-btn--outline {
                background: #fff;
                color: #334155;
                border-color: rgba(148, 163, 184, 0.35);
            }

            .spd-btn--success {
                background: #10b981;
                color: #fff;
            }

            .spd-btn--danger {
                background: #ef4444;
                color: #fff;
            }

            .spd-btn:hover {
                filter: brightness(0.98);
            }

            .spd-empty {
                padding: 18px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.9);
                border: 1px dashed rgba(148, 163, 184, 0.35);
                color: #64748b;
            }

            .spd-row-hidden {
                display: none;
            }

            .spd-muted-note {
                color: #64748b;
                font-size: 13px;
            }

            .spd-status-badge {
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

            .spd-priority {
                display: inline-flex;
                align-items: center;
                padding: 4px 8px;
                border-radius: 999px;
                background: rgba(148, 163, 184, 0.14);
                color: #475569;
                font-size: 11px;
                font-weight: 700;
                white-space: nowrap;
            }

            @media (max-width: 1180px) {
                .spd-table th,
                .spd-table td {
                    font-size: 12px;
                    padding: 7px 6px;
                }

                .spd-sub {
                    font-size: 10px;
                }

                .spd-btn {
                    padding: 5px 7px;
                    font-size: 11px;
                }
            }
        </style>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['executed'])): ?>
            <div class="success">Le débit dû a bien été exécuté.</div>
        <?php endif; ?>

        <div class="spd-shell">
            <section class="spd-toolbar">
                <div class="spd-toolbar-grid">
                    <div>
                        <label for="spd-live-q">Recherche instantanée</label>
                        <input
                            type="text"
                            id="spd-live-q"
                            placeholder="Client, compte 411, libellé, notes, déclencheur..."
                            autocomplete="off"
                        >
                    </div>

                    <div>
                        <label for="spd-live-status">Statut</label>
                        <select id="spd-live-status">
                            <option value="">Tous</option>
                            <option value="pending">pending</option>
                            <option value="partial">partial</option>
                            <option value="ready">ready</option>
                            <option value="resolved">resolved</option>
                            <option value="settled">settled</option>
                            <option value="cancelled">cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label for="spd-live-client">Client</label>
                        <select id="spd-live-client">
                            <option value="">Tous</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int)$client['id'] ?>">
                                    <?= e((string)($client['client_code'] ?? '') . ' - ' . (string)($client['full_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-outline" id="spd-reset-filters">Réinitialiser</button>
                    </div>
                </div>
            </section>

            <section class="spd-panel">
                <div class="spd-kpi-grid">
                    <div class="spd-kpi">
                        <span class="spd-kpi__label">Total débits dus</span>
                        <span class="spd-kpi__value"><?= (int)$kpis['total_count'] ?></span>
                        <span class="spd-kpi__meta">Volume total</span>
                    </div>

                    <div class="spd-kpi">
                        <span class="spd-kpi__label">En attente</span>
                        <span class="spd-kpi__value"><?= (int)$kpis['pending_count'] ?></span>
                        <span class="spd-kpi__meta">Statut pending</span>
                    </div>

                    <div class="spd-kpi">
                        <span class="spd-kpi__label">Prêts</span>
                        <span class="spd-kpi__value"><?= (int)$kpis['ready_count'] ?></span>
                        <span class="spd-kpi__meta">Exécutables</span>
                    </div>

                    <div class="spd-kpi">
                        <span class="spd-kpi__label">Partiels</span>
                        <span class="spd-kpi__value"><?= (int)$kpis['partial_count'] ?></span>
                        <span class="spd-kpi__meta">Déjà commencés</span>
                    </div>

                    <div class="spd-kpi">
                        <span class="spd-kpi__label">Montant initial</span>
                        <span class="spd-kpi__value"><?= e(number_format((float)$kpis['initial_amount_total'], 2, ',', ' ')) ?></span>
                        <span class="spd-kpi__meta">Base totale</span>
                    </div>

                    <div class="spd-kpi">
                        <span class="spd-kpi__label">Déjà exécuté</span>
                        <span class="spd-kpi__value"><?= e(number_format((float)$kpis['executed_amount_total'], 2, ',', ' ')) ?></span>
                        <span class="spd-kpi__meta">Montant traité</span>
                    </div>

                    <div class="spd-kpi">
                        <span class="spd-kpi__label">Restant dû</span>
                        <span class="spd-kpi__value"><?= e(number_format((float)$kpis['remaining_amount_total'], 2, ',', ' ')) ?></span>
                        <span class="spd-kpi__meta">Montant ouvert</span>
                    </div>

                    <div class="spd-kpi">
                        <span class="spd-kpi__label">Résolus / annulés</span>
                        <span class="spd-kpi__value"><?= (int)$kpis['resolved_count'] ?> / <?= (int)$kpis['cancelled_count'] ?></span>
                        <span class="spd-kpi__meta">Traçabilité finale</span>
                    </div>
                </div>
            </section>

            <section class="spd-table-card">
                <div class="spd-table-head">
                    <div>
                        <h3>Liste des débits dus</h3>
                        <div class="spd-muted-note">
                            Vue dense, sans scroll horizontal, avec filtres live.
                        </div>
                    </div>

                    <div class="spd-results-chip">
                        <span id="spd-visible-count"><?= (int)$total ?></span>&nbsp;/&nbsp;<span><?= (int)$total ?></span>
                    </div>
                </div>

                <?php if ($items): ?>
                    <div class="spd-table-wrap">
                        <table class="spd-table">
                            <thead>
                                <tr>
                                    <th class="spd-id-col">ID</th>
                                    <th class="spd-client-col">Client</th>
                                    <th class="spd-label-col">Libellé</th>
                                    <th class="spd-amount-col">Montants</th>
                                    <th class="spd-status-col">Statut</th>
                                    <th class="spd-priority-col">Priorité</th>
                                    <th class="spd-date-col">Créé le</th>
                                    <th class="spd-actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="spd-table-body">
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $status = strtolower(trim((string)($item['status'] ?? '')));
                                    $canExecute = in_array($status, ['pending', 'partial', 'ready'], true);
                                    $canCancel = in_array($status, ['pending', 'partial', 'ready'], true);

                                    $clientCode = (string)($item['client_code'] ?? '');
                                    $fullName = (string)($item['full_name'] ?? '');
                                    $account411 = (string)($item['generated_client_account'] ?? '—');
                                    $label = (string)($item['label'] ?? '');
                                    $triggerType = (string)($item['trigger_type'] ?? '');
                                    $priority = (string)($item['priority_level'] ?? '—');
                                    $createdAt = (string)($item['created_at'] ?? '');
                                    $createdAtShort = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '—';

                                    $initialAmount = (float)($item['initial_amount'] ?? $item['amount_due'] ?? 0);
                                    $executedAmount = (float)($item['executed_amount'] ?? 0);
                                    $remainingAmount = (float)($item['remaining_amount'] ?? $item['amount_due'] ?? 0);

                                    $searchBlob = mb_strtolower(trim(
                                        $clientCode . ' ' .
                                        $fullName . ' ' .
                                        $account411 . ' ' .
                                        $label . ' ' .
                                        $triggerType . ' ' .
                                        (string)($item['notes'] ?? '') . ' ' .
                                        $status . ' ' .
                                        $priority
                                    ), 'UTF-8');
                                    ?>
                                    <tr
                                        class="spd-row"
                                        data-search="<?= e($searchBlob) ?>"
                                        data-status="<?= e($status) ?>"
                                        data-client-id="<?= (int)($item['client_id'] ?? 0) ?>"
                                    >
                                        <td class="spd-id-col">
                                            <span class="spd-main"><?= (int)($item['id'] ?? 0) ?></span>
                                        </td>

                                        <td class="spd-client-col">
                                            <div class="spd-client-box">
                                                <span class="spd-main"><?= e(trim($clientCode . ' - ' . $fullName) ?: '—') ?></span>
                                                <span class="spd-sub"><?= e($account411) ?></span>
                                            </div>
                                        </td>

                                        <td class="spd-label-col">
                                            <div class="spd-label-box">
                                                <span class="spd-main"><?= e($label !== '' ? $label : '—') ?></span>
                                                <span class="spd-sub"><?= e($triggerType !== '' ? $triggerType : '—') ?></span>
                                            </div>
                                        </td>

                                        <td class="spd-amount-col">
                                            <div class="spd-amount-box">
                                                <div class="spd-amount-line">
                                                    <span>Init.</span>
                                                    <strong><?= e(number_format($initialAmount, 2, ',', ' ')) ?></strong>
                                                </div>
                                                <div class="spd-amount-line">
                                                    <span>Exéc.</span>
                                                    <strong><?= e(number_format($executedAmount, 2, ',', ' ')) ?></strong>
                                                </div>
                                                <div class="spd-amount-line spd-amount-line--remaining">
                                                    <span>Rest.</span>
                                                    <strong><?= e(number_format($remainingAmount, 2, ',', ' ')) ?></strong>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="spd-status-col">
                                            <span class="spd-status-badge badge-<?= e(function_exists('sl_pending_debit_badge_class') ? sl_pending_debit_badge_class($status) : 'secondary') ?>">
                                                <?= e($status !== '' ? $status : '—') ?>
                                            </span>
                                        </td>

                                        <td class="spd-priority-col">
                                            <span class="spd-priority"><?= e($priority) ?></span>
                                        </td>

                                        <td class="spd-date-col">
                                            <div class="spd-label-box">
                                                <span class="spd-main"><?= e($createdAtShort) ?></span>
                                                <span class="spd-sub"><?= e($createdAt) ?></span>
                                            </div>
                                        </td>

                                        <td class="spd-actions-col">
                                            <div class="spd-actions">
                                                <a
                                                    href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)($item['id'] ?? 0) ?>"
                                                    class="spd-btn spd-btn--outline"
                                                    title="Voir"
                                                >
                                                    👁
                                                </a>
                                                    <a
        href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_edit.php?id=<?= (int)($item['id'] ?? 0) ?>"
        class="spd-btn spd-btn--outline"
        title="Modifier"
    >
        ✎
    </a>

                                                <?php if ($canExecute): ?>
                                                    <a
                                                        href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_execute.php?id=<?= (int)($item['id'] ?? 0) ?>"
                                                        class="spd-btn spd-btn--success"
                                                        title="Initier"
                                                    >
                                                        ▶
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($canCancel): ?>
                                                    <form
                                                        method="POST"
                                                        action="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_cancel.php"
                                                        style="display:inline-block; margin:0;"
                                                        onsubmit="return confirm('Confirmer l’annulation de ce débit dû ?');"
                                                    >
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                                                        <button type="submit" class="spd-btn spd-btn--danger" title="Annuler">
                                                            ✖
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="spd-empty">Aucun débit dû trouvé.</div>
                <?php endif; ?>
            </section>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const qInput = document.getElementById('spd-live-q');
            const statusSelect = document.getElementById('spd-live-status');
            const clientSelect = document.getElementById('spd-live-client');
            const resetBtn = document.getElementById('spd-reset-filters');
            const rows = Array.from(document.querySelectorAll('#spd-table-body .spd-row'));
            const visibleCountEl = document.getElementById('spd-visible-count');

            function normalize(str) {
                return (str || '')
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            }

            function applyFilters() {
                const q = normalize(qInput ? qInput.value : '');
                const status = normalize(statusSelect ? statusSelect.value : '');
                const clientId = (clientSelect ? clientSelect.value : '').trim();

                let visible = 0;

                rows.forEach(function (row) {
                    const search = normalize(row.getAttribute('data-search') || '');
                    const rowStatus = normalize(row.getAttribute('data-status') || '');
                    const rowClientId = (row.getAttribute('data-client-id') || '').trim();

                    let keep = true;

                    if (q !== '' && !search.includes(q)) {
                        keep = false;
                    }

                    if (keep && status !== '' && rowStatus !== status) {
                        keep = false;
                    }

                    if (keep && clientId !== '' && rowClientId !== clientId) {
                        keep = false;
                    }

                    row.classList.toggle('spd-row-hidden', !keep);

                    if (keep) {
                        visible++;
                    }
                });

                if (visibleCountEl) {
                    visibleCountEl.textContent = String(visible);
                }
            }

            if (qInput) {
                qInput.addEventListener('input', applyFilters);
            }

            if (statusSelect) {
                statusSelect.addEventListener('change', applyFilters);
            }

            if (clientSelect) {
                clientSelect.addEventListener('change', applyFilters);
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    if (qInput) qInput.value = '';
                    if (statusSelect) statusSelect.value = '';
                    if (clientSelect) clientSelect.value = '';
                    applyFilters();
                });
            }

            applyFilters();
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>