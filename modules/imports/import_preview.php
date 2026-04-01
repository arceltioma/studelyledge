<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_preview_page');
} else {
    enforcePagePermission($pdo, 'imports_preview');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT
            id,
            client_code,
            full_name,
            country_commercial,
            country_destination,
            generated_client_account,
            initial_treasury_account_id
        FROM clients
        WHERE COALESCE(is_active,1)=1
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT
            rs.id,
            rs.code,
            rs.label,
            rs.operation_type_id,
            rot.code AS operation_type_code
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        WHERE COALESCE(rs.is_active,1)=1
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [];
$previewRows = $_SESSION['statement_import_preview']['rows'] ?? [];
$flash = $_SESSION['import_validate_flash'] ?? null;
unset($_SESSION['import_validate_flash']);

$pageTitle = 'Prévisualisation imports';
$pageSubtitle = 'Même moteur comptable que la saisie manuelle.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>
    <?php render_app_header_bar('Prévisualisation des relevés bancaires', 'Même moteur comptable que la saisie manuelle'); ?>

    <?php if ($flash && !empty($flash['message'])): ?>
        <div class="<?= ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['type'] ?? '') === 'warning' ? 'warning' : 'success') ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($previewRows): ?>
        <form method="POST" action="<?= APP_URL ?>modules/imports/import_validate.php">
            <?= function_exists('csrf_input') ? csrf_input() : '' ?>

            <div class="table-card" style="margin-top:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                    <h3 class="section-title">Prévisualisation</h3>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Valider l’import</button>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Importer</th>
                            <th>Ligne</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Devise</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Service</th>
                            <th>Compte lié</th>
                            <th>512 source</th>
                            <th>512 cible</th>
                            <th>Débit manuel</th>
                            <th>Crédit manuel</th>
                            <th>Référence</th>
                            <th>Note</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewRows as $idx => $row): ?>
                            <?php
                            $statusClass = ($row['status'] ?? '') === 'ok'
                                ? 'status-success'
                                : ((($row['status'] ?? '') === 'ambiguous') ? 'status-warning' : 'status-danger');
                            ?>
                            <tr>
                                <td><input type="checkbox" name="selected_rows[]" value="<?= (int)$idx ?>" <?= (($row['status'] ?? '') === 'rejected') ? '' : 'checked' ?>></td>
                                <td><?= (int)($row['row_no'] ?? $idx) ?></td>

                                <td>
                                    <input type="date" name="row_operation_date[<?= (int)$idx ?>]" value="<?= e($row['operation_date'] ?? '') ?>">
                                </td>

                                <td>
                                    <input type="number" step="0.01" name="row_amount[<?= (int)$idx ?>]" value="<?= e((string)($row['amount'] ?? '')) ?>">
                                </td>

                                <td>
                                    <select name="row_currency_code[<?= (int)$idx ?>]">
                                        <?php foreach ($currencies as $currency): ?>
                                            <option value="<?= e($currency['code']) ?>" <?= (($row['currency_code'] ?? 'EUR') === $currency['code']) ? 'selected' : '' ?>>
                                                <?= e($currency['code']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <select name="row_client_id[<?= (int)$idx ?>]" class="row-client-select">
                                        <option value="">Aucun</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option
                                                value="<?= (int)$client['id'] ?>"
                                                data-country-commercial="<?= e($client['country_commercial'] ?? '') ?>"
                                                data-country-destination="<?= e($client['country_destination'] ?? '') ?>"
                                                data-client-account="<?= e($client['generated_client_account'] ?? '') ?>"
                                                data-treasury-id="<?= (int)($client['initial_treasury_account_id'] ?? 0) ?>"
                                                <?= (string)($row['client_id'] ?? '') === (string)$client['id'] ? 'selected' : '' ?>
                                            >
                                                <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <select name="row_operation_type_code[<?= (int)$idx ?>]" class="row-operation-type">
                                        <option value="">Choisir</option>
                                        <?php foreach ($operationTypes as $type): ?>
                                            <option value="<?= e($type['code']) ?>" data-type-code="<?= e(sl_normalize_code($type['code'] ?? '')) ?>" <?= (($row['operation_type_code'] ?? '') === $type['code']) ? 'selected' : '' ?>>
                                                <?= e($type['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <select name="row_service_code[<?= (int)$idx ?>]" class="row-service-select">
                                        <option value="">Choisir d’abord un type</option>
                                        <?php foreach ($services as $service): ?>
                                            <option
                                                value="<?= e($service['code']) ?>"
                                                data-type-code="<?= e(sl_normalize_code($service['operation_type_code'] ?? '')) ?>"
                                                data-service-code="<?= e(sl_normalize_code($service['code'] ?? '')) ?>"
                                                <?= (($row['service_code'] ?? '') === $service['code']) ? 'selected' : '' ?>
                                            >
                                                <?= e($service['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <input type="number" name="row_linked_bank_account_id[<?= (int)$idx ?>]" value="<?= e((string)($row['linked_bank_account_id'] ?? '')) ?>">
                                </td>

                                <td>
                                    <select name="row_source_treasury_account_id[<?= (int)$idx ?>]">
                                        <option value="">Aucun</option>
                                        <?php foreach ($treasuryAccounts as $ta): ?>
                                            <option value="<?= (int)$ta['id'] ?>" <?= (string)($row['source_treasury_account_id'] ?? '') === (string)$ta['id'] ? 'selected' : '' ?>>
                                                <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <select name="row_target_treasury_account_id[<?= (int)$idx ?>]">
                                        <option value="">Aucun</option>
                                        <?php foreach ($treasuryAccounts as $ta): ?>
                                            <option value="<?= (int)$ta['id'] ?>" <?= (string)($row['target_treasury_account_id'] ?? '') === (string)$ta['id'] ? 'selected' : '' ?>>
                                                <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <td>
                                    <input type="text" name="row_manual_debit_account_code[<?= (int)$idx ?>]" value="<?= e((string)($row['manual_debit_account_code'] ?? '')) ?>">
                                </td>

                                <td>
                                    <input type="text" name="row_manual_credit_account_code[<?= (int)$idx ?>]" value="<?= e((string)($row['manual_credit_account_code'] ?? '')) ?>">
                                </td>

                                <td>
                                    <input type="text" name="row_reference[<?= (int)$idx ?>]" value="<?= e($row['reference'] ?? '') ?>">
                                </td>

                                <td>
                                    <input type="text" name="row_notes[<?= (int)$idx ?>]" value="<?= e($row['notes'] ?? '') ?>">
                                </td>

                                <td>
                                    <span class="status-pill <?= $statusClass ?>"><?= e($row['status'] ?? 'unknown') ?></span>
                                    <?php if (!empty($row['status_reason'])): ?>
                                        <div class="muted"><?= e($row['status_reason']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$previewRows): ?>
                            <tr><td colspan="16">Aucune ligne à afficher.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php else: ?>
        <div class="dashboard-note">Aucune ligne à prévisualiser.</div>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const map = <?= json_encode(sl_operation_service_map(), JSON_UNESCAPED_UNICODE) ?>;

        document.querySelectorAll('tr').forEach(function (row) {
            const typeSelect = row.querySelector('.row-operation-type');
            const serviceSelect = row.querySelector('.row-service-select');

            if (!typeSelect || !serviceSelect) return;

            const originalOptions = Array.from(serviceSelect.querySelectorAll('option')).map(option => option.cloneNode(true));

            function refreshServices() {
                const selectedType = typeSelect.options[typeSelect.selectedIndex];
                const typeCode = selectedType ? (selectedType.getAttribute('data-type-code') || '') : '';
                const currentValue = serviceSelect.value;

                serviceSelect.innerHTML = '';

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = typeCode ? 'Choisir' : 'Choisir d’abord un type';
                serviceSelect.appendChild(placeholder);

                const allowedCodes = map[typeCode] || [];
                let stillValid = false;

                originalOptions.forEach(option => {
                    if (option.value === '') return;

                    const serviceCode = option.getAttribute('data-service-code') || '';
                    if (allowedCodes.includes(serviceCode)) {
                        const cloned = option.cloneNode(true);
                        if (cloned.value === currentValue) stillValid = true;
                        serviceSelect.appendChild(cloned);
                    }
                });

                serviceSelect.value = stillValid ? currentValue : '';
            }

            typeSelect.addEventListener('change', refreshServices);
            refreshServices();
        });
    });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>