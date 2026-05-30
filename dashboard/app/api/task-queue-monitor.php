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

function pickTime(array $row): ?int
{
    $candidates = ['_timestamp', 'timestamp', '_lastInform'];
    foreach ($candidates as $field) {
        if (!isset($row[$field])) {
            continue;
        }
        $value = $row[$field];
        if (is_numeric($value)) {
            $ts = (int) $value;
            // Handle milliseconds
            if ($ts > 20000000000) {
                $ts = (int) floor($ts / 1000);
            }
            return $ts;
        }

        if (is_string($value) && $value !== '') {
            $parsed = strtotime($value);
            if ($parsed !== false) {
                return $parsed;
            }
        }
    }

    return null;
}

$conn = getDBConnection();
$query = strtolower(trim((string) ($_GET['q'] ?? '')));
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

$tasksResult = $genieacs->getTasks([], 500, 0);
if (!$tasksResult['success']) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil task queue']);
}

$faultsResult = $genieacs->getFaults([], 500, 0);
if (!$faultsResult['success']) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil fault queue']);
}

$tasks = is_array($tasksResult['data'] ?? null) ? $tasksResult['data'] : [];
$faults = is_array($faultsResult['data'] ?? null) ? $faultsResult['data'] : [];
$deviceMeta = [];

$devicesResult = DeviceCache::getDevices($genieacs);
if ($devicesResult['success'] && is_array($devicesResult['data'] ?? null)) {
    foreach ($devicesResult['data'] as $device) {
        $parsed = GenieACS_Fast::parseDeviceDataFast($device);
        $deviceId = (string) ($parsed['device_id'] ?? '');
        if ($deviceId === '') {
            continue;
        }

        $serial = strtoupper(trim((string) ($parsed['serial_number'] ?? '')));
        $oltName = $parsed['olt_name'] ?? '';
        if ($serial !== '' && $serial !== 'N/A') {
            $serialEscaped = $conn->real_escape_string($serial);
            $inventoryResult = $conn->query("
                SELECT inv.description, olt.name AS olt_name
                FROM olt_onu_inventory inv
                LEFT JOIN map_items olt ON olt.id = inv.olt_item_id
                WHERE inv.serial_number = '{$serialEscaped}'
                ORDER BY inv.updated_at DESC, inv.id DESC
                LIMIT 1
            ");
            if ($inventoryResult && ($invRow = $inventoryResult->fetch_assoc())) {
                if (($parsed['customer_name'] ?? '' ) === '' || ($parsed['customer_name'] ?? '') === 'N/A') {
                    $parsed['customer_name'] = $invRow['description'] ?? $parsed['customer_name'];
                }
                if ($oltName === '' || $oltName === 'N/A') {
                    $oltName = $invRow['olt_name'] ?? $oltName;
                }
            }
        }

        $deviceMeta[$deviceId] = [
            'device_id' => $deviceId,
            'serial_number' => (string) ($parsed['serial_number'] ?? 'N/A'),
            'customer_name' => (string) ($parsed['customer_name'] ?? 'N/A'),
            'pppoe_username' => (string) ($parsed['pppoe_username'] ?? 'N/A'),
            'wifi_ssid' => (string) ($parsed['wifi_ssid'] ?? 'N/A'),
            'product_class' => (string) ($parsed['product_class'] ?? 'N/A'),
            'mac_address' => (string) ($parsed['mac_address'] ?? 'N/A'),
            'olt_name' => (string) ($oltName ?: 'N/A'),
            'search_text' => strtolower(implode(' ', [
                $parsed['device_id'] ?? '',
                $parsed['serial_number'] ?? '',
                $parsed['customer_name'] ?? '',
                $parsed['pppoe_username'] ?? '',
                $parsed['wifi_ssid'] ?? '',
                $parsed['product_class'] ?? '',
                $parsed['mac_address'] ?? '',
                $oltName ?? '',
            ])),
        ];
    }
}

if ($query !== '') {
    
    $tasks = array_values(array_filter($tasks, static function (array $task) use ($query, $deviceMeta): bool {
        $deviceId = (string) ($task['device'] ?? '');
        $haystack = strtolower(implode(' ', [
            $task['name'] ?? '',
            $task['channel'] ?? '',
            $task['device'] ?? '',
            $deviceMeta[$deviceId]['search_text'] ?? '',
        ]));
        return str_contains($haystack, $query);
    }));

    $faults = array_values(array_filter($faults, static function (array $fault) use ($query, $deviceMeta): bool {
        $deviceId = (string) ($fault['device'] ?? $fault['deviceId'] ?? '');
        $haystack = strtolower(implode(' ', [
            $fault['channel'] ?? '',
            $fault['device'] ?? '',
            $fault['deviceId'] ?? '',
            $fault['message'] ?? '',
            $fault['faultString'] ?? '',
            $fault['detail'] ?? '',
            $fault['error'] ?? '',
            $deviceMeta[$deviceId]['search_text'] ?? '',
        ]));
        return str_contains($haystack, $query);
    }));
}

$byName = [];
$byFaultType = [
    'connection_request' => 0,
    'timeout' => 0,
    'other' => 0,
];
$latestTaskTime = null;
$oldestTaskTime = null;
$taskSamples = [];
$queuedByOlt = [];
$queuedByVendor = [];

foreach ($tasks as $task) {
    $name = (string) ($task['name'] ?? 'unknown');
    $deviceId = (string) ($task['device'] ?? '');
    $meta = $deviceMeta[$deviceId] ?? [];
    if (!isset($byName[$name])) {
        $byName[$name] = 0;
    }
    $byName[$name]++;

    $oltName = (string) ($meta['olt_name'] ?? 'N/A');
    $vendor = (string) ($meta['product_class'] ?? 'N/A');
    $queuedByOlt[$oltName] = ($queuedByOlt[$oltName] ?? 0) + 1;
    $queuedByVendor[$vendor] = ($queuedByVendor[$vendor] ?? 0) + 1;

    $ts = pickTime($task);
    if ($ts !== null) {
        if ($latestTaskTime === null || $ts > $latestTaskTime) {
            $latestTaskTime = $ts;
        }
        if ($oldestTaskTime === null || $ts < $oldestTaskTime) {
            $oldestTaskTime = $ts;
        }
    }

    if (count($taskSamples) < 20) {
        $taskSamples[] = [
            'device' => $deviceId ?: 'N/A',
            'serial_number' => $meta['serial_number'] ?? 'N/A',
            'customer_name' => $meta['customer_name'] ?? 'N/A',
            'olt_name' => $oltName,
            'product_class' => $vendor,
            'name' => $name,
            'timestamp' => $ts ? date('Y-m-d H:i:s', $ts) : 'N/A',
        ];
    }
}

arsort($byName);
$byNameTop = array_slice($byName, 0, 8, true);

$now = time();
$faults24h = 0;
$faultByChannel = [];
$faultSamples = [];
$faultByOlt = [];
$faultByVendor = [];

foreach ($faults as $fault) {
    $ts = pickTime($fault);
    $deviceId = (string) ($fault['device'] ?? $fault['deviceId'] ?? '');
    $meta = $deviceMeta[$deviceId] ?? [];
    $oltName = (string) ($meta['olt_name'] ?? 'N/A');
    $vendor = (string) ($meta['product_class'] ?? 'N/A');
    if ($ts !== null && ($now - $ts) <= 86400) {
        $faults24h++;
    }

    $channel = (string) ($fault['channel'] ?? 'unknown');
    if (!isset($faultByChannel[$channel])) {
        $faultByChannel[$channel] = 0;
    }
    $faultByChannel[$channel]++;

    $faultText = strtolower(trim((string) (($fault['message'] ?? $fault['faultString'] ?? $fault['detail'] ?? $fault['error'] ?? ''))));
    if (str_contains($faultText, 'connection request')) {
        $byFaultType['connection_request']++;
    } elseif (str_contains($faultText, 'timeout') || str_contains($faultText, 'timed out') || str_contains($faultText, 'etimedout')) {
        $byFaultType['timeout']++;
    } else {
        $byFaultType['other']++;
    }

    $faultByOlt[$oltName] = ($faultByOlt[$oltName] ?? 0) + 1;
    $faultByVendor[$vendor] = ($faultByVendor[$vendor] ?? 0) + 1;

    if (count($faultSamples) < 20) {
        $faultSamples[] = [
            'device' => $deviceId ?: 'N/A',
            'serial_number' => $meta['serial_number'] ?? 'N/A',
            'customer_name' => $meta['customer_name'] ?? 'N/A',
            'olt_name' => $oltName,
            'product_class' => $vendor,
            'channel' => $channel,
            'message' => $fault['message'] ?? $fault['faultString'] ?? $fault['detail'] ?? $fault['error'] ?? 'N/A',
            'timestamp' => $ts ? date('Y-m-d H:i:s', $ts) : 'N/A',
        ];
    }
}

arsort($faultByChannel);
arsort($queuedByOlt);
arsort($queuedByVendor);
arsort($faultByOlt);
arsort($faultByVendor);

jsonResponse([
    'success' => true,
    'query' => $query,
    'summary' => [
        'queued_total' => count($tasks),
        'faults_total' => count($faults),
        'faults_24h' => $faults24h,
        'connection_request_faults' => $byFaultType['connection_request'],
        'timeout_faults' => $byFaultType['timeout'],
        'other_faults' => $byFaultType['other'],
        'latest_task_at' => $latestTaskTime ? date('Y-m-d H:i:s', $latestTaskTime) : null,
        'oldest_task_at' => $oldestTaskTime ? date('Y-m-d H:i:s', $oldestTaskTime) : null,
    ],
    'queued_by_name' => $byNameTop,
    'queued_by_olt' => array_slice($queuedByOlt, 0, 8, true),
    'queued_by_vendor' => array_slice($queuedByVendor, 0, 8, true),
    'faults_by_channel' => array_slice($faultByChannel, 0, 8, true),
    'faults_by_olt' => array_slice($faultByOlt, 0, 8, true),
    'faults_by_vendor' => array_slice($faultByVendor, 0, 8, true),
    'task_samples' => $taskSamples,
    'fault_samples' => $faultSamples,
]);
