<?php
/*
|--------------------------------------------------------------------------
| Création / réinitialisation d'un compte super administrateur (CLI uniquement)
|--------------------------------------------------------------------------
| Usage : php tools/create_admin.php <identifiant>
| Le mot de passe est demandé au clavier (jamais passé en argument).
|--------------------------------------------------------------------------
*/
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$username = trim((string)($argv[1] ?? ''));
if ($username === '' || mb_strlen($username, 'UTF-8') > 100) {
    fwrite(STDERR, "Usage : php tools/create_admin.php <identifiant>\n");
    exit(1);
}

function sl_cli_prompt_hidden(string $label): string
{
    fwrite(STDOUT, $label);

    $isWindows = stripos(PHP_OS_FAMILY, 'Windows') === 0;
    if (!$isWindows) {
        shell_exec('stty -echo');
    }

    $value = rtrim((string)fgets(STDIN), "\r\n");

    if (!$isWindows) {
        shell_exec('stty echo');
    }
    fwrite(STDOUT, "\n");

    return $value;
}

$password = sl_cli_prompt_hidden('Mot de passe : ');
$confirm = sl_cli_prompt_hidden('Confirmation : ');

if ($password !== $confirm) {
    fwrite(STDERR, "Les mots de passe ne correspondent pas.\n");
    exit(1);
}

if (mb_strlen($password, 'UTF-8') < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    fwrite(STDERR, "Mot de passe trop faible : 10 caractères minimum, avec au moins une lettre et un chiffre.\n");
    exit(1);
}

$pdo = getPDO();

$role = $pdo->query("SELECT id, code FROM roles WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$role) {
    fwrite(STDERR, "Le rôle super administrateur (id 1) est absent : importer database/seed_reference.sql.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$existingId = $stmt->fetchColumn();

if ($existingId) {
    $pdo->prepare("UPDATE users SET password = ?, role = ?, role_id = 1, is_active = 1, updated_at = NOW() WHERE id = ?")
        ->execute([$hash, (string)$role['code'], (int)$existingId]);
    fwrite(STDOUT, "Compte « {$username} » mis à jour (super administrateur, actif).\n");
} else {
    $pdo->prepare("INSERT INTO users (username, password, role, role_id, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, 1, NOW(), NOW())")
        ->execute([$username, $hash, (string)$role['code']]);
    fwrite(STDOUT, "Compte « {$username} » créé (super administrateur).\n");
}
