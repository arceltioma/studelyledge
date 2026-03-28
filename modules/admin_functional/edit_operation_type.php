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
    exit('Type invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM ref_operation_types
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Type introuvable.');
}

$allServices = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT id, code, label, operation_type_id, is_active
        FROM ref_services
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
              AND id <> ?
            LIMIT 1
        ");
        $stmtDup->execute([$code, $id]);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code existe déjà.');
        }

        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("
            UPDATE ref_operation_types
            SET
                code = ?,
                label = ?,
                direction = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$code, $label, $direction, $isActive, $id]);

        if ($attachServiceIds) {
            $stmtAttach = $pdo->prepare("
                UPDATE ref_services
                SET operation_type_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            foreach ($attachServiceIds as $serviceId) {
                $stmtAttach->execute([$id, $serviceId]);
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
                $id,
                $serviceAccountId,
                $treasuryAccountId
            ]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_operation_type',
                'admin_functional',
                'operation_type',
                $id,
                'Modification d’un type d’opération avec rattachement / création de services'
            );
        }

        $pdo->commit();
        $successMessage = 'Type d’opération mis à jour.';

        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$linkedServices = tableExists($pdo, 'ref_services')
    ? (function () use ($pdo, $id) {
        $stmtLinked = $pdo->prepare("
            SELECT
                rs.*,
                sa.account_code AS service_account_code,
                ta.account_code AS treasury_account_code
            FROM ref_services rs
            LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
            LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
            WHERE rs.operation_type_id = ?
            ORDER BY rs.label ASC
        ");
        $stmtLinked->execute([$id]);
        return $stmtLinked->fetchAll(PDO::FETCH_ASSOC);
    })()
    : [];

$pageTitle = 'Modifier un type d’opération';
$pageSubtitle = 'Le type porte la logique mère ; les services liés peuvent être rattachés ou créés ici.';
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
                            <label>Code</label>
                            <input type="text" name="code" value="<?= e($row['code'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Libellé</label>
                            <input type="text" name="label" value="<?= e($row['label'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Direction</label>
                            <select name="direction">
                                <option value="mixed" <?= (($row['direction'] ?? 'mixed') === 'mixed') ? 'selected' : '' ?>>Mixte</option>
                                <option value="credit" <?= (($row['direction'] ?? '') === 'credit') ? 'selected' : '' ?>>Crédit</option>
                                <option value="debit" <?= (($row['direction'] ?? '') === 'debit') ? 'selected' : '' ?>>Débit</option>
                            </select>
                        </div>

                        <div style="display:flex;align-items:flex-end;">
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="is_active" <?= ((int)($row['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
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
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="table-card">
                <h3 class="section-title">Services déjà liés</h3>

                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>706</th>
                            <th>512</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linkedServices as $service): ?>
                            <tr>
                                <td><?= e($service['code'] ?? '') ?></td>
                                <td><?= e($service['label'] ?? '') ?></td>
                                <td><?= e($service['service_account_code'] ?? '') ?></td>
                                <td><?= e($service['treasury_account_code'] ?? '') ?></td>
                                <td><?= ((int)($service['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$linkedServices): ?>
                            <tr><td colspan="5">Aucun service rattaché.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>