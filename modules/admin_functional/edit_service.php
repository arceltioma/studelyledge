<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Service invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM ref_services
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Service introuvable.');
}

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label, direction, is_active
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM service_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                        'create_operation_type_from_edit_service',
                        'admin_functional',
                        'operation_type',
                        $operationTypeId,
                        'Création d’un type d’opération depuis la modification d’un service'
                    );
                }
            }
        }

        if ($operationTypeId === null || $operationTypeId <= 0) {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM ref_services
            WHERE code = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtDup->execute([$code, $id]);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code service existe déjà.');
        }

        $stmtUpdate = $pdo->prepare("
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
        $stmtUpdate->execute([
            $code,
            $label,
            $operationTypeId,
            $serviceAccountId,
            $treasuryAccountId,
            $isActive,
            $id
        ]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_service',
                'admin_functional',
                'service',
                $id,
                'Modification d’un service avec logique métier type/service'
            );
        }

        $successMessage = 'Service mis à jour.';

        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un service';
$pageSubtitle = 'Un service dépend d’un type d’opération ; ce type peut aussi être créé à la volée.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code service</label>
                            <input type="text" name="code" value="<?= e($row['code'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Libellé service</label>
                            <input type="text" name="label" value="<?= e($row['label'] ?? '') ?>" required>
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
                                    <option value="<?= (int)$item['id'] ?>" <?= (string)($row['operation_type_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e($item['label'] . ' (' . $item['code'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dashboard-note">
                            Utilise ce champ si le type existe déjà. Sinon, remplis les champs ci-dessous pour le créer.
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
                                    <option value="<?= (int)$item['id'] ?>" <?= (string)($row['service_account_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= (int)$item['id'] ?>" <?= (string)($row['treasury_account_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e($item['account_code'] . ' - ' . $item['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;align-items:flex-end;">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input type="checkbox" name="is_active" <?= ((int)($row['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                            Service actif
                        </label>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Règle métier</h3>
                <div class="dashboard-note">
                    Un service ne doit pas vivre sans type d’opération. Ce type peut être existant ou créé pendant l’édition.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>