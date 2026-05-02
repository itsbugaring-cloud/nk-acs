<?php

namespace App;

use mysqli;
use RuntimeException;

class OltInventorySync
{
    private array $olt;

    public function __construct(array $olt)
    {
        $this->olt = $olt;
    }

    public static function ensureSchema(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS olt_onu_inventory (
                id INT(11) NOT NULL AUTO_INCREMENT,
                olt_item_id INT(11) NOT NULL,
                serial_number VARCHAR(64) NOT NULL,
                pon_port VARCHAR(32) DEFAULT NULL,
                ont_index INT(11) DEFAULT NULL,
                description VARCHAR(255) DEFAULT NULL,
                status ENUM('online','offline','unknown') DEFAULT 'unknown',
                rx_power DECIMAL(8,2) DEFAULT NULL,
                tx_power DECIMAL(8,2) DEFAULT NULL,
                distance INT(11) DEFAULT NULL,
                firmware_version VARCHAR(128) DEFAULT NULL,
                equipment_id VARCHAR(128) DEFAULT NULL,
                last_synced_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL DEFAULT current_timestamp(),
                updated_at TIMESTAMP NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_olt_serial (olt_item_id, serial_number),
                KEY idx_olt_item_id (olt_item_id),
                CONSTRAINT fk_olt_onu_inventory_olt
                    FOREIGN KEY (olt_item_id) REFERENCES map_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public static function markSyncFailure(mysqli $conn, array $olt, string $error): void
    {
        self::updateOltProperties($conn, (int) $olt['id'], [
            'inventory_sync_state' => 'error',
            'inventory_last_attempt_at' => date('Y-m-d H:i:s'),
            'inventory_last_error' => $error,
        ]);
    }

    public function sync(mysqli $conn): array
    {
        self::ensureSchema($conn);
        $records = $this->fetchViaTelnet();

        $created = 0;
        $updated = 0;
        $syncedSerials = [];
        $onlineTotal = 0;
        $offlineTotal = 0;
        $unknownTotal = 0;

        foreach ($records as $record) {
            $serial = strtoupper(trim((string) ($record['serial_number'] ?? '')));
            if ($serial === '') {
                continue;
            }

            $syncedSerials[] = $serial;

            $existingStmt = $conn->prepare("
                SELECT id
                FROM olt_onu_inventory
                WHERE olt_item_id = ? AND serial_number = ?
                LIMIT 1
            ");
            $oltItemId = (int) $this->olt['id'];
            $existingStmt->bind_param("is", $oltItemId, $serial);
            $existingStmt->execute();
            $existing = $existingStmt->get_result()->fetch_assoc();

            $ponPort = $record['pon_port'] ?? null;
            $ontIndex = $record['olt_port_index'] ?? null;
            $description = $record['description'] ?? null;
            $status = $record['status'] ?? 'unknown';
            $rxPower = $record['rx_power'] ?? null;
            $txPower = $record['tx_power'] ?? null;
            $distance = $record['distance'] ?? null;
            $firmware = $record['firmware_version'] ?? null;
            $equipmentId = $record['equipment_id'] ?? null;

            if ($status === 'online') {
                $onlineTotal++;
            } elseif ($status === 'offline') {
                $offlineTotal++;
            } else {
                $unknownTotal++;
            }

            if ($existing) {
                $stmt = $conn->prepare("
                    UPDATE olt_onu_inventory
                    SET pon_port = ?, ont_index = ?, description = ?, status = ?, rx_power = ?, tx_power = ?,
                        distance = ?, firmware_version = ?, equipment_id = ?, last_synced_at = NOW()
                    WHERE id = ?
                ");
                $id = (int) $existing['id'];
                $stmt->bind_param(
                    "sissddissi",
                    $ponPort,
                    $ontIndex,
                    $description,
                    $status,
                    $rxPower,
                    $txPower,
                    $distance,
                    $firmware,
                    $equipmentId,
                    $id
                );
                $stmt->execute();
                $updated++;
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO olt_onu_inventory
                        (olt_item_id, serial_number, pon_port, ont_index, description, status, rx_power, tx_power, distance, firmware_version, equipment_id, last_synced_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param(
                    "ississddiss",
                    $oltItemId,
                    $serial,
                    $ponPort,
                    $ontIndex,
                    $description,
                    $status,
                    $rxPower,
                    $txPower,
                    $distance,
                    $firmware,
                    $equipmentId
                );
                $stmt->execute();
                $created++;
            }
        }

        if (!empty($syncedSerials)) {
            $quoted = array_map(static fn(string $serial) => "'" . $conn->real_escape_string($serial) . "'", array_unique($syncedSerials));
            $conn->query("
                DELETE FROM olt_onu_inventory
                WHERE olt_item_id = " . (int) $this->olt['id'] . "
                  AND serial_number NOT IN (" . implode(',', $quoted) . ")
            ");
        }

        self::updateOltProperties($conn, (int) $this->olt['id'], [
            'inventory_sync_state' => 'success',
            'inventory_last_attempt_at' => date('Y-m-d H:i:s'),
            'inventory_last_synced_at' => date('Y-m-d H:i:s'),
            'inventory_last_error' => null,
            'inventory_last_total' => count($records),
            'inventory_last_online_total' => $onlineTotal,
            'inventory_last_offline_total' => $offlineTotal,
            'inventory_last_unknown_total' => $unknownTotal,
        ]);

        return [
            'olt_id' => (int) $this->olt['id'],
            'olt_name' => $this->olt['name'],
            'created' => $created,
            'updated' => $updated,
            'total' => count($records),
            'online_total' => $onlineTotal,
            'offline_total' => $offlineTotal,
            'unknown_total' => $unknownTotal,
        ];
    }

    private static function updateOltProperties(mysqli $conn, int $oltId, array $updates): void
    {
        $stmt = $conn->prepare("SELECT properties FROM map_items WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $oltId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $properties = json_decode($row['properties'] ?? '{}', true);
        if (!is_array($properties)) {
            $properties = [];
        }

        foreach ($updates as $key => $value) {
            if ($value === null) {
                unset($properties[$key]);
            } else {
                $properties[$key] = $value;
            }
        }

        $encoded = json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $updateStmt = $conn->prepare("UPDATE map_items SET properties = ? WHERE id = ?");
        $updateStmt->bind_param("si", $encoded, $oltId);
        $updateStmt->execute();
    }

    private function fetchViaTelnet(): array
    {
        if ($this->isCData()) {
            return $this->fetchCData();
        }
        if ($this->isHsgq()) {
            return $this->fetchHsgq();
        }
        return $this->fetchTenda();
    }

    private function isTenda(): bool
    {
        $value = strtolower(($this->olt['properties']['brand'] ?? '') . ' ' . ($this->olt['properties']['model'] ?? '') . ' ' . ($this->olt['name'] ?? ''));
        return str_contains($value, 'tenda') || str_contains($value, 'tes7001');
    }

    private function isCData(): bool
    {
        $value = strtolower(($this->olt['properties']['brand'] ?? '') . ' ' . ($this->olt['properties']['model'] ?? '') . ' ' . ($this->olt['name'] ?? ''));
        return str_contains($value, 'c-data') || str_contains($value, 'cdata') || preg_match('/\bfd1(604|608|616)/', $value) === 1;
    }

    private function isHsgq(): bool
    {
        $value = strtolower(($this->olt['properties']['brand'] ?? '') . ' ' . ($this->olt['properties']['model'] ?? '') . ' ' . ($this->olt['name'] ?? ''));
        return str_contains($value, 'hsgq') || str_contains($value, 'g02id');
    }

    private function telnetHost(): string
    {
        return (string) (($this->olt['config']['olt_link'] ?? null) ?: ($this->olt['properties']['olt_link'] ?? ''));
    }

    private function telnetUser(): string
    {
        if (!empty($this->olt['properties']['telnet_user'])) {
            return (string) $this->olt['properties']['telnet_user'];
        }
        if ($this->isCData()) {
            return 'root';
        }
        if ($this->isHsgq()) {
            return 'netking';
        }
        return 'admin';
    }

    private function telnetPass(): string
    {
        if (!empty($this->olt['properties']['telnet_pass'])) {
            return (string) $this->olt['properties']['telnet_pass'];
        }
        if ($this->isCData()) {
            return 'admin';
        }
        if ($this->isHsgq()) {
            return 'netking';
        }
        return 'admin';
    }

    private function telnetPort(): int
    {
        return (int) (($this->olt['properties']['telnet_port'] ?? null) ?: 23);
    }

    private function telnetConnect()
    {
        $host = $this->telnetHost();
        $port = $this->telnetPort();

        if ($host === '') {
            throw new RuntimeException('OLT link/IP belum dikonfigurasi.');
        }

        $sock = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$sock) {
            throw new RuntimeException("Telnet connect failed: {$host}:{$port} — {$errstr}");
        }

        stream_set_timeout($sock, 8);
        @fwrite($sock, "\xff\xfb\x01\xff\xfb\x03");
        usleep(100000);
        @fread($sock, 512);
        return $sock;
    }

    private function telnetLogin($sock): void
    {
        $this->telnetReadUntil($sock, ['Username:', 'login:', 'name:', 'User:'], 5);
        $this->telnetSend($sock, $this->telnetUser());

        $this->telnetReadUntil($sock, ['Password:', 'assword:'], 5);
        $this->telnetSend($sock, $this->telnetPass());

        $prompt = $this->telnetReadUntil($sock, ['>', '#', '$'], 5);
        if (str_ends_with(trim($prompt), '>')) {
            $this->telnetSend($sock, 'enable');
            $enableResp = $this->telnetReadUntil($sock, ['Password:', '#'], 5);
            if (stripos($enableResp, 'assword') !== false) {
                $this->telnetSend($sock, $this->telnetPass());
                $this->telnetReadUntil($sock, ['#'], 5);
            }
        }
    }

    private function telnetLogout($sock): void
    {
        try {
            $this->telnetSend($sock, 'exit');
            usleep(200000);
            $this->telnetSend($sock, 'exit');
        } catch (\Throwable) {
        }
        @fclose($sock);
    }

    private function telnetSend($sock, string $command): void
    {
        $result = @fwrite($sock, $command . "\r\n");
        if ($result === false) {
            throw new RuntimeException("Telnet send failed — koneksi ke OLT terputus");
        }
    }

    private function telnetReadUntil($sock, array $prompts, int $timeoutSec): string
    {
        $buffer = '';
        $start = time();

        while (time() - $start < $timeoutSec) {
            $chunk = @fread($sock, 4096);
            if ($chunk === false || $chunk === '') {
                usleep(200000);
                continue;
            }
            $buffer .= $chunk;
            foreach ($prompts as $prompt) {
                if (str_contains($buffer, $prompt)) {
                    return $buffer;
                }
            }
        }

        return $buffer;
    }

    private function telnetReadMore($sock, int $timeoutSec): string
    {
        $buffer = '';
        $start = time();

        while (time() - $start < $timeoutSec) {
            $chunk = @fread($sock, 8192);
            if ($chunk === false || $chunk === '') {
                usleep(200000);
                continue;
            }
            $buffer .= $chunk;
            if (str_contains($buffer, '--More--')) {
                @fwrite($sock, ' ');
                $buffer = str_replace('--More--', '', $buffer);
            }
            if (preg_match('/[\\]>$#]\s*$/', $buffer)) {
                break;
            }
        }

        return $buffer;
    }

    private function cleanTelnet(string $value): string
    {
        $value = preg_replace('/\xff[\xfb-\xfe]./s', '', $value);
        return preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $value);
    }

    private function fetchTenda(): array
    {
        $sock = $this->telnetConnect();
        $this->telnetLogin($sock);

        $this->telnetSend($sock, 'ont');
        $this->telnetReadUntil($sock, ['ont#'], 3);

        $authOutput = '';
        foreach ([1, 2, 3, 4] as $slot) {
            $this->telnetSend($sock, "display ont-auth-info slotno {$slot}");
            $resp = $this->telnetReadMore($sock, 8);
            if (stripos($resp, 'invalid') !== false || stripos($resp, 'error') !== false) {
                continue;
            }
            $authOutput .= $resp . "\n";
        }

        $statusOutput = '';
        foreach ([1, 2, 3, 4] as $slot) {
            foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $pon) {
                $this->telnetSend($sock, "display ont-status {$slot} {$pon}");
                $resp = $this->telnetReadMore($sock, 5);
                if (stripos($resp, 'invalid') !== false || stripos($resp, 'error') !== false) {
                    continue;
                }
                $statusOutput .= $resp . "\n";
            }
        }

        $basicOutput = '';
        foreach ([1, 2, 3, 4] as $slot) {
            $this->telnetSend($sock, "display ont-basic-info {$slot}");
            $resp = $this->telnetReadMore($sock, 10);
            if (stripos($resp, 'invalid') !== false || stripos($resp, 'error') !== false) {
                continue;
            }
            $basicOutput .= $resp . "\n";
        }

        $ontMap = $this->parseTendaAuth($authOutput, $statusOutput, $basicOutput);

        foreach ($ontMap as $idx => &$record) {
            [$slot, $pon, $ontId] = explode('/', $idx);

            $this->telnetSend($sock, "display ont-name {$slot} {$pon} {$ontId}");
            $nameResp = $this->telnetReadMore($sock, 2);
            if (preg_match('/ont name:\s*(.+)/i', $nameResp, $match)) {
                $record['description'] = trim($match[1]);
            }

            if (($record['status'] ?? 'unknown') === 'online') {
                $this->telnetSend($sock, "display ont-optical-info {$slot} {$pon} {$ontId}");
                $optResp = $this->telnetReadMore($sock, 2);
                if (preg_match('/receivedOpticalPower:\s*([-\d.]+)\(Dbm\)/i', $optResp, $rx)) {
                    $record['rx_power'] = (float) $rx[1];
                }
                if (preg_match('/transmittedOpticalPower:\s*([-\d.]+)\(Dbm\)/i', $optResp, $tx)) {
                    $record['tx_power'] = (float) $tx[1];
                }
            }
        }
        unset($record);

        $this->telnetLogout($sock);
        return array_values($ontMap);
    }

    private function parseTendaAuth(string $authRaw, string $statusRaw, string $basicRaw = ''): array
    {
        $authRaw = $this->cleanTelnet($authRaw);
        $statusRaw = $this->cleanTelnet($statusRaw);
        $basicRaw = $this->cleanTelnet($basicRaw);
        $ontMap = [];

        foreach (explode("\n", $authRaw) as $line) {
            $line = trim($line);
            if (preg_match('/(\d+)\/(\d+)\/(\d+)\s+\S+\s+\S*\s+([A-Za-z0-9]{8,16})\s/i', $line, $m)) {
                $idx = "{$m[1]}/{$m[2]}/{$m[3]}";
                $ontMap[$idx] = [
                    'serial_number' => strtoupper($m[4]),
                    'equipment_id' => null,
                    'pon_port' => "{$m[1]}/{$m[2]}",
                    'olt_port_index' => (int) $m[3],
                    'status' => str_contains($line, 'Not online') ? 'offline' : 'online',
                    'rx_power' => null,
                    'tx_power' => null,
                    'distance' => null,
                    'description' => null,
                    'firmware_version' => null,
                ];
            }
        }

        foreach (explode("\n", $statusRaw) as $line) {
            $line = trim($line);
            if (preg_match('/\d+\s+(\d+)\/(\d+)\/(\d+)\s+\S+\s+(up|down)/i', $line, $m)) {
                $idx = "{$m[1]}/{$m[2]}/{$m[3]}";
                if (isset($ontMap[$idx])) {
                    $ontMap[$idx]['status'] = strtolower($m[4]) === 'up' ? 'online' : 'offline';
                }
            }
        }

        foreach (explode("\n", $basicRaw) as $line) {
            $line = trim($line);
            if (!preg_match('/(\d+\/\d+\/\d+)/', $line, $idxMatch)) {
                continue;
            }
            $idx = $idxMatch[1];
            if (!isset($ontMap[$idx])) {
                continue;
            }
            $cols = preg_split('/\s+/', $line);
            $lastCol = end($cols);
            if (is_numeric($lastCol) && (int) $lastCol > 0) {
                $ontMap[$idx]['distance'] = (int) $lastCol;
            }
            foreach ($cols as $col) {
                if (preg_match('/^V[\d.]+$/i', $col)) {
                    $ontMap[$idx]['firmware_version'] = $col;
                }
            }
        }

        return $ontMap;
    }

    private function fetchHsgq(): array
    {
        $sock = $this->telnetConnect();
        $this->telnetLogin($sock);

        $this->telnetSend($sock, 'enable');
        $this->telnetReadUntil($sock, ['#'], 5);
        $this->telnetSend($sock, 'configure');
        $this->telnetReadUntil($sock, ['(config)#', '(config)>', '#'], 5);

        $this->telnetSend($sock, 'show ont-info all');
        $infoRaw = $this->telnetReadMore($sock, 12);

        $this->telnetSend($sock, 'show ont-optical all');
        $opticalRaw = $this->telnetReadMore($sock, 12);

        $this->telnetLogout($sock);
        return $this->parseHsgqOutput($infoRaw, $opticalRaw);
    }

    private function parseHsgqOutput(string $infoRaw, string $opticalRaw): array
    {
        $ontMap = [];
        foreach (explode("\n", $this->cleanTelnet($infoRaw)) as $line) {
            $line = trim($line);
            if (!preg_match('/^(\d+)\/(\d+)\s+GPON\s+([A-Za-z0-9]{8,20})\s+(\w+)\s+(\w+)/i', $line, $m)) {
                continue;
            }
            $description = null;
            if (preg_match('/\s{2,}(\S+)\s*$/', $line, $dm)) {
                $description = trim($dm[1]);
            }
            $key = "{$m[1]}/{$m[2]}";
            $ontMap[$key] = [
                'serial_number' => strtoupper($m[3]),
                'equipment_id' => null,
                'pon_port' => "{$m[1]}/0",
                'olt_port_index' => (int) $m[2],
                'status' => (strtolower($m[4]) === 'active' && strtolower($m[5]) === 'online') ? 'online' : 'offline',
                'rx_power' => null,
                'tx_power' => null,
                'distance' => null,
                'description' => $description !== '-' ? $description : null,
                'firmware_version' => null,
            ];
        }

        foreach (explode("\n", $this->cleanTelnet($opticalRaw)) as $line) {
            $line = trim($line);
            if (!preg_match('/^(\d+)\/(\d+)\s+([A-Za-z0-9]{8,20})\s+.*?([-\d.]+)\s+dBm\s+([-\d.]+)\s+dBm/i', $line, $m)) {
                continue;
            }
            $key = "{$m[1]}/{$m[2]}";
            if (isset($ontMap[$key])) {
                $ontMap[$key]['tx_power'] = (float) $m[4];
                $ontMap[$key]['rx_power'] = (float) $m[5];
            }
        }

        return array_values($ontMap);
    }

    private function fetchCData(): array
    {
        $sock = $this->telnetConnect();
        $this->telnetLogin($sock);

        $this->telnetSend($sock, 'show ont info all');
        $infoRaw = $this->telnetReadMore($sock, 12);

        $ontMap = $this->parseCDataInfo($infoRaw);
        if (empty($ontMap)) {
            $this->telnetLogout($sock);
            return [];
        }

        $portsByFs = [];
        foreach ($ontMap as $key => $record) {
            $ponPort = (string) ($record['pon_port'] ?? '');
            $ontId = (int) ($record['olt_port_index'] ?? 0);
            if ($ponPort === '' || $ontId <= 0) {
                continue;
            }

            $lastSlash = strrpos($ponPort, '/');
            if ($lastSlash === false) {
                continue;
            }

            $fs = substr($ponPort, 0, $lastSlash);
            $pon = substr($ponPort, $lastSlash + 1);
            if ($fs === '' || $pon === '') {
                continue;
            }

            $portsByFs[$fs][$pon][] = $ontId;
        }

        $containsAny = static function (string $haystack, array $needles): bool {
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        };

        $isUnsupportedResponse = static function (string $resp) use ($containsAny): bool {
            return $containsAny($resp, [
                '% Unknown',
                '% Invalid',
                'Unknown command',
                'unknown command',
                'Invalid command',
                'invalid command',
                'There is no matched command',
            ]);
        };

        $isInvalidPortResponse = static function (string $resp) use ($containsAny): bool {
            return $containsAny($resp, [
                '% Unknown',
                '% Invalid',
                'Unknown command',
                'unknown command',
                'Invalid command',
                'invalid command',
                'Incorrect F/S parameters',
                'Incorrect F/S parameter',
                'Failure: input parameter',
            ]);
        };

        $hasOpticalPayload = function (string $resp) use ($containsAny, $isInvalidPortResponse, $isUnsupportedResponse): bool {
            if ($isUnsupportedResponse($resp) || $isInvalidPortResponse($resp)) {
                return false;
            }

            $clean = trim($this->cleanTelnet($resp));
            if ($clean === '') {
                return false;
            }

            if ($containsAny($clean, [
                'There is no ONT available',
                'No related information to show',
                'No data',
            ])) {
                return false;
            }

            return preg_match('/Rx\s+power|Tx\s+power|Rx\s+Optical\s+Power|Tx\s+Optical\s+Power/i', $clean) === 1
                || preg_match('/^\d+\/\d+\s+\d+\s+\d+\b.*-?\d+\.\d+/m', $clean) === 1
                || preg_match('/^\d+\b.*-?\d+\.\d+/m', $clean) === 1;
        };

        $versionRaw = '';
        $opticalRaw = '';
        try {
            foreach ($portsByFs as $fs => $pons) {
                $this->telnetSend($sock, 'config');
                $this->telnetReadMore($sock, 3);
                $this->telnetSend($sock, "interface gpon {$fs}");
                $gponResp = $this->telnetReadMore($sock, 3);

                $enteredGponMode = !$isUnsupportedResponse($gponResp)
                    && !$isInvalidPortResponse($gponResp)
                    && !str_contains($gponResp, 'There is no matched command')
                    && !str_contains($gponResp, 'Unknown command');

                $probePon = null;
                foreach (array_keys($pons) as $pon) {
                    $probePon = $pon;
                    break;
                }

                if ($enteredGponMode && $probePon !== null) {
                    $this->telnetSend($sock, "show ont optical-info {$probePon} all");
                    $probeResp = $this->telnetReadMore($sock, 8);

                    if ($hasOpticalPayload($probeResp)) {
                        $opticalRaw .= "### PON {$fs}/{$probePon} ###\n{$probeResp}\n";
                    }

                    foreach (array_keys($pons) as $pon) {
                        if ((string) $pon === (string) $probePon) {
                            continue;
                        }

                        $this->telnetSend($sock, "show ont optical-info {$pon} all");
                        $resp = $this->telnetReadMore($sock, 8);
                        if ($hasOpticalPayload($resp)) {
                            $opticalRaw .= "### PON {$fs}/{$pon} ###\n{$resp}\n";
                        }
                    }
                }

                $this->telnetSend($sock, 'exit');
                $this->telnetReadMore($sock, 2);
                $this->telnetSend($sock, 'exit');
                $this->telnetReadMore($sock, 2);
            }

            if (trim($opticalRaw) === '') {
                $this->telnetSend($sock, 'show ont run-info all');
                $runInfoFallback = $this->telnetReadMore($sock, 10);
                if ($hasOpticalPayload($runInfoFallback)) {
                    $opticalRaw = $runInfoFallback;
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $this->telnetSend($sock, 'show ont version all');
            $versionRaw = $this->telnetReadMore($sock, 10);
        } catch (\Throwable $e) {
        }

        $this->telnetLogout($sock);

        $this->mergeCDataOptical($ontMap, $opticalRaw);
        $this->mergeCDataVersion($ontMap, $versionRaw);

        // Distance is useful, but for unstable C-Data sessions RX must win priority.
        // We fetch per-ONT detail in a second best-effort pass so a broken detail sweep
        // does not block optical inventory from being saved.
        try {
            $detailSock = $this->telnetConnect();
            $this->telnetLogin($detailSock);

            foreach ($portsByFs as $fs => $pons) {
                foreach ($pons as $pon => $ontIds) {
                    foreach ($ontIds as $ontId) {
                        $this->telnetSend($detailSock, "show ont info {$fs} {$pon} {$ontId}");
                        $detailResp = $this->telnetReadMore($detailSock, 6);
                        if (preg_match('/Distance\(m\)\s*:\s*(\d+)/i', $this->cleanTelnet($detailResp), $dm)) {
                            $key = "{$fs}/{$pon}/{$ontId}";
                            if (isset($ontMap[$key])) {
                                $ontMap[$key]['distance'] = (int) $dm[1];
                            }
                        }
                    }
                }
            }

            $this->telnetLogout($detailSock);
        } catch (\Throwable $e) {
            if (is_resource($detailSock ?? null)) {
                @fclose($detailSock);
            }
        }

        return array_values($ontMap);
    }

    private function parseCDataInfo(string $infoRaw): array
    {
        $ontMap = [];
        foreach (explode("\n", $this->cleanTelnet($infoRaw)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '-') || preg_match('/^\s*F[\/\s]S/i', $line)) {
                continue;
            }

            if (!preg_match('/^\s*(\d+\/\d+)\s+(\d+)\s+(\d+)\s+([A-Za-z0-9]{8,20})\s+(.*)/i', $line, $m)) {
                continue;
            }

            $status = 'unknown';
            if (preg_match('/\b(Online|Offline)\b/i', $m[5], $statusMatch)) {
                $status = strtolower($statusMatch[1]) === 'online' ? 'online' : 'offline';
            }

            $afterStatus = preg_replace('/^.*?\b(?:Online|Offline)\b\s*/i', '', $m[5]);
            $description = preg_replace('/^(?:\S+\s+){0,4}/', '', $afterStatus);
            $description = trim($description);
            if ($description === '' || $description === '-' || $description === '--') {
                $description = null;
            }

            $key = "{$m[1]}/{$m[2]}/{$m[3]}";
            $ontMap[$key] = [
                'serial_number' => strtoupper($m[4]),
                'equipment_id' => null,
                'pon_port' => "{$m[1]}/{$m[2]}",
                'olt_port_index' => (int) $m[3],
                'status' => $status,
                'rx_power' => null,
                'tx_power' => null,
                'distance' => null,
                'description' => $description,
                'firmware_version' => null,
            ];
        }

        return $ontMap;
    }

    private function mergeCDataOptical(array &$ontMap, string $opticalRaw): void
    {
        $currentFs = '0/0';
        $currentPon = '0';
        $clean = $this->cleanTelnet($opticalRaw);
        $multiLine = preg_match('/Rx\s+(?:Optical\s+)?Power\s*(?:\(dBm\))?\s*:|Tx\s+(?:Optical\s+)?Power\s*(?:\(dBm\))?\s*:/i', $clean) === 1;

        if ($multiLine) {
            $currentKey = null;
            foreach (explode("\n", $clean) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/###\s*PON\s+(\d+\/\d+)\/(\d+)\s*###/i', $line, $m)) {
                    $currentFs = $m[1];
                    $currentPon = $m[2];
                    $currentKey = null;
                    continue;
                }
                if (preg_match('/^(?:ONT|ONU|ONUID|ONT[-\s]?(?:Index|ID))\s*[:\s]+(?:ONT[-\s]?)?\s*(\d+)\/(\d+)\/(\d+)/i', $line, $m)) {
                    $currentKey = "{$m[1]}/{$m[2]}/{$m[3]}";
                    continue;
                }
                if (preg_match('/^(?:ONT[-\s]?(?:Index|ID)|ONUID|Index)\s*:\s*(\d+)\s*$/i', $line, $m)) {
                    $currentKey = "{$currentFs}/{$currentPon}/{$m[1]}";
                    continue;
                }
                if (!$currentKey || !isset($ontMap[$currentKey])) {
                    continue;
                }
                if (!preg_match('/\bOLT\b/i', $line) && preg_match('/Rx\s+(?:Optical\s+)?Power\s*(?:\(dBm\))?\s*:\s*(-?\d+\.?\d*)/i', $line, $rx)) {
                    $ontMap[$currentKey]['rx_power'] = (float) $rx[1];
                }
                if (preg_match('/Tx\s+(?:Optical\s+)?Power\s*(?:\(dBm\))?\s*:\s*(-?\d+\.?\d*)/i', $line, $tx)) {
                    $ontMap[$currentKey]['tx_power'] = (float) $tx[1];
                }
                if (preg_match('/Distance\s*(?:\(m\))?\s*:\s*(\d+)/i', $line, $dist)) {
                    $ontMap[$currentKey]['distance'] = (int) $dist[1];
                }
            }
            return;
        }

        foreach (explode("\n", $clean) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/###\s*PON\s+(\d+\/\d+)\/(\d+)\s*###/i', $line, $m)) {
                $currentFs = $m[1];
                $currentPon = $m[2];
                continue;
            }

            $key = null;
            if (preg_match('/^\s*(\d+\/\d+)\s+(\d+)\s+(\d+)\b/', $line, $hdr)) {
                $key = "{$hdr[1]}/{$hdr[2]}/{$hdr[3]}";
            } elseif (preg_match('/^\s*(\d+)\b/', $line, $hdr2)) {
                $key = "{$currentFs}/{$currentPon}/{$hdr2[1]}";
            }

            if (!$key || !isset($ontMap[$key])) {
                continue;
            }

            preg_match_all('/-?\d+\.\d+/', $line, $floats);
            $values = array_map('floatval', $floats[0] ?? []);
            if (count($values) >= 2) {
                $ontMap[$key]['rx_power'] = $values[0];
                $ontMap[$key]['tx_power'] = $values[1];
            }
            if (preg_match('/(\d+)\s*m\b/i', $line, $dist)) {
                $ontMap[$key]['distance'] = (int) $dist[1];
            }
        }
    }

    private function mergeCDataVersion(array &$ontMap, string $versionRaw): void
    {
        $clean = $this->cleanTelnet($versionRaw);
        foreach (explode("\n", $clean) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\s*(\d+\/\d+)\s+(\d+)\s+(\d+)\s+[A-Za-z0-9]{8,20}\s+(.*)$/i', $line, $m)) {
                $key = "{$m[1]}/{$m[2]}/{$m[3]}";
                if (!isset($ontMap[$key])) {
                    continue;
                }
                if (preg_match('/\b(V[\d.]+)\b/i', $m[4], $fw)) {
                    $ontMap[$key]['firmware_version'] = $fw[1];
                }
            }
        }
    }
}
