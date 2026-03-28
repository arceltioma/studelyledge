<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

function afot_fetch_operation_types(PDO $pdo): array
{
    if (!tableExists($pdo, 'ref_operation_types')) {
        return [];
    }

    return $pdo->query("
        SELECT *
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function afot_fetch_services(PDO $pdo): array
{
    if (!tableExists($pdo, 'ref_services')) {
        return [];
    }

    return $pdo->query("
        SELECT id, code, label, operation_type_id, is_active
        FROM ref_services
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function afot_fetch_service_accounts(PDO $pdo): array
{
    if (!tableExists($pdo, 'service_accounts')) {
        return [];
    }

    return $pdo->query("
        SELECT id, account_code, account_label
        FROM service_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function afot_fetch_treasury_accounts(PDO $pdo): array
{
    if (!tableExists($pdo, 'treasury_accounts')) {
        return [];
    }

    return $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$archiveId = (int)($_GET['archive'] ?? 0);
$restoreId = (int)($_GET['restore'] ?? 0);

if ($archiveId > 0) {
    $stmt = $pdo->prepare("
        UPDATE ref_operation_types
        SET is_active = 0, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$archiveId]);
    header('Location: ' . APP_URL . 'modules/admin_functional/manage_operation_types.php?ok=archived');
    exit;
}

if ($restoreId > 0) {
    $stmt = $pdo->prepare("
        UPDATE ref_operation_types
        SET is_active = 1, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$restoreId]);
    header('Location: ' . APP_URL . 'modules/admin_functional/manage_operation_types.php?ok=restored');
    exit;
}

$allServices = afot_fetch_services($pdo);
$serviceAccounts = afot_fetch_service_accounts($pdo);
$treasuryAccounts = afot_fetch_treasury_accounts($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_operation_type'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $code = trim((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $direction = trim((string)($_POST['direction'] ?? 'mixed'));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $attachServiceIds = array_values(array_filter(array_map('intval', $_POST['attach_service_ids'] ?? []), fn($v) => $v > 0));

        $newServiceCodes = $_POST['new_service_code'] ?? [];
        $newServiceLabels = $_POST['new_service_label'] ?? [];
        $newService706 = $_POST['new_service_account_id'] ?? [];
        $newService512 = $_POST['new_treasury_account_id'] ?? [];

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        if (!in_array($direction, ['credit', 'debit', 'mixed'], true)) {
            $direction = 'mixed';
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM ref_operation_types
            WHERE code = ?
              " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $paramsDup = [$code];
        if ($editId > 0) {
            $paramsDup[] = $editId;
        }
        $stmtDup->execute($paramsDup);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code existe déjà.');
        }

        $pdo->beginTransaction();

        if ($editId > 0) {
            $stmt = $pdo->prepare("
                UPDATE ref_operation_types
                SET
                    code = ?,
                    label = ?,
                    direction = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$code, $label, $direction, $isActive, $editId]);
            $operationTypeId = $editId;
            $successMessage = 'Type d’opération mis à jour.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ref_operation_types (
                    code, label, direction, is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$code, $label, $direction, $isActive]);
            $operationTypeId = (int)$pdo->lastInsertId();
            $successMessage = 'Type d’opération créé.';
        }

        if ($attachServiceIds) {
            $stmtAttach = $pdo->prepare("
                UPDATE ref_services
                SET operation_type_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            foreach ($attachServiceIds as $serviceId) {
                $stmtAttach->execute([$operationTypeId, $serviceId]);
            }
        }

        $stmtDupService = $pdo->prepare("
            SELECT id
            FROM ref_services
            WHERE code = ?
            LIMIT 1
        ");

        $stmtInsertService = $pdo->prepare("
            INSERT INTO ref_services (
                code, label, operation_type_id, service_account_id, treasury_account_id,
                is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");

        $rowCount = max(
            count($newServiceCodes),
            count($newServiceLabels),
            count($newService706),
            count($newService512)
        );

        for ($i = 0; $i < $rowCount; $i++) {
            $serviceCode = trim((string)($newServiceCodes[$i] ?? ''));
            $serviceLabel = trim((string)($newServiceLabels[$i] ?? ''));
            $serviceAccountId = (($newService706[$i] ?? '') !== '') ? (int)$newService706[$i] : null;
            $treasuryAccountId = (($newService512[$i] ?? '') !== '') ? (int)$newService512[$i] : null;

            if ($serviceCode === '' && $serviceLabel === '') {
                continue;
            }

            if ($serviceCode === '' || $serviceLabel === '') {
                throw new RuntimeException('Chaque nouveau service doit avoir un code et un libellé.');
            }

            if ($serviceAccountId === null && $treasuryAccountId === null) {
                throw new RuntimeException('Chaque nouveau service doit être lié à un compte 706 ou 512.');
            }

            $stmtDupService->execute([$serviceCode]);
            if ($stmtDupService->fetch()) {
                throw new RuntimeException('Le code service "' . $serviceCode . '" existe déjà.');
            }

            $stmtInsertService->execute([
                $serviceCode,
                $serviceLabel,
                $operationTypeId,
                $serviceAccountId,
                $treasuryAccountId
            ]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                $editId > 0 ? 'edit_operation_type' : 'create_operation_type',
                'admin_functional',
                'operation_type',
                $operationTypeId,
                'Création / mise à jour d’un type d’opération avec rattachement de services'
            );
        }

        $pdo->commit();
        $allServices = afot_fetch_services($pdo);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$editItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM ref_operation_types
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT
            rot.*,
            COUNT(rs.id) AS linked_services_count
        FROM ref_operation_types rot
        LEFT JOIN ref_services rs ON rs.operation_type_id = rot.id
        GROUP BY rot.id, rot.code, rot.label, rot.direction, rot.is_active, rot.created_at, rot.updated_at
        ORDER BY rot.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$servicesByType = [];
if (tableExists($pdo, 'ref_services')) {
    $serviceRows = $pdo->query("
        SELECT
            rs.id,
            rs.code,
            rs.label,
            rs.operation_type_id,
            rs.is_active
        FROM ref_services rs
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($serviceRows as $serviceRow) {
        $typeId = (int)($serviceRow['operation_type_id'] ?? 0);
        if (!isset($servicesByType[$typeId])) {
            $servicesByType[$typeId] = [];
        }
        $servicesByType[$typeId][] = $serviceRow;
    }
}

$pageTitle = 'Types d’opérations';
$pageSubtitle = 'Le type est la logique mère ; on peut lui rattacher des services existants ou en créer immédiatement.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if (isset($_GET['ok']) && $_GET['ok'] === 'archived'): ?>
            <div class="success">Type d’opération archivé.</div>
        <?php endif; ?>

        <?php if (isset($_GET['ok']) && $_GET['ok'] === 'restored'): ?>
            <div class="success">Type d’opération réactivé.</div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title"><?= $editItem ? 'Modifier un type d’opération' : 'Créer un type d’opération' ?></h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <?php if ($editItem): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$editItem['id'] ?>">
                    <?php endif; ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code</label>
                            <input type="text" name="code" value="<?= e($editItem['code'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Libellé</label>
                            <input type="text" name="label" value="<?= e($editItem['label'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Direction</label>
                            <select name="direction">
                                <option value="mixed" <?= (($editItem['direction'] ?? 'mixed') === 'mixed') ? 'selected' : '' ?>>Mixte</option>
                                <option value="credit" <?= (($editItem['direction'] ?? '') === 'credit') ? 'selected' : '' ?>>Crédit</option>
                                <option value="debit" <?= (($editItem['direction'] ?? '') === 'debit') ? 'selected' : '' ?>>Débit</option>
                            </select>
                        </div>

                        <div style="display:flex;align-items:flex-end;">
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="is_active" <?= ((int)($editItem['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                                Type actif
                            </label>
                        </div>
                    </div>

                    <div>
                        <label>Rattacher des services existants</label>
                        <select name="attach_service_ids[]" multiple size="8">
                            <?php foreach ($allServices as $service): ?>
                                <option value="<?= (int)$service['id'] ?>">
                                    <?= e(($service['code'] ?? '') . ' - ' . ($service['label'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="table-card" style="margin-top:14px;">
                        <h3 class="section-title">Créer de nouveaux services liés à ce type</h3>

                        <table>
                            <thead>
                                <tr>
                                    <th>Code service</th>
                                    <th>Libellé service</th>
                                    <th>Compte 706</th>
                                    <th>Compte 512</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                    <tr>
                                        <td><input type="text" name="new_service_code[]"></td>
                                        <td><input type="text" name="new_service_label[]"></td>
                                        <td>
                                            <select name="new_service_account_id[]">
                                                <option value="">Aucun</option>
                                                <?php foreach ($serviceAccounts as $account): ?>
                                                    <option value="<?= (int)$account['id'] ?>">
                                                        <?= e($account['account_code'] . ' - ' . $account['account_label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="new_treasury_account_id[]">
                                                <option value="">Aucun</option>
                                                <?php foreach ($treasuryAccounts as $account): ?>
                                                    <option value="<?= (int)$account['id'] ?>">
                                                        <?= e($account['account_code'] . ' - ' . $account['account_label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_operation_type" value="1" class="btn btn-success">
                            <?= $editItem ? 'Enregistrer' : 'Créer' ?>
                        </button>

                        <?php if ($editItem): ?>
                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture métier</h3>
                <div class="dashboard-note">
                    Le type d’opération porte la logique comptable mère. Les services sont ses spécialisations métier :
                    plusieurs services peuvent dépendre d’un même type.
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Direction</th>
                        <th>Services liés</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $typeServices = $servicesByType[(int)$row['id']] ?? []; ?>
                        <tr>
                            <td><?= e($row['code'] ?? '') ?></td>
                            <td><?= e($row['label'] ?? '') ?></td>
                            <td><?= e($row['direction'] ?? '') ?></td>
                            <td>
                                <?php if (!$typeServices): ?>
                                    <span class="muted">Aucun service lié</span>
                                <?php else: ?>
                                    <?php foreach ($typeServices as $service): ?>
                                        <div><?= e(($service['code'] ?? '') . ' - ' . ($service['label'] ?? '')) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php?edit=<?= (int)$row['id'] ?>">Modifier</a>

                                    <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                        <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php?archive=<?= (int)$row['id'] ?>" onclick="return confirm('Archiver ce type d’opération ?');">Archiver</a>
                                    <?php else: ?>
                                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php?restore=<?= (int)$row['id'] ?>">Réactiver</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="6">Aucun type d’opération.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>