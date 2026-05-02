<?php
/**
 * Health Check Endpoint
 * Returns system status for monitoring tools (Uptime Robot, etc.)
 *
 * Usage: curl http://your-domain.com/api/health-check.php
 */

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: Database connectivity
try {
    require_once __DIR__ . '/../config/config.php';
    $db = getDBConnection();

    $result = $db->query("SELECT 1");
    if ($result) {
        $health['checks']['database'] = [
            'status' => 'ok',
            'message' => 'Database connection successful'
        ];
    } else {
        throw new Exception('Query failed');
    }
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}

// Check 2: GenieACS connectivity
try {
    $stmt = $db->prepare("SELECT host, port FROM genieacs_credentials LIMIT 1");
    $stmt->execute();
    $genieacs = $stmt->get_result()->fetch_assoc();

    if ($genieacs) {
        $url = "http://{$genieacs['host']}:{$genieacs['port']}/devices/?limit=1";
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            $health['checks']['genieacs'] = [
                'status' => 'ok',
                'message' => 'GenieACS API reachable'
            ];
        } else {
            throw new Exception('Connection timeout or refused');
        }
    } else {
        $health['checks']['genieacs'] = [
            'status' => 'warning',
            'message' => 'GenieACS not configured'
        ];
    }
} catch (Exception $e) {
    $health['checks']['genieacs'] = [
        'status' => 'error',
        'message' => 'GenieACS unreachable: ' . $e->getMessage()
    ];
}

// Check 3: Telegram transport status
try {
    $stmt = $db->prepare("SELECT bot_token FROM telegram_config LIMIT 1");
    $stmt->execute();
    $telegram = $stmt->get_result()->fetch_assoc();
    $transport = strtolower((string) envValue('TELEGRAM_BOT_TRANSPORT', 'webhook'));

    if ($telegram && !empty($telegram['bot_token'])) {
        if ($transport === 'polling') {
            $heartbeatStmt = $db->prepare("SELECT config_value FROM configurations WHERE config_key = 'telegram_polling_heartbeat' LIMIT 1");
            $heartbeatStmt->execute();
            $heartbeat = $heartbeatStmt->get_result()->fetch_assoc()['config_value'] ?? null;

            if ($heartbeat) {
                $heartbeatAge = time() - strtotime($heartbeat);
                if ($heartbeatAge <= 120) {
                    $health['checks']['telegram'] = [
                        'status' => 'ok',
                        'message' => 'Polling mode active',
                        'heartbeat_age_sec' => $heartbeatAge
                    ];
                } else {
                    $health['status'] = 'degraded';
                    $health['checks']['telegram'] = [
                        'status' => 'warning',
                        'message' => 'Polling heartbeat stale',
                        'heartbeat_age_sec' => $heartbeatAge
                    ];
                }
            } else {
                $health['status'] = 'degraded';
                $health['checks']['telegram'] = [
                    'status' => 'warning',
                    'message' => 'Polling mode configured but heartbeat missing'
                ];
            }
        } else {
            $url = "https://api.telegram.org/bot{$telegram['bot_token']}/getWebhookInfo";
            $response = @file_get_contents($url);
            $data = json_decode($response, true);

            if ($data && $data['ok']) {
                $webhookUrl = $data['result']['url'] ?? '';

                if (!empty($webhookUrl)) {
                    $health['checks']['telegram'] = [
                        'status' => 'ok',
                        'message' => 'Webhook configured',
                        'pending_updates' => $data['result']['pending_update_count'] ?? 0
                    ];
                } else {
                    $health['status'] = 'degraded';
                    $health['checks']['telegram'] = [
                        'status' => 'warning',
                        'message' => 'Webhook URL is empty'
                    ];
                }
            } else {
                throw new Exception('API request failed');
            }
        }
    } else {
        $health['checks']['telegram'] = [
            'status' => 'warning',
            'message' => 'Telegram not configured'
        ];
    }
} catch (Exception $e) {
    $health['checks']['telegram'] = [
        'status' => 'error',
        'message' => 'Telegram check failed: ' . $e->getMessage()
    ];
}

// Check 4: Disk space
$diskFree = disk_free_space('/');
$diskTotal = disk_total_space('/');
$diskUsedPercent = round((1 - ($diskFree / $diskTotal)) * 100, 2);

if ($diskUsedPercent > 90) {
    $health['status'] = 'degraded';
    $health['checks']['disk'] = [
        'status' => 'warning',
        'message' => "Disk usage critical: {$diskUsedPercent}%"
    ];
} else {
    $health['checks']['disk'] = [
        'status' => 'ok',
        'message' => "Disk usage: {$diskUsedPercent}%"
    ];
}

// Check 5: PHP version
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4', '<')) {
    $health['status'] = 'degraded';
    $health['checks']['php'] = [
        'status' => 'warning',
        'message' => "PHP version outdated: {$phpVersion}"
    ];
} else {
    $health['checks']['php'] = [
        'status' => 'ok',
        'message' => "PHP version: {$phpVersion}"
    ];
}

// Return appropriate HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

echo json_encode($health, JSON_PRETTY_PRINT);
