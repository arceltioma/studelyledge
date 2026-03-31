<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceAccess($pdo, 'imports_preview_page');

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
        SELECT id, code, label
        FROM ref_services
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

if (!empty($_SESSION['statement_import_preview']['rows'])) {
    $previewRows = $_SESSION['statement_import_preview']['rows'];
}

$pageTitle = 'Prévisualisation imports';
$pageSubtitle = 'On détecte, on normalise, on rattache, puis seulement après on valide.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php if (function_exists('render_app_header_bar')): ?>
            <?php render_app_header_bar($pageTitle, $pageSubtitle); ?>
        <?php endif; ?>

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
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_rows[]" value="<?= (int)$idx ?>" <?= $row['status'] === 'rejected' ? '' : 'checked' ?>></td>
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
                                        <select name="row_operation_type_code[<?= (int)$idx ?>]">
                                            <?php foreach ($operationTypes as $type): ?>
                                                <option value="<?= e($type['code']) ?>" <?= ($row['operation_type_code'] ?? '') === $type['code'] ? 'selected' : '' ?>>
                                                    <?= e($type['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="row_service_code[<?= (int)$idx ?>]">
                                            <option value="">Aucun</option>
                                            <?php foreach ($services as $service): ?>
                                                <option value="<?= e($service['code']) ?>" <?= ($row['service_code'] ?? '') === $service['code'] ? 'selected' : '' ?>>
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

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>