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

function extractConnectionRequestUrl(array $device): ?string
{
    $paths = [
        ['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestURL', '_value'],
        ['Device', 'ManagementServer', 'ConnectionRequestURL', '_value'],
    ];

    foreach ($paths as $path) {
        $cursor = $device;
        $ok = true;
        foreach ($path as $key) {
            if (!isset($cursor[$key])) {
                $ok = false;
                break;
            }
            $cursor = $cursor[$key];
        }
        if ($ok && is_string($cursor) && $cursor !== '') {
            return $cursor;
        }
    }

    return null;
}

function isPrivateHost(?string $host): bool
{
    if (!$host) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }
    if (str_starts_with($host, '10.')) {
        return true;
    }
    if (str_starts_with($host, '192.168.')) {
        return true;
    }
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) === 1) {
        return true;
    }
    return false;
}

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

$devicesResult = DeviceCache::getDevices($genieacs);
if (!$devicesResult['success']) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil data device']);
}

$devices = is_array($devicesResult['data'] ?? null) ? $devicesResult['data'] : [];
$faultsResult = $genieacs->getFaults([], 500, 0);
$faults = ($faultsResult['success'] && is_array($faultsResult['data'] ?? null)) ? $faultsResult['data'] : [];

function pickFaultTime(array $row): ?int
{
    foreach (['_timestamp', 'timestamp', '_lastInform'] as $field) {
        if (!isset($row[$field])) {
            continue;
        }
        $value = $row[$field];
        if (is_numeric($value)) {
            $ts = (int) $value;
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

$recentCrFaultByDevice = [];
$now = time();
foreach ($faults as $fault) {
    $deviceId = (string) ($fault['device'] ?? $fault['deviceId'] ?? '');
    if ($deviceId === '') {
        continue;
    }

    $faultText = strtolower(trim((string) ($fault['message'] ?? $fault['faultString'] ?? $fault['detail'] ?? $fault['error'] ?? '')));
    if ($faultText === '') {
        continue;
    }

    $isCrFault = str_contains($faultText, 'connection request') ||
        str_contains($faultText, 'timeout') ||
        str_contains($faultText, 'timed out') ||
        str_contains($faultText, 'etimedout') ||
        str_contains($faultText, 'ehostunreach') ||
        str_contains($faultText, 'econnrefused');

    if (!$isCrFault) {
        continue;
    }

    $ts = pickFaultTime($fault);
    if ($ts !== null && ($now - $ts) > 86400) {
        continue;
    }

    $recentCrFaultByDevice[$deviceId] = [
        'channel' => (string) ($fault['channel'] ?? 'fault'),
        'message' => (string) ($fault['message'] ?? $fault['faultString'] ?? $fault['detail'] ?? $fault['error'] ?? 'Fault'),
        'timestamp' => $ts ? date('Y-m-d H:i:s', $ts) : 'N/A',
    ];
}

$summary = [
    'total' => 0,
    'recent_inform_alive' => 0,
    'private_nat' => 0,
    'recent_probe_failed' => 0,
    'url_only' => 0,
    'missing_url' => 0,
];

$byProductClass = [];
$byOlt = [];
$samples = [
    'recent_probe_failed' => [],
    'url_only' => [],
    'missing_url' => [],
];

foreach ($devices as $device) {
    $summary['total']++;
    $parsed = GenieACS_Fast::parseDeviceDataFast($device);
    $deviceId = (string) ($parsed['device_id'] ?? ($device['_id'] ?? ''));
    $serial = strtoupper(trim((string) ($parsed['serial_number'] ?? 'N/A')));
    $productClass = (string) ($parsed['product_class'] ?? ($device['_deviceId']['_ProductClass'] ?? 'Unknown'));
    $customerName = (string) ($parsed['customer_name'] ?? 'N/A');
    $oltName = (string) ($parsed['olt_name'] ?? 'N/A');
    if (($oltName === '' || $oltName === 'N/A') && $serial !== '' && $serial !== 'N/A') {
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
            if ($customerName === '' || $customerName === 'N/A') {
                $customerName = (string) ($invRow['description'] ?? $customerName);
            }
            $oltName = (string) ($invRow['olt_name'] ?? $oltName);
        }
    }

    if (!isset($byProductClass[$productClass])) {
        $byProductClass[$productClass] = [
            'total' => 0,
            'recent_inform_alive' => 0,
            'private_nat' => 0,
            'recent_probe_failed' => 0,
            'url_only' => 0,
            'missing_url' => 0,
        ];
    }
    $byProductClass[$productClass]['total']++;

    if (!isset($byOlt[$oltName])) {
        $byOlt[$oltName] = [
            'total' => 0,
            'recent_inform_alive' => 0,
            'private_nat' => 0,
            'recent_probe_failed' => 0,
            'url_only' => 0,
            'missing_url' => 0,
        ];
    }
    $byOlt[$oltName]['total']++;

    $url = extractConnectionRequestUrl($device);
    if (!$url) {
        $summary['missing_url']++;
        $byProductClass[$productClass]['missing_url']++;
        $byOlt[$oltName]['missing_url']++;
        if (count($samples['missing_url']) < 8) {
            $samples['missing_url'][] = [
                'device_id' => $deviceId ?: 'N/A',
                'serial_number' => $serial ?: 'N/A',
                'customer_name' => $customerName ?: 'N/A',
                'olt_name' => $oltName ?: 'N/A',
                'product_class' => $productClass ?: 'N/A',
            ];
        }
        continue;
    }

    $host = parse_url($url, PHP_URL_HOST);
    $lastInformAgeSec = is_numeric($parsed['last_inform_age_sec'] ?? null) ? (int) $parsed['last_inform_age_sec'] : null;
    $hasRecentProbeFailure = isset($recentCrFaultByDevice[$deviceId]);

    if ($hasRecentProbeFailure) {
        $summary['recent_probe_failed']++;
        $byProductClass[$productClass]['recent_probe_failed']++;
        $byOlt[$oltName]['recent_probe_failed']++;
        if (count($samples['recent_probe_failed']) < 8) {
            $samples['recent_probe_failed'][] = [
                'device_id' => $deviceId ?: 'N/A',
                'serial_number' => $serial ?: 'N/A',
                'customer_name' => $customerName ?: 'N/A',
                'olt_name' => $oltName ?: 'N/A',
                'product_class' => $productClass ?: 'N/A',
                'host' => is_string($host) ? $host : 'N/A',
                'message' => $recentCrFaultByDevice[$deviceId]['message'],
                'timestamp' => $recentCrFaultByDevice[$deviceId]['timestamp'],
            ];
        }
    } elseif ($lastInformAgeSec !== null && $lastInformAgeSec <= 900) {
        $summary['recent_inform_alive']++;
        $byProductClass[$productClass]['recent_inform_alive']++;
        $byOlt[$oltName]['recent_inform_alive']++;
    } elseif (is_string($host) && isPrivateHost($host)) {
        $summary['private_nat']++;
        $byProductClass[$productClass]['private_nat']++;
        $byOlt[$oltName]['private_nat']++;
    } else {
        $summary['url_only']++;
        $byProductClass[$productClass]['url_only']++;
        $byOlt[$oltName]['url_only']++;
        if (count($samples['url_only']) < 8) {
            $samples['url_only'][] = [
                'device_id' => $deviceId ?: 'N/A',
                'serial_number' => $serial ?: 'N/A',
                'customer_name' => $customerName ?: 'N/A',
                'olt_name' => $oltName ?: 'N/A',
                'product_class' => $productClass ?: 'N/A',
                'host' => is_string($host) ? $host : 'N/A',
                'last_inform_age_sec' => $lastInformAgeSec,
            ];
        }
    }
}

uasort($byProductClass, static function ($a, $b) {
    return [$b['recent_probe_failed'], $b['private_nat'], $b['url_only']]
        <=> [$a['recent_probe_failed'], $a['private_nat'], $a['url_only']];
});

uasort($byOlt, static function ($a, $b) {
    return [$b['recent_probe_failed'], $b['private_nat'], $b['url_only']]
        <=> [$a['recent_probe_failed'], $a['private_nat'], $a['url_only']];
});

jsonResponse([
    'success' => true,
    'summary' => $summary,
    'by_product_class' => array_slice($byProductClass, 0, 8, true),
    'by_olt' => array_slice($byOlt, 0, 8, true),
    'samples' => $samples,
]);
