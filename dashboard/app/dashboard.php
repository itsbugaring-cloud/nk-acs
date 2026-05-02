<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Check if GenieACS is configured
$genieacsConfigured = isGenieACSConfigured();

include __DIR__ . '/views/layouts/header.php';
?>

<?php if (!$genieacsConfigured): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        GenieACS belum dikonfigurasi. Silakan konfigurasi terlebih dahulu di
        <a href="/configuration.php">halaman Integrasi</a>.
    </div>
<?php else: ?>
    <!-- Stats Cards -->
    <div class="stats-grid" id="stats-container">
        <a href="/devices.php" class="stat-card primary" style="text-decoration: none; color: inherit; cursor: pointer;">
            <div class="stat-header">
                <span>Total Devices</span>
                <i class="bi bi-router stat-icon"></i>
            </div>
            <div class="stat-info">
                <h3 id="stat-total">-</h3>
                <p>Managed ONTs</p>
            </div>
        </a>

        <a href="/devices.php" class="stat-card success" style="text-decoration: none; color: inherit; cursor: pointer;">
            <div class="stat-header">
                <span>Online</span>
                <i class="bi bi-check-circle stat-icon"></i>
            </div>
            <div class="stat-info">
                <h3 id="stat-online">-</h3>
                <p>Currently Connected</p>
            </div>
        </a>

        <a href="/devices.php" class="stat-card danger" style="text-decoration: none; color: inherit; cursor: pointer;">
            <div class="stat-header">
                <span>Offline</span>
                <i class="bi bi-x-circle stat-icon"></i>
            </div>
            <div class="stat-info">
                <h3 id="stat-offline">-</h3>
                <p>Not Reachable</p>
            </div>
        </a>

        <div class="stat-card warning">
            <div class="stat-header">
                <span>Availability</span>
                <i class="bi bi-clock-history stat-icon"></i>
            </div>
            <div class="stat-info">
                <h3 id="stat-uptime">-</h3>
                <p>Active Ratio</p>
            </div>
        </div>
    </div>

    <!-- Device Overview & Uplink -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-bar-chart"></i> Device Overview
                    <button class="btn btn-sm btn-primary float-end" onclick="loadDashboardData()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div style="max-width: 300px; margin: 0 auto;">
                        <canvas id="deviceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-reception-4"></i> Uplink Signal Strength
                    <button class="btn btn-sm btn-primary float-end" onclick="loadUplinkData()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div style="max-width: 300px; margin: 0 auto;">
                        <canvas id="uplinkChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Devices -->
    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-activity"></i> Recent Device Activity
                </div>
                <div class="card-body">
                    <div id="recent-devices">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-hdd-network"></i> OLT Health Board</div>
                    <span class="badge bg-primary" id="olt-health-count">-</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Board operasional per OLT: online rate, sync freshness, redaman kritis, dan ONT online belum ACS.</p>
                    <div class="row g-2 mb-3">
                        <div class="col-lg-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="olt-health-filter-input" placeholder="Cari OLT atau area...">
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary active" data-olt-health-filter="all" onclick="setOltHealthFilter('all', this)">Semua</button>
                                <button type="button" class="btn btn-outline-danger" data-olt-health-filter="critical" onclick="setOltHealthFilter('critical', this)">Health Buruk</button>
                                <button type="button" class="btn btn-outline-warning" data-olt-health-filter="gap" onclick="setOltHealthFilter('gap', this)">Online Belum ACS</button>
                                <button type="button" class="btn btn-outline-dark" data-olt-health-filter="stale" onclick="setOltHealthFilter('stale', this)">Sync Stale</button>
                            </div>
                        </div>
                    </div>
                    <div id="olt-health-board">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-exclamation-diamond"></i> RX Unsupported</div>
                    <span class="badge bg-danger" id="rx-unsupported-count">-</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Daftar device yang masih tidak mengekspose RX ke ACS, jadi jelas ini bukan bug table.</p>
                    <div id="rx-unsupported-devices">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-broadcast"></i> First Inform Gap</div>
                    <span class="badge bg-warning text-dark" id="first-inform-gap-count">-</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Prioritaskan ONU yang online di OLT tetapi belum pernah inform ke ACS. Itu target bootstrap/TR-069 yang paling nyata.</p>
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <select class="form-select form-select-sm" id="first-inform-olt-filter" onchange="loadFirstInformGap()">
                                <option value="">Semua OLT</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" id="first-inform-status-filter" onchange="loadFirstInformGap()">
                                <option value="all">Semua status OLT</option>
                                <option value="online">Online belum ACS</option>
                                <option value="unknown">Unknown belum ACS</option>
                                <option value="offline">Offline belum ACS</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="first-inform-limit-filter" onchange="loadFirstInformGap()">
                                <option value="25">Tampilkan 25</option>
                                <option value="50" selected>Tampilkan 50</option>
                                <option value="100">Tampilkan 100</option>
                            </select>
                        </div>
                    </div>
                    <div id="first-inform-gap-summary" class="mb-3">
                        <div class="spinner"></div>
                    </div>
                    <div id="first-inform-gap-list">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-list-check"></i> Task Queue Monitor</div>
                    <span class="badge bg-primary" id="task-queue-count">-</span>
                </div>
                <div class="card-body">
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="task-queue-filter-input" placeholder="Filter task/fault by device, OLT, vendor, atau nama task...">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearTaskQueueFilter()">Clear</button>
                    </div>
                    <div id="task-queue-summary">
                        <div class="spinner"></div>
                    </div>
                    <div id="task-queue-by-name" class="mt-3">
                        <div class="spinner"></div>
                    </div>
                    <div id="task-queue-context" class="mt-3">
                        <div class="spinner"></div>
                    </div>
                    <div id="task-queue-fault-samples" class="mt-3">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-broadcast-pin"></i> Connection Request Reachability</div>
                    <span class="badge bg-secondary" id="reachability-total">-</span>
                </div>
                <div class="card-body">
                    <div id="reachability-summary">
                        <div class="spinner"></div>
                    </div>
                    <div id="reachability-by-olt" class="mt-3">
                        <div class="spinner"></div>
                    </div>
                    <div id="reachability-by-product" class="mt-3">
                        <div class="spinner"></div>
                    </div>
                    <div id="reachability-samples" class="mt-3">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-graph-down-arrow"></i> Optical Trend View</div>
                    <span class="badge bg-primary" id="optical-trend-count">-</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-lg-5">
                            <div id="optical-redaman-summary">
                                <div class="spinner"></div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div id="optical-redaman-by-olt">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>
                    <div id="optical-trend-summary" class="mb-3">
                        <div class="spinner"></div>
                    </div>
                    <div id="optical-trend-list">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Summon Confirmation Modal -->
<div class="modal fade" id="summonModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-lightning-charge"></i> Konfirmasi Summon Device
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: var(--warning-color);"></i>
                <h5 class="mt-3">Summon Device?</h5>
                <p class="text-muted mb-0">Apakah Anda yakin ingin melakukan connection request ke device ini?</p>
                <p class="text-muted mb-0"><small>Device ID: <strong id="summon-device-id"></strong></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Batal
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmSummon()">
                    <i class="bi bi-lightning-charge"></i> Ya, Summon
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Device Location Alert Modal -->
<div class="modal fade" id="notInMapModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-circle"></i> Lokasi Topologi Tidak Aktif
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-info-circle" style="font-size: 3rem; color: var(--secondary-color);"></i>
                <h5 class="mt-3">Fitur Topologi Dinonaktifkan</h5>
                <p class="text-muted mb-2">Device <strong id="not-in-map-serial"></strong> tetap bisa dipantau penuh dari halaman perangkat.</p>
                <p class="text-muted mb-0"><small>Gunakan detail perangkat dan inventory OLT untuk operasional.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
let deviceChart = null;
let uplinkChart = null;
let dashboardFetchInProgress = false;
let uplinkFetchInProgress = false;
let recentDevicesFetchInProgress = false;
let rxUnsupportedFetchInProgress = false;
let firstInformGapFetchInProgress = false;
let taskQueueFetchInProgress = false;
let reachabilityFetchInProgress = false;
let oltHealthFetchInProgress = false;
let opticalTrendFetchInProgress = false;
let taskQueueFilterTimer = null;
let oltHealthFilterMode = 'all';
let oltHealthFilterTimer = null;

async function loadDashboardData() {
    // Prevent concurrent requests
    if (dashboardFetchInProgress) {
        console.debug('[DASHBOARD] Stats fetch already in progress, skipping...');
        return;
    }

    dashboardFetchInProgress = true;
    try {
        const result = await fetchAPI('/api/dashboard-stats.php', { timeout: 25000 });

        if (result && result.success) {
            const stats = result.stats;

            document.getElementById('stat-total').textContent = stats.total;
            document.getElementById('stat-online').textContent = stats.online;
            document.getElementById('stat-offline').textContent = stats.offline;

            // Calculate percentage
            const onlinePercentage = stats.total > 0 ? Math.round((stats.online / stats.total) * 100) : 0;
            document.getElementById('stat-uptime').textContent = onlinePercentage + '%';

            // Update chart
            updateChart(stats);
        } else {
            if (result && result.error !== 'timeout') {
                showToast('Gagal memuat data dashboard', 'danger');
            }
        }
    } catch (error) {
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.error('Error loading dashboard:', error);
        }
    } finally {
        dashboardFetchInProgress = false;
    }
}

function updateChart(stats) {
    const ctx = document.getElementById('deviceChart').getContext('2d');

    if (deviceChart) {
        deviceChart.destroy();
    }

    deviceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Online', 'Offline'],
            datasets: [{
                data: [stats.online, stats.offline],
                backgroundColor: [
                    'rgba(28, 200, 138, 0.8)',
                    'rgba(231, 74, 59, 0.8)'
                ],
                borderColor: [
                    'rgba(28, 200, 138, 1)',
                    'rgba(231, 74, 59, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Device Status Distribution'
                }
            }
        }
    });
}

async function loadUplinkData() {
    // Prevent concurrent requests
    if (uplinkFetchInProgress) {
        console.debug('[DASHBOARD] Uplink fetch already in progress, skipping...');
        return;
    }

    uplinkFetchInProgress = true;
    try {
        const result = await fetchAPI('/api/uplink-stats.php', { timeout: 25000 });

        if (result && result.success) {
            updateUplinkChart(result.data);
        } else {
            if (result && result.error !== 'timeout') {
                showToast('Gagal memuat data uplink', 'danger');
            }
        }
    } catch (error) {
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.error('Error loading uplink:', error);
        }
    } finally {
        uplinkFetchInProgress = false;
    }
}

function updateUplinkChart(data) {
    const ctx = document.getElementById('uplinkChart').getContext('2d');

    if (uplinkChart) {
        uplinkChart.destroy();
    }

    uplinkChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Excellent', 'Good', 'Fair', 'Poor', 'No Signal'],
            datasets: [{
                data: [data.excellent, data.good, data.fair, data.poor, data.no_signal],
                backgroundColor: [
                    'rgba(28, 200, 138, 0.8)',  // Excellent - green
                    'rgba(52, 152, 219, 0.8)',  // Good - blue
                    'rgba(241, 196, 15, 0.8)',  // Fair - yellow
                    'rgba(231, 76, 60, 0.8)',   // Poor - red
                    'rgba(149, 165, 166, 0.8)'  // No signal - gray
                ],
                borderColor: [
                    'rgba(28, 200, 138, 1)',
                    'rgba(52, 152, 219, 1)',
                    'rgba(241, 196, 15, 1)',
                    'rgba(231, 76, 60, 1)',
                    'rgba(149, 165, 166, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        padding: 5,
                        font: {
                            size: 9
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'PON Signal Distribution'
                }
            }
        }
    });
}

function extractIP(ipString) {
    if (!ipString || ipString === 'N/A') return 'N/A';
    const match = ipString.match(/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/);
    return match ? match[1] : 'N/A';
}

async function loadRecentDevices() {
    // Prevent concurrent requests
    if (recentDevicesFetchInProgress) {
        console.debug('[DASHBOARD] Recent devices fetch already in progress, skipping...');
        return;
    }

    const container = document.getElementById('recent-devices');
    container.innerHTML = '<div class="spinner"></div>';

    recentDevicesFetchInProgress = true;
    try {
        const result = await fetchAPI('/api/recent-devices.php', { timeout: 25000 });

        if (result && result.success) {
            const devices = result.devices;

            if (devices.length === 0) {
                container.innerHTML = '<p class="text-center text-muted">No recent device activity</p>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr>';
            html += '<th>SN</th>';
            html += '<th>MAC</th>';
            html += '<th>Tipe</th>';
            html += '<th>IP</th>';
            html += '<th>SSID</th>';
            html += '<th>PPPoE</th>';
            html += '<th>Rx</th>';
            html += '<th>Temp</th>';
            html += '<th>Client</th>';
            html += '<th>Status</th>';
            html += '<th>Action</th>';
            html += '</tr></thead><tbody>';

            devices.forEach(device => {
                const ipAddress = extractIP(device.ip_tr069);

                // Create clickable IP link if IP is valid
                let ipDisplay;
                if (ipAddress !== 'N/A' && ipAddress !== '') {
                    ipDisplay = `<a href="http://${ipAddress}" target="_blank" rel="noopener noreferrer" title="Open ${ipAddress} in new tab">${ipAddress}</a>`;
                } else {
                    ipDisplay = ipAddress;
                }

                // Connected clients count with badge
                const clientsCount = device.connected_devices_count || 0;
                let clientsBadge = '';
                if (clientsCount > 0) {
                    clientsBadge = `<span class="badge bg-primary">${clientsCount}</span>`;
                } else {
                    clientsBadge = `<span class="badge bg-secondary">0</span>`;
                }

                // RX Power badge with color based on signal strength
                const rxPower = parseFloat(device.rx_power);
                let rxBadgeClass = 'bg-secondary'; // Default for N/A
                let rxDisplay = device.rx_power;

                if (!isNaN(rxPower) && rxPower !== -999) {
                    if (rxPower > -20.00) {
                        rxBadgeClass = 'bg-success'; // Green: Good signal (above -20 dBm)
                    } else if (rxPower >= -23.00) {
                        rxBadgeClass = 'bg-warning'; // Yellow: Moderate signal (-20 to -23 dBm)
                    } else {
                        rxBadgeClass = 'bg-danger'; // Red: Weak signal (below -23 dBm)
                    }
                    rxDisplay = `<span class="badge ${rxBadgeClass}">${device.rx_power} dBm</span>`;
                } else {
                    rxDisplay = `<span class="badge ${rxBadgeClass}">N/A</span>`;
                }

                // Status badge with ping
                let statusBadge;
                if (device.status === 'online') {
                    const ping = device.ping || '-';
                    statusBadge = `<span class="badge online">ON [${ping}ms]</span>`;
                } else {
                    statusBadge = `<span class="badge offline">OFF [-]</span>`;
                }

                html += '<tr>';
                html += `<td><a href="/device-detail.php?id=${encodeURIComponent(device.device_id)}">${device.serial_number}</a></td>`;
                html += `<td>${device.mac_address}</td>`;
                html += `<td>${device.product_class || 'N/A'}</td>`;
                html += `<td>${ipDisplay}</td>`;
                html += `<td>${device.wifi_ssid}</td>`;
                html += `<td>${device.pppoe_username || 'N/A'}</td>`;
                html += `<td>${rxDisplay}</td>`;
                html += `<td>${device.temperature}°C</td>`;
                html += `<td class="text-center">${clientsBadge}</td>`;
                html += `<td>${statusBadge}</td>`;
                html += `<td>`;
                html += `<button class="btn btn-sm btn-primary" onclick="summonDeviceQuick('${device.device_id}')" title="Summon Device"><i class="bi bi-lightning-charge"></i></button>`;
                html += `</td>`;
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else {
            if (result && result.error !== 'timeout') {
                container.innerHTML = '<p class="text-center text-danger">Failed to load recent devices</p>';
            } else {
                container.innerHTML = '<p class="text-center text-warning">Request timeout - please refresh</p>';
            }
        }
    } catch (error) {
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.error('Error loading recent devices:', error);
        }
        container.innerHTML = '<p class="text-center text-danger">Error loading data</p>';
    } finally {
        recentDevicesFetchInProgress = false;
    }
}

async function loadRxUnsupportedDevices() {
    if (rxUnsupportedFetchInProgress) {
        return;
    }

    const container = document.getElementById('rx-unsupported-devices');
    const countBadge = document.getElementById('rx-unsupported-count');
    if (!container || !countBadge) return;

    rxUnsupportedFetchInProgress = true;
    container.innerHTML = '<div class="spinner"></div>';

    try {
        const result = await fetchAPI('/api/rx-unsupported-devices.php', { timeout: 25000 });

        if (!result || !result.success) {
            countBadge.textContent = '!';
            container.innerHTML = '<p class="text-danger mb-0">Gagal memuat daftar RX unsupported.</p>';
            return;
        }

        countBadge.textContent = result.count;

        if (!result.devices || result.devices.length === 0) {
            container.innerHTML = '<p class="text-success mb-0">Semua device sudah punya RX.</p>';
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
        html += '<thead><tr><th>SN</th><th>Tipe</th><th>Last Inform</th></tr></thead><tbody>';
        result.devices.forEach((device) => {
            html += '<tr>';
            html += `<td><a href="/device-detail.php?id=${encodeURIComponent(device.device_id)}">${device.serial_number || 'N/A'}</a></td>`;
            html += `<td>${device.product_class || 'N/A'}</td>`;
            html += `<td>${device.last_inform || 'N/A'}</td>`;
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += '<p class="text-muted small mb-0 mt-2">Reason: parameter optik RX tidak diekspos oleh model TR-069 device pada path yang didukung.</p>';
        container.innerHTML = html;
    } catch (error) {
        container.innerHTML = '<p class="text-danger mb-0">Error memuat daftar RX unsupported.</p>';
    } finally {
        rxUnsupportedFetchInProgress = false;
    }
}

function formatOltSyncAge(lastSyncedAt) {
    if (!lastSyncedAt || lastSyncedAt === 'Belum sync') return 'Belum sync';
    const syncDate = new Date(lastSyncedAt.replace(' ', 'T'));
    if (Number.isNaN(syncDate.getTime())) return lastSyncedAt;
    const diffMin = Math.max(0, Math.round((Date.now() - syncDate.getTime()) / 60000));
    return `${diffMin} menit`;
}

function dashboardEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function renderDashboardActionCard(title, description, actions = []) {
    const actionsHtml = actions.map(action => `
        <a class="btn btn-sm ${action.className || 'btn-outline-dark'}" href="${action.href || '#'}"${action.targetBlank ? ' target="_blank"' : ''}>
            ${action.icon ? `<i class="bi bi-${action.icon}"></i> ` : ''}${dashboardEscapeHtml(action.label || 'Buka')}
        </a>
    `).join('');

    return `
        <div class="border rounded p-3 bg-light-subtle">
            <div class="fw-semibold mb-1">${dashboardEscapeHtml(title)}</div>
            <div class="text-muted small mb-3">${dashboardEscapeHtml(description)}</div>
            ${actionsHtml ? `<div class="d-flex flex-wrap gap-2">${actionsHtml}</div>` : ''}
        </div>
    `;
}

async function loadOltHealthBoard() {
    if (oltHealthFetchInProgress) {
        return;
    }

    const container = document.getElementById('olt-health-board');
    const countBadge = document.getElementById('olt-health-count');
    if (!container || !countBadge) return;

    oltHealthFetchInProgress = true;
    container.innerHTML = '<div class="spinner"></div>';

    try {
        const result = await fetchAPI('/api/olt-inventory-summary.php', { timeout: 25000 });
        if (!result || !result.success) {
            countBadge.textContent = '!';
            container.innerHTML = '<p class="text-danger mb-0">Gagal memuat OLT health board.</p>';
            return;
        }

        const items = Object.values(result.summary || {});
        countBadge.textContent = items.length;

        if (items.length === 0) {
            container.innerHTML = '<p class="text-muted mb-0">Belum ada inventory OLT yang bisa ditampilkan.</p>';
            return;
        }

        const sorted = items.sort((a, b) => {
            const aRate = a.inventory_total > 0 ? (a.online_total / a.inventory_total) : 0;
            const bRate = b.inventory_total > 0 ? (b.online_total / b.inventory_total) : 0;
            return aRate - bRate || (b.online_missing_total || 0) - (a.online_missing_total || 0);
        });

        const filterInput = document.getElementById('olt-health-filter-input');
        const keyword = filterInput ? filterInput.value.trim().toLowerCase() : '';
        const filteredItems = sorted.filter((item) => {
            const total = Number(item.inventory_total || 0);
            const online = Number(item.online_total || 0);
            const onlineMissing = Number(item.online_missing_total || 0);
            const syncAgeMin = getOltSyncAgeMinutes(item.last_synced_at);
            const rate = total > 0 ? Math.round((online / total) * 100) : 0;
            const name = String(item.olt_name || '').toLowerCase();
            const state = String(item.sync_state || '').toLowerCase();

            if (keyword && !name.includes(keyword)) {
                return false;
            }

            if (oltHealthFilterMode === 'critical') {
                return rate < 70 || onlineMissing > 10 || Number(item.critical_rx_total || 0) > 0;
            }

            if (oltHealthFilterMode === 'gap') {
                return onlineMissing > 0;
            }

            if (oltHealthFilterMode === 'stale') {
                return syncAgeMin === null || syncAgeMin > 60 || state === 'error';
            }

            return true;
        });

        countBadge.textContent = `${filteredItems.length}/${items.length}`;

        if (filteredItems.length === 0) {
            container.innerHTML = renderDashboardActionCard(
                'Tidak ada OLT yang cocok',
                'Coba ubah filter pencarian atau mode board. Data inventory tetap aman dan read-only.',
                [
                    { label: 'Reset Filter', href: '#', icon: 'arrow-counterclockwise', className: 'btn-outline-secondary', onClick: 'resetOltHealthFilter(); return false;' }
                ]
            );
            return;
        }

        let html = '<div class="row g-3">';
        filteredItems.slice(0, 12).forEach((item) => {
            const total = Number(item.inventory_total || 0);
            const online = Number(item.online_total || 0);
            const inAcs = Number(item.in_acs_total || 0);
            const missing = Number(item.missing_total || 0);
            const onlineMissing = Number(item.online_missing_total || 0);
            const offline = Number(item.offline_total || 0);
            const criticalRx = Number(item.critical_rx_total || 0);
            const warningRx = Number(item.warning_rx_total || 0);
            const noRx = Number(item.no_rx_total || 0);
            const rate = total > 0 ? Math.round((online / total) * 100) : 0;
            const criticalClass = rate < 70 || onlineMissing > 10 ? 'border-danger' : (rate < 90 || onlineMissing > 0 ? 'border-warning' : 'border-success');
            const syncAge = formatOltSyncAge(item.last_synced_at);
            const syncState = String(item.sync_state || 'unknown');
            const detailUrl = `/olt-onu-inventory.php?olt_id=${encodeURIComponent(item.olt_item_id)}`;
            const firstInformUrl = `/devices.php?view=first-inform&olt=${encodeURIComponent(item.olt_name || '')}`;

            html += `
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 ${criticalClass}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-semibold">${item.olt_name}</div>
                                    <div class="text-muted small">Sync ${syncAge}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge ${rate >= 90 ? 'bg-success' : (rate >= 70 ? 'bg-warning text-dark' : 'bg-danger')}">${rate}% online</span>
                                    <div class="small text-muted mt-1">${dashboardEscapeHtml(syncState)}</div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="badge bg-primary">ONT ${online}/${total}</span>
                                <span class="badge bg-secondary">Offline ${offline}</span>
                                <span class="badge bg-success">ACS ${inAcs}</span>
                                <span class="badge bg-warning text-dark">Belum ACS ${missing}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-danger">Online belum ACS ${onlineMissing}</span>
                                <span class="badge bg-danger-subtle text-danger-emphasis border">RX kritis ${criticalRx}</span>
                                <span class="badge bg-warning-subtle text-warning-emphasis border">RX warning ${warningRx}</span>
                                <span class="badge bg-dark-subtle text-dark-emphasis border">RX kosong ${noRx}</span>
                            </div>
                            <div class="d-grid gap-2">
                                <a class="btn btn-sm btn-outline-dark" href="${detailUrl}" target="_blank">Buka Inventory</a>
                                <a class="btn btn-sm btn-outline-secondary" href="${firstInformUrl}" target="_blank">Fokus First Inform</a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (error) {
        container.innerHTML = '<p class="text-danger mb-0">Error memuat OLT health board.</p>';
    } finally {
        oltHealthFetchInProgress = false;
    }
}

async function loadFirstInformGap() {
    if (firstInformGapFetchInProgress) {
        return;
    }

    const container = document.getElementById('first-inform-gap-list');
    const summaryContainer = document.getElementById('first-inform-gap-summary');
    const countBadge = document.getElementById('first-inform-gap-count');
    if (!container || !summaryContainer || !countBadge) return;

    firstInformGapFetchInProgress = true;
    container.innerHTML = '<div class="spinner"></div>';
    summaryContainer.innerHTML = '<div class="spinner"></div>';

    try {
        const oltFilter = document.getElementById('first-inform-olt-filter');
        const statusFilter = document.getElementById('first-inform-status-filter');
        const limitFilter = document.getElementById('first-inform-limit-filter');
        const params = new URLSearchParams();
        if (oltFilter && oltFilter.value) params.set('olt', oltFilter.value);
        if (statusFilter && statusFilter.value) params.set('status', statusFilter.value);
        if (limitFilter && limitFilter.value) params.set('limit', limitFilter.value);
        const queryString = params.toString() ? `?${params.toString()}` : '';
        const result = await fetchAPI(`/api/first-inform-gap.php${queryString}`, { timeout: 25000 });

        if (!result || !result.success) {
            countBadge.textContent = '!';
            summaryContainer.innerHTML = '<p class="text-danger mb-2">Gagal memuat ringkasan per OLT.</p>';
            container.innerHTML = '<p class="text-danger mb-0">Gagal memuat daftar first inform gap.</p>';
            return;
        }

        if (result.topology_ready === false) {
            countBadge.textContent = '0';
            summaryContainer.innerHTML = '<p class="text-muted mb-2">Belum ada inventory OLT/ONU untuk menghitung gap bootstrap.</p>';
            container.innerHTML = '<p class="text-muted mb-0">Inventory ONU/OLT di GACS masih kosong, jadi belum ada target first-inform gap.</p>';
            return;
        }

        countBadge.textContent = result.filters?.olt || (result.filters?.status && result.filters.status !== 'all')
            ? `${result.filtered_missing_count || 0}/${result.missing_count || 0}`
            : result.missing_count;

        if (result.summary_by_olt && result.summary_by_olt.length > 0) {
            hydrateFirstInformOltFilter(result.summary_by_olt, result.filters?.olt || '');
            let summaryHtml = '<div class="row g-2">';
            result.summary_by_olt.slice(0, 6).forEach((item) => {
                const detailUrl = `/olt-onu-inventory.php?olt_id=${encodeURIComponent(item.olt_id)}`;
                const onlineReady = Number(item.online_total || 0) - Number(item.online_missing_total || 0);
                const severityClass = Number(item.online_missing_total || 0) > 10
                    ? 'border-danger'
                    : (Number(item.online_missing_total || 0) > 0 ? 'border-warning' : 'border-success');
                summaryHtml += `
                    <div class="col-md-6">
                        <a href="${detailUrl}" target="_blank" class="text-decoration-none text-reset">
                            <div class="border rounded p-2 h-100 ${severityClass}">
                                <div class="fw-semibold mb-2">${item.olt_name}</div>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge bg-success">Sudah ACS ${item.in_acs_total}</span>
                                    <span class="badge bg-danger">Online belum ACS ${item.online_missing_total}</span>
                                    <span class="badge bg-warning text-dark">Total gap ${item.missing_total}</span>
                                </div>
                                <div class="small text-muted">Checklist bootstrap: OLT online <strong>${item.online_total}</strong> → siap ACS <strong>${onlineReady}</strong> → gap aktif <strong>${item.online_missing_total}</strong></div>
                            </div>
                        </a>
                    </div>
                `;
            });
            summaryHtml += '</div>';
            summaryHtml += `<p class="text-muted small mb-0 mt-3">Online belum ACS: ${result.online_missing_count || 0} ONU. Fokus ini yang paling cepat untuk bootstrap/TR-069 first-inform.</p>`;
            summaryContainer.innerHTML = summaryHtml;
        } else {
            summaryContainer.innerHTML = '<p class="text-muted mb-2">Belum ada ringkasan gap per OLT.</p>';
        }

        if (!result.items || result.items.length === 0) {
            container.innerHTML = renderDashboardActionCard(
                'Tidak ada gap untuk filter ini',
                'Filter saat ini tidak menemukan ONT yang belum first-inform. Coba ganti OLT, status, atau tampilkan lebih banyak data.',
                [
                    { label: 'Reset Filter', href: '#', icon: 'arrow-counterclockwise', className: 'btn-outline-secondary', onClick: 'resetFirstInformFilter(); return false;' }
                ]
            );
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
        html += '<thead><tr><th>Customer</th><th>OLT/ODP</th><th>Port</th><th>Status OLT</th><th>Aksi</th></tr></thead><tbody>';
        result.items.forEach((item) => {
            const inventoryUrl = `/olt-onu-inventory.php?olt_id=${encodeURIComponent(item.olt_item_id)}`;
            const customerName = item.customer_name || item.onu_name || 'N/A';
            const serialGuess = item.serial_guess || '';
            const deviceDetailUrl = serialGuess ? `/devices.php?search=${encodeURIComponent(serialGuess)}` : inventoryUrl;
            const oltStatus = item.onu_status === 'online'
                ? '<span class="badge bg-danger">Online Belum ACS</span>'
                : item.onu_status === 'offline'
                    ? '<span class="badge bg-secondary">Offline</span>'
                    : '<span class="badge bg-warning text-dark">Unknown</span>';
            html += '<tr>';
            html += `<td><a href="${deviceDetailUrl}" target="_blank">${customerName}</a><br><small class="text-muted">${serialGuess || 'N/A'}</small></td>`;
            html += `<td>${item.olt_name || 'N/A'}<br><small class="text-muted">${item.odp_name || 'N/A'}</small></td>`;
            html += `<td>${item.odp_port || 'N/A'}</td>`;
            html += `<td>${oltStatus}<br><small class="text-muted">Checklist: Bootstrap → Inform → Provision</small></td>`;
            html += `<td><div class="d-grid gap-1"><a class="btn btn-sm btn-outline-secondary" href="${inventoryUrl}" target="_blank">Inventory</a><a class="btn btn-sm btn-outline-dark" href="${deviceDetailUrl}" target="_blank">Cari Device</a></div></td>`;
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += `<p class="text-muted small mb-0 mt-2">Menampilkan ${result.items.length} dari ${result.filtered_missing_count || result.missing_count || 0} gap yang cocok. GenieACS tidak bisa push read ke device yang belum pernah inform. Fokuskan dulu ONU yang online di OLT tetapi masih belum ACS.</p>`;
        container.innerHTML = html;
    } catch (error) {
        summaryContainer.innerHTML = '<p class="text-danger mb-2">Error memuat ringkasan per OLT.</p>';
        container.innerHTML = '<p class="text-danger mb-0">Error memuat daftar first inform gap.</p>';
    } finally {
        firstInformGapFetchInProgress = false;
    }
}

function getOltSyncAgeMinutes(value) {
    if (!value) return null;
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return null;
    return Math.round((Date.now() - parsed.getTime()) / 60000);
}

function setOltHealthFilter(mode, buttonEl) {
    oltHealthFilterMode = mode;
    document.querySelectorAll('[data-olt-health-filter]').forEach((btn) => btn.classList.remove('active'));
    if (buttonEl) {
        buttonEl.classList.add('active');
    }
    loadOltHealthBoard();
}

function resetOltHealthFilter() {
    const input = document.getElementById('olt-health-filter-input');
    if (input) input.value = '';
    const defaultButton = document.querySelector('[data-olt-health-filter="all"]');
    setOltHealthFilter('all', defaultButton);
}

function hydrateFirstInformOltFilter(summaryRows, selectedValue) {
    const select = document.getElementById('first-inform-olt-filter');
    if (!select) return;
    const current = selectedValue || select.value || '';
    const options = ['<option value="">Semua OLT</option>'];
    summaryRows.forEach((item) => {
        const value = String(item.olt_name || '');
        const selected = value === current ? 'selected' : '';
        options.push(`<option value="${dashboardEscapeHtml(value)}" ${selected}>${dashboardEscapeHtml(value)} (${item.online_missing_total || 0} online belum ACS)</option>`);
    });
    select.innerHTML = options.join('');
}

function resetFirstInformFilter() {
    const olt = document.getElementById('first-inform-olt-filter');
    const status = document.getElementById('first-inform-status-filter');
    const limit = document.getElementById('first-inform-limit-filter');
    if (olt) olt.value = '';
    if (status) status.value = 'all';
    if (limit) limit.value = '50';
    loadFirstInformGap();
}

async function loadTaskQueueMonitor() {
    if (taskQueueFetchInProgress) return;

    const countBadge = document.getElementById('task-queue-count');
    const summaryContainer = document.getElementById('task-queue-summary');
    const byNameContainer = document.getElementById('task-queue-by-name');
    const contextContainer = document.getElementById('task-queue-context');
    const faultSamplesContainer = document.getElementById('task-queue-fault-samples');
    const filterInput = document.getElementById('task-queue-filter-input');
    if (!countBadge || !summaryContainer || !byNameContainer || !contextContainer || !faultSamplesContainer) return;

    taskQueueFetchInProgress = true;
    summaryContainer.innerHTML = '<div class="spinner"></div>';
    byNameContainer.innerHTML = '<div class="spinner"></div>';
    contextContainer.innerHTML = '<div class="spinner"></div>';
    faultSamplesContainer.innerHTML = '<div class="spinner"></div>';
    const filterValue = filterInput ? filterInput.value.trim() : '';

    try {
        const queryString = filterValue ? `?q=${encodeURIComponent(filterValue)}` : '';
        const result = await fetchAPI(`/api/task-queue-monitor.php${queryString}`, { timeout: 25000 });
        if (!result || !result.success) {
            countBadge.textContent = '!';
            summaryContainer.innerHTML = '<p class="text-danger mb-0">Gagal memuat task queue monitor.</p>';
            byNameContainer.innerHTML = '';
            contextContainer.innerHTML = '';
            faultSamplesContainer.innerHTML = '';
            return;
        }

        const summary = result.summary || {};
        countBadge.textContent = summary.queued_total ?? 0;
        summaryContainer.innerHTML = `
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-primary">Queued: ${summary.queued_total ?? 0}</span>
                <span class="badge bg-danger">Faults 24h: ${summary.faults_24h ?? 0}</span>
                <span class="badge bg-warning text-dark">CR Fault: ${summary.connection_request_faults ?? 0}</span>
                <span class="badge bg-secondary">Timeout: ${summary.timeout_faults ?? 0}</span>
                <span class="badge bg-secondary">Faults Total: ${summary.faults_total ?? 0}</span>
            </div>
            <p class="text-muted small mb-0 mt-2">Latest task: ${summary.latest_task_at || 'N/A'} | Oldest queued: ${summary.oldest_task_at || 'N/A'}${filterValue ? ` | Filter: <strong>${dashboardEscapeHtml(filterValue)}</strong>` : ''}</p>
        `;

        const byName = result.queued_by_name || {};
        const entries = Object.entries(byName);
        if (entries.length === 0) {
            byNameContainer.innerHTML = renderDashboardActionCard(
                'Tidak ada queued task',
                filterValue
                    ? 'Tidak ada task yang cocok dengan filter saat ini. Coba pakai SN, OLT, vendor, atau nama task lain.'
                    : 'Antrian task sedang kosong. Ini sehat, tapi Anda tetap bisa cek fault sample untuk timeout atau connection request issue.',
                [
                    { label: 'Buka Perangkat', href: '/devices.php', icon: 'router', className: 'btn-outline-dark' },
                    { label: 'Alarm Center', href: '/alarm-events.php', icon: 'bell', className: 'btn-outline-secondary' }
                ]
            );
        } else {
            let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
            html += '<thead><tr><th>Task</th><th>Jumlah</th></tr></thead><tbody>';
            entries.forEach(([name, count]) => {
                html += `<tr><td>${name}</td><td><span class="badge bg-primary">${count}</span></td></tr>`;
            });
            html += '</tbody></table></div>';
            byNameContainer.innerHTML = html;
        }

        const queuedByOlt = Object.entries(result.queued_by_olt || {});
        const queuedByVendor = Object.entries(result.queued_by_vendor || {});
        const faultsByOlt = Object.entries(result.faults_by_olt || {});
        const faultsByVendor = Object.entries(result.faults_by_vendor || {});
        let contextHtml = '<div class="row g-3">';
        contextHtml += '<div class="col-md-6">';
        if (queuedByOlt.length === 0) {
            contextHtml += '<p class="text-muted small mb-0">Belum ada queued task per OLT.</p>';
        } else {
            contextHtml += '<div class="fw-semibold small mb-2">Queued per OLT</div><div class="d-flex flex-wrap gap-2">';
            queuedByOlt.forEach(([name, count]) => {
                contextHtml += `<span class="badge bg-primary">${dashboardEscapeHtml(name)} ${count}</span>`;
            });
            contextHtml += '</div>';
        }
        contextHtml += '</div>';
        contextHtml += '<div class="col-md-6">';
        if (faultsByOlt.length === 0) {
            contextHtml += '<p class="text-muted small mb-0">Belum ada fault per OLT.</p>';
        } else {
            contextHtml += '<div class="fw-semibold small mb-2">Fault per OLT</div><div class="d-flex flex-wrap gap-2">';
            faultsByOlt.forEach(([name, count]) => {
                contextHtml += `<span class="badge bg-danger">${dashboardEscapeHtml(name)} ${count}</span>`;
            });
            contextHtml += '</div>';
        }
        contextHtml += '</div>';
        contextHtml += '<div class="col-md-6">';
        if (queuedByVendor.length === 0) {
            contextHtml += '<p class="text-muted small mb-0">Belum ada queued task per vendor.</p>';
        } else {
            contextHtml += '<div class="fw-semibold small mb-2">Queued per Vendor</div><div class="d-flex flex-wrap gap-2">';
            queuedByVendor.forEach(([name, count]) => {
                contextHtml += `<span class="badge bg-secondary">${dashboardEscapeHtml(name)} ${count}</span>`;
            });
            contextHtml += '</div>';
        }
        contextHtml += '</div>';
        contextHtml += '<div class="col-md-6">';
        if (faultsByVendor.length === 0) {
            contextHtml += '<p class="text-muted small mb-0">Belum ada fault per vendor.</p>';
        } else {
            contextHtml += '<div class="fw-semibold small mb-2">Fault per Vendor</div><div class="d-flex flex-wrap gap-2">';
            faultsByVendor.forEach(([name, count]) => {
                contextHtml += `<span class="badge bg-dark">${dashboardEscapeHtml(name)} ${count}</span>`;
            });
            contextHtml += '</div>';
        }
        contextHtml += '</div></div>';
        contextContainer.innerHTML = contextHtml;

        const faultSamples = result.fault_samples || [];
        if (faultSamples.length === 0) {
            faultSamplesContainer.innerHTML = renderDashboardActionCard(
                'Belum ada fault sample terbaru',
                'Belum ada timeout atau connection request fault terbaru yang terekam di queue monitor.',
                [
                    { label: 'Lihat Alarm', href: '/alarm-events.php', icon: 'bell', className: 'btn-outline-secondary' }
                ]
            );
            return;
        }

        let faultHtml = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
        faultHtml += '<thead><tr><th>Device</th><th>Konteks</th><th>Fault</th><th>Waktu</th></tr></thead><tbody>';
        faultSamples.slice(0, 8).forEach((row) => {
            const msg = String(row.message || 'N/A');
            const badge = msg.toLowerCase().includes('connection request')
                ? '<span class="badge bg-warning text-dark">CR</span>'
                : (msg.toLowerCase().includes('timeout') ? '<span class="badge bg-secondary">Timeout</span>' : '<span class="badge bg-danger">Fault</span>');
            faultHtml += '<tr>';
            faultHtml += `<td><code>${row.serial_number || row.device || 'N/A'}</code><br><small class="text-muted">${dashboardEscapeHtml(row.customer_name || 'N/A')}</small></td>`;
            faultHtml += `<td>${dashboardEscapeHtml(row.olt_name || 'N/A')}<br><small class="text-muted">${dashboardEscapeHtml(row.product_class || 'N/A')} · ${dashboardEscapeHtml(row.channel || 'N/A')}</small></td>`;
            faultHtml += `<td>${badge} <span class="small">${msg}</span></td>`;
            faultHtml += `<td>${row.timestamp || 'N/A'}</td>`;
            faultHtml += '</tr>';
        });
        faultHtml += '</tbody></table></div>';
        faultSamplesContainer.innerHTML = faultHtml;
    } catch (error) {
        summaryContainer.innerHTML = '<p class="text-danger mb-0">Error memuat task queue monitor.</p>';
        byNameContainer.innerHTML = '';
        contextContainer.innerHTML = '';
        faultSamplesContainer.innerHTML = '';
    } finally {
        taskQueueFetchInProgress = false;
    }
}

function clearTaskQueueFilter() {
    const input = document.getElementById('task-queue-filter-input');
    if (!input) return;
    input.value = '';
    loadTaskQueueMonitor();
}

async function loadConnectionReachability() {
    if (reachabilityFetchInProgress) return;

    const totalBadge = document.getElementById('reachability-total');
    const summaryContainer = document.getElementById('reachability-summary');
    const byOltContainer = document.getElementById('reachability-by-olt');
    const byProductContainer = document.getElementById('reachability-by-product');
    const samplesContainer = document.getElementById('reachability-samples');
    if (!totalBadge || !summaryContainer || !byOltContainer || !byProductContainer || !samplesContainer) return;

    reachabilityFetchInProgress = true;
    summaryContainer.innerHTML = '<div class="spinner"></div>';
    byOltContainer.innerHTML = '<div class="spinner"></div>';
    byProductContainer.innerHTML = '<div class="spinner"></div>';
    samplesContainer.innerHTML = '<div class="spinner"></div>';

    try {
        const result = await fetchAPI('/api/connection-reachability.php', { timeout: 25000 });
        if (!result || !result.success) {
            totalBadge.textContent = '!';
            summaryContainer.innerHTML = '<p class="text-danger mb-0">Gagal memuat reachability.</p>';
            byOltContainer.innerHTML = '';
            byProductContainer.innerHTML = '';
            samplesContainer.innerHTML = '';
            return;
        }

        const summary = result.summary || {};
        totalBadge.textContent = summary.total ?? 0;
        summaryContainer.innerHTML = `
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-success">Inform hidup: ${summary.recent_inform_alive ?? 0}</span>
                <span class="badge bg-danger">Probe fail: ${summary.recent_probe_failed ?? 0}</span>
                <span class="badge bg-warning text-dark">Private NAT: ${summary.private_nat ?? 0}</span>
                <span class="badge bg-secondary">URL only: ${summary.url_only ?? 0}</span>
                <span class="badge bg-secondary">Missing URL: ${summary.missing_url ?? 0}</span>
            </div>
            <p class="text-muted small mb-0 mt-2">Inform hidup berarti ACS baru saja melihat device. Probe fail berarti ada fault connection request/timeout terbaru, jadi lebih dekat ke reachability nyata daripada sekadar URL ada.</p>
        `;

        const byOltRows = Object.entries(result.by_olt || {});
        if (byOltRows.length === 0) {
            byOltContainer.innerHTML = '<p class="text-muted mb-0">Belum ada ringkasan reachability per OLT.</p>';
        } else {
            let oltHtml = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
            oltHtml += '<thead><tr><th>OLT</th><th>Inform hidup</th><th>Probe fail</th><th>Private NAT</th><th>URL only</th><th>Missing URL</th></tr></thead><tbody>';
            byOltRows.forEach(([oltName, counts]) => {
                oltHtml += '<tr>';
                oltHtml += `<td>${dashboardEscapeHtml(oltName)}</td>`;
                oltHtml += `<td><span class="badge bg-success">${counts.recent_inform_alive || 0}</span></td>`;
                oltHtml += `<td><span class="badge bg-danger">${counts.recent_probe_failed || 0}</span></td>`;
                oltHtml += `<td><span class="badge bg-warning text-dark">${counts.private_nat || 0}</span></td>`;
                oltHtml += `<td><span class="badge bg-secondary">${counts.url_only || 0}</span></td>`;
                oltHtml += `<td><span class="badge bg-dark">${counts.missing_url || 0}</span></td>`;
                oltHtml += '</tr>';
            });
            oltHtml += '</tbody></table></div>';
            byOltContainer.innerHTML = oltHtml;
        }

        const rows = Object.entries(result.by_product_class || {});
        if (rows.length === 0) {
            byProductContainer.innerHTML = '<p class="text-muted mb-0">Belum ada data product class.</p>';
        } else {
            let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
            html += '<thead><tr><th>Product Class</th><th>Inform hidup</th><th>Probe fail</th><th>Private NAT</th><th>URL only</th><th>Missing URL</th></tr></thead><tbody>';
            rows.forEach(([productClass, counts]) => {
                html += '<tr>';
                html += `<td>${dashboardEscapeHtml(productClass)}</td>`;
                html += `<td><span class="badge bg-success">${counts.recent_inform_alive || 0}</span></td>`;
                html += `<td><span class="badge bg-danger">${counts.recent_probe_failed || 0}</span></td>`;
                html += `<td><span class="badge bg-warning text-dark">${counts.private_nat || 0}</span></td>`;
                html += `<td><span class="badge bg-secondary">${counts.url_only || 0}</span></td>`;
                html += `<td><span class="badge bg-dark">${counts.missing_url || 0}</span></td>`;
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            byProductContainer.innerHTML = html;
        }

        const sampleBlocks = result.samples || {};
        let sampleHtml = '<div class="row g-3">';
        const renderSampleCard = (title, items, badgeClass, emptyText, formatter) => {
            if (!items || items.length === 0) {
                return `
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold mb-2">${title}</div>
                            <p class="text-muted small mb-0">${emptyText}</p>
                        </div>
                    </div>
                `;
            }
            let inner = `
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">${title}</div>
            `;
            items.slice(0, 5).forEach((item) => {
                inner += `
                    <div class="small mb-2">
                        <span class="badge ${badgeClass} mb-1">${dashboardEscapeHtml(item.serial_number || item.device_id || 'N/A')}</span><br>
                        ${formatter(item)}
                    </div>
                `;
            });
            inner += '</div></div>';
            return inner;
        };
        sampleHtml += renderSampleCard(
            'Recent Probe Fail',
            sampleBlocks.recent_probe_failed || [],
            'bg-danger',
            'Belum ada sample fault connection request/timeout terbaru.',
            (item) => `${dashboardEscapeHtml(item.olt_name || 'N/A')}<br><span class="text-muted">${dashboardEscapeHtml(item.message || 'Fault')}</span>`
        );
        sampleHtml += renderSampleCard(
            'URL Only',
            sampleBlocks.url_only || [],
            'bg-secondary',
            'Belum ada sample device URL-only.',
            (item) => `${dashboardEscapeHtml(item.olt_name || 'N/A')}<br><span class="text-muted">Host ${dashboardEscapeHtml(item.host || 'N/A')}</span>`
        );
        sampleHtml += renderSampleCard(
            'Missing URL',
            sampleBlocks.missing_url || [],
            'bg-dark',
            'Belum ada sample device tanpa URL.',
            (item) => `${dashboardEscapeHtml(item.olt_name || 'N/A')}<br><span class="text-muted">${dashboardEscapeHtml(item.product_class || 'N/A')}</span>`
        );
        sampleHtml += '</div>';
        samplesContainer.innerHTML = sampleHtml;
    } catch (error) {
        summaryContainer.innerHTML = '<p class="text-danger mb-0">Error memuat reachability.</p>';
        byOltContainer.innerHTML = '';
        byProductContainer.innerHTML = '';
        samplesContainer.innerHTML = '';
    } finally {
        reachabilityFetchInProgress = false;
    }
}

async function loadOpticalTrends() {
    if (opticalTrendFetchInProgress) return;

    const countBadge = document.getElementById('optical-trend-count');
    const redamanSummaryContainer = document.getElementById('optical-redaman-summary');
    const redamanByOltContainer = document.getElementById('optical-redaman-by-olt');
    const summaryContainer = document.getElementById('optical-trend-summary');
    const listContainer = document.getElementById('optical-trend-list');
    if (!countBadge || !redamanSummaryContainer || !redamanByOltContainer || !summaryContainer || !listContainer) return;

    opticalTrendFetchInProgress = true;
    redamanSummaryContainer.innerHTML = '<div class="spinner"></div>';
    redamanByOltContainer.innerHTML = '<div class="spinner"></div>';
    summaryContainer.innerHTML = '<div class="spinner"></div>';
    listContainer.innerHTML = '<div class="spinner"></div>';

    try {
        const result = await fetchAPI('/api/optical-trends.php', { timeout: 20000 });
        if (!result || !result.success) {
            countBadge.textContent = '!';
            redamanSummaryContainer.innerHTML = '<p class="text-danger mb-0">Gagal memuat ringkasan redaman.</p>';
            redamanByOltContainer.innerHTML = '';
            summaryContainer.innerHTML = '<p class="text-danger mb-0">Gagal memuat trend optik.</p>';
            listContainer.innerHTML = '';
            return;
        }

        const summary = result.summary || {};
        const distribution = result.distribution || {};
        const inventoryByOlt = result.inventory_by_olt || [];
        const items = result.items || [];
        countBadge.textContent = summary.total ?? 0;

        redamanSummaryContainer.innerHTML = `
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">Redaman All</div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge bg-success">Normal ${distribution.normal_total ?? 0}</span>
                    <span class="badge bg-warning text-dark">Warning ${distribution.warning_total ?? 0}</span>
                    <span class="badge bg-danger">Kritis ${distribution.critical_total ?? 0}</span>
                    <span class="badge bg-dark">No RX ${distribution.no_rx_total ?? 0}</span>
                </div>
                <p class="text-muted small mb-0">Total inventory optik: ${distribution.inventory_total ?? 0} ONT di ${distribution.inventory_olt_total ?? 0} OLT.</p>
            </div>
        `;

        if (!inventoryByOlt.length) {
            redamanByOltContainer.innerHTML = '<p class="text-muted mb-0">Belum ada ringkasan redaman per OLT.</p>';
        } else {
            let redamanHtml = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
            redamanHtml += '<thead><tr><th>OLT</th><th>Normal</th><th>Warning</th><th>Kritis</th><th>No RX</th><th>Worst RX</th></tr></thead><tbody>';
            inventoryByOlt.forEach((row) => {
                const worstRx = row.worst_rx !== null ? `${Number(row.worst_rx).toFixed(2)} dBm` : 'N/A';
                redamanHtml += '<tr>';
                redamanHtml += `<td>${dashboardEscapeHtml(row.olt_name || 'N/A')}</td>`;
                redamanHtml += `<td><span class="badge bg-success">${row.normal_total || 0}</span></td>`;
                redamanHtml += `<td><span class="badge bg-warning text-dark">${row.warning_total || 0}</span></td>`;
                redamanHtml += `<td><span class="badge bg-danger">${row.critical_total || 0}</span></td>`;
                redamanHtml += `<td><span class="badge bg-dark">${row.no_rx_total || 0}</span></td>`;
                redamanHtml += `<td>${dashboardEscapeHtml(worstRx)}</td>`;
                redamanHtml += '</tr>';
            });
            redamanHtml += '</tbody></table></div>';
            redamanByOltContainer.innerHTML = redamanHtml;
        }

        summaryContainer.innerHTML = `
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-primary">Dipantau: ${summary.total ?? 0}</span>
                <span class="badge bg-danger">Kritis: ${summary.critical ?? 0}</span>
                <span class="badge bg-warning text-dark">Warning: ${summary.warning ?? 0}</span>
            </div>
            <p class="text-muted small mb-0 mt-2">Daftar ini menyorot ONT dengan redaman yang memburuk cepat dari baseline trend monitor, jadi tim bisa bergerak sebelum benar-benar down.</p>
        `;

        if (items.length === 0) {
            listContainer.innerHTML = renderDashboardActionCard(
                'Belum ada penurunan redaman tajam',
                'Trend RX saat ini relatif stabil. Kalau panel ini tetap kosong padahal banyak redaman kosong, cek vendor parameter dan first-inform ACS.',
                [
                    { label: 'Buka Perangkat', href: '/devices.php', icon: 'router', className: 'btn-outline-dark' },
                    { label: 'Alarm Center', href: '/alarm-events.php', icon: 'bell', className: 'btn-outline-secondary' }
                ]
            );
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">';
        html += '<thead><tr><th>SN / Customer</th><th>OLT</th><th>Baseline</th><th>Sekarang</th><th>Drop</th><th>Update</th></tr></thead><tbody>';
        items.forEach((item) => {
            const drop = Number(item.drop_db || 0);
            const severityBadge = drop >= 6
                ? '<span class="badge bg-danger">Kritis</span>'
                : '<span class="badge bg-warning text-dark">Warning</span>';
            html += '<tr>';
            html += `<td><a href="/device-detail.php?id=${encodeURIComponent(item.device_id)}">${item.serial_number || 'N/A'}</a><br><small class="text-muted">${item.customer_name || 'N/A'}</small></td>`;
            html += `<td>${item.olt_name || 'N/A'}</td>`;
            html += `<td>${item.baseline_rx ?? 'N/A'} dBm</td>`;
            html += `<td>${item.current_rx ?? 'N/A'} dBm</td>`;
            html += `<td>${severityBadge} <span class="fw-semibold">${drop.toFixed(2)} dB</span></td>`;
            html += `<td>${item.updated_at || 'N/A'}</td>`;
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        listContainer.innerHTML = html;
    } catch (error) {
        redamanSummaryContainer.innerHTML = '<p class="text-danger mb-0">Error memuat ringkasan redaman.</p>';
        redamanByOltContainer.innerHTML = '';
        summaryContainer.innerHTML = '<p class="text-danger mb-0">Error memuat trend optik.</p>';
        listContainer.innerHTML = '';
    } finally {
        opticalTrendFetchInProgress = false;
    }
}

let currentSummonDeviceId = null;

function summonDeviceQuick(deviceId) {
    currentSummonDeviceId = deviceId;
    document.getElementById('summon-device-id').textContent = deviceId;
    const modal = new bootstrap.Modal(document.getElementById('summonModal'), {
        backdrop: false
    });
    modal.show();
}

function showNotInMapAlert(serialNumber) {
    document.getElementById('not-in-map-serial').textContent = decodeURIComponent(serialNumber);
    const modal = new bootstrap.Modal(document.getElementById('notInMapModal'), {
        backdrop: false
    });
    modal.show();
}

async function confirmSummon() {
    if (!currentSummonDeviceId) return;

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('summonModal'));
    modal.hide();

    showLoading();

    const result = await fetchAPI('/api/summon-device.php', {
        method: 'POST',
        body: JSON.stringify({ device_id: currentSummonDeviceId })
    });

    hideLoading();

    if (result && result.success) {
        showToast('Device summon berhasil!', 'success');
    } else {
        showToast(result.message || 'Gagal summon device', 'danger');
    }

    currentSummonDeviceId = null;
}

// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($genieacsConfigured): ?>
        const taskQueueInput = document.getElementById('task-queue-filter-input');
        const oltHealthInput = document.getElementById('olt-health-filter-input');
        if (taskQueueInput) {
            taskQueueInput.addEventListener('input', function() {
                clearTimeout(taskQueueFilterTimer);
                taskQueueFilterTimer = setTimeout(() => loadTaskQueueMonitor(), 250);
            });
        }
        if (oltHealthInput) {
            oltHealthInput.addEventListener('input', function() {
                clearTimeout(oltHealthFilterTimer);
                oltHealthFilterTimer = setTimeout(() => loadOltHealthBoard(), 200);
            });
        }
        loadDashboardData();
        loadUplinkData();
        loadRecentDevices();
        loadOltHealthBoard();
        loadRxUnsupportedDevices();
        loadFirstInformGap();
        loadTaskQueueMonitor();
        loadConnectionReachability();
        loadOpticalTrends();
        // Auto refresh every 30 seconds
        setInterval(() => {
            loadDashboardData();
            loadUplinkData();
            loadRecentDevices();
            loadOltHealthBoard();
            loadRxUnsupportedDevices();
            loadFirstInformGap();
            loadTaskQueueMonitor();
            loadConnectionReachability();
            loadOpticalTrends();
        }, 30000);
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
