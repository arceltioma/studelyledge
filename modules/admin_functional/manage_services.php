<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

function af_fetch_operation_types(PDO $pdo): array
{
    if (!tableExists($pdo, 'ref_operation_types')) {
        return [];
    }

    return $pdo->query("
        SELECT id, code, label, direction, is_active
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function af_fetch_service_accounts(PDO $pdo): array
{
    if (!tableExists($pdo, 'service_accounts')) {
        return [];
    }

    return $pdo->query("
        SELECT id, account_code, account_label, is_active
        FROM service_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function af_fetch_treasury_accounts(PDO $pdo): array
{
    if (!tableExists($pdo, 'treasury_accounts')) {
        return [];
    }

    return $pdo->query("
        SELECT id, account_code, account_label, is_active
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
        UPDATE ref_services
        SET is_active = 0, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$archiveId]);
    header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php?ok=archived');
    exit;
}

if ($restoreId > 0) {
    $stmt = $pdo->prepare("
        UPDATE ref_services
        SET is_active = 1, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$restoreId]);
    header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php?ok=restored');
    exit;
}

$operationTypes = af_fetch_operation_types($pdo);
$serviceAccounts = af_fetch_service_accounts($pdo);
$treasuryAccounts = af_fetch_treasury_accounts($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $code = trim((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $operationTypeMode = trim((string)($_POST['operation_type_mode'] ?? 'existing'));
        $operationTypeId = ($_POST['operation_type_id'] ?? '') !== '' ? (int)$_POST['operation_type_id'] : null;

        $newOperationTypeCode = trim((string)($_POST['new_operation_type_code'] ?? ''));
        $newOperationTypeLabel = trim((string)($_POST['new_operation_type_label'] ?? ''));
        $newOperationTypeDirection = trim((string)($_POST['new_operation_type_direction'] ?? 'mixed'));

        $serviceAccountId = ($_POST['service_account_id'] ?? '') !== '' ? (int)$_POST['service_account_id'] : null;
        $treasuryAccountId = ($_POST['treasury_account_id'] ?? '') !== '' ? (int)$_POST['treasury_account_id'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé du service sont obligatoires.');
        }

        if ($serviceAccountId === null && $treasuryAccountId === null) {
            throw new RuntimeException('Le service doit être lié à un compte 706 ou 512.');
        }

        if ($operationTypeMode === 'new') {
            if ($newOperationTypeCode === '' || $newOperationTypeLabel === '') {
                throw new RuntimeException('Le code et le libellé du nouveau type d’opération sont obligatoires.');
            }

            if (!in_array($newOperationTypeDirection, ['credit', 'debit', 'mixed'], true)) {
                $newOperationTypeDirection = 'mixed';
            }

            $stmtDupType = $pdo->prepare("
                SELECT id
                FROM ref_operation_types
                WHERE code = ?
                LIMIT 1
            ");
            $stmtDupType->execute([$newOperationTypeCode]);
            $existingTypeId = $stmtDupType->fetchColumn();

            if ($existingTypeId) {
                $operationTypeId = (int)$existingTypeId;
            } else {
                $stmtInsertType = $pdo->prepare("
                    INSERT INTO ref_operation_types (
                        code, label, direction, is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, 1, NOW(), NOW())
                ");
                $stmtInsertType->execute([
                    $newOperationTypeCode,
                    $newOperationTypeLabel,
                    $newOperationTypeDirection
                ]);
                $operationTypeId = (int)$pdo->lastInsertId();

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'create_operation_type_from_service',
                        'admin_functional',
                        'operation_type',
                        $operationTypeId,
                        'Création d’un type d’opération depuis la création d’un service'
                    );
                }
            }
        }

        if ($operationTypeId === null || $operationTypeId <= 0) {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        $stmtDupService = $pdo->prepare("
            SELECT id
            FROM ref_services
            WHERE code = ?
              " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $paramsDup = [$code];
        if ($editId > 0) {
            $paramsDup[] = $editId;
        }
        $stmtDupService->execute($paramsDup);

        if ($stmtDupService->fetch()) {
            throw new RuntimeException('Ce code service existe déjà.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare("
                UPDATE ref_services
                SET
                    code = ?,
                    label = ?,
                    operation_type_id = ?,
                    service_account_id = ?,
                    treasury_account_id = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $code,
                $label,
                $operationTypeId,
                $serviceAccountId,
                $treasuryAccountId,
                $isActive,
                $editId
            ]);
            $successMessage = 'Service mis à jour.';

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_service',
                    'admin_functional',
                    'service',
                    $editId,
                    'Modification d’un service avec rattachement métier'
                );
            }
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ref_services (
                    code, label, operation_type_id, service_account_id, treasury_account_id,
                    is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $code,
                $label,
                $operationTypeId,
                $serviceAccountId,
                $treasuryAccountId,
                $isActive
            ]);
            $newServiceId = (int)$pdo->lastInsertId();
            $successMessage = 'Service créé.';

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_service',
                    'admin_functional',
                    'service',
                    $newServiceId,
                    'Création d’un service rattaché à un type d’opération'
                );
            }
        }

        $operationTypes = af_fetch_operation_types($pdo);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM ref_services
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT
            rs.*,
            rot.code AS operation_type_code,
            rot.label AS operation_type_label,
            rot.direction AS operation_type_direction,
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

$pageTitle = 'Services';
$pageSubtitle = 'Chaque service dépend d’un type d’opération ; on peut aussi créer ce type à la volée.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if (isset($_GET['ok']) && $_GET['ok'] === 'archived'): ?>
            <div class="success">Service archivé.</div>
        <?php endif; ?>

        <?php if (isset($_GET['ok']) && $_GET['ok'] === 'restored'): ?>
            <div class="success">Service réactivé.</div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title"><?= $editItem ? 'Modifier un service' : 'Créer un service' ?></h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <?php if ($editItem): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$editItem['id'] ?>">
                    <?php endif; ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code service</label>
                            <input type="text" name="code" value="<?= e($editItem['code'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Libellé service</label>
                            <input type="text" name="label" value="<?= e($editItem['label'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div>
                        <label>Mode de rattachement au type d’opération</label>
                        <select name="operation_type_mode">
                            <option value="existing" selected>Rattacher à un type existant</option>
                            <option value="new">Créer un nouveau type d’opération</option>
                        </select>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Type d’opération existant</label>
                            <select name="operation_type_id">
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= (string)($editItem['operation_type_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e($item['label'] . ' (' . $item['code'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dashboard-note">
                            Utilise ce champ si le type existe déjà. Sinon, remplis les champs ci-dessous pour le créer en même temps.
                        </div>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Nouveau code type d’opération</label>
                            <input type="text" name="new_operation_type_code" placeholder="Ex: FRAIS_DOSSIER">
                        </div>

                        <div>
                            <label>Nouveau libellé type d’opération</label>
                            <input type="text" name="new_operation_type_label" placeholder="Ex: Frais dossier">
                        </div>
                    </div>

                    <div>
                        <label>Direction du nouveau type</label>
                        <select name="new_operation_type_direction">
                            <option value="mixed">Mixte</option>
                            <option value="credit">Crédit</option>
                            <option value="debit">Débit</option>
                        </select>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Compte 706</label>
                            <select name="service_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($serviceAccounts as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= (string)($editItem['service_account_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e($item['account_code'] . ' - ' . $item['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512</label>
                            <select name="treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= (string)($editItem['treasury_account_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e($item['account_code'] . ' - ' . $item['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;align-items:end;">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input type="checkbox" name="is_active" <?= ((int)($editItem['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                            Service actif
                        </label>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_service" value="1" class="btn btn-success">
                            <?= $editItem ? 'Enregistrer' : 'Créer' ?>
                        </button>

                        <?php if ($editItem): ?>
                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Règle métier</h3>
                <div class="dashboard-note">
                    Un service appartient à un type d’opération. Plusieurs services peuvent dépendre du même type.
                    À la création d’un service, on peut soit choisir un type existant, soit le créer immédiatement.
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Type d’opération</th>
                        <th>Direction</th>
                        <th>Compte 706</th>
                        <th>Compte 512</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['code'] ?? '') ?></td>
                            <td><?= e($row['label'] ?? '') ?></td>
                            <td><?= e(trim((string)($row['operation_type_label'] ?? '') . ' (' . (string)($row['operation_type_code'] ?? '') . ')')) ?></td>
                            <td><?= e($row['operation_type_direction'] ?? '') ?></td>
                            <td><?= e(trim((string)($row['service_account_code'] ?? '') . ' - ' . (string)($row['service_account_label'] ?? ''))) ?></td>
                            <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                            <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php?edit=<?= (int)$row['id'] ?>">Modifier</a>

                                    <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                        <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php?archive=<?= (int)$row['id'] ?>" onclick="return confirm('Archiver ce service ?');">Archiver</a>
                                    <?php else: ?>
                                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php?restore=<?= (int)$row['id'] ?>">Réactiver</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="8">Aucun service.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>