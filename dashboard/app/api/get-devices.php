<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

// Set time limit for this script to handle large datasets
set_time_limit(300); // Increased to 5 minutes for large datasets
ini_set('max_execution_time', 300);

if (!isGenieACSConfigured()) {
    jsonResponse(['success' => false, 'message' => 'GenieACS belum dikonfigurasi']);
}

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

// Get pagination parameters (optional)
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100; // Default limit to 100 for performance
$skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;

// Check if client wants all devices (chunked parameter)
$chunked = isset($_GET['chunked']) && $_GET['chunked'] === 'true';

// Parser selection: 'fast' (default for performance) or 'full' (for complete data)
$parser = isset($_GET['parser']) ? $_GET['parser'] : 'fast';
$useFastParser = ($parser === 'fast');

function enrichDevicesWithOltInventory(mysqli $conn, array $devices): array {
    if (empty($devices)) {
        return $devices;
    }

    $serials = [];
    foreach ($devices as $device) {
        $serial = strtoupper(trim((string) ($device['serial_number'] ?? '')));
        if ($serial !== '' && $serial !== 'N/A') {
            $serials[] = $serial;
        }
    }

    $serials = array_values(array_unique($serials));
    if (empty($serials)) {
        return $devices;
    }

    $quoted = array_map(static fn(string $serial) => "'" . $conn->real_escape_string($serial) . "'", $serials);
    $sql = "
        SELECT
            inv.serial_number,
            inv.description,
            inv.pon_port,
            inv.ont_index,
            inv.status AS olt_onu_status,
            inv.rx_power AS olt_rx_power,
            olt.name AS olt_name
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id
        WHERE inv.serial_number IN (" . implode(',', $quoted) . ")
        ORDER BY inv.updated_at DESC, inv.id DESC
    ";

    $inventoryMap = [];
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $serial = strtoupper((string) $row['serial_number']);
        if (!isset($inventoryMap[$serial])) {
            $inventoryMap[$serial] = $row;
        }
    }

    foreach ($devices as &$device) {
        $serial = strtoupper(trim((string) ($device['serial_number'] ?? '')));
        if ($serial === '' || !isset($inventoryMap[$serial])) {
            continue;
        }

        $inventory = $inventoryMap[$serial];
        $description = trim((string) ($inventory['description'] ?? ''));
        $device['olt_name'] = $inventory['olt_name'] ?? 'N/A';
        $device['pon_port'] = $inventory['pon_port'] ?? 'N/A';
        $device['ont_index'] = $inventory['ont_index'] ?? 'N/A';
        $device['olt_onu_status'] = $inventory['olt_onu_status'] ?? 'unknown';

        if ($description !== '') {
            $device['customer_name'] = $description;
            $device['ont_name'] = $description;
        } else {
            $device['customer_name'] = $device['customer_name'] ?? 'N/A';
            $device['ont_name'] = $device['ont_name'] ?? 'N/A';
        }

        if (($device['rx_power'] ?? 'N/A') === 'N/A' && $inventory['olt_rx_power'] !== null) {
            $device['rx_power'] = number_format((float) $inventory['olt_rx_power'], 2);
        }
    }
    unset($device);

    return $devices;
}

try {
    $devicesResult = $genieacs->getDevices([], $limit, $skip);

    if ($devicesResult['success']) {
        $devices = [];

        // Use selected parser
        foreach ($devicesResult['data'] as $device) {
            if ($useFastParser) {
                // Fast parser - optimized for performance (10x faster)
                $parsed = GenieACS_Fast::parseDeviceDataFast($device);
            } else {
                // Full parser - complete data extraction
                $parsed = $genieacs->parseDeviceData($device);
            }
            $devices[] = $parsed;
        }

        $devices = enrichDevicesWithOltInventory($conn, $devices);

        $response = [
            'success' => true,
            'devices' => $devices,
            'count' => count($devices),
            'total' => count($devices),
            'hasMore' => count($devices) === $limit,
            'pagination' => [
                'limit' => $limit,
                'skip' => $skip,
                'returned' => count($devices),
                'nextSkip' => $skip + $limit
            ]
        ];

        jsonResponse($response);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Gagal mengambil data devices',
            'error' => $devicesResult['error'] ?? 'Unknown error'
        ]);
    }
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], 500);
}
