<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_functional_edit');

$pageTitle = 'Créer règle comptable';

$operationTypes = $pdo->query("SELECT id, label FROM operation_types")->fetchAll();
$services = $pdo->query("SELECT id, label FROM services")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        INSERT INTO accounting_rules
        (operation_type_id, service_id, rule_code, rule_label,
         debit_mode, credit_mode,
         requires_client, requires_manual_accounts, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST['operation_type_id'],
        $_POST['service_id'],
        $_POST['rule_code'],
        $_POST['rule_label'],
        $_POST['debit_mode'],
        $_POST['credit_mode'],
        isset($_POST['requires_client']) ? 1 : 0,
        isset($_POST['requires_manual_accounts']) ? 1 : 0,
        1
    ]);

    header('Location: manage_accounting_rules.php');
    exit;
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main">
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="card">
<form method="POST">

<label>Type opération</label>
<select name="operation_type_id" required>
<?php foreach ($operationTypes as $ot): ?>
<option value="<?= $ot['id'] ?>"><?= e($ot['label']) ?></option>
<?php endforeach; ?>
</select>

<label>Service</label>
<select name="service_id" required>
<?php foreach ($services as $s): ?>
<option value="<?= $s['id'] ?>"><?= e($s['label']) ?></option>
<?php endforeach; ?>
</select>

<label>Code règle</label>
<input type="text" name="rule_code" required>

<label>Label</label>
<input type="text" name="rule_label">

<label>Mode débit</label>
<input type="text" name="debit_mode" placeholder="CLIENT_411">

<label>Mode crédit</label>
<input type="text" name="credit_mode" placeholder="SERVICE_706">

<label><input type="checkbox" name="requires_client"> Nécessite client</label>
<label><input type="checkbox" name="requires_manual_accounts"> Comptes manuels</label>

<button class="btn btn-success">Créer</button>

</form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>