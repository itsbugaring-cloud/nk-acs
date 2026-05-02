<?php
namespace App;

/**
 * Report Generator for Telegram Bot
 * Generates daily and weekly network reports
 */
class ReportGenerator {
    private $conn;
    private $genieacs;

    public function __construct($dbConnection, $genieacsInstance = null) {
        $this->conn = $dbConnection;
        $this->genieacs = $genieacsInstance;
    }

    /**
     * Generate daily report
     *
     * @param string $reportDate Date in Y-m-d format (default: today)
     * @return array Report data
     */
    public function generateDailyReport($reportDate = null) {
        if (!$reportDate) {
            $reportDate = date('Y-m-d');
        }

        $report = [
            'type' => 'daily',
            'date' => $reportDate,
            'title' => 'Laporan Harian NETKING-ACS',
            'total_devices' => 0,
            'online_devices' => 0,
            'offline_devices' => 0,
            'new_online_count' => 0,
            'new_offline_count' => 0,
            'offline_24h_count' => 0,
            'poor_signal_count' => 0,
            'critical_signal_count' => 0,
            'warning_signal_count' => 0,
            'missing_acs_count' => 0,
            'olt_problem_count' => 0,
            'devices_by_status' => [],
            'top_issues' => []
        ];

        // Get current device statistics
        if ($this->genieacs) {
            $devicesResult = $this->genieacs->getDevices();

            if ($devicesResult['success']) {
                $devices = $devicesResult['data'];
                $report['total_devices'] = count($devices);

                $poorSignalDevices = [];
                $acsSerials = [];

                foreach ($devices as $device) {
                    $parsed = $this->genieacs->parseDeviceData($device);
                    $serial = strtolower(trim((string) ($parsed['serial_number'] ?? '')));
                    if ($serial !== '' && $serial !== 'n/a') {
                        $acsSerials[$serial] = true;
                    }

                    if ($parsed['status'] === 'online') {
                        $report['online_devices']++;
                    } else {
                        $report['offline_devices']++;
                    }

                    // Check for poor signal (Rx Power < -25 dBm)
                    if ($parsed['rx_power'] !== 'N/A' && is_numeric($parsed['rx_power'])) {
                        $rxPower = floatval($parsed['rx_power']);
                        if ($rxPower < -25) {
                            $report['poor_signal_count']++;
                            $poorSignalDevices[] = [
                                'serial' => $parsed['serial_number'],
                                'rx_power' => $rxPower
                            ];
                        }
                    }
                }

                // Sort poor signal devices by worst signal
                usort($poorSignalDevices, function($a, $b) {
                    return $a['rx_power'] <=> $b['rx_power'];
                });
                $report['top_issues'] = array_slice($poorSignalDevices, 0, 5);

                $inventory = $this->getInventorySummary($acsSerials);
                $report['critical_signal_count'] = $inventory['critical_signal_count'];
                $report['warning_signal_count'] = $inventory['warning_signal_count'];
                $report['missing_acs_count'] = $inventory['missing_acs_count'];
                $report['olt_problem_count'] = $inventory['olt_problem_count'];
            }
        }

        // Get status changes from device_monitoring table
        $startOfDay = $reportDate . ' 00:00:00';
        $endOfDay = $reportDate . ' 23:59:59';

        // Count devices that came online today
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT device_id) as count
            FROM device_monitoring
            WHERE status = 'online'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $startOfDay, $endOfDay);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $report['new_online_count'] = $result['count'] ?? 0;

        // Count devices that went offline today
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT device_id) as count
            FROM device_monitoring
            WHERE status = 'offline'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $startOfDay, $endOfDay);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $report['new_offline_count'] = $result['count'] ?? 0;

        // Count devices offline for more than 24 hours
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT device_id) as count
            FROM device_monitoring
            WHERE status = 'offline'
            AND created_at < ?
            AND device_id NOT IN (
                SELECT DISTINCT device_id
                FROM device_monitoring
                WHERE status = 'online'
                AND created_at >= ?
            )
        ");
        $stmt->bind_param("ss", $yesterday, $yesterday);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $report['offline_24h_count'] = $result['count'] ?? 0;

        return $report;
    }

    /**
     * Generate weekly report
     *
     * @param string $endDate End date in Y-m-d format (default: today)
     * @return array Report data
     */
    public function generateWeeklyReport($endDate = null) {
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }

        $startDate = date('Y-m-d', strtotime($endDate . ' -6 days'));

        $report = [
            'type' => 'weekly',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'title' => 'Laporan Mingguan NETKING-ACS',
            'total_devices' => 0,
            'online_devices' => 0,
            'offline_devices' => 0,
            'total_online_events' => 0,
            'total_offline_events' => 0,
            'offline_24h_count' => 0,
            'poor_signal_count' => 0,
            'critical_signal_count' => 0,
            'warning_signal_count' => 0,
            'missing_acs_count' => 0,
            'olt_problem_count' => 0,
            'avg_uptime_percent' => 0,
            'daily_breakdown' => []
        ];

        // Get current statistics
        $acsSerials = [];
        if ($this->genieacs) {
            $stats = $this->genieacs->getDeviceStats();
            if ($stats['success']) {
                $report['total_devices'] = $stats['data']['total'];
                $report['online_devices'] = $stats['data']['online'];
                $report['offline_devices'] = $stats['data']['offline'];
            }

            $devicesResult = $this->genieacs->getDevices();
            if ($devicesResult['success']) {
                foreach ($devicesResult['data'] as $device) {
                    $parsed = $this->genieacs->parseDeviceData($device);
                    $serial = strtolower(trim((string) ($parsed['serial_number'] ?? '')));
                    if ($serial !== '' && $serial !== 'n/a') {
                        $acsSerials[$serial] = true;
                    }
                    if ($parsed['rx_power'] !== 'N/A' && is_numeric($parsed['rx_power'])) {
                        if (floatval($parsed['rx_power']) < -25) {
                            $report['poor_signal_count']++;
                        }
                    }
                }
            }
        }

        $inventory = $this->getInventorySummary($acsSerials);
        $report['critical_signal_count'] = $inventory['critical_signal_count'];
        $report['warning_signal_count'] = $inventory['warning_signal_count'];
        $report['missing_acs_count'] = $inventory['missing_acs_count'];
        $report['olt_problem_count'] = $inventory['olt_problem_count'];

        // Get weekly status change statistics
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';

        // Count total online events
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM device_monitoring
            WHERE status = 'online'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $startDateTime, $endDateTime);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $report['total_online_events'] = $result['count'] ?? 0;

        // Count total offline events
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM device_monitoring
            WHERE status = 'offline'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $startDateTime, $endDateTime);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $report['total_offline_events'] = $result['count'] ?? 0;

        // Get daily breakdown
        $stmt = $this->conn->prepare("
            SELECT
                DATE(created_at) as date,
                SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online_count,
                SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline_count
            FROM device_monitoring
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->bind_param("ss", $startDateTime, $endDateTime);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $report['daily_breakdown'][] = [
                'date' => $row['date'],
                'online' => $row['online_count'],
                'offline' => $row['offline_count']
            ];
        }

        // Calculate average uptime (devices that stayed online / total devices)
        if ($report['total_devices'] > 0) {
            $report['avg_uptime_percent'] = round(($report['online_devices'] / $report['total_devices']) * 100, 1);
        }

        return $report;
    }

    /**
     * Format report as Telegram message
     *
     * @param array $report Report data from generateDailyReport() or generateWeeklyReport()
     * @return string Formatted message with HTML
     */
    public function formatReportMessage($report) {
        $message = "<b>{$report['title']}</b>\n\n";

        if ($report['type'] === 'daily') {
            $message .= "Tanggal: " . date('Y-m-d', strtotime($report['date'])) . "\n";
            $message .= "Safe Mode: ON / read-only\n\n";

            $message .= "<b>Status ACS</b>\n";
            $message .= "Total perangkat: <b>{$report['total_devices']}</b>\n";

            $onlinePercent = $report['total_devices'] > 0
                ? round(($report['online_devices'] / $report['total_devices']) * 100, 1)
                : 0;
            $message .= "Online: <b>{$report['online_devices']}</b> ({$onlinePercent}%)\n";
            $message .= "Offline: <b>{$report['offline_devices']}</b>\n\n";

            $message .= "<b>Aktivitas Hari Ini</b>\n";
            $message .= "Masuk online: <b>{$report['new_online_count']}</b>\n";
            $message .= "Masuk offline: <b>{$report['new_offline_count']}</b>\n\n";

            $message .= "<b>Alarm Ringkas</b>\n";
            $message .= "Offline >24 jam: <b>{$report['offline_24h_count']}</b>\n";
            $message .= "Redaman kritis: <b>{$report['critical_signal_count']}</b>\n";
            $message .= "Redaman warning: <b>{$report['warning_signal_count']}</b>\n";
            $message .= "ONT online belum ACS: <b>{$report['missing_acs_count']}</b>\n";
            $message .= "OLT bermasalah: <b>{$report['olt_problem_count']}</b>\n\n";

            // Show top issues
            if (!empty($report['top_issues'])) {
                $message .= "<b>Redaman Terburuk</b>\n";
                foreach (array_slice($report['top_issues'], 0, 3) as $issue) {
                    $message .= "• <code>{$issue['serial']}</code>: {$issue['rx_power']} dBm\n";
                }
            }

        } elseif ($report['type'] === 'weekly') {
            $message .= "Periode: " . date('Y-m-d', strtotime($report['start_date'])) . " - "
                     . date('Y-m-d', strtotime($report['end_date'])) . "\n";
            $message .= "Safe Mode: ON / read-only\n\n";

            $message .= "<b>Status ACS</b>\n";
            $message .= "Total perangkat: <b>{$report['total_devices']}</b>\n";
            $message .= "Online: <b>{$report['online_devices']}</b> ({$report['avg_uptime_percent']}%)\n";
            $message .= "Offline: <b>{$report['offline_devices']}</b>\n\n";

            $message .= "<b>Aktivitas Mingguan</b>\n";
            $message .= "Event online: <b>{$report['total_online_events']}</b>\n";
            $message .= "Event offline: <b>{$report['total_offline_events']}</b>\n\n";

            $message .= "<b>Alarm Ringkas</b>\n";
            $message .= "Redaman kritis: <b>{$report['critical_signal_count']}</b>\n";
            $message .= "Redaman warning: <b>{$report['warning_signal_count']}</b>\n";
            $message .= "ONT online belum ACS: <b>{$report['missing_acs_count']}</b>\n";
            $message .= "OLT bermasalah: <b>{$report['olt_problem_count']}</b>\n\n";

            // Show daily breakdown (last 3 days)
            if (!empty($report['daily_breakdown'])) {
                $message .= "<b>3 Hari Terakhir</b>\n";
                $recentDays = array_slice($report['daily_breakdown'], -3);
                foreach ($recentDays as $day) {
                    $dayName = date('Y-m-d', strtotime($day['date']));
                    $message .= "• {$dayName}: +{$day['online']} / -{$day['offline']}\n";
                }
            }
        }

        $message .= "\nDibuat: " . date('Y-m-d H:i:s');

        return $message;
    }

    private function getInventorySummary(array $acsSerials): array {
        $summary = [
            'critical_signal_count' => 0,
            'warning_signal_count' => 0,
            'missing_acs_count' => 0,
            'olt_problem_count' => 0,
        ];

        $result = $this->conn->query("
            SELECT
                inv.serial_number,
                inv.status,
                inv.rx_power,
                olt.id AS olt_id
            FROM olt_onu_inventory inv
            INNER JOIN map_items olt ON olt.id = inv.olt_item_id AND olt.item_type = 'olt'
        ");
        if (!$result) {
            return $summary;
        }

        $oltProblems = [];
        while ($row = $result->fetch_assoc()) {
            $rx = $row['rx_power'];
            if ($rx !== null && $rx !== '' && is_numeric($rx)) {
                $rxValue = (float) $rx;
                if ($rxValue <= -28) {
                    $summary['critical_signal_count']++;
                    $oltProblems[(int) $row['olt_id']] = true;
                } elseif ($rxValue <= -25) {
                    $summary['warning_signal_count']++;
                }
            }

            $serial = strtolower(trim((string) ($row['serial_number'] ?? '')));
            if (
                strtolower((string) ($row['status'] ?? '')) === 'online'
                && $serial !== ''
                && !isset($acsSerials[$serial])
            ) {
                $summary['missing_acs_count']++;
                $oltProblems[(int) $row['olt_id']] = true;
            }
        }

        $summary['olt_problem_count'] = count($oltProblems);
        return $summary;
    }

    /**
     * Log report to database
     *
     * @param string $chatId Telegram chat ID
     * @param array $report Report data
     * @return bool Success status
     */
    public function logReport($chatId, $report) {
        $reportDate = $report['type'] === 'daily' ? $report['date'] : $report['end_date'];
        $reportDataJson = json_encode($report);

        $stmt = $this->conn->prepare("
            INSERT INTO telegram_report_logs
            (chat_id, report_type, report_date, total_devices, online_devices, offline_devices,
             new_online_count, new_offline_count, offline_24h_count, poor_signal_count, report_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $newOnline = $report['new_online_count'] ?? ($report['total_online_events'] ?? 0);
        $newOffline = $report['new_offline_count'] ?? ($report['total_offline_events'] ?? 0);
        $offline24h = $report['offline_24h_count'] ?? 0;

        $stmt->bind_param(
            "sssiiiiiiis",
            $chatId,
            $report['type'],
            $reportDate,
            $report['total_devices'],
            $report['online_devices'],
            $report['offline_devices'],
            $newOnline,
            $newOffline,
            $offline24h,
            $report['poor_signal_count'],
            $reportDataJson
        );

        return $stmt->execute();
    }
}
