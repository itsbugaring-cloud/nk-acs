<?php

require_once __DIR__ . '/../config/config.php';

use App\OltInventorySync;

$conn = getDBConnection();
OltInventorySync::ensureSchema($conn);

$itemId = isset($argv[1]) ? (int) $argv[1] : null;
$whereSql = $itemId ? 'WHERE mi.item_type = \'olt\' AND mi.id = ' . $itemId : 'WHERE mi.item_type = \'olt\'';

$result = $conn->query("
    SELECT mi.*, oc.olt_link, oc.pon_count
    FROM map_items mi
    LEFT JOIN olt_config oc ON oc.map_item_id = mi.id
    {$whereSql}
    ORDER BY mi.id ASC
");

$items = [];
while ($row = $result->fetch_assoc()) {
    $row['properties'] = json_decode($row['properties'] ?? '{}', true) ?: [];
    $row['config'] = [
        'olt_link' => $row['olt_link'] ?? null,
        'pon_count' => isset($row['pon_count']) ? (int) $row['pon_count'] : null,
    ];
    $items[] = $row;
}

$summary = [
    'success' => true,
    'items' => [],
];

foreach ($items as $item) {
    try {
        $sync = new OltInventorySync($item);
        $summary['items'][] = $sync->sync($conn);
    } catch (\Throwable $e) {
        OltInventorySync::markSyncFailure($conn, $item, $e->getMessage());
        $summary['items'][] = [
            'olt_id' => (int) $item['id'],
            'olt_name' => $item['name'],
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
