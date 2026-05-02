<?php
/**
 * Delete WAN Connection Configuration
 *
 * Deletes a WAN connection on ONU via GenieACS TR-069
 * Includes TR069 connection protection with confirmation requirement
 *
 * Input (POST JSON):
 * {
 *     "device_id": "A4F33B-ZX%2DF663NV3a%20XPON-ZICG295C078F",
 *     "connection_index": 2,
 *     "connection_type": "ppp",  // "ppp" or "ip"
 *     "connection_name": "2_INTERNET_R_VID_30",
 *     "service_list": "INTERNET",
 *     "confirm_tr069_delete": false  // Must be true for TR069 connections
 * }
 *
 * Output (TR069 Blocked):
 * {
 *     "success": false,
 *     "error": "TR069 connection deletion blocked",
 *     "requires_confirmation": true,
 *     "is_tr069": true
 * }
 *
 * Output (Success):
 * {
 *     "success": true,
 *     "message": "WAN connection deleted successfully",
 *     "task_status": "queued"
 * }
 */

require_once __DIR__ . '/../config/config.php';
use App\GenieACS;
use App\WanConfigBuilder;

header('Content-Type: application/json');

// Require login
requireLogin();
enforceCpeWriteGuard('Hapus WAN profile ONT');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['device_id']) || !isset($input['connection_index']) || !isset($input['connection_type'])) {
    jsonResponse(false, 'Missing required fields: device_id, connection_index, connection_type');
}

$deviceId = $input['device_id'];
$connectionIndex = intval($input['connection_index']);
$connectionType = strtolower($input['connection_type']); // "ppp" or "ip"
$connectionName = $input['connection_name'] ?? '';
$serviceList = $input['service_list'] ?? '';
$confirmTR069Delete = $input['confirm_tr069_delete'] ?? false;

// Validate connection index (1-8)
if ($connectionIndex < 1 || $connectionIndex > 8) {
    jsonResponse(false, 'Invalid connection index. Must be between 1 and 8.');
}

// Validate connection type
if (!in_array($connectionType, ['ppp', 'ip'])) {
    jsonResponse(false, 'Invalid connection type. Must be "ppp" or "ip".');
}

/**
 * Check if this is a TR069 connection
 * Detection logic: Check if service name or connection name contains TR069/CWMP
 */
function isTR069Connection($connectionName, $serviceList) {
    $serviceName = strtoupper($serviceList);
    $connName = strtoupper($connectionName);

    return strpos($serviceName, 'TR069') !== false ||
           strpos($serviceName, 'CWMP') !== false ||
           strpos($connName, 'TR069') !== false ||
           strpos($connName, 'CWMP') !== false;
}

// Check if this is a TR069 connection
$isTR069 = isTR069Connection($connectionName, $serviceList);

// Block TR069 deletion if not confirmed
if ($isTR069 && !$confirmTR069Delete) {
    jsonResponse(false, 'TR069 connection deletion blocked', [
        'requires_confirmation' => true,
        'is_tr069' => true,
        'connection_name' => $connectionName,
        'service_list' => $serviceList,
        'warning' => 'Deleting this connection will break device communication with GenieACS'
    ]);
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
$disableParams = WanConfigBuilder::buildParameterValues($deviceData, $connectionIndex, $connectionType, [
    'Enable' => false,
]);
$deleteResult = $genieacs->deleteObject($deviceId, WanConfigBuilder::buildDeleteTarget($connectionIndex, $connectionType));
$result = $genieacs->setParameterValues($deviceId, $disableParams);

if ($result['success']) {
    // Determine task status based on HTTP code
    $taskStatus = isset($result['http_code']) && $result['http_code'] == 200 ? 'immediate' : 'queued';

    jsonResponse(true, 'WAN connection deleted successfully (disabled)', [
        'task_status' => $taskStatus,
        'connection_path' => WanConfigBuilder::buildDeleteTarget($connectionIndex, $connectionType),
        'is_tr069' => $isTR069,
        'method' => ($deleteResult['success'] ?? false) ? 'deleteObject+disable' : 'disabled',
        'note' => ($deleteResult['success'] ?? false)
            ? 'DeleteObject task dikirim, lalu koneksi juga di-disable sebagai fallback aman.'
            : 'Connection has been disabled. Full deletion may require device support for deleteObject.',
        'delete_http_code' => $deleteResult['http_code'] ?? null
    ]);
} else {
    jsonResponse(false, 'Failed to delete WAN connection: ' . ($result['error'] ?? 'Unknown error'));
}
