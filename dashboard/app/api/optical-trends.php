<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
requireLogin();

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT config_key, config_value, updated_at
    FROM configurations
    WHERE config_key LIKE 'telegram_rx_trend_%'
    ORDER BY updated_at DESC
    LIMIT 1000
");

if (!$stmt) {
    jsonResponse(['success' => false, 'message' => 'Gagal membaca trend optik']);
}

$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $state = json_decode((string) ($row['config_value'] ?? ''), true);
    if (!is_array($state)) {
        continue;
    }

    $history = is_array($state['history'] ?? null) ? $state['history'] : [];
    $baseline = null;
    $current = null;
    foreach ($history as $point) {
        if (!isset($point['rx']) || !is_numeric($point['rx'])) {
            continue;
        }
        $rx = (float) $point['rx'];
        $baseline = $baseline === null ? $rx : max($baseline, $rx);
        $current = $rx;
    }

    if ($baseline === null || $current === null) {
        continue;
    }

    $drop = round($baseline - $current, 2);
    if ($drop <= 0) {
        continue;
    }

    $items[] = [
        'device_id' => $state['device_id'] ?? 'N/A',
        'serial_number' => $state['serial_number'] ?? 'N/A',
        'olt_name' => $state['olt_name'] ?? 'N/A',
        'customer_name' => $state['customer_name'] ?? 'N/A',
        'baseline_rx' => $baseline,
        'current_rx' => $current,
        'drop_db' => $drop,
        'updated_at' => $state['updated_at'] ?? $row['updated_at'],
        'last_alert_at' => $state['last_alert_at'] ?? null,
    ];
}

usort($items, static function (array $a, array $b): int {
    return [$b['drop_db'], strcmp((string) $b['updated_at'], (string) $a['updated_at'])]
        <=> [$a['drop_db'], strcmp((string) $a['updated_at'], (string) $b['updated_at'])];
});

$critical = count(array_filter($items, static fn(array $item): bool => (float) $item['drop_db'] >= 6.0));
$warning = count(array_filter($items, static fn(array $item): bool => (float) $item['drop_db'] >= 3.0 && (float) $item['drop_db'] < 6.0));

$inventorySummary = [];
$inventoryResult = $conn->query("
    SELECT
        olt.id AS olt_item_id,
        olt.name AS olt_name,
        COUNT(inv.id) AS total,
        COALESCE(SUM(CASE WHEN inv.rx_power IS NULL THEN 1 ELSE 0 END), 0) AS no_rx_total,
        COALESCE(SUM(CASE WHEN inv.rx_power > -25 THEN 1 ELSE 0 END), 0) AS normal_total,
        COALESCE(SUM(CASE WHEN inv.rx_power > -28 AND inv.rx_power <= -25 THEN 1 ELSE 0 END), 0) AS warning_total,
        COALESCE(SUM(CASE WHEN inv.rx_power <= -28 THEN 1 ELSE 0 END), 0) AS critical_total,
        COALESCE(MIN(inv.rx_power), 0) AS worst_rx,
        MAX(inv.last_synced_at) AS last_synced_at
    FROM map_items olt
    LEFT JOIN olt_onu_inventory inv ON inv.olt_item_id = olt.id
    WHERE olt.item_type = 'olt'
    GROUP BY olt.id, olt.name
    ORDER BY critical_total DESC, warning_total DESC, no_rx_total DESC, olt.name ASC
");

if ($inventoryResult) {
    while ($row = $inventoryResult->fetch_assoc()) {
        $inventorySummary[] = [
            'olt_item_id' => (int) $row['olt_item_id'],
            'olt_name' => $row['olt_name'],
            'total' => (int) $row['total'],
            'normal_total' => (int) $row['normal_total'],
            'warning_total' => (int) $row['warning_total'],
            'critical_total' => (int) $row['critical_total'],
            'no_rx_total' => (int) $row['no_rx_total'],
            'worst_rx' => $row['worst_rx'] !== null ? (float) $row['worst_rx'] : null,
            'last_synced_at' => $row['last_synced_at'],
        ];
    }
}

$distribution = [
    'inventory_olt_total' => count($inventorySummary),
    'inventory_total' => array_sum(array_map(static fn(array $row): int => (int) $row['total'], $inventorySummary)),
    'normal_total' => array_sum(array_map(static fn(array $row): int => (int) $row['normal_total'], $inventorySummary)),
    'warning_total' => array_sum(array_map(static fn(array $row): int => (int) $row['warning_total'], $inventorySummary)),
    'critical_total' => array_sum(array_map(static fn(array $row): int => (int) $row['critical_total'], $inventorySummary)),
    'no_rx_total' => array_sum(array_map(static fn(array $row): int => (int) $row['no_rx_total'], $inventorySummary)),
];

jsonResponse([
    'success' => true,
    'summary' => [
        'total' => count($items),
        'critical' => $critical,
        'warning' => $warning,
    ],
    'distribution' => $distribution,
    'inventory_by_olt' => array_slice($inventorySummary, 0, 12),
    'items' => array_slice($items, 0, 12),
]);
