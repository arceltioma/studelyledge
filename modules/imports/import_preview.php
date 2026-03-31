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

if (!function_exists('sl_normalize_code')) {
    function sl_normalize_code(?string $value): string
    {
        $value = strtoupper(trim((string)$value));
        $value = str_replace(
            ['É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Ä', 'Î', 'Ï', 'Ô', 'Ö', 'Ù', 'Û', 'Ü', 'Ç', ' ', '-', '/', '\''],
            ['E', 'E', 'E', 'E', 'A', 'A', 'A', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'C', '_', '_', '_', ''],
            $value
        );
        $value = preg_replace('/[^A-Z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('sl_operation_service_map')) {
    function sl_operation_service_map(): array
    {
        return [
            'VERSEMENT' => ['VERSEMENT'],
            'VIREMENT' => ['INTERNE', 'MENSUEL', 'EXCEPTIONEL', 'REGULIER'],
            'REGULARISATION' => ['POSITIVE', 'NEGATIVE'],
            'FRAIS_SERVICE' => ['AVI', 'ATS'],
            'FRAIS_GESTION' => ['GESTION'],
            'COMMISSION_DE_TRANSFERT' => ['COMMISSION_DE_TRANSFERT'],
            'CA_PLACEMENT' => ['CA_PLACEMENT'],
            'CA_DIVERS' => ['CA_DIVERS'],
            'CA_LOGEMENT' => ['CA_LOGEMENT'],
            'CA_COURTAGE_PRET' => ['CA_COURTAGE_PRET'],
            'FRAIS_DEBOURDS_MICROFINANCE' => ['FRAIS_DEBOURDS_MICROFINANCE'],
        ];
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$successMessage = '';
$errorMessage = '';
$previewRows = [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
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
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

if (!empty($_SESSION['statement_import_preview']['rows'])) {
    $previewRows = $_SESSION['statement_import_preview']['rows'];
}

$pageTitle = 'Prévisualisation imports';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php render_app_header_bar('Prévisualisation des relevés bancaires', 'On détecte, on normalise, on rattache, puis seulement après on valide.'); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Charger un relevé</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div>
                        <label>Fichier CSV / TXT</label>
                        <input type="file" name="statement_file" accept=".csv,.txt" required>
                    </div>

                    <div style="margin-top:16px;">
                        <label>Compte interne source (optionnel)</label>
                        <select name="forced_treasury_account_id">
                            <option value="">Détection automatique</option>
                            <?php foreach ($treasuryAccounts as $ta): ?>
                                <option value="<?= (int)$ta['id'] ?>">
                                    <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-primary">Analyser le fichier</button>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Ce que fait le parseur</h3>
                <div class="dashboard-note">
                    Détection souple des colonnes, gestion débit/crédit/solde, normalisation des montants, reconnaissance client et compte interne.
                </div>
            </div>
        </div>

        <?php if ($previewRows): ?>
            <form method="POST" action="<?= APP_URL ?>modules/imports/import_validate.php">
                <?php if (function_exists('csrf_input')): ?>
                    <?= csrf_input() ?>
                <?php endif; ?>

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
                                <th>Libellé</th>
                                <th>Débit</th>
                                <th>Crédit</th>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Service</th>
                                <th>Compte interne</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewRows as $idx => $row): ?>
                                <?php
                                $statusClass = $row['status'] === 'ok'
                                    ? 'status-success'
                                    : ($row['status'] === 'ambiguous' ? 'status-warning' : 'status-danger');
                                $rowTypeCode = sl_normalize_code((string)($row['operation_type_code'] ?? ''));
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_rows[]" value="<?= (int)$idx ?>" <?= $row['status'] === 'rejected' ? '' : 'checked' ?>>
                                    </td>
                                    <td><?= (int)$row['row_no'] ?></td>
                                    <td><?= e($row['operation_date'] ?? '') ?></td>
                                    <td><?= e($row['label'] ?? '') ?></td>
                                    <td><?= isset($row['debit']) && $row['debit'] !== null ? number_format((float)$row['debit'], 2, ',', ' ') : '—' ?></td>
                                    <td><?= isset($row['credit']) && $row['credit'] !== null ? number_format((float)$row['credit'], 2, ',', ' ') : '—' ?></td>

                                    <td>
                                        <select name="row_client_id[<?= (int)$idx ?>]">
                                            <option value="">Aucun</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?= (int)$client['id'] ?>" <?= (string)($row['client_id'] ?? '') === (string)$client['id'] ? 'selected' : '' ?>>
                                                    <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="row_operation_type_code[<?= (int)$idx ?>]" class="row-operation-type">
                                            <?php foreach ($operationTypes as $type): ?>
                                                <option value="<?= e($type['code']) ?>" data-type-code="<?= e(sl_normalize_code($type['code'] ?? '')) ?>" <?= ($row['operation_type_code'] ?? '') === $type['code'] ? 'selected' : '' ?>>
                                                    <?= e($type['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="row_service_code[<?= (int)$idx ?>]" class="row-service-select" data-row-type="<?= e($rowTypeCode) ?>">
                                            <option value="">Aucun</option>
                                            <?php foreach ($services as $service): ?>
                                                <option
                                                    value="<?= e($service['code']) ?>"
                                                    data-operation-type-id="<?= (int)($service['operation_type_id'] ?? 0) ?>"
                                                    data-operation-type-code="<?= e(sl_normalize_code($service['operation_type_code'] ?? '')) ?>"
                                                    data-service-code="<?= e(sl_normalize_code($service['code'] ?? '')) ?>"
                                                    <?= ($row['service_code'] ?? '') === $service['code'] ? 'selected' : '' ?>
                                                >
                                                    <?= e($service['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="row_treasury_account_id[<?= (int)$idx ?>]">
                                            <option value="">Aucun</option>
                                            <?php foreach ($treasuryAccounts as $ta): ?>
                                                <option value="<?= (int)$ta['id'] ?>" <?= (string)($row['treasury_account_id'] ?? '') === (string)$ta['id'] ? 'selected' : '' ?>>
                                                    <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <span class="status-pill <?= $statusClass ?>"><?= e($row['status']) ?></span>
                                        <?php if (!empty($row['status_reason'])): ?>
                                            <div class="muted"><?= e($row['status_reason']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$previewRows): ?>
                                <tr><td colspan="11">Aucune ligne à afficher.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const map = <?= json_encode(sl_operation_service_map(), JSON_UNESCAPED_UNICODE) ?>;

            document.querySelectorAll('tr').forEach(function (row) {
                const typeSelect = row.querySelector('.row-operation-type');
                const serviceSelect = row.querySelector('.row-service-select');

                if (!typeSelect || !serviceSelect) {
                    return;
                }

                const originalOptions = Array.from(serviceSelect.querySelectorAll('option')).map(option => option.cloneNode(true));

                function refreshRowServices() {
                    const selectedOption = typeSelect.options[typeSelect.selectedIndex];
                    const selectedTypeCode = selectedOption ? (selectedOption.getAttribute('data-type-code') || '') : '';
                    const currentValue = serviceSelect.value;

                    serviceSelect.innerHTML = '';

                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = selectedTypeCode ? 'Aucun' : 'Choisir d’abord un type';
                    serviceSelect.appendChild(placeholder);

                    const allowedCodes = map[selectedTypeCode] || [];
                    let stillValid = false;

                    originalOptions.forEach(option => {
                        if (option.value === '') {
                            return;
                        }

                        const serviceCode = option.getAttribute('data-service-code') || '';
                        const serviceTypeCode = option.getAttribute('data-operation-type-code') || '';

                        if (allowedCodes.includes(serviceCode) || serviceTypeCode === selectedTypeCode) {
                            const cloned = option.cloneNode(true);
                            if (cloned.value === currentValue) {
                                stillValid = true;
                            }
                            serviceSelect.appendChild(cloned);
                        }
                    });

                    serviceSelect.value = stillValid ? currentValue : '';
                }

                typeSelect.addEventListener('change', refreshRowServices);
                refreshRowServices();
            });
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>