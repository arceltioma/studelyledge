<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'treasury_view');

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$archiveId = (int)($_GET['archive'] ?? 0);
$restoreId = (int)($_GET['restore'] ?? 0);

if ($archiveId > 0) {
    $pdo->prepare("UPDATE service_accounts SET is_active = 0, updated_at = NOW() WHERE id = ?")->execute([$archiveId]);
    header('Location: ' . APP_URL . 'modules/treasury/service_accounts.php?ok=archived');
    exit;
}

if ($restoreId > 0) {
    $pdo->prepare("UPDATE service_accounts SET is_active = 1, updated_at = NOW() WHERE id = ?")->execute([$restoreId]);
    header('Location: ' . APP_URL . 'modules/treasury/service_accounts.php?ok=restored');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service_account'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $accountCode = trim((string)($_POST['account_code'] ?? ''));
        $accountLabel = trim((string)($_POST['account_label'] ?? ''));
        $operationTypeLabel = trim((string)($_POST['operation_type_label'] ?? ''));
        $destinationCountryLabel = trim((string)($_POST['destination_country_label'] ?? ''));
        $commercialCountryLabel = trim((string)($_POST['commercial_country_label'] ?? ''));
        $levelDepth = (int)($_POST['level_depth'] ?? 3);
        $isPostable = isset($_POST['is_postable']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($accountCode === '' || $accountLabel === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $sqlDup = "SELECT id FROM service_accounts WHERE account_code = ?";
        $paramsDup = [$accountCode];
        if ($editId > 0) {
            $sqlDup .= " AND id <> ?";
            $paramsDup[] = $editId;
        }
        $sqlDup .= " LIMIT 1";

        $stmtDup = $pdo->prepare($sqlDup);
        $stmtDup->execute($paramsDup);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce compte 706 existe déjà.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare("
                UPDATE service_accounts
                SET
                    account_code = ?,
                    account_label = ?,
                    operation_type_label = ?,
                    destination_country_label = ?,
                    commercial_country_label = ?,
                    level_depth = ?,
                    is_postable = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $accountCode,
                $accountLabel,
                $operationTypeLabel !== '' ? $operationTypeLabel : null,
                $destinationCountryLabel !== '' ? $destinationCountryLabel : null,
                $commercialCountryLabel !== '' ? $commercialCountryLabel : null,
                $levelDepth,
                $isPostable,
                $isActive,
                $editId
            ]);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction($pdo, (int)$_SESSION['user_id'], 'edit_service_account', 'treasury', 'service_account', $editId, 'Modification d’un compte 706');
            }

            $successMessage = 'Compte 706 mis à jour.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO service_accounts (
                    account_code,
                    account_label,
                    operation_type_label,
                    destination_country_label,
                    commercial_country_label,
                    level_depth,
                    is_postable,
                    is_active,
                    current_balance,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
            ");
            $stmt->execute([
                $accountCode,
                $accountLabel,
                $operationTypeLabel !== '' ? $operationTypeLabel : null,
                $destinationCountryLabel !== '' ? $destinationCountryLabel : null,
                $commercialCountryLabel !== '' ? $commercialCountryLabel : null,
                $levelDepth,
                $isPostable,
                $isActive
            ]);

            $newId = (int)$pdo->lastInsertId();

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction($pdo, (int)$_SESSION['user_id'], 'create_service_account', 'treasury', 'service_account', $newId, 'Création d’un compte 706');
            }

            $successMessage = 'Compte 706 créé.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editAccount = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM service_accounts WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = $pdo->query("
    SELECT *
    FROM service_accounts
    ORDER BY account_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Comptes internes 706';
$pageSubtitle = 'Gestion des comptes produits et services.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if (isset($_GET['ok']) && $_GET['ok'] === 'archived'): ?><div class="success">Compte archivé.</div><?php endif; ?>
        <?php if (isset($_GET['ok']) && $_GET['ok'] === 'restored'): ?><div class="success">Compte réactivé.</div><?php endif; ?>
        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title"><?= $editAccount ? 'Modifier un compte 706' : 'Créer un compte 706' ?></h3>

                <form method="POST">
                    <?= csrf_input() ?>
                    <?php if ($editAccount): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$editAccount['id'] ?>">
                    <?php endif; ?>

                    <div class="dashboard-grid-2">
                        <div><label>Code</label><input type="text" name="account_code" value="<?= e($editAccount['account_code'] ?? '') ?>" required></div>
                        <div><label>Libellé</label><input type="text" name="account_label" value="<?= e($editAccount['account_label'] ?? '') ?>" required></div>
                        <div><label>Type opération</label><input type="text" name="operation_type_label" value="<?= e($editAccount['operation_type_label'] ?? '') ?>"></div>
                        <div><label>Destination</label><input type="text" name="destination_country_label" value="<?= e($editAccount['destination_country_label'] ?? '') ?>"></div>
                        <div><label>Commercial</label><input type="text" name="commercial_country_label" value="<?= e($editAccount['commercial_country_label'] ?? '') ?>"></div>
                        <div><label>Niveau</label><input type="number" name="level_depth" value="<?= e((string)($editAccount['level_depth'] ?? 3)) ?>"></div>
                        <div style="display:flex;align-items:end;"><label><input type="checkbox" name="is_postable" <?= ((int)($editAccount['is_postable'] ?? 1) === 1) ? 'checked' : '' ?>> Postable</label></div>
                        <div style="display:flex;align-items:end;"><label><input type="checkbox" name="is_active" <?= ((int)($editAccount['is_active'] ?? 1) === 1) ? 'checked' : '' ?>> Actif</label></div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_service_account" value="1" class="btn btn-success"><?= $editAccount ? 'Enregistrer' : 'Créer' ?></button>
                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/treasury/index.php">Gérer les 512</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Les 706 enregistrent les produits de service. Ils ne doivent pas être confondus avec la trésorerie 512.
                </div>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Type op</th>
                        <th>Destination</th>
                        <th>Commercial</th>
                        <th>Solde</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['account_code']) ?></td>
                            <td><?= e($row['account_label']) ?></td>
                            <td><?= e($row['operation_type_label'] ?? '') ?></td>
                            <td><?= e($row['destination_country_label'] ?? '') ?></td>
                            <td><?= e($row['commercial_country_label'] ?? '') ?></td>
                            <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/treasury/service_accounts.php?edit=<?= (int)$row['id'] ?>">Modifier</a>
                                    <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                        <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/treasury/service_accounts.php?archive=<?= (int)$row['id'] ?>">Archiver</a>
                                    <?php else: ?>
                                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/treasury/service_accounts.php?restore=<?= (int)$row['id'] ?>">Réactiver</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="8">Aucun compte 706.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>