param(
    [string]$InventoryPath = "D:\ACS\genieacs-production-ready\inventory\olts.json",
    [string]$OutputSqlPath = "D:\ACS\genieacs-production-ready\tmp\sync-olts.sql",
    [double]$StartLatitude = -6.914744,
    [double]$StartLongitude = 107.609810,
    [double]$LatitudeStep = 0.035,
    [double]$LongitudeStep = 0.050,
    [int]$Columns = 4
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Escape-SqlString([string]$Value) {
    if ($null -eq $Value) {
        return ''
    }

    return $Value.Replace('\', '\\').Replace("'", "''")
}

if (-not (Test-Path -LiteralPath $InventoryPath)) {
    throw "Inventory file not found: $InventoryPath"
}

$inventory = Get-Content -LiteralPath $InventoryPath -Raw | ConvertFrom-Json

if (-not $inventory -or $inventory.Count -eq 0) {
    throw "No OLT data found in inventory: $InventoryPath"
}

$sqlLines = New-Object System.Collections.Generic.List[string]
$sqlLines.Add("START TRANSACTION;")

for ($index = 0; $index -lt $inventory.Count; $index++) {
    $item = $inventory[$index]
    $row = [math]::Floor($index / $Columns)
    $column = $index % $Columns

    $latitude = [math]::Round($StartLatitude - ($row * $LatitudeStep), 8)
    $longitude = [math]::Round($StartLongitude + ($column * $LongitudeStep), 8)

    $name = Escape-SqlString([string]$item.name)
    $model = Escape-SqlString([string]$item.model)
    $site = Escape-SqlString([string]$item.site)
    $ip = Escape-SqlString([string]$item.ip)
    $protocol = Escape-SqlString([string]$item.protocol)
    $ponCount = [int]$item.pon_count

    $properties = @{
        olt_link = $item.ip
        protocol = $item.protocol
        model = $item.model
        site = $item.site
    } | ConvertTo-Json -Compress

    $propertiesEscaped = Escape-SqlString($properties)

    $sqlLines.Add("")
    $sqlLines.Add("-- $($item.name)")
    $sqlLines.Add("SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = '$name' LIMIT 1);")
    $sqlLines.Add("INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)")
    $sqlLines.Add("SELECT 'olt', NULL, '$name', $latitude, $longitude, NULL, '$propertiesEscaped', 'unknown'")
    $sqlLines.Add("WHERE @olt_id IS NULL;")
    $sqlLines.Add("SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());")
    $sqlLines.Add("UPDATE map_items")
    $sqlLines.Add("SET latitude = $latitude, longitude = $longitude, properties = '$propertiesEscaped', status = 'unknown'")
    $sqlLines.Add("WHERE id = @olt_id;")
    $sqlLines.Add("")
    $sqlLines.Add("INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)")
    $sqlLines.Add("SELECT @olt_id, 0, $ponCount, 0, '$ip'")
    $sqlLines.Add("WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);")
    $sqlLines.Add("UPDATE olt_config SET output_power = 0, pon_count = $ponCount, attenuation_db = 0, olt_link = '$ip' WHERE map_item_id = @olt_id;")
    $sqlLines.Add("")
    $sqlLines.Add("DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;")
    for ($pon = 1; $pon -le $ponCount; $pon++) {
        $sqlLines.Add("INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, $pon, 9);")
    }
}

$sqlLines.Add("")
$sqlLines.Add("COMMIT;")

$outputDirectory = Split-Path -Parent $OutputSqlPath
if (-not (Test-Path -LiteralPath $outputDirectory)) {
    New-Item -ItemType Directory -Path $outputDirectory | Out-Null
}

$sqlLines -join [Environment]::NewLine | Set-Content -LiteralPath $OutputSqlPath -NoNewline
Write-Output $OutputSqlPath
