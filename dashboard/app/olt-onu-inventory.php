<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Inventory ONU OLT';
$currentPage = 'devices';
$selectedOltId = isset($_GET['olt_id']) ? (int) $_GET['olt_id'] : 0;
$selectedViewMode = $_GET['view_mode'] ?? 'all';

include __DIR__ . '/views/layouts/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-broadcast-pin"></i> Inventory ONU dari OLT
        </div>
        <div class="d-flex gap-2">
            <a href="/devices.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Perangkat
            </a>
            <button class="btn btn-sm btn-primary" onclick="loadOltInventoryPage()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Filter OLT</label>
                <select id="olt-filter" class="form-select" onchange="loadOltInventoryPage()">
                    <option value="0">Semua OLT</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cari Serial / Customer / PON</label>
                <input id="olt-onu-search" type="text" class="form-control" placeholder="Cari serial, nama, port..." oninput="renderOltInventoryTable()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Ringkasan</label>
                <div id="olt-onu-summary" class="d-flex flex-wrap gap-2"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Mode Tampilan</label>
                <select id="olt-onu-view-mode" class="form-select" onchange="renderOltInventoryTable()">
                    <option value="all">Semua ONU</option>
                    <option value="missing">Belum ACS</option>
                    <option value="online-missing">Online di OLT, Belum ACS</option>
                </select>
            </div>
        </div>

        <!-- Inform Gap Section -->
        <div class="card bg-light mb-3" id="inform-gap-section">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><i class="bi bi-exclamation-triangle text-warning"></i> Stale Devices</strong>
                        <span class="text-muted ms-2" id="stale-info">Loading...</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-info" onclick="loadInformGap()">
                            <i class="bi bi-arrow-clockwise"></i> Cek Gap
                        </button>
                        <button class="btn btn-sm btn-warning" id="btn-push-all-stale" onclick="pushAllStale()" disabled>
                            <i class="bi bi-send"></i> Push All Stale
                        </button>
                    </div>
                </div>
                <div id="stale-devices-list" class="mt-2" style="max-height: 200px; overflow-y: auto; display: none;"></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm" style="white-space: nowrap;">
                <thead>
                    <tr>
                        <th>OLT</th>
                        <th>PON</th>
                        <th>ONT ID</th>
                        <th>Serial</th>
                        <th>Customer</th>
                        <th>Status OLT</th>
                        <th>RX OLT</th>
                        <th>Distance</th>
                        <th>Firmware</th>
                        <th>ACS</th>
                        <th>Last Sync</th>
                    </tr>
                </thead>
                <tbody id="olt-onu-tbody">
                    <tr>
                        <td colspan="11" class="text-center"><div class="spinner"></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const initialOltId = <?php echo $selectedOltId; ?>;
const initialViewMode = <?php echo json_encode(in_array($selectedViewMode, ['all', 'missing', 'online-missing'], true) ? $selectedViewMode : 'all'); ?>;
let oltInventoryRows = [];

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function populateOltFilter(olts) {
    const select = document.getElementById('olt-filter');
    const currentValue = parseInt(select.value || initialOltId || 0, 10);
    select.innerHTML = '<option value="0">Semua OLT</option>';

    olts.forEach((olt) => {
        const option = document.createElement('option');
        option.value = String(olt.id);
        option.textContent = olt.name;
        if (olt.id === currentValue) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function renderOltInventorySummary(totals) {
    const summary = document.getElementById('olt-onu-summary');
    summary.innerHTML = `
        <span class="badge bg-primary">Inventory [${totals.inventory_total || 0}]</span>
        <span class="badge bg-success">Masuk ACS [${totals.in_acs_total || 0}]</span>
        <span class="badge bg-warning text-dark">Belum ACS [${totals.missing_total || 0}]</span>
        <span class="badge bg-danger">Online Belum ACS [${totals.online_missing_total || 0}]</span>
    `;
}

function renderOltInventoryTable() {
    const tbody = document.getElementById('olt-onu-tbody');
    const searchTerm = document.getElementById('olt-onu-search').value.toLowerCase().trim();
    const viewMode = document.getElementById('olt-onu-view-mode').value;

    let filtered = searchTerm === ''
        ? oltInventoryRows.slice()
        : oltInventoryRows.filter((item) => {
            const haystack = [
                item.olt_name,
                item.serial_number,
                item.pon_port,
                item.ont_index,
                item.description,
                item.firmware_version,
                item.equipment_id
            ].join(' ').toLowerCase();
            return haystack.includes(searchTerm);
        });

    if (viewMode === 'missing') {
        filtered = filtered.filter((item) => !item.in_acs);
    } else if (viewMode === 'online-missing') {
        filtered = filtered.filter((item) => !item.in_acs && item.status === 'online');
    }

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center">Tidak ada data ONU dari OLT untuk filter ini.</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map((item) => {
        const rx = item.rx_power !== null ? `${item.rx_power.toFixed(2)} dBm` : 'N/A';
        const distance = item.distance !== null ? `${item.distance} m` : 'N/A';
        const statusBadge = item.status === 'online'
            ? '<span class="badge bg-success">Online</span>'
            : item.status === 'offline'
                ? '<span class="badge bg-danger">Offline</span>'
                : '<span class="badge bg-secondary">Unknown</span>';
        const acsBadge = item.in_acs
            ? '<span class="badge bg-success">Masuk ACS</span>'
            : '<span class="badge bg-warning text-dark">Belum ACS</span>';

        return `
            <tr>
                <td>${escapeHtml(item.olt_name)}</td>
                <td>${escapeHtml(item.pon_port || 'N/A')}</td>
                <td>${escapeHtml(item.ont_index ?? 'N/A')}</td>
                <td>${escapeHtml(item.serial_number)}</td>
                <td>${escapeHtml(item.description || '-')}</td>
                <td>${statusBadge}</td>
                <td>${rx}</td>
                <td>${distance}</td>
                <td>${escapeHtml(item.firmware_version || '-')}</td>
                <td>${acsBadge}</td>
                <td>${escapeHtml(item.last_synced_at || '-')}</td>
            </tr>
        `;
    }).join('');
}

async function loadOltInventoryPage() {
    const tbody = document.getElementById('olt-onu-tbody');
    const oltItemId = parseInt(document.getElementById('olt-filter').value || initialOltId || 0, 10);
    tbody.innerHTML = '<tr><td colspan="11" class="text-center"><div class="spinner"></div></td></tr>';

    const query = oltItemId > 0 ? `?olt_item_id=${oltItemId}` : '';
    const result = await fetchAPI(`/api/olt-onu-inventory.php${query}`, { timeout: 30000 });

    if (!result || !result.success) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger">Gagal memuat inventory ONU dari OLT.</td></tr>';
        return;
    }

    populateOltFilter(result.olts || []);
    if (oltItemId > 0) {
        document.getElementById('olt-filter').value = String(oltItemId);
    }

    oltInventoryRows = result.items || [];
    renderOltInventorySummary(result.totals || {});
    renderOltInventoryTable();
}

document.addEventListener('DOMContentLoaded', function () {
    if (initialOltId > 0) {
        document.getElementById('olt-filter').value = String(initialOltId);
    }
    document.getElementById('olt-onu-view-mode').value = initialViewMode;
    loadOltInventoryPage();
    loadInformGap();
});

let staleDevices = [];

async function loadInformGap() {
    const infoEl = document.getElementById('stale-info');
    const listEl = document.getElementById('stale-devices-list');
    const btnPush = document.getElementById('btn-push-all-stale');
    infoEl.textContent = 'Checking...';

    const result = await fetchAPI('/api/first-inform-gap.php?limit=200', { timeout: 60000 });
    if (!result || !result.success) {
        infoEl.textContent = 'Gagal cek gap';
        return;
    }

    const missingCount = result.missing_count || 0;
    const onlineMissing = result.online_missing_count || 0;
    const staleCount = result.stale_count || 0;
    staleDevices = result.stale_devices || [];

    infoEl.innerHTML = `
        <span class="badge bg-danger">${missingCount} belum inform</span>
        <span class="badge bg-warning text-dark">${onlineMissing} online belum ACS</span>
        <span class="badge bg-info">${staleCount} stale (>15min)</span>
    `;

    if (staleDevices.length > 0) {
        btnPush.disabled = false;
        btnPush.textContent = `Push ${staleDevices.length} Stale`;
        listEl.style.display = 'block';
        listEl.innerHTML = `
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                <thead><tr><th>Serial</th><th>Device ID</th><th>Last Inform</th><th>Action</th></tr></thead>
                <tbody>
                    ${staleDevices.slice(0, 50).map(d => `
                        <tr>
                            <td>${escapeHtml(d.serial)}</td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(d.device_id)}</td>
                            <td>${d.last_inform ? new Date(d.last_inform).toLocaleString('id-ID') : 'N/A'}</td>
                            <td><button class="btn btn-xs btn-outline-warning" onclick="pushSingle('${escapeHtml(d.device_id)}', this)"><i class="bi bi-send"></i></button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        btnPush.disabled = true;
        listEl.style.display = 'none';
    }
}

async function pushSingle(deviceId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const result = await fetchAPI('/api/push-inform-batch.php', {
        method: 'POST',
        body: JSON.stringify({ device_ids: [deviceId] }),
    });
    if (result && result.success && result.success_count > 0) {
        btn.innerHTML = '<i class="bi bi-check text-success"></i>';
        showToast('Push inform berhasil', 'success');
    } else {
        btn.innerHTML = '<i class="bi bi-x text-danger"></i>';
        showToast(result?.message || 'Push inform gagal', 'danger');
    }
}

async function pushAllStale() {
    if (!staleDevices.length) return;
    if (!confirm(`Push inform ke ${staleDevices.length} device stale? Ini akan mengirim connection request ke semua device.`)) return;

    const btn = document.getElementById('btn-push-all-stale');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pushing...';

    const deviceIds = staleDevices.map(d => d.device_id);

    // Send in batches of 50
    let totalSuccess = 0;
    let totalFail = 0;
    for (let i = 0; i < deviceIds.length; i += 50) {
        const batch = deviceIds.slice(i, i + 50);
        const result = await fetchAPI('/api/push-inform-batch.php', {
            method: 'POST',
            body: JSON.stringify({ device_ids: batch }),
        });
        if (result && result.success) {
            totalSuccess += result.success_count || 0;
            totalFail += result.fail_count || 0;
        }
    }

    showToast(`Push selesai: ${totalSuccess} berhasil, ${totalFail} gagal`, totalFail > 0 ? 'warning' : 'success');
    btn.innerHTML = '<i class="bi bi-send"></i> Push All Stale';
    btn.disabled = false;

    // Reload gap data after push
    setTimeout(() => loadInformGap(), 5000);
}
</script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
