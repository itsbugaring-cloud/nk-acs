<?php
require_once __DIR__ . '/../config/config.php';

// Increase timeout for large dataset
set_time_limit(20);

header('Content-Type: application/json');
requireLogin();

// Check if GenieACS is configured
if (!isGenieACSConfigured()) {
    jsonResponse(['success' => false, 'message' => 'GenieACS belum dikonfigurasi']);
}

// Get GenieACS credentials
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$credentials = $result->fetch_assoc();

if (!$credentials) {
    jsonResponse(['success' => false, 'message' => 'GenieACS tidak terhubung']);
}

use App\GenieACS;
use App\GenieACS_Fast;

$genieacs = new GenieACS(
    $credentials['host'],
    $credentials['port'],
    $credentials['username'],
    $credentials['password']
);

$devicesProcessed = 0;
$devicesResult = $genieacs->walkDevices(function ($device) use (&$excellent, &$good, &$fair, &$poor, &$noSignal, &$devicesProcessed) {
    $devicesProcessed++;

    $parsed = GenieACS_Fast::parseDeviceDataFast($device);
    $rxPower = $parsed['rx_power'];

    if ($rxPower === 'N/A' || $rxPower === '' || $rxPower === null) {
        $noSignal++;
        return;
    }

    $rxPower = floatval($rxPower);
    if ($rxPower > -20) {
        $excellent++;
    } elseif ($rxPower >= -25) {
        $good++;
    } elseif ($rxPower >= -28) {
        $fair++;
    } else {
        $poor++;
    }
}, [], 50);

if (!$devicesResult['success']) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil data devices']);
}

jsonResponse([
    'success' => true,
    'data' => [
        'excellent' => $excellent,
        'good' => $good,
        'fair' => $fair,
        'poor' => $poor,
        'no_signal' => $noSignal,
        'total' => $devicesProcessed
    ]
]);
