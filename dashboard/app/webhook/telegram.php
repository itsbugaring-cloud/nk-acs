<?php
require_once __DIR__ . '/../config/config.php';

use App\TelegramBot;
use App\GenieACS;
use App\GenieACS_Fast;
use App\ReportGenerator;
use App\PermissionManager;

// Get Telegram webhook update
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    exit;
}

// Get telegram config
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM telegram_config WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$telegramConfig = $result->fetch_assoc();

if (!$telegramConfig) {
    http_response_code(200);
    exit;
}

$telegram = new TelegramBot($telegramConfig['bot_token'], $telegramConfig['chat_id']);

// Initialize Permission Manager
$permissionManager = new PermissionManager($conn);

// Get GenieACS config
$genieacsResult = $conn->query("SELECT * FROM genieacs_credentials WHERE is_connected = 1 ORDER BY id DESC LIMIT 1");
$genieacsConfig = $genieacsResult->fetch_assoc();

// Initialize GenieACS if configured
$genieacs = null;
if ($genieacsConfig) {
    $genieacs = new GenieACS(
        $genieacsConfig['host'],
        $genieacsConfig['port'],
        $genieacsConfig['username'],
        $genieacsConfig['password']
    );
}

// ====================
// User Authorization Middleware
// ====================
function getUserFromUpdate($update, $permissionManager) {
    $chatId = null;
    $username = null;
    $firstName = null;
    $lastName = null;

    if (isset($update['callback_query'])) {
        $user = $update['callback_query']['from'];
        $chatId = $user['id'];
        $username = $user['username'] ?? null;
        $firstName = $user['first_name'] ?? null;
        $lastName = $user['last_name'] ?? null;
    } elseif (isset($update['message'])) {
        $user = $update['message']['from'];
        $chatId = $user['id'];
        $username = $user['username'] ?? null;
        $firstName = $user['first_name'] ?? null;
        $lastName = $user['last_name'] ?? null;
    }

    if ($chatId) {
        // Auto-register new users or update existing
        $permissionManager->upsertUser($chatId, $username, $firstName, $lastName);
        $permissionManager->updateLastActivity($chatId);

        return $permissionManager->getUser($chatId);
    }

    return null;
}

// Auto-register/update user
$currentUser = getUserFromUpdate($update, $permissionManager);

// Check if user is authorized
if ($currentUser && !$currentUser['is_active']) {
    $telegram->sendMessage(
        "❌ <b>Access Denied</b>\n\n" .
        "Your account has been deactivated.\n\n" .
        "Please contact the system administrator.",
        $currentUser['chat_id']
    );
    http_response_code(200);
    exit;
}

// ====================
// Handle Callback Query (Button Clicks)
// ====================
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $callbackData = $callbackQuery['data'];
    $callbackId = $callbackQuery['id'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];

    // Answer callback query immediately
    $telegram->answerCallbackQuery($callbackId);

    // Parse callback data
    $parts = explode('_', $callbackData);
    $action = $parts[0];

    switch ($action) {
        case 'menu':
            handleMenuAction($parts, $chatId, $messageId, $telegram, $genieacs, $conn);
            break;

        case 'device':
            handleDeviceAction($parts, $chatId, $messageId, $telegram, $genieacs, $conn);
            break;

        case 'action':
            handleQuickAction($parts, $chatId, $messageId, $telegram, $genieacs, $conn);
            break;

        case 'confirm':
            handleConfirmation($parts, $chatId, $messageId, $telegram, $genieacs, $conn);
            break;

        case 'filter':
            handleFilter($parts, $chatId, $messageId, $telegram, $genieacs, $conn);
            break;

        case 'noop':
            // Do nothing - just for display purposes
            break;

        default:
            $telegram->answerCallbackQuery($callbackId, "Unknown action", true);
            break;
    }

    http_response_code(200);
    exit;
}

// ====================
// Handle Regular Message
// ====================
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';

    // Check if user is in a session (multi-step interaction)
    $session = getUserSession($chatId, $conn);

    if ($session) {
        handleSessionMessage($session, $text, $chatId, $telegram, $genieacs, $conn);
        http_response_code(200);
        exit;
    }

    // Process command
    $command = $telegram->processCommand($text, $chatId);

    if (is_array($command) && isset($command['command'])) {
        switch ($command['command']) {
            case 'status':
                handleStatusCommand($command, $chatId, $telegram, $genieacs);
                break;

            case 'check':
                handleCheckCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'list':
                handleListCommand($chatId, $telegram, $genieacs);
                break;

            case 'stats':
                handleStatsCommand($chatId, $telegram, $genieacs);
                break;

            case 'summon':
                handleSummonCommand($command, $chatId, $telegram, $genieacs);
                break;

            case 'search':
                handleSearchCommand($command, $chatId, $telegram, $genieacs);
                break;

            case 'filter':
                handleFilterCommand($chatId, $telegram);
                break;

            case 'subscribe':
                handleSubscribeCommand($command, $chatId, $telegram, $conn);
                break;

            case 'unsubscribe':
                handleUnsubscribeCommand($command, $chatId, $telegram, $conn);
                break;

            case 'subscriptions':
                handleSubscriptionsCommand($chatId, $telegram, $conn);
                break;

            case 'first_inform_watch':
                handleFirstInformWatchCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'alarm_summary':
                handleAlarmSummaryCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'optical_summary':
                handleOpticalSummaryCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'offline_confirmed':
                handleOfflineConfirmedCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'flapping_devices':
                handleFlappingDevicesCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'massdrop_summary':
                handleMassdropSummaryCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'mute_area':
                handleMuteAreaCommand($command, $chatId, $telegram, $conn);
                break;

            case 'unmute_area':
                handleUnmuteAreaCommand($command, $chatId, $telegram, $conn);
                break;

            case 'mute_list':
                handleMuteListCommand($chatId, $telegram, $conn);
                break;

            case 'olt_summary':
                handleOltSummaryCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'task_monitor':
                handleTaskMonitorCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'timeline':
                handleTimelineCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'safe_mode':
                handleSafeModeCommand($chatId, $telegram);
                break;

            case 'report':
                handleReportCommand($command, $chatId, $telegram, $genieacs, $conn);
                break;

            case 'schedule_list':
                handleScheduleListCommand($chatId, $telegram, $conn);
                break;

            case 'schedule_daily':
                handleScheduleDailyCommand($command, $chatId, $telegram, $conn);
                break;

            case 'schedule_weekly':
                handleScheduleWeeklyCommand($command, $chatId, $telegram, $conn);
                break;

            case 'schedule_disable':
                handleScheduleDisableCommand($command, $chatId, $telegram, $conn);
                break;

            case 'whoami':
                handleWhoamiCommand($chatId, $telegram, $permissionManager);
                break;

            case 'users_list':
                handleUsersListCommand($chatId, $telegram, $permissionManager);
                break;

            case 'user_info':
                handleUserInfoCommand($command, $chatId, $telegram, $permissionManager);
                break;

            case 'user_setrole':
                handleUserSetRoleCommand($command, $chatId, $telegram, $permissionManager);
                break;

            case 'user_activate':
                handleUserActivateCommand($command, $chatId, $telegram, $permissionManager);
                break;

            case 'user_deactivate':
                handleUserDeactivateCommand($command, $chatId, $telegram, $permissionManager);
                break;
        }
    }
}

http_response_code(200);

// ====================
// Menu Action Handlers
// ====================
function handleMenuAction($parts, $chatId, $messageId, $telegram, $genieacs, $conn) {
    $menuType = $parts[1] ?? 'main';

    switch ($menuType) {
        case 'main':
            $message = "🤖 <b>" . APP_NAME . " Bot</b>\n\n";
            $message .= "Menu cepat operasional ACS. Aksi write ke ONT pelanggan tetap dikunci secara default.";
            $telegram->editMessage($messageId, $chatId, $message, $telegram->getMainMenuKeyboard());
            break;

        case 'device':
            if ($parts[1] === 'device' && $parts[2] === 'list') {
                handleListCommand($chatId, $telegram, $genieacs, $messageId);
            } elseif ($parts[1] === 'device' && $parts[2] === 'status') {
                $telegram->editMessage($messageId, $chatId, "Gunakan <code>/status &lt;device_id&gt;</code> atau pilih dari daftar perangkat.", $telegram->getMainMenuKeyboard());
            }
            break;

        case 'stats':
            handleStatsCommand($chatId, $telegram, $genieacs, $messageId);
            break;

        case 'search':
            $telegram->editMessage($messageId, $chatId, "🔍 <b>Cari Perangkat</b>\n\nGunakan: <code>/cek keyword</code>\n\nBisa cari SN pendek, SSID, PPPoE, customer, IP TR069, MAC, atau description OLT.\n\nContoh:\n<code>/cek TDTC35B24C50</code>\n<code>/cek Sonang</code>", $telegram->getMainMenuKeyboard());
            break;

        case 'subscriptions':
            handleSubscriptionsCommand($chatId, $telegram, $conn, $messageId);
            break;

        case 'firstinform':
            handleFirstInformWatchCommand(['olt' => ''], $chatId, $telegram, $genieacs, $conn, $messageId);
            break;

        case 'alarm':
            handleAlarmSummaryCommand(['query' => ''], $chatId, $telegram, $genieacs, $conn, $messageId);
            break;

        case 'redaman':
            handleOpticalSummaryCommand(['query' => ''], $chatId, $telegram, $genieacs, $conn, $messageId);
            break;

        case 'offline':
            handleOfflineConfirmedCommand(['query' => ''], $chatId, $telegram, $genieacs, $conn, $messageId);
            break;

        case 'olt':
            handleOltSummaryCommand(['query' => ''], $chatId, $telegram, $genieacs, $conn, $messageId);
            break;

        case 'tasks':
            handleTaskMonitorCommand(['query' => ''], $chatId, $telegram, $genieacs, $conn, $messageId);
            break;

        case 'safe':
            handleSafeModeCommand($chatId, $telegram, $messageId);
            break;

        case 'help':
            $helpMessage = "📖 <b>Daftar Perintah:</b>\n\n";
            $helpMessage .= "/start - Menu utama\n";
            $helpMessage .= "/stats - Statistik dashboard\n";
            $helpMessage .= "/list - Daftar perangkat\n";
            $helpMessage .= "/status &lt;id&gt; - Status perangkat\n";
            $helpMessage .= "/cek &lt;keyword&gt; - Snapshot cepat ACS + OLT\n";
            $helpMessage .= "/summon &lt;id&gt; - Panggil read-only\n";
            $helpMessage .= "/belumacs [olt] - ONT online OLT belum ACS\n";
            $helpMessage .= "/alarm [olt] - Ringkasan alarm aktif\n";
            $helpMessage .= "/redaman [olt] - Redaman semua / per OLT\n";
            $helpMessage .= "/offline [olt] - ONT offline confirmed\n";
            $helpMessage .= "/flapping [olt] - ONT sering naik-turun\n";
            $helpMessage .= "/massdrop [olt] - Ringkasan mass-drop OLT\n";
            $helpMessage .= "/mute &lt;olt&gt; 60m - Senyapkan alarm area saat maintenance\n";
            $helpMessage .= "/unmute &lt;olt&gt; - Buka mute area\n";
            $helpMessage .= "/mutes - Lihat area yang sedang mute\n";
            $helpMessage .= "/olt [nama] - Ringkasan OLT\n";
            $helpMessage .= "/task [keyword] - Task queue\n";
            $helpMessage .= "/timeline &lt;keyword&gt; - Riwayat perangkat\n";
            $helpMessage .= "/help - Bantuan";
            $telegram->editMessage($messageId, $chatId, $helpMessage, $telegram->getMainMenuKeyboard());
            break;

        case 'summon':
            $telegram->editMessage($messageId, $chatId, "⚡ <b>Panggil Read-Only</b>\n\nGunakan: <code>/summon device_id</code>\n\nAksi ini hanya connection request + refreshObject. Tidak reboot dan tidak ubah konfigurasi ONT.", $telegram->getMainMenuKeyboard());
            break;
    }
}

function handleDeviceAction($parts, $chatId, $messageId, $telegram, $genieacs, $conn) {
    $deviceAction = $parts[1] ?? '';

    if ($deviceAction === 'detail') {
        $deviceId = count($parts) > 2 ? implode('_', array_slice($parts, 2)) : '';
        if ($deviceId) {
            showDeviceDetail($deviceId, $chatId, $messageId, $telegram, $genieacs);
        }
    } elseif ($deviceAction === 'list' && isset($parts[2]) && $parts[2] === 'page') {
        $page = intval($parts[3] ?? 1);
        handleListCommand($chatId, $telegram, $genieacs, $messageId, $page);
    }
}

function handleQuickAction($parts, $chatId, $messageId, $telegram, $genieacs, $conn) {
    $quickAction = $parts[1] ?? '';
    $deviceId = count($parts) > 2 ? implode('_', array_slice($parts, 2)) : '';

    switch ($quickAction) {
        case 'summon':
            if ($deviceId) {
                $message = "⚡ <b>Panggil Perangkat</b>\n\n";
                $message .= "Device ID: <code>{$deviceId}</code>\n\n";
                $message .= "Aksi ini hanya kirim connection request + refreshObject read-only. Tidak reboot dan tidak mengubah konfigurasi ONT.\n\n";
                $message .= "Lanjutkan?";
                $telegram->editMessage($messageId, $chatId, $message, $telegram->getConfirmationKeyboard('summon', $deviceId));
            }
            break;

        case 'subscribe':
            handleSubscribeAction($deviceId, $chatId, $messageId, $telegram, $conn);
            break;

        case 'editwifi':
            startWiFiEditSession($deviceId, $chatId, $messageId, $telegram, $conn);
            break;

        case 'optical':
            showOpticalDetail($deviceId, $chatId, $messageId, $telegram, $genieacs, $conn);
            break;

        case 'wifiinfo':
            showWifiInfo($deviceId, $chatId, $messageId, $telegram, $genieacs, $conn);
            break;

        case 'timeline':
            handleTimelineCommand(['query' => $deviceId], $chatId, $telegram, $genieacs, $conn, $messageId);
            break;

        case 'location':
            showDeviceLocation($deviceId, $chatId, $messageId, $telegram, $conn);
            break;

        case 'sendgps':
            sendDeviceGPSLocation($deviceId, $chatId, $telegram, $conn);
            break;
    }
}

function handleConfirmation($parts, $chatId, $messageId, $telegram, $genieacs, $conn) {
    $confirmed = $parts[1] === 'yes';
    $action = $parts[2] ?? '';
    $data = count($parts) > 3 ? implode('_', array_slice($parts, 3)) : '';

    if (!$confirmed) {
        $telegram->editMessage($messageId, $chatId, "❌ Aksi dibatalkan.", $telegram->getMainMenuKeyboard());
        return;
    }

    switch ($action) {
        case 'summon':
            if ($genieacs) {
                $result = $genieacs->summonDevice($data);
                $telegram->editMessage($messageId, $chatId, buildTelegramSummonFeedback($data, $result), $telegram->getMainMenuKeyboard());
            }
            break;
    }
}

function handleFilter($parts, $chatId, $messageId, $telegram, $genieacs, $conn) {
    $filterType = $parts[1] ?? 'all';

    if ($filterType === 'online' || $filterType === 'offline') {
        handleListCommand($chatId, $telegram, $genieacs, $messageId, 1, $filterType);
    } elseif (strpos($filterType, 'signal') === 0) {
        $signalLevel = $parts[2] ?? 'all';
        $telegram->editMessage($messageId, $chatId, "📶 Filtering by signal: {$signalLevel}\n\nThis feature is coming soon!", $telegram->getFilterKeyboard());
    } elseif ($filterType === 'all') {
        handleListCommand($chatId, $telegram, $genieacs, $messageId, 1, 'all');
    }
}

// ====================
// Command Handlers
// ====================
function handleStatusCommand($command, $chatId, $telegram, $genieacs) {
    if (!$genieacs) {
        $telegram->sendMessage("GenieACS belum terkonfigurasi.", $chatId);
        return;
    }

    $deviceId = $command['device_id'];
    $deviceResult = $genieacs->getDevice($deviceId);

    if ($deviceResult['success']) {
        $device = $genieacs->parseDeviceData($deviceResult['data']);
        showDeviceInfo($device, $chatId, null, $telegram, $genieacs);
    } else {
        $telegram->sendMessage("Device tidak ditemukan.", $chatId);
    }
}

function handleCheckCommand($command, $chatId, $telegram, $genieacs, $conn) {
    if (!$genieacs) {
        $telegram->sendMessage("❌ GenieACS not configured", $chatId);
        return;
    }

    $query = trim((string) ($command['query'] ?? ''));
    if ($query === '') {
        $telegram->sendMessage("❌ Query kosong. Gunakan format: <code>/cek SERIAL</code>", $chatId);
        return;
    }

    $telegram->sendMessage("Sedang cek data ACS/OLT...\n<code>/cek " . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . "</code>", $chatId);

    $lookup = findTelegramDeviceByQuery($query, $genieacs, $conn);
    if (!$lookup['success']) {
        $telegram->sendMessage("❌ " . htmlspecialchars($lookup['message'], ENT_QUOTES, 'UTF-8'), $chatId);
        return;
    }

    showDeviceInfo($lookup['device'], $chatId, null, $telegram, $genieacs);
}

function handleListCommand($chatId, $telegram, $genieacs, $messageId = null, $page = 1, $filter = 'all') {
    if (!$genieacs) {
        $msg = "GenieACS belum terkonfigurasi.";
        if ($messageId) {
            $telegram->editMessage($messageId, $chatId, $msg, $telegram->getMainMenuKeyboard());
        } else {
            $telegram->sendMessage($msg, $chatId);
        }
        return;
    }

    $devices = [];
    $walk = $genieacs->walkDevices(function ($device) use (&$devices, $genieacs, $filter) {
        $parsed = $genieacs->parseDeviceData($device);

        if ($filter === 'online' && $parsed['status'] !== 'online') {
            return;
        }
        if ($filter === 'offline' && $parsed['status'] !== 'offline') {
            return;
        }

        $devices[] = $parsed;
    }, [], 40);

    if (!$walk['success']) {
        $msg = "Gagal mengambil daftar perangkat.";
        if ($messageId) {
            $telegram->editMessage($messageId, $chatId, $msg, $telegram->getMainMenuKeyboard());
        } else {
            $telegram->sendMessage($msg, $chatId);
        }
        return;
    }

    if (count($devices) === 0) {
        $msg = "Tidak ada perangkat yang cocok.";
        if ($messageId) {
            $telegram->editMessage($messageId, $chatId, $msg, $telegram->getMainMenuKeyboard());
        } else {
            $telegram->sendMessage($msg, $chatId);
        }
        return;
    }

    $totalDevices = count($devices);
    $perPage = 10;
    $totalPages = ceil($totalDevices / $perPage);

    $filterText = $filter === 'all' ? 'Semua Perangkat' : 'Perangkat ' . ucfirst($filter);
    $message = "<b>{$filterText}</b>\n\n";
    $message .= "Total: {$totalDevices} perangkat\n";
    $message .= "Halaman: {$page}/{$totalPages}\n\n";
    $message .= "Pilih perangkat untuk melihat detail:";

    $keyboard = $telegram->getDeviceListKeyboard($devices, $page, $perPage);

    if ($messageId) {
        $telegram->editMessage($messageId, $chatId, $message, $keyboard);
    } else {
        $telegram->sendMessage($message, $chatId, $keyboard);
    }
}

function handleStatsCommand($chatId, $telegram, $genieacs, $messageId = null) {
    if (!$genieacs) {
        $msg = "GenieACS belum terkonfigurasi.";
        if ($messageId) {
            $telegram->editMessage($messageId, $chatId, $msg, $telegram->getMainMenuKeyboard());
        } else {
            $telegram->sendMessage($msg, $chatId);
        }
        return;
    }

    $stats = $genieacs->getDeviceStats();

    if (!$stats['success']) {
        $msg = "Gagal mengambil statistik.";
        if ($messageId) {
            $telegram->editMessage($messageId, $chatId, $msg, $telegram->getMainMenuKeyboard());
        } else {
            $telegram->sendMessage($msg, $chatId);
        }
        return;
    }

    $data = $stats['data'];
    $onlinePercent = $data['total'] > 0 ? round(($data['online'] / $data['total']) * 100) : 0;

    $message = "<b>Statistik NETKING-ACS</b>\n\n";
    $message .= "Total perangkat: <b>{$data['total']}</b>\n";
    $message .= "Online: <b>{$data['online']}</b> ({$onlinePercent}%)\n";
    $message .= "Offline: <b>{$data['offline']}</b>\n\n";
    $message .= "Update: " . date('Y-m-d H:i:s');

    if ($messageId) {
        $telegram->editMessage($messageId, $chatId, $message, $telegram->getMainMenuKeyboard());
    } else {
        $telegram->sendMessage($message, $chatId, $telegram->getMainMenuKeyboard());
    }
}

function handleSummonCommand($command, $chatId, $telegram, $genieacs) {
    global $permissionManager;

    // Check permission
    if (!$permissionManager->hasPermission($chatId, \App\PermissionManager::DEVICE_SUMMON)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::DEVICE_SUMMON), $chatId);
        return;
    }

    if (!$genieacs) {
        $telegram->sendMessage("GenieACS belum terkonfigurasi.", $chatId);
        return;
    }

    $deviceId = $command['device_id'];
    $result = $genieacs->summonDevice($deviceId);

    $telegram->sendMessage(buildTelegramSummonFeedback($deviceId, $result), $chatId);
}

function handleSearchCommand($command, $chatId, $telegram, $genieacs) {
    if (!$genieacs) {
        $telegram->sendMessage("GenieACS belum terkonfigurasi.", $chatId);
        return;
    }

    $keyword = strtolower($command['keyword']);
    $matchedDevices = [];
    $walk = $genieacs->walkDevices(function ($device) use (&$matchedDevices, $genieacs, $keyword) {
        $parsed = $genieacs->parseDeviceData($device);

        $searchFields = [
            strtolower($parsed['serial_number']),
            strtolower($parsed['mac_address']),
            strtolower($parsed['wifi_ssid']),
            strtolower($parsed['ip_address']),
            strtolower($parsed['product_class'])
        ];

        foreach ($searchFields as $field) {
            if (strpos($field, $keyword) !== false) {
                $matchedDevices[] = $parsed;
                break;
            }
        }
    }, [], 40);

    if (!$walk['success']) {
        $telegram->sendMessage("Gagal mencari perangkat.", $chatId);
        return;
    }

    if (count($matchedDevices) === 0) {
        $telegram->sendMessage("Tidak ada perangkat yang cocok: <code>{$keyword}</code>", $chatId);
        return;
    }

    $message = "<b>Hasil Pencarian</b>\n\n";
    $message .= "Keyword: <code>{$keyword}</code>\n";
    $message .= "Ditemukan: <b>" . count($matchedDevices) . "</b> perangkat\n\n";
    $message .= "Pilih perangkat:";

    $keyboard = $telegram->getDeviceListKeyboard($matchedDevices, 1, 10);
    $telegram->sendMessage($message, $chatId, $keyboard);
}

function handleFilterCommand($chatId, $telegram) {
    $message = "<b>Filter Perangkat</b>\n\n";
    $message .= "Pilih filter:";
    $telegram->sendMessage($message, $chatId, $telegram->getFilterKeyboard());
}

function handleSafeModeCommand($chatId, $telegram, $messageId = null) {
    $writeEnabled = function_exists('isCpeWriteEnabled') && isCpeWriteEnabled();
    $message = $writeEnabled
        ? "⚠️ <b>Aksi write ONT sedang terbuka</b>\n\nPastikan hanya admin yang menjalankan aksi sensitif."
        : "🔒 <b>Aksi write ONT sedang dikunci</b>\n\nBot hanya menampilkan data dan aksi aman seperti panggil refresh.";

    telegramSendOrEdit($telegram, $chatId, $message, $telegram->getMainMenuKeyboard(), $messageId);
}

function handleAlarmSummaryCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    $filter = trim((string) ($command['query'] ?? ''));
    if (in_array(strtolower($filter), ['all', 'semua', '*', '-'], true)) {
        $filter = '';
    }
    $offline = buildTelegramOfflineConfirmedSnapshot($conn, $filter);
    $flapping = buildTelegramFlappingSnapshot($conn, $filter);
    $massdrop = buildTelegramMassdropSnapshot($conn, $filter);
    $optical = buildTelegramOpticalAlarmSnapshot($conn, $filter);

    $message = "🚨 <b>Alarm Aktif NETKING-ACS</b>\n";
    if ($filter !== '') {
        $message .= "Filter: <code>" . telegramHtml($filter) . "</code>\n";
    }
    $message .= "Offline confirmed: <b>" . count($offline['items']) . "</b>\n";
    $message .= "Offline pending konfirmasi: <b>{$offline['pending']}</b>\n";
    $message .= "Redaman kritis: <b>{$optical['critical_count']}</b>\n";
    $message .= "Redaman warning: <b>{$optical['warning_count']}</b>\n";
    $message .= "Flapping 1 jam: <b>" . count($flapping['items']) . "</b>\n";
    $message .= "Mass-drop OLT: <b>" . count($massdrop['items']) . "</b>\n\n";

    if (!empty($massdrop['items'])) {
        $message .= "<b>Mass-drop terbaru</b>\n";
        foreach (array_slice($massdrop['items'], 0, 5) as $row) {
            $message .= "- " . telegramHtml($row['olt_name']) . ": " . telegramHtml($row['offline_confirmed_count'] ?? '-') . " ONT | " . telegramHtml(telegramSnapshotValue($row['last_alert_at'] ?? null)) . "\n";
        }
        $message .= "\n";
    }

    if (!empty($offline['items'])) {
        $message .= "<b>Offline confirmed</b>\n";
        foreach (array_slice($offline['items'], 0, 8) as $row) {
            $message .= "- " . telegramFormatAlarmDeviceLine($row) . "\n";
        }
        $message .= "\n";
    }

    if (!empty($optical['items'])) {
        $message .= "<b>Redaman terburuk</b>\n";
        foreach (array_slice($optical['items'], 0, 8) as $row) {
            $message .= "- " . telegramHtml($row['olt_name']) . " | " . telegramHtml(telegramSnapshotValue($row['description'] ?? null, $row['serial_number'] ?? null)) . " | RX <b>" . telegramHtml($row['rx_power']) . " dBm</b>\n";
        }
    }

    if (empty($massdrop['items']) && empty($offline['items']) && empty($optical['items']) && empty($flapping['items'])) {
        $message .= "Tidak ada alarm aktif yang tercatat oleh monitor.";
    }

    telegramSendOrEdit($telegram, $chatId, trim($message), $telegram->getMainMenuKeyboard(), $messageId);
}

function handleOpticalSummaryCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    $filter = trim((string) ($command['query'] ?? ''));
    if (in_array(strtolower($filter), ['all', 'semua', '*', '-'], true)) {
        $filter = '';
    }

    $snapshot = buildTelegramOpticalAlarmSnapshot($conn, $filter);
    $message = "📶 <b>Alarm Redaman NETKING-ACS</b>\n";
    if ($filter !== '') {
        $message .= "Filter OLT/keyword: <code>" . telegramHtml($filter) . "</code>\n";
    } else {
        $message .= "Scope: <b>Semua OLT</b>\n";
    }
    $message .= "Kritis (&lt;= -28 dBm): <b>{$snapshot['critical_count']}</b>\n";
    $message .= "Warning (-25 sampai -27.99 dBm): <b>{$snapshot['warning_count']}</b>\n";
    $message .= "Belum ada data RX: <b>{$snapshot['missing_count']}</b>\n";
    $message .= "OLT terdampak: <b>" . count($snapshot['olt_summary']) . "</b>\n";
    $message .= "Ditampilkan: <b>" . count($snapshot['items']) . "</b> ONT\n\n";

    if (!empty($snapshot['olt_summary'])) {
        $message .= "<b>Ringkasan per OLT</b>\n";
        foreach (array_slice($snapshot['olt_summary'], 0, 10) as $row) {
            $message .= "- " . telegramHtml($row['olt_name']) . ": "
                . "<b>{$row['critical_count']}</b> kritis / <b>{$row['warning_count']}</b> warning";
            if (isset($row['worst_rx'])) {
                $message .= " | terburuk <b>" . telegramHtml((string) $row['worst_rx']) . " dBm</b>";
            }
            $message .= "\n";
        }
        $message .= "\n";
    }

    if (empty($snapshot['items'])) {
        $message .= "Tidak ada ONT dengan redaman warning/kritis untuk filter ini.";
        if (($snapshot['missing_count'] ?? 0) > 0) {
            $message .= "\n\nNamun ada <b>{$snapshot['missing_count']}</b> ONT yang belum memiliki data RX.";
            if (!empty($snapshot['missing_items'])) {
                $message .= "\n\n<b>Contoh ONT tanpa data RX</b>\n";
                foreach (array_slice($snapshot['missing_items'], 0, 10) as $row) {
                    $message .= "- " . telegramHtml($row['olt_name']) . " | "
                        . telegramHtml(telegramSnapshotValue($row['description'] ?? null, $row['serial_number'] ?? null))
                        . " | PON " . telegramHtml(telegramSnapshotValue($row['pon_port'] ?? null))
                        . " / ID " . telegramHtml(telegramSnapshotValue($row['ont_index'] ?? null))
                        . "\n";
                }
            }
        }
    } else {
        $message .= "<b>ONT terburuk</b>\n";
        foreach (array_slice($snapshot['items'], 0, 15) as $row) {
            $status = ((float) ($row['rx_power'] ?? 0) <= -28) ? 'Kritis' : 'Warning';
            $message .= "- " . telegramHtml($row['olt_name']) . " | "
                . telegramHtml(telegramSnapshotValue($row['description'] ?? null, $row['serial_number'] ?? null))
                . " | PON " . telegramHtml(telegramSnapshotValue($row['pon_port'] ?? null))
                . " / ID " . telegramHtml(telegramSnapshotValue($row['ont_index'] ?? null))
                . " | RX <b>" . telegramHtml((string) $row['rx_power']) . " dBm</b>"
                . " | " . $status . "\n";
        }
        if (count($snapshot['items']) >= 15) {
            $message .= "\nTip: pakai <code>/redaman NamaOLT</code> agar daftar lebih ringkas.";
        }
    }

    telegramSendOrEdit($telegram, $chatId, trim($message), $telegram->getMainMenuKeyboard(), $messageId);
}

function handleOfflineConfirmedCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    $filter = trim((string) ($command['query'] ?? ''));
    $snapshot = buildTelegramOfflineConfirmedSnapshot($conn, $filter);

    $message = "🔴 <b>ONT Offline Confirmed</b>\n";
    if ($filter !== '') {
        $message .= "Filter: <code>" . telegramHtml($filter) . "</code>\n";
    }
    $message .= "Total: <b>" . count($snapshot['items']) . "</b>\n";
    $message .= "Pending konfirmasi: <b>{$snapshot['pending']}</b>\n";
    $message .= "\n";

    if (empty($snapshot['items'])) {
        $message .= "Tidak ada ONT offline confirmed untuk filter ini.";
    } else {
        foreach (array_slice($snapshot['items'], 0, 20) as $row) {
            $message .= "- " . telegramFormatAlarmDeviceLine($row) . "\n";
        }
        if (count($snapshot['items']) > 20) {
            $message .= "\nDitampilkan 20 dari " . count($snapshot['items']) . ". Pakai filter OLT agar lebih pendek.";
        }
    }

    telegramSendOrEdit($telegram, $chatId, trim($message), $telegram->getMainMenuKeyboard(), $messageId);
}

function handleFlappingDevicesCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    $filter = trim((string) ($command['query'] ?? ''));
    $snapshot = buildTelegramFlappingSnapshot($conn, $filter);

    $message = "🟡 <b>ONT Flapping</b>\n";
    if ($filter !== '') {
        $message .= "Filter: <code>" . telegramHtml($filter) . "</code>\n";
    }
    $message .= "Total tercatat: <b>" . count($snapshot['items']) . "</b>\n";
    $message .= "\n";

    if (empty($snapshot['items'])) {
        $message .= "Belum ada ONT flapping yang melewati ambang alert.";
    } else {
        foreach (array_slice($snapshot['items'], 0, 20) as $row) {
            $message .= "- " . telegramFormatAlarmDeviceLine($row) . " | " . telegramHtml($row['change_count_1h'] ?? '-') . "x/jam\n";
        }
    }

    telegramSendOrEdit($telegram, $chatId, trim($message), $telegram->getMainMenuKeyboard(), $messageId);
}

function handleMassdropSummaryCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    $filter = trim((string) ($command['query'] ?? ''));
    $snapshot = buildTelegramMassdropSnapshot($conn, $filter);

    $message = "🚨 <b>Mass-Drop OLT</b>\n";
    if ($filter !== '') {
        $message .= "Filter: <code>" . telegramHtml($filter) . "</code>\n";
    }
    $message .= "Total OLT tercatat: <b>" . count($snapshot['items']) . "</b>\n";
    $message .= "\n";

    if (empty($snapshot['items'])) {
        $message .= "Belum ada mass-drop yang melewati ambang alert.";
    } else {
        foreach (array_slice($snapshot['items'], 0, 20) as $row) {
            $message .= "- <b>" . telegramHtml($row['olt_name']) . "</b>: " . telegramHtml($row['offline_confirmed_count'] ?? '-') . " ONT | " . telegramHtml(telegramSnapshotValue($row['last_alert_at'] ?? null)) . "\n";
        }
    }

    telegramSendOrEdit($telegram, $chatId, trim($message), $telegram->getMainMenuKeyboard(), $messageId);
}

function handleMuteAreaCommand($command, $chatId, $telegram, $conn, $messageId = null) {
    $area = trim((string) ($command['query'] ?? ''));
    $duration = trim((string) ($command['duration'] ?? '60m'));
    if ($area === '') {
        telegramSendOrEdit($telegram, $chatId, "Gunakan: <code>/mute nama_OLT 60m</code>\n\nContoh:\n<code>/mute Cikalong 60m</code>", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $seconds = telegramParseDurationSeconds($duration);
    if ($seconds <= 0) {
        telegramSendOrEdit($telegram, $chatId, "Durasi tidak valid. Pakai format seperti <code>30m</code>, <code>2h</code>, atau <code>1d</code>.", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $mutedUntil = date('Y-m-d H:i:s', time() + $seconds);
    $payload = [
        'area' => $area,
        'muted_until' => $mutedUntil,
        'created_by' => (string) $chatId,
        'created_at' => date('c'),
        'duration' => $duration,
    ];

    telegramSetJsonConfig($conn, 'telegram_area_mute_' . sha1(telegramNormalizeArea($area)), $payload);

    $message = "🔕 <b>Mute Maintenance Aktif</b>\n\n";
    $message .= "Area/OLT: <b>" . telegramHtml($area) . "</b>\n";
    $message .= "Sampai: <code>" . telegramHtml($mutedUntil) . "</code>\n\n";
    $message .= "Alarm Telegram untuk area ini akan disenyapkan sementara. Tidak ada perubahan ke ONT pelanggan.";

    telegramSendOrEdit($telegram, $chatId, $message, $telegram->getMainMenuKeyboard(), $messageId);
}

function handleUnmuteAreaCommand($command, $chatId, $telegram, $conn, $messageId = null) {
    $area = trim((string) ($command['query'] ?? ''));
    if ($area === '') {
        telegramSendOrEdit($telegram, $chatId, "Gunakan: <code>/unmute nama_OLT</code>\n\nContoh:\n<code>/unmute Cikalong</code>", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $key = 'telegram_area_mute_' . sha1(telegramNormalizeArea($area));
    $stmt = $conn->prepare("DELETE FROM configurations WHERE config_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();

    telegramSendOrEdit($telegram, $chatId, "🔔 Mute dibuka untuk <b>" . telegramHtml($area) . "</b>.\n\nAlarm area ini aktif lagi.", $telegram->getMainMenuKeyboard(), $messageId);
}

function handleMuteListCommand($chatId, $telegram, $conn, $messageId = null) {
    $mutes = telegramGetActiveMutes($conn);
    $message = "🔕 <b>Mute Maintenance Aktif</b>\n\n";
    if (empty($mutes)) {
        $message .= "Tidak ada area/OLT yang sedang mute.";
    } else {
        foreach ($mutes as $mute) {
            $message .= "- <b>" . telegramHtml($mute['area'] ?? '-') . "</b> sampai <code>" . telegramHtml($mute['muted_until'] ?? '-') . "</code>\n";
        }
        $message .= "\nBuka dengan: <code>/unmute nama_OLT</code>";
    }

    telegramSendOrEdit($telegram, $chatId, $message, $telegram->getMainMenuKeyboard(), $messageId);
}

function handleFirstInformWatchCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    if (!$genieacs) {
        telegramSendOrEdit($telegram, $chatId, "❌ GenieACS belum terkonfigurasi", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $filter = trim((string) ($command['olt'] ?? ''));
    $snapshot = buildTelegramFirstInformGap($genieacs, $conn, $filter);
    if (!$snapshot['success']) {
        telegramSendOrEdit($telegram, $chatId, "❌ " . telegramHtml($snapshot['message']), $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $title = $filter !== '' ? "ONT belum ACS - " . $filter : "ONT online belum ACS";
    $message = "🟠 <b>" . telegramHtml($title) . "</b>\n\n";
    $message .= "Total belum ACS: <b>{$snapshot['total_missing']}</b>\n";
    $message .= "OLT terdampak: <b>" . count($snapshot['summary']) . "</b>\n";
    $message .= "Mode aksi: <b>monitoring saja</b>\n\n";

    if (empty($snapshot['summary'])) {
        $message .= "✅ Tidak ada ONT online dari inventory OLT yang belum first-inform ACS.";
    } else {
        $message .= "<b>Ringkasan per OLT</b>\n";
        foreach (array_slice($snapshot['summary'], 0, 10) as $row) {
            $message .= "- " . telegramHtml($row['olt_name']) . ": <b>{$row['missing']}</b> belum ACS dari {$row['online']} online\n";
        }

        if (!empty($snapshot['items'])) {
            $message .= "\n<b>Contoh target bootstrap</b>\n";
            $index = 1;
            foreach (array_slice($snapshot['items'], 0, 12) as $item) {
                $desc = telegramSnapshotValue($item['description'] ?? null);
                $message .= $index . ". " . telegramHtml($item['olt_name']) . " | <code>" . telegramHtml($item['serial_number']) . "</code> | PON " . telegramHtml($item['pon_port'] ?: '-') . " | " . telegramHtml($desc) . "\n";
                $index++;
            }
        }
    }

    telegramSendOrEdit($telegram, $chatId, $message, $telegram->getMainMenuKeyboard(), $messageId);
}

function handleOltSummaryCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    if (!$genieacs) {
        telegramSendOrEdit($telegram, $chatId, "❌ GenieACS belum terkonfigurasi", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $filter = trim((string) ($command['query'] ?? ''));
    $summary = buildTelegramOltSummary($genieacs, $conn, $filter);
    if (!$summary['success']) {
        telegramSendOrEdit($telegram, $chatId, "❌ " . telegramHtml($summary['message']), $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $message = "🧭 <b>Ringkasan OLT</b>\n";
    if ($filter !== '') {
        $message .= "Filter: <code>" . telegramHtml($filter) . "</code>\n";
    }
    $message .= "Mode aksi: <b>monitoring saja</b>\n\n";

    if (empty($summary['items'])) {
        $message .= "Tidak ada OLT yang cocok.";
    } else {
        foreach (array_slice($summary['items'], 0, 12) as $row) {
            $message .= "<b>" . telegramHtml($row['olt_name']) . "</b>\n";
            $message .= "ONT: {$row['online']}/{$row['total']} online, {$row['offline']} offline\n";
            $message .= "ACS: {$row['in_acs']} masuk, {$row['missing_acs']} belum, {$row['online_missing_acs']} online-belum ACS\n";
            $message .= "Redaman: {$row['critical_rx']} kritis, {$row['warning_rx']} warning, {$row['missing_rx']} kosong\n";
            $message .= "Sync: " . telegramHtml(telegramSnapshotValue($row['last_sync'] ?? null)) . "\n\n";
        }
    }

    telegramSendOrEdit($telegram, $chatId, trim($message), $telegram->getMainMenuKeyboard(), $messageId);
}

function handleTaskMonitorCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    if (!$genieacs) {
        telegramSendOrEdit($telegram, $chatId, "❌ GenieACS belum terkonfigurasi", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $query = trim((string) ($command['query'] ?? ''));
    $deviceId = null;
    if ($query !== '') {
        $lookup = findTelegramDeviceByQuery($query, $genieacs, $conn);
        if (!empty($lookup['success'])) {
            $deviceId = $lookup['device']['device_id'] ?? null;
        } else {
            $deviceId = $query;
        }
    }

    $tasks = $genieacs->getTasks($deviceId ? ['device' => $deviceId] : [], 20, 0);
    $faults = $genieacs->getFaults($deviceId ? ['device' => $deviceId] : [], 20, 0);

    $message = "🧾 <b>Task Queue GenieACS</b>\n";
    if ($deviceId) {
        $message .= "Device: <code>" . telegramHtml($deviceId) . "</code>\n";
    } else {
        $message .= "Scope: task terbaru semua perangkat\n";
    }
    $message .= "Mode aksi: <b>monitoring saja</b>\n\n";

    $taskItems = (!empty($tasks['success']) && is_array($tasks['data'] ?? null)) ? $tasks['data'] : [];
    $faultItems = (!empty($faults['success']) && is_array($faults['data'] ?? null)) ? $faults['data'] : [];

    $message .= "<b>Pending Task:</b> " . count($taskItems) . "\n";
    foreach (array_slice($taskItems, 0, 8) as $task) {
        $message .= "- " . telegramHtml(telegramTaskLabel($task)) . "\n";
    }

    $message .= "\n<b>Fault Terakhir:</b> " . count($faultItems) . "\n";
    foreach (array_slice($faultItems, 0, 8) as $fault) {
        $message .= "- " . telegramHtml(telegramFaultLabel($fault)) . "\n";
    }

    if (empty($taskItems) && empty($faultItems)) {
        $message .= "\n✅ Tidak ada task/fault yang terlihat untuk scope ini.";
    }

    telegramSendOrEdit($telegram, $chatId, $message, $telegram->getMainMenuKeyboard(), $messageId);
}

function handleTimelineCommand($command, $chatId, $telegram, $genieacs, $conn, $messageId = null) {
    if (!$genieacs) {
        telegramSendOrEdit($telegram, $chatId, "❌ GenieACS belum terkonfigurasi", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $query = trim((string) ($command['query'] ?? ''));
    if ($query === '') {
        telegramSendOrEdit($telegram, $chatId, "Gunakan: <code>/timeline SERIAL</code>", $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $lookup = findTelegramDeviceByQuery($query, $genieacs, $conn);
    if (empty($lookup['success'])) {
        telegramSendOrEdit($telegram, $chatId, "❌ " . telegramHtml($lookup['message']), $telegram->getMainMenuKeyboard(), $messageId);
        return;
    }

    $device = $lookup['device'];
    $inventory = getTelegramOltInventorySnapshot($conn, $device);
    $deviceId = (string) ($device['device_id'] ?? '');

    $message = "🕒 <b>Incident Timeline</b>\n";
    $message .= "Device: <code>" . telegramHtml($deviceId) . "</code>\n";
    $message .= "Customer: " . telegramHtml(telegramSnapshotValue($inventory['customer_name'] ?? null, $inventory['description'] ?? null, $device['customer_name'] ?? null)) . "\n\n";
    $message .= "Sekarang: " . telegramHtml(strtoupper((string) ($device['status'] ?? 'unknown'))) . "\n";
    $message .= "Last Inform: " . telegramHtml(telegramSnapshotValue($device['last_inform'] ?? null)) . "\n";
    $message .= "Redaman: " . telegramHtml(telegramSnapshotMetric($inventory['rx_power'] ?? ($device['rx_power'] ?? null), 'dBm')) . "\n";
    $message .= "OLT: " . telegramHtml(telegramSnapshotValue($inventory['olt_name'] ?? null)) . "\n\n";

    $stmt = $conn->prepare("SELECT status, created_at FROM device_monitoring WHERE device_id = ? ORDER BY created_at DESC LIMIT 8");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();

    $message .= "<b>Status History</b>\n";
    $hasHistory = false;
    while ($row = $result->fetch_assoc()) {
        $hasHistory = true;
        $message .= "- " . telegramHtml($row['created_at']) . " : " . telegramHtml(strtoupper($row['status'])) . "\n";
    }
    if (!$hasHistory) {
        $message .= "- Belum ada history monitor lokal.\n";
    }

    telegramSendOrEdit($telegram, $chatId, $message, $telegram->getDeviceDetailKeyboard($deviceId), $messageId);
}


function handleSubscribeCommand($command, $chatId, $telegram, $conn) {
    global $permissionManager;

    // Check permission
    if (!$permissionManager->hasPermission($chatId, \App\PermissionManager::NOTIFICATION_SUBSCRIBE)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::NOTIFICATION_SUBSCRIBE), $chatId);
        return;
    }

    $deviceId = $command['device_id'];

    // Check if already subscribed
    $stmt = $conn->prepare("SELECT id FROM telegram_subscriptions WHERE chat_id = ? AND device_id = ? AND is_active = 1");
    $stmt->bind_param("ss", $chatId, $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $telegram->sendMessage("ℹ️ You are already subscribed to <code>{$deviceId}</code>", $chatId);
        return;
    }

    // Add subscription
    $stmt = $conn->prepare("INSERT INTO telegram_subscriptions (chat_id, device_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_active = 1");
    $stmt->bind_param("ss", $chatId, $deviceId);

    if ($stmt->execute()) {
        $telegram->sendMessage("✅ Successfully subscribed to <code>{$deviceId}</code>\n\nYou will receive notifications when this device status changes.", $chatId);
    } else {
        $telegram->sendMessage("❌ Failed to subscribe to device", $chatId);
    }
}

function handleUnsubscribeCommand($command, $chatId, $telegram, $conn) {
    $deviceId = $command['device_id'];

    $stmt = $conn->prepare("UPDATE telegram_subscriptions SET is_active = 0 WHERE chat_id = ? AND device_id = ?");
    $stmt->bind_param("ss", $chatId, $deviceId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $telegram->sendMessage("✅ Successfully unsubscribed from <code>{$deviceId}</code>", $chatId);
    } else {
        $telegram->sendMessage("❌ You are not subscribed to this device", $chatId);
    }
}

function handleSubscriptionsCommand($chatId, $telegram, $conn, $messageId = null) {
    $stmt = $conn->prepare("SELECT device_id, subscribed_at FROM telegram_subscriptions WHERE chat_id = ? AND is_active = 1 ORDER BY subscribed_at DESC");
    $stmt->bind_param("s", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();

    $subscriptions = [];
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }

    if (count($subscriptions) === 0) {
        $msg = "🔔 <b>Your Subscriptions</b>\n\nYou have no active subscriptions.\n\nUse /subscribe <device_id> to subscribe to device notifications.";
        if ($messageId) {
            $telegram->editMessage($messageId, $chatId, $msg, $telegram->getMainMenuKeyboard());
        } else {
            $telegram->sendMessage($msg, $chatId, $telegram->getMainMenuKeyboard());
        }
        return;
    }

    $message = "🔔 <b>Your Subscriptions</b>\n\n";
    $message .= "You are subscribed to <b>" . count($subscriptions) . "</b> device(s):\n\n";

    foreach ($subscriptions as $sub) {
        $message .= "• <code>{$sub['device_id']}</code>\n";
    }

    $message .= "\n💡 Use /unsubscribe <device_id> to unsubscribe";

    if ($messageId) {
        $telegram->editMessage($messageId, $chatId, $message, $telegram->getMainMenuKeyboard());
    } else {
        $telegram->sendMessage($message, $chatId, $telegram->getMainMenuKeyboard());
    }
}

// ====================
// Helper Functions
// ====================
function showDeviceDetail($deviceId, $chatId, $messageId, $telegram, $genieacs) {
    if (!$genieacs) {
        $telegram->editMessage($messageId, $chatId, "❌ GenieACS not configured", $telegram->getMainMenuKeyboard());
        return;
    }

    $deviceResult = $genieacs->getDevice($deviceId);

    if (!$deviceResult['success']) {
        $telegram->editMessage($messageId, $chatId, "❌ Device not found", $telegram->getMainMenuKeyboard());
        return;
    }

    $device = $genieacs->parseDeviceData($deviceResult['data']);
    showDeviceInfo($device, $chatId, $messageId, $telegram, $genieacs);
}

function showDeviceInfo($device, $chatId, $messageId, $telegram, $genieacs) {
    global $conn;

    $message = buildTelegramDeviceSnapshot($device, $conn);

    $keyboard = $telegram->getDeviceDetailKeyboard($device['device_id']);

    if ($messageId) {
        $telegram->editMessage($messageId, $chatId, $message, $keyboard);
    } else {
        $telegram->sendMessage($message, $chatId, $keyboard);
    }
}

function showOpticalDetail($deviceId, $chatId, $messageId, $telegram, $genieacs, $conn) {
    if (!$genieacs) {
        $telegram->editMessage($messageId, $chatId, "❌ GenieACS belum terkonfigurasi", $telegram->getMainMenuKeyboard());
        return;
    }

    $deviceResult = $genieacs->getDevice($deviceId);
    if (!$deviceResult['success']) {
        $telegram->editMessage($messageId, $chatId, "❌ Device tidak ditemukan", $telegram->getMainMenuKeyboard());
        return;
    }

    $device = $genieacs->parseDeviceData($deviceResult['data']);
    $inventory = getTelegramOltInventorySnapshot($conn, $device);
    $rxValue = telegramSnapshotValue($inventory['rx_power'] ?? null) !== 'N/A' ? ($inventory['rx_power'] ?? null) : ($device['rx_power'] ?? null);

    $message = "📶 <b>Detail Redaman</b>\n\n";
    $message .= "Device: <code>" . telegramHtml($deviceId) . "</code>\n";
    $message .= "OLT: " . telegramHtml(telegramSnapshotValue($inventory['olt_name'] ?? null)) . "\n";
    $message .= "PON/ONT: " . telegramHtml(telegramSnapshotValue(buildTelegramFspId($inventory), $inventory['pon_port'] ?? null)) . "\n\n";
    $message .= "RX OLT/ACS: <b>" . telegramHtml(telegramSnapshotMetric($rxValue, 'dBm')) . "</b>\n";
    $message .= "RX ACS: " . telegramHtml(telegramSnapshotMetric($device['rx_power'] ?? null, 'dBm')) . "\n";
    $message .= "TX ACS: " . telegramHtml(telegramSnapshotMetric($device['optical_tx_power'] ?? ($inventory['tx_power'] ?? null), 'dBm')) . "\n";
    $message .= "Temperature: " . telegramHtml(telegramSnapshotMetric($device['temperature'] ?? null, 'C')) . "\n";
    $message .= "Voltage: " . telegramHtml(telegramSnapshotValue($device['optical_voltage'] ?? null)) . "\n";
    $message .= "Bias Current: " . telegramHtml(telegramSnapshotValue($device['optical_bias_current'] ?? null)) . "\n";
    $message .= "Status: <b>" . telegramHtml(classifyTelegramOpticalStatus($rxValue)) . "</b>\n\n";
    $message .= "Catatan: ini hanya baca data ACS/OLT, tidak mengubah ONT.";

    $telegram->editMessage($messageId, $chatId, $message, $telegram->getDeviceDetailKeyboard($deviceId));
}

function showWifiInfo($deviceId, $chatId, $messageId, $telegram, $genieacs, $conn) {
    if (!$genieacs) {
        $telegram->editMessage($messageId, $chatId, "❌ GenieACS belum terkonfigurasi", $telegram->getMainMenuKeyboard());
        return;
    }

    $deviceResult = $genieacs->getDevice($deviceId);
    if (!$deviceResult['success']) {
        $telegram->editMessage($messageId, $chatId, "❌ Device tidak ditemukan", $telegram->getMainMenuKeyboard());
        return;
    }

    $device = $genieacs->parseDeviceData($deviceResult['data']);
    $message = "📡 <b>Detail WiFi</b>\n\n";
    $message .= "Device: <code>" . telegramHtml($deviceId) . "</code>\n";
    $message .= "Customer: " . telegramHtml(telegramSnapshotValue($device['customer_name'] ?? null)) . "\n";
    $message .= "SSID: <b>" . telegramHtml(telegramSnapshotValue($device['wifi_ssid'] ?? null)) . "</b>\n";
    $message .= "Password: <i>disembunyikan untuk keamanan</i>\n";
    $message .= "Channel: " . telegramHtml(telegramSnapshotValue($device['wifi_channel'] ?? null)) . "\n";
    $message .= "Bandwidth: " . telegramHtml(telegramSnapshotValue($device['wifi_bandwidth'] ?? null)) . "\n";
    $message .= "Tx Power: " . telegramHtml(telegramSnapshotValue($device['wifi_tx_power'] ?? null)) . "\n";
    $message .= "Guest SSID: " . telegramHtml(telegramSnapshotValue($device['guest_ssid_state'] ?? null)) . "\n";
    $message .= "Client: " . telegramHtml(telegramSnapshotValue($device['connected_devices_count'] ?? $device['host_count'] ?? null)) . "\n\n";
    $message .= "Tombol ini hanya menampilkan data WiFi, tanpa mengubah ONT.";

    $telegram->editMessage($messageId, $chatId, $message, $telegram->getDeviceDetailKeyboard($deviceId));
}

function findTelegramDeviceByQuery(string $query, GenieACS $genieacs, mysqli $conn): array
{
    $needle = strtolower(trim($query));
    if ($needle === '') {
        return ['success' => false, 'message' => 'Query kosong'];
    }

    $direct = $genieacs->getDevice($query);
    if (!empty($direct['success']) && !empty($direct['data'])) {
        return [
            'success' => true,
            'device' => $genieacs->parseDeviceData($direct['data']),
        ];
    }

    $matches = [];
    $serialToDeviceId = [];
    $walk = $genieacs->walkDevices(function ($rawDevice) use (&$matches, &$serialToDeviceId, $needle) {
        $parsed = GenieACS_Fast::parseDeviceDataFast($rawDevice);
        $parsedDeviceId = $parsed['device_id'] ?? ($rawDevice['_id'] ?? null);
        $serialKey = strtolower((string) ($parsed['serial_number'] ?? ''));
        if ($serialKey !== '' && $serialKey !== 'n/a' && $parsedDeviceId) {
            $serialToDeviceId[$serialKey] = $parsedDeviceId;
        }

        $haystacks = [
            strtolower((string) ($parsedDeviceId ?? '')),
            strtolower((string) ($parsed['serial_number'] ?? '')),
            strtolower((string) ($parsed['mac_address'] ?? '')),
            strtolower((string) ($parsed['pppoe_username'] ?? '')),
            strtolower((string) ($parsed['wifi_ssid'] ?? '')),
            strtolower((string) ($parsed['ip_address'] ?? '')),
            strtolower((string) ($parsed['customer_name'] ?? '')),
        ];

        $score = 0;
        foreach ($haystacks as $field) {
            if ($field === '' || $field === 'n/a') {
                continue;
            }
            if ($field === $needle) {
                $score = max($score, 100);
            } elseif (str_contains($field, $needle)) {
                $score = max($score, 50);
            }
        }

        if ($score > 0) {
            $matches[] = [
                'score' => $score,
                'device_id' => $parsedDeviceId,
            ];
        }
    }, [], 40);

    if (!$walk['success']) {
        return ['success' => false, 'message' => 'Gagal mengambil data device dari GenieACS'];
    }

    if (empty($matches)) {
        $sql = "
            SELECT onu_config.genieacs_device_id
            FROM onu_config
            WHERE onu_config.genieacs_device_id LIKE ? OR onu_config.customer_name LIKE ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $like = '%' . $query . '%';
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!empty($row['genieacs_device_id'])) {
            $deviceId = $row['genieacs_device_id'];
            $mapped = $genieacs->getDevice($deviceId);
            if (!empty($mapped['success']) && !empty($mapped['data'])) {
                return [
                    'success' => true,
                    'device' => $genieacs->parseDeviceData($mapped['data']),
                ];
            }
        }

        $inventorySql = "
            SELECT inv.serial_number, inv.description, inv.pon_port, inv.status, olt.name AS olt_name
            FROM olt_onu_inventory inv
            INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
            WHERE inv.serial_number LIKE ? OR inv.description LIKE ? OR inv.pon_port LIKE ? OR olt.name LIKE ?
            ORDER BY inv.updated_at DESC, inv.id DESC
            LIMIT 5
        ";
        $stmtInv = $conn->prepare($inventorySql);
        $stmtInv->bind_param('ssss', $like, $like, $like, $like);
        $stmtInv->execute();
        $inventoryResult = $stmtInv->get_result();
        while ($inv = $inventoryResult->fetch_assoc()) {
            $serialKey = strtolower((string) ($inv['serial_number'] ?? ''));
            if ($serialKey !== '' && isset($serialToDeviceId[$serialKey])) {
                $mapped = $genieacs->getDevice($serialToDeviceId[$serialKey]);
                if (!empty($mapped['success']) && !empty($mapped['data'])) {
                    return [
                        'success' => true,
                        'device' => $genieacs->parseDeviceData($mapped['data']),
                    ];
                }
            }

            if ($serialKey !== '') {
                return [
                    'success' => false,
                    'message' => "Data OLT ditemukan ({$inv['olt_name']} / {$inv['serial_number']}), tapi ONT belum masuk ACS atau belum first-inform.",
                ];
            }
        }

        return ['success' => false, 'message' => "Device tidak ditemukan untuk query: {$query}"];
    }

    usort($matches, static function (array $left, array $right) {
        return $right['score'] <=> $left['score'];
    });

    $bestDeviceId = $matches[0]['device_id'] ?? null;
    if (!$bestDeviceId) {
        return ['success' => false, 'message' => 'Device cocok ditemukan, tetapi device_id tidak valid'];
    }

    $best = $genieacs->getDevice($bestDeviceId);
    if (empty($best['success']) || empty($best['data'])) {
        return ['success' => false, 'message' => 'Device cocok ditemukan, tetapi gagal mengambil detail'];
    }

    return [
        'success' => true,
        'device' => $genieacs->parseDeviceData($best['data']),
    ];
}

function buildTelegramDeviceSnapshot(array $device, mysqli $conn): string
{
    $inventory = getTelegramOltInventorySnapshot($conn, $device);

    $serial = strtoupper((string) ($device['serial_number'] ?? 'N/A'));
    $cwmpOnline = strtolower((string) ($device['status'] ?? 'offline')) === 'online';
    $cwmpStatus = $cwmpOnline ? 'ONLINE' : 'OFFLINE';
    $customerName = telegramSnapshotValue(
        $inventory['customer_name'] ?? null,
        $inventory['description'] ?? null,
        $device['customer_name'] ?? null,
        $device['ont_name'] ?? null
    );
    $fspId = telegramSnapshotValue(
        buildTelegramFspId($inventory),
        $inventory['pon_port'] ?? null
    );
    $ipTr069 = telegramSnapshotValue($device['ip_address'] ?? null, extractTelegramHost($device['ip_tr069'] ?? null));
    $rxTr069Value = $device['rx_power'] ?? null;
    $rxOltValue = telegramSnapshotValue($inventory['rx_power'] ?? null) !== 'N/A'
        ? ($inventory['rx_power'] ?? null)
        : $rxTr069Value;
    $rxOlt = telegramSnapshotMetric($rxOltValue, 'dBm');
    $lastInform = telegramSnapshotValue($device['last_inform'] ?? null);
    $deviceId = telegramSnapshotValue($device['device_id'] ?? null);

    $message = "<b>NETKING-ACS | Snapshot ONT</b>\n";
    $message .= "\n";
    $message .= "Status: <b>" . telegramHtml($cwmpStatus) . "</b>\n";
    $message .= "Customer: " . telegramHtml($customerName) . "\n";
    $message .= "SN: <code>" . telegramHtml($serial) . "</code>\n";
    $message .= "Device ID: <code>" . telegramHtml($deviceId) . "</code>\n";
    $message .= "OLT: " . telegramHtml(telegramSnapshotValue($inventory['olt_name'] ?? null, $device['olt_area'] ?? null)) . "\n";
    $message .= "PON/ONT: " . telegramHtml($fspId) . "\n";
    $message .= "RX: <b>" . telegramHtml($rxOlt) . "</b> (" . telegramHtml(classifyTelegramOpticalStatus($rxOltValue, false)) . ")\n";
    $message .= "IP TR069: <code>" . telegramHtml($ipTr069) . "</code>\n";
    $message .= "SSID: " . telegramHtml(telegramSnapshotValue($device['wifi_ssid'] ?? null)) . "\n";
    $message .= "Last Inform: " . telegramHtml($lastInform) . "\n";
    return $message;
}

function getTelegramOltInventorySnapshot(mysqli $conn, array $device): array
{
    $snapshot = [
        'olt_name' => null,
        'status' => null,
        'description' => null,
        'pon_port' => null,
        'ont_index' => null,
        'rx_power' => null,
        'tx_power' => null,
        'distance' => null,
        'last_down_cause' => null,
        'last_down_at' => null,
        'last_up_at' => null,
        'last_synced_at' => null,
        'customer_name' => null,
    ];

    $serial = strtoupper(trim((string) ($device['serial_number'] ?? '')));
    if ($serial !== '' && $serial !== 'N/A') {
        $sql = "
            SELECT
                inv.serial_number,
                inv.pon_port,
                inv.ont_index,
                inv.description,
                inv.status,
                inv.rx_power,
                inv.tx_power,
                inv.distance,
                inv.last_synced_at,
                olt.name AS olt_name
            FROM olt_onu_inventory inv
            INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
            WHERE inv.serial_number = ?
            ORDER BY inv.updated_at DESC, inv.id DESC
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $serial);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $snapshot = array_merge($snapshot, $row);
        }

        $sqlMap = "
            SELECT
                onu_config.customer_name,
                onu_config.odp_port,
                onu.name AS onu_name,
                odp.name AS odp_name,
                odc.name AS odc_name,
                olt.name AS map_olt_name
            FROM onu_config
            INNER JOIN map_items onu ON onu.id = onu_config.map_item_id AND onu.item_type = 'onu'
            LEFT JOIN map_items odp ON odp.id = onu.parent_id AND odp.item_type = 'odp'
            LEFT JOIN map_items odc ON odc.id = odp.parent_id AND odc.item_type = 'odc'
            LEFT JOIN map_items olt ON olt.id = odc.parent_id AND olt.item_type = 'olt'
            WHERE onu_config.genieacs_device_id LIKE ?
            LIMIT 1
        ";
        $like = '%' . $serial . '%';
        $stmtMap = $conn->prepare($sqlMap);
        $stmtMap->bind_param('s', $like);
        $stmtMap->execute();
        $mapRow = $stmtMap->get_result()->fetch_assoc();
        if ($mapRow) {
            if (!empty($mapRow['customer_name'])) {
                $snapshot['customer_name'] = $mapRow['customer_name'];
            }
            if (empty($snapshot['description']) && !empty($mapRow['onu_name'])) {
                $snapshot['description'] = $mapRow['onu_name'];
            }
            if (empty($snapshot['olt_name']) && !empty($mapRow['map_olt_name'])) {
                $snapshot['olt_name'] = $mapRow['map_olt_name'];
            }
            if (empty($snapshot['ont_index']) && !empty($mapRow['odp_port'])) {
                $snapshot['ont_index'] = $mapRow['odp_port'];
            }
        }
    }

    return $snapshot;
}

function buildTelegramFirstInformGap(GenieACS $genieacs, mysqli $conn, string $oltFilter = ''): array
{
    $acsIndex = buildTelegramAcsSerialIndex($genieacs);
    if (!$acsIndex['success']) {
        return $acsIndex;
    }

    $sql = "
        SELECT inv.serial_number, inv.description, inv.pon_port, inv.ont_index, inv.status, olt.id AS olt_id, olt.name AS olt_name
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
        WHERE LOWER(inv.status) = 'online'
    ";
    $params = [];
    $types = '';
    if ($oltFilter !== '') {
        $sql .= " AND (olt.name LIKE ? OR inv.serial_number LIKE ? OR inv.description LIKE ?)";
        $like = '%' . $oltFilter . '%';
        $params = [$like, $like, $like];
        $types = 'sss';
    }
    $sql .= " ORDER BY olt.name ASC, inv.pon_port ASC, inv.ont_index ASC, inv.serial_number ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Query inventory OLT gagal disiapkan: ' . $conn->error];
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $summary = [];
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $oltId = (int) $row['olt_id'];
        if (!isset($summary[$oltId])) {
            $summary[$oltId] = [
                'olt_name' => $row['olt_name'],
                'online' => 0,
                'missing' => 0,
            ];
        }
        $summary[$oltId]['online']++;

        $serial = strtolower(trim((string) ($row['serial_number'] ?? '')));
        if ($serial !== '' && isset($acsIndex['serials'][$serial])) {
            continue;
        }

        $summary[$oltId]['missing']++;
        $items[] = $row;
    }

    $summary = array_values(array_filter($summary, static fn($row) => (int) $row['missing'] > 0));
    usort($summary, static fn($a, $b) => ((int) $b['missing']) <=> ((int) $a['missing']));

    return [
        'success' => true,
        'summary' => $summary,
        'items' => $items,
        'total_missing' => count($items),
    ];
}

function buildTelegramOltSummary(GenieACS $genieacs, mysqli $conn, string $oltFilter = ''): array
{
    $acsIndex = buildTelegramAcsSerialIndex($genieacs);
    if (!$acsIndex['success']) {
        return $acsIndex;
    }

    $where = "WHERE olt.item_type = 'olt'";
    $params = [];
    $types = '';
    if ($oltFilter !== '') {
        $where .= " AND olt.name LIKE ?";
        $params[] = '%' . $oltFilter . '%';
        $types .= 's';
    }

    $sql = "
        SELECT
            olt.id AS olt_id,
            olt.name AS olt_name,
            COUNT(inv.id) AS total,
            SUM(CASE WHEN LOWER(inv.status) = 'online' THEN 1 ELSE 0 END) AS online,
            SUM(CASE WHEN LOWER(inv.status) = 'offline' THEN 1 ELSE 0 END) AS offline,
            SUM(CASE WHEN inv.rx_power <= -28 THEN 1 ELSE 0 END) AS critical_rx,
            SUM(CASE WHEN inv.rx_power <= -25 AND inv.rx_power > -28 THEN 1 ELSE 0 END) AS warning_rx,
            SUM(CASE WHEN inv.rx_power IS NULL THEN 1 ELSE 0 END) AS missing_rx,
            MAX(inv.last_synced_at) AS last_sync
        FROM map_items olt
        LEFT JOIN olt_onu_inventory inv ON inv.olt_item_id = olt.id
        {$where}
        GROUP BY olt.id, olt.name
        ORDER BY olt.name ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Query ringkasan OLT gagal disiapkan: ' . $conn->error];
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[(int) $row['olt_id']] = [
            'olt_name' => $row['olt_name'],
            'total' => (int) $row['total'],
            'online' => (int) $row['online'],
            'offline' => (int) $row['offline'],
            'critical_rx' => (int) $row['critical_rx'],
            'warning_rx' => (int) $row['warning_rx'],
            'missing_rx' => (int) $row['missing_rx'],
            'last_sync' => $row['last_sync'],
            'in_acs' => 0,
            'missing_acs' => 0,
            'online_missing_acs' => 0,
        ];
    }

    if (empty($items)) {
        return ['success' => true, 'items' => []];
    }

    $inventorySql = "
        SELECT inv.olt_item_id, inv.serial_number, inv.status
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
        {$where}
    ";
    $stmtInv = $conn->prepare($inventorySql);
    if ($params) {
        $stmtInv->bind_param($types, ...$params);
    }
    $stmtInv->execute();
    $inventoryResult = $stmtInv->get_result();

    while ($row = $inventoryResult->fetch_assoc()) {
        $oltId = (int) $row['olt_item_id'];
        if (!isset($items[$oltId])) {
            continue;
        }
        $serial = strtolower(trim((string) ($row['serial_number'] ?? '')));
        $existsInAcs = $serial !== '' && isset($acsIndex['serials'][$serial]);
        if ($existsInAcs) {
            $items[$oltId]['in_acs']++;
        } else {
            $items[$oltId]['missing_acs']++;
            if (strtolower((string) ($row['status'] ?? '')) === 'online') {
                $items[$oltId]['online_missing_acs']++;
            }
        }
    }

    return ['success' => true, 'items' => array_values($items)];
}

function buildTelegramAcsSerialIndex(GenieACS $genieacs): array
{
    $serials = [];
    $total = 0;
    $walk = $genieacs->walkDevices(function ($rawDevice) use (&$serials, &$total) {
        $total++;
        $parsed = GenieACS_Fast::parseDeviceDataFast($rawDevice);
        $serial = strtolower(trim((string) ($parsed['serial_number'] ?? '')));
        if ($serial !== '' && $serial !== 'n/a') {
            $serials[$serial] = true;
        }
    }, [], 40);

    if (!$walk['success']) {
        return ['success' => false, 'message' => 'Gagal mengambil daftar device dari GenieACS'];
    }

    return ['success' => true, 'serials' => $serials, 'total' => $total];
}

function buildTelegramOfflineConfirmedSnapshot(mysqli $conn, string $filter = ''): array
{
    $states = getTelegramMonitorStates($conn, 'telegram_status_alert_');
    $items = [];
    $pending = 0;

    foreach ($states as $state) {
        if (!telegramAlarmStateMatchesFilter($state, $filter)) {
            continue;
        }
        if (($state['pending_status'] ?? null) === 'offline') {
            $pending++;
        }
        if (($state['alerted_status'] ?? null) !== 'offline') {
            continue;
        }
        $items[] = $state;
    }

    usort($items, static function ($a, $b) {
        return strcmp((string) ($b['last_notified_at'] ?? $b['updated_at'] ?? ''), (string) ($a['last_notified_at'] ?? $a['updated_at'] ?? ''));
    });

    return ['items' => $items, 'pending' => $pending];
}

function buildTelegramFlappingSnapshot(mysqli $conn, string $filter = ''): array
{
    $states = getTelegramMonitorStates($conn, 'telegram_flap_alert_');
    $items = array_values(array_filter($states, static fn($state) => telegramAlarmStateMatchesFilter($state, $filter)));
    usort($items, static function ($a, $b) {
        return ((int) ($b['change_count_1h'] ?? 0)) <=> ((int) ($a['change_count_1h'] ?? 0));
    });
    return ['items' => $items];
}

function buildTelegramMassdropSnapshot(mysqli $conn, string $filter = ''): array
{
    $states = getTelegramMonitorStates($conn, 'telegram_massdrop_alert_');
    $items = array_values(array_filter($states, static fn($state) => telegramAlarmStateMatchesFilter($state, $filter)));
    usort($items, static function ($a, $b) {
        return ((int) ($b['offline_confirmed_count'] ?? 0)) <=> ((int) ($a['offline_confirmed_count'] ?? 0));
    });
    return ['items' => $items];
}

function buildTelegramOpticalAlarmSnapshot(mysqli $conn, string $filter = ''): array
{
    $where = "WHERE inv.rx_power <= -25";
    $params = [];
    $types = '';
    $missingWhere = "WHERE inv.rx_power IS NULL";
    if ($filter !== '') {
        $where .= " AND (olt.name LIKE ? OR inv.serial_number LIKE ? OR inv.description LIKE ?)";
        $missingWhere .= " AND (olt.name LIKE ? OR inv.serial_number LIKE ? OR inv.description LIKE ?)";
        $like = '%' . $filter . '%';
        $params = [$like, $like, $like];
        $types = 'sss';
    }

    $sql = "
        SELECT inv.serial_number, inv.description, inv.pon_port, inv.ont_index, inv.rx_power, olt.name AS olt_name
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
        {$where}
        ORDER BY inv.rx_power ASC, olt.name ASC
        LIMIT 50
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['items' => [], 'critical_count' => 0, 'warning_count' => 0, 'olt_summary' => [], 'missing_count' => 0, 'missing_items' => []];
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    $critical = 0;
    $warning = 0;
    $oltSummary = [];
    while ($row = $result->fetch_assoc()) {
        $rx = (float) $row['rx_power'];
        $oltName = (string) ($row['olt_name'] ?? 'Tanpa OLT');
        if (!isset($oltSummary[$oltName])) {
            $oltSummary[$oltName] = [
                'olt_name' => $oltName,
                'critical_count' => 0,
                'warning_count' => 0,
                'worst_rx' => $rx,
            ];
        }
        if ($rx <= -28) {
            $critical++;
            $oltSummary[$oltName]['critical_count']++;
        } else {
            $warning++;
            $oltSummary[$oltName]['warning_count']++;
        }
        if ($rx < (float) $oltSummary[$oltName]['worst_rx']) {
            $oltSummary[$oltName]['worst_rx'] = $rx;
        }
        $items[] = $row;
    }

    usort($oltSummary, static function ($a, $b) {
        $scoreA = ($a['critical_count'] * 1000) + ($a['warning_count'] * 100) + abs((float) $a['worst_rx']);
        $scoreB = ($b['critical_count'] * 1000) + ($b['warning_count'] * 100) + abs((float) $b['worst_rx']);
        return $scoreB <=> $scoreA;
    });

    $missingCount = 0;
    $missingItems = [];
    $missingCountSql = "
        SELECT COUNT(*) AS total_missing
        FROM olt_onu_inventory inv
        INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
        {$missingWhere}
    ";
    $missingCountStmt = $conn->prepare($missingCountSql);
    if ($missingCountStmt) {
        if ($params) {
            $missingCountStmt->bind_param($types, ...$params);
        }
        $missingCountStmt->execute();
        $missingCountResult = $missingCountStmt->get_result();
        if ($missingCountRow = $missingCountResult->fetch_assoc()) {
            $missingCount = (int) ($missingCountRow['total_missing'] ?? 0);
        }
    }

    if ($missingCount > 0) {
        $missingSql = "
            SELECT inv.serial_number, inv.description, inv.pon_port, inv.ont_index, olt.name AS olt_name
            FROM olt_onu_inventory inv
            INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
            {$missingWhere}
            ORDER BY olt.name ASC, inv.pon_port ASC, inv.ont_index ASC
            LIMIT 20
        ";
        $missingStmt = $conn->prepare($missingSql);
        if ($missingStmt) {
            if ($params) {
                $missingStmt->bind_param($types, ...$params);
            }
            $missingStmt->execute();
            $missingResult = $missingStmt->get_result();
            while ($row = $missingResult->fetch_assoc()) {
                $missingItems[] = $row;
            }
        }
    }

    return [
        'items' => $items,
        'critical_count' => $critical,
        'warning_count' => $warning,
        'olt_summary' => $oltSummary,
        'missing_count' => $missingCount,
        'missing_items' => $missingItems,
    ];
}

function getTelegramMonitorStates(mysqli $conn, string $prefix): array
{
    $like = $prefix . '%';
    $stmt = $conn->prepare("SELECT config_key, config_value, updated_at FROM configurations WHERE config_key LIKE ? ORDER BY updated_at DESC LIMIT 1000");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $states = [];
    while ($row = $result->fetch_assoc()) {
        $decoded = json_decode((string) ($row['config_value'] ?? ''), true);
        if (!is_array($decoded)) {
            continue;
        }
        $decoded['config_key'] = $row['config_key'];
        $decoded['config_updated_at'] = $row['updated_at'];
        $states[] = $decoded;
    }

    return $states;
}

function telegramSetJsonConfig(mysqli $conn, string $key, array $payload): void
{
    $value = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare("
        INSERT INTO configurations (config_key, config_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
    ");
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

function telegramNormalizeArea(string $area): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $area) ?? $area));
}

function telegramParseDurationSeconds(string $duration): int
{
    $duration = strtolower(trim($duration));
    if (!preg_match('/^(\d+)\s*([mhd])$/', $duration, $matches)) {
        return 0;
    }

    $value = max(1, (int) $matches[1]);
    return match ($matches[2]) {
        'm' => min($value, 1440) * 60,
        'h' => min($value, 72) * 3600,
        'd' => min($value, 7) * 86400,
        default => 0,
    };
}

function telegramGetActiveMutes(mysqli $conn): array
{
    $states = getTelegramMonitorStates($conn, 'telegram_area_mute_');
    $active = [];
    foreach ($states as $state) {
        $until = strtotime((string) ($state['muted_until'] ?? ''));
        if ($until <= time()) {
            continue;
        }
        $active[] = $state;
    }

    usort($active, static function ($a, $b) {
        return strcmp((string) ($a['muted_until'] ?? ''), (string) ($b['muted_until'] ?? ''));
    });

    return $active;
}

function telegramAlarmStateMatchesFilter(array $state, string $filter): bool
{
    $filter = strtolower(trim($filter));
    if ($filter === '') {
        return true;
    }

    $fields = [
        $state['olt_name'] ?? '',
        $state['device_id'] ?? '',
        $state['serial_number'] ?? '',
        $state['customer_name'] ?? '',
        $state['ont_name'] ?? '',
        $state['pon_port'] ?? '',
        $state['ont_index'] ?? '',
    ];

    foreach ($fields as $field) {
        if ($field !== null && str_contains(strtolower((string) $field), $filter)) {
            return true;
        }
    }

    return false;
}

function telegramFormatAlarmDeviceLine(array $state): string
{
    $name = telegramSnapshotValue($state['customer_name'] ?? null, $state['ont_name'] ?? null, $state['serial_number'] ?? null, $state['device_id'] ?? null);
    $olt = telegramSnapshotValue($state['olt_name'] ?? null);
    $serial = telegramSnapshotValue($state['serial_number'] ?? null);
    $pon = telegramSnapshotValue(buildTelegramFspId([
        'pon_port' => $state['pon_port'] ?? null,
        'ont_index' => $state['ont_index'] ?? null,
    ]));
    $time = telegramSnapshotValue($state['last_notified_at'] ?? $state['last_alert_at'] ?? $state['updated_at'] ?? $state['config_updated_at'] ?? null);

    return telegramHtml($olt) . " | " . telegramHtml($name) . " | <code>" . telegramHtml($serial) . "</code> | PON " . telegramHtml($pon) . " | " . telegramHtml($time);
}

function buildTelegramSummonFeedback(string $deviceId, array $result): string
{
    $safeDeviceId = telegramHtml($deviceId);
    $httpCode = (int) ($result['http_code'] ?? 0);

    if (!empty($result['success'])) {
        $status = $httpCode === 202
            ? 'Berhasil queued'
            : ($httpCode === 200 ? 'Berhasil diproses langsung' : 'Diterima GenieACS');

        return "✅ <b>{$status}</b>\n\nDevice: <code>{$safeDeviceId}</code>\nHTTP: <code>{$httpCode}</code>\n\nAksi read-only: connection request + refreshObject. Tidak ada reboot, tidak ubah WiFi/WAN/password ONT.";
    }

    $rawError = (string) ($result['error'] ?? ('HTTP ' . ($result['http_code'] ?? 'unknown')));
    $lowerError = strtolower($rawError);
    if (str_contains($lowerError, 'timeout')) {
        $status = 'Connection request timeout';
    } elseif ($httpCode === 404) {
        $status = 'Device tidak ditemukan';
    } elseif (in_array($httpCode, [500, 502, 503, 504, 599], true)) {
        $status = 'ONT tidak reachable / NBI timeout';
    } else {
        $status = 'Gagal queued';
    }

    return "❌ <b>{$status}</b>\n\nDevice: <code>{$safeDeviceId}</code>\nAlasan: <code>" . telegramHtml($rawError) . "</code>\n\nIni tidak mengubah konfigurasi ONT pelanggan.";
}

function telegramTaskLabel(array $task): string
{
    $name = $task['name'] ?? $task['_id'] ?? 'task';
    $device = $task['device'] ?? $task['deviceId'] ?? '-';
    $time = $task['timestamp'] ?? $task['created'] ?? $task['createdAt'] ?? '';
    return trim((string) $name . ' | ' . (string) $device . ($time ? ' | ' . (string) $time : ''));
}

function telegramFaultLabel(array $fault): string
{
    $code = $fault['faultCode'] ?? $fault['code'] ?? 'fault';
    $message = $fault['message'] ?? $fault['faultString'] ?? $fault['detail'] ?? '';
    $device = $fault['device'] ?? $fault['deviceId'] ?? '-';
    return trim((string) $code . ' | ' . (string) $device . ($message ? ' | ' . (string) $message : ''));
}

function telegramSendOrEdit($telegram, $chatId, string $message, $keyboard = null, $messageId = null): void
{
    if ($messageId) {
        $telegram->editMessage($messageId, $chatId, $message, $keyboard);
    } else {
        $telegram->sendMessage($message, $chatId, $keyboard);
    }
}

function telegramHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function telegramRenderSnapshotBlock(string $title, array $lines): string
{
    $border = str_repeat('#', 26);
    $output = $border . "\n";
    $output .= '# ' . telegramEscapePre($title) . "\n";
    $output .= $border . "\n";

    foreach ($lines as $label => $value) {
        if ($label === '') {
            $output .= "\n";
            continue;
        }

        $safeLabel = telegramEscapePre($label);
        $safeValue = telegramEscapePre($value);
        if ($safeValue === 'N/A') {
            continue;
        }
        $output .= $safeLabel . ': ' . $safeValue . "\n";
    }

    return rtrim($output);
}

function telegramEscapePre($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function telegramSnapshotValue(...$values): string
{
    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }
        $text = trim((string) $value);
        if ($text === '' || strtoupper($text) === 'N/A') {
            continue;
        }
        return $text;
    }

    return 'N/A';
}

function telegramSnapshotMetric($value, string $suffix): string
{
    $text = telegramSnapshotValue($value);
    if ($text === 'N/A') {
        return $text;
    }

    return rtrim($text) . ' ' . $suffix;
}

function buildTelegramFspId(array $inventory): ?string
{
    $ponPort = trim((string) ($inventory['pon_port'] ?? ''));
    $ontIndex = trim((string) ($inventory['ont_index'] ?? ''));
    if ($ponPort === '' && $ontIndex === '') {
        return null;
    }

    if ($ponPort === '') {
        return $ontIndex;
    }

    return $ontIndex !== '' ? $ponPort . '-' . $ontIndex : $ponPort;
}

function classifyTelegramOpticalStatus($rxPower, bool $withIcon = true): string
{
    if ($rxPower === null || $rxPower === '' || strtoupper((string) $rxPower) === 'N/A') {
        return $withIcon ? '⚪ Tidak ada data' : 'Tidak ada data';
    }

    $value = (float) $rxPower;
    if ($value <= -28) {
        return $withIcon ? '🔴 Kritis' : 'Kritis';
    }
    if ($value <= -25) {
        return $withIcon ? '🟠 Warning' : 'Warning';
    }

    return $withIcon ? '🟢 Normal' : 'Normal';
}

function extractTelegramHost($value): ?string
{
    $text = trim((string) $value);
    if ($text === '' || strtoupper($text) === 'N/A') {
        return null;
    }

    if (preg_match('/https?:\/\/([^:\/]+)/i', $text, $matches)) {
        return $matches[1];
    }

    return $text;
}

function handleSubscribeAction($deviceId, $chatId, $messageId, $telegram, $conn) {
    // Check if already subscribed
    $stmt = $conn->prepare("SELECT id FROM telegram_subscriptions WHERE chat_id = ? AND device_id = ? AND is_active = 1");
    $stmt->bind_param("ss", $chatId, $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $telegram->answerCallbackQuery($messageId, "Already subscribed to this device", true);
        return;
    }

    // Add subscription
    $stmt = $conn->prepare("INSERT INTO telegram_subscriptions (chat_id, device_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_active = 1");
    $stmt->bind_param("ss", $chatId, $deviceId);

    if ($stmt->execute()) {
        showDeviceDetail($deviceId, $chatId, $messageId, $telegram, null);
        $telegram->sendMessage("✅ Successfully subscribed to <code>{$deviceId}</code>", $chatId);
    }
}

function startWiFiEditSession($deviceId, $chatId, $messageId, $telegram, $conn) {
    global $permissionManager;

    if (function_exists('isCpeWriteEnabled') && !isCpeWriteEnabled()) {
        $telegram->editMessage(
            $messageId,
            $chatId,
            "🔒 <b>Aksi WiFi dikunci</b>\n\nUbah WiFi dari Telegram sedang dinonaktifkan agar tidak ada perubahan ke ONT pelanggan. Tombol Panggil tetap aman karena hanya refresh monitoring.",
            $telegram->getMainMenuKeyboard()
        );
        return;
    }

    // Check permission
    if (!$permissionManager->hasPermission($chatId, \App\PermissionManager::DEVICE_EDIT_WIFI)) {
        $telegram->editMessage($messageId, $chatId, $permissionManager->getDenialMessage(\App\PermissionManager::DEVICE_EDIT_WIFI));
        return;
    }

    // Create session
    $sessionData = json_encode(['device_id' => $deviceId, 'step' => 'ssid']);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    $stmt = $conn->prepare("INSERT INTO telegram_user_sessions (chat_id, session_type, session_data, current_step, expires_at) VALUES (?, 'editwifi', ?, 'ssid', ?) ON DUPLICATE KEY UPDATE session_data = ?, current_step = 'ssid', expires_at = ?");
    $stmt->bind_param("sssss", $chatId, $sessionData, $expiresAt, $sessionData, $expiresAt);
    $stmt->execute();

    $message = "📶 <b>Edit WiFi Configuration</b>\n\n";
    $message .= "Device ID: <code>{$deviceId}</code>\n\n";
    $message .= "Please enter new WiFi SSID:\n";
    $message .= "(or send /cancel to cancel)";

    $telegram->editMessage($messageId, $chatId, $message);
}

function showDeviceLocation($deviceId, $chatId, $messageId, $telegram, $conn) {
    // Get ONU location from database using serial number
    // First, we need to extract serial number from device_id
    $parts = explode('-', $deviceId);
    $serialNumber = end($parts);

    // Query to get ONU location through the hierarchy
    $query = "
        SELECT
            onu.id as onu_id,
            onu.name as onu_name,
            onu.latitude as onu_lat,
            onu.longitude as onu_lng,
            odp.name as odp_name,
            odc.name as odc_name,
            olt.name as olt_name,
            onu_config.odp_port
        FROM map_items onu
        LEFT JOIN onu_config ON onu.id = onu_config.onu_id
        LEFT JOIN map_items odp ON onu.parent_id = odp.id
        LEFT JOIN map_items odc ON odp.parent_id = odc.id
        LEFT JOIN map_items olt ON odc.parent_id = olt.id
        WHERE onu.item_type = 'onu'
        AND (onu_config.genieacs_device_id = ? OR onu.name LIKE ?)
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $serialLike = "%{$serialNumber}%";
    $stmt->bind_param("ss", $deviceId, $serialLike);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $message = "🗺️ <b>Device Location</b>\n\n";
        $message .= "❌ This device is not mapped in the network topology.\n\n";
        $message .= "To view location:\n";
        $message .= "1. Add device to Network Map in dashboard\n";
        $message .= "2. Assign GPS coordinates\n";
        $message .= "3. Link to ODP/ODC/OLT hierarchy";

        $telegram->editMessage($messageId, $chatId, $message, $telegram->getDeviceDetailKeyboard($deviceId));
        return;
    }

    $location = $result->fetch_assoc();

    // Check if GPS coordinates are available
    if (empty($location['onu_lat']) || empty($location['onu_lng']) ||
        $location['onu_lat'] == 0 || $location['onu_lng'] == 0) {
        $message = "🗺️ <b>Device Location</b>\n\n";
        $message .= "📍 <b>Topology Location:</b>\n";
        $message .= "ONU: {$location['onu_name']}\n";
        if ($location['odp_name']) $message .= "↳ ODP: {$location['odp_name']} (Port {$location['odp_port']})\n";
        if ($location['odc_name']) $message .= "  ↳ ODC: {$location['odc_name']}\n";
        if ($location['olt_name']) $message .= "    ↳ OLT: {$location['olt_name']}\n\n";
        $message .= "❌ GPS coordinates not set.\n\n";
        $message .= "Please set coordinates in Network Map.";

        $telegram->editMessage($messageId, $chatId, $message, $telegram->getDeviceDetailKeyboard($deviceId));
        return;
    }

    // Format coordinates (6 decimal places)
    $lat = number_format($location['onu_lat'], 6, '.', '');
    $lng = number_format($location['onu_lng'], 6, '.', '');

    // Build message with location info
    $message = "🗺️ <b>Device Location</b>\n\n";
    $message .= "📍 <b>GPS Coordinates:</b>\n";
    $message .= "Latitude: <code>{$lat}</code>\n";
    $message .= "Longitude: <code>{$lng}</code>\n\n";

    $message .= "🔗 <b>Topology Path:</b>\n";
    $message .= "ONU: {$location['onu_name']}\n";
    if ($location['odp_name']) $message .= "↳ ODP: {$location['odp_name']} (Port {$location['odp_port']})\n";
    if ($location['odc_name']) $message .= "  ↳ ODC: {$location['odc_name']}\n";
    if ($location['olt_name']) $message .= "    ↳ OLT: {$location['olt_name']}\n\n";

    // Create Google Maps URL
    $googleMapsUrl = "https://www.google.com/maps?q={$lat},{$lng}";
    $message .= "🌐 <a href=\"{$googleMapsUrl}\">Open in Google Maps</a>\n";

    // Create Network Map URL
    global $config;
    $appUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
    $networkMapUrl = "{$appUrl}/map.php?focus_type=onu&focus_id={$location['onu_id']}";
    $message .= "🗺️ <a href=\"{$networkMapUrl}\">View on Network Map</a>";

    // Create keyboard with location sharing option
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📍 Send GPS Location', 'callback_data' => "action_sendgps_{$deviceId}"]
            ],
            [
                ['text' => '🔙 Back to Device', 'callback_data' => "device_detail_{$deviceId}"]
            ]
        ]
    ];

    $telegram->editMessage($messageId, $chatId, $message, $keyboard);
}

function sendDeviceGPSLocation($deviceId, $chatId, $telegram, $conn) {
    // Extract serial number from device_id
    $parts = explode('-', $deviceId);
    $serialNumber = end($parts);

    // Query to get GPS coordinates
    $query = "
        SELECT
            onu.id as onu_id,
            onu.name as onu_name,
            onu.latitude as onu_lat,
            onu.longitude as onu_lng
        FROM map_items onu
        LEFT JOIN onu_config ON onu.id = onu_config.onu_id
        WHERE onu.item_type = 'onu'
        AND (onu_config.genieacs_device_id = ? OR onu.name LIKE ?)
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $serialLike = "%{$serialNumber}%";
    $stmt->bind_param("ss", $deviceId, $serialLike);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $telegram->sendMessage("❌ Device location not found in map database.", $chatId);
        return;
    }

    $location = $result->fetch_assoc();

    // Check if GPS coordinates are valid
    if (empty($location['onu_lat']) || empty($location['onu_lng']) ||
        $location['onu_lat'] == 0 || $location['onu_lng'] == 0) {
        $telegram->sendMessage("❌ GPS coordinates not set for this device.", $chatId);
        return;
    }

    // Send GPS location
    $lat = floatval($location['onu_lat']);
    $lng = floatval($location['onu_lng']);

    $result = $telegram->sendLocation($lat, $lng, $chatId);

    if (isset($result['ok']) && $result['ok']) {
        // Send success message
        $telegram->sendMessage("✅ GPS location sent!\n\nDevice: <code>{$location['onu_name']}</code>", $chatId);
    } else {
        $telegram->sendMessage("❌ Failed to send GPS location.", $chatId);
    }
}

function getUserSession($chatId, $conn) {
    $stmt = $conn->prepare("SELECT * FROM telegram_user_sessions WHERE chat_id = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

function handleSessionMessage($session, $text, $chatId, $telegram, $genieacs, $conn) {
    if ($text === '/cancel') {
        clearUserSession($chatId, $conn);
        $telegram->sendMessage("❌ Operation cancelled", $chatId, $telegram->getMainMenuKeyboard());
        return;
    }

    $sessionType = $session['session_type'];

    if ($sessionType === 'editwifi') {
        handleWiFiEditSession($session, $text, $chatId, $telegram, $genieacs, $conn);
    }
}

function handleWiFiEditSession($session, $text, $chatId, $telegram, $genieacs, $conn) {
    $sessionData = json_decode($session['session_data'], true);
    $deviceId = $sessionData['device_id'];
    $currentStep = $session['current_step'];

    if (function_exists('isCpeWriteEnabled') && !isCpeWriteEnabled()) {
        clearUserSession($chatId, $conn);
        $telegram->sendMessage(
            "🔒 <b>Sesi ubah WiFi dibatalkan</b>\n\nPerubahan WiFi dari Telegram tetap dinonaktifkan agar tidak ada perubahan ke ONT pelanggan.",
            $chatId,
            $telegram->getMainMenuKeyboard()
        );
        return;
    }

    if ($currentStep === 'ssid') {
        // Validate SSID
        if (strlen($text) < 1 || strlen($text) > 32) {
            $telegram->sendMessage("❌ SSID must be between 1-32 characters. Please try again:", $chatId);
            return;
        }

        // Save SSID and ask for password
        $sessionData['ssid'] = $text;
        updateSessionStep($chatId, 'editwifi', $sessionData, 'password', $conn);

        $message = "✅ SSID: <code>{$text}</code>\n\n";
        $message .= "Now enter WiFi password:\n";
        $message .= "(8-63 characters, or send /skip for open network)";
        $telegram->sendMessage($message, $chatId);
    } elseif ($currentStep === 'password') {
        $password = $text;

        // Validate password
        if ($text !== '/skip' && (strlen($text) < 8 || strlen($text) > 63)) {
            $telegram->sendMessage("❌ Password must be between 8-63 characters. Please try again:\n(or send /skip for open network)", $chatId);
            return;
        }

        $securityMode = $text === '/skip' ? 'None' : 'WPA2PSK';
        $ssid = $sessionData['ssid'];

        // Update WiFi via GenieACS
        if ($genieacs) {
            $result = $genieacs->setWiFiConfig($deviceId, $ssid, $password === '/skip' ? '' : $password, 1, $securityMode);

            if ($result['success']) {
                $httpCode = $result['http_code'] ?? 0;

                if ($httpCode === 200) {
                    $telegram->sendMessage("✅ WiFi configuration updated successfully!\n\nSSID: <code>{$ssid}</code>\nSecurity: {$securityMode}", $chatId, $telegram->getMainMenuKeyboard());
                } elseif ($httpCode === 202) {
                    $telegram->sendMessage("✅ WiFi configuration task queued!\n\nSSID: <code>{$ssid}</code>\nSecurity: {$securityMode}\n\nDevice will update on next inform cycle.", $chatId, $telegram->getMainMenuKeyboard());
                } else {
                    $telegram->sendMessage("✅ WiFi configuration task sent to device.\n\nSSID: <code>{$ssid}</code>\nSecurity: {$securityMode}", $chatId, $telegram->getMainMenuKeyboard());
                }
            } else {
                $telegram->sendMessage("❌ Failed to update WiFi configuration", $chatId, $telegram->getMainMenuKeyboard());
            }
        } else {
            $telegram->sendMessage("❌ GenieACS not configured", $chatId);
        }

        // Clear session
        clearUserSession($chatId, $conn);
    }
}

function updateSessionStep($chatId, $sessionType, $sessionData, $newStep, $conn) {
    $sessionDataJson = json_encode($sessionData);
    $stmt = $conn->prepare("UPDATE telegram_user_sessions SET session_data = ?, current_step = ? WHERE chat_id = ? AND session_type = ?");
    $stmt->bind_param("ssss", $sessionDataJson, $newStep, $chatId, $sessionType);
    $stmt->execute();
}

function clearUserSession($chatId, $conn) {
    $stmt = $conn->prepare("DELETE FROM telegram_user_sessions WHERE chat_id = ?");
    $stmt->bind_param("s", $chatId);
    $stmt->execute();
}

// ====================
// Report Command Handlers
// ====================
function handleReportCommand($command, $chatId, $telegram, $genieacs, $conn) {
    if (!$genieacs) {
        $telegram->sendMessage("❌ GenieACS not configured", $chatId);
        return;
    }

    $reportType = $command['report_type'];
    $reportGen = new ReportGenerator($conn, $genieacs);

    $telegram->sendMessage("⏳ Generating {$reportType} report...", $chatId);

    try {
        if ($reportType === 'daily') {
            $report = $reportGen->generateDailyReport();
        } else {
            $report = $reportGen->generateWeeklyReport();
        }

        $message = $reportGen->formatReportMessage($report);
        $telegram->sendMessage($message, $chatId);

        // Log the report
        $reportGen->logReport($chatId, $report);

    } catch (Exception $e) {
        $telegram->sendMessage("❌ Failed to generate report: " . $e->getMessage(), $chatId);
    }
}

function handleScheduleListCommand($chatId, $telegram, $conn) {
    $stmt = $conn->prepare("
        SELECT report_type, schedule_time, schedule_day, is_active, last_sent_at
        FROM telegram_report_schedules
        WHERE chat_id = ?
        ORDER BY report_type
    ");
    $stmt->bind_param("s", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }

    if (count($schedules) === 0) {
        $message = "📅 <b>Scheduled Reports</b>\n\n";
        $message .= "You have no scheduled reports.\n\n";
        $message .= "Use /schedule to create one:\n";
        $message .= "• /schedule daily 08:00\n";
        $message .= "• /schedule weekly monday 09:00";

        $telegram->sendMessage($message, $chatId);
        return;
    }

    $message = "📅 <b>Scheduled Reports</b>\n\n";

    foreach ($schedules as $schedule) {
        $status = $schedule['is_active'] ? '✅ Active' : '❌ Disabled';
        $type = ucfirst($schedule['report_type']);

        $message .= "<b>{$type} Report</b> - {$status}\n";
        $message .= "Time: {$schedule['schedule_time']}\n";

        if ($schedule['report_type'] === 'weekly') {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $dayName = $days[$schedule['schedule_day']] ?? 'Unknown';
            $message .= "Day: {$dayName}\n";
        }

        if ($schedule['last_sent_at']) {
            $lastSent = date('M j, Y H:i', strtotime($schedule['last_sent_at']));
            $message .= "Last sent: {$lastSent}\n";
        } else {
            $message .= "Last sent: Never\n";
        }

        $message .= "\n";
    }

    $message .= "💡 Use /schedule disable &lt;type&gt; to disable a schedule";

    $telegram->sendMessage($message, $chatId);
}

function handleScheduleDailyCommand($command, $chatId, $telegram, $conn) {
    global $permissionManager;

    // Check permission
    if (!$permissionManager->hasPermission($chatId, \App\PermissionManager::REPORT_SCHEDULE)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::REPORT_SCHEDULE), $chatId);
        return;
    }

    $time = $command['time'];

    // Validate time format (HH:MM)
    if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
        $telegram->sendMessage("❌ Invalid time format. Use HH:MM (e.g., 08:00)", $chatId);
        return;
    }

    // Add :00 seconds
    $scheduleTime = $time . ':00';

    // Insert or update schedule
    $stmt = $conn->prepare("
        INSERT INTO telegram_report_schedules (chat_id, report_type, schedule_time, is_active)
        VALUES (?, 'daily', ?, 1)
        ON DUPLICATE KEY UPDATE schedule_time = ?, is_active = 1
    ");
    $stmt->bind_param("sss", $chatId, $scheduleTime, $scheduleTime);

    if ($stmt->execute()) {
        $message = "✅ <b>Daily Report Scheduled</b>\n\n";
        $message .= "Time: <code>{$time}</code>\n";
        $message .= "Timezone: Asia/Jakarta\n\n";
        $message .= "You will receive a daily report at this time every day.\n\n";
        $message .= "Use /schedule list to view all schedules.";

        $telegram->sendMessage($message, $chatId);
    } else {
        $telegram->sendMessage("❌ Failed to schedule daily report", $chatId);
    }
}

function handleScheduleWeeklyCommand($command, $chatId, $telegram, $conn) {
    global $permissionManager;

    // Check permission
    if (!$permissionManager->hasPermission($chatId, \App\PermissionManager::REPORT_SCHEDULE)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::REPORT_SCHEDULE), $chatId);
        return;
    }

    $day = $command['day'];
    $time = $command['time'];

    // Validate time format
    if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
        $telegram->sendMessage("❌ Invalid time format. Use HH:MM (e.g., 08:00)", $chatId);
        return;
    }

    // Map day name to day number (0 = Sunday, 6 = Saturday)
    $dayMap = [
        'sunday' => 0, 'sun' => 0,
        'monday' => 1, 'mon' => 1,
        'tuesday' => 2, 'tue' => 2,
        'wednesday' => 3, 'wed' => 3,
        'thursday' => 4, 'thu' => 4,
        'friday' => 5, 'fri' => 5,
        'saturday' => 6, 'sat' => 6
    ];

    if (!isset($dayMap[$day])) {
        $telegram->sendMessage(
            "❌ Invalid day. Use:\n" .
            "sunday, monday, tuesday, wednesday, thursday, friday, saturday\n" .
            "Or short forms: sun, mon, tue, wed, thu, fri, sat",
            $chatId
        );
        return;
    }

    $scheduleDay = $dayMap[$day];
    $scheduleTime = $time . ':00';

    // Insert or update schedule
    $stmt = $conn->prepare("
        INSERT INTO telegram_report_schedules (chat_id, report_type, schedule_time, schedule_day, is_active)
        VALUES (?, 'weekly', ?, ?, 1)
        ON DUPLICATE KEY UPDATE schedule_time = ?, schedule_day = ?, is_active = 1
    ");
    $stmt->bind_param("ssiis", $chatId, $scheduleTime, $scheduleDay, $scheduleTime, $scheduleDay);

    if ($stmt->execute()) {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayName = $days[$scheduleDay];

        $message = "✅ <b>Weekly Report Scheduled</b>\n\n";
        $message .= "Day: <code>{$dayName}</code>\n";
        $message .= "Time: <code>{$time}</code>\n";
        $message .= "Timezone: Asia/Jakarta\n\n";
        $message .= "You will receive a weekly report every {$dayName} at this time.\n\n";
        $message .= "Use /schedule list to view all schedules.";

        $telegram->sendMessage($message, $chatId);
    } else {
        $telegram->sendMessage("❌ Failed to schedule weekly report", $chatId);
    }
}

function handleScheduleDisableCommand($command, $chatId, $telegram, $conn) {
    $reportType = $command['report_type'];

    if (!in_array($reportType, ['daily', 'weekly'])) {
        $telegram->sendMessage("❌ Invalid report type. Use 'daily' or 'weekly'", $chatId);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE telegram_report_schedules
        SET is_active = 0
        WHERE chat_id = ? AND report_type = ?
    ");
    $stmt->bind_param("ss", $chatId, $reportType);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "✅ <b>" . ucfirst($reportType) . " Report Schedule Disabled</b>\n\n";
        $message .= "You will no longer receive automated {$reportType} reports.\n\n";
        $message .= "Use /schedule {$reportType} to re-enable it.";

        $telegram->sendMessage($message, $chatId);
    } else {
        $telegram->sendMessage("❌ No active {$reportType} schedule found", $chatId);
    }
}

// ====================
// User Management Handlers
// ====================
function handleWhoamiCommand($chatId, $telegram, $permissionManager) {
    $user = $permissionManager->getUser($chatId);

    if (!$user) {
        $telegram->sendMessage("❌ User not found in database", $chatId);
        return;
    }

    $roleDisplay = $permissionManager->getRoleDisplay($user['role']);
    $permissions = $permissionManager->getUserPermissions($chatId);

    $message = "👤 <b>Your Account Information</b>\n\n";
    $message .= "Name: <b>{$user['first_name']}" . ($user['last_name'] ? ' ' . $user['last_name'] : '') . "</b>\n";
    if ($user['username']) {
        $message .= "Username: @{$user['username']}\n";
    }
    $message .= "Chat ID: <code>{$chatId}</code>\n";
    $message .= "Role: {$roleDisplay}\n";
    $message .= "Status: " . ($user['is_active'] ? '✅ Active' : '❌ Inactive') . "\n\n";

    $message .= "📋 <b>Your Permissions:</b>\n";
    if (count($permissions) > 0) {
        foreach ($permissions as $perm) {
            $permName = str_replace('_', ' ', $perm);
            $permName = ucwords(str_replace('.', ' - ', $permName));
            $message .= "• {$permName}\n";
        }
    } else {
        $message .= "No permissions assigned\n";
    }

    $telegram->sendMessage($message, $chatId);
}

function handleUsersListCommand($chatId, $telegram, $permissionManager) {
    // Check admin permission
    if (!$permissionManager->isAdmin($chatId)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::ADMIN_USER_MANAGE), $chatId);
        return;
    }

    $users = $permissionManager->getAllUsers();

    if (count($users) === 0) {
        $telegram->sendMessage("📋 No users found in database", $chatId);
        return;
    }

    $message = "👥 <b>User List</b>\n\n";
    $message .= "Total users: <b>" . count($users) . "</b>\n\n";

    foreach ($users as $user) {
        $status = $user['is_active'] ? '✅' : '❌';
        $roleDisplay = $permissionManager->getRoleDisplay($user['role']);
        $name = $user['first_name'] . ($user['last_name'] ? ' ' . $user['last_name'] : '');
        $username = $user['username'] ? ' (@' . $user['username'] . ')' : '';

        $message .= "{$status} <b>{$name}</b>{$username}\n";
        $message .= "   Chat ID: <code>{$user['chat_id']}</code>\n";
        $message .= "   Role: {$roleDisplay}\n";
        $message .= "   Last active: " . ($user['last_activity'] ? date('M j, H:i', strtotime($user['last_activity'])) : 'Never') . "\n\n";
    }

    $message .= "💡 Use /user &lt;chat_id&gt; to view details";

    $telegram->sendMessage($message, $chatId);
}

function handleUserInfoCommand($command, $chatId, $telegram, $permissionManager) {
    // Check admin permission
    if (!$permissionManager->isAdmin($chatId)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::ADMIN_USER_MANAGE), $chatId);
        return;
    }

    $targetChatId = $command['target_chat_id'];
    $user = $permissionManager->getUser($targetChatId);

    if (!$user) {
        $telegram->sendMessage("❌ User not found with Chat ID: <code>{$targetChatId}</code>", $chatId);
        return;
    }

    $roleDisplay = $permissionManager->getRoleDisplay($user['role']);
    $permissions = $permissionManager->getUserPermissions($targetChatId);

    $message = "👤 <b>User Information</b>\n\n";
    $message .= "Name: <b>{$user['first_name']}" . ($user['last_name'] ? ' ' . $user['last_name'] : '') . "</b>\n";
    if ($user['username']) {
        $message .= "Username: @{$user['username']}\n";
    }
    $message .= "Chat ID: <code>{$targetChatId}</code>\n";
    $message .= "Role: {$roleDisplay}\n";
    $message .= "Status: " . ($user['is_active'] ? '✅ Active' : '❌ Inactive') . "\n";
    $message .= "Registered: " . date('M j, Y H:i', strtotime($user['created_at'])) . "\n";
    $message .= "Last Activity: " . ($user['last_activity'] ? date('M j, Y H:i', strtotime($user['last_activity'])) : 'Never') . "\n\n";

    $message .= "📋 <b>Permissions:</b>\n";
    if (count($permissions) > 0) {
        foreach ($permissions as $perm) {
            $permName = str_replace('_', ' ', $perm);
            $permName = ucwords(str_replace('.', ' - ', $permName));
            $message .= "• {$permName}\n";
        }
    } else {
        $message .= "No permissions assigned\n";
    }

    $message .= "\n💡 <b>Management Commands:</b>\n";
    $message .= "/setrole {$targetChatId} &lt;role&gt;\n";
    $message .= "/activate {$targetChatId}\n";
    $message .= "/deactivate {$targetChatId}";

    $telegram->sendMessage($message, $chatId);
}

function handleUserSetRoleCommand($command, $chatId, $telegram, $permissionManager) {
    // Check admin permission
    if (!$permissionManager->isAdmin($chatId)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::ADMIN_USER_MANAGE), $chatId);
        return;
    }

    $targetChatId = $command['target_chat_id'];
    $role = $command['role'];

    // Validate role
    $validRoles = ['admin', 'operator', 'viewer'];
    if (!in_array($role, $validRoles)) {
        $telegram->sendMessage(
            "❌ Invalid role: <code>{$role}</code>\n\n" .
            "Valid roles:\n" .
            "• <b>admin</b> - Full access\n" .
            "• <b>operator</b> - Manage devices & reports\n" .
            "• <b>viewer</b> - Read-only access",
            $chatId
        );
        return;
    }

    // Check if user exists
    $user = $permissionManager->getUser($targetChatId);
    if (!$user) {
        $telegram->sendMessage("❌ User not found with Chat ID: <code>{$targetChatId}</code>", $chatId);
        return;
    }

    // Prevent user from changing their own role
    if ($targetChatId == $chatId) {
        $telegram->sendMessage("❌ You cannot change your own role.\n\nPlease ask another admin to do this.", $chatId);
        return;
    }

    // Set role
    $success = $permissionManager->setUserRole($targetChatId, $role);

    if ($success) {
        $roleDisplay = $permissionManager->getRoleDisplay($role);
        $userName = $user['first_name'] . ($user['last_name'] ? ' ' . $user['last_name'] : '');

        $message = "✅ <b>Role Updated</b>\n\n";
        $message .= "User: <b>{$userName}</b>\n";
        $message .= "Chat ID: <code>{$targetChatId}</code>\n";
        $message .= "New Role: {$roleDisplay}\n\n";

        // Get new permissions
        $permissions = $permissionManager->getUserPermissions($targetChatId);
        $permCount = count($permissions);
        $message .= "📋 <b>Updated Permissions ({$permCount}):</b>\n";
        foreach ($permissions as $perm) {
            $permName = str_replace('_', ' ', $perm);
            $permName = ucwords(str_replace('.', ' - ', $permName));
            $message .= "• {$permName}\n";
        }

        $telegram->sendMessage($message, $chatId);

        // Notify target user
        $notifyMessage = "👑 <b>Your role has been updated</b>\n\n";
        $notifyMessage .= "New Role: {$roleDisplay}\n\n";
        $notifyMessage .= "Use /whoami to see your new permissions.";
        $telegram->sendMessage($notifyMessage, $targetChatId);
    } else {
        $telegram->sendMessage("❌ Failed to update user role", $chatId);
    }
}

function handleUserActivateCommand($command, $chatId, $telegram, $permissionManager) {
    // Check admin permission
    if (!$permissionManager->isAdmin($chatId)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::ADMIN_USER_MANAGE), $chatId);
        return;
    }

    $targetChatId = $command['target_chat_id'];

    // Check if user exists
    $user = $permissionManager->getUser($targetChatId);
    if (!$user) {
        $telegram->sendMessage("❌ User not found with Chat ID: <code>{$targetChatId}</code>", $chatId);
        return;
    }

    if ($user['is_active']) {
        $telegram->sendMessage("ℹ️ User is already active", $chatId);
        return;
    }

    // Activate user
    $success = $permissionManager->activateUser($targetChatId);

    if ($success) {
        $userName = $user['first_name'] . ($user['last_name'] ? ' ' . $user['last_name'] : '');

        $message = "✅ <b>User Activated</b>\n\n";
        $message .= "User: <b>{$userName}</b>\n";
        $message .= "Chat ID: <code>{$targetChatId}</code>\n";
        $message .= "Role: " . $permissionManager->getRoleDisplay($user['role']) . "\n\n";
        $message .= "User can now use the bot.";

        $telegram->sendMessage($message, $chatId);

        // Notify target user
        $notifyMessage = "✅ <b>Your account has been activated</b>\n\n";
        $notifyMessage .= "You can now use the bot.\n";
        $notifyMessage .= "Use /start to get started.";
        $telegram->sendMessage($notifyMessage, $targetChatId);
    } else {
        $telegram->sendMessage("❌ Failed to activate user", $chatId);
    }
}

function handleUserDeactivateCommand($command, $chatId, $telegram, $permissionManager) {
    // Check admin permission
    if (!$permissionManager->isAdmin($chatId)) {
        $telegram->sendMessage($permissionManager->getDenialMessage(\App\PermissionManager::ADMIN_USER_MANAGE), $chatId);
        return;
    }

    $targetChatId = $command['target_chat_id'];

    // Check if user exists
    $user = $permissionManager->getUser($targetChatId);
    if (!$user) {
        $telegram->sendMessage("❌ User not found with Chat ID: <code>{$targetChatId}</code>", $chatId);
        return;
    }

    // Prevent user from deactivating themselves
    if ($targetChatId == $chatId) {
        $telegram->sendMessage("❌ You cannot deactivate your own account.\n\nPlease ask another admin to do this.", $chatId);
        return;
    }

    if (!$user['is_active']) {
        $telegram->sendMessage("ℹ️ User is already inactive", $chatId);
        return;
    }

    // Deactivate user
    $success = $permissionManager->deactivateUser($targetChatId);

    if ($success) {
        $userName = $user['first_name'] . ($user['last_name'] ? ' ' . $user['last_name'] : '');

        $message = "✅ <b>User Deactivated</b>\n\n";
        $message .= "User: <b>{$userName}</b>\n";
        $message .= "Chat ID: <code>{$targetChatId}</code>\n\n";
        $message .= "User can no longer use the bot.";

        $telegram->sendMessage($message, $chatId);

        // Notify target user
        $notifyMessage = "❌ <b>Your account has been deactivated</b>\n\n";
        $notifyMessage .= "Please contact the system administrator for assistance.";
        $telegram->sendMessage($notifyMessage, $targetChatId);
    } else {
        $telegram->sendMessage("❌ Failed to deactivate user", $chatId);
    }
}
