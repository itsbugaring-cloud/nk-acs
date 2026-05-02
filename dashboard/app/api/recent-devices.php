<?php
require_once __DIR__ . '/../config/config.php';

// Increase timeout for large dataset
set_time_limit(20);

requireLogin();

use App\GenieACS;
use App\GenieACS_Fast;

header('Content-Type: application/json');

try {
    // Get GenieACS credentials from database
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT host, port, username, password FROM genieacs_credentials LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse([
            'success' => false,
            'message' => 'GenieACS not configured'
        ]);
        exit;
    }

    $config = $result->fetch_assoc();
    $stmt->close();

    // Initialize GenieACS client
    $genieacs = new GenieACS(
        $config['host'],
        $config['port'],
        $config['username'],
        $config['password']
    );

    $recentDevices = [];

    $result = $genieacs->walkDevices(function ($device) use (&$recentDevices) {
        $parsed = GenieACS_Fast::parseDeviceDataFast($device);
        $parsed['last_inform_timestamp'] = isset($device['_lastInform']) ? strtotime($device['_lastInform']) : 0;
        $recentDevices[] = $parsed;
    }, [], 50);

    if (!$result['success']) {
        jsonResponse([
            'success' => false,
            'message' => 'Failed to fetch devices from GenieACS'
        ]);
        exit;
    }

    // Sort by last inform (most recent first)
    usort($recentDevices, function($a, $b) {
        return $b['last_inform_timestamp'] - $a['last_inform_timestamp'];
    });

    // Take only top 10 most recent
    $recentDevices = array_slice($recentDevices, 0, 10);

    jsonResponse([
        'success' => true,
        'devices' => $recentDevices
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
