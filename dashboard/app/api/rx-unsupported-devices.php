<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

if (!isGenieACSConfigured()) {
    jsonResponse(['success' => false, 'message' => 'GenieACS belum dikonfigurasi']);
}

use App\GenieACS;
use App\GenieACS_Fast;

$conn = getDBConnection();
$result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$credentials = $result->fetch_assoc();

if (!$credentials) {
    jsonResponse(['success' => false, 'message' => 'GenieACS tidak terhubung']);
}

$genieacs = new GenieACS(
    $credentials['host'],
    $credentials['port'],
    $credentials['username'],
    $credentials['password']
);

$devicesResult = $genieacs->getDevices([], 0, 0);
if (!$devicesResult['success']) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil data devices dari GenieACS']);
}

$unsupported = [];

foreach ($devicesResult['data'] as $device) {
    $parsed = GenieACS_Fast::parseDeviceDataFast($device);
    if (($parsed['rx_power'] ?? 'N/A') !== 'N/A') {
        continue;
    }

    $unsupported[] = [
        'device_id' => $parsed['device_id'] ?? ($device['_id'] ?? 'N/A'),
        'serial_number' => $parsed['serial_number'] ?? extractSerialFromDeviceId($device['_id'] ?? ''),
        'manufacturer' => $parsed['manufacturer'] ?? 'N/A',
        'product_class' => $parsed['product_class'] ?? 'N/A',
        'last_inform' => $parsed['last_inform'] ?? 'N/A',
        'reason' => 'TR-069 device tidak mengekspose parameter optik RX ke ACS pada path yang didukung',
    ];
}

usort($unsupported, function ($left, $right) {
    return strcmp($right['last_inform'] ?? '', $left['last_inform'] ?? '');
});

jsonResponse([
    'success' => true,
    'count' => count($unsupported),
    'devices' => array_slice($unsupported, 0, 20),
]);
