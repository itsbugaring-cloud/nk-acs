<?php
namespace App;

/**
 * Telegram Bot Client
 */
class TelegramBot {
    private $botToken;
    private $chatId;
    private $apiUrl;

    public function __construct($botToken = null, $chatId = null) {
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    private function botName() {
        return defined('APP_NAME') ? APP_NAME . ' Bot' : 'NETKING-ACS Bot';
    }

    /**
     * Make API request
     */
    private function request($method, $params = []) {
        $url = $this->apiUrl . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'error' => $error];
        }

        return json_decode($response, true);
    }

    /**
     * Test connection
     */
    public function testConnection() {
        $result = $this->request('getMe');
        return isset($result['ok']) && $result['ok'] === true;
    }

    /**
     * Send message
     */
    public function sendMessage($message, $chatId = null, $replyMarkup = null) {
        $targetChatId = $chatId ?? $this->chatId;

        $params = [
            'chat_id' => $targetChatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Send location
     */
    public function sendLocation($latitude, $longitude, $chatId = null) {
        $targetChatId = $chatId ?? $this->chatId;

        $params = [
            'chat_id' => $targetChatId,
            'latitude' => $latitude,
            'longitude' => $longitude
        ];

        return $this->request('sendLocation', $params);
    }

    /**
     * Edit message with inline keyboard
     */
    public function editMessage($messageId, $chatId, $text, $replyMarkup = null) {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('editMessageText', $params);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery($callbackQueryId, $text = null, $showAlert = false) {
        $params = [
            'callback_query_id' => $callbackQueryId
        ];

        if ($text) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }

        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * Create main menu inline keyboard
     */
    public function getMainMenuKeyboard() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔍 Cari Perangkat', 'callback_data' => 'menu_search'],
                    ['text' => '📋 Daftar Perangkat', 'callback_data' => 'menu_device_list']
                ],
                [
                    ['text' => '🟠 ONT Belum ACS', 'callback_data' => 'menu_firstinform'],
                    ['text' => '🧭 Ringkasan OLT', 'callback_data' => 'menu_olt']
                ],
                [
                    ['text' => '🚨 Alarm Aktif', 'callback_data' => 'menu_alarm'],
                    ['text' => '📶 Alarm Redaman', 'callback_data' => 'menu_redaman']
                ],
                [
                    ['text' => '🔴 Offline Confirmed', 'callback_data' => 'menu_offline'],
                    ['text' => '🧭 Ringkasan OLT', 'callback_data' => 'menu_olt']
                ],
                [
                    ['text' => '🧾 Task Queue', 'callback_data' => 'menu_tasks'],
                    ['text' => '📈 Statistik', 'callback_data' => 'menu_stats']
                ],
                [
                    ['text' => '🔔 Langganan', 'callback_data' => 'menu_subscriptions']
                ],
                [
                    ['text' => '❓ Bantuan', 'callback_data' => 'menu_help']
                ]
            ]
        ];
    }

    /**
     * Create device list keyboard with pagination
     */
    public function getDeviceListKeyboard($devices, $page = 1, $perPage = 10) {
        $keyboard = ['inline_keyboard' => []];
        $totalDevices = count($devices);
        $totalPages = ceil($totalDevices / $perPage);
        $offset = ($page - 1) * $perPage;
        $deviceSlice = array_slice($devices, $offset, $perPage);

        // Add device buttons
        foreach ($deviceSlice as $device) {
            $status = $device['status'] === 'online' ? '🟢' : '🔴';
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "{$status} {$device['serial_number']}",
                    'callback_data' => "device_detail_{$device['device_id']}"
                ]
            ];
        }

        // Add pagination buttons
        $paginationRow = [];
        if ($page > 1) {
            $paginationRow[] = ['text' => '⬅️ Sebelumnya', 'callback_data' => "device_list_page_" . ($page - 1)];
        }
        $paginationRow[] = ['text' => "📄 {$page}/{$totalPages}", 'callback_data' => 'noop'];
        if ($page < $totalPages) {
            $paginationRow[] = ['text' => 'Berikutnya ➡️', 'callback_data' => "device_list_page_" . ($page + 1)];
        }
        $keyboard['inline_keyboard'][] = $paginationRow;

        // Add back button
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 Kembali ke Menu', 'callback_data' => 'menu_main']
        ];

        return $keyboard;
    }

    /**
     * Create device detail keyboard
     */
    public function getDeviceDetailKeyboard($deviceId) {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Cek Ulang', 'callback_data' => "device_detail_{$deviceId}"],
                    ['text' => '⚡ Panggil Read-Only', 'callback_data' => "action_summon_{$deviceId}"]
                ],
                [
                    ['text' => '📶 Detail Redaman', 'callback_data' => "action_optical_{$deviceId}"],
                    ['text' => '📡 Detail WiFi', 'callback_data' => "action_wifiinfo_{$deviceId}"]
                ],
                [
                    ['text' => '🕒 Timeline', 'callback_data' => "action_timeline_{$deviceId}"],
                    ['text' => '🔔 Langganan', 'callback_data' => "action_subscribe_{$deviceId}"]
                ],
                [
                    ['text' => '🔍 Cari Lagi', 'callback_data' => 'menu_search'],
                    ['text' => '🔙 Daftar', 'callback_data' => 'menu_device_list']
                ]
            ]
        ];
    }

    /**
     * Create confirmation keyboard
     */
    public function getConfirmationKeyboard($action, $data) {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Ya', 'callback_data' => "confirm_yes_{$action}_{$data}"],
                    ['text' => '❌ Tidak', 'callback_data' => "confirm_no_{$action}_{$data}"]
                ]
            ]
        ];
    }

    /**
     * Create filter keyboard
     */
    public function getFilterKeyboard() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🟢 Hanya Online', 'callback_data' => 'filter_online'],
                    ['text' => '🔴 Hanya Offline', 'callback_data' => 'filter_offline']
                ],
                [
                    ['text' => '📶 Sinyal Sangat Baik', 'callback_data' => 'filter_signal_excellent'],
                    ['text' => '📶 Sinyal Baik', 'callback_data' => 'filter_signal_good']
                ],
                [
                    ['text' => '📶 Sinyal Cukup', 'callback_data' => 'filter_signal_fair'],
                    ['text' => '📶 Sinyal Buruk', 'callback_data' => 'filter_signal_poor']
                ],
                [
                    ['text' => '🔄 Tampilkan Semua', 'callback_data' => 'filter_all']
                ],
                [
                    ['text' => '🔙 Kembali ke Menu', 'callback_data' => 'menu_main']
                ]
            ]
        ];
    }

    /**
     * Send device status notification
     */
    public function sendDeviceStatus($deviceId, $status, $deviceInfo = []) {
        $statusEmoji = $status === 'online' ? '🟢' : '🔴';
        $statusText = $status === 'online' ? 'ONLINE' : 'OFFLINE';
        $safeDeviceId = htmlspecialchars((string) $deviceId, ENT_QUOTES, 'UTF-8');

        $message = "{$statusEmoji} <b>Status ONT: {$statusText}</b>\n\n";
        $message .= "Device ID: <code>{$safeDeviceId}</code>\n";

        if (!empty($deviceInfo)) {
            if (isset($deviceInfo['serial_number'])) {
                $message .= "SN: <code>" . htmlspecialchars((string) $deviceInfo['serial_number'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            if (!empty($deviceInfo['olt_name'])) {
                $message .= "OLT: <b>" . htmlspecialchars((string) $deviceInfo['olt_name'], ENT_QUOTES, 'UTF-8') . "</b>\n";
            }
            if (!empty($deviceInfo['pon_port']) || !empty($deviceInfo['ont_index'])) {
                $pon = htmlspecialchars((string) ($deviceInfo['pon_port'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                $ont = htmlspecialchars((string) ($deviceInfo['ont_index'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                $message .= "PON/ONT: <code>{$pon} / {$ont}</code>\n";
            }
            if (!empty($deviceInfo['ont_name'])) {
                $message .= "Nama ONT: <b>" . htmlspecialchars((string) $deviceInfo['ont_name'], ENT_QUOTES, 'UTF-8') . "</b>\n";
            }
            if (isset($deviceInfo['ip_tr069'])) {
                $message .= "IP TR069: <code>" . htmlspecialchars((string) $deviceInfo['ip_tr069'], ENT_QUOTES, 'UTF-8') . "</code>\n";
            }
            if (isset($deviceInfo['customer_name'])) {
                $message .= "Customer: <b>" . htmlspecialchars((string) $deviceInfo['customer_name'], ENT_QUOTES, 'UTF-8') . "</b>\n";
            }
        }

        $message .= "\nWaktu: " . date('Y-m-d H:i:s');

        return $this->sendMessage($message);
    }

    /**
     * Get updates (for interactive bot)
     */
    public function getUpdates($offset = 0) {
        $params = ['offset' => $offset];
        return $this->request('getUpdates', $params);
    }

    /**
     * Process command
     */
    public function processCommand($command, $chatId) {
        $command = trim((string) $command);
        if ($command === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $command) ?: [];
        if (empty($parts)) {
            return null;
        }

        $cmd = strtolower($parts[0]);
        if (str_contains($cmd, '@')) {
            $cmd = substr($cmd, 0, (int) strpos($cmd, '@'));
            $parts[0] = $cmd;
        }

        switch ($cmd) {
            case '/start':
            case '/menu':
                $message = "🤖 <b>" . $this->botName() . "</b>\n\n";
                $message .= "Selamat datang. Pilih menu di bawah untuk mulai memakai bot ACS.\n\n";
                $message .= "📊 Lihat status dan detail perangkat\n";
                $message .= "📋 Buka daftar perangkat dengan pagination\n";
                $message .= "⚡ Panggil perangkat untuk pengecekan cepat\n";
                $message .= "📈 Lihat statistik jaringan\n";
                $message .= "🔍 Cari perangkat dengan cepat\n";
                $message .= "🔔 Kelola langganan notifikasi perangkat";

                return $this->sendMessage($message, $chatId, $this->getMainMenuKeyboard());

            case '/status':
                if (isset($parts[1])) {
                    $deviceId = $parts[1];
                    return ['command' => 'status', 'device_id' => $deviceId, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /status <device_id>\nAtau gunakan /cek <serial|pppoe|ssid> untuk pencarian cepat.", $chatId, $this->getMainMenuKeyboard());

            case '/cek':
                if (isset($parts[1])) {
                    $subCommand = strtolower((string) $parts[1]);
                    if (in_array($subCommand, ['redaman', 'rx', 'optical'], true)) {
                        return [
                            'command' => 'optical_summary',
                            'query' => trim(implode(' ', array_slice($parts, 2))),
                            'chat_id' => $chatId,
                        ];
                    }
                    $query = implode(' ', array_slice($parts, 1));
                    return ['command' => 'check', 'query' => $query, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /cek <serial|device_id|pppoe|ssid>\n\nContoh:\n/cek TDTC35B24C50", $chatId, $this->getMainMenuKeyboard());

            case '/list':
                return ['command' => 'list', 'chat_id' => $chatId];

            case '/stats':
            case '/dashboard':
                return ['command' => 'stats', 'chat_id' => $chatId];

            case '/summon':
                if (isset($parts[1])) {
                    $deviceId = $parts[1];
                    return ['command' => 'summon', 'device_id' => $deviceId, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /summon <device_id>\n\nAtau pakai menu interaktif.", $chatId, $this->getMainMenuKeyboard());

            case '/search':
                if (isset($parts[1])) {
                    $keyword = implode(' ', array_slice($parts, 1));
                    return ['command' => 'search', 'keyword' => $keyword, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /search <keyword>\n\nPencarian bisa berdasarkan Serial Number, MAC Address, atau WiFi SSID.\nTip: /cek <keyword> untuk snapshot cepat.", $chatId);

            case '/filter':
                return ['command' => 'filter', 'chat_id' => $chatId];

            case '/subscribe':
                if (isset($parts[1])) {
                    $deviceId = $parts[1];
                    return ['command' => 'subscribe', 'device_id' => $deviceId, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /subscribe <device_id>", $chatId);

            case '/unsubscribe':
                if (isset($parts[1])) {
                    $deviceId = $parts[1];
                    return ['command' => 'unsubscribe', 'device_id' => $deviceId, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /unsubscribe <device_id>", $chatId);

            case '/subscriptions':
                return ['command' => 'subscriptions', 'chat_id' => $chatId];

            case '/belumacs':
                return ['command' => 'first_inform_watch', 'olt' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/alarm':
            case '/alarms':
                return ['command' => 'alarm_summary', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/redaman':
            case '/rx':
            case '/optical':
                return ['command' => 'optical_summary', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/offline':
                return ['command' => 'offline_confirmed', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/flap':
            case '/flapping':
                return ['command' => 'flapping_devices', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/massdrop':
                return ['command' => 'massdrop_summary', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/mute':
                if (isset($parts[1])) {
                    $duration = $parts[count($parts) - 1] ?? '';
                    $queryParts = array_slice($parts, 1);
                    if (preg_match('/^\d+\s*(m|h|d)$/i', $duration)) {
                        array_pop($queryParts);
                    } else {
                        $duration = '60m';
                    }

                    return [
                        'command' => 'mute_area',
                        'query' => trim(implode(' ', $queryParts)),
                        'duration' => $duration,
                        'chat_id' => $chatId,
                    ];
                }
                return $this->sendMessage("Gunakan: /mute <nama_OLT/area> <durasi>\n\nContoh:\n/mute Cikalong 60m\n/mute Cicaheum 2h", $chatId, $this->getMainMenuKeyboard());

            case '/unmute':
                if (isset($parts[1])) {
                    return ['command' => 'unmute_area', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /unmute <nama_OLT/area>\n\nContoh:\n/unmute Cikalong", $chatId, $this->getMainMenuKeyboard());

            case '/mutes':
                return ['command' => 'mute_list', 'chat_id' => $chatId];

            case '/olt':
                return ['command' => 'olt_summary', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/task':
                return ['command' => 'task_monitor', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];

            case '/timeline':
                if (isset($parts[1])) {
                    return ['command' => 'timeline', 'query' => trim(implode(' ', array_slice($parts, 1))), 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /timeline <serial|device_id|ssid|pppoe>\n\nContoh:\n/timeline TDTC35B24C50", $chatId, $this->getMainMenuKeyboard());

            case '/safe':
            case '/safemode':
                return ['command' => 'safe_mode', 'chat_id' => $chatId];

            case '/report':
                if (isset($parts[1])) {
                    $reportType = strtolower($parts[1]);
                    if (in_array($reportType, ['daily', 'weekly'])) {
                        return ['command' => 'report', 'report_type' => $reportType, 'chat_id' => $chatId];
                    }
                }
                return $this->sendMessage("Gunakan: /report <type>\n\nTipe yang tersedia:\n• daily - Buat laporan harian\n• weekly - Buat laporan mingguan", $chatId);

            case '/schedule':
                if (isset($parts[1])) {
                    $action = strtolower($parts[1]);

                    if ($action === 'list') {
                        return ['command' => 'schedule_list', 'chat_id' => $chatId];
                    } elseif ($action === 'disable' && isset($parts[2])) {
                        $reportType = strtolower($parts[2]);
                        return ['command' => 'schedule_disable', 'report_type' => $reportType, 'chat_id' => $chatId];
                    } elseif ($action === 'daily' && isset($parts[2])) {
                        $time = $parts[2];
                        return ['command' => 'schedule_daily', 'time' => $time, 'chat_id' => $chatId];
                    } elseif ($action === 'weekly' && isset($parts[2]) && isset($parts[3])) {
                        $day = strtolower($parts[2]);
                        $time = $parts[3];
                        return ['command' => 'schedule_weekly', 'day' => $day, 'time' => $time, 'chat_id' => $chatId];
                    }
                }
                return $this->sendMessage(
                    "Gunakan: /schedule <action>\n\n" .
                    "Aksi:\n" .
                    "• list - Lihat jadwal aktif\n" .
                    "• daily HH:MM - Jadwalkan laporan harian\n" .
                    "• weekly <day> HH:MM - Jadwalkan laporan mingguan\n" .
                    "• disable <type> - Nonaktifkan jadwal\n\n" .
                    "Contoh:\n" .
                    "/schedule daily 08:00\n" .
                    "/schedule weekly monday 09:00\n" .
                    "/schedule disable daily",
                    $chatId
                );

            case '/whoami':
            case '/myrole':
                return ['command' => 'whoami', 'chat_id' => $chatId];

            case '/users':
                return ['command' => 'users_list', 'chat_id' => $chatId];

            case '/user':
                if (isset($parts[1])) {
                    $targetChatId = $parts[1];
                    return ['command' => 'user_info', 'target_chat_id' => $targetChatId, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /user <chat_id>\n\nContoh: /user 123456789", $chatId);

            case '/setrole':
                if (isset($parts[1]) && isset($parts[2])) {
                    $targetChatId = $parts[1];
                    $role = strtolower($parts[2]);
                    return ['command' => 'user_setrole', 'target_chat_id' => $targetChatId, 'role' => $role, 'chat_id' => $chatId];
                }
                return $this->sendMessage(
                    "Gunakan: /setrole <chat_id> <role>\n\n" .
                    "Role yang tersedia:\n" .
                    "• admin - Akses penuh\n" .
                    "• operator - Kelola perangkat & laporan\n" .
                    "• viewer - Hanya baca\n\n" .
                    "Contoh: /setrole 123456789 operator",
                    $chatId
                );

            case '/activate':
                if (isset($parts[1])) {
                    $targetChatId = $parts[1];
                    return ['command' => 'user_activate', 'target_chat_id' => $targetChatId, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /activate <chat_id>\n\nContoh: /activate 123456789", $chatId);

            case '/deactivate':
                if (isset($parts[1])) {
                    $targetChatId = $parts[1];
                    return ['command' => 'user_deactivate', 'target_chat_id' => $targetChatId, 'chat_id' => $chatId];
                }
                return $this->sendMessage("Gunakan: /deactivate <chat_id>\n\nContoh: /deactivate 123456789", $chatId);

            case '/help':
                $helpMessage = "📖 <b>Daftar Perintah:</b>\n\n";
                $helpMessage .= "<b>Utama:</b>\n";
                $helpMessage .= "/start - Tampilkan menu utama\n";
                $helpMessage .= "/menu - Tampilkan menu utama\n";
                $helpMessage .= "/help - Tampilkan bantuan ini\n\n";

                $helpMessage .= "<b>Perangkat:</b>\n";
                $helpMessage .= "/stats - Statistik dashboard\n";
                $helpMessage .= "/list - Tampilkan daftar perangkat\n";
                $helpMessage .= "/status &lt;id&gt; - Status perangkat\n";
                $helpMessage .= "/cek &lt;keyword&gt; - Snapshot perangkat cepat\n";
                $helpMessage .= "/summon &lt;id&gt; - Panggil read-only (Operator+)\n";
                $helpMessage .= "/search &lt;keyword&gt; - Cari perangkat\n";
                $helpMessage .= "/filter - Filter perangkat\n";
                $helpMessage .= "/belumacs [olt] - ONT online OLT belum first-inform\n";
                $helpMessage .= "/alarm [olt] - Ringkasan alarm aktif\n";
                $helpMessage .= "/redaman [olt] - Alarm redaman semua / per OLT\n";
                $helpMessage .= "/offline [olt] - ONT offline confirmed\n";
                $helpMessage .= "/flapping [olt] - ONT sering naik-turun\n";
                $helpMessage .= "/massdrop [olt] - Ringkasan mass-drop OLT\n";
                $helpMessage .= "/mute &lt;olt&gt; 60m - Senyapkan alarm area saat maintenance\n";
                $helpMessage .= "/unmute &lt;olt&gt; - Buka mute area\n";
                $helpMessage .= "/mutes - Lihat area yang sedang mute\n";
                $helpMessage .= "/olt [nama] - Ringkasan inventory OLT\n";
                $helpMessage .= "/task [keyword] - Monitor task queue GenieACS\n";
                $helpMessage .= "/timeline &lt;keyword&gt; - Riwayat singkat perangkat\n";
                $helpMessage .= "\n";

                $helpMessage .= "<b>Notifikasi:</b>\n";
                $helpMessage .= "/subscribe &lt;id&gt; - Langganan perangkat (Operator+)\n";
                $helpMessage .= "/unsubscribe &lt;id&gt; - Batalkan langganan\n";
                $helpMessage .= "/subscriptions - Lihat langganan\n\n";

                $helpMessage .= "<b>Laporan:</b>\n";
                $helpMessage .= "/report daily - Laporan harian\n";
                $helpMessage .= "/report weekly - Laporan mingguan\n";
                $helpMessage .= "/schedule list - Lihat jadwal\n";
                $helpMessage .= "/schedule daily HH:MM - Jadwal harian (Operator+)\n";
                $helpMessage .= "/schedule weekly &lt;day&gt; HH:MM - Jadwal mingguan (Operator+)\n\n";

                $helpMessage .= "<b>Manajemen User:</b>\n";
                $helpMessage .= "/whoami - Lihat role & izin Anda\n";
                $helpMessage .= "/users - Daftar semua user (Admin)\n";
                $helpMessage .= "/user &lt;chat_id&gt; - Detail user (Admin)\n";
                $helpMessage .= "/setrole &lt;chat_id&gt; &lt;role&gt; - Ubah role user (Admin)\n";
                $helpMessage .= "/activate &lt;chat_id&gt; - Aktifkan user (Admin)\n";
                $helpMessage .= "/deactivate &lt;chat_id&gt; - Nonaktifkan user (Admin)\n\n";

                $helpMessage .= "💡 <b>Tip:</b> Pakai menu interaktif agar lebih mudah.";

                return $this->sendMessage($helpMessage, $chatId, $this->getMainMenuKeyboard());

            default:
                return $this->sendMessage("Perintah tidak dikenal. Ketik /help untuk melihat daftar perintah.", $chatId, $this->getMainMenuKeyboard());
        }
    }
}
