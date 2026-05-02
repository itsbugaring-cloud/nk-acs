<?php
require_once __DIR__ . '/../config/config.php';

use App\GenieACS;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireLogin();
enforceCpeWriteGuard('Factory reset ONT');

$input = json_decode(file_get_contents('php://input'), true);
$deviceId = $input['device_id'] ?? '';
$confirm = $input['confirm'] ?? false;

if (empty($deviceId)) {
    jsonResponse(['success' => false, 'message' => 'Device ID required'], 400);
}

if ($confirm !== true) {
    jsonResponse(['success' => false, 'message' => 'Factory reset requires explicit confirmation'], 400);
}

if (!isGenieACSConfigured()) {
    jsonResponse(['success' => false, 'message' => 'GenieACS belum dikonfigurasi'], 400);
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT host, port, username, password FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$config = $result->fetch_assoc();

if (!$config) {
    jsonResponse(['success' => false, 'message' => 'GenieACS tidak terhubung'], 400);
}

$genieacs = new GenieACS(
    $config['host'],
    $config['port'],
    $config['username'],
    $config['password']
);

$taskResult = $genieacs->factoryResetDevice($deviceId);

if ($taskResult['success']) {
    jsonResponse([
        'success' => true,
        'message' => 'Task factory reset berhasil dikirim ke device',
        'task_status' => (($taskResult['http_code'] ?? 0) === 200) ? 'immediate' : 'queued',
        'http_code' => $taskResult['http_code'] ?? null
    ]);
}

jsonResponse([
    'success' => false,
    'message' => 'Gagal kirim factory reset: ' . ($taskResult['error'] ?? 'Unknown error'),
    'http_code' => $taskResult['http_code'] ?? null
], 400);
