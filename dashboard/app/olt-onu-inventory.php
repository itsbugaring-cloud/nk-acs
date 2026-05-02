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
});
</script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
