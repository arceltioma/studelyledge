<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operation_types_manage_page');
} else {
    enforcePagePermission($pdo, 'operation_types_manage');
}

if (!tableExists($pdo, 'ref_operation_types')) {
    exit('Table ref_operation_types introuvable.');
}

$pageTitle = 'Gérer les types d’opérations';
$pageSubtitle = 'Création et pilotage des types d’opérations avec prévisualisation avant validation';

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$directions = ['credit', 'debit', 'mixed'];

$formData = [
    'code' => '',
    'label' => '',
    'direction' => 'mixed',
    'is_active' => 1,
];

if (!function_exists('sl_manage_operation_type_value')) {
    function sl_manage_operation_type_value(array $data, string $key, mixed $default = ''): string
    {
        return e((string)($data[$key] ?? $default));
    }
}

if (!function_exists('sl_manage_operation_type_preview')) {
    function sl_manage_operation_type_preview(array $formData): array
    {
        return [
            'code' => strtoupper(trim((string)($formData['code'] ?? ''))),
            'label' => trim((string)($formData['label'] ?? '')),
            'direction' => trim((string)($formData['direction'] ?? 'mixed')),
            'is_active' => (int)($formData['is_active'] ?? 1),
        ];
    }
}

if (!function_exists('mot_like')) {
    function mot_like(string $value): string
    {
        return '%' . $value . '%';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'code' => strtoupper(trim((string)($_POST['code'] ?? ''))),
        'label' => trim((string)($_POST['label'] ?? '')),
        'direction' => trim((string)($_POST['direction'] ?? 'mixed')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['code'] === '') {
            throw new RuntimeException('Le code est obligatoire.');
        }

        if ($formData['label'] === '') {
            throw new RuntimeException('Le libellé est obligatoire.');
        }

        if (!in_array($formData['direction'], $directions, true)) {
            throw new RuntimeException('La direction sélectionnée est invalide.');
        }

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ref_operation_types WHERE code = ?");
        $stmtCheck->execute([$formData['code']]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Ce code existe déjà.');
        }

        $previewData = sl_manage_operation_type_preview($formData);
        $previewMode = true;

        if ($actionMode === 'save') {
            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'code' => $formData['code'],
                'label' => $formData['label'],
                'direction' => $formData['direction'],
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'ref_operation_types', $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'ref_operation_types', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }

            if (columnExists($pdo, 'ref_operation_types', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $stmtInsert = $pdo->prepare("
                INSERT INTO ref_operation_types (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmtInsert->execute($params);

            $newId = (int)$pdo->lastInsertId();

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_operation_type',
                    'admin_functional',
                    'operation_type',
                    $newId,
                    'Création d’un type d’opération'
                );
            }

            $successMessage = 'Type d’opération créé avec succès.';
            $previewMode = false;
            $previewData = null;

            $formData = [
                'code' => '',
                'label' => '',
                'direction' => 'mixed',
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
$filterDirection = trim((string)($_GET['filter_direction'] ?? ''));
$filterStatus = trim((string)($_GET['filter_status'] ?? ''));

$sqlRows = "
    SELECT rot.*,
           (
               SELECT COUNT(*)
               FROM ref_services rs
               WHERE rs.operation_type_id = rot.id
           ) AS linked_services_count
    FROM ref_operation_types rot
    WHERE 1=1
";
$paramsRows = [];

if ($filterSearch !== '') {
    $sqlRows .= " AND (rot.code LIKE ? OR rot.label LIKE ?) ";
    $paramsRows[] = mot_like($filterSearch);
    $paramsRows[] = mot_like($filterSearch);
}

if ($filterDirection !== '') {
    $sqlRows .= " AND COALESCE(rot.direction, 'mixed') = ? ";
    $paramsRows[] = $filterDirection;
}

if ($filterStatus === 'active') {
    $sqlRows .= " AND COALESCE(rot.is_active, 1) = 1 ";
} elseif ($filterStatus === 'inactive') {
    $sqlRows .= " AND COALESCE(rot.is_active, 1) = 0 ";
} elseif ($filterStatus === 'linked') {
    $sqlRows .= " AND EXISTS (SELECT 1 FROM ref_services rs WHERE rs.operation_type_id = rot.id) ";
} elseif ($filterStatus === 'unlinked') {
    $sqlRows .= " AND NOT EXISTS (SELECT 1 FROM ref_services rs WHERE rs.operation_type_id = rot.id) ";
}

$sqlRows .= " ORDER BY rot.label ASC, rot.id DESC ";

$stmtRows = $pdo->prepare($sqlRows);
$stmtRows->execute($paramsRows);
$rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

$dashboard = [
    'total' => count($rows),
    'active' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'credit' => count(array_filter($rows, fn($r) => (string)($r['direction'] ?? '') === 'credit')),
    'debit' => count(array_filter($rows, fn($r) => (string)($r['direction'] ?? '') === 'debit')),
    'mixed' => count(array_filter($rows, fn($r) => (string)($r['direction'] ?? '') === 'mixed')),
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
                <h3 class="section-title">Dashboard types d’opérations</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$dashboard['total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Actifs</span><strong><?= (int)$dashboard['active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Inactifs</span><strong><?= (int)$dashboard['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédit</span><strong><?= (int)$dashboard['credit'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Débit</span><strong><?= (int)$dashboard['debit'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Mixte</span><strong><?= (int)$dashboard['mixed'] ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Prévisualisation</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code</span><strong><?= e($previewData['code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($previewData['label']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Direction</span><strong><?= e($previewData['direction']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)$previewData['is_active'] === 1 ? 'Actif' : 'Inactif' ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Vérifie ici le type d’opération avant validation : code métier, libellé, direction comptable et statut.
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
                            <label>Code</label>
                            <input type="text" name="code" value="<?= sl_manage_operation_type_value($formData, 'code') ?>" required>
                        </div>

                        <div>
                            <label>Libellé</label>
                            <input type="text" name="label" value="<?= sl_manage_operation_type_value($formData, 'label') ?>" required>
                        </div>

                        <div>
                            <label>Direction</label>
                            <select name="direction" required>
                                <?php foreach ($directions as $direction): ?>
                                    <option value="<?= e($direction) ?>" <?= ($formData['direction'] ?? 'mixed') === $direction ? 'selected' : '' ?>>
                                        <?= e($direction) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)($formData['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                            Type actif
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                    </div>
                </form>
            </div>

            <div class="form-card">
                <h3 class="section-title">Filtres liste</h3>
                <form method="GET">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input type="text" name="filter_search" value="<?= e($filterSearch) ?>" placeholder="Code ou libellé">
                        </div>

                        <div>
                            <label>Direction</label>
                            <select name="filter_direction">
                                <option value="">Toutes</option>
                                <?php foreach ($directions as $direction): ?>
                                    <option value="<?= e($direction) ?>" <?= $filterDirection === $direction ? 'selected' : '' ?>>
                                        <?= e($direction) ?>
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
                                <option value="linked" <?= $filterStatus === 'linked' ? 'selected' : '' ?>>Avec services liés</option>
                                <option value="unlinked" <?= $filterStatus === 'unlinked' ? 'selected' : '' ?>>Sans service lié</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Liste des types d’opérations</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Direction</th>
                            <th>Statut</th>
                            <th>Services liés</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e((string)($row['code'] ?? '')) ?></td>
                                <td><?= e((string)($row['label'] ?? '')) ?></td>
                                <td><?= e((string)($row['direction'] ?? '')) ?></td>
                                <td><?= !empty($row['is_active']) ? 'Actif' : 'Inactif' ?></td>
                                <td><?= (int)($row['linked_services_count'] ?? 0) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/edit_operation_type.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="6">Aucun type d’opération trouvé.</td>
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