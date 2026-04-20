<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

// DATA
$clients = $pdo->query("SELECT id, client_code, full_name FROM clients WHERE is_active=1 ORDER BY client_code")->fetchAll(PDO::FETCH_ASSOC);
$treasuryAccounts = $pdo->query("SELECT account_code, account_label FROM treasury_accounts WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$serviceAccounts = $pdo->query("SELECT account_code, account_label FROM service_accounts WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);

function getAccountType($code)
{
    if (str_starts_with($code, '411')) return '411';
    if (str_starts_with($code, '512')) return '512';
    if (str_starts_with($code, '706')) return '706';
    return 'unknown';
}

$success = '';
$error = '';
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new Exception('CSRF invalide');
        }

        $amount = (float)$_POST['amount'];
        $debit = trim($_POST['debit_account']);
        $credit = trim($_POST['credit_account']);
        $clientDebit = $_POST['client_debit'] ?? null;
        $clientCredit = $_POST['client_credit'] ?? null;

        if ($amount <= 0) throw new Exception('Montant invalide');
        if ($debit === '' || $credit === '') throw new Exception('Comptes obligatoires');
        if ($debit === $credit) throw new Exception('Débit et crédit doivent être différents');

        $typeDebit = getAccountType($debit);
        $typeCredit = getAccountType($credit);

        // Gestion clients obligatoire si 411
        if ($typeDebit === '411' && !$clientDebit) throw new Exception('Client débit requis');
        if ($typeCredit === '411' && !$clientCredit) throw new Exception('Client crédit requis');

        $preview = [
            'debit' => $debit,
            'credit' => $credit,
            'amount' => $amount
        ];

        if ($_POST['action'] === 'save') {

            $pdo->beginTransaction();

            // INSERT OPERATION
            $stmt = $pdo->prepare("
                INSERT INTO operations 
                (amount, operation_date, label, operation_kind)
                VALUES (?, NOW(), ?, 'manual')
            ");
            $stmt->execute([
                $amount,
                'Opération manuelle'
            ]);

            $operationId = $pdo->lastInsertId();

            // Écriture comptable
            $stmt2 = $pdo->prepare("
                INSERT INTO accounting_entries
                (operation_id, debit_account, credit_account, amount)
                VALUES (?, ?, ?, ?)
            ");
            $stmt2->execute([$operationId, $debit, $credit, $amount]);

            // UPDATE SOLDES (simplifié)
            $pdo->exec("UPDATE treasury_accounts SET balance = balance - $amount WHERE account_code = '$debit'");
            $pdo->exec("UPDATE treasury_accounts SET balance = balance + $amount WHERE account_code = '$credit'");

            // LOG + NOTIF
            logUserAction($pdo, $_SESSION['user_id'], 'manual_operation', 'operations', 'operation', $operationId, 'Création manuelle');
            createNotification($pdo, $_SESSION['user_id'], 'Nouvelle opération manuelle');

            $pdo->commit();

            $success = "Opération créée avec succès";
            $preview = null;
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main">
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<h2>Opération manuelle</h2>

<?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<form method="POST">
<?= csrf_input() ?>

<label>Montant</label>
<input type="number" step="0.01" name="amount" required>

<label>Compte débit</label>
<select name="debit_account" required>
<?php foreach ($treasuryAccounts as $a): ?>
<option value="<?= e($a['account_code']) ?>">
<?= e($a['account_code'].' '.$a['account_label']) ?>
</option>
<?php endforeach; ?>
<?php foreach ($serviceAccounts as $a): ?>
<option value="<?= e($a['account_code']) ?>">
<?= e($a['account_code'].' '.$a['account_label']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Compte crédit</label>
<select name="credit_account" required>
<?php foreach ($treasuryAccounts as $a): ?>
<option value="<?= e($a['account_code']) ?>">
<?= e($a['account_code'].' '.$a['account_label']) ?>
</option>
<?php endforeach; ?>
<?php foreach ($serviceAccounts as $a): ?>
<option value="<?= e($a['account_code']) ?>">
<?= e($a['account_code'].' '.$a['account_label']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Client débit (si 411)</label>
<select name="client_debit">
<option value="">-</option>
<?php foreach ($clients as $c): ?>
<option value="<?= $c['id'] ?>"><?= e($c['client_code'].' '.$c['full_name']) ?></option>
<?php endforeach; ?>
</select>

<label>Client crédit (si 411)</label>
<select name="client_credit">
<option value="">-</option>
<?php foreach ($clients as $c): ?>
<option value="<?= $c['id'] ?>"><?= e($c['client_code'].' '.$c['full_name']) ?></option>
<?php endforeach; ?>
</select>

<div class="btn-group">
<button name="action" value="preview" class="btn">Prévisualiser</button>
<button name="action" value="save" class="btn btn-success">Enregistrer</button>
</div>

</form>

<?php if ($preview): ?>
<div class="card">
<h3>Preview</h3>
Débit: <?= e($preview['debit']) ?><br>
Crédit: <?= e($preview['credit']) ?><br>
Montant: <?= e($preview['amount']) ?>
</div>
<?php endif; ?>

</div>
</div>