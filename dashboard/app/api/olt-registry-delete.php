<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

use App\OltRegistry;

$payload = json_decode(file_get_contents('php://input'), true);
$id = isset($payload['id']) ? (int) $payload['id'] : 0;
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID OLT tidak valid'], 400);
}

$conn = getDBConnection();

try {
    OltRegistry::delete($conn, $id);
    jsonResponse([
        'success' => true,
        'message' => 'OLT berhasil dihapus',
    ]);
} catch (\Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 400);
}

