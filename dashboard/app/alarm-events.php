<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Alarm';
$currentPage = 'alarm-events';

include __DIR__ . '/views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-1">Alarm Monitor</h5>
        <div class="text-muted small">Event Telegram dan monitor ACS/OLT. Semua data di halaman ini read-only.</div>
    </div>
    <button class="btn btn-sm btn-primary" onclick="loadAlarmEvents()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Alarm Open</div>
                <h3 class="mb-0" id="alarm-open-total">-</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Critical Open</div>
                <h3 class="mb-0 text-danger" id="alarm-critical-open">-</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Warning Open</div>
                <h3 class="mb-0 text-warning" id="alarm-warning-open">-</h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" id="alarm-search" class="form-control form-control-sm" placeholder="Cari OLT, SN, device, pesan...">
            </div>
            <div class="col-md-3">
                <select id="alarm-status" class="form-select form-select-sm">
                    <option value="open">Open</option>
                    <option value="all">Semua Status</option>
                    <option value="acked">Acked</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="alarm-severity" class="form-select form-select-sm">
                    <option value="all">Semua Severity</option>
                    <option value="critical">Critical</option>
                    <option value="warning">Warning</option>
                    <option value="info">Info</option>
                </select>
            </div>
            <div class="col-md-2 text-md-end">
                <span class="badge bg-success">Safe Mode ON</span>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Severity</th>
                    <th>Event</th>
                    <th>OLT</th>
                    <th>SN / Device</th>
                    <th>Status</th>
                    <th>Pesan</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="alarm-events-body">
                <tr><td colspan="8" class="text-center text-muted py-4">Memuat alarm...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="alarmTimelineDrawer" aria-labelledby="alarmTimelineTitle">
    <div class="offcanvas-header">
        <div>
            <h5 class="offcanvas-title" id="alarmTimelineTitle">Timeline Alarm</h5>
            <div class="text-muted small">Read-only event trail dari monitor.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="alarm-timeline-content">
        <div class="text-muted">Pilih alarm untuk melihat timeline.</div>
    </div>
</div>

<script>
const alarmBody = document.getElementById('alarm-events-body');
const alarmSearch = document.getElementById('alarm-search');
const alarmStatus = document.getElementById('alarm-status');
const alarmSeverity = document.getElementById('alarm-severity');
const initialAlarmQuery = new URLSearchParams(window.location.search).get('q');
if (initialAlarmQuery) {
    alarmSearch.value = initialAlarmQuery;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function severityBadge(severity) {
    const map = {
        critical: 'danger',
        warning: 'warning',
        info: 'secondary'
    };
    return `<span class="badge bg-${map[severity] || 'secondary'}">${escapeHtml(severity || '-')}</span>`;
}

function alarmActionButtons(item) {
    const id = Number(item.id);
    const disabledAck = item.status === 'acked' || item.status === 'resolved' ? 'disabled' : '';
    const disabledResolve = item.status === 'resolved' ? 'disabled' : '';
    return `
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" onclick="openAlarmTimeline(${id})" title="Timeline">
                <i class="bi bi-clock-history"></i>
            </button>
            <button class="btn btn-outline-primary" onclick="updateAlarmStatus(${id}, 'ack')" ${disabledAck} title="Ack">
                Ack
            </button>
            <button class="btn btn-outline-success" onclick="updateAlarmStatus(${id}, 'resolve')" ${disabledResolve} title="Resolve">
                Resolve
            </button>
        </div>
    `;
}

async function loadAlarmEvents() {
    const params = new URLSearchParams({
        status: alarmStatus.value,
        severity: alarmSeverity.value,
        q: alarmSearch.value.trim(),
        limit: '100'
    });

    alarmBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Memuat alarm...</td></tr>';

    try {
        const response = await fetch(`/api/telegram-alarm-events.php?${params.toString()}`);
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Gagal memuat alarm');
        }

        document.getElementById('alarm-open-total').textContent = result.summary.open_total;
        document.getElementById('alarm-critical-open').textContent = result.summary.critical_open;
        document.getElementById('alarm-warning-open').textContent = result.summary.warning_open;

        if (!result.items.length) {
            alarmBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Belum ada alarm untuk filter ini.</td></tr>';
            return;
        }

        alarmBody.innerHTML = result.items.map(item => `
            <tr>
                <td class="small text-muted">${escapeHtml(item.created_at)}</td>
                <td>${severityBadge(item.severity)}</td>
                <td>
                    <div class="fw-semibold">${escapeHtml(item.title)}</div>
                    <div class="text-muted small">${escapeHtml(item.event_type)} / ${escapeHtml(item.scope)}</div>
                </td>
                <td>${escapeHtml(item.olt_name || '-')}</td>
                <td>
                    <div>${escapeHtml(item.serial_number || '-')}</div>
                    <div class="text-muted small">${escapeHtml(item.device_id || '-')}</div>
                </td>
                <td><span class="badge bg-light text-dark border">${escapeHtml(item.status)}</span></td>
                <td class="small">${escapeHtml(item.message || '-')}</td>
                <td>${alarmActionButtons(item)}</td>
            </tr>
        `).join('');
    } catch (error) {
        alarmBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
    }
}

async function updateAlarmStatus(id, action) {
    try {
        const response = await fetch('/api/telegram-alarm-events.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, action})
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Gagal update alarm');
        }
        loadAlarmEvents();
    } catch (error) {
        alert(error.message);
    }
}

async function openAlarmTimeline(id) {
    const drawerElement = document.getElementById('alarmTimelineDrawer');
    const content = document.getElementById('alarm-timeline-content');
    content.innerHTML = '<div class="text-muted">Memuat timeline...</div>';
    bootstrap.Offcanvas.getOrCreateInstance(drawerElement).show();

    try {
        const response = await fetch(`/api/telegram-alarm-events.php?id=${encodeURIComponent(id)}`);
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Gagal memuat timeline');
        }

        const event = result.event;
        const payload = event.payload ? `<pre class="bg-light border rounded p-2 small" style="white-space: pre-wrap; max-height: 220px; overflow:auto;">${escapeHtml(event.payload)}</pre>` : '';
        const timeline = result.timeline.length
            ? result.timeline.map(row => `
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between gap-2">
                        <strong>${escapeHtml(row.title)}</strong>
                        ${severityBadge(row.severity)}
                    </div>
                    <div class="text-muted small">${escapeHtml(row.created_at)} · ${escapeHtml(row.event_type)} · ${escapeHtml(row.status)}</div>
                    <div class="small mt-1">${escapeHtml(row.message || '-')}</div>
                </div>
            `).join('')
            : '<div class="text-muted">Belum ada event terkait.</div>';

        content.innerHTML = `
            <div class="mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    ${severityBadge(event.severity)}
                    <span class="badge bg-light text-dark border">${escapeHtml(event.status)}</span>
                </div>
                <h6>${escapeHtml(event.title)}</h6>
                <div class="small text-muted">${escapeHtml(event.created_at)} · ${escapeHtml(event.event_type)}</div>
                <div class="mt-2 small">${escapeHtml(event.message || '-')}</div>
            </div>
            <dl class="row small">
                <dt class="col-4">OLT</dt><dd class="col-8">${escapeHtml(event.olt_name || '-')}</dd>
                <dt class="col-4">SN</dt><dd class="col-8">${escapeHtml(event.serial_number || '-')}</dd>
                <dt class="col-4">Device</dt><dd class="col-8">${escapeHtml(event.device_id || '-')}</dd>
                <dt class="col-4">Ack</dt><dd class="col-8">${escapeHtml(event.acked_by || '-')} ${event.acked_at ? `(${escapeHtml(event.acked_at)})` : ''}</dd>
            </dl>
            ${payload}
            <h6 class="mt-3">Timeline Terkait</h6>
            ${timeline}
        `;
    } catch (error) {
        content.innerHTML = `<div class="text-danger">${escapeHtml(error.message)}</div>`;
    }
}

let alarmSearchTimer = null;
alarmSearch.addEventListener('input', () => {
    clearTimeout(alarmSearchTimer);
    alarmSearchTimer = setTimeout(loadAlarmEvents, 300);
});
alarmStatus.addEventListener('change', loadAlarmEvents);
alarmSeverity.addEventListener('change', loadAlarmEvents);
loadAlarmEvents();
setInterval(loadAlarmEvents, 30000);
</script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
