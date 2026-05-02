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

function currentCrUrl(array $device): string {
    foreach ([['InternetGatewayDevice', 'ManagementServer', 'ConnectionRequestURL'], ['Device', 'ManagementServer', 'ConnectionRequestURL']] as $path) {
        $value = nestedValue($device, $path);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

$options = getopt('', [
    'host::',
    'port::',
    'username::',
    'password::',
    'limit::',
    'dry-run',
]);

$host = (string) cliOption($options, 'host', envString('DASHBOARD_GENIEACS_HOST', '127.0.0.1'));
$port = (int) cliOption($options, 'port', envString('DASHBOARD_GENIEACS_PORT', '7557'));
$username = (string) cliOption($options, 'username', envString('DASHBOARD_GENIEACS_USERNAME', ''));
$password = (string) cliOption($options, 'password', envString('DASHBOARD_GENIEACS_PASSWORD', ''));
$limit = max(0, (int) cliOption($options, 'limit', 0));
$dryRun = array_key_exists('dry-run', $options);

$client = new GenieACS($host, $port, $username, $password);
$result = $client->getDevices([], 0, 0);
if (!$result['success']) {
    fwrite(STDERR, "Failed to fetch devices from GenieACS NBI.\n");
    exit(1);
}

$devices = is_array($result['data']) ? $result['data'] : [];
$targets = [];
foreach ($devices as $device) {
    $id = (string) ($device['_id'] ?? '');
    if ($id === '' || !preg_match('/^C83A35-HG3V1%2E0-TDTC/i', $id)) {
        continue;
    }
    $targets[] = [
        'id' => $id,
        'cr' => currentCrUrl($device),
        'lastInform' => (string) ($device['_lastInform'] ?? ''),
    ];
}

usort($targets, static fn ($a, $b) => strcmp($b['lastInform'], $a['lastInform']));

if ($limit > 0) {
    $targets = array_slice($targets, 0, $limit);
}

echo sprintf("Found %d TDTC device(s)\n", count($targets));

if ($dryRun) {
    foreach ($targets as $target) {
        echo sprintf("[dry-run] %s :: CR=%s :: lastInform=%s\n", $target['id'], $target['cr'], $target['lastInform']);
    }
    exit(0);
}

$success = 0;
$failure = 0;
foreach ($targets as $target) {
    $response = $client->summonDevice($target['id']);
    if (!empty($response['success'])) {
        $success++;
        echo sprintf("[ok] %s :: CR=%s :: lastInform=%s\n", $target['id'], $target['cr'], $target['lastInform']);
        continue;
    }
    $failure++;
    $error = $response['error'] ?? ('HTTP ' . ($response['http_code'] ?? 'unknown'));
    echo sprintf("[fail] %s :: %s\n", $target['id'], (string) $error);
}

echo sprintf("Completed. Success=%d Failure=%d\n", $success, $failure);
