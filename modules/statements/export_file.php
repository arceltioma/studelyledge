<?php
/*
|--------------------------------------------------------------------------
| Téléchargement sécurisé des exports (aperçus PDF, fiches, relevés, ZIP)
|--------------------------------------------------------------------------
| Le dossier exports/ n'est plus accessible directement depuis le web :
| les fichiers sont servis ici, après authentification et contrôle d'accès.
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$fileName = (string)($_GET['file'] ?? '');

if (!preg_match('/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*\.(pdf|zip)$/', $fileName)) {
    http_response_code(400);
    exit('Fichier invalide.');
}

$exportDir = realpath(app_path('exports'));
$filePath = $exportDir !== false ? realpath($exportDir . DIRECTORY_SEPARATOR . $fileName) : false;

if ($exportDir === false || $filePath === false || !is_file($filePath)
    || dirname($filePath) !== $exportDir) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$isPdf = str_ends_with(strtolower($fileName), '.pdf');
$disposition = ($isPdf && !isset($_GET['download'])) ? 'inline' : 'attachment';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . ($isPdf ? 'application/pdf' : 'application/zip'));
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
header('Content-Length: ' . (string)filesize($filePath));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
