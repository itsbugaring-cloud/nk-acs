<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

use App\OltRegistry;

$conn = getDBConnection();
$rows = OltRegistry::list($conn);

jsonResponse([
    'success' => true,
    'items' => $rows,
]);

