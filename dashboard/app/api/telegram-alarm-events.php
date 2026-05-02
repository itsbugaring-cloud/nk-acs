<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

$conn = getDBConnection();
$conn->query("
    CREATE TABLE IF NOT EXISTS telegram_alarm_events (
        id INT(11) NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(64) NOT NULL,
        severity VARCHAR(32) NOT NULL DEFAULT 'info',
        scope VARCHAR(64) DEFAULT NULL,
        olt_name VARCHAR(255) DEFAULT NULL,
        device_id VARCHAR(255) DEFAULT NULL,
        serial_number VARCHAR(255) DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT DEFAULT NULL,
        payload LONGTEXT DEFAULT NULL,
        status ENUM('open','acked','resolved') NOT NULL DEFAULT 'open',
        acked_by VARCHAR(255) DEFAULT NULL,
        acked_at TIMESTAMP NULL DEFAULT NULL,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT current_timestamp(),
        updated_at TIMESTAMP NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (id),
        KEY idx_event_type (event_type),
        KEY idx_status (status),
        KEY idx_olt_name (olt_name),
        KEY idx_device_id (device_id),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['success' => false, 'message' => 'Payload tidak valid']);
    }

    $id = (int) ($input['id'] ?? 0);
    $action = (string) ($input['action'] ?? '');
    if ($id <= 0 || !in_array($action, ['ack', 'resolve', 'reopen'], true)) {
        jsonResponse(['success' => false, 'message' => 'Aksi alarm tidak valid']);
    }

    $username = (string) ($_SESSION['username'] ?? 'system');
    if ($action === 'ack') {
        $stmt = $conn->prepare("UPDATE telegram_alarm_events SET status = 'acked', acked_by = ?, acked_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $username, $id);
    } elseif ($action === 'resolve') {
        $stmt = $conn->prepare("UPDATE telegram_alarm_events SET status = 'resolved', resolved_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare("UPDATE telegram_alarm_events SET status = 'open', acked_by = NULL, acked_at = NULL, resolved_at = NULL WHERE id = ?");
        $stmt->bind_param('i', $id);
    }

    if (!$stmt || !$stmt->execute()) {
        jsonResponse(['success' => false, 'message' => 'Gagal update alarm']);
    }

    jsonResponse(['success' => true, 'message' => 'Alarm berhasil diperbarui']);
}

$eventId = (int) ($_GET['id'] ?? 0);
if ($eventId > 0) {
    $stmt = $conn->prepare("
        SELECT id, event_type, severity, scope, olt_name, device_id, serial_number, title, message, payload, status,
               acked_by, acked_at, resolved_at, created_at, updated_at
        FROM telegram_alarm_events
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    if (!$event) {
        jsonResponse(['success' => false, 'message' => 'Alarm tidak ditemukan']);
    }

    $timelineWhere = [];
    $timelineParams = [];
    $timelineTypes = '';
    if (!empty($event['device_id'])) {
        $timelineWhere[] = 'device_id = ?';
        $timelineParams[] = $event['device_id'];
        $timelineTypes .= 's';
    }
    if (!empty($event['serial_number'])) {
        $timelineWhere[] = 'serial_number = ?';
        $timelineParams[] = $event['serial_number'];
        $timelineTypes .= 's';
    }
    if (!$timelineWhere && !empty($event['olt_name'])) {
        $timelineWhere[] = 'olt_name = ?';
        $timelineParams[] = $event['olt_name'];
        $timelineTypes .= 's';
    }

    $timeline = [];
    if ($timelineWhere) {
        $timelineSql = "
            SELECT id, event_type, severity, title, message, status, created_at
            FROM telegram_alarm_events
            WHERE " . implode(' OR ', $timelineWhere) . "
            ORDER BY created_at DESC, id DESC
            LIMIT 20
        ";
        $timelineStmt = $conn->prepare($timelineSql);
        $timelineStmt->bind_param($timelineTypes, ...$timelineParams);
        $timelineStmt->execute();
        $timelineResult = $timelineStmt->get_result();
        while ($row = $timelineResult->fetch_assoc()) {
            $timeline[] = $row;
        }
    }

    jsonResponse(['success' => true, 'event' => $event, 'timeline' => $timeline]);
}

$status = trim((string) ($_GET['status'] ?? 'open'));
$severity = trim((string) ($_GET['severity'] ?? ''));
$query = trim((string) ($_GET['q'] ?? ''));
$limit = min(200, max(20, (int) ($_GET['limit'] ?? 80)));

$where = [];
$params = [];
$types = '';

if ($status !== '' && $status !== 'all') {
    $where[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($severity !== '' && $severity !== 'all') {
    $where[] = 'severity = ?';
    $params[] = $severity;
    $types .= 's';
}

if ($query !== '') {
    $where[] = '(olt_name LIKE ? OR device_id LIKE ? OR serial_number LIKE ? OR title LIKE ? OR message LIKE ?)';
    $like = '%' . $query . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "
    SELECT id, event_type, severity, scope, olt_name, device_id, serial_number, title, message, status,
           acked_by, acked_at, resolved_at, created_at, updated_at
    FROM telegram_alarm_events
    {$whereSql}
    ORDER BY created_at DESC, id DESC
    LIMIT ?
";
$params[] = $limit;
$types .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    jsonResponse(['success' => false, 'message' => 'Gagal menyiapkan query alarm']);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$summaryResult = $conn->query("
    SELECT
        SUM(status = 'open') AS open_total,
        SUM(severity = 'critical' AND status = 'open') AS critical_open,
        SUM(severity = 'warning' AND status = 'open') AS warning_open,
        COUNT(*) AS total
    FROM telegram_alarm_events
");
$summary = $summaryResult ? $summaryResult->fetch_assoc() : ['open_total' => 0, 'critical_open' => 0, 'warning_open' => 0, 'total' => 0];

jsonResponse([
    'success' => true,
    'items' => $items,
    'summary' => [
        'open_total' => (int) ($summary['open_total'] ?? 0),
        'critical_open' => (int) ($summary['critical_open'] ?? 0),
        'warning_open' => (int) ($summary['warning_open'] ?? 0),
        'total' => (int) ($summary['total'] ?? 0),
    ],
]);
