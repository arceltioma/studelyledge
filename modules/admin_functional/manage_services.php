<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'services_manage_page');
} else {
    enforcePagePermission($pdo, 'operations_create');
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

if (!function_exists('sl_service_allowed_for_type')) {
    function sl_service_allowed_for_type(?string $typeCode, ?string $serviceCode): bool
    {
        $map = sl_operation_service_map();
        $typeCode = sl_normalize_code($typeCode);
        $serviceCode = sl_normalize_code($serviceCode);

        if ($typeCode === '' || $serviceCode === '' || !isset($map[$typeCode])) {
            return false;
        }

        return in_array($serviceCode, $map[$typeCode], true);
    }
}

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM ref_services WHERE id = ? LIMIT 1");
        $stmt->execute([$deleteId]);
        header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php?ok=deleted');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label, is_active
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT
            id,
            account_code,
            account_label,
            operation_type_label,
            destination_country_label,
            commercial_country_label
        FROM service_accounts
        WHERE COALESCE(is_active,1) = 1
          AND COALESCE(is_postable,0) = 1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1) = 1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $code = sl_normalize_code((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $operationTypeId = ($_POST['operation_type_id'] ?? '') !== '' ? (int)$_POST['operation_type_id'] : null;
        $serviceAccountId = ($_POST['service_account_id'] ?? '') !== '' ? (int)$_POST['service_account_id'] : null;
        $treasuryAccountId = ($_POST['treasury_account_id'] ?? '') !== '' ? (int)$_POST['treasury_account_id'] : null;

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        if ($operationTypeId === null) {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        $selectedType = null;
        foreach ($operationTypes as $type) {
            if ((int)$type['id'] === $operationTypeId) {
                $selectedType = $type;
                break;
            }
        }

        if (!$selectedType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        if (!sl_service_allowed_for_type($selectedType['code'] ?? '', $code)) {
            throw new RuntimeException('Ce service n’est pas autorisé pour le type d’opération sélectionné.');
        }

        if ($serviceAccountId === null && $treasuryAccountId === null) {
            throw new RuntimeException('Le service doit être lié à un compte 706 ou 512.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id FROM ref_services
            WHERE code = ?
            " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $params = [$code];
        if ($editId > 0) {
            $params[] = $editId;
        }
        $stmtDup->execute($params);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code service existe déjà.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare("
                UPDATE ref_services
                SET code = ?, label = ?, operation_type_id = ?, service_account_id = ?, treasury_account_id = ?, is_active = 1
                WHERE id = ?
            ");
            $stmt->execute([
                $code,
                $label,
                $operationTypeId,
                $serviceAccountId,
                $treasuryAccountId,
                $editId
            ]);
            $successMessage = 'Service mis à jour.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ref_services (code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $code,
                $label,
                $operationTypeId,
                $serviceAccountId,
                $treasuryAccountId
            ]);
            $successMessage = 'Service créé.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ref_services WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT
            rs.*,
            rot.code AS operation_type_code,
            rot.label AS operation_type_label,
            sa.account_code AS service_account_code,
            sa.account_label AS service_account_label,
            ta.account_code AS treasury_account_code,
            ta.account_label AS treasury_account_label
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
        LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$map = sl_operation_service_map();

$pageTitle = 'Services';
$pageSubtitle = 'Le service est maintenant contraint par le type d’opération choisi.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

    <div class="dashboard-grid-2">
        <div class="form-card">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="edit_id" value="<?= (int)($editItem['id'] ?? 0) ?>">

                <div class="dashboard-grid-2">
                    <div>
                        <label>Type d’opération</label>
                        <select name="operation_type_id" id="operation_type_id" required>
                            <option value="">Choisir</option>
                            <?php foreach ($operationTypes as $type): ?>
                                <option value="<?= (int)$type['id'] ?>" data-type-code="<?= e(sl_normalize_code($type['code'] ?? '')) ?>" <?= ((string)($editItem['operation_type_id'] ?? '') === (string)$type['id']) ? 'selected' : '' ?>>
                                    <?= e($type['label']) ?> (<?= e($type['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Code service</label>
                        <select name="code" id="service_code_select" required>
                            <option value="">Choisir d’abord un type</option>
                            <?php foreach ($map as $typeCode => $serviceCodes): ?>
                                <?php foreach ($serviceCodes as $serviceCode): ?>
                                    <option
                                        value="<?= e($serviceCode) ?>"
                                        data-type-code="<?= e($typeCode) ?>"
                                        <?= sl_normalize_code($editItem['code'] ?? '') === $serviceCode ? 'selected' : '' ?>
                                    >
                                        <?= e($serviceCode) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e($editItem['label'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label>Compte 706</label>
                        <select name="service_account_id">
                            <option value="">Aucun</option>
                            <?php foreach ($serviceAccounts as $acc): ?>
                                <option value="<?= (int)$acc['id'] ?>" <?= ((string)($editItem['service_account_id'] ?? '') === (string)$acc['id']) ? 'selected' : '' ?>>
                                    <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Compte 512</label>
                        <select name="treasury_account_id">
                            <option value="">Aucun</option>
                            <?php foreach ($treasuryAccounts as $acc): ?>
                                <option value="<?= (int)$acc['id'] ?>" <?= ((string)($editItem['treasury_account_id'] ?? '') === (string)$acc['id']) ? 'selected' : '' ?>>
                                    <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" name="save_service" value="1" class="btn btn-primary"><?= $editItem ? 'Enregistrer' : 'Créer' ?></button>
                    <?php if ($editItem): ?>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_services.php">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="dashboard-panel">
            <h3 class="section-title">Lecture</h3>
            <div class="dashboard-note">
                Le formulaire ne propose que les codes service compatibles avec le type d’opération choisi.
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Type op</th>
                    <th>Compte 706</th>
                    <th>Compte 512</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['code'] ?? '') ?></td>
                        <td><?= e($row['label'] ?? '') ?></td>
                        <td><?= e(trim((string)($row['operation_type_label'] ?? '') . ' (' . (string)($row['operation_type_code'] ?? '') . ')')) ?></td>
                        <td><?= e(trim((string)($row['service_account_code'] ?? '') . ' - ' . (string)($row['service_account_label'] ?? ''))) ?></td>
                        <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                        <td>
                            <div class="btn-group">
                                <a class="btn btn-secondary" href="<?= APP_URL ?>modules/admin_functional/manage_services.php?edit=<?= (int)$row['id'] ?>">Modifier</a>
                                <a class="btn btn-danger" href="<?= APP_URL ?>modules/admin_functional/manage_services.php?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Supprimer ce service ?');">Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="6">Aucun service.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const typeSelect = document.getElementById('operation_type_id');
        const codeSelect = document.getElementById('service_code_select');

        if (!typeSelect || !codeSelect) {
            return;
        }

        const originalOptions = Array.from(codeSelect.querySelectorAll('option')).map(option => option.cloneNode(true));

        function getSelectedTypeCode() {
            const selected = typeSelect.options[typeSelect.selectedIndex];
            return selected ? (selected.getAttribute('data-type-code') || '') : '';
        }

        function refreshServiceCodes() {
            const typeCode = getSelectedTypeCode();
            const currentValue = codeSelect.value;

            codeSelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = typeCode ? 'Choisir' : 'Choisir d’abord un type';
            codeSelect.appendChild(placeholder);

            let stillValid = false;

            originalOptions.forEach(option => {
                if (option.value === '') {
                    return;
                }

                if ((option.getAttribute('data-type-code') || '') === typeCode) {
                    const cloned = option.cloneNode(true);
                    if (cloned.value === currentValue) {
                        stillValid = true;
                    }
                    codeSelect.appendChild(cloned);
                }
            });

            codeSelect.value = stillValid ? currentValue : '';
        }

        typeSelect.addEventListener('change', refreshServiceCodes);
        refreshServiceCodes();
    });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>