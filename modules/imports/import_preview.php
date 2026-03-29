<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_create');

function ip_normalize_key(string $value): string
{
    $value = trim($value);
    $value = mb_strtolower($value, 'UTF-8');
    $value = str_replace([' ', '-', '/', '\\'], '_', $value);
    return $value;
}

function ip_parse_amount(array $row): float
{
    $candidates = [
        $row['amount'] ?? null,
        $row['montant'] ?? null,
        $row['credit'] ?? null,
        $row['debit'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === null || $candidate === '') {
            continue;
        }

        $normalized = str_replace([' ', "\xc2\xa0"], '', (string)$candidate);
        $normalized = str_replace(',', '.', $normalized);

        if (is_numeric($normalized)) {
            return (float)$normalized;
        }
    }

    return 0.0;
}

function ip_find_first_non_empty(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }

    return '';
}

$pageTitle = 'Prévisualisation des imports';
$pageSubtitle = 'Contrôle strict client / type / service / hiérarchie 706 avant validation.';
require_once __DIR__ . '/../../includes/document_start.php';

$previewRows = [];
$errorMessage = '';
$successMessage = '';

$typesByCode = [];
if (tableExists($pdo, 'ref_operation_types')) {
    $rows = $pdo->query("
        SELECT id, code, label, is_active
        FROM ref_operation_types
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $typesByCode[(string)$row['code']] = $row;
    }
}

$servicesByCode = [];
if (tableExists($pdo, 'ref_services')) {
    $rows = $pdo->query("
        SELECT
            rs.id,
            rs.code,
            rs.label,
            rs.operation_type_id,
            rs.service_account_id,
            rs.treasury_account_id,
            rs.is_active,

            rot.code AS operation_type_code,
            rot.label AS operation_type_label,
            rot.is_active AS operation_type_active,

            sa.account_code AS service_account_code,
            sa.account_label AS service_account_label,
            sa.operation_type_label AS service_account_operation_type_label,
            sa.destination_country_label AS service_account_destination_country_label,
            sa.commercial_country_label AS service_account_commercial_country_label,
            sa.is_postable AS service_account_postable,
            sa.is_active AS service_account_active,

            ta.account_code AS treasury_account_code,
            ta.account_label AS treasury_account_label,
            ta.is_active AS treasury_account_active
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
        LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $servicesByCode[(string)$row['code']] = $row;
    }
}

$clientsByCode = [];
if (tableExists($pdo, 'clients')) {
    $rows = $pdo->query("
        SELECT id, client_code, full_name, generated_client_account, is_active
        FROM clients
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $clientsByCode[(string)$row['client_code']] = $row;
    }
}

$treasuryByCode = [];
if (tableExists($pdo, 'treasury_accounts')) {
    $rows = $pdo->query("
        SELECT id, account_code, account_label, is_active
        FROM treasury_accounts
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $treasuryByCode[(string)$row['account_code']] = $row;
    }
}

$serviceAccountsByCode = [];
if (tableExists($pdo, 'service_accounts')) {
    $rows = $pdo->query("
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
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $serviceAccountsByCode[(string)$row['account_code']] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            throw new RuntimeException('Aucun fichier importé.');
        }

        $handle = fopen($_FILES['import_file']['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier.');
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            throw new RuntimeException('Fichier vide.');
        }

        $normalizedHeaders = array_map(
            fn($v) => ip_normalize_key((string)$v),
            $headers
        );

        $lineNo = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNo++;

            $row = [];
            foreach ($normalizedHeaders as $i => $header) {
                $row[$header] = trim((string)($data[$i] ?? ''));
            }

            $errors = [];

            $clientCode = ip_find_first_non_empty($row, ['client_code', 'code_client']);
            $operationTypeCode = ip_find_first_non_empty($row, ['operation_type_code', 'type_operation', 'type_operation_code']);
            $serviceCode = ip_find_first_non_empty($row, ['service_code', 'code_service']);
            $treasuryCode = ip_find_first_non_empty($row, ['treasury_account_code', 'compte_512', 'account_512']);
            $serviceAccountCode = ip_find_first_non_empty($row, ['service_account_code', 'compte_706', 'account_706']);
            $amount = ip_parse_amount($row);

            $client = $clientCode !== '' ? ($clientsByCode[$clientCode] ?? null) : null;
            $type = $operationTypeCode !== '' ? ($typesByCode[$operationTypeCode] ?? null) : null;
            $service = $serviceCode !== '' ? ($servicesByCode[$serviceCode] ?? null) : null;
            $treasury = $treasuryCode !== '' ? ($treasuryByCode[$treasuryCode] ?? null) : null;
            $serviceAccount = $serviceAccountCode !== '' ? ($serviceAccountsByCode[$serviceAccountCode] ?? null) : null;

            if ($operationTypeCode === '') {
                $errors[] = 'Type d’opération manquant.';
            } elseif (!$type) {
                $errors[] = 'Type d’opération inconnu.';
            } elseif ((int)($type['is_active'] ?? 0) !== 1) {
                $errors[] = 'Type d’opération archivé.';
            }

            if ($amount <= 0) {
                $errors[] = 'Montant invalide.';
            }

            $isInternalTransfer = ($operationTypeCode === 'VIREMENT_INTERNE');
            $requiresClient = !$isInternalTransfer;
            $requiresService = in_array($operationTypeCode, ['FRAIS_DE_SERVICE', 'FRAIS_BANCAIRES', 'VIREMENT_EXCEPTIONEL'], true);

            if ($requiresClient) {
                if ($clientCode === '') {
                    $errors[] = 'Code client manquant.';
                } elseif (!$client) {
                    $errors[] = 'Client introuvable.';
                } elseif ((int)($client['is_active'] ?? 0) !== 1) {
                    $errors[] = 'Client archivé.';
                }
            } else {
                if ($clientCode !== '') {
                    $errors[] = 'Un virement interne ne doit pas être rattaché à un client.';
                }
            }

            if ($requiresService) {
                if ($serviceCode === '') {
                    $errors[] = 'Le service est obligatoire pour ce type d’opération.';
                }
            }

            if ($serviceCode !== '') {
                if (!$service) {
                    $errors[] = 'Service inconnu.';
                } else {
                    if ((int)($service['is_active'] ?? 0) !== 1) {
                        $errors[] = 'Service archivé.';
                    }

                    if ((int)($service['operation_type_active'] ?? 0) !== 1) {
                        $errors[] = 'Type parent du service archivé.';
                    }

                    if (($service['operation_type_code'] ?? '') !== $operationTypeCode) {
                        $errors[] = 'Le service n’est pas rattaché au type d’opération fourni.';
                    }

                    if (!empty($service['service_account_id'])) {
                        if ((int)($service['service_account_active'] ?? 0) !== 1) {
                            $errors[] = 'Le compte 706 lié au service est archivé.';
                        }

                        if ((int)($service['service_account_postable'] ?? 0) !== 1) {
                            $errors[] = 'Le compte 706 lié au service n’est pas mouvementable.';
                        }

                        if (!empty($service['service_account_operation_type_label'])) {
                            $normalized706Type = strtoupper(trim((string)$service['service_account_operation_type_label']));
                            $normalizedType = strtoupper(trim((string)$operationTypeCode));

                            if ($normalized706Type !== $normalizedType) {
                                $errors[] = 'Le compte 706 du service n’est pas cohérent avec le type d’opération.';
                            }
                        }

                        if ($serviceAccountCode !== '' && ($service['service_account_code'] ?? '') !== $serviceAccountCode) {
                            $errors[] = 'Le compte 706 fourni ne correspond pas à celui rattaché au service.';
                        }
                    }
                }
            }

            if ($serviceAccountCode !== '') {
                if (!$serviceAccount) {
                    $errors[] = 'Compte 706 inconnu.';
                } else {
                    if ((int)($serviceAccount['is_active'] ?? 0) !== 1) {
                        $errors[] = 'Compte 706 archivé.';
                    }

                    if ((int)($serviceAccount['is_postable'] ?? 0) !== 1) {
                        $errors[] = 'Le compte 706 fourni est un compte parent non mouvementable.';
                    }

                    if ($type && !empty($serviceAccount['operation_type_label'])) {
                        $normalized706Type = strtoupper(trim((string)$serviceAccount['operation_type_label']));
                        $normalizedType = strtoupper(trim((string)($type['code'] ?? '')));

                        if ($normalized706Type !== $normalizedType) {
                            $errors[] = 'Le compte 706 fourni n’est pas cohérent avec le type d’opération.';
                        }
                    }
                }
            }

            if ($treasuryCode !== '') {
                if (!$treasury) {
                    $errors[] = 'Compte 512 inconnu.';
                } elseif ((int)($treasury['is_active'] ?? 0) !== 1) {
                    $errors[] = 'Compte 512 archivé.';
                }
            }

            if ($isInternalTransfer) {
                if ($serviceCode !== '') {
                    $errors[] = 'Un virement interne ne doit pas être rattaché à un service.';
                }
                if ($serviceAccountCode !== '') {
                    $errors[] = 'Un virement interne ne doit pas utiliser un compte 706.';
                }
                if ($treasuryCode === '') {
                    $errors[] = 'Compte 512 obligatoire pour un virement interne.';
                }
            }

            $previewRows[] = [
                'line_no' => $lineNo,
                'row' => $row,
                'status' => $errors ? 'rejected' : 'ready',
                'errors' => $errors,
                'resolved_client' => $client['full_name'] ?? '',
                'resolved_type' => $type['label'] ?? '',
                'resolved_service' => $service['label'] ?? '',
                'resolved_treasury' => $treasury['account_label'] ?? '',
                'resolved_706' => $serviceAccount ? opServiceAccountDisplay($serviceAccount) : (($service['service_account_code'] ?? '') !== '' ? trim(($service['service_account_code'] ?? '') . ' - ' . ($service['service_account_label'] ?? '')) : ''),
            ];
        }

        fclose($handle);
        $successMessage = 'Prévisualisation terminée.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
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

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <label>Fichier CSV</label>
                <input type="file" name="import_file" accept=".csv" required>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Prévisualiser</button>
                </div>
            </form>
        </div>

        <?php if ($previewRows): ?>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Service</th>
                            <th>Compte 706</th>
                            <th>Compte 512</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Erreurs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewRows as $item): ?>
                            <tr>
                                <td><?= (int)$item['line_no'] ?></td>
                                <td>
                                    <?= e($item['row']['client_code'] ?? '') ?>
                                    <?php if ($item['resolved_client'] !== ''): ?>
                                        <div class="muted"><?= e($item['resolved_client']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($item['row']['operation_type_code'] ?? $item['row']['type_operation'] ?? '') ?>
                                    <?php if ($item['resolved_type'] !== ''): ?>
                                        <div class="muted"><?= e($item['resolved_type']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($item['row']['service_code'] ?? '') ?>
                                    <?php if ($item['resolved_service'] !== ''): ?>
                                        <div class="muted"><?= e($item['resolved_service']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($item['row']['service_account_code'] ?? $item['row']['compte_706'] ?? '') ?>
                                    <?php if ($item['resolved_706'] !== ''): ?>
                                        <div class="muted"><?= e($item['resolved_706']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($item['row']['treasury_account_code'] ?? $item['row']['compte_512'] ?? '') ?>
                                    <?php if ($item['resolved_treasury'] !== ''): ?>
                                        <div class="muted"><?= e($item['resolved_treasury']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($item['row']['amount'] ?? $item['row']['montant'] ?? $item['row']['credit'] ?? $item['row']['debit'] ?? '') ?></td>
                                <td><?= $item['status'] === 'ready' ? 'OK' : 'Rejetée' ?></td>
                                <td>
                                    <?php if (!$item['errors']): ?>
                                        —
                                    <?php else: ?>
                                        <?php foreach ($item['errors'] as $err): ?>
                                            <div><?= e($err) ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$previewRows): ?>
                            <tr><td colspan="9">Aucune ligne analysée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>