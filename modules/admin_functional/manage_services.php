<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

require_once __DIR__ . '/../../includes/header.php';

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
        SELECT id, code, label
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM service_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    try {
        $code = trim((string)($_POST['code'] ?? ''));
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
        if ($editId > 0) $params[] = $editId;
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
            $stmt->execute([$code, $label, $operationTypeId, $serviceAccountId, $treasuryAccountId, $editId]);
            $successMessage = 'Service mis à jour.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ref_services (code, label, operation_type_id, service_account_id, treasury_account_id, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$code, $label, $operationTypeId, $serviceAccountId, $treasuryAccountId]);
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
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Services',
        'Création, modification et suppression des services métier.'
    ); ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'deleted'): ?>
        <div class="success">Service supprimé.</div>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

    <div class="dashboard-grid-2">
        <div class="form-card">
            <h3 class="section-title"><?= $editItem ? 'Modifier un service' : 'Créer un service' ?></h3>

            <form method="POST">
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

                    <div>
                        <label>Type d’opération</label>
                        <select name="operation_type_id" required>
                            <option value="">Choisir</option>
                            <?php foreach ($operationTypes as $item): ?>
                                <option value="<?= (int)$item['id'] ?>" <?= (string)($editItem['operation_type_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
                                    <?= e($item['label'] . ' (' . $item['code'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" name="save_service" value="1" class="btn btn-success"><?= $editItem ? 'Enregistrer' : 'Créer' ?></button>
                    <?php if ($editItem): ?>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_services.php">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="dashboard-panel">
            <h3 class="section-title">Règle</h3>
            <div class="dashboard-note">
                Chaque service doit être rattaché à un type d’opération et à au moins un compte interne.
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

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>