<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

use App\GenieACS;
use App\OltInventorySync;

$conn = getDBConnection();
OltInventorySync::ensureSchema($conn);

$oltItemId = isset($_GET['olt_item_id']) ? (int) $_GET['olt_item_id'] : 0;

function getAcsSerialLookupForInventory(mysqli $conn): array
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

    $devicesResult = $genieacs->getDevices([], 0, 0);
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

$acsSerials = getAcsSerialLookupForInventory($conn);

$olts = [];
$oltResult = $conn->query("SELECT id, name FROM map_items WHERE item_type = 'olt' ORDER BY name ASC");
while ($row = $oltResult->fetch_assoc()) {
    $olts[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
    ];
}

$sql = "
    SELECT
        inv.id,
        inv.olt_item_id,
        olt.name AS olt_name,
        inv.serial_number,
        inv.pon_port,
        inv.ont_index,
        inv.description,
        inv.status,
        inv.rx_power,
        inv.tx_power,
        inv.distance,
        inv.firmware_version,
        inv.equipment_id,
        inv.last_synced_at
    FROM olt_onu_inventory inv
    INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
";

if ($oltItemId > 0) {
    $sql .= " WHERE inv.olt_item_id = " . $oltItemId;
}

$sql .= " ORDER BY olt.name ASC, inv.pon_port ASC, inv.ont_index ASC, inv.serial_number ASC";

$items = [];
$inAcsCount = 0;
$missingCount = 0;
$onlineMissingCount = 0;
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $serial = strtolower((string) ($row['serial_number'] ?? ''));
    $inAcs = $serial !== '' && isset($acsSerials[$serial]);
    if ($inAcs) {
        $inAcsCount++;
    } else {
        $missingCount++;
        if (($row['status'] ?? 'unknown') === 'online') {
            $onlineMissingCount++;
        }
    }

    $items[] = [
        'id' => (int) $row['id'],
        'olt_item_id' => (int) $row['olt_item_id'],
        'olt_name' => $row['olt_name'],
        'serial_number' => $row['serial_number'],
        'pon_port' => $row['pon_port'],
        'ont_index' => $row['ont_index'] !== null ? (int) $row['ont_index'] : null,
        'description' => $row['description'],
        'status' => $row['status'],
        'rx_power' => $row['rx_power'] !== null ? (float) $row['rx_power'] : null,
        'tx_power' => $row['tx_power'] !== null ? (float) $row['tx_power'] : null,
        'distance' => $row['distance'] !== null ? (int) $row['distance'] : null,
        'firmware_version' => $row['firmware_version'],
        'equipment_id' => $row['equipment_id'],
        'last_synced_at' => $row['last_synced_at'],
        'in_acs' => $inAcs,
    ];
}

jsonResponse([
    'success' => true,
    'filters' => [
        'olt_item_id' => $oltItemId,
    ],
    'totals' => [
        'inventory_total' => count($items),
        'in_acs_total' => $inAcsCount,
        'missing_total' => $missingCount,
        'online_missing_total' => $onlineMissingCount,
    ],
    'olts' => $olts,
    'items' => $items,
]);
