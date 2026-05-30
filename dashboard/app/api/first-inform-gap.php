<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

if (!isGenieACSConfigured()) {
    jsonResponse(['success' => false, 'message' => 'GenieACS belum dikonfigurasi']);
}

use App\GenieACS;
use App\DeviceCache;
use App\OltInventorySync;

$requestedOlt = trim((string) ($_GET['olt'] ?? ''));
$requestedStatus = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$requestedLimit = (int) ($_GET['limit'] ?? 50);
if ($requestedLimit <= 0) {
    $requestedLimit = 50;
}
$requestedLimit = min($requestedLimit, 200);

$conn = getDBConnection();
OltInventorySync::ensureSchema($conn);
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

$devicesResult = DeviceCache::getDevices($genieacs);
if (!$devicesResult['success']) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil data devices dari GenieACS']);
}

$acsDeviceIds = [];
$acsSerials = [];
$acsLastInform = [];
$staleThresholdSec = 900; // 15 minutes

foreach ($devicesResult['data'] as $device) {
    $deviceId = $device['_id'] ?? '';
    if ($deviceId !== '') {
        $acsDeviceIds[strtolower($deviceId)] = true;
    }

    $serial = $device['_deviceId']['_SerialNumber'] ?? null;
    if (!$serial) {
        $serial = $device['InternetGatewayDevice']['DeviceInfo']['SerialNumber']['_value'] ?? null;
    }
    if ($serial) {
        $serialLower = strtolower($serial);
        $acsSerials[$serialLower] = true;

        // Track last inform time and device ID for stale detection
        $lastInform = $device['_lastInform'] ?? null;
        $acsLastInform[$serialLower] = [
            'device_id' => $deviceId,
            'last_inform' => $lastInform,
            'is_stale' => false,
        ];

        if ($lastInform) {
            $lastInformTs = strtotime($lastInform);
            if ($lastInformTs !== false && (time() - $lastInformTs) > $staleThresholdSec) {
                $acsLastInform[$serialLower]['is_stale'] = true;
            }
        }
    }
}

$query = "
    SELECT
        onu.id AS onu_map_id,
        onu.name AS onu_name,
        onu.status AS onu_status,
        onu.genieacs_device_id AS map_device_id,
        onu_config.customer_name,
        onu_config.odp_port,
        onu_config.genieacs_device_id AS onu_device_id,
        odp.id AS odp_id,
        odp.name AS odp_name,
        odc.id AS odc_id,
        odc.name AS odc_name,
        olt.id AS olt_id,
        olt.name AS olt_name
    FROM map_items onu
    INNER JOIN onu_config ON onu_config.map_item_id = onu.id
    LEFT JOIN map_items odp ON odp.id = onu.parent_id AND odp.item_type = 'odp'
    LEFT JOIN map_items odc ON odc.id = odp.parent_id AND odc.item_type = 'odc'
    LEFT JOIN map_items olt ON olt.id = odc.parent_id AND olt.item_type = 'olt'
    WHERE onu.item_type = 'onu'
    ORDER BY olt.name ASC, odp.name ASC, onu_config.odp_port ASC, onu.id ASC
";

$mappedOnuResult = $conn->query($query);
$missing = [];
$mappedCount = 0;
$summaryByOlt = [];

function ensureOltGapSummary(array &$summaryByOlt, array $row): void
{
    $oltId = (int) ($row['olt_id'] ?? 0);
    if (!isset($summaryByOlt[$oltId])) {
        $summaryByOlt[$oltId] = [
            'olt_id' => $oltId,
            'olt_name' => $row['olt_name'] ?: 'N/A',
            'inventory_total' => 0,
            'online_total' => 0,
            'offline_total' => 0,
            'unknown_total' => 0,
            'in_acs_total' => 0,
            'missing_total' => 0,
            'online_missing_total' => 0,
            'offline_missing_total' => 0,
            'unknown_missing_total' => 0,
        ];
    }
}

function incrementOltStatus(array &$summary, string $status, bool $existsInAcs): void
{
    $summary['inventory_total']++;

    if ($status === 'online') {
        $summary['online_total']++;
    } elseif ($status === 'offline') {
        $summary['offline_total']++;
    } else {
        $summary['unknown_total']++;
    }

    if ($existsInAcs) {
        $summary['in_acs_total']++;
        return;
    }

    $summary['missing_total']++;
    if ($status === 'online') {
        $summary['online_missing_total']++;
    } elseif ($status === 'offline') {
        $summary['offline_missing_total']++;
    } else {
        $summary['unknown_missing_total']++;
    }
}

while ($row = $mappedOnuResult->fetch_assoc()) {
    $mappedCount++;

    $candidateDeviceId = $row['onu_device_id'] ?: $row['map_device_id'];
    $candidateSerial = extractSerialFromDeviceId($candidateDeviceId);

    $existsInAcs = false;
    if ($candidateDeviceId && isset($acsDeviceIds[strtolower($candidateDeviceId)])) {
        $existsInAcs = true;
    }
    if (!$existsInAcs && $candidateSerial && isset($acsSerials[strtolower($candidateSerial)])) {
        $existsInAcs = true;
    }

    ensureOltGapSummary($summaryByOlt, $row);
    incrementOltStatus($summaryByOlt[(int) $row['olt_id']], (string) ($row['onu_status'] ?: 'unknown'), $existsInAcs);

    if ($existsInAcs) {
        continue;
    }

    $missing[] = [
        'map_item_id' => (int) $row['onu_map_id'],
        'onu_name' => $row['onu_name'],
        'customer_name' => $row['customer_name'] ?: 'N/A',
        'odp_port' => $row['odp_port'] ?: 'N/A',
        'olt_name' => $row['olt_name'] ?: 'N/A',
        'odc_name' => $row['odc_name'] ?: 'N/A',
        'odp_name' => $row['odp_name'] ?: 'N/A',
        'genieacs_device_id' => $candidateDeviceId ?: 'N/A',
        'serial_guess' => $candidateSerial ?: 'N/A',
        'onu_status' => $row['onu_status'] ?: 'unknown',
        'olt_item_id' => (int) ($row['olt_id'] ?? 0),
        'status' => 'Belum first-inform ke ACS',
    ];
}

if ($mappedCount === 0) {
    $inventoryQuery = "
        SELECT
            inv.id AS inventory_id,
            inv.serial_number,
            inv.pon_port,
            inv.ont_index,
            inv.description,
            inv.status AS onu_status,
            olt.id AS olt_id,
            olt.name AS olt_name
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
        ORDER BY olt.name ASC, inv.pon_port ASC, inv.ont_index ASC, inv.serial_number ASC
    ";

    $inventoryResult = $conn->query($inventoryQuery);
    while ($row = $inventoryResult->fetch_assoc()) {
        $mappedCount++;
        $serial = strtolower((string) $row['serial_number']);
        $existsInAcs = $serial !== '' && isset($acsSerials[$serial]);

        ensureOltGapSummary($summaryByOlt, $row);
        incrementOltStatus($summaryByOlt[(int) $row['olt_id']], (string) ($row['onu_status'] ?: 'unknown'), $existsInAcs);

        if ($existsInAcs) {
            continue;
        }

        $missing[] = [
            'map_item_id' => (int) $row['inventory_id'],
            'onu_name' => $row['description'] ?: $row['serial_number'],
            'customer_name' => $row['description'] ?: 'N/A',
            'odp_port' => $row['ont_index'] ?: 'N/A',
            'olt_name' => $row['olt_name'] ?: 'N/A',
            'odc_name' => $row['pon_port'] ?: 'N/A',
            'odp_name' => 'Inventory OLT',
            'genieacs_device_id' => $row['serial_number'],
            'serial_guess' => $row['serial_number'],
            'onu_status' => $row['onu_status'] ?: 'unknown',
            'olt_item_id' => (int) ($row['olt_id'] ?? 0),
            'status' => 'Belum first-inform ke ACS',
        ];
    }
}

usort($missing, static function (array $left, array $right): int {
    $priority = ['online' => 0, 'unknown' => 1, 'offline' => 2];
    $leftPriority = $priority[$left['onu_status'] ?? 'unknown'] ?? 1;
    $rightPriority = $priority[$right['onu_status'] ?? 'unknown'] ?? 1;
    if ($leftPriority !== $rightPriority) {
        return $leftPriority <=> $rightPriority;
    }

    return [$left['olt_name'] ?? '', $left['customer_name'] ?? '', $left['serial_guess'] ?? '']
        <=> [$right['olt_name'] ?? '', $right['customer_name'] ?? '', $right['serial_guess'] ?? ''];
});

$summaryRows = array_values($summaryByOlt);
usort($summaryRows, static function (array $left, array $right): int {
    return [$right['online_missing_total'], $right['missing_total'], $left['olt_name']]
        <=> [$left['online_missing_total'], $left['missing_total'], $right['olt_name']];
});

$filteredMissing = array_values(array_filter($missing, static function (array $row) use ($requestedOlt, $requestedStatus): bool {
    if ($requestedOlt !== '') {
        $needle = strtolower($requestedOlt);
        $oltName = strtolower((string) ($row['olt_name'] ?? ''));
        if ($oltName !== $needle && !str_contains($oltName, $needle)) {
            return false;
        }
    }

    if ($requestedStatus !== '' && $requestedStatus !== 'all') {
        $status = strtolower((string) ($row['onu_status'] ?? 'unknown'));
        if ($status !== $requestedStatus) {
            return false;
        }
    }

    return true;
}));

jsonResponse([
    'success' => true,
    'mapped_onu_count' => $mappedCount,
    'topology_ready' => $mappedCount > 0,
    'missing_count' => count($missing),
    'online_missing_count' => count(array_filter($missing, static fn(array $row): bool => ($row['onu_status'] ?? '') === 'online')),
    'filtered_missing_count' => count($filteredMissing),
    'stale_count' => count(array_filter($acsLastInform, static fn(array $info): bool => $info['is_stale'])),
    'filters' => [
        'olt' => $requestedOlt,
        'status' => $requestedStatus,
        'limit' => $requestedLimit,
    ],
    'summary_by_olt' => $summaryRows,
    'items' => array_slice($filteredMissing, 0, $requestedLimit),
    'stale_devices' => array_values(array_map(
        static fn(string $serial, array $info): array => [
            'serial' => strtoupper($serial),
            'device_id' => $info['device_id'],
            'last_inform' => $info['last_inform'],
        ],
        array_keys(array_filter($acsLastInform, static fn(array $info): bool => $info['is_stale'])),
        array_filter($acsLastInform, static fn(array $info): bool => $info['is_stale'])
    )),
]);
