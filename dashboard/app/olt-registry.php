<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Perangkat OLT';
$currentPage = 'olt-registry';

include __DIR__ . '/views/layouts/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-broadcast-pin"></i> Registry OLT (Manual)</div>
        <div class="d-flex gap-2">
            <a href="/olt-onu-inventory.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-list-ul"></i> Inventory ONU
            </a>
            <button type="button" class="btn btn-sm btn-primary" onclick="openOltModal()">
                <i class="bi bi-plus-lg"></i> Tambah OLT
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadOlts()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input id="olt-search" type="text" class="form-control" placeholder="Cari nama/IP/brand/model..." oninput="renderOlts()">
            </div>
            <div class="col-md-3">
                <select id="olt-filter-protocol" class="form-select" onchange="renderOlts()">
                    <option value="">Semua Protocol</option>
                    <option value="telnet">Telnet</option>
                    <option value="ssh">SSH</option>
                    <option value="snmp">SNMP</option>
                    <option value="rest">REST</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="olt-filter-status" class="form-select" onchange="renderOlts()">
                    <option value="">Semua Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="unknown">Unknown</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <div id="olt-summary" class="small text-muted"></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm" style="white-space: nowrap;">
                <thead>
                    <tr>
                        <th>Nama OLT</th>
                        <th>Brand / Model</th>
                        <th>IP</th>
                        <th>Protocol</th>
                        <th>Status</th>
                        <th>ONU Online / Total</th>
                        <th>Sync</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="olt-table-body">
                    <tr><td colspan="8" class="text-center"><div class="spinner"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="oltModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-broadcast-pin"></i> Form OLT</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="olt-form">
                    <input type="hidden" name="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama OLT</label>
                            <input name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Brand</label>
                            <input name="brand" class="form-control" placeholder="Tenda / C-Data / HSGQ">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Model</label>
                            <input name="model" class="form-control" placeholder="TES7001 / FD1608S">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IP OLT</label>
                            <input name="ip_address" class="form-control" required placeholder="172.16.x.x">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Protocol</label>
                            <select name="preferred_protocol" class="form-select" onchange="updateProtocolHints()">
                                <option value="telnet">Telnet</option>
                                <option value="ssh">SSH</option>
                                <option value="snmp">SNMP</option>
                                <option value="rest">REST</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">PON</label>
                            <input name="pon_count" class="form-control" type="number" min="1" max="16" value="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Area</label>
                            <input name="area" class="form-control" placeholder="Contoh: Cikalong">
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="row g-3" id="telnet-fields">
                        <div class="col-md-4">
                            <label class="form-label">Telnet User</label>
                            <input name="telnet_user" class="form-control" placeholder="admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Telnet Password</label>
                            <input name="telnet_pass" class="form-control" placeholder="admin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Telnet Port</label>
                            <input name="telnet_port" class="form-control" type="number" value="23">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="ssh-fields">
                        <div class="col-md-3">
                            <label class="form-label">SSH User</label>
                            <input name="ssh_user" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SSH Password</label>
                            <input name="ssh_pass" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SSH Port</label>
                            <input name="ssh_port" class="form-control" type="number" value="22">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Enable Password</label>
                            <input name="ssh_enable_pass" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="snmp-fields">
                        <div class="col-md-3">
                            <label class="form-label">SNMP Community</label>
                            <input name="snmp_community" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SNMP Version</label>
                            <select name="snmp_version" class="form-select">
                                <option value="1">v1</option>
                                <option value="2c" selected>v2c</option>
                                <option value="3">v3</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SNMP Username</label>
                            <input name="snmp_username" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SNMP Auth Pass</label>
                            <input name="snmp_auth_pass" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="rest-fields">
                        <div class="col-md-6">
                            <label class="form-label">API URL</label>
                            <input name="api_url" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API Token</label>
                            <input name="api_token" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label">Catatan</label>
                            <input name="notes" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="is-active" checked>
                                <label for="is-active" class="form-check-label">Aktif</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="saveOlt()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
let oltRows = [];

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getStatusBadge(status) {
    if (status === 'online') return '<span class="badge bg-success">Online</span>';
    if (status === 'offline') return '<span class="badge bg-danger">Offline</span>';
    return '<span class="badge bg-secondary">Unknown</span>';
}

function getSyncBadge(item) {
    if (item.sync_state === 'success') return '<span class="badge bg-success">Success</span>';
    if (item.sync_state === 'error') return '<span class="badge bg-danger">Error</span>';
    if (item.sync_state === 'running') return '<span class="badge bg-warning text-dark">Running</span>';
    return '<span class="badge bg-secondary">N/A</span>';
}

function updateSummary(rows) {
    const total = rows.length;
    const online = rows.filter((r) => r.status === 'online').length;
    const offline = rows.filter((r) => r.status === 'offline').length;
    document.getElementById('olt-summary').textContent = `Total ${total} | Online ${online} | Offline ${offline}`;
}

function renderOlts() {
    const search = document.getElementById('olt-search').value.toLowerCase().trim();
    const protocolFilter = document.getElementById('olt-filter-protocol').value;
    const statusFilter = document.getElementById('olt-filter-status').value;
    const tbody = document.getElementById('olt-table-body');

    let rows = oltRows.slice();
    if (search) {
        rows = rows.filter((item) => {
            const blob = [
                item.name,
                item.ip_address,
                item.brand,
                item.model,
                item.area,
                item.notes
            ].join(' ').toLowerCase();
            return blob.includes(search);
        });
    }
    if (protocolFilter) {
        rows = rows.filter((item) => item.preferred_protocol === protocolFilter);
    }
    if (statusFilter) {
        rows = rows.filter((item) => item.status === statusFilter);
    }

    updateSummary(rows);

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Belum ada OLT untuk filter ini.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((item) => {
        const onlineRatio = `${item.online_total} / ${item.inventory_total}`;
        const syncText = item.last_synced_at ? escapeHtml(item.last_synced_at) : '-';
        const errorLine = item.sync_last_error ? `<div class="text-danger small">${escapeHtml(item.sync_last_error)}</div>` : '';
        return `
            <tr>
                <td><strong>${escapeHtml(item.name)}</strong><div class="small text-muted">${escapeHtml(item.area || '-')}</div></td>
                <td>${escapeHtml(item.brand || '-')} / ${escapeHtml(item.model || '-')}</td>
                <td>${escapeHtml(item.ip_address || '-')}</td>
                <td><span class="badge bg-info">${escapeHtml((item.preferred_protocol || 'telnet').toUpperCase())}</span></td>
                <td>${getStatusBadge(item.status)}</td>
                <td>${onlineRatio}</td>
                <td>${getSyncBadge(item)}<div class="small text-muted mt-1">${syncText}</div>${errorLine}</td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" onclick="editOlt(${item.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="syncOlt(${item.id})"><i class="bi bi-arrow-repeat"></i></button>
                        <a class="btn btn-sm btn-outline-secondary" href="/olt-onu-inventory.php?olt_id=${item.id}"><i class="bi bi-list-ul"></i></a>
                        <button class="btn btn-sm btn-danger" onclick="deleteOlt(${item.id}, '${escapeHtml(item.name)}')"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function updateProtocolHints() {
    const protocol = document.querySelector('#olt-form [name="preferred_protocol"]').value;
    document.getElementById('telnet-fields').style.opacity = protocol === 'telnet' ? '1' : '0.55';
    document.getElementById('ssh-fields').style.opacity = protocol === 'ssh' ? '1' : '0.55';
    document.getElementById('snmp-fields').style.opacity = protocol === 'snmp' ? '1' : '0.55';
    document.getElementById('rest-fields').style.opacity = protocol === 'rest' ? '1' : '0.55';
}

async function loadOlts() {
    const tbody = document.getElementById('olt-table-body');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner"></div></td></tr>';
    const result = await fetchAPI('/api/olt-registry-list.php', { timeout: 30000 });
    if (!result || !result.success) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Gagal memuat data OLT.</td></tr>';
        return;
    }
    oltRows = result.items || [];
    renderOlts();
}

function openOltModal() {
    const form = document.getElementById('olt-form');
    form.reset();
    form.id.value = '';
    form.preferred_protocol.value = 'telnet';
    form.pon_count.value = 1;
    form.telnet_port.value = 23;
    form.ssh_port.value = 22;
    form.is_active.checked = true;
    updateProtocolHints();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('oltModal')).show();
}

function editOlt(id) {
    const row = oltRows.find((r) => r.id === id);
    if (!row) return;
    const form = document.getElementById('olt-form');
    form.id.value = row.id;
    form.name.value = row.name || '';
    form.brand.value = row.brand || '';
    form.model.value = row.model || '';
    form.ip_address.value = row.ip_address || '';
    form.preferred_protocol.value = row.preferred_protocol || 'telnet';
    form.pon_count.value = row.pon_count || 1;
    form.area.value = row.area || '';
    form.notes.value = row.notes || '';
    form.telnet_user.value = row.credentials?.telnet_user || '';
    form.telnet_pass.value = row.credentials?.telnet_pass || '';
    form.telnet_port.value = row.credentials?.telnet_port || 23;
    form.ssh_user.value = row.credentials?.ssh_user || '';
    form.ssh_pass.value = row.credentials?.ssh_pass || '';
    form.ssh_port.value = row.credentials?.ssh_port || 22;
    form.ssh_enable_pass.value = row.credentials?.ssh_enable_pass || '';
    form.snmp_community.value = row.credentials?.snmp_community || '';
    form.snmp_version.value = row.credentials?.snmp_version || '2c';
    form.snmp_username.value = row.credentials?.snmp_username || '';
    form.snmp_auth_pass.value = row.credentials?.snmp_auth_pass || '';
    form.api_url.value = row.credentials?.api_url || '';
    form.api_token.value = row.credentials?.api_token || '';
    form.is_active.checked = !!row.is_active;

    updateProtocolHints();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('oltModal')).show();
}

function collectFormData() {
    const form = document.getElementById('olt-form');
    return {
        id: form.id.value ? Number(form.id.value) : 0,
        name: form.name.value.trim(),
        brand: form.brand.value.trim(),
        model: form.model.value.trim(),
        ip_address: form.ip_address.value.trim(),
        preferred_protocol: form.preferred_protocol.value,
        pon_count: Number(form.pon_count.value || 1),
        area: form.area.value.trim(),
        notes: form.notes.value.trim(),
        telnet_user: form.telnet_user.value.trim(),
        telnet_pass: form.telnet_pass.value.trim(),
        telnet_port: Number(form.telnet_port.value || 23),
        ssh_user: form.ssh_user.value.trim(),
        ssh_pass: form.ssh_pass.value.trim(),
        ssh_port: Number(form.ssh_port.value || 22),
        ssh_enable_pass: form.ssh_enable_pass.value.trim(),
        snmp_community: form.snmp_community.value.trim(),
        snmp_version: form.snmp_version.value,
        snmp_username: form.snmp_username.value.trim(),
        snmp_auth_pass: form.snmp_auth_pass.value.trim(),
        api_url: form.api_url.value.trim(),
        api_token: form.api_token.value.trim(),
        is_active: !!form.is_active.checked
    };
}

async function saveOlt() {
    const payload = collectFormData();
    if (!payload.name || !payload.ip_address) {
        showToast('Nama OLT dan IP wajib diisi.', 'warning');
        return;
    }
    const result = await fetchAPI('/api/olt-registry-save.php', {
        method: 'POST',
        body: JSON.stringify(payload),
        timeout: 30000
    });
    if (!result || !result.success) {
        showToast(result?.message || 'Gagal menyimpan OLT', 'danger');
        return;
    }
    showToast(result.message || 'OLT berhasil disimpan', 'success');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('oltModal')).hide();
    loadOlts();
}

async function deleteOlt(id, name) {
    if (!confirm(`Hapus OLT ${name}? Inventory ONU untuk OLT ini juga akan terhapus.`)) {
        return;
    }
    const result = await fetchAPI('/api/olt-registry-delete.php', {
        method: 'POST',
        body: JSON.stringify({ id }),
        timeout: 30000
    });
    if (!result || !result.success) {
        showToast(result?.message || 'Gagal menghapus OLT', 'danger');
        return;
    }
    showToast(result.message || 'OLT berhasil dihapus', 'success');
    loadOlts();
}

async function syncOlt(id) {
    showToast('Menjalankan sync OLT...', 'info');
    const result = await fetchAPI('/api/olt-registry-sync.php', {
        method: 'POST',
        body: JSON.stringify({ id }),
        timeout: 60000
    });
    if (!result || !result.success) {
        showToast(result?.message || 'Sync OLT gagal', 'danger');
        await loadOlts();
        return;
    }
    showToast(result.message || 'Sync OLT selesai', 'success');
    await loadOlts();
}

document.addEventListener('DOMContentLoaded', function () {
    updateProtocolHints();
    loadOlts();
});
</script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>

