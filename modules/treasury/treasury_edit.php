<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'treasury_edit');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de trésorerie invalide.');
}

if (!tableExists($pdo, 'treasury_accounts')) {
    exit('Table treasury_accounts introuvable.');
}

$stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de trésorerie introuvable.');
}

$pageTitle = 'Modifier un compte de trésorerie';
$pageSubtitle = 'Mise à jour sécurisée du compte interne avec prévisualisation';

$currencies = function_exists('sl_get_currency_options')
    ? sl_get_currency_options($pdo)
    : [['code' => 'EUR', 'label' => 'Euro']];

$commercialCountries = function_exists('studely_commercial_countries')
    ? studely_commercial_countries()
    : [];

$countryTypes = ['Filiale', 'Partenaire', 'Siège', 'Autre'];
$paymentPlaces = ['Local', 'International', 'Mixte'];

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$formData = [
    'account_code' => $account['account_code'] ?? '',
    'account_label' => $account['account_label'] ?? '',
    'bank_name' => $account['bank_name'] ?? '',
    'subsidiary_name' => $account['subsidiary_name'] ?? '',
    'zone_code' => $account['zone_code'] ?? '',
    'country_label' => $account['country_label'] ?? '',
    'country_type' => $account['country_type'] ?? 'Filiale',
    'payment_place' => $account['payment_place'] ?? 'Local',
    'currency_code' => $account['currency_code'] ?? 'EUR',
    'opening_balance' => (string)($account['opening_balance'] ?? '0'),
    'current_balance' => (string)($account['current_balance'] ?? '0'),
    'is_active' => (int)($account['is_active'] ?? 1),
];

if (!function_exists('sl_treasury_edit_build_preview')) {
    function sl_treasury_edit_build_preview(array $formData, array $account): array
    {
        $openingBalance = (float)($formData['opening_balance'] ?? 0);
        $currentBalance = (float)($formData['current_balance'] ?? 0);
        $oldOpening = (float)($account['opening_balance'] ?? 0);
        $oldCurrent = (float)($account['current_balance'] ?? 0);

        return [
            'account_code' => trim((string)($formData['account_code'] ?? '')),
            'account_label' => trim((string)($formData['account_label'] ?? '')),
            'bank_name' => trim((string)($formData['bank_name'] ?? '')),
            'subsidiary_name' => trim((string)($formData['subsidiary_name'] ?? '')),
            'zone_code' => trim((string)($formData['zone_code'] ?? '')),
            'country_label' => trim((string)($formData['country_label'] ?? '')),
            'country_type' => trim((string)($formData['country_type'] ?? '')),
            'payment_place' => trim((string)($formData['payment_place'] ?? '')),
            'currency_code' => trim((string)($formData['currency_code'] ?? 'EUR')),
            'opening_balance' => $openingBalance,
            'current_balance' => $currentBalance,
            'old_opening_balance' => $oldOpening,
            'old_current_balance' => $oldCurrent,
            'opening_delta' => $openingBalance - $oldOpening,
            'current_delta' => $currentBalance - $oldCurrent,
            'is_active' => (int)($formData['is_active'] ?? 0),
        ];
    }
}

if (!function_exists('sl_create_notification_if_possible')) {
    function sl_create_notification_if_possible(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $linkUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $createdBy = null
    ): void {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_by' => $createdBy > 0 ? $createdBy : null,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'account_code' => trim((string)($_POST['account_code'] ?? '')),
        'account_label' => trim((string)($_POST['account_label'] ?? '')),
        'bank_name' => trim((string)($_POST['bank_name'] ?? '')),
        'subsidiary_name' => trim((string)($_POST['subsidiary_name'] ?? '')),
        'zone_code' => trim((string)($_POST['zone_code'] ?? '')),
        'country_label' => trim((string)($_POST['country_label'] ?? '')),
        'country_type' => trim((string)($_POST['country_type'] ?? 'Filiale')),
        'payment_place' => trim((string)($_POST['payment_place'] ?? 'Local')),
        'currency_code' => trim((string)($_POST['currency_code'] ?? 'EUR')),
        'opening_balance' => trim((string)($_POST['opening_balance'] ?? '0')),
        'current_balance' => trim((string)($_POST['current_balance'] ?? '0')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['account_code'] === '') {
            throw new RuntimeException('Le code compte est obligatoire.');
        }

        if ($formData['account_label'] === '') {
            throw new RuntimeException('L’intitulé est obligatoire.');
        }

        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM treasury_accounts
            WHERE account_code = ?
              AND id <> ?
        ");
        $stmtCheck->execute([$formData['account_code'], $id]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Un autre compte utilise déjà ce code.');
        }

        if ($actionMode === 'preview') {
            $previewMode = true;
            $previewData = sl_treasury_edit_build_preview($formData, $account);
        }

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $fields = [];
            $params = [];

            $map = [
                'account_code' => $formData['account_code'],
                'account_label' => $formData['account_label'],
                'bank_name' => $formData['bank_name'] !== '' ? $formData['bank_name'] : null,
                'subsidiary_name' => $formData['subsidiary_name'] !== '' ? $formData['subsidiary_name'] : null,
                'zone_code' => $formData['zone_code'] !== '' ? $formData['zone_code'] : null,
                'country_label' => $formData['country_label'] !== '' ? $formData['country_label'] : null,
                'country_type' => $formData['country_type'] !== '' ? $formData['country_type'] : null,
                'payment_place' => $formData['payment_place'] !== '' ? $formData['payment_place'] : null,
                'currency_code' => $formData['currency_code'] !== '' ? $formData['currency_code'] : 'EUR',
                'opening_balance' => (float)$formData['opening_balance'],
                'current_balance' => (float)$formData['current_balance'],
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'treasury_accounts', $column)) {
                    $fields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            $params[] = $id;

            $stmtUpdate = $pdo->prepare("
                UPDATE treasury_accounts
                SET " . implode(', ', $fields) . "
                WHERE id = ?
            ");
            $stmtUpdate->execute($params);

            if (function_exists('recomputeAllBalances')) {
                recomputeAllBalances($pdo);
            }

            $userId = (int)($_SESSION['user_id'] ?? 0);

            if (function_exists('logUserAction') && $userId > 0) {
                logUserAction(
                    $pdo,
                    $userId,
                    'edit_treasury_account',
                    'treasury',
                    'treasury_account',
                    $id,
                    'Modification du compte de trésorerie ' . $formData['account_code'] . ' - ' . $formData['account_label']
                );
            }

            sl_create_notification_if_possible(
                $pdo,
                'treasury_update',
                'Compte de trésorerie mis à jour : ' . $formData['account_code'] . ' - ' . $formData['account_label'],
                'info',
                APP_URL . 'modules/treasury/treasury_view.php?id=' . $id,
                'treasury_account',
                $id,
                $userId
            );

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: $account;

            $successMessage = 'Compte de trésorerie mis à jour avec succès.';
            $previewMode = false;
            $previewData = null;

            $formData = [
                'account_code' => $account['account_code'] ?? '',
                'account_label' => $account['account_label'] ?? '',
                'bank_name' => $account['bank_name'] ?? '',
                'subsidiary_name' => $account['subsidiary_name'] ?? '',
                'zone_code' => $account['zone_code'] ?? '',
                'country_label' => $account['country_label'] ?? '',
                'country_type' => $account['country_type'] ?? 'Filiale',
                'payment_place' => $account['payment_place'] ?? 'Local',
                'currency_code' => $account['currency_code'] ?? 'EUR',
                'opening_balance' => (string)($account['opening_balance'] ?? '0'),
                'current_balance' => (string)($account['current_balance'] ?? '0'),
                'is_active' => (int)($account['is_active'] ?? 1),
            ];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
        $previewMode = false;
        $previewData = null;
    }
}

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
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="dashboard-grid-2">
                <div>
                    <label>Code compte</label>
                    <input type="text" name="account_code" value="<?= e($formData['account_code']) ?>" required>
                </div>

                <div>
                    <label>Intitulé</label>
                    <input type="text" name="account_label" value="<?= e($formData['account_label']) ?>" required>
                </div>

                <div>
                    <label>Banque</label>
                    <input type="text" name="bank_name" value="<?= e($formData['bank_name']) ?>">
                </div>

                <div>
                    <label>Filiale</label>
                    <input type="text" name="subsidiary_name" value="<?= e($formData['subsidiary_name']) ?>">
                </div>

                <div>
                    <label>Zone code</label>
                    <input type="text" name="zone_code" value="<?= e($formData['zone_code']) ?>">
                </div>

                <div>
                    <label>Pays</label>
                    <select name="country_label">
                        <option value="">Choisir</option>
                        <?php foreach ($commercialCountries as $country): ?>
                            <option value="<?= e($country) ?>" <?= $formData['country_label'] === $country ? 'selected' : '' ?>>
                                <?= e($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Type de pays</label>
                    <select name="country_type">
                        <?php foreach ($countryTypes as $item): ?>
                            <option value="<?= e($item) ?>" <?= $formData['country_type'] === $item ? 'selected' : '' ?>>
                                <?= e($item) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Lieu de paiement</label>
                    <select name="payment_place">
                        <?php foreach ($paymentPlaces as $item): ?>
                            <option value="<?= e($item) ?>" <?= $formData['payment_place'] === $item ? 'selected' : '' ?>>
                                <?= e($item) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Devise</label>
                    <select name="currency_code">
                        <?php foreach ($currencies as $currency): ?>
                            <option value="<?= e($currency['code']) ?>" <?= $formData['currency_code'] === $currency['code'] ? 'selected' : '' ?>>
                                <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Solde ouverture</label>
                    <input type="number" step="0.01" name="opening_balance" value="<?= e($formData['opening_balance']) ?>">
                </div>

                <div>
                    <label>Solde courant</label>
                    <input type="number" step="0.01" name="current_balance" value="<?= e($formData['current_balance']) ?>">
                </div>
            </div>

            <div style="margin-top:10px;">
                <label style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                    Compte actif
                </label>
            </div>

            <div class="btn-group" style="margin-top:20px;">
                <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                <a href="<?= e(APP_URL) ?>modules/treasury/treasury_view.php?id=<?= (int)$id ?>" class="btn btn-secondary">Voir</a>
                <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Retour</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3><?= $previewMode ? 'Prévisualisation avant mise à jour' : 'État actuel' ?></h3>

        <?php if ($previewMode && $previewData): ?>
            <div class="sl-data-list">
                <div class="sl-data-list__row"><span>Code</span><strong><?= e($previewData['account_code']) ?></strong></div>
                <div class="sl-data-list__row"><span>Intitulé</span><strong><?= e($previewData['account_label']) ?></strong></div>
                <div class="sl-data-list__row"><span>Banque</span><strong><?= e($previewData['bank_name'] !== '' ? $previewData['bank_name'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Filiale</span><strong><?= e($previewData['subsidiary_name'] !== '' ? $previewData['subsidiary_name'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Zone</span><strong><?= e($previewData['zone_code'] !== '' ? $previewData['zone_code'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Pays</span><strong><?= e($previewData['country_label'] !== '' ? $previewData['country_label'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Type de pays</span><strong><?= e($previewData['country_type'] !== '' ? $previewData['country_type'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Lieu de paiement</span><strong><?= e($previewData['payment_place'] !== '' ? $previewData['payment_place'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Devise</span><strong><?= e($previewData['currency_code']) ?></strong></div>
                <div class="sl-data-list__row"><span>Ouverture actuelle</span><strong><?= number_format((float)$previewData['old_opening_balance'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Nouvelle ouverture</span><strong><?= number_format((float)$previewData['opening_balance'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Écart ouverture</span><strong><?= number_format((float)$previewData['opening_delta'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Courant actuel</span><strong><?= number_format((float)$previewData['old_current_balance'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Nouveau courant</span><strong><?= number_format((float)$previewData['current_balance'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Écart courant</span><strong><?= number_format((float)$previewData['current_delta'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)$previewData['is_active'] === 1 ? 'Actif' : 'Archivé' ?></strong></div>
            </div>
        <?php else: ?>
            <div class="sl-data-list">
                <div class="sl-data-list__row"><span>Code</span><strong><?= e((string)($account['account_code'] ?? '')) ?></strong></div>
                <div class="sl-data-list__row"><span>Intitulé</span><strong><?= e((string)($account['account_label'] ?? '')) ?></strong></div>
                <div class="sl-data-list__row"><span>Solde ouverture</span><strong><?= e(number_format((float)($account['opening_balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                <div class="sl-data-list__row"><span>Solde courant</span><strong><?= e(number_format((float)($account['current_balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                <div class="sl-data-list__row"><span>Devise</span><strong><?= e((string)($account['currency_code'] ?? 'EUR')) ?></strong></div>
                <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>