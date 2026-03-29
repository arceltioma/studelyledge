<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

function afs_fetch_operation_types(PDO $pdo): array
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

function afs_fetch_service_accounts(PDO $pdo): array
{
    if (!tableExists($pdo, 'service_accounts')) {
        return [];
    }

    $hasParent = false;
    $hasLevel = false;
    $hasSort = false;

    try {
        $columns = $pdo->query("SHOW COLUMNS FROM service_accounts")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_map(fn($c) => $c['Field'], $columns);
        $hasParent = in_array('parent_account_id', $names, true);
        $hasLevel = in_array('account_level', $names, true);
        $hasSort = in_array('sort_order', $names, true);
    } catch (Throwable $e) {
        // fallback simple if SHOW COLUMNS fails
    }

    $sql = "
        SELECT
            sa.id,
            sa.account_code,
            sa.account_label,
            sa.operation_type_label,
            sa.destination_country_label,
            sa.commercial_country_label,
            sa.level_depth,
            sa.is_postable,
            sa.is_active
    ";

    if ($hasParent) {
        $sql .= ",
            sa.parent_account_id,
            p.account_code AS parent_account_code,
            p.account_label AS parent_account_label
        ";
    } else {
        $sql .= ",
            NULL AS parent_account_id,
            NULL AS parent_account_code,
            NULL AS parent_account_label
        ";
    }

    if ($hasLevel) {
        $sql .= ", sa.account_level";
    } else {
        $sql .= ", NULL AS account_level";
    }

    if ($hasSort) {
        $sql .= ", sa.sort_order";
    } else {
        $sql .= ", 0 AS sort_order";
    }

    $sql .= "
        FROM service_accounts sa
    ";

    if ($hasParent) {
        $sql .= " LEFT JOIN service_accounts p ON p.id = sa.parent_account_id ";
    }

    $sql .= "
        WHERE COALESCE(sa.is_active,1) = 1
          AND COALESCE(sa.is_postable,0) = 1
        ORDER BY
            COALESCE(sa.sort_order, 0) ASC,
            sa.account_code ASC
    ";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function afs_fetch_treasury_accounts(PDO $pdo): array
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

function afs_can_restore_service(PDO $pdo, int $serviceId): array
{
    $stmt = $pdo->prepare("
        SELECT
            rs.id,
            rs.code,
            rs.label,
            rs.operation_type_id,
            rs.service_account_id,
            rs.treasury_account_id,
            rot.is_active AS operation_type_active,
            sa.is_active AS service_account_active,
            sa.is_postable AS service_account_postable,
            ta.is_active AS treasury_account_active
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
        LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
        WHERE rs.id = ?
        LIMIT 1
    ");
    $stmt->execute([$serviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [false, 'Service introuvable.'];
    }

    if (empty($row['operation_type_id'])) {
        return [false, 'Impossible de réactiver ce service : aucun type d’opération n’est rattaché.'];
    }

    if ((int)($row['operation_type_active'] ?? 0) !== 1) {
        return [false, 'Impossible de réactiver ce service : son type d’opération parent est archivé.'];
    }

    if (empty($row['service_account_id']) && empty($row['treasury_account_id'])) {
        return [false, 'Impossible de réactiver ce service : aucun compte 706 ou 512 n’est rattaché.'];
    }

    if (!empty($row['service_account_id'])) {
        if ((int)($row['service_account_active'] ?? 0) !== 1) {
            return [false, 'Impossible de réactiver ce service : le compte 706 lié est archivé.'];
        }
        if ((int)($row['service_account_postable'] ?? 0) !== 1) {
            return [false, 'Impossible de réactiver ce service : le compte 706 lié n’est pas mouvementable.'];
        }
    }

    if (!empty($row['treasury_account_id']) && (int)($row['treasury_account_active'] ?? 0) !== 1) {
        return [false, 'Impossible de réactiver ce service : le compte 512 lié est archivé.'];
    }

    return [true, null];
}

function afs_find_operation_type(PDO $pdo, int $operationTypeId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, code, label, direction, is_active
        FROM ref_operation_types
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$operationTypeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function afs_find_service_account(PDO $pdo, ?int $serviceAccountId): ?array
{
    if ($serviceAccountId === null || $serviceAccountId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT
            sa.id,
            sa.account_code,
            sa.account_label,
            sa.operation_type_label,
            sa.destination_country_label,
            sa.commercial_country_label,
            sa.is_postable,
            sa.is_active
        FROM service_accounts sa
        WHERE sa.id = ?
        LIMIT 1
    ");
    $stmt->execute([$serviceAccountId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function afs_service_account_display(array $account): string
{
    $parts = [];
    $parts[] = $account['account_code'] ?? '';
    $parts[] = $account['account_label'] ?? '';

    $meta = [];
    if (!empty($account['operation_type_label'])) {
        $meta[] = $account['operation_type_label'];
    }
    if (!empty($account['destination_country_label'])) {
        $meta[] = 'Destination: ' . $account['destination_country_label'];
    }
    if (!empty($account['commercial_country_label'])) {
        $meta[] = 'Commercial: ' . $account['commercial_country_label'];
    }

    $label = trim(implode(' - ', array_filter($parts)));
    if ($meta) {
        $label .= ' [' . implode(' | ', $meta) . ']';
    }

    return $label;
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

    if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'archive_service',
            'admin_functional',
            'service',
            $archiveId,
            'Archivage d’un service'
        );
    }

    header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php?ok=archived');
    exit;
}

if ($restoreId > 0) {
    [$canRestore, $restoreError] = afs_can_restore_service($pdo, $restoreId);

    if (!$canRestore) {
        header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php?error=' . urlencode($restoreError));
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE ref_services
        SET is_active = 1, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$restoreId]);

    if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'restore_service',
            'admin_functional',
            'service',
            $restoreId,
            'Réactivation d’un service après contrôle de cohérence'
        );
    }

    header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php?ok=restored');
    exit;
}

$operationTypes = afs_fetch_operation_types($pdo);
$serviceAccounts = afs_fetch_service_accounts($pdo);
$treasuryAccounts = afs_fetch_treasury_accounts($pdo);

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
                        'Création d’un type d’opération depuis la création / édition d’un service'
                    );
                }
            }
        }

        if ($operationTypeId === null || $operationTypeId <= 0) {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        $parentType = afs_find_operation_type($pdo, $operationTypeId);
        if (!$parentType) {
            throw new RuntimeException('Le type d’opération sélectionné est introuvable.');
        }

        if ($isActive === 1 && (int)($parentType['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Un service actif ne peut pas être rattaché à un type d’opération archivé.');
        }

        $selected706 = afs_find_service_account($pdo, $serviceAccountId);
        if ($serviceAccountId !== null && !$selected706) {
            throw new RuntimeException('Le compte 706 sélectionné est introuvable.');
        }

        if ($selected706) {
            if ((int)($selected706['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le compte 706 sélectionné est archivé.');
            }

            if ((int)($selected706['is_postable'] ?? 0) !== 1) {
                throw new RuntimeException('Le compte 706 sélectionné n’est pas mouvementable.');
            }

            if (!empty($selected706['operation_type_label']) && !empty($parentType['code'])) {
                $normalizedAccountType = strtoupper(trim((string)$selected706['operation_type_label']));
                $normalizedParentType = strtoupper(trim((string)$parentType['code']));

                if ($normalizedAccountType !== $normalizedParentType) {
                    throw new RuntimeException('Le compte 706 sélectionné n’est pas cohérent avec le type d’opération choisi.');
                }
            }
        }

        if ($treasuryAccountId !== null) {
            $stmtTreasury = $pdo->prepare("
                SELECT is_active
                FROM treasury_accounts
                WHERE id = ?
                LIMIT 1
            ");
            $stmtTreasury->execute([$treasuryAccountId]);
            $treasuryActive = $stmtTreasury->fetchColumn();

            if ($treasuryActive === false) {
                throw new RuntimeException('Le compte 512 sélectionné est introuvable.');
            }

            if ((int)$treasuryActive !== 1) {
                throw new RuntimeException('Le compte 512 sélectionné est archivé.');
            }
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
                    'Modification d’un service avec hiérarchie 706'
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
                    'Création d’un service avec hiérarchie 706'
                );
            }
        }

        $operationTypes = afs_fetch_operation_types($pdo);
        $serviceAccounts = afs_fetch_service_accounts($pdo);
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
            rot.is_active AS operation_type_active,

            sa.account_code AS service_account_code,
            sa.account_label AS service_account_label,
            sa.operation_type_label AS service_account_operation_type_label,
            sa.destination_country_label AS service_account_destination_country_label,
            sa.commercial_country_label AS service_account_commercial_country_label,
            sa.is_postable AS service_account_postable,
            sa.is_active AS service_account_active,
            sa.parent_account_id AS service_account_parent_id,

            sap.account_code AS service_account_parent_code,
            sap.account_label AS service_account_parent_label,

            ta.account_code AS treasury_account_code,
            ta.account_label AS treasury_account_label,
            ta.is_active AS treasury_account_active
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
        LEFT JOIN service_accounts sap ON sap.id = sa.parent_account_id
        LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

if (isset($_GET['ok']) && $_GET['ok'] === 'archived') {
    $successMessage = 'Service archivé.';
}

if (isset($_GET['ok']) && $_GET['ok'] === 'restored') {
    $successMessage = 'Service réactivé.';
}

if (isset($_GET['error']) && $_GET['error'] !== '') {
    $errorMessage = (string)$_GET['error'];
}

$pageTitle = 'Services';
$pageSubtitle = 'Le choix du compte 706 exploite désormais la hiérarchie réelle et ne propose que les comptes finaux mouvementables.';
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
                                        <?= e($item['label'] . ' (' . $item['code'] . ')') ?><?= (int)$item['is_active'] !== 1 ? ' [archivé]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dashboard-note">
                            Un service actif doit dépendre d’un type actif. Le compte 706 proposé plus bas est limité aux comptes finaux mouvementables.
                        </div>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Nouveau code type d’opération</label>
                            <input type="text" name="new_operation_type_code" placeholder="Ex: FRAIS_DE_SERVICE">
                        </div>

                        <div>
                            <label>Nouveau libellé type d’opération</label>
                            <input type="text" name="new_operation_type_label" placeholder="Ex: Frais de service">
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
                            <label>Compte 706 (final mouvementable)</label>
                            <select name="service_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($serviceAccounts as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= (string)($editItem['service_account_id'] ?? '') === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(afs_service_account_display($item)) ?>
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
                                        <?= e($item['account_code'] . ' - ' . $item['account_label']) ?><?= (int)$item['is_active'] !== 1 ? ' [archivé]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;align-items:flex-end;">
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
                <h3 class="section-title">Hiérarchie 706</h3>
                <div class="dashboard-note">
                    Les comptes parents 706, 7061, 70611, etc. ne sont plus proposés ici. Seuls les comptes finaux postables sont sélectionnables pour rattacher un service.
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
                        <th>Compte parent 706</th>
                        <th>Compte 706 final</th>
                        <th>Compte 512</th>
                        <th>État</th>
                        <th>Cohérence</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $item): ?>
                        <?php
                        $isCoherent = !empty($item['operation_type_id'])
                            && (int)($item['operation_type_active'] ?? 0) === 1
                            && (
                                !empty($item['service_account_id']) || !empty($item['treasury_account_id'])
                            )
                            && (
                                empty($item['service_account_id']) ||
                                (
                                    (int)($item['service_account_active'] ?? 0) === 1 &&
                                    (int)($item['service_account_postable'] ?? 0) === 1
                                )
                            )
                            && (
                                empty($item['treasury_account_id']) ||
                                (int)($item['treasury_account_active'] ?? 0) === 1
                            );
                        ?>
                        <tr>
                            <td><?= e($item['code'] ?? '') ?></td>
                            <td><?= e($item['label'] ?? '') ?></td>
                            <td><?= e(trim((string)($item['operation_type_label'] ?? '') . ' (' . (string)($item['operation_type_code'] ?? '') . ')')) ?></td>
                            <td><?= e(trim((string)($item['service_account_parent_code'] ?? '') . ' - ' . (string)($item['service_account_parent_label'] ?? ''))) ?></td>
                            <td>
                                <?= e(trim((string)($item['service_account_code'] ?? '') . ' - ' . (string)($item['service_account_label'] ?? ''))) ?>
                                <?php if (!empty($item['service_account_destination_country_label']) || !empty($item['service_account_commercial_country_label'])): ?>
                                    <div class="muted">
                                        <?= e(trim(
                                            (!empty($item['service_account_destination_country_label']) ? 'Destination: ' . $item['service_account_destination_country_label'] : '')
                                            . (!empty($item['service_account_destination_country_label']) && !empty($item['service_account_commercial_country_label']) ? ' | ' : '')
                                            . (!empty($item['service_account_commercial_country_label']) ? 'Commercial: ' . $item['service_account_commercial_country_label'] : '')
                                        )) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= e(trim((string)($item['treasury_account_code'] ?? '') . ' - ' . (string)($item['treasury_account_label'] ?? ''))) ?></td>
                            <td><?= ((int)($item['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            <td><?= $isCoherent ? 'OK' : 'À corriger' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php?edit=<?= (int)$item['id'] ?>">Modifier</a>

                                    <?php if ((int)($item['is_active'] ?? 1) === 1): ?>
                                        <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php?archive=<?= (int)$item['id'] ?>" onclick="return confirm('Archiver ce service ?');">Archiver</a>
                                    <?php else: ?>
                                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php?restore=<?= (int)$item['id'] ?>">Réactiver</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="9">Aucun service.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>