<?php
/**
 * Push Inform Batch - Send connection requests to multiple devices
 * Forces stale/offline devices to inform to ACS
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireLogin();
requireCpeWrite('Push Inform Batch');

if (!isGenieACSConfigured()) {
    jsonResponse(['success' => false, 'message' => 'GenieACS belum dikonfigurasi']);
}

$data = json_decode(file_get_contents('php://input'), true);
$deviceIds = $data['device_ids'] ?? [];

if (!is_array($deviceIds) || empty($deviceIds)) {
    jsonResponse(['success' => false, 'message' => 'device_ids harus berupa array dan tidak boleh kosong']);
}

// Limit to 50 devices per batch to avoid overwhelming GenieACS
if (count($deviceIds) > 50) {
    jsonResponse(['success' => false, 'message' => 'Maksimal 50 device per batch']);
}

$conn = getDBConnection();
$result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$credentials = $result->fetch_assoc();

if (!$credentials) {
    jsonResponse(['success' => false, 'message' => 'GenieACS tidak terhubung']);
}

use App\GenieACS;

$genieacs = new GenieACS(
    $credentials['host'],
    $credentials['port'],
    $credentials['username'],
    $credentials['password']
);

$results = [];
$successCount = 0;
$failCount = 0;

foreach ($deviceIds as $deviceId) {
    $deviceId = trim((string) $deviceId);
    if ($deviceId === '') {
        continue;
    }

    $response = $genieacs->summonDevice($deviceId);

    if (!empty($response['success'])) {
        $successCount++;
        $results[] = ['device_id' => $deviceId, 'status' => 'ok'];
    } else {
        $failCount++;
        $error = $response['error'] ?? ('HTTP ' . ($response['http_code'] ?? 'unknown'));
        $results[] = ['device_id' => $deviceId, 'status' => 'fail', 'error' => $error];
    }

    // Small delay between requests to avoid overwhelming GenieACS
    usleep(200000); // 200ms
}

jsonResponse([
    'success' => true,
    'message' => "Push inform selesai: {$successCount} berhasil, {$failCount} gagal",
    'total' => count($results),
    'success_count' => $successCount,
    'fail_count' => $failCount,
    'results' => $results,
]);
