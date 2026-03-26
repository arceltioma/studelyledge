<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

$pagePermission = 'manual_actions_create';
require_once __DIR__ . '/../../includes/permission_middleware.php';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';

$clients = $pdo->query("
    SELECT id, client_code, first_name, last_name
    FROM clients
    WHERE is_active = 1
    ORDER BY client_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$clientId = (int)($_POST['client_id'] ?? 0);
$operationDate = trim($_POST['operation_date'] ?? date('Y-m-d'));
$operationType = trim($_POST['operation_type'] ?? 'debit');
$labelChoice = trim($_POST['label_choice'] ?? '');
$labelOther = trim($_POST['label_other'] ?? '');
$amount = trim($_POST['amount'] ?? '');
$reference = trim($_POST['reference'] ?? '');

$labelChoices = [
    'service_fees' => 'Frais de services',
    'accounting_correction' => 'Correction comptable',
    'balance_adjustment' => 'Ajustement de solde',
    'entry_cancellation' => 'Annulation d’écriture',
    'manual_regularization' => 'Régularisation manuelle',
    'other' => 'Autre',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($clientId <= 0) {
            throw new Exception('Sélectionne un client.');
        }

        if ($operationDate === '') {
            throw new Exception('La date est obligatoire.');
        }

        if (!in_array($operationType, ['credit', 'debit'], true)) {
            throw new Exception('Le type doit être crédit ou débit.');
        }

        if ($labelChoice === '' || !array_key_exists($labelChoice, $labelChoices)) {
            throw new Exception('Le libellé doit être choisi dans la liste.');
        }

        if ($labelChoice === 'other' && $labelOther === '') {
            throw new Exception('Merci de préciser le libellé "Autre".');
        }

        if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
            throw new Exception('Le montant doit être numérique et supérieur à zéro.');
        }

        $stmtClient = $pdo->prepare("
            SELECT id
            FROM clients
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmtClient->execute([$clientId]);
        if (!$stmtClient->fetch()) {
            throw new Exception('Le client sélectionné est invalide.');
        }

        $finalLabel = $labelChoice === 'other' ? $labelOther : $labelChoices[$labelChoice];

        $stmtInsert = $pdo->prepare("
            INSERT INTO operations (
                client_id,
                operation_date,
                operation_type,
                operation_kind,
                label,
                amount,
                reference,
                source_type,
                created_by,
                created_at
            ) VALUES (?, ?, ?, 'manual', ?, ?, ?, 'manual', ?, NOW())
        ");

        $stmtInsert->execute([
            $clientId,
            $operationDate,
            $operationType,
            $finalLabel,
            (float)$amount,
            $reference !== '' ? $reference : null,
            (int)$_SESSION['user_id']
        ]);

        $operationId = (int)$pdo->lastInsertId();

        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'manual_operation_create',
            'manual_actions',
            'operation',
            $operationId,
            'Création d’une opération manuelle'
        );

        $successMessage = 'L’opération manuelle a été créée avec succès.';

        $clientId = 0;
        $operationDate = date('Y-m-d');
        $operationType = 'debit';
        $labelChoice = '';
        $labelOther = '';
        $amount = '';
        $reference = '';

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Action manuelle', 'Créer une écriture manuelle ciblée sur un client précis.'); ?>

        <?php if ($successMessage): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Créer une écriture manuelle</h3>

                <form method="POST">
                    <label for="client_id">Client</label>
                    <select name="client_id" id="client_id" required>
                        <option value="0">Choisir un client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int)$client['id'] ?>" <?= $clientId === (int)$client['id'] ? 'selected' : '' ?>>
                                <?= e($client['client_code'] . ' — ' . $client['first_name'] . ' ' . $client['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="dashboard-grid-2">
                        <div>
                            <label for="operation_date">Date</label>
                            <input type="date" name="operation_date" id="operation_date" value="<?= e($operationDate) ?>" required>
                        </div>

                        <div>
                            <label for="operation_type">Type</label>
                            <select name="operation_type" id="operation_type" required>
                                <option value="debit" <?= $operationType === 'debit' ? 'selected' : '' ?>>Débit</option>
                                <option value="credit" <?= $operationType === 'credit' ? 'selected' : '' ?>>Crédit</option>
                            </select>
                        </div>
                    </div>

                    <label for="label_choice">Libellé</label>
                    <select name="label_choice" id="label_choice" required>
                        <option value="">Choisir</option>
                        <?php foreach ($labelChoices as $key => $value): ?>
                            <option value="<?= e($key) ?>" <?= $labelChoice === $key ? 'selected' : '' ?>>
                                <?= e($value) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="label_other">Précision si "Autre"</label>
                    <input type="text" name="label_other" id="label_other" value="<?= e($labelOther) ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label for="amount">Montant</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="amount" value="<?= e($amount) ?>" required>
                        </div>

                        <div>
                            <label for="reference">Référence</label>
                            <input type="text" name="reference" id="reference" value="<?= e($reference) ?>">
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Créer l’écriture</button>
                    </div>
                </form>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>