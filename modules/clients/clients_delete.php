<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'clients_delete');
}

if (!function_exists('sl_clients_delete_find_client_411_bank_account')) {
    function sl_clients_delete_find_client_411_bank_account(PDO $pdo, array $client): ?array
    {
        $clientId = (int)($client['id'] ?? 0);
        $generated411 = trim((string)($client['generated_client_account'] ?? ''));

        if ($clientId > 0 && tableExists($pdo, 'client_bank_accounts') && tableExists($pdo, 'bank_accounts')) {
            $stmt = $pdo->prepare("
                SELECT ba.*
                FROM client_bank_accounts cba
                INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
                WHERE cba.client_id = ?
                ORDER BY cba.id ASC
                LIMIT 1
            ");
            $stmt->execute([$clientId]);
            $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bankAccount) {
                return $bankAccount;
            }
        }

        if ($generated411 !== '' && tableExists($pdo, 'bank_accounts') && columnExists($pdo, 'bank_accounts', 'account_number')) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM bank_accounts
                WHERE account_number = ?
                LIMIT 1
            ");
            $stmt->execute([$generated411]);
            $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bankAccount) {
                return $bankAccount;
            }
        }

        return null;
    }
}

if (!function_exists('sl_clients_delete_get_active_treasury_accounts')) {
    function sl_clients_delete_get_active_treasury_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        $select = ['id'];

        foreach (['account_code', 'account_label', 'currency_code', 'is_active'] as $column) {
            if (columnExists($pdo, 'treasury_accounts', $column)) {
                $select[] = $column;
            }
        }

        $where = columnExists($pdo, 'treasury_accounts', 'is_active')
            ? "WHERE COALESCE(is_active,1)=1"
            : "";

        $stmt = $pdo->query("
            SELECT " . implode(', ', $select) . "
            FROM treasury_accounts
            $where
            ORDER BY account_code ASC, id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_clients_delete_find_treasury_account')) {
    function sl_clients_delete_find_treasury_account(PDO $pdo, int $treasuryId): ?array
    {
        if ($treasuryId <= 0 || !tableExists($pdo, 'treasury_accounts')) {
            return null;
        }

        $select = ['id'];
        foreach (['account_code', 'account_label', 'currency_code', 'is_active'] as $column) {
            if (columnExists($pdo, 'treasury_accounts', $column)) {
                $select[] = $column;
            }
        }

        $stmt = $pdo->prepare("
            SELECT " . implode(', ', $select) . "
            FROM treasury_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$treasuryId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return null;
        }

        if (array_key_exists('is_active', $row) && (int)$row['is_active'] !== 1) {
            return null;
        }

        return $row;
    }
}

if (!function_exists('sl_clients_delete_archive_bank_account')) {
    function sl_clients_delete_archive_bank_account(PDO $pdo, int $bankAccountId): void
    {
        if ($bankAccountId <= 0 || !tableExists($pdo, 'bank_accounts')) {
            return;
        }

        $sets = [];

        if (columnExists($pdo, 'bank_accounts', 'is_active')) {
            $sets[] = "is_active = 0";
        }
        if (columnExists($pdo, 'bank_accounts', 'archived_at')) {
            $sets[] = "archived_at = NOW()";
        }
        if (columnExists($pdo, 'bank_accounts', 'updated_at')) {
            $sets[] = "updated_at = NOW()";
        }

        if (!$sets) {
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE bank_accounts
            SET " . implode(', ', $sets) . "
            WHERE id = ?
        ");
        $stmt->execute([$bankAccountId]);
    }
}

if (!function_exists('sl_clients_delete_insert_transfer_operation')) {
    function sl_clients_delete_insert_transfer_operation(
        PDO $pdo,
        array $client,
        array $client411Account,
        array $treasuryAccount,
        float $amount,
        ?int $userId = null
    ): int {
        if ($amount <= 0) {
            return 0;
        }

        if (!tableExists($pdo, 'operations')) {
            throw new RuntimeException('Table operations introuvable pour historiser le transfert.');
        }

        $clientId = (int)($client['id'] ?? 0);
        $bankAccountId = (int)($client411Account['id'] ?? 0);
        $currencyCode = trim((string)(
            $client['currency']
            ?? $client411Account['currency_code']
            ?? $treasuryAccount['currency_code']
            ?? 'EUR'
        ));
        $clientCode = trim((string)($client['client_code'] ?? ''));
        $clientName = trim((string)($client['full_name'] ?? ''));
        $account411 = trim((string)($client['generated_client_account'] ?? $client411Account['account_number'] ?? ''));
        $account512 = trim((string)($treasuryAccount['account_code'] ?? ''));

        if ($account411 === '' || $account512 === '') {
            throw new RuntimeException('Impossible de déterminer les comptes 411/512 pour le transfert avant suppression.');
        }

        $reference = 'DEL-' . ($clientCode !== '' ? $clientCode : ('CLIENT-' . $clientId)) . '-' . date('YmdHis');
        $label = 'Transfert solde avant suppression client';
        if ($clientCode !== '') {
            $label .= ' - ' . $clientCode;
        }

        $notes = 'Suppression client'
            . ($clientCode !== '' ? ' ' . $clientCode : '')
            . ($clientName !== '' ? ' - ' . $clientName : '')
            . ' | Débit ' . $account512
            . ' / Crédit ' . $account411;

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_id' => $clientId > 0 ? $clientId : null,
            'bank_account_id' => $bankAccountId > 0 ? $bankAccountId : null,
            'linked_bank_account_id' => $bankAccountId > 0 ? $bankAccountId : null,
            'operation_date' => date('Y-m-d'),
            'amount' => $amount,
            'currency_code' => $currencyCode !== '' ? $currencyCode : 'EUR',
            'operation_type_code' => 'CLIENT_DELETE_TRANSFER',
            'operation_kind' => 'transfer',
            'label' => $label,
            'reference' => $reference,
            'notes' => $notes,
            'source_type' => 'client_delete',
            'debit_account_code' => $account512,
            'credit_account_code' => $account411,
            'created_by' => $userId,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'operations', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'operations', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (columnExists($pdo, 'operations', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            throw new RuntimeException('Aucune colonne exploitable pour historiser le transfert.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO operations (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        if (function_exists('recomputeAllBalances')) {
            recomputeAllBalances($pdo);
        }

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('sl_clients_delete_cancel_pending_debits')) {
    function sl_clients_delete_cancel_pending_debits(PDO $pdo, int $clientId, ?int $userId = null): array
    {
        $result = [
            'count' => 0,
            'ids' => [],
            'amount_total' => 0.0,
        ];

        if ($clientId <= 0 || !tableExists($pdo, 'pending_client_debits')) {
            return $result;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM pending_client_debits
            WHERE client_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$clientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$rows) {
            return $result;
        }

        foreach ($rows as $row) {
            $pendingId = (int)($row['id'] ?? 0);
            $status = strtolower(trim((string)($row['status'] ?? 'pending')));

            if ($pendingId <= 0 || in_array($status, ['resolved', 'settled', 'cancelled'], true)) {
                continue;
            }

            $remainingAmount = (float)($row['remaining_amount'] ?? $row['amount_due'] ?? 0);

            $sets = ["status = 'cancelled'"];
            if (columnExists($pdo, 'pending_client_debits', 'updated_at')) {
                $sets[] = "updated_at = NOW()";
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE pending_client_debits
                SET " . implode(', ', $sets) . "
                WHERE id = ?
            ");
            $stmtUpdate->execute([$pendingId]);

            if (function_exists('sl_create_pending_client_debit_log')) {
                sl_create_pending_client_debit_log(
                    $pdo,
                    $pendingId,
                    'cancel',
                    $status,
                    'cancelled',
                    $remainingAmount,
                    'Annulation automatique suite à suppression du client',
                    $userId
                );
            }

            $result['count']++;
            $result['ids'][] = $pendingId;
            $result['amount_total'] += $remainingAmount;
        }

        $result['amount_total'] = round($result['amount_total'], 2);

        return $result;
    }
}

$clientId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($clientId <= 0) {
    exit('Client invalide.');
}

if (!tableExists($pdo, 'clients')) {
    exit('Table clients introuvable.');
}

$stmt = $pdo->prepare("
    SELECT c.*
    FROM clients c
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$client411Account = sl_clients_delete_find_client_411_bank_account($pdo, $client);
$client411Balance = (float)($client411Account['balance'] ?? 0);
$client411InitialBalance = (float)($client411Account['initial_balance'] ?? 0);
$treasuryAccounts = sl_clients_delete_get_active_treasury_accounts($pdo);

$pendingDebitsSummary = [
    'total_count' => 0,
    'total_amount' => 0.0,
];

if (tableExists($pdo, 'pending_client_debits') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
    $amountExpr = '0';

    if (columnExists($pdo, 'pending_client_debits', 'remaining_amount')) {
        $amountExpr = 'COALESCE(remaining_amount, 0)';
    } elseif (columnExists($pdo, 'pending_client_debits', 'initial_amount')) {
        $amountExpr = 'COALESCE(initial_amount, 0)';
    }

    $statusCondition = columnExists($pdo, 'pending_client_debits', 'status')
        ? "LOWER(COALESCE(status, 'pending')) NOT IN ('resolved','settled','cancelled')"
        : '1=1';

    $stmtPending = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE
                WHEN {$statusCondition}
                THEN {$amountExpr}
                ELSE 0
            END), 0) AS total_amount
        FROM pending_client_debits
        WHERE client_id = ?
    ");
    $stmtPending->execute([$clientId]);
    $pendingDebitsSummary = $stmtPending->fetch(PDO::FETCH_ASSOC) ?: $pendingDebitsSummary;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $deleteMode = trim((string)($_POST['delete_mode'] ?? 'soft'));
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        $pdo->beginTransaction();

        $before = $client;

        if ($deleteMode === 'hard') {
            $pendingCancelResult = sl_clients_delete_cancel_pending_debits($pdo, $clientId, $userId);
            $transferOperationId = 0;

            if ($client411Balance < 0) {
                throw new RuntimeException('Le compte 411 du client est négatif. Une régularisation est nécessaire avant suppression.');
            }

            if ($client411Balance > 0) {
                $targetTreasuryId = (int)($_POST['target_treasury_account_id'] ?? 0);
                $targetTreasury = sl_clients_delete_find_treasury_account($pdo, $targetTreasuryId);

                if (!$targetTreasury) {
                    throw new RuntimeException('Merci de sélectionner un compte 512 valide pour transférer le solde du 411.');
                }

                $transferOperationId = sl_clients_delete_insert_transfer_operation(
                    $pdo,
                    $client,
                    $client411Account ?? [],
                    $targetTreasury,
                    $client411Balance,
                    $userId
                );
            }

            if ($client411Account && !empty($client411Account['id'])) {
                sl_clients_delete_archive_bank_account($pdo, (int)$client411Account['id']);
            }

            if (tableExists($pdo, 'client_bank_accounts')) {
                $stmtLinks = $pdo->prepare("DELETE FROM client_bank_accounts WHERE client_id = ?");
                $stmtLinks->execute([$clientId]);
            }

            $stmtDelete = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmtDelete->execute([$clientId]);

            if (function_exists('logUserAction') && $userId) {
                logUserAction(
                    $pdo,
                    $userId,
                    'delete_client_hard',
                    'clients',
                    'client',
                    $clientId,
                    'Suppression définitive du client '
                    . ((string)($before['client_code'] ?? ('#' . $clientId)))
                    . ' | solde 411: ' . number_format($client411Balance, 2, '.', '')
                    . ($transferOperationId > 0 ? ' | transfert opération #' . $transferOperationId : ' | sans transfert')
                    . ($client411Account ? ' | compte 411 archivé' : ' | aucun compte 411 trouvé')
                    . ($pendingCancelResult['count'] > 0
                        ? ' | débits dus annulés: ' . (int)$pendingCancelResult['count'] . ' | montant: ' . number_format((float)$pendingCancelResult['amount_total'], 2, '.', '')
                        : ' | aucun débit dû à annuler')
                );
            }

            if (function_exists('createNotification') && $userId) {
                createNotification(
                    $pdo,
                    'client_delete',
                    'Client supprimé définitivement : '
                    . ((string)($before['client_code'] ?? '') . ' - ' . (string)($before['full_name'] ?? ''))
                    . ' | Solde 411 traité : ' . number_format($client411Balance, 2, ',', ' ')
                    . ($transferOperationId > 0 ? ' | opération #' . $transferOperationId : '')
                    . ($pendingCancelResult['count'] > 0
                        ? ' | débits dus annulés : ' . (int)$pendingCancelResult['count']
                        : ''),
                    'warning',
                    APP_URL . 'modules/clients/clients_list.php',
                    'client',
                    $clientId,
                    $userId
                );
            }

            $pdo->commit();
            header('Location: ' . APP_URL . 'modules/clients/clients_list.php?deleted=1');
            exit;
        }

        $updated = false;

        if (columnExists($pdo, 'clients', 'is_active')) {
            $sql = "UPDATE clients SET is_active = 0";
            if (columnExists($pdo, 'clients', 'updated_at')) {
                $sql .= ", updated_at = NOW()";
            }
            $sql .= " WHERE id = ?";

            $stmtArchive = $pdo->prepare($sql);
            $stmtArchive->execute([$clientId]);
            $updated = true;
        } elseif (columnExists($pdo, 'clients', 'is_deleted')) {
            $sql = "UPDATE clients SET is_deleted = 1";
            if (columnExists($pdo, 'clients', 'updated_at')) {
                $sql .= ", updated_at = NOW()";
            }
            $sql .= " WHERE id = ?";

            $stmtArchive = $pdo->prepare($sql);
            $stmtArchive->execute([$clientId]);
            $updated = true;
        } elseif (columnExists($pdo, 'clients', 'deleted_at')) {
            $sql = "UPDATE clients SET deleted_at = NOW()";
            if (columnExists($pdo, 'clients', 'updated_at')) {
                $sql .= ", updated_at = NOW()";
            }
            $sql .= " WHERE id = ?";

            $stmtArchive = $pdo->prepare($sql);
            $stmtArchive->execute([$clientId]);
            $updated = true;
        } else {
            if (tableExists($pdo, 'client_bank_accounts')) {
                $stmtLinks = $pdo->prepare("DELETE FROM client_bank_accounts WHERE client_id = ?");
                $stmtLinks->execute([$clientId]);
            }

            $stmtDelete = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            $stmtDelete->execute([$clientId]);

            if (function_exists('logUserAction') && $userId) {
                logUserAction(
                    $pdo,
                    $userId,
                    'delete_client_fallback',
                    'clients',
                    'client',
                    $clientId,
                    'Suppression fallback du client ' . ((string)($before['client_code'] ?? ('#' . $clientId)))
                );
            }

            if (function_exists('createNotification') && $userId) {
                createNotification(
                    $pdo,
                    'client_delete',
                    'Client supprimé : ' . ((string)($before['client_code'] ?? '') . ' - ' . (string)($before['full_name'] ?? '')),
                    'warning',
                    APP_URL . 'modules/clients/clients_list.php',
                    'client',
                    $clientId,
                    $userId
                );
            }

            $pdo->commit();
            header('Location: ' . APP_URL . 'modules/clients/clients_list.php?deleted=1');
            exit;
        }

        if ($updated) {
            if (function_exists('auditEntityChanges') && $userId) {
                $stmtAfter = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
                $stmtAfter->execute([$clientId]);
                $after = $stmtAfter->fetch(PDO::FETCH_ASSOC) ?: [];

                auditEntityChanges($pdo, 'client', $clientId, $before, $after, $userId);
            }

            if (function_exists('logUserAction') && $userId) {
                logUserAction(
                    $pdo,
                    $userId,
                    'archive_client',
                    'clients',
                    'client',
                    $clientId,
                    'Archivage du client ' . ((string)($before['client_code'] ?? ('#' . $clientId)))
                );
            }

            if (function_exists('createNotification') && $userId) {
                createNotification(
                    $pdo,
                    'client_archive',
                    'Client archivé : ' . ((string)($before['client_code'] ?? '') . ' - ' . (string)($before['full_name'] ?? '')),
                    'info',
                    APP_URL . 'modules/clients/clients_list.php',
                    'client',
                    $clientId,
                    $userId
                );
            }
        }

        $pdo->commit();
        header('Location: ' . APP_URL . 'modules/clients/clients_list.php?archived=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Supprimer un client';
$pageSubtitle = 'Archivage ou suppression définitive du client avec traitement du compte 411 et des débits dus';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Confirmation</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>ID</span><strong><?= (int)$clientId ?></strong></div>
                    <div class="sl-data-list__row"><span>Code client</span><strong><?= e((string)($client['client_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e((string)($client['full_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e((string)($client['generated_client_account'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Type</span><strong><?= e((string)($client['client_type'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Inactif' ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde initial 411</span><strong><?= e(number_format($client411InitialBalance, 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant 411</span><strong><?= e(number_format($client411Balance, 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débits dus ouverts</span><strong><?= (int)($pendingDebitsSummary['total_count'] ?? 0) ?></strong></div>
                    <div class="sl-data-list__row"><span>Montant débits dus</span><strong><?= e(number_format((float)($pendingDebitsSummary['total_amount'] ?? 0), 2, ',', ' ')) ?></strong></div>
                </div>

                <form method="POST" style="margin-top:20px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$clientId ?>">

                    <div style="display:grid; gap:12px;">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="delete_mode" value="soft" checked>
                            Archiver le client uniquement
                        </label>

                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="radio" name="delete_mode" value="hard">
                            Supprimer définitivement le client
                        </label>
                    </div>

                    <?php if ($client411Balance > 0): ?>
                        <div style="margin-top:18px;">
                            <label for="target_treasury_account_id">Compte 512 de réception du solde 411</label>
                            <select name="target_treasury_account_id" id="target_treasury_account_id">
                                <option value="">Choisir un compte 512</option>
                                <?php foreach ($treasuryAccounts as $treasury): ?>
                                    <option value="<?= (int)($treasury['id'] ?? 0) ?>">
                                        <?= e(trim((string)($treasury['account_code'] ?? '') . ' - ' . (string)($treasury['account_label'] ?? '')) ?: ('Compte #' . (int)($treasury['id'] ?? 0))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="dashboard-note" style="margin-top:10px;">
                                En suppression définitive, le solde du 411 sera transféré par écriture comptable
                                <strong>Débit 512 / Crédit 411</strong>, puis le compte 411 sera archivé.
                            </div>
                        </div>
                    <?php elseif ($client411Balance == 0.0): ?>
                        <div class="dashboard-note" style="margin-top:18px;">
                            Le compte 411 est à <strong>0,00</strong>. En suppression définitive, le client sera supprimé et le compte 411 sera simplement archivé.
                        </div>
                    <?php else: ?>
                        <div class="warning" style="margin-top:18px;">
                            Le compte 411 est négatif. La suppression définitive est bloquée tant qu’une régularisation n’a pas été effectuée.
                        </div>
                    <?php endif; ?>

                    <?php if ((int)($pendingDebitsSummary['total_count'] ?? 0) > 0): ?>
                        <div class="warning" style="margin-top:18px;">
                            Ce client a des <strong>débits dus ouverts</strong>.  
                            En suppression définitive, ils seront automatiquement passés au statut <strong>cancelled</strong> avant suppression du client.
                        </div>
                    <?php endif; ?>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-danger">Confirmer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$clientId ?>" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Règles de suppression</h3>
                <div class="dashboard-note">
                    <strong>Archivage simple :</strong> le client reste en base mais devient inactif.<br><br>
                    <strong>Suppression définitive :</strong><br>
                    - si le 411 = 0 : suppression du client + archivage du compte 411<br>
                    - si le 411 &gt; 0 : transfert vers un 512 sélectionné + archivage du compte 411 + suppression du client<br>
                    - si le 411 &lt; 0 : suppression bloquée pour éviter une incohérence comptable<br>
                    - si des débits dus existent : ils sont automatiquement annulés avant suppression
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const hardRadio = document.querySelector('input[name="delete_mode"][value="hard"]');
            const treasurySelect = document.getElementById('target_treasury_account_id');

            if (!hardRadio || !treasurySelect) {
                return;
            }

            function syncTreasuryRequirement() {
                const mustRequire = hardRadio.checked && <?= json_encode($client411Balance > 0) ?>;
                treasurySelect.required = !!mustRequire;
            }

            document.querySelectorAll('input[name="delete_mode"]').forEach(function (radio) {
                radio.addEventListener('change', syncTreasuryRequirement);
            });

            syncTreasuryRequirement();
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>