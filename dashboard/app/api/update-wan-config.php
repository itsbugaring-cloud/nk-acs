<?php
/**
 * Update WAN Connection Configuration
 *
 * Updates WAN connection parameters on ONU via GenieACS TR-069
 *
 * Input (POST JSON):
 * {
 *     "device_id": "A4F33B-ZX%2DF663NV3a%20XPON-ZICG295C078F",
 *     "connection_index": 2,
 *     "connection_type": "ppp",  // "ppp" or "ip"
 *     "parameters": {
 *         "Enable": true,
 *         "ConnectionType": "IP_Routed",
 *         "Username": "user@isp",
 *         "Password": "password",
 *         "NATEnabled": true,
 *         "X_CT-COM_VLANID": 30
 *     }
 * }
 *
 * Output:
 * {
 *     "success": true,
 *     "message": "WAN connection updated successfully",
 *     "task_status": "queued"
 * }
 */

require_once __DIR__ . '/../config/config.php';
use App\GenieACS;
use App\WanConfigBuilder;

header('Content-Type: application/json');

// Require login
requireLogin();
enforceCpeWriteGuard('Update WAN ONT');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['device_id']) || !isset($input['connection_index']) || !isset($input['connection_type']) || !isset($input['parameters'])) {
    jsonResponse(false, 'Missing required fields: device_id, connection_index, connection_type, parameters');
}

$deviceId = $input['device_id'];
$connectionIndex = intval($input['connection_index']);
$connectionType = strtolower($input['connection_type']); // "ppp" or "ip"
$parameters = $input['parameters'];

// Validate connection index (1-8)
if ($connectionIndex < 1 || $connectionIndex > 8) {
    jsonResponse(false, 'Invalid connection index. Must be between 1 and 8.');
}

// Validate connection type
if (!in_array($connectionType, ['ppp', 'ip'])) {
    jsonResponse(false, 'Invalid connection type. Must be "ppp" or "ip".');
}

$allowedParams = [
    'Enable',
    'ConnectionType',
    'Username',
    'Password',
    'NATEnabled',
    'X_CT-COM_ServiceList',
    'X_CT-COM_LanInterface',
    'X_CT-COM_VLANID',
    'ServiceList',
    'LanInterface',
    'VLANID',
];

foreach ($parameters as $key => $value) {
    if (!in_array($key, $allowedParams, true)) {
        jsonResponse(false, "Invalid parameter: {$key}");
    }
}

// Validate that we have at least one parameter to update
if (empty($parameters) || !is_array($parameters)) {
    jsonResponse(false, 'No valid parameters provided for update');
}

// Get GenieACS credentials
$db = getDBConnection();
$stmt = $db->prepare("SELECT host, port, username, password FROM genieacs_credentials LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$genieConfig = $result->fetch_assoc();

if (!$genieConfig) {
    jsonResponse(false, 'GenieACS credentials not configured');
}

// Initialize GenieACS client
$genieacs = new GenieACS(
    $genieConfig['host'],
    $genieConfig['port'],
    $genieConfig['username'],
    $genieConfig['password']
);

$deviceResult = $genieacs->getDevice($deviceId);
$deviceData = $deviceResult['success'] ? $deviceResult['data'] : [];
$genieParams = WanConfigBuilder::buildParameterValues($deviceData, $connectionIndex, $connectionType, $parameters);

if (empty($genieParams)) {
    jsonResponse(false, 'No valid WAN parameters could be mapped for this device');
}

$result = $genieacs->setParameterValues($deviceId, $genieParams);

if ($result['success']) {
    // Determine task status based on HTTP code
    $taskStatus = isset($result['http_code']) && $result['http_code'] == 200 ? 'immediate' : 'queued';

    jsonResponse(true, 'WAN connection updated successfully', [
        'task_status' => $taskStatus,
        'parameters_updated' => count($genieParams),
        'connection_path' => WanConfigBuilder::buildDeleteTarget($connectionIndex, $connectionType)
    ]);
} else {
    jsonResponse(false, 'Failed to update WAN connection: ' . ($result['error'] ?? 'Unknown error'));
}
