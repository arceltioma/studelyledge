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

    $hasParent = false;
    $hasSort = false;

    try {
        $columns = $pdo->query("SHOW COLUMNS FROM service_accounts")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_map(fn($c) => $c['Field'], $columns);
        $hasParent = in_array('parent_account_id', $names, true);
        $hasSort = in_array('sort_order', $names, true);
    } catch (Throwable $e) {
        // fallback silencieux
    }

    $sql = "
        SELECT
            sa.id,
            sa.account_code,
            sa.account_label,
            sa.operation_type_label,
            sa.destination_country_label,
            sa.commercial_country_label,
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
        ORDER BY COALESCE(sa.sort_order,0) ASC, sa.account_code ASC
    ";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function afot_fetch_treasury_accounts(PDO $pdo): array
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

function afot_service_account_display(array $account): string
{
    $parts = [];
    $parts[] = $account['account_code'] ?? '';
    $parts[] = $account['account_label'] ?? '';

    $meta = [];
    if (!empty($account['parent_account_code']) || !empty($account['parent_account_label'])) {
        $meta[] = 'Parent: ' . trim(($account['parent_account_code'] ?? '') . ' ' . ($account['parent_account_label'] ?? ''));
    }
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

function afot_find_service_account(PDO $pdo, ?int $serviceAccountId): ?array
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

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$archiveId = (int)($_GET['archive'] ?? 0);
$restoreId = (int)($_GET['restore'] ?? 0);

if ($archiveId > 0) {
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM ref_services
        WHERE operation_type_id = ?
          AND COALESCE(is_active,1) = 1
    ");
    $stmtCheck->execute([$archiveId]);
    $activeChildren = (int)$stmtCheck->fetchColumn();

    if ($activeChildren > 0) {
        header('Location: ' . APP_URL . 'modules/admin_functional/manage_operation_types.php?error=' . urlencode('Impossible d’archiver ce type : des services actifs lui sont encore rattachés.'));
        exit;
    }

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

    if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'restore_operation_type',
            'admin_functional',
            'operation_type',
            $restoreId,
            'Réactivation d’un type d’opération'
        );
    }

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
            $stmtAttachCheck = $pdo->prepare("
                SELECT is_active
                FROM ref_services
                WHERE id = ?
                LIMIT 1
            ");

            $stmtAttach = $pdo->prepare("
                UPDATE ref_services
                SET operation_type_id = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($attachServiceIds as $serviceId) {
                $stmtAttachCheck->execute([$serviceId]);
                $serviceActive = $stmtAttachCheck->fetchColumn();

                if ($serviceActive === false) {
                    throw new RuntimeException('Un service à rattacher est introuvable.');
                }

                if ($isActive === 0 && (int)$serviceActive === 1) {
                    throw new RuntimeException('Impossible de rattacher un service actif à un type d’opération archivé.');
                }

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
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
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

            $selected706 = afot_find_service_account($pdo, $serviceAccountId);
            if ($serviceAccountId !== null && !$selected706) {
                throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" est introuvable.');
            }

            if ($selected706) {
                if ((int)($selected706['is_active'] ?? 0) !== 1) {
                    throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" est archivé.');
                }

                if ((int)($selected706['is_postable'] ?? 0) !== 1) {
                    throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" n’est pas mouvementable.');
                }

                if (!empty($selected706['operation_type_label'])) {
                    $normalized706Type = strtoupper(trim((string)$selected706['operation_type_label']));
                    $normalizedType = strtoupper(trim((string)$code));

                    if ($normalized706Type !== $normalizedType) {
                        throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" n’est pas cohérent avec le type d’opération.');
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
                    throw new RuntimeException('Le compte 512 sélectionné pour le service "' . $serviceCode . '" est introuvable.');
                }

                if ((int)$treasuryActive !== 1) {
                    throw new RuntimeException('Le compte 512 sélectionné pour le service "' . $serviceCode . '" est archivé.');
                }
            }

            $stmtInsertService->execute([
                $serviceCode,
                $serviceLabel,
                $operationTypeId,
                $serviceAccountId,
                $treasuryAccountId,
                $isActive
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
                'Création / mise à jour d’un type d’opération avec services et hiérarchie 706'
            );
        }

        $pdo->commit();

        $allServices = afot_fetch_services($pdo);
        $serviceAccounts = afot_fetch_service_accounts($pdo);
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
            COUNT(rs.id) AS linked_services_count,
            SUM(CASE WHEN COALESCE(rs.is_active,1) = 1 THEN 1 ELSE 0 END) AS linked_active_services_count
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
            rs.is_active,
            sa.account_code AS service_account_code,
            sa.account_label AS service_account_label
        FROM ref_services rs
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
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

if (isset($_GET['ok']) && $_GET['ok'] === 'archived') {
    $successMessage = 'Type d’opération archivé.';
}

if (isset($_GET['ok']) && $_GET['ok'] === 'restored') {
    $successMessage = 'Type d’opération réactivé.';
}

if (isset($_GET['error']) && $_GET['error'] !== '') {
    $errorMessage = (string)$_GET['error'];
}

$pageTitle = 'Types d’opérations';
$pageSubtitle = 'Les nouveaux services rattachés ici ne peuvent utiliser que des comptes 706 finaux mouvementables.';
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
                                    <?= e(($service['code'] ?? '') . ' - ' . ($service['label'] ?? '')) ?><?= (int)($service['is_active'] ?? 1) !== 1 ? ' [archivé]' : '' ?>
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
                                    <th>Compte 706 final</th>
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
                                                        <?= e(afot_service_account_display($account)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="new_treasury_account_id[]">
                                                <option value="">Aucun</option>
                                                <?php foreach ($treasuryAccounts as $account): ?>
                                                    <option value="<?= (int)$account['id'] ?>">
                                                        <?= e($account['account_code'] . ' - ' . $account['account_label']) ?><?= (int)$account['is_active'] !== 1 ? ' [archivé]' : '' ?>
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
                <h3 class="section-title">Hiérarchie 706</h3>
                <div class="dashboard-note">
                    Lors de la création de nouveaux services depuis cette page, seuls les comptes 706 finaux postables sont proposés. Les comptes parents 706, 7061, 70611, etc. sont exclus.
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
                        <th>Services actifs</th>
                        <th>État</th>
                        <th>Cohérence</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $item): ?>
                        <?php
                        $activeChildren = (int)($item['linked_active_services_count'] ?? 0);
                        $isCoherent = !(((int)($item['is_active'] ?? 1) === 0) && $activeChildren > 0);
                        ?>
                        <tr>
                            <td><?= e($item['code'] ?? '') ?></td>
                            <td><?= e($item['label'] ?? '') ?></td>
                            <td><?= e($item['direction'] ?? '') ?></td>
                            <td><?= (int)($item['linked_services_count'] ?? 0) ?></td>
                            <td><?= $activeChildren ?></td>
                            <td><?= ((int)($item['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            <td><?= $isCoherent ? 'OK' : 'À corriger' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php?edit=<?= (int)$item['id'] ?>">Modifier</a>

                                    <?php if ((int)($item['is_active'] ?? 1) === 1): ?>
                                        <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php?archive=<?= (int)$item['id'] ?>" onclick="return confirm('Archiver ce type d’opération ?');">Archiver</a>
                                    <?php else: ?>
                                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php?restore=<?= (int)$item['id'] ?>">Réactiver</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <?php $children = $servicesByType[(int)$item['id']] ?? []; ?>
                        <?php if ($children): ?>
                            <tr>
                                <td colspan="8">
                                    <strong>Services liés :</strong>
                                    <?php foreach ($children as $child): ?>
                                        <div>
                                            <?= e(($child['code'] ?? '') . ' - ' . ($child['label'] ?? '')) ?>
                                            <?php if (!empty($child['service_account_code'])): ?>
                                                — <?= e(($child['service_account_code'] ?? '') . ' - ' . ($child['service_account_label'] ?? '')) ?>
                                            <?php endif; ?>
                                            — <?= ((int)($child['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="8">Aucun type d’opération.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>