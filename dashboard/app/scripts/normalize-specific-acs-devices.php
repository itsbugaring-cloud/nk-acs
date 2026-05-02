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

function loadActiveGenieAcsCredentials(mysqli $conn): ?array {
    $result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    return is_array($row) ? $row : null;
}

function buildParameterValues(string $url, string $username, string $password, int $interval): array {
    return [
        ['InternetGatewayDevice.ManagementServer.URL', $url, 'xsd:string'],
        ['InternetGatewayDevice.ManagementServer.Username', $username, 'xsd:string'],
        ['InternetGatewayDevice.ManagementServer.Password', $password, 'xsd:string'],
        ['InternetGatewayDevice.ManagementServer.PeriodicInformEnable', true, 'xsd:boolean'],
        ['InternetGatewayDevice.ManagementServer.PeriodicInformInterval', $interval, 'xsd:unsignedInt'],
        ['Device.ManagementServer.URL', $url, 'xsd:string'],
        ['Device.ManagementServer.Username', $username, 'xsd:string'],
        ['Device.ManagementServer.Password', $password, 'xsd:string'],
        ['Device.ManagementServer.PeriodicInformEnable', true, 'xsd:boolean'],
        ['Device.ManagementServer.PeriodicInformInterval', $interval, 'xsd:unsignedInt'],
    ];
}

$options = getopt('', [
    'device-id:',
    'target-url::',
    'target-username::',
    'target-password::',
    'interval::',
    'timeout::',
    'dry-run',
]);

$deviceIds = cliOption($options, 'device-id', []);
if (!is_array($deviceIds)) {
    $deviceIds = [$deviceIds];
}
$deviceIds = array_values(array_filter(array_map(static fn($value) => trim((string) $value), $deviceIds)));

if (!$deviceIds) {
    stderr('Usage: php normalize-specific-acs-devices.php --device-id=<id> [--device-id=<id> ...] [--target-url=http://10.88.0.100:7547] [--interval=120] [--dry-run]');
    exit(1);
}

$targetUrl = rtrim((string) cliOption($options, 'target-url', 'http://10.88.0.100:7547'), '/');
$targetUsername = (string) cliOption($options, 'target-username', 'caesarbugar');
$targetPassword = (string) cliOption($options, 'target-password', 'CaesarBugar007');
$interval = max(30, (int) cliOption($options, 'interval', 120));
$timeout = max(1000, (int) cliOption($options, 'timeout', 8000));
$dryRun = array_key_exists('dry-run', $options);

$conn = getDBConnection();
$credentials = loadActiveGenieAcsCredentials($conn);
if (!$credentials) {
    stderr('GenieACS credentials aktif tidak ditemukan di database.');
    exit(1);
}

$client = new GenieACS(
    $credentials['host'],
    (int) $credentials['port'],
    (string) $credentials['username'],
    (string) $credentials['password']
);

$parameterValues = buildParameterValues($targetUrl, $targetUsername, $targetPassword, $interval);

echo sprintf(
    "Targets=%d targetUrl=%s interval=%d dryRun=%s\n",
    count($deviceIds),
    $targetUrl,
    $interval,
    $dryRun ? 'yes' : 'no'
);

if ($dryRun) {
    foreach ($deviceIds as $deviceId) {
        echo sprintf("[dry-run] %s\n", $deviceId);
    }
    exit(0);
}

$success = 0;
$failure = 0;

foreach ($deviceIds as $deviceId) {
    $response = $client->setParameterValues($deviceId, $parameterValues, $timeout);
    if (!empty($response['success'])) {
        $success++;
        echo sprintf("[ok] %s\n", $deviceId);
        continue;
    }

    $failure++;
    $error = $response['error'] ?? ('HTTP ' . ($response['http_code'] ?? 'unknown'));
    echo sprintf("[fail] %s :: %s\n", $deviceId, (string) $error);
}

echo sprintf("Completed. Success=%d Failure=%d\n", $success, $failure);
