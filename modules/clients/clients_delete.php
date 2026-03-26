<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'clients_archive';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$clientId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($clientId <= 0) {
    die('Client invalide.');
}


/*
|--------------------------------------------------------------------------
| Chargement client
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        client_code,
        first_name,
        last_name,
        is_active
    FROM clients
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die('Client introuvable.');
}


$isActive = (int)$client['is_active'];
$newState = $isActive ? 0 : 1;

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| Traitement POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $stmtUpdate = $pdo->prepare("
            UPDATE clients
            SET is_active = ?
            WHERE id = ?
        ");

        $stmtUpdate->execute([$newState, $clientId]);


        /*
        |--------------------------------------------------------------------------
        | Audit log
        |--------------------------------------------------------------------------
        */

        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            $newState ? 'reactivate_client' : 'archive_client',
            'clients',
            'client',
            $clientId,
            ($newState ? 'Réactivation' : 'Archivage') .
            " du client {$client['client_code']} {$client['first_name']} {$client['last_name']}"
        );


        header(
            "Location: " .
            APP_URL .
            "modules/clients/client_view.php?id=" .
            $clientId .
            "&status=success"
        );

        exit;

    } catch (Throwable $e) {

        $errorMessage = $e->getMessage();

    }

}

?>

<div class="layout">

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main">

<?php render_app_header_bar(
    'Archivage client',
    'Gestion du statut actif / inactif du client.'
); ?>


<div class="page-title">

<div>

<h1>

<?= $isActive ? 'Archiver un client' : 'Réactiver un client' ?>

</h1>

<p class="muted">

Client concerné :

<strong>

<?= e($client['client_code']) ?>

</strong>

—

<?= e($client['first_name'] . ' ' . $client['last_name']) ?>

</p>

</div>

<div class="btn-group">

<a
class="btn btn-outline"
href="<?= APP_URL ?>modules/clients/client_view.php?id=<?= (int)$clientId ?>"
>
Annuler
</a>

</div>

</div>


<?php if ($errorMessage): ?>

<div class="error">

<?= e($errorMessage) ?>

</div>

<?php endif; ?>


<div class="dashboard-grid-2">


<div class="card">

<h3>Confirmation</h3>

<p>

<?php if ($isActive): ?>

Vous êtes sur le point d’archiver ce client.

<br><br>

Le client ne sera plus actif dans les opérations mais son historique
restera intact.

<?php else: ?>

Ce client est actuellement archivé.

<br><br>

La réactivation permettra à nouveau de l’utiliser dans les opérations.

<?php endif; ?>

</p>


<form method="POST">

<input type="hidden" name="id" value="<?= (int)$clientId ?>">


<div class="btn-group" style="margin-top:20px;">

<button
type="submit"
class="btn <?= $isActive ? 'btn-danger' : 'btn-success' ?>"
>

<?= $isActive ? 'Confirmer archivage' : 'Confirmer réactivation' ?>

</button>

<a
class="btn btn-outline"
href="<?= APP_URL ?>modules/clients/client_view.php?id=<?= (int)$clientId ?>"
>
Annuler
</a>

</div>

</form>

</div>


<div class="card">

<h3>Informations client</h3>

<div class="stat-row">

<span class="metric-label">Code client</span>

<span class="metric-value">

<?= e($client['client_code']) ?>

</span>

</div>


<div class="stat-row">

<span class="metric-label">Nom complet</span>

<span class="metric-value">

<?= e($client['first_name'] . ' ' . $client['last_name']) ?>

</span>

</div>


<div class="stat-row">

<span class="metric-label">Etat actuel</span>

<span class="metric-value">

<?= $isActive ? 'Actif' : 'Archivé' ?>

</span>

</div>


<div class="dashboard-note">

Archiver un client ne supprime jamais les données.  
C’est une mesure de gestion, pas une amnésie.

</div>

</div>


</div>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

</div>

</div>