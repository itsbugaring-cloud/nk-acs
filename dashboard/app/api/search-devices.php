<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

if (!isGenieACSConfigured()) {
    jsonResponse(['success' => false, 'message' => 'GenieACS belum dikonfigurasi']);
}

use App\GenieACS;
use App\DeviceCache;
use App\GenieACS_Fast;

$query = strtolower(trim((string) ($_GET['q'] ?? '')));
$limit = min(15, max(5, (int) ($_GET['limit'] ?? 8)));

if ($query === '' || strlen($query) < 2) {
    jsonResponse(['success' => true, 'devices' => []]);
}

$conn = getDBConnection();
$result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$credentials = $result ? $result->fetch_assoc() : null;

if (!$credentials) {
    jsonResponse(['success' => false, 'message' => 'GenieACS tidak terhubung']);
}

$genieacs = new GenieACS(
    $credentials['host'],
    $credentials['port'],
    $credentials['username'],
    $credentials['password']
);

$devicesResult = DeviceCache::getDevices($genieacs);
if (!$devicesResult['success']) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil devices dari GenieACS']);
}

$devices = [];
foreach ($devicesResult['data'] as $device) {
    $parsed = GenieACS_Fast::parseDeviceDataFast($device);
    $serial = strtoupper(trim((string) ($parsed['serial_number'] ?? '')));
    if ($serial !== '' && $serial !== 'N/A') {
        $devices[$serial] = $parsed;
    }
}

if ($devices) {
    $quoted = array_map(static fn(string $serial) => "'" . $conn->real_escape_string($serial) . "'", array_keys($devices));
    $sql = "
        SELECT inv.serial_number, inv.description, inv.pon_port, inv.ont_index, olt.name AS olt_name
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id
        WHERE inv.serial_number IN (" . implode(',', $quoted) . ")
        ORDER BY inv.updated_at DESC, inv.id DESC
    ";
    $invResult = $conn->query($sql);
    while ($row = $invResult->fetch_assoc()) {
        $serial = strtoupper((string) ($row['serial_number'] ?? ''));
        if (!isset($devices[$serial])) {
            continue;
        }
        if (empty($devices[$serial]['customer_name']) || $devices[$serial]['customer_name'] === 'N/A') {
            $devices[$serial]['customer_name'] = $row['description'] ?: 'N/A';
        }
        $devices[$serial]['olt_name'] = $row['olt_name'] ?? 'N/A';
        $devices[$serial]['pon_port'] = $row['pon_port'] ?? 'N/A';
        $devices[$serial]['ont_index'] = $row['ont_index'] ?? 'N/A';
    }
}

$matches = [];
foreach ($devices as $device) {
    $haystack = strtolower(implode(' ', [
        $device['serial_number'] ?? '',
        $device['device_id'] ?? '',
        $device['mac_address'] ?? '',
        $device['pppoe_username'] ?? '',
        $device['wifi_ssid'] ?? '',
        $device['customer_name'] ?? '',
        $device['ont_name'] ?? '',
        $device['olt_name'] ?? '',
        $device['ip_tr069'] ?? '',
    ]));

    if (!str_contains($haystack, $query)) {
        continue;
    }

    $matches[] = [
        'device_id' => $device['device_id'] ?? '',
        'serial_number' => $device['serial_number'] ?? 'N/A',
        'customer_name' => $device['customer_name'] ?? ($device['ont_name'] ?? 'N/A'),
        'olt_name' => $device['olt_name'] ?? 'N/A',
        'status' => $device['status'] ?? 'unknown',
        'product_class' => $device['product_class'] ?? 'N/A',
        'pppoe_username' => $device['pppoe_username'] ?? 'N/A',
    ];

    if (count($matches) >= $limit) {
        break;
    }
}

jsonResponse([
    'success' => true,
    'devices' => $matches,
]);
