<?php
require_once __DIR__ . '/../../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['flash_message'] = 'La page monthly_payments_import.php est un ancien point d’entrée. Redirection vers le tunnel officiel d’import.';
$_SESSION['flash_type'] = 'info';

header('Location: ' . APP_URL . 'modules/monthly_payments/import_monthly_payments.php');
exit;