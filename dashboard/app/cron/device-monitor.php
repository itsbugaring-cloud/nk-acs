#!/usr/bin/env php
<?php
/**
 * Device Monitor - Cron Job
 *
 * Monitor device status and send Telegram notifications
 *
 * SETUP CRON JOB:
 * Run this script every 5 minutes via crontab.
 *
 * To edit crontab:
 *   crontab -e
 *
 * Add this line (replace /path/to/project with your actual project path):
 *   every 5 minutes: /usr/bin/php /path/to/project/cron/device-monitor.php >> /var/log/gacs-monitor.log 2>&1
 *
 * Example:
 *   every 5 minutes: /usr/bin/php /var/www/html/gacs-dashboard/cron/device-monitor.php >> /var/log/gacs-monitor.log 2>&1
 *
 * To find your project path, run this command from project root:
 *   pwd
 *
 * Alternative intervals:
 *   every 1 minute   - not recommended, too frequent
 *   every 5 minutes  - recommended
 *   every 10 minutes
 *   every 15 minutes
 *   0 * * * *    - Every hour
 */

require_once __DIR__ . '/../config/config.php';

use App\GenieACS;
use App\TelegramBot;

echo "[" . date('Y-m-d H:i:s') . "] Device Monitor Started\n";

// Get GenieACS credentials
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$genieacsConfig = $result->fetch_assoc();

if (!$genieacsConfig) {
    echo "GenieACS not configured. Exiting.\n";
    exit;
}

// Get Telegram config
$telegramResult = $conn->query("SELECT * FROM telegram_config WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$telegramConfig = $telegramResult->fetch_assoc();

$telegram = null;
if ($telegramConfig) {
    $telegram = new TelegramBot($telegramConfig['bot_token'], $telegramConfig['chat_id']);
}

$offlineConfirmChecks = max(1, (int) (getenv('TELEGRAM_OFFLINE_CONFIRM_CHECKS') ?: 2));
$onlineConfirmChecks = max(1, (int) (getenv('TELEGRAM_ONLINE_CONFIRM_CHECKS') ?: 1));
$flapThreshold = max(2, (int) (getenv('TELEGRAM_FLAP_THRESHOLD') ?: 3));
$massDropThreshold = max(2, (int) (getenv('TELEGRAM_MASSDROP_THRESHOLD') ?: 5));
$oltDropPercent = max(5, (int) (getenv('TELEGRAM_OLT_DROP_PERCENT') ?: 30));
$oltDropWindowSec = max(60, (int) (getenv('TELEGRAM_OLT_DROP_WINDOW_SEC') ?: 600));
$rxTrendDropDb = max(1, (float) (getenv('TELEGRAM_RX_TREND_DROP_DB') ?: 6));
$rxTrendWindowSec = max(300, (int) (getenv('TELEGRAM_RX_TREND_WINDOW_SEC') ?: 3600));
$oltSyncStaleSec = max(600, (int) (getenv('TELEGRAM_OLT_SYNC_STALE_SEC') ?: 3600));
$miniDigestIntervalSec = max(300, (int) (getenv('TELEGRAM_MINI_DIGEST_INTERVAL_SEC') ?: 1800));

ensureTelegramAlarmEventSchema($conn);

// Get devices from GenieACS
$genieacs = new GenieACS(
    $genieacsConfig['host'],
    $genieacsConfig['port'],
    $genieacsConfig['username'],
    $genieacsConfig['password']
);

$devicesResult = $genieacs->getDevices();

if (!$devicesResult['success']) {
    echo "Failed to fetch devices from GenieACS\n";
    exit;
}

$devices = $devicesResult['data'];
echo "Found " . count($devices) . " devices\n";

$monitorStartKey = 'telegram_device_monitor_started_at';
$suppressInitialStatusAlerts = getMonitorConfig($conn, $monitorStartKey) === null;
if ($suppressInitialStatusAlerts) {
    setMonitorConfig($conn, $monitorStartKey, date('Y-m-d H:i:s'));
    echo "First monitor run detected; status alerts will be initialized silently.\n";
}

$confirmedStatusEvents = [];
$deviceStats = ['total' => 0, 'online' => 0, 'offline' => 0];
$oltHealth = [];
$acsSerials = [];

$firstInformStartKey = 'telegram_first_inform_watch_started_at';
$suppressInitialFirstInformAlerts = getMonitorConfig($conn, $firstInformStartKey) === null;
if ($suppressInitialFirstInformAlerts) {
    setMonitorConfig($conn, $firstInformStartKey, date('Y-m-d H:i:s'));
    echo "First inform watch initialized silently for existing ACS devices.\n";
}

foreach ($devices as $device) {
    $parsed = $genieacs->parseDeviceData($device);
    $deviceId = $parsed['device_id'];
    $currentStatus = strtolower((string) $parsed['status']) === 'online' ? 'online' : 'offline';
    $inventory = getAlertInventorySnapshot($conn, $parsed);
    $serial = strtoupper(trim((string) ($parsed['serial_number'] ?? '')));

    $deviceStats['total']++;
    $deviceStats[$currentStatus]++;
    if ($serial !== '' && $serial !== 'N/A') {
        $acsSerials[$serial] = true;
    }
    collectOltHealth($oltHealth, $inventory, $currentStatus);

    handleFirstInformAlert($conn, $telegram, $deviceId, $parsed, $inventory, $suppressInitialFirstInformAlerts);

    // Get last known status
    $stmt = $conn->prepare("SELECT status, notified FROM device_monitoring WHERE device_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $lastRecord = $stmt->get_result()->fetch_assoc();

    $lastStatus = $lastRecord ? $lastRecord['status'] : null;
    // Record raw status changes for history, then let smart-alert guard decide when to notify.
    if ($lastStatus !== $currentStatus) {
        echo "Device {$deviceId}: {$lastStatus} -> {$currentStatus}\n";

        // Insert new record
        $stmt = $conn->prepare("INSERT INTO device_monitoring (device_id, status, notified) VALUES (?, ?, 0)");
        $stmt->bind_param("ss", $deviceId, $currentStatus);
        $stmt->execute();

        handleFlappingAlert($conn, $telegram, $deviceId, $parsed, $flapThreshold);
    }

    $statusEvent = handleStatusAlert(
        $conn,
        $telegram,
        $deviceId,
        $currentStatus,
        $lastStatus,
        $parsed,
        $suppressInitialStatusAlerts,
        $offlineConfirmChecks,
        $onlineConfirmChecks
    );
    if ($statusEvent !== null) {
        $confirmedStatusEvents[] = $statusEvent;
    }

    if ($telegram) {
        handleOpticalAlert($conn, $telegram, $deviceId, $parsed);
        handleOpticalTrendAlert($conn, $telegram, $deviceId, $parsed, $inventory, $rxTrendDropDb, $rxTrendWindowSec);
    }
}

handleMassDropAlerts($conn, $telegram, $confirmedStatusEvents, $massDropThreshold);
handleOltHealthAlerts($conn, $telegram, $oltHealth, $oltDropPercent, $oltDropWindowSec);
handleOltSyncFreshnessAlerts($conn, $telegram, $oltSyncStaleSec);
handleTaskFailureAlerts($conn, $telegram, $genieacs);
handleMiniDigest($conn, $telegram, $deviceStats, $oltHealth, $acsSerials, $miniDigestIntervalSec);

// Cleanup old records (keep last 30 days)
$conn->query("DELETE FROM device_monitoring WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

echo "[" . date('Y-m-d H:i:s') . "] Device Monitor Finished\n";

function handleStatusAlert(
    mysqli $conn,
    ?TelegramBot $telegram,
    string $deviceId,
    string $currentStatus,
    ?string $lastStatus,
    array $parsed,
    bool $suppressInitialStatusAlerts,
    int $offlineConfirmChecks,
    int $onlineConfirmChecks
): ?array {
    if (!$telegram) {
        return null;
    }

    $key = 'telegram_status_alert_' . sha1($deviceId);
    $state = getJsonMonitorConfig($conn, $key);
    $alertedStatus = $state['alerted_status'] ?? null;

    if (!in_array($alertedStatus, ['online', 'offline'], true)) {
        $alertedStatus = in_array($lastStatus, ['online', 'offline'], true) ? $lastStatus : $currentStatus;
    }

    if ($suppressInitialStatusAlerts) {
        setJsonMonitorConfig($conn, $key, buildStatusStatePayload($conn, $deviceId, $currentStatus, $parsed, [
            'alerted_status' => $currentStatus,
            'pending_status' => null,
            'pending_count' => 0,
            'updated_at' => date('c'),
            'note' => 'initialized_silently',
        ]));
        return null;
    }

    if ($currentStatus === $alertedStatus) {
        setJsonMonitorConfig($conn, $key, buildStatusStatePayload($conn, $deviceId, $currentStatus, $parsed, [
            'alerted_status' => $alertedStatus,
            'pending_status' => null,
            'pending_count' => 0,
            'last_notified_at' => $state['last_notified_at'] ?? null,
            'updated_at' => date('c'),
        ]));
        return null;
    }

    $pendingStatus = $state['pending_status'] ?? null;
    $pendingCount = $pendingStatus === $currentStatus ? (int) ($state['pending_count'] ?? 0) + 1 : 1;
    $threshold = $currentStatus === 'offline' ? $offlineConfirmChecks : $onlineConfirmChecks;

    echo "Smart status pending {$deviceId}: {$alertedStatus} -> {$currentStatus} ({$pendingCount}/{$threshold})\n";

    if ($pendingCount < $threshold) {
        setJsonMonitorConfig($conn, $key, buildStatusStatePayload($conn, $deviceId, $currentStatus, $parsed, [
            'alerted_status' => $alertedStatus,
            'pending_status' => $currentStatus,
            'pending_count' => $pendingCount,
            'updated_at' => date('c'),
        ]));
        return null;
    }

    $deviceInfo = buildStatusAlertDeviceInfo($conn, $deviceId, $parsed);
    if (isTelegramAreaMuted($conn, $deviceInfo['olt_name'] ?? null)) {
        setJsonMonitorConfig($conn, $key, buildStatusStatePayload($conn, $deviceId, $currentStatus, $parsed, [
            'alerted_status' => $currentStatus,
            'pending_status' => null,
            'pending_count' => 0,
            'last_muted_at' => date('c'),
            'updated_at' => date('c'),
        ]));
        echo "Smart notification muted for {$deviceId}\n";
        return null;
    }

    $telegram->sendDeviceStatus($deviceId, $currentStatus, $deviceInfo);
    logTelegramAlarmEvent(
        $conn,
        'status_' . $currentStatus,
        $currentStatus === 'offline' ? 'critical' : 'info',
        'device',
        $deviceInfo['olt_name'] ?? null,
        $deviceId,
        $deviceInfo['serial_number'] ?? null,
        'Status ONT ' . strtoupper($currentStatus),
        'Status confirmed by smart monitor.',
        $deviceInfo
    );

    $stmt = $conn->prepare("UPDATE device_monitoring SET notified = 1 WHERE device_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("ss", $deviceId, $currentStatus);
    $stmt->execute();

    setJsonMonitorConfig($conn, $key, buildStatusStatePayload($conn, $deviceId, $currentStatus, $parsed, [
        'alerted_status' => $currentStatus,
        'pending_status' => null,
        'pending_count' => 0,
        'last_notified_at' => date('c'),
        'updated_at' => date('c'),
    ]));

    echo "Smart notification sent for {$deviceId}\n";
    return [
        'device_id' => $deviceId,
        'status' => $currentStatus,
        'device_info' => $deviceInfo,
    ];
}

function buildStatusStatePayload(mysqli $conn, string $deviceId, string $currentStatus, array $parsed, array $extra = []): array
{
    $deviceInfo = buildStatusAlertDeviceInfo($conn, $deviceId, $parsed);
    return array_merge($extra, [
        'device_id' => $deviceId,
        'current_status' => $currentStatus,
        'serial_number' => $deviceInfo['serial_number'] ?? ($parsed['serial_number'] ?? null),
        'ip_tr069' => $deviceInfo['ip_tr069'] ?? ($parsed['ip_tr069'] ?? null),
        'olt_name' => $deviceInfo['olt_name'] ?? null,
        'ont_name' => $deviceInfo['ont_name'] ?? null,
        'customer_name' => $deviceInfo['customer_name'] ?? null,
        'pon_port' => $deviceInfo['pon_port'] ?? null,
        'ont_index' => $deviceInfo['ont_index'] ?? null,
        'last_inform' => $parsed['last_inform'] ?? null,
    ]);
}

function handleFlappingAlert(mysqli $conn, ?TelegramBot $telegram, string $deviceId, array $parsed, int $threshold): void
{
    if (!$telegram) {
        return;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM device_monitoring WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $changeCount = (int) ($row['total'] ?? 0);
    if ($changeCount < $threshold) {
        return;
    }

    $key = 'telegram_flap_alert_' . sha1($deviceId);
    $state = getJsonMonitorConfig($conn, $key);
    if (!empty($state['last_alert_at']) && strtotime((string) $state['last_alert_at']) > time() - 3600) {
        return;
    }

    $info = buildStatusAlertDeviceInfo($conn, $deviceId, $parsed);
    if (isTelegramAreaMuted($conn, $info['olt_name'] ?? null)) {
        setJsonMonitorConfig($conn, $key, [
            'device_id' => $deviceId,
            'serial_number' => $info['serial_number'] ?? null,
            'olt_name' => $info['olt_name'] ?? null,
            'customer_name' => $info['customer_name'] ?? ($info['ont_name'] ?? null),
            'change_count_1h' => $changeCount,
            'last_muted_at' => date('c'),
            'updated_at' => date('c'),
        ]);
        return;
    }

    $message = "🟡 <b>Alert Flapping ONT</b>\n\n";
    $message .= "Device: <code>" . htmlAlertValue($deviceId) . "</code>\n";
    $message .= "SN: <code>" . htmlAlertValue($info['serial_number'] ?? 'N/A') . "</code>\n";
    $message .= "OLT: <b>" . htmlAlertValue($info['olt_name'] ?? 'N/A') . "</b>\n";
    $message .= "PON/ONT: <code>" . htmlAlertValue($info['pon_port'] ?? 'N/A') . " / " . htmlAlertValue($info['ont_index'] ?? 'N/A') . "</code>\n";
    $message .= "Nama ONT: <b>" . htmlAlertValue($info['customer_name'] ?? ($info['ont_name'] ?? 'N/A')) . "</b>\n";
    $message .= "Perubahan status 1 jam: <b>{$changeCount}</b>x\n\n";
    $message .= "Notifikasi ini hanya baca data, tidak mengubah ONT.";

    $telegram->sendMessage($message);
    logTelegramAlarmEvent(
        $conn,
        'flapping',
        'warning',
        'device',
        $info['olt_name'] ?? null,
        $deviceId,
        $info['serial_number'] ?? null,
        'ONT flapping',
        'Perubahan status 1 jam: ' . $changeCount . 'x',
        array_merge($info, ['change_count_1h' => $changeCount])
    );
    setJsonMonitorConfig($conn, $key, [
        'device_id' => $deviceId,
        'serial_number' => $info['serial_number'] ?? null,
        'olt_name' => $info['olt_name'] ?? null,
        'customer_name' => $info['customer_name'] ?? ($info['ont_name'] ?? null),
        'pon_port' => $info['pon_port'] ?? null,
        'ont_index' => $info['ont_index'] ?? null,
        'change_count_1h' => $changeCount,
        'last_alert_at' => date('c'),
        'updated_at' => date('c'),
    ]);
}

function handleMassDropAlerts(mysqli $conn, ?TelegramBot $telegram, array $confirmedStatusEvents, int $threshold): void
{
    if (!$telegram) {
        return;
    }

    $groups = [];
    foreach ($confirmedStatusEvents as $event) {
        if (($event['status'] ?? '') !== 'offline') {
            continue;
        }
        $info = $event['device_info'] ?? [];
        $oltName = trim((string) ($info['olt_name'] ?? 'N/A'));
        if ($oltName === '') {
            $oltName = 'N/A';
        }
        if (!isset($groups[$oltName])) {
            $groups[$oltName] = [];
        }
        $groups[$oltName][] = [
            'device_id' => $event['device_id'] ?? 'N/A',
            'serial_number' => $info['serial_number'] ?? 'N/A',
            'customer_name' => $info['customer_name'] ?? ($info['ont_name'] ?? 'N/A'),
        ];
    }

    foreach ($groups as $oltName => $items) {
        $count = count($items);
        if ($count < $threshold) {
            continue;
        }

        $key = 'telegram_massdrop_alert_' . sha1($oltName);
        $state = getJsonMonitorConfig($conn, $key);
        if (!empty($state['last_alert_at']) && strtotime((string) $state['last_alert_at']) > time() - 1800) {
            continue;
        }
        if (isTelegramAreaMuted($conn, $oltName)) {
            setJsonMonitorConfig($conn, $key, [
                'olt_name' => $oltName,
                'offline_confirmed_count' => $count,
                'last_muted_at' => date('c'),
                'updated_at' => date('c'),
            ]);
            continue;
        }

        $message = "🚨 <b>Mass-Drop OLT</b>\n\n";
        $message .= "OLT: <b>" . htmlAlertValue($oltName) . "</b>\n";
        $message .= "Offline confirmed run ini: <b>{$count}</b> ONT\n";
        $message .= "Ambang alarm: {$threshold} ONT\n\n";
        foreach (array_slice($items, 0, 10) as $item) {
            $message .= "- " . htmlAlertValue($item['customer_name']) . " | <code>" . htmlAlertValue($item['serial_number']) . "</code>\n";
        }
        $message .= "\nNotifikasi ini hanya baca data, tidak mengubah ONT.";

        $telegram->sendMessage($message);
        logTelegramAlarmEvent(
            $conn,
            'massdrop',
            'critical',
            'olt',
            $oltName,
            null,
            null,
            'Mass-drop OLT',
            'Offline confirmed run ini: ' . $count . ' ONT',
            ['olt_name' => $oltName, 'offline_confirmed_count' => $count, 'sample' => array_slice($items, 0, 10)]
        );
        setJsonMonitorConfig($conn, $key, [
            'olt_name' => $oltName,
            'offline_confirmed_count' => $count,
            'last_alert_at' => date('c'),
            'updated_at' => date('c'),
        ]);
    }
}

function buildStatusAlertDeviceInfo(mysqli $conn, string $deviceId, array $parsed): array
{
    $inventory = getAlertInventorySnapshot($conn, $parsed);
    $deviceInfo = [
        'serial_number' => $parsed['serial_number'],
        'ip_tr069' => $parsed['ip_tr069'],
        'olt_name' => $inventory['olt_name'] ?? null,
        'ont_name' => $inventory['ont_name'] ?? null,
        'pon_port' => $inventory['pon_port'] ?? null,
        'ont_index' => $inventory['ont_index'] ?? null,
    ];

    $stmt = $conn->prepare("SELECT customer_name FROM onu_config WHERE genieacs_device_id = ?");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $onuConfig = $stmt->get_result()->fetch_assoc();

    if ($onuConfig) {
        $deviceInfo['customer_name'] = $onuConfig['customer_name'];
    } elseif (!empty($inventory['ont_name'])) {
        $deviceInfo['customer_name'] = $inventory['ont_name'];
    }

    return $deviceInfo;
}

function handleOpticalAlert(mysqli $conn, TelegramBot $telegram, string $deviceId, array $parsed): void
{
    $rx = $parsed['rx_power'] ?? null;
    if ($rx === null || $rx === '' || strtoupper((string) $rx) === 'N/A') {
        return;
    }

    $rxValue = (float) $rx;
    $level = 'normal';
    if ($rxValue <= -28) {
        $level = 'critical';
    } elseif ($rxValue <= -25) {
        $level = 'warning';
    }

    $key = 'telegram_rx_alert_' . sha1($deviceId);
    $previous = getMonitorConfig($conn, $key);
    setMonitorConfig($conn, $key, $level);

    // Avoid alert storms on first monitor run; alert only on future state changes.
    if ($previous === null || $previous === $level || $level === 'normal') {
        return;
    }

    $label = $level === 'critical' ? 'KRITIS' : 'WARNING';
    $icon = $level === 'critical' ? '🔴' : '🟠';
    $serial = $parsed['serial_number'] ?? 'N/A';
    $inventory = getAlertInventorySnapshot($conn, $parsed);
    if (isTelegramAreaMuted($conn, $inventory['olt_name'] ?? null)) {
        return;
    }

    $customer = htmlAlertValue($inventory['ont_name'] ?? ($parsed['customer_name'] ?? 'N/A'));
    $oltName = htmlAlertValue($inventory['olt_name'] ?? 'N/A');
    $ponPort = htmlAlertValue($inventory['pon_port'] ?? 'N/A');
    $ontIndex = htmlAlertValue($inventory['ont_index'] ?? 'N/A');
    $safeDeviceId = htmlAlertValue($deviceId);
    $safeSerial = htmlAlertValue($serial);

    $message = "{$icon} <b>Alert Redaman {$label}</b>\n\n";
    $message .= "OLT: <b>{$oltName}</b>\n";
    $message .= "PON/ONT: <code>{$ponPort} / {$ontIndex}</code>\n";
    $message .= "Nama ONT: <b>{$customer}</b>\n";
    $message .= "Device: <code>{$safeDeviceId}</code>\n";
    $message .= "SN: <code>{$safeSerial}</code>\n";
    $message .= "RX: <b>{$rxValue} dBm</b>\n";
    $message .= "Ambang: warning <= -25 dBm, kritis <= -28 dBm\n\n";
    $message .= "Notifikasi ini hanya baca data, tidak mengubah ONT.";

    $telegram->sendMessage($message);
    logTelegramAlarmEvent(
        $conn,
        'optical_' . $level,
        $level,
        'device',
        $inventory['olt_name'] ?? null,
        $deviceId,
        (string) $serial,
        'Redaman ' . $label,
        'RX: ' . $rxValue . ' dBm',
        array_merge($inventory, ['rx_power' => $rxValue, 'level' => $level])
    );
}

function collectOltHealth(array &$oltHealth, array $inventory, string $currentStatus): void
{
    $oltName = trim((string) ($inventory['olt_name'] ?? ''));
    if ($oltName === '') {
        return;
    }

    if (!isset($oltHealth[$oltName])) {
        $oltHealth[$oltName] = ['olt_name' => $oltName, 'total' => 0, 'online' => 0, 'offline' => 0];
    }

    $oltHealth[$oltName]['total']++;
    $oltHealth[$oltName][$currentStatus]++;
}

function handleFirstInformAlert(
    mysqli $conn,
    ?TelegramBot $telegram,
    string $deviceId,
    array $parsed,
    array $inventory,
    bool $suppressInitialFirstInformAlerts
): void {
    $serial = strtoupper(trim((string) ($parsed['serial_number'] ?? '')));
    if ($serial === '' || $serial === 'N/A') {
        return;
    }

    $key = 'telegram_first_inform_' . sha1($serial);
    $state = getJsonMonitorConfig($conn, $key);
    if (!empty($state)) {
        return;
    }

    $payload = [
        'device_id' => $deviceId,
        'serial_number' => $serial,
        'ip_tr069' => $parsed['ip_tr069'] ?? null,
        'olt_name' => $inventory['olt_name'] ?? null,
        'ont_name' => $inventory['ont_name'] ?? null,
        'pon_port' => $inventory['pon_port'] ?? null,
        'ont_index' => $inventory['ont_index'] ?? null,
        'first_seen_at' => date('c'),
    ];
    setJsonMonitorConfig($conn, $key, $payload);

    if (!$telegram || $suppressInitialFirstInformAlerts || isTelegramAreaMuted($conn, $inventory['olt_name'] ?? null)) {
        return;
    }

    $message = "🟢 <b>ONT Baru Masuk ACS</b>\n\n";
    $message .= "SN: <code>" . htmlAlertValue($serial) . "</code>\n";
    $message .= "OLT: <b>" . htmlAlertValue($inventory['olt_name'] ?? 'N/A') . "</b>\n";
    $message .= "PON/ONT: <code>" . htmlAlertValue($inventory['pon_port'] ?? 'N/A') . " / " . htmlAlertValue($inventory['ont_index'] ?? 'N/A') . "</code>\n";
    $message .= "Nama ONT: <b>" . htmlAlertValue($inventory['ont_name'] ?? 'N/A') . "</b>\n";
    $message .= "IP TR069: <code>" . htmlAlertValue($parsed['ip_tr069'] ?? 'N/A') . "</code>\n\n";
    $message .= "Notifikasi ini hanya baca data, tidak mengubah ONT.";

    $telegram->sendMessage($message);
    logTelegramAlarmEvent($conn, 'first_inform', 'info', 'device', $inventory['olt_name'] ?? null, $deviceId, $serial, 'ONT baru masuk ACS', 'First inform detected by monitor.', $payload);
}

function handleOpticalTrendAlert(
    mysqli $conn,
    TelegramBot $telegram,
    string $deviceId,
    array $parsed,
    array $inventory,
    float $dropDb,
    int $windowSec
): void {
    $rx = parseAlertNumeric($parsed['rx_power'] ?? null);
    if ($rx === null) {
        return;
    }

    $key = 'telegram_rx_trend_' . sha1($deviceId);
    $state = getJsonMonitorConfig($conn, $key);
    $history = is_array($state['history'] ?? null) ? $state['history'] : [];
    $now = time();
    $history[] = ['ts' => $now, 'rx' => $rx];
    $history = array_values(array_filter($history, static fn($row) => (int) ($row['ts'] ?? 0) >= $now - $windowSec));
    if (count($history) > 20) {
        $history = array_slice($history, -20);
    }

    $baseline = null;
    foreach ($history as $row) {
        $value = parseAlertNumeric($row['rx'] ?? null);
        if ($value === null) {
            continue;
        }
        $baseline = $baseline === null ? $value : max($baseline, $value);
    }

    $state['history'] = $history;
    $state['device_id'] = $deviceId;
    $state['serial_number'] = $parsed['serial_number'] ?? null;
    $state['olt_name'] = $inventory['olt_name'] ?? null;
    $state['customer_name'] = $inventory['ont_name'] ?? null;
    $state['updated_at'] = date('c');

    if ($baseline !== null && ($baseline - $rx) >= $dropDb) {
        $lastAlertAt = strtotime((string) ($state['last_alert_at'] ?? ''));
        if ($lastAlertAt <= time() - 3600 && !isTelegramAreaMuted($conn, $inventory['olt_name'] ?? null)) {
            $message = "📉 <b>Tren Redaman Memburuk</b>\n\n";
            $message .= "OLT: <b>" . htmlAlertValue($inventory['olt_name'] ?? 'N/A') . "</b>\n";
            $message .= "Nama ONT: <b>" . htmlAlertValue($inventory['ont_name'] ?? 'N/A') . "</b>\n";
            $message .= "SN: <code>" . htmlAlertValue($parsed['serial_number'] ?? 'N/A') . "</code>\n";
            $message .= "RX awal terbaik: <b>" . htmlAlertValue($baseline) . " dBm</b>\n";
            $message .= "RX sekarang: <b>" . htmlAlertValue($rx) . " dBm</b>\n";
            $message .= "Drop: <b>" . htmlAlertValue(round($baseline - $rx, 2)) . " dB</b> dalam " . htmlAlertValue((int) round($windowSec / 60)) . " menit\n\n";
            $message .= "Alarm ini hanya baca data.";
            $telegram->sendMessage($message);
            logTelegramAlarmEvent($conn, 'rx_trend_drop', 'warning', 'device', $inventory['olt_name'] ?? null, $deviceId, $parsed['serial_number'] ?? null, 'Tren redaman memburuk', 'RX drop ' . round($baseline - $rx, 2) . ' dB', $state);
            $state['last_alert_at'] = date('c');
        }
    }

    setJsonMonitorConfig($conn, $key, $state);
}

function handleOltHealthAlerts(mysqli $conn, ?TelegramBot $telegram, array $oltHealth, int $dropPercent, int $windowSec): void
{
    if (!$telegram) {
        return;
    }

    foreach ($oltHealth as $oltName => $stats) {
        $total = max(1, (int) ($stats['total'] ?? 0));
        $online = (int) ($stats['online'] ?? 0);
        $rate = round(($online / $total) * 100, 2);
        $key = 'telegram_olt_health_' . sha1($oltName);
        $state = getJsonMonitorConfig($conn, $key);
        $previousRate = isset($state['online_rate']) ? (float) $state['online_rate'] : null;
        $previousAt = strtotime((string) ($state['updated_at'] ?? ''));
        $lastAlertAt = strtotime((string) ($state['last_alert_at'] ?? ''));

        if (
            $previousRate !== null
            && $previousAt >= time() - $windowSec
            && ($previousRate - $rate) >= $dropPercent
            && $lastAlertAt <= time() - 1800
            && !isTelegramAreaMuted($conn, $oltName)
        ) {
            $message = "🚨 <b>Live OLT Health Drop</b>\n\n";
            $message .= "OLT: <b>" . htmlAlertValue($oltName) . "</b>\n";
            $message .= "Online rate: <b>" . htmlAlertValue($previousRate) . "% → " . htmlAlertValue($rate) . "%</b>\n";
            $message .= "Sekarang: <b>{$online}/{$total}</b> ONT online\n";
            $message .= "Window: " . htmlAlertValue((int) round($windowSec / 60)) . " menit\n\n";
            $message .= "Alarm ini hanya baca data.";
            $telegram->sendMessage($message);
            logTelegramAlarmEvent($conn, 'olt_health_drop', 'critical', 'olt', $oltName, null, null, 'Live OLT health drop', "Online rate {$previousRate}% -> {$rate}%", $stats);
            $state['last_alert_at'] = date('c');
        }

        $state = array_merge($state, [
            'olt_name' => $oltName,
            'total' => $total,
            'online' => $online,
            'offline' => (int) ($stats['offline'] ?? 0),
            'online_rate' => $rate,
            'updated_at' => date('c'),
        ]);
        setJsonMonitorConfig($conn, $key, $state);
    }
}

function handleOltSyncFreshnessAlerts(mysqli $conn, ?TelegramBot $telegram, int $staleSec): void
{
    if (!$telegram) {
        return;
    }

    $sql = "
        SELECT mi.id, mi.name, mi.properties, MAX(inv.last_synced_at) AS last_synced_at
        FROM map_items mi
        LEFT JOIN olt_onu_inventory inv ON inv.olt_item_id = mi.id
        WHERE mi.item_type = 'olt'
        GROUP BY mi.id, mi.name, mi.properties
    ";
    $result = $conn->query($sql);
    if (!$result) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $oltName = trim((string) ($row['name'] ?? ''));
        if ($oltName === '') {
            continue;
        }

        $props = json_decode((string) ($row['properties'] ?? ''), true);
        if (is_array($props) && empty($row['last_synced_at']) && !empty($props['inventory_last_synced_at'])) {
            $row['last_synced_at'] = $props['inventory_last_synced_at'];
        }

        $lastSyncTs = strtotime((string) ($row['last_synced_at'] ?? ''));
        if ($lastSyncTs <= 0 || $lastSyncTs >= time() - $staleSec) {
            continue;
        }

        $key = 'telegram_olt_sync_stale_' . sha1($oltName);
        $state = getJsonMonitorConfig($conn, $key);
        $lastAlertAt = strtotime((string) ($state['last_alert_at'] ?? ''));
        if ($lastAlertAt > time() - 3600 || isTelegramAreaMuted($conn, $oltName)) {
            continue;
        }

        $ageMin = (int) floor((time() - $lastSyncTs) / 60);
        $message = "⏱️ <b>OLT Sync Stale</b>\n\n";
        $message .= "OLT: <b>" . htmlAlertValue($oltName) . "</b>\n";
        $message .= "Last sync: <code>" . htmlAlertValue($row['last_synced_at']) . "</code>\n";
        $message .= "Umur data: <b>{$ageMin} menit</b>\n\n";
        $message .= "Data OLT/ACS bisa terlihat tidak cocok kalau inventory belum sync. Alarm ini read-only.";
        $telegram->sendMessage($message);

        $payload = ['olt_name' => $oltName, 'last_synced_at' => $row['last_synced_at'], 'age_minutes' => $ageMin, 'last_alert_at' => date('c'), 'updated_at' => date('c')];
        logTelegramAlarmEvent($conn, 'olt_sync_stale', 'warning', 'olt', $oltName, null, null, 'OLT sync stale', 'Umur data ' . $ageMin . ' menit', $payload);
        setJsonMonitorConfig($conn, $key, $payload);
    }
}

function handleTaskFailureAlerts(mysqli $conn, ?TelegramBot $telegram, GenieACS $genieacs): void
{
    if (!$telegram) {
        return;
    }

    $startKey = 'telegram_task_failure_watch_started_at';
    $suppressInitial = getMonitorConfig($conn, $startKey) === null;
    if ($suppressInitial) {
        setMonitorConfig($conn, $startKey, date('Y-m-d H:i:s'));
    }

    $faults = $genieacs->getFaults([], 50, 0);
    if (empty($faults['success']) || !is_array($faults['data'] ?? null)) {
        return;
    }

    foreach ($faults['data'] as $fault) {
        if (!is_array($fault)) {
            continue;
        }
        $hash = sha1(json_encode([
            $fault['_id'] ?? null,
            $fault['device'] ?? $fault['deviceId'] ?? null,
            $fault['faultCode'] ?? $fault['code'] ?? null,
            $fault['message'] ?? $fault['faultString'] ?? null,
            $fault['timestamp'] ?? $fault['created'] ?? $fault['createdAt'] ?? null,
        ], JSON_UNESCAPED_SLASHES));
        $key = 'telegram_task_fault_' . $hash;
        if (!empty(getJsonMonitorConfig($conn, $key))) {
            continue;
        }

        $deviceId = (string) ($fault['device'] ?? $fault['deviceId'] ?? 'N/A');
        $label = telegramMonitorFaultLabel($fault);
        $isConnectionIssue = telegramMonitorFaultLooksConnectionRelated($label);
        $payload = [
            'device_id' => $deviceId,
            'fault' => $label,
            'connection_request_issue' => $isConnectionIssue,
            'created_at' => date('c'),
        ];
        setJsonMonitorConfig($conn, $key, $payload);

        if ($suppressInitial) {
            continue;
        }

        $message = ($isConnectionIssue ? "📡" : "🧾") . " <b>Task/Fault GenieACS</b>\n\n";
        $message .= "Device: <code>" . htmlAlertValue($deviceId) . "</code>\n";
        $message .= "Fault: <code>" . htmlAlertValue($label) . "</code>\n";
        if ($isConnectionIssue) {
            $message .= "Indikasi: <b>Connection request tidak reachable / timeout</b>\n";
        }
        $message .= "\nMonitor ini hanya baca fault queue, tidak memanggil ONT massal.";

        $telegram->sendMessage($message);
        logTelegramAlarmEvent($conn, $isConnectionIssue ? 'connection_request_unreachable' : 'task_failure', 'warning', 'device', null, $deviceId, null, 'Task/Fault GenieACS', $label, $payload);
    }
}

function handleMiniDigest(mysqli $conn, ?TelegramBot $telegram, array $deviceStats, array $oltHealth, array $acsSerials, int $intervalSec): void
{
    if (!$telegram) {
        return;
    }

    $key = 'telegram_mini_digest_last_sent_at';
    $lastSentAt = strtotime((string) getMonitorConfig($conn, $key));
    if ($lastSentAt > time() - $intervalSec) {
        return;
    }

    $offline = 0;
    foreach (getTelegramActiveMonitorStates($conn, 'telegram_status_alert_') as $state) {
        if (($state['alerted_status'] ?? null) === 'offline') {
            $offline++;
        }
    }

    $criticalRx = 0;
    $rxResult = $conn->query("SELECT COUNT(*) AS total FROM olt_onu_inventory WHERE rx_power <= -28");
    if ($rxResult) {
        $criticalRx = (int) (($rxResult->fetch_assoc()['total'] ?? 0));
    }

    $onlineMissingAcs = 0;
    $invResult = $conn->query("SELECT serial_number FROM olt_onu_inventory WHERE status = 'online'");
    if ($invResult) {
        while ($row = $invResult->fetch_assoc()) {
            $serial = strtoupper(trim((string) ($row['serial_number'] ?? '')));
            if ($serial !== '' && !isset($acsSerials[$serial])) {
                $onlineMissingAcs++;
            }
        }
    }

    $worstOlt = null;
    foreach ($oltHealth as $row) {
        $total = max(1, (int) ($row['total'] ?? 0));
        $rate = round(((int) ($row['online'] ?? 0) / $total) * 100, 1);
        if ($worstOlt === null || $rate < $worstOlt['rate']) {
            $worstOlt = ['name' => $row['olt_name'] ?? 'N/A', 'rate' => $rate];
        }
    }

    $message = "📊 <b>Mini Digest NETKING-ACS</b>\n\n";
    $message .= "ACS online: <b>" . (int) ($deviceStats['online'] ?? 0) . "</b>\n";
    $message .= "ACS offline: <b>" . (int) ($deviceStats['offline'] ?? 0) . "</b>\n";
    $message .= "Offline confirmed: <b>{$offline}</b>\n";
    $message .= "Redaman kritis: <b>{$criticalRx}</b>\n";
    $message .= "ONT online belum ACS: <b>{$onlineMissingAcs}</b>\n";
    if ($worstOlt) {
        $message .= "OLT rate terendah: <b>" . htmlAlertValue($worstOlt['name']) . "</b> (" . htmlAlertValue($worstOlt['rate']) . "%)\n";
    }
    $message .= "\nDigest ini read-only.";

    $telegram->sendMessage($message);
    setMonitorConfig($conn, $key, date('c'));
}

function getMonitorConfig(mysqli $conn, string $key): ?string
{
    $stmt = $conn->prepare("SELECT config_value FROM configurations WHERE config_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (string) $row['config_value'] : null;
}

function setMonitorConfig(mysqli $conn, string $key, string $value): void
{
    $stmt = $conn->prepare("
        INSERT INTO configurations (config_key, config_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
    ");
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
}

function getJsonMonitorConfig(mysqli $conn, string $key): array
{
    $value = getMonitorConfig($conn, $key);
    if ($value === null || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function setJsonMonitorConfig(mysqli $conn, string $key, array $value): void
{
    setMonitorConfig($conn, $key, json_encode($value, JSON_UNESCAPED_SLASHES));
}

function getTelegramActiveMonitorStates(mysqli $conn, string $prefix): array
{
    $like = $prefix . '%';
    $stmt = $conn->prepare("SELECT config_key, config_value, updated_at FROM configurations WHERE config_key LIKE ? ORDER BY updated_at DESC LIMIT 1000");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $decoded = json_decode((string) ($row['config_value'] ?? ''), true);
        if (!is_array($decoded)) {
            continue;
        }
        $decoded['config_key'] = $row['config_key'];
        $decoded['config_updated_at'] = $row['updated_at'];
        $items[] = $decoded;
    }

    return $items;
}

function isTelegramAreaMuted(mysqli $conn, ?string $oltName): bool
{
    $target = normalizeTelegramArea((string) $oltName);
    if ($target === '') {
        return false;
    }

    foreach (getTelegramActiveMonitorStates($conn, 'telegram_area_mute_') as $state) {
        $until = strtotime((string) ($state['muted_until'] ?? ''));
        if ($until <= time()) {
            continue;
        }

        $area = normalizeTelegramArea((string) ($state['area'] ?? ''));
        if ($area !== '' && ($area === $target || str_contains($target, $area) || str_contains($area, $target))) {
            return true;
        }
    }

    return false;
}

function normalizeTelegramArea(string $area): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $area) ?? $area));
}

function parseAlertNumeric($value): ?float
{
    if ($value === null || $value === '' || strtoupper((string) $value) === 'N/A') {
        return null;
    }
    if (is_numeric($value)) {
        return (float) $value;
    }
    if (preg_match('/-?\d+(?:\.\d+)?/', (string) $value, $matches)) {
        return (float) $matches[0];
    }
    return null;
}

function ensureTelegramAlarmEventSchema(mysqli $conn): void
{
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
}

function logTelegramAlarmEvent(
    mysqli $conn,
    string $eventType,
    string $severity,
    string $scope,
    ?string $oltName,
    ?string $deviceId,
    ?string $serialNumber,
    string $title,
    string $message,
    array $payload = []
): void {
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare("
        INSERT INTO telegram_alarm_events
            (event_type, severity, scope, olt_name, device_id, serial_number, title, message, payload)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sssssssss', $eventType, $severity, $scope, $oltName, $deviceId, $serialNumber, $title, $message, $payloadJson);
    $stmt->execute();
}

function telegramMonitorFaultLabel(array $fault): string
{
    $code = $fault['faultCode'] ?? $fault['code'] ?? $fault['name'] ?? 'fault';
    $message = $fault['message'] ?? $fault['faultString'] ?? $fault['detail'] ?? $fault['error'] ?? '';
    $device = $fault['device'] ?? $fault['deviceId'] ?? '-';
    $time = $fault['timestamp'] ?? $fault['created'] ?? $fault['createdAt'] ?? '';
    return trim((string) $code . ' | ' . (string) $device . ($message ? ' | ' . (string) $message : '') . ($time ? ' | ' . (string) $time : ''));
}

function telegramMonitorFaultLooksConnectionRelated(string $text): bool
{
    $lower = strtolower($text);
    foreach (['connection request', 'timeout', 'timed out', 'unreachable', 'no contact', 'connect ehostunreach', 'connect etimedout'] as $needle) {
        if (str_contains($lower, $needle)) {
            return true;
        }
    }
    return false;
}

function getAlertInventorySnapshot(mysqli $conn, array $parsed): array
{
    $serial = strtoupper(trim((string) ($parsed['serial_number'] ?? '')));
    if ($serial === '' || $serial === 'N/A') {
        return [];
    }

    $sql = "
        SELECT
            olt.name AS olt_name,
            inv.description AS ont_name,
            inv.pon_port,
            inv.ont_index
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
        WHERE inv.serial_number = ?
        ORDER BY inv.updated_at DESC, inv.id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: [];
}

function htmlAlertValue($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
