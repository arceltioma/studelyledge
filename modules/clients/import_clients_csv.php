<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_create_page');
} else {
    enforcePagePermission($pdo, 'clients_create');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Import clients CSV';
$pageSubtitle = 'Import sécurisé de clients avec contrôle des doublons';

$successMessage = '';
$errorMessage = '';
$previewRows = [];
$importedCount = 0;
$duplicateCount = 0;
$errorCount = 0;

if (!function_exists('sl_clients_import_normalize_header')) {
    function sl_clients_import_normalize_header(string $value): string
    {
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(
            ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','ù','û','ü','ç','œ','æ','/','\\','-','.'],
            ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c','oe','ae',' ',' ',' ',' '],
            $value
        );
        $value = preg_replace('/\s+/', '_', $value);
        $value = preg_replace('/[^a-z0-9_]/', '', $value);
        $value = trim($value, '_');

        $aliases = [
            'code_client' => 'client_code',
            'client_code' => 'client_code',
            'nom' => 'last_name',
            'prenom' => 'first_name',
            'full_name' => 'full_name',
            'email' => 'email',
            'telephone' => 'phone',
            'phone' => 'phone',
            'adresse' => 'postal_address',
            'adresse_postale' => 'postal_address',
            'postal_address' => 'postal_address',
            'pays_commercial' => 'country_commercial',
            'country_commercial' => 'country_commercial',
            'pays_destination' => 'country_destination',
            'country_destination' => 'country_destination',
            'type_client' => 'client_type',
            'client_type' => 'client_type',
        ];

        return $aliases[$value] ?? $value;
    }
}

if (!function_exists('sl_clients_import_detect_delimiter')) {
    function sl_clients_import_detect_delimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ';';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }
}

if (!function_exists('sl_clients_import_read_rows')) {
    function sl_clients_import_read_rows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new RuntimeException('Le fichier est vide.');
        }

        $delimiter = sl_clients_import_detect_delimiter($firstLine);
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Entêtes introuvables.');
        }

        $headers = array_map(static fn($h) => sl_clients_import_normalize_header((string)$h), $headers);

        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            if ($data === [null] || count(array_filter($data, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string)$data[$index]) : '';
            }

            $row['_line_number'] = $lineNumber;
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}

if (!function_exists('sl_clients_import_build_full_name')) {
    function sl_clients_import_build_full_name(array $row): string
    {
        $fullName = trim((string)($row['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $firstName = trim((string)($row['first_name'] ?? ''));
        $lastName = trim((string)($row['last_name'] ?? ''));

        return trim($firstName . ' ' . $lastName);
    }
}

if (!function_exists('sl_clients_import_find_duplicate')) {
    function sl_clients_import_find_duplicate(PDO $pdo, string $clientCode, string $email, string $fullName): ?array
    {
        if (!tableExists($pdo, 'clients')) {
            return null;
        }

        $sql = "SELECT * FROM clients WHERE 1=0";
        $params = [];
        $conditions = [];

        if ($clientCode !== '' && columnExists($pdo, 'clients', 'client_code')) {
            $conditions[] = "client_code = ?";
            $params[] = $clientCode;
        }

        if ($email !== '' && columnExists($pdo, 'clients', 'email')) {
            $conditions[] = "email = ?";
            $params[] = $email;
        }

        if ($fullName !== '' && columnExists($pdo, 'clients', 'full_name')) {
            $conditions[] = "full_name = ?";
            $params[] = $fullName;
        }

        if (!$conditions) {
            return null;
        }

        $sql = "SELECT * FROM clients WHERE " . implode(' OR ', $conditions) . " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!isset($_FILES['clients_file']) || !is_array($_FILES['clients_file'])) {
            throw new RuntimeException('Aucun fichier reçu.');
        }

        $file = $_FILES['clients_file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erreur lors de l’upload du fichier.');
        }

        $originalName = (string)($file['name'] ?? 'clients.csv');
        $tmpPath = (string)($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Fichier uploadé invalide.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            throw new RuntimeException('Format non supporté. Utilisez CSV ou TXT.');
        }

        $rows = sl_clients_import_read_rows($tmpPath);
        if (!$rows) {
            throw new RuntimeException('Aucune ligne exploitable trouvée.');
        }

        $pdo->beginTransaction();

        foreach ($rows as $row) {
            $line = (int)($row['_line_number'] ?? 0);

            try {
                $clientCode = trim((string)($row['client_code'] ?? ''));
                $fullName = sl_clients_import_build_full_name($row);
                $email = trim((string)($row['email'] ?? ''));
                $phone = trim((string)($row['phone'] ?? ''));
                $postalAddress = trim((string)($row['postal_address'] ?? ''));
                $countryCommercial = trim((string)($row['country_commercial'] ?? ''));
                $countryDestination = trim((string)($row['country_destination'] ?? ''));
                $clientType = trim((string)($row['client_type'] ?? ''));

                if ($fullName === '') {
                    throw new RuntimeException('Nom complet manquant.');
                }

                if ($clientCode === '' && function_exists('generateClientCode')) {
                    $clientCode = generateClientCode($pdo);
                }

                $duplicate = sl_clients_import_find_duplicate($pdo, $clientCode, $email, $fullName);
                if ($duplicate) {
                    $duplicateCount++;
                    $previewRows[] = [
                        'line' => $line,
                        'status' => 'duplicate',
                        'client_code' => $clientCode,
                        'full_name' => $fullName,
                        'email' => $email,
                        'message' => 'Client déjà existant',
                    ];
                    continue;
                }

                $columns = [];
                $values = [];
                $params = [];

                $map = [
                    'client_code' => $clientCode,
                    'full_name' => $fullName,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'postal_address' => $postalAddress !== '' ? $postalAddress : null,
                    'country_commercial' => $countryCommercial !== '' ? $countryCommercial : null,
                    'country_destination' => $countryDestination !== '' ? $countryDestination : null,
                    'client_type' => $clientType !== '' ? $clientType : null,
                    'is_active' => columnExists($pdo, 'clients', 'is_active') ? 1 : null,
                ];

                foreach ($map as $column => $value) {
                    if ($value === null && $column === 'is_active') {
                        continue;
                    }
                    if (columnExists($pdo, 'clients', $column)) {
                        $columns[] = $column;
                        $values[] = '?';
                        $params[] = $value;
                    }
                }

                if (columnExists($pdo, 'clients', 'created_at')) {
                    $columns[] = 'created_at';
                    $values[] = 'NOW()';
                }

                if (columnExists($pdo, 'clients', 'updated_at')) {
                    $columns[] = 'updated_at';
                    $values[] = 'NOW()';
                }

                if (!$columns) {
                    throw new RuntimeException('Aucune colonne insérable trouvée.');
                }

                $sql = "
                    INSERT INTO clients (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $values) . ")
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $newId = (int)$pdo->lastInsertId();
                $importedCount++;

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'import_client_csv',
                        'clients',
                        'client',
                        $newId,
                        'Import CSV d’un client'
                    );
                }

                $previewRows[] = [
                    'line' => $line,
                    'status' => 'ok',
                    'client_code' => $clientCode,
                    'full_name' => $fullName,
                    'email' => $email,
                    'message' => 'Importé',
                ];
            } catch (Throwable $e) {
                $errorCount++;
                $previewRows[] = [
                    'line' => $line,
                    'status' => 'error',
                    'client_code' => trim((string)($row['client_code'] ?? '')),
                    'full_name' => sl_clients_import_build_full_name($row),
                    'email' => trim((string)($row['email'] ?? '')),
                    'message' => $e->getMessage(),
                ];
            }
        }

        $pdo->commit();
        $successMessage = $importedCount . ' client(s) importé(s). ' . $duplicateCount . ' doublon(s). ' . $errorCount . ' erreur(s).';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

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

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3>Importer des clients</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <div>
                        <label for="clients_file">Fichier CSV / TXT</label>
                        <input type="file" id="clients_file" name="clients_file" accept=".csv,.txt" required>
                    </div>

                    <div style="margin-top:18px;">
                        <label>Entêtes reconnues</label>
                        <div class="card" style="padding:14px;">
                            <div class="muted">
                                <strong>client_code</strong>,
                                <strong>full_name</strong> ou <strong>first_name</strong> + <strong>last_name</strong>,
                                <strong>email</strong>,
                                <strong>phone</strong>,
                                <strong>postal_address</strong>,
                                <strong>country_commercial</strong>,
                                <strong>country_destination</strong>,
                                <strong>client_type</strong>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Importer les clients</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour liste clients</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Exemple</h3>
                <pre style="white-space:pre-wrap; margin:0;">client_code;full_name;email;phone;postal_address;country_commercial;country_destination;client_type
000123456;Jean Dupont;jean@example.com;0600000000;12 rue Exemple, Paris;France;Allemagne;Etudiant</pre>
            </div>
        </div>

        <?php if ($previewRows): ?>
            <div class="card" style="margin-top:20px;">
                <h3>Résultat détaillé</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Ligne</th>
                                <th>Statut</th>
                                <th>Code client</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewRows as $row): ?>
                                <tr>
                                    <td><?= (int)$row['line'] ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'ok'): ?>
                                            <span class="badge badge-success">OK</span>
                                        <?php elseif ($row['status'] === 'duplicate'): ?>
                                            <span class="badge badge-warning">Doublon</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Erreur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string)$row['client_code']) ?></td>
                                    <td><?= e((string)$row['full_name']) ?></td>
                                    <td><?= e((string)$row['email']) ?></td>
                                    <td><?= e((string)$row['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>