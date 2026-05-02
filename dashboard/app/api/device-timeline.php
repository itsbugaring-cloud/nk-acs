<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

$deviceId = trim((string) ($_GET['device_id'] ?? ''));
$serial = strtoupper(trim((string) ($_GET['serial'] ?? '')));
$limit = min(30, max(5, (int) ($_GET['limit'] ?? 12)));

if ($deviceId === '' && $serial === '') {
    jsonResponse(['success' => false, 'message' => 'device_id atau serial wajib diisi']);
}

$conn = getDBConnection();
$events = [];

$monitoringTableExists = false;
$alarmTableExists = false;

$checkMonitoring = $conn->query("SHOW TABLES LIKE 'device_monitoring'");
if ($checkMonitoring && $checkMonitoring->num_rows > 0) {
    $monitoringTableExists = true;
}

$checkAlarm = $conn->query("SHOW TABLES LIKE 'telegram_alarm_events'");
if ($checkAlarm && $checkAlarm->num_rows > 0) {
    $alarmTableExists = true;
}

if ($monitoringTableExists && $deviceId !== '') {
    $stmt = $conn->prepare("
        SELECT device_id, status, notified, created_at
        FROM device_monitoring
        WHERE device_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT ?
    ");
    if ($stmt) {
        $stmt->bind_param('si', $deviceId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $status = strtolower((string) ($row['status'] ?? 'unknown'));
            $events[] = [
                'source' => 'monitor',
                'severity' => $status === 'online' ? 'info' : 'warning',
                'title' => 'Status ' . strtoupper($status),
                'message' => ($status === 'online' ? 'ONT terdeteksi online oleh monitor.' : 'ONT terdeteksi offline oleh monitor.'),
                'meta' => [
                    'device_id' => $row['device_id'] ?? $deviceId,
                    'notified' => (int) ($row['notified'] ?? 0),
                ],
                'created_at' => $row['created_at'] ?? null,
            ];
        }
    }
}

if ($alarmTableExists) {
    $where = [];
    $params = [];
    $types = '';
    if ($deviceId !== '') {
        $where[] = 'device_id = ?';
        $params[] = $deviceId;
        $types .= 's';
    }
    if ($serial !== '') {
        $where[] = 'serial_number = ?';
        $params[] = $serial;
        $types .= 's';
    }

    if ($where) {
        $sql = "
            SELECT event_type, severity, title, message, status, olt_name, device_id, serial_number, created_at
            FROM telegram_alarm_events
            WHERE " . implode(' OR ', $where) . "
            ORDER BY created_at DESC, id DESC
            LIMIT ?
        ";
        $params[] = $limit;
        $types .= 'i';

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'source' => 'alarm',
                    'severity' => $row['severity'] ?? 'info',
                    'title' => $row['title'] ?? ($row['event_type'] ?? 'Alarm'),
                    'message' => $row['message'] ?? '',
                    'meta' => [
                        'event_type' => $row['event_type'] ?? '',
                        'status' => $row['status'] ?? 'open',
                        'olt_name' => $row['olt_name'] ?? '',
                        'device_id' => $row['device_id'] ?? '',
                        'serial_number' => $row['serial_number'] ?? '',
                    ],
                    'created_at' => $row['created_at'] ?? null,
                ];
            }
        }
    }
}

usort($events, static function (array $a, array $b): int {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$events = array_slice($events, 0, $limit);

jsonResponse([
    'success' => true,
    'items' => $events,
    'summary' => [
        'total' => count($events),
        'has_monitoring' => $monitoringTableExists,
        'has_alarm_events' => $alarmTableExists,
    ],
]);
