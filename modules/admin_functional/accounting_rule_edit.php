<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM accounting_rules WHERE id=?");
$stmt->execute([$id]);
$rule = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE accounting_rules
        SET
            rule_code=?,
            rule_label=?,
            debit_mode=?,
            credit_mode=?,
            requires_client=?,
            requires_manual_accounts=?,
            is_active=?
        WHERE id=?
    ");

    $stmt->execute([
        $_POST['rule_code'],
        $_POST['rule_label'],
        $_POST['debit_mode'],
        $_POST['credit_mode'],
        isset($_POST['requires_client']) ? 1 : 0,
        isset($_POST['requires_manual_accounts']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0,
        $id
    ]);

    header('Location: manage_accounting_rules.php');
    exit;
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<div class="main">

<h2>Modifier règle</h2>

<form method="POST">

<label>Code</label>
<input name="rule_code" value="<?= e($rule['rule_code']) ?>">

<label>Label</label>
<input name="rule_label" value="<?= e($rule['rule_label']) ?>">

<label>Débit</label>
<input name="debit_mode" value="<?= e($rule['debit_mode']) ?>">

<label>Crédit</label>
<input name="credit_mode" value="<?= e($rule['credit_mode']) ?>">

<label><input type="checkbox" name="requires_client" <?= $rule['requires_client'] ? 'checked' : '' ?>> Client</label>
<label><input type="checkbox" name="requires_manual_accounts" <?= $rule['requires_manual_accounts'] ? 'checked' : '' ?>> Manuel</label>
<label><input type="checkbox" name="is_active" <?= $rule['is_active'] ? 'checked' : '' ?>> Actif</label>

<button class="btn btn-success">Enregistrer</button>

</form>

</div>
</div>