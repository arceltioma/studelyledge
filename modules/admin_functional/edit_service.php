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
    enforcePagePermission($pdo, 'services_manage');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Service invalide.');
}

if (!function_exists('es_fetch_service_accounts')) {
    function es_fetch_service_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return [];
        }

        $hasParent = false;
        $hasSort = false;

        try {
            $columns = $pdo->query("SHOW COLUMNS FROM service_accounts")->fetchAll(PDO::FETCH_ASSOC);
            $names = array_map(static fn($c) => $c['Field'], $columns);
            $hasParent = in_array('parent_account_id', $names, true);
            $hasSort = in_array('sort_order', $names, true);
        } catch (Throwable $e) {
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
}

if (!function_exists('es_service_account_display')) {
    function es_service_account_display(array $account): string
    {
        $base = trim((string)($account['account_code'] ?? '') . ' - ' . (string)($account['account_label'] ?? ''));
        $meta = [];

        if (!empty($account['parent_account_code']) || !empty($account['parent_account_label'])) {
            $meta[] = 'Parent: ' . trim((string)($account['parent_account_code'] ?? '') . ' ' . (string)($account['parent_account_label'] ?? ''));
        }
        if (!empty($account['operation_type_label'])) {
            $meta[] = (string)$account['operation_type_label'];
        }
        if (!empty($account['destination_country_label'])) {
            $meta[] = 'Destination: ' . (string)$account['destination_country_label'];
        }
        if (!empty($account['commercial_country_label'])) {
            $meta[] = 'Commercial: ' . (string)$account['commercial_country_label'];
        }

        if ($meta) {
            $base .= ' [' . implode(' | ', $meta) . ']';
        }

        return $base;
    }
}

if (!function_exists('es_find_operation_type')) {
    function es_find_operation_type(PDO $pdo, int $operationTypeId): ?array
    {
        if ($operationTypeId <= 0 || !tableExists($pdo, 'ref_operation_types')) {
            return null;
        }

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
}

if (!function_exists('es_find_service_account')) {
    function es_find_service_account(PDO $pdo, ?int $serviceAccountId): ?array
    {
        if ($serviceAccountId === null || $serviceAccountId <= 0 || !tableExists($pdo, 'service_accounts')) {
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
}

if (!function_exists('es_find_treasury_account')) {
    function es_find_treasury_account(PDO $pdo, ?int $treasuryAccountId): ?array
    {
        if ($treasuryAccountId === null || $treasuryAccountId <= 0 || !tableExists($pdo, 'treasury_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT id, account_code, account_label, is_active
            FROM treasury_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$treasuryAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
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

$serviceAccounts = es_fetch_service_accounts($pdo);

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
$previewMode = false;

$formData = [
    'code' => (string)($row['code'] ?? ''),
    'label' => (string)($row['label'] ?? ''),
    'operation_type_mode' => 'existing',
    'operation_type_id' => (string)($row['operation_type_id'] ?? ''),
    'new_operation_type_code' => '',
    'new_operation_type_label' => '',
    'new_operation_type_direction' => 'mixed',
    'service_account_id' => (string)($row['service_account_id'] ?? ''),
    'treasury_account_id' => (string)($row['treasury_account_id'] ?? ''),
    'is_active' => (int)($row['is_active'] ?? 1),
];

$previewData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'code' => trim((string)($_POST['code'] ?? '')),
        'label' => trim((string)($_POST['label'] ?? '')),
        'operation_type_mode' => trim((string)($_POST['operation_type_mode'] ?? 'existing')),
        'operation_type_id' => (string)($_POST['operation_type_id'] ?? ''),
        'new_operation_type_code' => trim((string)($_POST['new_operation_type_code'] ?? '')),
        'new_operation_type_label' => trim((string)($_POST['new_operation_type_label'] ?? '')),
        'new_operation_type_direction' => trim((string)($_POST['new_operation_type_direction'] ?? 'mixed')),
        'service_account_id' => (string)($_POST['service_account_id'] ?? ''),
        'treasury_account_id' => (string)($_POST['treasury_account_id'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $code = strtoupper($formData['code']);
        $label = $formData['label'];

        $operationTypeMode = in_array($formData['operation_type_mode'], ['existing', 'new'], true)
            ? $formData['operation_type_mode']
            : 'existing';

        $operationTypeId = $formData['operation_type_id'] !== '' ? (int)$formData['operation_type_id'] : null;
        $newOperationTypeCode = strtoupper($formData['new_operation_type_code']);
        $newOperationTypeLabel = $formData['new_operation_type_label'];
        $newOperationTypeDirection = in_array($formData['new_operation_type_direction'], ['credit', 'debit', 'mixed'], true)
            ? $formData['new_operation_type_direction']
            : 'mixed';

        $serviceAccountId = $formData['service_account_id'] !== '' ? (int)$formData['service_account_id'] : null;
        $treasuryAccountId = $formData['treasury_account_id'] !== '' ? (int)$formData['treasury_account_id'] : null;
        $isActive = (int)$formData['is_active'];

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé du service sont obligatoires.');
        }

        if ($serviceAccountId === null && $treasuryAccountId === null) {
            throw new RuntimeException('Le service doit être lié à un compte 706 ou 512.');
        }

        $resolvedOperationTypeId = null;
        $resolvedOperationType = null;
        $createdOperationTypeId = null;

        if ($operationTypeMode === 'new') {
            if ($newOperationTypeCode === '' || $newOperationTypeLabel === '') {
                throw new RuntimeException('Le code et le libellé du nouveau type d’opération sont obligatoires.');
            }

            $stmtDupType = $pdo->prepare("
                SELECT id, code, label, direction, is_active
                FROM ref_operation_types
                WHERE code = ?
                LIMIT 1
            ");
            $stmtDupType->execute([$newOperationTypeCode]);
            $existingType = $stmtDupType->fetch(PDO::FETCH_ASSOC);

            if ($existingType) {
                $resolvedOperationTypeId = (int)$existingType['id'];
                $resolvedOperationType = $existingType;
            } else {
                $resolvedOperationType = [
                    'id' => 0,
                    'code' => $newOperationTypeCode,
                    'label' => $newOperationTypeLabel,
                    'direction' => $newOperationTypeDirection,
                    'is_active' => 1,
                ];
            }
        } else {
            if ($operationTypeId === null || $operationTypeId <= 0) {
                throw new RuntimeException('Le type d’opération est obligatoire.');
            }

            $resolvedOperationTypeId = $operationTypeId;
            $resolvedOperationType = es_find_operation_type($pdo, $resolvedOperationTypeId);

            if (!$resolvedOperationType) {
                throw new RuntimeException('Le type d’opération sélectionné est introuvable.');
            }
        }

        if ($resolvedOperationType && $isActive === 1 && (int)($resolvedOperationType['is_active'] ?? 0) !== 1 && $operationTypeMode !== 'new') {
            throw new RuntimeException('Un service actif ne peut pas être rattaché à un type d’opération archivé.');
        }

        $selected706 = es_find_service_account($pdo, $serviceAccountId);
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

            if (!empty($selected706['operation_type_label']) && !empty($resolvedOperationType['code'])) {
                $normalizedAccountType = strtoupper(trim((string)$selected706['operation_type_label']));
                $normalizedParentType = strtoupper(trim((string)$resolvedOperationType['code']));

                if ($normalizedAccountType !== $normalizedParentType) {
                    throw new RuntimeException('Le compte 706 sélectionné n’est pas cohérent avec le type d’opération choisi.');
                }
            }
        }

        $selected512 = es_find_treasury_account($pdo, $treasuryAccountId);
        if ($treasuryAccountId !== null && !$selected512) {
            throw new RuntimeException('Le compte 512 sélectionné est introuvable.');
        }

        if ($selected512 && (int)($selected512['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Le compte 512 sélectionné est archivé.');
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

        $previewData = [
            'code' => $code,
            'label' => $label,
            'operation_type_mode' => $operationTypeMode,
            'operation_type' => $resolvedOperationType,
            'service_account' => $selected706,
            'treasury_account' => $selected512,
            'is_active' => $isActive,
        ];

        $previewMode = true;

        if ($actionMode === 'save') {
            if ($operationTypeMode === 'new' && ($resolvedOperationTypeId === null || $resolvedOperationTypeId <= 0)) {
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
                $createdOperationTypeId = (int)$pdo->lastInsertId();
                $resolvedOperationTypeId = $createdOperationTypeId;
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
                $resolvedOperationTypeId,
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
                    'Modification d’un service avec hiérarchie 706'
                );
            }

            $successMessage = 'Service mis à jour.';
            $previewMode = false;

            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;
            $serviceAccounts = es_fetch_service_accounts($pdo);

            $formData = [
                'code' => (string)($row['code'] ?? ''),
                'label' => (string)($row['label'] ?? ''),
                'operation_type_mode' => 'existing',
                'operation_type_id' => (string)($row['operation_type_id'] ?? ''),
                'new_operation_type_code' => '',
                'new_operation_type_label' => '',
                'new_operation_type_direction' => 'mixed',
                'service_account_id' => (string)($row['service_account_id'] ?? ''),
                'treasury_account_id' => (string)($row['treasury_account_id'] ?? ''),
                'is_active' => (int)($row['is_active'] ?? 1),
            ];
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$currentOperationType = !empty($row['operation_type_id']) ? es_find_operation_type($pdo, (int)$row['operation_type_id']) : null;
$current706 = !empty($row['service_account_id']) ? es_find_service_account($pdo, (int)$row['service_account_id']) : null;
$current512 = !empty($row['treasury_account_id']) ? es_find_treasury_account($pdo, (int)$row['treasury_account_id']) : null;

$pageTitle = 'Modifier un service';
$pageSubtitle = 'Seuls les comptes 706 finaux mouvementables peuvent être utilisés.';
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
                            <input type="text" name="code" value="<?= e($formData['code']) ?>" required>
                        </div>

                        <div>
                            <label>Libellé service</label>
                            <input type="text" name="label" value="<?= e($formData['label']) ?>" required>
                        </div>
                    </div>

                    <div>
                        <label>Mode de rattachement au type d’opération</label>
                        <select name="operation_type_mode" id="operation_type_mode">
                            <option value="existing" <?= $formData['operation_type_mode'] === 'existing' ? 'selected' : '' ?>>Rattacher à un type existant</option>
                            <option value="new" <?= $formData['operation_type_mode'] === 'new' ? 'selected' : '' ?>>Créer un nouveau type d’opération</option>
                        </select>
                    </div>

                    <div class="dashboard-grid-2" id="existing-operation-type-block">
                        <div>
                            <label>Type d’opération existant</label>
                            <select name="operation_type_id">
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $formData['operation_type_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(($item['label'] ?? '') . ' (' . ($item['code'] ?? '') . ')') ?><?= (int)($item['is_active'] ?? 1) !== 1 ? ' [archivé]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dashboard-note">
                            Le compte 706 choisi doit être cohérent avec le type d’opération sélectionné.
                        </div>
                    </div>

                    <div id="new-operation-type-block">
                        <div class="dashboard-grid-2">
                            <div>
                                <label>Nouveau code type d’opération</label>
                                <input type="text" name="new_operation_type_code" value="<?= e($formData['new_operation_type_code']) ?>" placeholder="Ex: FRAIS_DE_SERVICE">
                            </div>

                            <div>
                                <label>Nouveau libellé type d’opération</label>
                                <input type="text" name="new_operation_type_label" value="<?= e($formData['new_operation_type_label']) ?>" placeholder="Ex: Frais de service">
                            </div>
                        </div>

                        <div>
                            <label>Direction du nouveau type</label>
                            <select name="new_operation_type_direction">
                                <option value="mixed" <?= $formData['new_operation_type_direction'] === 'mixed' ? 'selected' : '' ?>>Mixte</option>
                                <option value="credit" <?= $formData['new_operation_type_direction'] === 'credit' ? 'selected' : '' ?>>Crédit</option>
                                <option value="debit" <?= $formData['new_operation_type_direction'] === 'debit' ? 'selected' : '' ?>>Débit</option>
                            </select>
                        </div>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Compte 706 (final mouvementable)</label>
                            <select name="service_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($serviceAccounts as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $formData['service_account_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(es_service_account_display($item)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512</label>
                            <select name="treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $formData['treasury_account_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(($item['account_code'] ?? '') . ' - ' . ($item['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;align-items:flex-end; margin-top:16px;">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                            Service actif
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title"><?= $previewMode ? 'Prévisualisation avant validation' : 'État actuel' ?></h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Code service</span>
                        <strong><?= e($previewMode ? ($previewData['code'] ?? '') : (string)($row['code'] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Libellé</span>
                        <strong><?= e($previewMode ? ($previewData['label'] ?? '') : (string)($row['label'] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Type d’opération</span>
                        <strong>
                            <?= e($previewMode
                                ? trim((string)(($previewData['operation_type']['code'] ?? '') . ' - ' . ($previewData['operation_type']['label'] ?? '')))
                                : trim((string)(($currentOperationType['code'] ?? '') . ' - ' . ($currentOperationType['label'] ?? '')))) ?>
                        </strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Compte 706</span>
                        <strong>
                            <?= e($previewMode
                                ? ($previewData['service_account'] ? es_service_account_display($previewData['service_account']) : 'Aucun')
                                : ($current706 ? es_service_account_display($current706) : 'Aucun')) ?>
                        </strong>
                    </div>

<div class="sl-data-list__row">
    <span>Compte 512</span>
    <strong>
        <?=
        e(
            $previewMode
                ? (
                    trim((string)(($previewData['treasury_account']['account_code'] ?? '') . ' - ' . ($previewData['treasury_account']['account_label'] ?? ''))) ?: 'Aucun'
                )
                : (
                    trim((string)(($current512['account_code'] ?? '') . ' - ' . ($current512['account_label'] ?? ''))) ?: 'Aucun'
                )
        )
        ?>
    </strong>
</div>

                    <div class="sl-data-list__row">
                        <span>Statut</span>
                        <strong><?= ((int)($previewMode ? ($previewData['is_active'] ?? 0) : ($row['is_active'] ?? 0)) === 1) ? 'Actif' : 'Archivé' ?></strong>
                    </div>
                </div>

                <div class="dashboard-note" style="margin-top:16px;">
                    Les comptes parents 706 ne sont pas sélectionnables. Seuls les comptes finaux postables sont proposés.
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modeSelect = document.getElementById('operation_type_mode');
            const existingBlock = document.getElementById('existing-operation-type-block');
            const newBlock = document.getElementById('new-operation-type-block');

            function refreshOperationTypeMode() {
                const mode = modeSelect ? modeSelect.value : 'existing';
                if (existingBlock) {
                    existingBlock.style.display = mode === 'existing' ? '' : 'none';
                }
                if (newBlock) {
                    newBlock.style.display = mode === 'new' ? '' : 'none';
                }
            }

            if (modeSelect) {
                modeSelect.addEventListener('change', refreshOperationTypeMode);
            }

            refreshOperationTypeMode();
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>