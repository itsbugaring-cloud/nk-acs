<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/GenieACS.php';

use App\GenieACS;

function cliOption(array $options, string $name, $default = null) {
    return array_key_exists($name, $options) ? $options[$name] : $default;
}

function envString(string $name, string $default = ''): string {
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : (string) $value;
}

function getNestedValue(array $device, array $path): mixed {
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

function currentAcsUrl(array $device): string {
    $paths = [
        ['InternetGatewayDevice', 'ManagementServer', 'URL'],
        ['Device', 'ManagementServer', 'URL'],
    ];

    foreach ($paths as $path) {
        $value = getNestedValue($device, $path);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function buildParameterValues(string $url, string $username, string $password): array {
    return [
        ['InternetGatewayDevice.ManagementServer.URL', $url, 'xsd:string'],
        ['InternetGatewayDevice.ManagementServer.Username', $username, 'xsd:string'],
        ['InternetGatewayDevice.ManagementServer.Password', $password, 'xsd:string'],
        ['InternetGatewayDevice.ManagementServer.PeriodicInformEnable', true, 'xsd:boolean'],
        ['InternetGatewayDevice.ManagementServer.PeriodicInformInterval', 300, 'xsd:unsignedInt'],
        ['Device.ManagementServer.URL', $url, 'xsd:string'],
        ['Device.ManagementServer.Username', $username, 'xsd:string'],
        ['Device.ManagementServer.Password', $password, 'xsd:string'],
        ['Device.ManagementServer.PeriodicInformEnable', true, 'xsd:boolean'],
        ['Device.ManagementServer.PeriodicInformInterval', 300, 'xsd:unsignedInt'],
    ];
}

function stderr(string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
}

$options = getopt('', [
    'host::',
    'port::',
    'username::',
    'password::',
    'target-url::',
    'target-username::',
    'target-password::',
    'old-url::',
    'limit::',
    'timeout::',
    'dry-run',
    'include-slash',
]);

$host = (string) cliOption($options, 'host', envString('DASHBOARD_GENIEACS_HOST', '127.0.0.1'));
$port = (int) cliOption($options, 'port', envString('DASHBOARD_GENIEACS_PORT', '7557'));
$username = (string) cliOption($options, 'username', envString('DASHBOARD_GENIEACS_USERNAME', ''));
$password = (string) cliOption($options, 'password', envString('DASHBOARD_GENIEACS_PASSWORD', ''));
$targetUrl = rtrim((string) cliOption($options, 'target-url', envString('GENIEACS_CWMP_URL', 'http://10.88.0.100:7547')), '/');
$targetUsername = (string) cliOption($options, 'target-username', envString('GENIEACS_CPE_USERNAME', 'caesarbugar'));
$targetPassword = (string) cliOption($options, 'target-password', envString('GENIEACS_CPE_PASSWORD', 'CaesarBugar007'));
$limit = max(0, (int) cliOption($options, 'limit', 0));
$timeout = max(1000, (int) cliOption($options, 'timeout', 8000));
$dryRun = array_key_exists('dry-run', $options);

$oldUrls = [];
$customOldUrl = cliOption($options, 'old-url');
if (is_string($customOldUrl) && $customOldUrl !== '') {
    $oldUrls[] = $customOldUrl;
} else {
    $oldUrls[] = 'http://ont.alinos-dashboard.my.id';
}

if (array_key_exists('include-slash', $options)) {
    $oldUrls[] = $targetUrl . '/';
}

$oldUrls = array_values(array_unique(array_filter(array_map(
    static fn ($value) => rtrim(trim((string) $value), "\r\n"),
    $oldUrls
))));

$client = new GenieACS($host, $port, $username, $password);
$deviceResult = $client->getDevices([], 0, 0);

if (!$deviceResult['success']) {
    stderr('Failed to fetch devices from GenieACS NBI.');
    exit(1);
}

$devices = is_array($deviceResult['data']) ? $deviceResult['data'] : [];
$targets = [];

foreach ($devices as $device) {
    if (!is_array($device) || !isset($device['_id'])) {
        continue;
    }

    $currentUrl = currentAcsUrl($device);
    if ($currentUrl === '' || !in_array($currentUrl, $oldUrls, true)) {
        continue;
    }

    $targets[] = [
        'id' => (string) $device['_id'],
        'url' => $currentUrl,
    ];
}

if ($limit > 0) {
    $targets = array_slice($targets, 0, $limit);
}

echo sprintf(
    "Found %d target device(s) matching: %s\n",
    count($targets),
    implode(', ', $oldUrls),
);

if (count($targets) === 0) {
    exit(0);
}

$parameterValues = buildParameterValues($targetUrl, $targetUsername, $targetPassword);

if ($dryRun) {
    foreach ($targets as $target) {
        echo sprintf("[dry-run] %s :: %s -> %s\n", $target['id'], $target['url'], $targetUrl);
    }
    exit(0);
}

$successCount = 0;
$failureCount = 0;

foreach ($targets as $target) {
    $response = $client->setParameterValues($target['id'], $parameterValues, $timeout);
    $ok = !empty($response['success']);

    if ($ok) {
        $successCount++;
        echo sprintf("[ok] %s :: %s -> %s\n", $target['id'], $target['url'], $targetUrl);
        continue;
    }

    $failureCount++;
    $error = $response['error'] ?? ('HTTP ' . ($response['http_code'] ?? 'unknown'));
    echo sprintf("[fail] %s :: %s :: %s\n", $target['id'], $target['url'], (string) $error);
}

echo sprintf("Completed. Success=%d Failure=%d\n", $successCount, $failureCount);
