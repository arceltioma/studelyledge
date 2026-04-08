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

if (!tableExists($pdo, 'ref_services')) {
    exit('Table ref_services introuvable.');
}

$pageTitle = 'Gérer les services';
$pageSubtitle = 'Création et rattachement des services aux types d’opérations avec prévisualisation avant validation';

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
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

$formData = [
    'code' => '',
    'label' => '',
    'operation_type_id' => '',
    'service_account_id' => '',
    'treasury_account_id' => '',
    'is_active' => 1,
];

if (!function_exists('sl_manage_service_value')) {
    function sl_manage_service_value(array $data, string $key, mixed $default = ''): string
    {
        return e((string)($data[$key] ?? $default));
    }
}

if (!function_exists('sl_manage_find_row_by_id')) {
    function sl_manage_find_row_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('sl_manage_service_preview')) {
    function sl_manage_service_preview(array $formData, ?array $operationType, ?array $serviceAccount, ?array $treasuryAccount): array
    {
        return [
            'code' => strtoupper(trim((string)($formData['code'] ?? ''))),
            'label' => trim((string)($formData['label'] ?? '')),
            'operation_type_label' => (string)($operationType['label'] ?? ''),
            'operation_type_code' => (string)($operationType['code'] ?? ''),
            'service_account' => $serviceAccount ? (($serviceAccount['account_code'] ?? '') . ' - ' . ($serviceAccount['account_label'] ?? '')) : '',
            'treasury_account' => $treasuryAccount ? (($treasuryAccount['account_code'] ?? '') . ' - ' . ($treasuryAccount['account_label'] ?? '')) : '',
            'is_active' => (int)($formData['is_active'] ?? 1),
        ];
    }
}

if (!function_exists('ms_like')) {
    function ms_like(string $value): string
    {
        return '%' . $value . '%';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'code' => strtoupper(trim((string)($_POST['code'] ?? ''))),
        'label' => trim((string)($_POST['label'] ?? '')),
        'operation_type_id' => trim((string)($_POST['operation_type_id'] ?? '')),
        'service_account_id' => trim((string)($_POST['service_account_id'] ?? '')),
        'treasury_account_id' => trim((string)($_POST['treasury_account_id'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['code'] === '') {
            throw new RuntimeException('Le code du service est obligatoire.');
        }

        if ($formData['label'] === '') {
            throw new RuntimeException('Le libellé du service est obligatoire.');
        }

        if ($formData['operation_type_id'] === '') {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        $operationType = sl_manage_find_row_by_id($operationTypes, (int)$formData['operation_type_id']);
        if (!$operationType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        $serviceAccount = null;
        if ($formData['service_account_id'] !== '') {
            $serviceAccount = sl_manage_find_row_by_id($serviceAccounts, (int)$formData['service_account_id']);
            if (!$serviceAccount) {
                throw new RuntimeException('Compte de service introuvable.');
            }
        }

        $treasuryAccount = null;
        if ($formData['treasury_account_id'] !== '') {
            $treasuryAccount = sl_manage_find_row_by_id($treasuryAccounts, (int)$formData['treasury_account_id']);
            if (!$treasuryAccount) {
                throw new RuntimeException('Compte de trésorerie introuvable.');
            }
        }

        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM ref_services
            WHERE operation_type_id = ?
              AND code = ?
        ");
        $stmtCheck->execute([(int)$formData['operation_type_id'], $formData['code']]);

        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Ce code service existe déjà pour ce type d’opération.');
        }

        $previewData = sl_manage_service_preview($formData, $operationType, $serviceAccount, $treasuryAccount);
        $previewMode = true;

        if ($actionMode === 'save') {
            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'code' => $formData['code'],
                'label' => $formData['label'],
                'operation_type_id' => (int)$formData['operation_type_id'],
                'service_account_id' => $formData['service_account_id'] !== '' ? (int)$formData['service_account_id'] : null,
                'treasury_account_id' => $formData['treasury_account_id'] !== '' ? (int)$formData['treasury_account_id'] : null,
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'ref_services', $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'ref_services', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }

            if (columnExists($pdo, 'ref_services', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $stmtInsert = $pdo->prepare("
                INSERT INTO ref_services (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmtInsert->execute($params);

            $newId = (int)$pdo->lastInsertId();

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_service',
                    'admin_functional',
                    'service',
                    $newId,
                    'Création d’un service'
                );
            }

            $successMessage = 'Service créé avec succès.';
            $previewMode = false;
            $previewData = null;

            $formData = [
                'code' => '',
                'label' => '',
                'operation_type_id' => '',
                'service_account_id' => '',
                'treasury_account_id' => '',
                'is_active' => 1,
            ];
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        $previewMode = false;
        $previewData = null;
    }
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterTypeId = trim((string)($_GET['filter_type_id'] ?? ''));
$filter706Id = trim((string)($_GET['filter_706_id'] ?? ''));
$filter512Id = trim((string)($_GET['filter_512_id'] ?? ''));
$filterStatus = trim((string)($_GET['filter_status'] ?? ''));

$sqlRows = "
    SELECT
        rs.*,
        rot.label AS operation_type_label,
        rot.code AS operation_type_code,
        sa.account_code AS service_account_code,
        sa.account_label AS service_account_label,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
    FROM ref_services rs
    LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
    LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
    LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
    WHERE 1=1
";
$paramsRows = [];

if ($filterSearch !== '') {
    $sqlRows .= "
        AND (
            rs.code LIKE ?
            OR rs.label LIKE ?
            OR COALESCE(rot.label, '') LIKE ?
            OR COALESCE(rot.code, '') LIKE ?
            OR COALESCE(sa.account_code, '') LIKE ?
            OR COALESCE(sa.account_label, '') LIKE ?
            OR COALESCE(ta.account_code, '') LIKE ?
            OR COALESCE(ta.account_label, '') LIKE ?
        )
    ";
    for ($i = 0; $i < 8; $i++) {
        $paramsRows[] = ms_like($filterSearch);
    }
}

if ($filterTypeId !== '') {
    $sqlRows .= " AND rs.operation_type_id = ? ";
    $paramsRows[] = (int)$filterTypeId;
}

if ($filter706Id !== '') {
    $sqlRows .= " AND rs.service_account_id = ? ";
    $paramsRows[] = (int)$filter706Id;
}

if ($filter512Id !== '') {
    $sqlRows .= " AND rs.treasury_account_id = ? ";
    $paramsRows[] = (int)$filter512Id;
}

if ($filterStatus === 'active') {
    $sqlRows .= " AND COALESCE(rs.is_active, 1) = 1 ";
} elseif ($filterStatus === 'inactive') {
    $sqlRows .= " AND COALESCE(rs.is_active, 1) = 0 ";
} elseif ($filterStatus === 'with_706') {
    $sqlRows .= " AND rs.service_account_id IS NOT NULL ";
} elseif ($filterStatus === 'with_512') {
    $sqlRows .= " AND rs.treasury_account_id IS NOT NULL ";
} elseif ($filterStatus === 'without_link') {
    $sqlRows .= " AND rs.service_account_id IS NULL AND rs.treasury_account_id IS NULL ";
}

$sqlRows .= " ORDER BY rot.label ASC, rs.label ASC, rs.id DESC ";

$stmtRows = $pdo->prepare($sqlRows);
$stmtRows->execute($paramsRows);
$rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

$dashboard = [
    'total' => count($rows),
    'active' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'with_706' => count(array_filter($rows, fn($r) => !empty($r['service_account_id']))),
    'with_512' => count(array_filter($rows, fn($r) => !empty($r['treasury_account_id']))),
    'without_link' => count(array_filter($rows, fn($r) => empty($r['service_account_id']) && empty($r['treasury_account_id']))),
];

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

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Dashboard services</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$dashboard['total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Actifs</span><strong><?= (int)$dashboard['active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Inactifs</span><strong><?= (int)$dashboard['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Liés à un 706</span><strong><?= (int)$dashboard['with_706'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Liés à un 512</span><strong><?= (int)$dashboard['with_512'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Sans liaison</span><strong><?= (int)$dashboard['without_link'] ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Prévisualisation</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code</span><strong><?= e($previewData['code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($previewData['label']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Type d’opération</span><strong><?= e($previewData['operation_type_label']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Code type</span><strong><?= e($previewData['operation_type_code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 706</span><strong><?= e($previewData['service_account'] !== '' ? $previewData['service_account'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 512</span><strong><?= e($previewData['treasury_account'] !== '' ? $previewData['treasury_account'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)$previewData['is_active'] === 1 ? 'Actif' : 'Inactif' ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Vérifie ici le futur service : rattachement au type d’opération, compte 706, compte 512 et statut.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code service</label>
                            <input type="text" name="code" value="<?= sl_manage_service_value($formData, 'code') ?>" required>
                        </div>

                        <div>
                            <label>Libellé service</label>
                            <input type="text" name="label" value="<?= sl_manage_service_value($formData, 'label') ?>" required>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" <?= (string)($formData['operation_type_id'] ?? '') === (string)$type['id'] ? 'selected' : '' ?>>
                                        <?= e(($type['label'] ?? '') . ' (' . ($type['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 706 lié</label>
                            <select name="service_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($serviceAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($formData['service_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512 lié</label>
                            <select name="treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($formData['treasury_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)($formData['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                            Service actif
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                    </div>
                </form>
            </div>

            <div class="form-card">
                <h3 class="section-title">Filtres liste services</h3>
                <form method="GET">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input type="text" name="filter_search" value="<?= e($filterSearch) ?>" placeholder="Code, libellé, type, compte...">
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="filter_type_id">
                                <option value="">Tous</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" <?= $filterTypeId === (string)$type['id'] ? 'selected' : '' ?>>
                                        <?= e(($type['label'] ?? '') . ' (' . ($type['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 706</label>
                            <select name="filter_706_id">
                                <option value="">Tous</option>
                                <?php foreach ($serviceAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= $filter706Id === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512</label>
                            <select name="filter_512_id">
                                <option value="">Tous</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= $filter512Id === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Statut / liaison</label>
                            <select name="filter_status">
                                <option value="">Tous</option>
                                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actifs</option>
                                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                                <option value="with_706" <?= $filterStatus === 'with_706' ? 'selected' : '' ?>>Avec 706</option>
                                <option value="with_512" <?= $filterStatus === 'with_512' ? 'selected' : '' ?>>Avec 512</option>
                                <option value="without_link" <?= $filterStatus === 'without_link' ? 'selected' : '' ?>>Sans liaison</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Liste des services</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Type opération</th>
                            <th>Compte 706</th>
                            <th>Compte 512</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e((string)($row['code'] ?? '')) ?></td>
                                <td><?= e((string)($row['label'] ?? '')) ?></td>
                                <td><?= e((string)($row['operation_type_label'] ?? '')) ?></td>
                                <td><?= e(trim((string)($row['service_account_code'] ?? '') . ' - ' . (string)($row['service_account_label'] ?? ''))) ?></td>
                                <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                                <td><?= !empty($row['is_active']) ? 'Actif' : 'Inactif' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/edit_service.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="7">Aucun service trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>