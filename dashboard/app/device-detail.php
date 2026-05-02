<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Detail Perangkat';
$currentPage = 'devices';

$genieacsConfigured = isGenieACSConfigured();
$cpeWriteEnabled = isCpeWriteEnabled();

// Get device ID from URL
$deviceId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$deviceId) {
    header('Location: /devices.php');
    exit;
}

include __DIR__ . '/views/layouts/header.php';
?>

<?php if (!$genieacsConfigured): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        GenieACS belum dikonfigurasi. Silakan konfigurasi terlebih dahulu di
        <a href="/configuration.php">halaman Integrasi</a>.
    </div>
<?php else: ?>
    <?php if (!$cpeWriteEnabled): ?>
        <div class="alert alert-info mb-2">
            <i class="bi bi-shield-lock"></i>
            Safe Mode aktif: aksi write ONT (reboot/factory reset/WAN/WiFi/DHCP) sedang dikunci.
        </div>
    <?php endif; ?>
    <!-- Back Button -->
    <div class="mb-2 device-actions">
        <a href="/devices.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Devices
        </a>
        <button class="btn btn-primary" onclick="summonDevice()">
            <i class="bi bi-lightning-charge"></i> Summon Device
        </button>
        <button class="btn btn-success" onclick="showAddTagModal()">
            <i class="bi bi-tag"></i> Add Tag
        </button>
        <button class="btn btn-warning" onclick="showRemoveTagModal()">
            <i class="bi bi-tag-fill"></i> Remove Tag
        </button>
        <button class="btn btn-info" onclick="loadDeviceDetail()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <button class="btn btn-outline-dark" onclick="openSafeActionDrawer('overview')">
            <i class="bi bi-shield-lock"></i> Write Actions
        </button>
        <button class="btn btn-danger" onclick="<?php echo $cpeWriteEnabled ? 'rebootOnt()' : "openSafeActionDrawer('reboot')"; ?>" <?php echo !$cpeWriteEnabled ? 'title="Safe Mode aktif"' : ''; ?>>
            <i class="bi bi-arrow-repeat"></i> Reboot ONT
        </button>
        <button class="btn btn-outline-danger" onclick="<?php echo $cpeWriteEnabled ? 'factoryResetOnt()' : "openSafeActionDrawer('factory-reset')"; ?>" <?php echo !$cpeWriteEnabled ? 'title="Safe Mode aktif"' : ''; ?>>
            <i class="bi bi-exclamation-triangle"></i> Factory Reset
        </button>
    </div>

    <!-- Device Detail Card with Tabs -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-router"></i> Device Details
            <span id="device-id-badge" class="badge bg-secondary ms-2">Loading...</span>
            <span id="device-tags-badge"></span>
        </div>
        <div class="card-body">
            <!-- Loading Spinner (shown initially) -->
            <div id="loading-spinner" class="text-center">
                <div class="spinner"></div>
            </div>

            <!-- Tab Navigation (hidden initially) -->
            <ul class="nav nav-tabs" id="deviceTabs" role="tablist" style="display:none;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                        <i class="bi bi-info-circle"></i> Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="wan-tab" data-bs-toggle="tab" data-bs-target="#wan" type="button" role="tab">
                        <i class="bi bi-globe"></i> WAN Connections <span id="wan-count-badge" class="badge bg-primary ms-1">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="dhcp-tab" data-bs-toggle="tab" data-bs-target="#dhcp" type="button" role="tab">
                        <i class="bi bi-router"></i> DHCP Server
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="devices-tab" data-bs-toggle="tab" data-bs-target="#devices" type="button" role="tab">
                        <i class="bi bi-hdd-network"></i> Connected Devices <span id="devices-count-badge" class="badge bg-primary ms-1">0</span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content (hidden initially) -->
            <div class="tab-content mt-3" id="deviceTabContent" style="display:none;">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <div id="overview-content"></div>
                </div>

                <!-- WAN Connections Tab -->
                <div class="tab-pane fade" id="wan" role="tabpanel">
                    <div id="wan-content"></div>
                </div>

                <!-- DHCP Server Tab -->
                <div class="tab-pane fade" id="dhcp" role="tabpanel">
                    <div id="dhcp-content"></div>
                </div>

                <!-- Connected Devices Tab -->
                <div class="tab-pane fade" id="devices" role="tabpanel">
                    <div id="devices-content"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="offcanvas offcanvas-end" tabindex="-1" id="safeActionDrawer" aria-labelledby="safeActionDrawerTitle">
    <div class="offcanvas-header">
        <div>
            <h5 class="offcanvas-title" id="safeActionDrawerTitle">Safe Action Center</h5>
            <div class="text-muted small">Guard rail aksi write ke ONT pelanggan.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="alert <?php echo $cpeWriteEnabled ? 'alert-warning' : 'alert-info'; ?>">
            <i class="bi bi-shield-lock"></i>
            <?php if ($cpeWriteEnabled): ?>
                Safe Mode OFF lewat env. Aksi write tetap wajib pakai konfirmasi dan audit.
            <?php else: ?>
                Safe Mode ON. Semua aksi write dikunci dan tidak akan dikirim ke ONT.
            <?php endif; ?>
        </div>

        <div id="safe-action-content"></div>

        <div class="mt-3 small text-muted">
            Rule produksi: env unlock, role admin, alasan tindakan, ketik <code>KONFIRMASI</code>, audit log, dan limit device sebelum action write dibuka.
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/device-detail/modals.php'; ?>

<script>
// Global configuration for device-detail.js
window.DEVICE_ID = '<?php echo htmlspecialchars($deviceId, ENT_QUOTES, 'UTF-8'); ?>';
window.CPE_WRITE_ENABLED = <?php echo $cpeWriteEnabled ? 'true' : 'false'; ?>;
</script>
<script src="/assets/js/device-detail.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
