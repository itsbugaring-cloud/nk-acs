<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

use App\OltRegistry;

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    jsonResponse(['success' => false, 'message' => 'Payload tidak valid'], 400);
}

$conn = getDBConnection();

try {
    $saved = OltRegistry::save($conn, $payload);
    jsonResponse([
        'success' => true,
        'message' => 'OLT berhasil disimpan',
        'data' => $saved,
    ]);
} catch (\Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 400);
}

