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
    enforcePagePermission($pdo, 'operations_create');
}

if (!function_exists('sl_normalize_code')) {
    function sl_normalize_code(?string $value): string
    {
        $value = strtoupper(trim((string)$value));
        $value = str_replace(
            ['É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Ä', 'Î', 'Ï', 'Ô', 'Ö', 'Ù', 'Û', 'Ü', 'Ç', ' ', '-', '/', '\''],
            ['E', 'E', 'E', 'E', 'A', 'A', 'A', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'C', '_', '_', '_', ''],
            $value
        );
        $value = preg_replace('/[^A-Z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('sl_operation_service_map')) {
    function sl_operation_service_map(): array
    {
        return [
            'VERSEMENT' => ['VERSEMENT'],
            'VIREMENT' => ['INTERNE', 'MENSUEL', 'EXCEPTIONEL', 'REGULIER'],
            'REGULARISATION' => ['POSITIVE', 'NEGATIVE'],
            'FRAIS_SERVICE' => ['AVI', 'ATS'],
            'FRAIS_GESTION' => ['GESTION'],
            'COMMISSION_DE_TRANSFERT' => ['COMMISSION_DE_TRANSFERT'],
            'CA_PLACEMENT' => ['CA_PLACEMENT'],
            'CA_DIVERS' => ['CA_DIVERS'],
            'CA_LOGEMENT' => ['CA_LOGEMENT'],
            'CA_COURTAGE_PRET' => ['CA_COURTAGE_PRET'],
            'FRAIS_DEBOURDS_MICROFINANCE' => ['FRAIS_DEBOURDS_MICROFINANCE'],
        ];
    }
}

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0) {
    try {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ref_services WHERE operation_type_id = ?");
        $stmtCheck->execute([$deleteId]);
        $serviceCount = (int)$stmtCheck->fetchColumn();

        if ($serviceCount > 0) {
            throw new RuntimeException('Impossible de supprimer ce type : des services y sont encore rattachés.');
        }

        $stmt = $pdo->prepare("DELETE FROM ref_operation_types WHERE id = ? LIMIT 1");
        $stmt->execute([$deleteId]);
        header('Location: ' . APP_URL . 'modules/admin_functional/manage_operation_types.php?ok=deleted');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_operation_type'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $code = sl_normalize_code((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM ref_operation_types
            WHERE code = ?
            " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $params = [$code];
        if ($editId > 0) {
            $params[] = $editId;
        }
        $stmtDup->execute($params);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code existe déjà.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare("
                UPDATE ref_operation_types
                SET code = ?, label = ?, is_active = 1
                WHERE id = ?
            ");
            $stmt->execute([$code, $label, $editId]);
            $successMessage = 'Type d’opération mis à jour.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ref_operation_types (code, label, is_active, created_at)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$code, $label]);
            $editId = 0;
            $successMessage = 'Type d’opération créé.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ref_operation_types WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT
            rot.*,
            (SELECT COUNT(*) FROM ref_services rs WHERE rs.operation_type_id = rot.id) AS services_count
        FROM ref_operation_types rot
        ORDER BY rot.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$map = sl_operation_service_map();

$pageTitle = 'Types d’opérations';
$pageSubtitle = 'Le type d’opération pilote les services autorisés ensuite.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

    <div class="dashboard-grid-2">
        <div class="form-card">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="edit_id" value="<?= (int)($editItem['id'] ?? 0) ?>">

                <div>
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e($editItem['code'] ?? '') ?>" required>
                </div>

                <div>
                    <label>Libellé</label>
                    <input type="text" name="label" value="<?= e($editItem['label'] ?? '') ?>" required>
                </div>

                <?php
                $previewCode = sl_normalize_code($editItem['code'] ?? '');
                $suggestedServices = $map[$previewCode] ?? [];
                ?>
                <div style="margin-top:16px;">
                    <label>Services attendus pour ce type</label>
                    <div class="dashboard-note">
                        <?php if ($suggestedServices): ?>
                            <?= e(implode(' / ', $suggestedServices)) ?>
                        <?php else: ?>
                            Aucun mapping métier prédéfini pour ce code.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" name="save_operation_type" value="1" class="btn btn-primary"><?= $editItem ? 'Enregistrer' : 'Créer' ?></button>
                    <?php if ($editItem): ?>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="dashboard-panel">
            <h3 class="section-title">Lecture</h3>
            <div class="dashboard-note">
                Le type d’opération détermine la logique comptable utilisée ensuite dans les opérations et limite les services visibles.
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Services attendus</th>
                    <th>Services liés</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $normalized = sl_normalize_code($row['code'] ?? ''); ?>
                    <tr>
                        <td><?= e($row['code'] ?? '') ?></td>
                        <td><?= e($row['label'] ?? '') ?></td>
                        <td><?= e(implode(' / ', $map[$normalized] ?? [])) ?></td>
                        <td><?= (int)($row['services_count'] ?? 0) ?></td>
                        <td>
                            <div class="btn-group">
                                <a class="btn btn-secondary" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php?edit=<?= (int)$row['id'] ?>">Modifier</a>
                                <a class="btn btn-danger" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Supprimer ce type d’opération ?');">Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">Aucun type d’opération.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>