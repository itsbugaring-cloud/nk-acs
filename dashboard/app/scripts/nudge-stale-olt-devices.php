<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/GenieACS.php';

use App\GenieACS;

function cliOption(array $options, string $name, $default = null) {
    return array_key_exists($name, $options) ? $options[$name] : $default;
}

function stderr(string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
}

function nestedValue(array $device, array $path): mixed {
    $value = $device;
    foreach ($path as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }

    if (is_array($value) && array_key_exists('_value', $value)) {
        return $value['_value'];
    }

    return is_array($value) ? null : $value;
}

function extractSerial(array $device): string {
    $paths = [
        ['VirtualParameters', 'SerialNumber'],
        ['InternetGatewayDevice', 'DeviceInfo', 'SerialNumber'],
        ['Device', 'DeviceInfo', 'SerialNumber'],
    ];

    foreach ($paths as $path) {
        $value = nestedValue($device, $path);
        if (is_string($value) && trim($value) !== '') {
            return strtoupper(trim($value));
        }
    }

    $deviceId = isset($device['_id']) ? rawurldecode((string) $device['_id']) : '';
    if ($deviceId !== '' && str_contains($deviceId, '-')) {
        $parts = explode('-', $deviceId);
        $fallback = strtoupper(trim((string) end($parts)));
        if ($fallback !== '') {
            return $fallback;
        }
    }

    return '';
}

function extractConnectionRequestReachable(array $device): bool {
    $vp = nestedValue($device, ['VirtualParameters', 'ConnectionRequestReachable']);
    if (is_bool($vp)) {
        return $vp;
    }
    if (is_numeric($vp)) {
        return (int) $vp === 1;
    }
    if (is_string($vp)) {
        $normalized = strtolower(trim($vp));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
    }

    $url = nestedValue($device, ['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestURL'])
        ?? nestedValue($device, ['Device', 'ManagementServer', 'ConnectionRequestURL']);
    if (!is_string($url) || trim($url) === '') {
        return false;
    }

    $lower = strtolower(trim($url));
    if (!preg_match('#^https?://#', $lower)) {
        return false;
    }

    return !str_contains($lower, '0.0.0.0') && $lower !== 'n/a' && !str_contains($lower, '::');
}

function extractConnectionRequestUrl(array $device): string {
    $url = nestedValue($device, ['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestURL'])
        ?? nestedValue($device, ['Device', 'ManagementServer', 'ConnectionRequestURL']);

    return is_string($url) ? trim($url) : '';
}

function extractConnectionRequestUsername(array $device): string {
    $value = nestedValue($device, ['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestUsername'])
        ?? nestedValue($device, ['Device', 'ManagementServer', 'ConnectionRequestUsername']);

    return is_string($value) ? trim($value) : '';
}

function extractConnectionRequestPassword(array $device): string {
    $value = nestedValue($device, ['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestPassword'])
        ?? nestedValue($device, ['Device', 'ManagementServer', 'ConnectionRequestPassword']);

    return is_string($value) ? trim($value) : '';
}

function extractPeriodicInformInterval(array $device): ?int {
    $value = nestedValue($device, ['VirtualParameters', 'PeriodicInformIntervalActual'])
        ?? nestedValue($device, ['InternetGatewayDevice', 'ManagementServer', 'PeriodicInformInterval'])
        ?? nestedValue($device, ['Device', 'ManagementServer', 'PeriodicInformInterval']);

    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (int) $value : null;
}

function probeConnectionRequest(string $url, string $username, string $password, int $timeoutSec = 5): array {
    if ($url === '') {
        return ['ok' => false, 'result' => 'no-url'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_CONNECTTIMEOUT => $timeoutSec,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    if ($username !== '' || $password !== '') {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    }

    curl_exec($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($error !== '') {
        return ['ok' => false, 'result' => 'error:' . $error];
    }

    return [
        'ok' => in_array($code, [200, 204, 401], true),
        'result' => 'http:' . $code,
    ];
}

function loadActiveGenieAcsCredentials(mysqli $conn): ?array {
    $result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    return is_array($row) ? $row : null;
}

function loadInventorySerials(mysqli $conn, string $oltName): array {
    $sql = "
        SELECT DISTINCT UPPER(TRIM(inv.serial_number)) AS serial_number
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id
        WHERE olt.name = ?
          AND inv.serial_number IS NOT NULL
          AND TRIM(inv.serial_number) <> ''
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare inventory query.');
    }

    $stmt->bind_param('s', $oltName);
    $stmt->execute();
    $result = $stmt->get_result();

    $serials = [];
    while ($row = $result->fetch_assoc()) {
        $serial = (string) ($row['serial_number'] ?? '');
        if ($serial !== '') {
            $serials[] = $serial;
        }
    }

    $stmt->close();
    return array_values(array_unique($serials));
}

$options = getopt('', [
    'olt-name:',
    'stale-minutes::',
    'limit::',
    'timeout::',
    'probe-limit::',
    'probe-timeout::',
    'dry-run',
    'include-unreachable',
]);

$oltName = trim((string) cliOption($options, 'olt-name', ''));
if ($oltName === '') {
    stderr('Usage: php nudge-stale-olt-devices.php --olt-name="OLT Name" [--stale-minutes=15] [--limit=0] [--timeout=3000] [--dry-run] [--include-unreachable]');
    exit(1);
}

$staleMinutes = max(1, (int) cliOption($options, 'stale-minutes', 15));
$limit = max(0, (int) cliOption($options, 'limit', 0));
$timeout = max(1000, (int) cliOption($options, 'timeout', 3000));
$probeLimit = max(0, (int) cliOption($options, 'probe-limit', 0));
$probeTimeout = max(1, (int) cliOption($options, 'probe-timeout', 5));
$dryRun = array_key_exists('dry-run', $options);
$includeUnreachable = array_key_exists('include-unreachable', $options);

$conn = getDBConnection();
$credentials = loadActiveGenieAcsCredentials($conn);
if (!$credentials) {
    stderr('GenieACS credentials aktif tidak ditemukan di database.');
    exit(1);
}

$serials = loadInventorySerials($conn, $oltName);
if (!$serials) {
    stderr("Tidak ada serial inventory untuk {$oltName}.");
    exit(1);
}

$client = new GenieACS(
    $credentials['host'],
    (int) $credentials['port'],
    (string) $credentials['username'],
    (string) $credentials['password']
);

$deviceResult = $client->getDevices([], 0, 0);
if (empty($deviceResult['success']) || !is_array($deviceResult['data'])) {
    stderr('Gagal mengambil device dari GenieACS NBI.');
    exit(1);
}

$fetchedCount = count($deviceResult['data']);

$serialLookup = array_fill_keys($serials, true);
$cutoffTs = time() - ($staleMinutes * 60);
$targets = [];
$inventoryCount = count($serials);
$matchedCount = 0;

foreach ($deviceResult['data'] as $device) {
    if (!is_array($device) || !isset($device['_id'])) {
        continue;
    }

    $serial = extractSerial($device);
    if ($serial === '' || !isset($serialLookup[$serial])) {
        continue;
    }

    $matchedCount++;
    $lastInform = isset($device['_lastInform']) ? (string) $device['_lastInform'] : '';
    $lastInformTs = $lastInform !== '' ? strtotime($lastInform) : false;
    $isStale = $lastInform === '' || $lastInformTs === false || $lastInformTs < $cutoffTs;
    if (!$isStale) {
        continue;
    }

    $reachable = extractConnectionRequestReachable($device);
    if (!$includeUnreachable && !$reachable) {
        continue;
    }

    $targets[] = [
        'id' => (string) $device['_id'],
        'serial' => $serial,
        'lastInform' => $lastInform !== '' ? $lastInform : 'never',
        'reachable' => $reachable ? 'yes' : 'no',
        'informInterval' => extractPeriodicInformInterval($device),
        'crUrl' => extractConnectionRequestUrl($device),
        'crUser' => extractConnectionRequestUsername($device),
        'crPass' => extractConnectionRequestPassword($device),
    ];
}

usort($targets, static function (array $a, array $b): int {
    return strcmp($a['lastInform'], $b['lastInform']);
});

if ($limit > 0) {
    $targets = array_slice($targets, 0, $limit);
}

echo sprintf(
    "OLT=%s inventory=%d fetchedACS=%d matchedACS=%d staleTargets=%d reachableOnly=%s staleMinutes=%d\n",
    $oltName,
    $inventoryCount,
    $fetchedCount,
    $matchedCount,
    count($targets),
    $includeUnreachable ? 'no' : 'yes',
    $staleMinutes
);

if (!$targets) {
    exit(0);
}

foreach ($targets as $target) {
    echo sprintf(
        "[target] %s :: %s :: lastInform=%s :: CR=%s :: interval=%s\n",
        $target['serial'],
        $target['id'],
        $target['lastInform'],
        $target['reachable'],
        $target['informInterval'] === null ? 'n/a' : (string) $target['informInterval']
    );
}

if ($probeLimit > 0) {
    $probeTargets = array_slice($targets, 0, $probeLimit);
    foreach ($probeTargets as $target) {
        $probe = probeConnectionRequest($target['crUrl'], $target['crUser'], $target['crPass'], $probeTimeout);
        echo sprintf(
            "[probe] %s :: %s :: url=%s\n",
            $target['serial'],
            $probe['result'],
            $target['crUrl'] !== '' ? $target['crUrl'] : 'n/a'
        );
    }
}

if ($dryRun) {
    exit(0);
}

$success = 0;
$failure = 0;

foreach ($targets as $target) {
    $response = $client->summonDevice($target['id']);
    if (!empty($response['success'])) {
        $success++;
        echo sprintf("[ok] %s :: queued summon\n", $target['serial']);
        continue;
    }

    $failure++;
    $error = $response['error'] ?? ('HTTP ' . ($response['http_code'] ?? 'unknown'));
    echo sprintf("[fail] %s :: %s\n", $target['serial'], (string) $error);
}

echo sprintf("Completed. Success=%d Failure=%d\n", $success, $failure);
