<?php

namespace App;

use mysqli;
use RuntimeException;

class OltRegistry
{
    public static function list(mysqli $conn): array
    {
        OltInventorySync::ensureSchema($conn);

        $sql = "
            SELECT
                mi.id,
                mi.name,
                mi.latitude,
                mi.longitude,
                mi.status,
                mi.properties,
                oc.pon_count,
                oc.olt_link,
                COALESCE(COUNT(oi.id), 0) AS inventory_total,
                COALESCE(SUM(CASE WHEN oi.status = 'online' THEN 1 ELSE 0 END), 0) AS online_total,
                COALESCE(SUM(CASE WHEN oi.status = 'offline' THEN 1 ELSE 0 END), 0) AS offline_total,
                COALESCE(SUM(CASE WHEN oi.status = 'unknown' THEN 1 ELSE 0 END), 0) AS unknown_total,
                MAX(oi.last_synced_at) AS last_synced_at
            FROM map_items mi
            LEFT JOIN olt_config oc ON oc.map_item_id = mi.id
            LEFT JOIN olt_onu_inventory oi ON oi.olt_item_id = mi.id
            WHERE mi.item_type = 'olt'
            GROUP BY
                mi.id, mi.name, mi.latitude, mi.longitude, mi.status, mi.properties, oc.pon_count, oc.olt_link
            ORDER BY mi.name ASC
        ";

        $result = $conn->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $properties = json_decode($row['properties'] ?? '{}', true);
            if (!is_array($properties)) {
                $properties = [];
            }

            $rows[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'status' => $row['status'] ?: 'unknown',
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'brand' => (string) ($properties['brand'] ?? ''),
                'model' => (string) ($properties['model'] ?? ''),
                'area' => (string) ($properties['area'] ?? ''),
                'notes' => (string) ($properties['notes'] ?? ''),
                'preferred_protocol' => (string) ($properties['preferred_protocol'] ?? 'telnet'),
                'is_active' => (bool) ($properties['is_active'] ?? true),
                'ip_address' => (string) (($properties['ip_address'] ?? '') ?: ($row['olt_link'] ?? '')),
                'pon_count' => (int) ($row['pon_count'] ?? 0),
                'inventory_total' => (int) $row['inventory_total'],
                'online_total' => (int) $row['online_total'],
                'offline_total' => (int) $row['offline_total'],
                'unknown_total' => (int) $row['unknown_total'],
                'last_synced_at' => $row['last_synced_at'],
                'sync_state' => (string) ($properties['inventory_sync_state'] ?? ''),
                'sync_last_attempt_at' => (string) ($properties['inventory_last_attempt_at'] ?? ''),
                'sync_last_error' => (string) ($properties['inventory_last_error'] ?? ''),
                'credentials' => [
                    'telnet_user' => (string) ($properties['telnet_user'] ?? ''),
                    'telnet_pass' => (string) ($properties['telnet_pass'] ?? ''),
                    'telnet_port' => (int) ($properties['telnet_port'] ?? 23),
                    'ssh_user' => (string) ($properties['ssh_user'] ?? ''),
                    'ssh_pass' => (string) ($properties['ssh_pass'] ?? ''),
                    'ssh_port' => (int) ($properties['ssh_port'] ?? 22),
                    'ssh_enable_pass' => (string) ($properties['ssh_enable_pass'] ?? ''),
                    'snmp_community' => (string) ($properties['snmp_community'] ?? ''),
                    'snmp_version' => (string) ($properties['snmp_version'] ?? '2c'),
                    'snmp_username' => (string) ($properties['snmp_username'] ?? ''),
                    'snmp_auth_pass' => (string) ($properties['snmp_auth_pass'] ?? ''),
                    'api_url' => (string) ($properties['api_url'] ?? ''),
                    'api_token' => (string) ($properties['api_token'] ?? ''),
                ],
            ];
        }

        return $rows;
    }

    public static function save(mysqli $conn, array $payload): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $name = trim((string) ($payload['name'] ?? ''));
        $brand = trim((string) ($payload['brand'] ?? ''));
        $model = trim((string) ($payload['model'] ?? ''));
        $ipAddress = trim((string) ($payload['ip_address'] ?? ''));
        $area = trim((string) ($payload['area'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $preferredProtocol = strtolower(trim((string) ($payload['preferred_protocol'] ?? 'telnet')));
        $ponCount = (int) ($payload['pon_count'] ?? 1);
        $isActive = isset($payload['is_active']) ? (bool) $payload['is_active'] : true;

        if ($name === '' || $ipAddress === '') {
            throw new RuntimeException('Nama OLT dan IP wajib diisi.');
        }

        if (!in_array($preferredProtocol, ['telnet', 'ssh', 'snmp', 'rest'], true)) {
            $preferredProtocol = 'telnet';
        }

        if ($ponCount < 1) {
            $ponCount = 1;
        }
        if ($ponCount > 16) {
            $ponCount = 16;
        }

        $existingProperties = [];
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT properties FROM map_items WHERE id = ? AND item_type = 'olt' LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $existingProperties = json_decode((string) ($row['properties'] ?? '{}'), true);
            if (!is_array($existingProperties)) {
                $existingProperties = [];
            }
        }

        $properties = [
            'brand' => $brand,
            'model' => $model,
            'ip_address' => $ipAddress,
            'area' => $area,
            'notes' => $notes,
            'preferred_protocol' => $preferredProtocol,
            'is_active' => $isActive,
            'olt_link' => $ipAddress,
            'telnet_user' => trim((string) ($payload['telnet_user'] ?? '')),
            'telnet_pass' => trim((string) ($payload['telnet_pass'] ?? '')),
            'telnet_port' => (int) ($payload['telnet_port'] ?? 23),
            'ssh_user' => trim((string) ($payload['ssh_user'] ?? '')),
            'ssh_pass' => trim((string) ($payload['ssh_pass'] ?? '')),
            'ssh_port' => (int) ($payload['ssh_port'] ?? 22),
            'ssh_enable_pass' => trim((string) ($payload['ssh_enable_pass'] ?? '')),
            'snmp_community' => trim((string) ($payload['snmp_community'] ?? '')),
            'snmp_version' => trim((string) ($payload['snmp_version'] ?? '2c')),
            'snmp_username' => trim((string) ($payload['snmp_username'] ?? '')),
            'snmp_auth_pass' => trim((string) ($payload['snmp_auth_pass'] ?? '')),
            'api_url' => trim((string) ($payload['api_url'] ?? '')),
            'api_token' => trim((string) ($payload['api_token'] ?? '')),
            // OLT registry item is managed from OLT page, not topology marker.
            // This avoids accidental marker drop at coordinates 0,0.
            'hidden_marker' => $id > 0 ? (bool) ($existingProperties['hidden_marker'] ?? true) : true,
        ];

        $propertiesJson = json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($propertiesJson === false) {
            throw new RuntimeException('Gagal menyimpan properti OLT.');
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE map_items
                SET name = ?, properties = ?
                WHERE id = ? AND item_type = 'olt'
            ");
            $stmt->bind_param("ssi", $name, $propertiesJson, $id);
            $stmt->execute();
            if ($stmt->affected_rows < 0) {
                throw new RuntimeException('Gagal update OLT.');
            }
        } else {
            $itemType = 'olt';
            $parentId = null;
            $latitude = 0.0;
            $longitude = 0.0;
            $status = 'unknown';
            $genieacsDeviceId = null;
            $stmt = $conn->prepare("
                INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sisddsss", $itemType, $parentId, $name, $latitude, $longitude, $genieacsDeviceId, $propertiesJson, $status);
            $stmt->execute();
            $id = (int) $conn->insert_id;
        }

        $stmt = $conn->prepare("SELECT map_item_id FROM olt_config WHERE map_item_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $attenuationDb = 0.0;
        $outputPower = 0.0;
        if ($existing) {
            $stmt = $conn->prepare("
                UPDATE olt_config
                SET output_power = ?, pon_count = ?, attenuation_db = ?, olt_link = ?
                WHERE map_item_id = ?
            ");
            $stmt->bind_param("didsi", $outputPower, $ponCount, $attenuationDb, $ipAddress, $id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("idids", $id, $outputPower, $ponCount, $attenuationDb, $ipAddress);
            $stmt->execute();
        }

        return ['id' => $id];
    }

    public static function delete(mysqli $conn, int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('ID OLT tidak valid.');
        }

        $stmt = $conn->prepare("DELETE FROM olt_onu_inventory WHERE olt_item_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM olt_pon_ports WHERE olt_item_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM olt_config WHERE map_item_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM map_items WHERE id = ? AND item_type = 'olt'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
