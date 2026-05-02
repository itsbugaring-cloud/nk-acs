<?php
/**
 * Add New WAN Connection Configuration
 *
 * Creates a new WAN connection on ONU via GenieACS TR-069
 *
 * Input (POST JSON):
 * {
 *     "device_id": "A4F33B-ZX%2DF663NV3a%20XPON-ZICG295C078F",
 *     "connection_index": 4,
 *     "connection_type": "ppp",  // "ppp" or "ip"
 *     "name": "4_INTERNET_R_VID_100",
 *     "parameters": {
 *         "Enable": true,
 *         "ConnectionType": "IP_Routed",
 *         "Username": "user@isp",
 *         "Password": "password",
 *         "NATEnabled": true,
 *         "X_CT-COM_ServiceList": "INTERNET",
 *         "X_CT-COM_VLANID": 100
 *     }
 * }
 *
 * Output:
 * {
 *     "success": true,
 *     "message": "New WAN connection created successfully",
 *     "connection_index": 4,
 *     "task_status": "queued"
 * }
 */

require_once __DIR__ . '/../config/config.php';
use App\GenieACS;
use App\WanConfigBuilder;

header('Content-Type: application/json');

// Require login
requireLogin();
enforceCpeWriteGuard('Tambah WAN profile ONT');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['device_id']) || !isset($input['connection_index']) || !isset($input['connection_type']) || !isset($input['parameters'])) {
    jsonResponse(false, 'Missing required fields: device_id, connection_index, connection_type, parameters');
}

$deviceId = $input['device_id'];
$connectionIndex = intval($input['connection_index']);
$connectionType = strtolower($input['connection_type']); // "ppp" or "ip"
$connectionName = $input['name'] ?? '';
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
    'Name',
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

$normalizedParams = [];
if (!empty($connectionName)) {
    $normalizedParams['Name'] = $connectionName;
}

foreach ($parameters as $key => $value) {
    if (!in_array($key, $allowedParams, true)) {
        jsonResponse(false, "Invalid parameter: {$key}");
    }
    $normalizedParams[$key] = $value;
}

// Validate required parameters for new connection
$requiredParams = ['ConnectionType'];
foreach ($requiredParams as $param) {
    if (!isset($parameters[$param]) && $param !== 'Name') {
        jsonResponse(false, "Missing required parameter: {$param}");
    }
}

// For PPPoE connections, username and password are required
if ($connectionType === 'ppp') {
    if (empty($parameters['Username']) || empty($parameters['Password'])) {
        jsonResponse(false, 'Username and Password are required for PPPoE connections');
    }
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
$genieParams = WanConfigBuilder::buildParameterValues($deviceData, $connectionIndex, $connectionType, $normalizedParams);

if (empty($genieParams)) {
    jsonResponse(false, 'No valid WAN parameters could be mapped for this device');
}

$objectCreate = $genieacs->addObject($deviceId, WanConfigBuilder::buildObjectPath($connectionIndex, $connectionType));
$result = $genieacs->setParameterValues($deviceId, $genieParams);

if ($result['success']) {
    // Determine task status based on HTTP code
    $taskStatus = isset($result['http_code']) && $result['http_code'] == 200 ? 'immediate' : 'queued';

    jsonResponse(true, 'New WAN connection created successfully', [
        'task_status' => $taskStatus,
        'connection_index' => $connectionIndex,
        'connection_type' => $connectionType,
        'parameters_set' => count($genieParams),
        'connection_path' => WanConfigBuilder::buildDeleteTarget($connectionIndex, $connectionType),
        'object_create_http_code' => $objectCreate['http_code'] ?? null
    ]);
} else {
    jsonResponse(false, 'Failed to create WAN connection: ' . ($result['error'] ?? 'Unknown error'));
}
