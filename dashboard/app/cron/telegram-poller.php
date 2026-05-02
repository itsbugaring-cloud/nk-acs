#!/usr/bin/env php
<?php

require_once __DIR__ . '/../config/database.php';

const TELEGRAM_OFFSET_KEY = 'telegram_polling_offset';
const TELEGRAM_HEARTBEAT_KEY = 'telegram_polling_heartbeat';
const TELEGRAM_WEBHOOK_CLEARED_KEY = 'telegram_polling_webhook_cleared';

function pollerLog(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    fwrite(STDOUT, "[telegram-poller] {$timestamp} {$message}\n");
}

function getConfigValue(mysqli $db, string $key, $default = null) {
    $stmt = $db->prepare("SELECT config_value FROM configurations WHERE config_key = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['config_value'] ?? $default;
}

function setConfigValue(mysqli $db, string $key, string $value): void {
    $stmt = $db->prepare("
        INSERT INTO configurations (config_key, config_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE
            config_value = VALUES(config_value),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

function getTelegramConfig(mysqli $db): ?array {
    $result = $db->query("SELECT bot_token, chat_id FROM telegram_config WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
    if (!$result) {
        return null;
    }

    $config = $result->fetch_assoc();
    return $config ?: null;
}

function telegramApiRequest(string $botToken, string $method, array $params = []): array {
    $ch = curl_init("https://api.telegram.org/bot{$botToken}/{$method}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'error' => $error !== '' ? $error : 'Unknown cURL error',
            'http_code' => $httpCode,
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Invalid Telegram API response',
            'http_code' => $httpCode,
        ];
    }

    $decoded['http_code'] = $httpCode;
    return $decoded;
}

function disableWebhookOnce(mysqli $db, string $botToken): void {
    $tokenHash = hash('sha256', $botToken);
    if ((string) getConfigValue($db, TELEGRAM_WEBHOOK_CLEARED_KEY, '') === $tokenHash) {
        return;
    }

    $result = telegramApiRequest($botToken, 'deleteWebhook', [
        'drop_pending_updates' => 'false',
    ]);

    if (!($result['ok'] ?? false)) {
        $message = $result['description'] ?? $result['error'] ?? 'Failed to disable webhook';
        pollerLog("deleteWebhook warning: {$message}");
        return;
    }

    setConfigValue($db, TELEGRAM_WEBHOOK_CLEARED_KEY, $tokenHash);
    pollerLog('Webhook disabled, polling mode active');
}

function touchHeartbeat(mysqli $db): void {
    setConfigValue($db, TELEGRAM_HEARTBEAT_KEY, date('Y-m-d H:i:s'));
}

function forwardUpdate(string $targetUrl, array $update): bool {
    $payload = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        pollerLog('Failed to encode update payload');
        return false;
    }

    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        pollerLog("Forward failed: {$error}");
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $snippet = trim(preg_replace('/\s+/', ' ', strip_tags((string) $response)));
        if ($snippet !== '') {
            $snippet = substr($snippet, 0, 220);
            pollerLog("Forward failed with HTTP {$httpCode}: {$snippet}");
        } else {
            pollerLog("Forward failed with HTTP {$httpCode}");
        }
        return false;
    }

    return true;
}

$transport = strtolower((string) envValue('TELEGRAM_BOT_TRANSPORT', 'webhook'));
if ($transport !== 'polling') {
    pollerLog("Transport '{$transport}' is not polling; exiting");
    exit(0);
}

$targetUrl = (string) envValue('TELEGRAM_POLLING_TARGET_URL', 'http://127.0.0.1/webhook/telegram.php');
$idleSleep = max(2, (int) envValue('TELEGRAM_POLLING_IDLE_SLEEP', 5));
$errorSleep = max(3, (int) envValue('TELEGRAM_POLLING_ERROR_SLEEP', 8));
$timeout = max(10, min(50, (int) envValue('TELEGRAM_POLLING_TIMEOUT', 25)));

pollerLog("Starting polling loop -> {$targetUrl}");

while (true) {
    $db = getDBConnection();
    $config = getTelegramConfig($db);

    if (!$config || empty($config['bot_token'])) {
        pollerLog('Telegram config not connected yet; waiting');
        sleep($idleSleep);
        continue;
    }

    $botToken = (string) $config['bot_token'];
    disableWebhookOnce($db, $botToken);
    touchHeartbeat($db);

    $offset = (int) getConfigValue($db, TELEGRAM_OFFSET_KEY, '0');
    $response = telegramApiRequest($botToken, 'getUpdates', [
        'timeout' => (string) $timeout,
        'offset' => (string) $offset,
        'allowed_updates' => json_encode(['message', 'callback_query']),
    ]);

    touchHeartbeat($db);

    if (!($response['ok'] ?? false)) {
        $message = $response['description'] ?? $response['error'] ?? 'Unknown Telegram API error';
        pollerLog("getUpdates failed: {$message}");
        sleep($errorSleep);
        continue;
    }

    $updates = $response['result'] ?? [];
    if (!is_array($updates) || $updates === []) {
        sleep(1);
        continue;
    }

    foreach ($updates as $update) {
        $updateId = isset($update['update_id']) ? (int) $update['update_id'] : null;
        if ($updateId === null) {
            continue;
        }

        if (!forwardUpdate($targetUrl, $update)) {
            sleep($errorSleep);
            continue 2;
        }

        setConfigValue($db, TELEGRAM_OFFSET_KEY, (string) ($updateId + 1));
        touchHeartbeat($db);
        pollerLog("Update {$updateId} berhasil diproses");
    }
}
