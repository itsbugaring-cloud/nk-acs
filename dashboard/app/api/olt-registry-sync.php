<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

use App\OltInventorySync;

$payload = json_decode(file_get_contents('php://input'), true);
$id = isset($payload['id']) ? (int) $payload['id'] : 0;
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID OLT tidak valid'], 400);
}

$conn = getDBConnection();
OltInventorySync::ensureSchema($conn);

$stmt = $conn->prepare("
    SELECT mi.*, oc.olt_link, oc.pon_count
    FROM map_items mi
    LEFT JOIN olt_config oc ON oc.map_item_id = mi.id
    WHERE mi.id = ? AND mi.item_type = 'olt'
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    jsonResponse(['success' => false, 'message' => 'OLT tidak ditemukan'], 404);
}

$item['properties'] = json_decode($item['properties'] ?? '{}', true) ?: [];
$item['config'] = [
    'olt_link' => $item['olt_link'] ?? null,
    'pon_count' => isset($item['pon_count']) ? (int) $item['pon_count'] : null,
];

try {
    $sync = new OltInventorySync($item);
    $result = $sync->sync($conn);

    jsonResponse([
        'success' => true,
        'message' => 'Sync OLT selesai',
        'data' => $result,
    ]);
} catch (\Throwable $e) {
    OltInventorySync::markSyncFailure($conn, $item, $e->getMessage());

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}

