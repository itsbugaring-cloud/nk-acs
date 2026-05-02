<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

$payload = json_decode(file_get_contents('php://input'), true);
$itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : null;
if (!$itemId && is_array($payload) && isset($payload['item_id'])) {
    $itemId = (int) $payload['item_id'];
}

$command = 'php /var/www/html/scripts/sync-olt-onus.php';
if ($itemId) {
    $command .= ' ' . $itemId;
}

$output = [];
$exitCode = 0;
exec($command . ' 2>&1', $output, $exitCode);

if ($exitCode !== 0) {
    jsonResponse([
        'success' => false,
        'message' => 'OLT sync gagal dijalankan',
        'output' => $output,
    ], 500);
}

$decoded = json_decode(implode("\n", $output), true);
jsonResponse($decoded ?: [
    'success' => true,
    'raw' => $output,
]);
