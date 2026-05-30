<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

use App\GenieACS;
use App\DeviceCache;
use App\OltInventorySync;

$conn = getDBConnection();
OltInventorySync::ensureSchema($conn);

function getAcsSerialLookup(mysqli $conn): array
{
    if (!isGenieACSConfigured()) {
        return [];
    }

    $result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
    $credentials = $result ? $result->fetch_assoc() : null;
    if (!$credentials) {
        return [];
    }

    $genieacs = new GenieACS(
        $credentials['host'],
        $credentials['port'],
        $credentials['username'],
        $credentials['password']
    );

    $devicesResult = DeviceCache::getDevices($genieacs);
    if (!$devicesResult['success']) {
        return [];
    }

    $serials = [];
    foreach ($devicesResult['data'] as $device) {
        $serial = $device['_deviceId']['_SerialNumber'] ?? null;
        if (!$serial) {
            $serial = $device['InternetGatewayDevice']['DeviceInfo']['SerialNumber']['_value'] ?? null;
        }
        if ($serial) {
            $serials[strtolower((string) $serial)] = true;
        }
    }

    return $serials;
}

$acsSerials = getAcsSerialLookup($conn);

$summary = [];
$summaryResult = $conn->query("
    SELECT
        olt.id AS olt_item_id,
        olt.name AS olt_name,
        olt.properties,
        COUNT(inv.id) AS inventory_total,
        COALESCE(SUM(CASE WHEN inv.status = 'online' THEN 1 ELSE 0 END), 0) AS online_total,
        COALESCE(SUM(CASE WHEN inv.status = 'offline' THEN 1 ELSE 0 END), 0) AS offline_total,
        COALESCE(SUM(CASE WHEN inv.status = 'unknown' THEN 1 ELSE 0 END), 0) AS unknown_total,
        COALESCE(SUM(CASE WHEN inv.rx_power IS NULL THEN 1 ELSE 0 END), 0) AS no_rx_total,
        COALESCE(SUM(CASE WHEN inv.rx_power <= -28 THEN 1 ELSE 0 END), 0) AS critical_rx_total,
        COALESCE(SUM(CASE WHEN inv.rx_power > -28 AND inv.rx_power <= -25 THEN 1 ELSE 0 END), 0) AS warning_rx_total,
        MAX(inv.last_synced_at) AS last_synced_at
    FROM map_items olt
    LEFT JOIN olt_onu_inventory inv ON inv.olt_item_id = olt.id
    WHERE olt.item_type = 'olt'
    GROUP BY olt.id, olt.name
    ORDER BY olt.name ASC
");

while ($row = $summaryResult->fetch_assoc()) {
    $oltId = (int) $row['olt_item_id'];
    $properties = json_decode($row['properties'] ?? '{}', true);
    if (!is_array($properties)) {
        $properties = [];
    }
    $summary[$oltId] = [
        'olt_item_id' => $oltId,
        'olt_name' => $row['olt_name'],
        'inventory_total' => (int) $row['inventory_total'],
        'online_total' => (int) $row['online_total'],
        'offline_total' => (int) $row['offline_total'],
        'unknown_total' => (int) $row['unknown_total'],
        'no_rx_total' => (int) $row['no_rx_total'],
        'critical_rx_total' => (int) $row['critical_rx_total'],
        'warning_rx_total' => (int) $row['warning_rx_total'],
        'in_acs_total' => 0,
        'missing_total' => 0,
        'last_synced_at' => $row['last_synced_at'],
        'sync_state' => $properties['inventory_sync_state'] ?? null,
        'last_attempt_at' => $properties['inventory_last_attempt_at'] ?? null,
        'last_error' => $properties['inventory_last_error'] ?? null,
    ];
}

$inventoryResult = $conn->query("
    SELECT olt_item_id, serial_number, status
    FROM olt_onu_inventory
    ORDER BY olt_item_id ASC, serial_number ASC
");

while ($row = $inventoryResult->fetch_assoc()) {
    $oltId = (int) $row['olt_item_id'];
    if (!isset($summary[$oltId])) {
        continue;
    }

    $serial = strtolower((string) ($row['serial_number'] ?? ''));
    if ($serial !== '' && isset($acsSerials[$serial])) {
        $summary[$oltId]['in_acs_total']++;
    } else {
        $summary[$oltId]['missing_total']++;
        if (($row['status'] ?? 'unknown') === 'online') {
            $summary[$oltId]['online_missing_total'] = ($summary[$oltId]['online_missing_total'] ?? 0) + 1;
        }
    }
}

jsonResponse([
    'success' => true,
    'summary' => $summary,
]);
