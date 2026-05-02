/**
 * devices.js
 * Main JavaScript for devices page
 * Global state variables are defined in devices-state.js
 */

function loadSavedDeviceViews() {
    try {
        savedDeviceViews = JSON.parse(localStorage.getItem('netking_saved_device_views') || '[]');
        if (!Array.isArray(savedDeviceViews)) savedDeviceViews = [];
    } catch (error) {
        savedDeviceViews = [];
    }
}

function persistSavedDeviceViews() {
    localStorage.setItem('netking_saved_device_views', JSON.stringify(savedDeviceViews.slice(0, 8)));
}

function renderSavedDeviceViews() {
    const container = document.getElementById('saved-device-views');
    if (!container) return;
    if (!savedDeviceViews.length) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = savedDeviceViews.map((view, index) => `
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-dark" onclick="applySavedDeviceView(${index})">${view.name}</button>
            <button class="btn btn-outline-secondary" onclick="deleteSavedDeviceView(${index})" title="Hapus view">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `).join('');
}

function updateQuickFilterButtons() {
    document.querySelectorAll('#sticky-filter-bar button[onclick^="applyQuickFilter"]').forEach((button) => {
        const match = button.getAttribute('onclick')?.match(/'([^']+)'/);
        const value = match ? match[1] : 'all';
        button.classList.toggle('active', value === currentQuickFilter);
    });
}

function applyQuickFilter(filter) {
    currentQuickFilter = filter || 'all';
    currentPage = 1;
    updateQuickFilterButtons();
    filterDevices();
}

function saveCurrentDeviceView() {
    const searchTerm = document.getElementById('search-input')?.value.trim() || '';
    const defaultName = searchTerm || currentQuickFilter || currentFilterType || 'View';
    const name = prompt('Nama view untuk disimpan?', defaultName);
    if (!name) return;

    savedDeviceViews.unshift({
        name: name.trim(),
        type: currentFilterType,
        quickFilter: currentQuickFilter,
        search: searchTerm,
        itemsPerPage: itemsPerPage
    });

    savedDeviceViews = savedDeviceViews.filter((view, index, array) =>
        index === array.findIndex((candidate) =>
            candidate.name === view.name &&
            candidate.type === view.type &&
            candidate.quickFilter === view.quickFilter &&
            candidate.search === view.search &&
            candidate.itemsPerPage === view.itemsPerPage
        )
    ).slice(0, 8);

    persistSavedDeviceViews();
    renderSavedDeviceViews();
    showToast(`View "${name}" disimpan`, 'success');
}

function applySavedDeviceView(index) {
    const view = savedDeviceViews[index];
    if (!view) return;

    currentFilterType = view.type || 'onu';
    currentQuickFilter = view.quickFilter || 'all';
    itemsPerPage = Number(view.itemsPerPage || 20);
    currentPage = 1;

    document.getElementById('items-per-page').value = String(itemsPerPage);
    document.getElementById('search-input').value = view.search || '';

    const tabButton = document.getElementById(`${currentFilterType}-tab`);
    if (tabButton) {
        bootstrap.Tab.getOrCreateInstance(tabButton).show();
    }

    generateTableHeader(currentFilterType);
    updateSearchPlaceholder(currentFilterType);
    updateQuickFilterButtons();

    if (currentFilterType === 'onu') {
        filterDevices();
        updateDeviceStats(allDevices, true);
    } else {
        renderMapItems(currentFilterType);
        updateDeviceStats([], false);
    }
}

function deleteSavedDeviceView(index) {
    const view = savedDeviceViews[index];
    if (!view) return;
    savedDeviceViews.splice(index, 1);
    persistSavedDeviceViews();
    renderSavedDeviceViews();
    showToast(`View "${view.name}" dihapus`, 'info');
}

function applyDeviceSearchAndQuickFilter(devices) {
    const searchTerm = document.getElementById('search-input')?.value.toLowerCase().trim() || '';
    let filtered = [...devices];

    if (searchTerm !== '') {
        filtered = filtered.filter(device => {
            const serialNumber = (device.serial_number || '').toLowerCase();
            const macAddress = (device.mac_address || '').toLowerCase();
            const customerName = (device.customer_name || device.ont_name || device.pppoe_username || device.wifi_ssid || '').toLowerCase();
            const oltName = (device.olt_name || '').toLowerCase();
            let tagsMatch = false;
            if (device.tags && Array.isArray(device.tags) && device.tags.length > 0) {
                tagsMatch = device.tags.some(tag => tag.toLowerCase().includes(searchTerm));
            }

            return serialNumber.includes(searchTerm) || macAddress.includes(searchTerm) || customerName.includes(searchTerm) || oltName.includes(searchTerm) || tagsMatch;
        });
    }

    if (currentQuickFilter === 'online') {
        filtered = filtered.filter(device => device.status === 'online');
    } else if (currentQuickFilter === 'offline') {
        filtered = filtered.filter(device => device.status !== 'online');
    } else if (currentQuickFilter === 'rx_critical') {
        filtered = filtered.filter(device => {
            const rx = parseFloat(device.rx_power);
            return !Number.isNaN(rx) && rx <= -28;
        });
    } else if (currentQuickFilter === 'rx_warning') {
        filtered = filtered.filter(device => {
            const rx = parseFloat(device.rx_power);
            return !Number.isNaN(rx) && rx > -28 && rx <= -25;
        });
    } else if (currentQuickFilter === 'high_clients') {
        filtered = filtered.filter(device => Number(device.connected_devices_count || 0) >= 5);
    }

    return filtered;
}


async function loadDevices(isAutoRefresh = false) {
    // Save scroll position before refresh (for auto-refresh)
    if (isAutoRefresh) {
        savedScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    }

    const tbody = document.getElementById('devices-tbody');

    // Don't show spinner on auto-refresh to avoid flickering
    if (!isAutoRefresh) {
        tbody.innerHTML = '<tr><td colspan="12" class="text-center"><div class="spinner"></div><div style="margin-top: 10px;">Loading devices...</div></td></tr>';
    }

    // Progressive loading: Load devices in chunks
    allDevices = [];
    let skip = 0;
    const chunkSize = 100;
    let hasMore = true;

    try {
        // Load first chunk
        const [firstChunk, oltSummaryResult] = await Promise.all([
            fetchAPI(`/api/get-devices.php?limit=${chunkSize}&skip=${skip}`),
            fetchAPI('/api/olt-inventory-summary.php')
        ]);

        if (!firstChunk || !firstChunk.success) {
            throw new Error(firstChunk?.message || 'Failed to load devices');
        }

        allDevices = firstChunk.devices || [];
        allMapItems = [];
        oltInventorySummary = (oltSummaryResult && oltSummaryResult.success && oltSummaryResult.summary)
            ? oltSummaryResult.summary
            : {};
        hasMore = firstChunk.hasMore;
        skip += chunkSize;

        // Update UI with first chunk immediately
        if (!isAutoRefresh) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center"><div class="spinner"></div><div style="margin-top: 10px;">Loading devices... (' + allDevices.length + ' loaded)</div></td></tr>';
        }

        // Load remaining chunks
        while (hasMore) {
            const chunk = await fetchAPI(`/api/get-devices.php?limit=${chunkSize}&skip=${skip}`);

            if (!chunk || !chunk.success) break;

            allDevices = allDevices.concat(chunk.devices || []);
            hasMore = chunk.hasMore;
            skip += chunkSize;

            // Update loading indicator
            if (!isAutoRefresh) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center"><div class="spinner"></div><div style="margin-top: 10px;">Loading devices... (' + allDevices.length + ' loaded)</div></td></tr>';
            }
        }

        // Process loaded devices
        if (allDevices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center">No devices found</td></tr>';
            updateDeviceCount(0, 0);
            return;
        }

        // Only reset sort state on manual refresh, maintain on auto-refresh
        if (!isAutoRefresh) {
            currentSortColumn = null;
            currentSortDirection = 'asc';
            resetSortIcons();

            // Generate initial table header for "ONU" tab (default)
            generateTableHeader('onu');

            // Update search placeholder for ONU tab (default)
            updateSearchPlaceholder('onu');
        }

        // Re-apply current filter and sorting (ONU only)
        let devicesToRender = applyDeviceSearchAndQuickFilter(allDevices);
        if (currentSortColumn) {
            devicesToRender = applySorting(devicesToRender, currentSortColumn, currentSortDirection);
        }
        renderDevices(devicesToRender);
        updateDeviceCount(devicesToRender.length, allDevices.length);
        updateDeviceStats(allDevices);
        updateDeviceTypeCountsFromMap(allDevices, {});

        // Restore scroll position and sort icons after auto-refresh
        if (isAutoRefresh) {
            if (savedScrollPosition > 0) {
                setTimeout(() => {
                    window.scrollTo(0, savedScrollPosition);
                }, 100); // Small delay to ensure DOM is updated
            }

            // Restore sort icons if sorting is active
            if (currentSortColumn) {
                setTimeout(() => {
                    updateSortIcons(currentSortColumn, currentSortDirection);
                }, 50);
            }
        }
    } catch (error) {
        console.error('Error loading devices:', error);
        tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger">Failed to load devices: ' + error.message + '</td></tr>';
        updateDeviceCount(0, 0);
        updateDeviceStats([]);
        updateDeviceTypeCountsFromMap([], {}); // Reset counts
    }
}

async function renderDevices(devices) {
    const tbody = document.getElementById('devices-tbody');
    tbody.innerHTML = '';

    // Determine appropriate colspan based on current filter type
    const colspan = (currentFilterType === 'onu') ? 12 : 6;

    if (devices.length === 0) {
        // If showing infrastructure items, show map items instead
        if (currentFilterType !== 'onu') {
            renderMapItems(currentFilterType);
            return;
        }
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">No devices found</td></tr>`;
        updatePaginationUI(0);
        return;
    }

    // Store total for pagination
    totalDevices = devices.length;

    // Apply pagination (slice devices array)
    let devicesToRender = devices;
    if (itemsPerPage > 0) {
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        devicesToRender = devices.slice(startIndex, endIndex);

    }

    // Update pagination UI
    updatePaginationUI(totalDevices);

    devicesToRender.forEach(device => {
        const row = document.createElement('tr');
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
        let statusDisplay;
        if (device.status === 'online') {
            const ping = device.ping || '-';
            statusDisplay = `<span class="badge online">ON [${ping}ms]</span>`;
        } else {
            statusDisplay = `<span class="badge offline">OFF [-]</span>`;
        }

        // Tags display - show as badges
        let tagsDisplay = '';
        let tagsSortValue = '';
        if (device.tags && Array.isArray(device.tags) && device.tags.length > 0) {
            tagsDisplay = device.tags.map(tag => `<span class="badge bg-info me-1">${tag}</span>`).join('');
            tagsSortValue = device.tags.join(', '); // For sorting: join tags as string
        } else {
            tagsDisplay = '<span class="text-muted">-</span>';
            tagsSortValue = ''; // Empty for sorting (will be sorted to bottom)
        }

        // Check tags column visibility state for consistent display
        const tagsColumnDisplay = tagsColumnVisible ? '' : 'none';

        row.innerHTML = `
            <td>
                <input type="checkbox" class="device-checkbox" value="${encodeURIComponent(device.device_id)}" onchange="updateBulkActionButtons()">
            </td>
            <td>
                <a href="#" onclick="openDeviceInspector('${encodeURIComponent(device.device_id)}'); return false;">${device.serial_number}</a>
                <br><small class="text-muted">${device.customer_name || device.ont_name || device.pppoe_username || device.wifi_ssid || '-'}</small>
            </td>
            <td>${device.mac_address}</td>
            <td data-sort-value="${device.product_class || ''}">${device.product_class || 'N/A'}</td>
            <td data-sort-value="${ipAddress}">${ipDisplay}</td>
            <td data-sort-value="${device.wifi_ssid}">${device.wifi_ssid}</td>
            <td data-sort-value="${device.pppoe_username || ''}">${device.pppoe_username || 'N/A'}</td>
            <td data-sort-value="${parseFloat(device.rx_power) || -999}">${rxDisplay}</td>
            <td data-sort-value="${parseFloat(device.temperature) || -999}">${device.temperature}°C</td>
            <td data-sort-value="${clientsCount}" class="text-center">${clientsBadge}</td>
            <td data-sort-value="${device.status}">${statusDisplay}</td>
            <td class="tags-column" data-sort-value="${tagsSortValue}" style="display: ${tagsColumnDisplay};">${tagsDisplay}</td>
            <td>
                <button class="btn btn-sm btn-outline-dark me-1" onclick="openDeviceInspector('${encodeURIComponent(device.device_id)}')" title="Inspect Device">
                    <i class="bi bi-layout-sidebar-inset-reverse"></i>
                </button>
                <button class="btn btn-sm btn-primary" onclick="summonDeviceQuick('${device.device_id}')" title="Summon Device">
                    <i class="bi bi-lightning-charge"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function devicesEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function inspectorValue(value, fallback = 'N/A') {
    const text = String(value ?? '').trim();
    if (text === '' || text.toUpperCase() === 'N/A' || text.toLowerCase() === 'null') {
        return fallback;
    }
    return text;
}

function formatInspectorAgeSeconds(value) {
    const seconds = Number(value);
    if (!Number.isFinite(seconds) || seconds < 0) {
        return inspectorValue(value);
    }
    if (seconds < 60) return `${seconds} dtk`;
    if (seconds < 3600) return `${Math.round(seconds / 60)} mnt`;
    if (seconds < 86400) return `${Math.round(seconds / 3600)} jam`;
    return `${Math.round(seconds / 86400)} hari`;
}

function inspectorTimelineBadgeClass(severity) {
    const value = String(severity || '').toLowerCase();
    if (value === 'critical') return 'bg-danger';
    if (value === 'warning') return 'bg-warning text-dark';
    if (value === 'success') return 'bg-success';
    return 'bg-secondary';
}

function inspectorEmptyStateCard(title, description, actions = []) {
    const actionsHtml = actions.map(action => `
        <a class="btn btn-sm ${action.className || 'btn-outline-dark'}" href="${action.href || '#'}"${action.targetBlank ? ' target="_blank"' : ''}>
            ${action.icon ? `<i class="bi bi-${action.icon}"></i> ` : ''}${devicesEscapeHtml(action.label || 'Buka')}
        </a>
    `).join('');

    return `
        <div class="border rounded p-3 bg-light-subtle">
            <div class="fw-semibold mb-1">${devicesEscapeHtml(title)}</div>
            <div class="text-muted small mb-3">${devicesEscapeHtml(description)}</div>
            ${actionsHtml ? `<div class="d-flex flex-wrap gap-2">${actionsHtml}</div>` : ''}
        </div>
    `;
}

async function loadDeviceInspectorTimeline(device) {
    const container = document.getElementById('device-inspector-timeline');
    if (!container) return;

    container.innerHTML = '<div class="text-muted small">Memuat timeline...</div>';

    try {
        const serial = inspectorValue(device.serial_number, '');
        const deviceId = inspectorValue(device.device_id, '');
        const result = await fetchAPI(`/api/device-timeline.php?device_id=${encodeURIComponent(deviceId)}&serial=${encodeURIComponent(serial)}&limit=10`, { timeout: 12000 });

        if (!result || !result.success) {
            container.innerHTML = inspectorEmptyStateCard(
                'Timeline belum tersedia',
                'Riwayat event perangkat belum bisa dimuat saat ini.',
                [{ label: 'Buka Alarm Center', href: `/alarm-events.php?q=${encodeURIComponent(serial)}`, icon: 'clock-history', className: 'btn-outline-secondary' }]
            );
            return;
        }

        const items = result.items || [];
        if (!items.length) {
            container.innerHTML = inspectorEmptyStateCard(
                'Belum ada event timeline',
                'Belum ada alarm atau perubahan status yang terekam untuk ONT ini.',
                [
                    { label: 'Alarm Center', href: `/alarm-events.php?q=${encodeURIComponent(serial)}`, icon: 'bell', className: 'btn-outline-secondary' }
                ]
            );
            return;
        }

        container.innerHTML = items.map(item => {
            const sourceLabel = item.source === 'alarm' ? 'Alarm' : 'Monitor';
            const meta = item.meta || {};
            const metaParts = [];
            if (meta.status) metaParts.push(`Status ${meta.status}`);
            if (meta.olt_name) metaParts.push(meta.olt_name);
            if (meta.event_type) metaParts.push(meta.event_type);
            return `
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <div>
                            <div class="fw-semibold">${devicesEscapeHtml(item.title || 'Event')}</div>
                            <div class="text-muted small">${devicesEscapeHtml(item.created_at || 'N/A')}</div>
                        </div>
                        <span class="badge ${inspectorTimelineBadgeClass(item.severity)}">${devicesEscapeHtml(sourceLabel)}</span>
                    </div>
                    <div class="small mb-1">${devicesEscapeHtml(item.message || 'Tidak ada detail tambahan.')}</div>
                    ${metaParts.length ? `<div class="text-muted small">${devicesEscapeHtml(metaParts.join(' • '))}</div>` : ''}
                </div>
            `;
        }).join('');
    } catch (error) {
        container.innerHTML = inspectorEmptyStateCard(
            'Timeline gagal dimuat',
            error.message || 'Terjadi kesalahan saat memuat timeline.',
            [{ label: 'Buka Detail', href: `/device-detail.php?id=${encodeURIComponent(device.device_id || '')}`, icon: 'box-arrow-up-right', className: 'btn-outline-dark' }]
        );
    }
}

function inspectorBadgeStack(device) {
    const status = inspectorValue(device.status, 'unknown').toLowerCase();
    const rx = parseFloat(device.rx_power);
    let rxClass = 'secondary';
    let rxText = inspectorValue(device.rx_power);
    if (!Number.isNaN(rx)) {
        rxClass = rx <= -28 ? 'danger' : (rx <= -25 ? 'warning text-dark' : 'success');
        rxText = `${rx.toFixed(2)} dBm`;
    }

    const informAge = formatInspectorAgeSeconds(device.last_inform_age_sec);
    const cr = inspectorValue(device.connection_request_reachable, 'unknown');
    const clients = Number(device.connected_devices_count || 0);
    const provisionVersion = inspectorValue(device.provision_version, 'N/A');
    const provisionResult = inspectorValue(device.last_provision_result, 'N/A');
    const provisionBadge = provisionResult.toLowerCase().includes('success')
        ? 'bg-success'
        : (provisionResult.toLowerCase().includes('fail') || provisionResult.toLowerCase().includes('error')
            ? 'bg-danger'
            : 'bg-light text-dark border');
    return `
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge ${status === 'online' ? 'bg-success' : 'bg-danger'}">${devicesEscapeHtml(status.toUpperCase())}</span>
            <span class="badge bg-${rxClass}">RX ${devicesEscapeHtml(rxText)}</span>
            <span class="badge bg-light text-dark border">Inform ${devicesEscapeHtml(informAge)}</span>
            <span class="badge bg-light text-dark border">CR ${devicesEscapeHtml(cr)}</span>
            <span class="badge bg-light text-dark border">Client ${devicesEscapeHtml(String(clients))}</span>
            <span class="badge bg-light text-dark border">Provision ${devicesEscapeHtml(provisionVersion)}</span>
            <span class="badge ${provisionBadge}">Result ${devicesEscapeHtml(provisionResult)}</span>
        </div>
    `;
}

async function openDeviceInspector(encodedDeviceId) {
    const deviceId = decodeURIComponent(encodedDeviceId);
    const drawer = document.getElementById('deviceInspectorDrawer');
    const content = document.getElementById('device-inspector-content');
    if (!drawer || !content) {
        window.location.href = `/device-detail.php?id=${encodeURIComponent(deviceId)}`;
        return;
    }

    content.innerHTML = '<div class="text-muted">Memuat detail perangkat...</div>';
    bootstrap.Offcanvas.getOrCreateInstance(drawer).show();

    try {
        const result = await fetchAPI(`/api/get-device-detail.php?device_id=${encodeURIComponent(deviceId)}`, {timeout: 12000});
        if (!result || !result.success) {
            throw new Error(result?.message || 'Gagal memuat detail perangkat');
        }

        const device = result.device || {};
        const safeDeviceId = devicesEscapeHtml(device.device_id || deviceId);
        const safeSerial = devicesEscapeHtml(inspectorValue(device.serial_number));
        const customer = devicesEscapeHtml(inspectorValue(device.customer_name, inspectorValue(device.ont_name, inspectorValue(device.pppoe_username, '-'))));
        const olt = devicesEscapeHtml(inspectorValue(device.olt_name, inspectorValue(device.olt_area, '-')));
        const ponOnt = `${devicesEscapeHtml(inspectorValue(device.pon_port, '-'))} / ${devicesEscapeHtml(inspectorValue(device.ont_index, '-'))}`;
        const detailUrl = `/device-detail.php?id=${encodeURIComponent(device.device_id || deviceId)}`;

        content.innerHTML = `
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <h6 class="mb-1">${safeSerial}</h6>
                        <div class="text-muted small">${customer}</div>
                    </div>
                    <a class="btn btn-sm btn-outline-dark" href="${detailUrl}">
                        Detail
                    </a>
                </div>
            </div>

            ${inspectorBadgeStack(device)}

            <div class="border rounded p-3 mb-3">
                <div class="fw-semibold mb-2">ACS / OLT</div>
                <dl class="row small mb-0">
                    <dt class="col-4">Device</dt><dd class="col-8"><code>${safeDeviceId}</code></dd>
                    <dt class="col-4">OLT</dt><dd class="col-8">${olt}</dd>
                    <dt class="col-4">PON/ONT</dt><dd class="col-8">${ponOnt}</dd>
                    <dt class="col-4">IP TR069</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.ip_tr069))}</dd>
                    <dt class="col-4">Last Inform</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.last_inform))}</dd>
                </dl>
            </div>

            <div class="border rounded p-3 mb-3">
                <div class="fw-semibold mb-2">Optical</div>
                <dl class="row small mb-0">
                    <dt class="col-4">RX</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.rx_power))} dBm</dd>
                    <dt class="col-4">TX</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.optical_tx_power))} dBm</dd>
                    <dt class="col-4">Temp</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.temperature))} °C</dd>
                    <dt class="col-4">Voltage</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.optical_voltage))} V</dd>
                    <dt class="col-4">Bias</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.optical_bias_current))} mA</dd>
                </dl>
            </div>

            <div class="border rounded p-3 mb-3">
                <div class="fw-semibold mb-2">WiFi / WAN</div>
                <dl class="row small mb-0">
                    <dt class="col-4">SSID</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.wifi_ssid))}</dd>
                    <dt class="col-4">PPPoE</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.pppoe_username))}</dd>
                    <dt class="col-4">Client</dt><dd class="col-8">${devicesEscapeHtml(inspectorValue(device.connected_devices_count, '0'))}</dd>
                </dl>
            </div>

            <div class="border rounded p-3 mb-3">
                <div class="fw-semibold mb-2">Timeline</div>
                <div id="device-inspector-timeline">
                    <div class="text-muted small">Memuat timeline...</div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button class="btn btn-sm btn-primary" onclick="summonDeviceQuick('${devicesEscapeHtml(device.device_id || deviceId)}')">
                    <i class="bi bi-lightning-charge"></i> Panggil Read-Only
                </button>
                <a class="btn btn-sm btn-outline-secondary" href="/alarm-events.php?q=${encodeURIComponent(device.serial_number || '')}">
                    <i class="bi bi-clock-history"></i> Timeline Alarm
                </a>
                <button class="btn btn-sm btn-outline-secondary" disabled title="Safe Mode ON">
                    <i class="bi bi-shield-lock"></i> Write Actions Locked
                </button>
            </div>
        `;

        loadDeviceInspectorTimeline(device);
    } catch (error) {
        content.innerHTML = `
            <div class="alert alert-danger">
                ${devicesEscapeHtml(error.message)}
            </div>
            <a class="btn btn-sm btn-outline-dark" href="/device-detail.php?id=${encodeURIComponent(deviceId)}">Buka Detail</a>
        `;
    }
}

// Render map items (for infrastructure: Server, OLT, ODC, ODP)
function renderMapItems(itemType) {
    const items = getInfrastructureItems(itemType);
    renderInfrastructureItems(items, itemType);
}

function getInfrastructureItems(itemType) {
    if (itemType === 'olt') {
        const actualOlts = allMapItems.filter(item => item.item_type === 'olt');

        if (actualOlts.length > 0) {
            return actualOlts.map(item => {
                const parentServer = allMapItems.find(parent => parent.id == item.parent_id && parent.item_type === 'server');
                return {
                    ...item,
                    server_name: parentServer?.name || null,
                    olt_link: item.config?.olt_link || item.properties?.olt_link || '',
                    protocol: item.properties?.protocol || 'telnet',
                    model: item.properties?.model || 'N/A',
                    site: item.properties?.site || 'N/A',
                    pon_count: item.config?.pon_count || 0
                };
            });
        }

        // Legacy fallback: some older data stored OLT references on server properties.
        const legacyItems = [];
        allMapItems.forEach(item => {
            if (item.item_type === 'server' && item.properties && item.properties.olt_link) {
                legacyItems.push({
                    id: item.id,
                    name: item.properties.olt_link || 'OLT',
                    item_type: 'olt',
                    latitude: item.latitude,
                    longitude: item.longitude,
                    status: item.status,
                    server_name: item.name,
                    olt_link: item.properties.olt_link || '',
                    protocol: 'netwatch',
                    model: 'Legacy linked OLT',
                    site: item.name || 'N/A',
                    pon_count: 0
                });
            }
        });
        return legacyItems;
    }

    return allMapItems.filter(item => item.item_type === itemType);
}

function renderInfrastructureItems(items, itemType) {
    const tbody = document.getElementById('devices-tbody');
    tbody.innerHTML = '';

    const colspan = itemType === 'olt' ? 11 : 6;

    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">No items found</td></tr>`;
        updateDeviceCount(0, 0);
        return;
    }

    items.forEach(item => {
        const row = document.createElement('tr');
        const status = item.status || 'unknown';
        let statusBadge = '';
        if (status === 'online') {
            statusBadge = '<span class="badge online">Online</span>';
        } else if (status === 'offline') {
            statusBadge = '<span class="badge offline">Offline</span>';
        } else {
            statusBadge = '<span class="badge bg-secondary">Unknown</span>';
        }

        const focusType = item.item_type || itemType;
        const focusId = item.id;

        if (itemType === 'olt') {
            const displayName = item.server_name ? `${item.name} <br><small class="text-muted">${item.server_name}</small>` : item.name;
            const model = item.model || 'N/A';
            const site = item.site || 'N/A';
            const ipAddress = item.olt_link || 'N/A';
            const protocol = item.protocol || 'N/A';
            const ponCount = item.pon_count || 0;
            const summary = oltInventorySummary[String(item.id)] || oltInventorySummary[item.id] || {};
            const inventoryTotal = summary.inventory_total || 0;
            const missingTotal = summary.missing_total || 0;
            const inAcsTotal = summary.in_acs_total || 0;
            const onlineTotal = summary.online_total || 0;
            const onlineMissingTotal = summary.online_missing_total || 0;
            const lastSyncedAt = summary.last_synced_at || 'Belum sync';
            const syncState = summary.sync_state || null;
            const lastError = summary.last_error || '';
            let displayStatusBadge = statusBadge;

            if ((item.status || 'unknown') === 'unknown') {
                if (syncState === 'success') {
                    displayStatusBadge = '<span class="badge bg-info text-dark">Telnet OK</span>';
                } else if (syncState === 'error') {
                    displayStatusBadge = '<span class="badge bg-warning text-dark">Gagal Sync</span>';
                } else {
                    displayStatusBadge = '<span class="badge bg-secondary">Belum Dicek</span>';
                }
            }

            row.innerHTML = `
                <td>${displayName}</td>
                <td>${model}</td>
                <td>${ipAddress}</td>
                <td><span class="badge bg-info text-dark">${protocol}</span></td>
                <td class="text-center"><span class="badge bg-primary">${ponCount}</span></td>
                <td class="text-center">
                    <span class="badge bg-dark">${inventoryTotal}</span>
                    <div><small class="text-muted">${onlineTotal} ONU online</small></div>
                </td>
                <td class="text-center">
                    <span class="badge bg-success" title="Sudah first-inform ke ACS">${inAcsTotal}</span>
                    <span class="badge bg-warning text-dark" title="Belum first-inform ke ACS">${missingTotal}</span>
                    <div><small class="text-danger">${onlineMissingTotal} online belum ACS</small></div>
                </td>
                <td><small>${lastSyncedAt}${lastError ? `<br><span class="text-danger">${lastError}</span>` : ''}</small></td>
                <td>${site}</td>
                <td>${displayStatusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-primary me-1" onclick="syncOltInventory(${focusId}, '${String(item.name).replace(/'/g, "\\'")}')" title="Sync ONU dari OLT">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <button class="btn btn-sm btn-info me-1" onclick="window.open('/olt-onu-inventory.php?olt_id=${focusId}', '_blank')" title="Lihat ONU hasil sync">
                        <i class="bi bi-list-ul"></i>
                    </button>
                    <button class="btn btn-sm btn-warning me-1" onclick="window.open('/olt-onu-inventory.php?olt_id=${focusId}&view_mode=online-missing', '_blank')" title="Lihat ONU online yang belum masuk ACS">
                        <i class="bi bi-broadcast-pin"></i>
                    </button>
                    <button class="btn btn-sm btn-success" onclick="window.open('/map.php?focus_type=${focusType}&focus_id=${focusId}', '_blank')" title="View on Map">
                        <i class="bi bi-map"></i>
                    </button>
                </td>
            `;
        } else {
            const lat = parseFloat(item.latitude).toFixed(6);
            const lng = parseFloat(item.longitude).toFixed(6);

            row.innerHTML = `
                <td>${item.name}</td>
                <td><span class="badge bg-primary">${itemType.toUpperCase()}</span></td>
                <td>${lat}</td>
                <td>${lng}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-success" onclick="window.open('/map.php?focus_type=${focusType}&focus_id=${focusId}', '_blank')" title="View on Map">
                        <i class="bi bi-map"></i>
                    </button>
                </td>
            `;
        }

        tbody.appendChild(row);
    });

    updateDeviceCount(items.length, items.length);
}

function updateDeviceCount(shown, total) {
    const countElement = document.getElementById('device-count');

    // If using pagination, show range
    if (itemsPerPage > 0 && total > itemsPerPage) {
        const startIndex = (currentPage - 1) * itemsPerPage + 1;
        const endIndex = Math.min(currentPage * itemsPerPage, total);
        countElement.textContent = `Showing ${startIndex}-${endIndex} of ${total} item${total !== 1 ? 's' : ''}`;
    } else if (shown === total) {
        countElement.textContent = `Showing ${total} item${total !== 1 ? 's' : ''}`;
    } else {
        countElement.textContent = `Showing ${shown} of ${total} item${total !== 1 ? 's' : ''}`;
    }
}

function updateDeviceStats(devices, showStats = true) {
    const statsContainer = document.getElementById('device-stats-badges');

    if (!showStats) {
        statsContainer.innerHTML = '';
        return;
    }

    const total = devices.length;
    const online = devices.filter(d => d.status === 'online').length;
    const offline = total - online;

    statsContainer.innerHTML = `
        <span class="badge bg-secondary">Total [${total}]</span>
        <span class="badge bg-success">Online [${online}]</span>
        <span class="badge bg-danger">Offline [${offline}]</span>
    `;
}

// Update device type counts in tab badges using map data
function updateDeviceTypeCountsFromMap(devices, mapCounts) {
    // Count ALL devices from GenieACS (no filtering by product_class)
    const onuCount = devices.length;

    const counts = {
        onu: onuCount, // From all devices in GenieACS
        odp: mapCounts.odp || 0, // From map
        odc: mapCounts.odc || 0, // From map
        olt: mapCounts.olt || 0, // From map
        server: mapCounts.server || 0 // From map
    };

    // Update badges
    const countOnu = document.getElementById('count-onu');
    const countOdp = document.getElementById('count-odp');
    const countOdc = document.getElementById('count-odc');
    const countOlt = document.getElementById('count-olt');
    const countServer = document.getElementById('count-server');

    if (countOnu) countOnu.textContent = counts.onu;
    if (countOdp) countOdp.textContent = counts.odp;
    if (countOdc) countOdc.textContent = counts.odc;
    if (countOlt) countOlt.textContent = counts.olt;
    if (countServer) countServer.textContent = counts.server;
}

// Generate table header based on device type
function generateTableHeader(type) {
    const tableHeader = document.getElementById('table-header');

    if (type === 'onu') {
        // Check current tags column visibility state
        const tagsDisplay = tagsColumnVisible ? '' : 'none';

        // ONU devices table header (GenieACS devices)
        tableHeader.innerHTML = `
            <tr>
                <th style="width: 40px;">
                    <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll()" title="Select All">
                </th>
                <th>SN</th>
                <th>MAC</th>
                <th class="sortable" onclick="sortTable('product_class')" style="cursor: pointer;">
                    Tipe <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="sortable" onclick="sortTable('ip')" style="cursor: pointer;">
                    IP <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="sortable" onclick="sortTable('ssid')" style="cursor: pointer;">
                    SSID <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="sortable" onclick="sortTable('pppoe_username')" style="cursor: pointer;">
                    PPPoE <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="sortable" onclick="sortTable('rx_power')" style="cursor: pointer;">
                    Rx <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="sortable" onclick="sortTable('temperature')" style="cursor: pointer;">
                    Temp <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="sortable" onclick="sortTable('connected_clients')" style="cursor: pointer;">
                    Client <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="sortable" onclick="sortTable('status')" style="cursor: pointer;">
                    Status <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th class="tags-column sortable" onclick="sortTable('tags')" style="cursor: pointer; display: ${tagsDisplay};">
                    Tags <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
                <th>Action</th>
            </tr>
        `;
    } else if (type === 'olt') {
        tableHeader.innerHTML = `
            <tr>
                <th>Name</th>
                <th>Model</th>
                <th>IP OLT</th>
                <th>Protocol</th>
                <th>PON</th>
                <th>Inventory ONU</th>
                <th>Masuk / Belum ACS</th>
                <th>Sinkron Terakhir</th>
                <th>Site</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        `;
    } else {
        // Infrastructure items table header (Map items: Server, OLT, ODC, ODP)
        tableHeader.innerHTML = `
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        `;
    }
}

async function refreshOltInventorySummary() {
    const result = await fetchAPI('/api/olt-inventory-summary.php', { timeout: 30000 });
    if (result && result.success && result.summary) {
        oltInventorySummary = result.summary;
    }
}

async function syncOltInventory(itemId, itemName) {
    if (!confirmAction(`Sync ONU dari ${itemName} sekarang?`)) {
        return;
    }

    showLoading();
    const result = await fetchAPI('/api/olt-sync-now.php', {
        method: 'POST',
        body: JSON.stringify({ item_id: itemId }),
        timeout: 120000
    });
    hideLoading();

    if (!result || !result.success) {
        showToast(result?.message || `Gagal sync OLT ${itemName}`, 'danger', 5000);
        return;
    }

    const syncResult = Array.isArray(result.results) ? result.results[0] : result;
    const total = syncResult?.total ?? 0;
    const created = syncResult?.created ?? 0;
    const updated = syncResult?.updated ?? 0;
    showToast(`Sync ${itemName} selesai. ONU ${total}, baru ${created}, update ${updated}.`, 'success', 5000);

    await refreshOltInventorySummary();
    if (currentFilterType === 'olt') {
        renderMapItems('olt');
    }
}

// Filter devices by type using map data
function filterByType(type) {
    currentFilterType = type;

    // Generate appropriate table header
    generateTableHeader(type);

    // Clear search box when switching tabs
    document.getElementById('search-input').value = '';
    currentQuickFilter = 'all';

    // Update search placeholder based on tab
    updateSearchPlaceholder(type);
    updateQuickFilterButtons();

    // Reset sort state
    currentSortColumn = null;
    currentSortDirection = 'asc';
    resetSortIcons();

    if (type === 'onu') {
        // For ONU: show ALL devices from GenieACS (no filtering by product_class)
        renderDevices(allDevices);
        updateDeviceCount(allDevices.length, allDevices.length);
        // Show stats for ONU tab
        updateDeviceStats(allDevices, true);
    } else {
        // For ODP, ODC, OLT, Server: show map items
        renderMapItems(type);
        // Hide stats for infrastructure tabs
        updateDeviceStats([], false);
    }
}

function extractIP(ipString) {
    if (!ipString || ipString === 'N/A') return 'N/A';
    const match = ipString.match(/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/);
    return match ? match[1] : 'N/A';
}

// Update search placeholder based on current tab
function updateSearchPlaceholder(type) {
    const searchInput = document.getElementById('search-input');
    if (type === 'onu') {
        searchInput.placeholder = 'Search by Serial Number, MAC Address, or Tags...';
    } else if (type === 'olt') {
        searchInput.placeholder = 'Search by OLT name, IP, model, site, or protocol...';
    } else {
        searchInput.placeholder = 'Search by Name...';
    }
}

// Search functionality
function filterDevices() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase().trim();

    // Reset to page 1 when search term changes
    currentPage = 1;

    // Get devices based on current tab
    let baseDevices = allDevices;
    if (currentFilterType === 'onu') {
        baseDevices = applyDeviceSearchAndQuickFilter(allDevices);
    } else {
        // For ODP, ODC, OLT, Server: use map data
        const mapItemDeviceIds = new Set();

        allMapItems.forEach(item => {
            if (item.item_type === currentFilterType) {
                // For Server, match by mikrotik_device_id in properties
                if (currentFilterType === 'server' && item.properties && item.properties.mikrotik_device_id) {
                    mapItemDeviceIds.add(item.properties.mikrotik_device_id);
                }
            }
        });

        // Filter devices that match map items
        baseDevices = allDevices.filter(device => {
            return mapItemDeviceIds.has(device.device_id);
        });
    }

    if (currentFilterType === 'onu' && searchTerm === '' && currentQuickFilter === 'all') {
        if (currentFilterType === 'onu') {
            renderDevices(baseDevices);
            updateDeviceCount(baseDevices.length, allDevices.length);
            updateDeviceStats(baseDevices, true);
        } else {
            renderMapItems(currentFilterType);
            updateDeviceStats([], false);
        }
        return;
    }

    // Different search logic based on tab type
    if (currentFilterType === 'onu') {
        const filteredDevices = baseDevices;

        // Debug: Log search results
        console.log(`[SEARCH] Found ${filteredDevices.length} device(s) matching "${searchTerm}" with quick filter "${currentQuickFilter}"`);

        renderDevices(filteredDevices);
        updateDeviceCount(filteredDevices.length, allDevices.length);
        updateDeviceStats(filteredDevices, true);
    } else {
        let items = getInfrastructureItems(currentFilterType);

        if (currentFilterType === 'olt') {
            items = items.filter(item => {
                const summary = oltInventorySummary[String(item.id)] || oltInventorySummary[item.id] || {};
                const haystack = [
                    item.name,
                    item.olt_link,
                    item.model,
                    item.site,
                    item.protocol,
                    item.server_name,
                    summary.inventory_total,
                    summary.in_acs_total,
                    summary.missing_total,
                    summary.last_synced_at
                ].join(' ').toLowerCase();
                return haystack.includes(searchTerm);
            });
        } else {
            items = allMapItems.filter(item => {
                if (item.item_type !== currentFilterType) return false;
                const itemName = (item.name || '').toLowerCase();
                return itemName.includes(searchTerm);
            });
        }
        renderInfrastructureItems(items, currentFilterType);
    }
}

function clearSearch() {
    document.getElementById('search-input').value = '';
    currentQuickFilter = 'all';
    updateQuickFilterButtons();
    filterDevices();
}

// Apply sorting to devices array (helper function for auto-refresh)
function applySorting(devices, column, direction) {
    const sortedDevices = [...devices];

    sortedDevices.sort((a, b) => {
        let valueA, valueB;

        switch (column) {
            case 'product_class':
                valueA = (a.product_class || '').toLowerCase();
                valueB = (b.product_class || '').toLowerCase();
                break;
            case 'ip':
                valueA = extractIP(a.ip_tr069);
                valueB = extractIP(b.ip_tr069);
                // Convert IP to comparable format
                valueA = valueA === 'N/A' ? '' : valueA.split('.').map(n => n.padStart(3, '0')).join('.');
                valueB = valueB === 'N/A' ? '' : valueB.split('.').map(n => n.padStart(3, '0')).join('.');
                break;
            case 'ssid':
                valueA = (a.wifi_ssid || '').toLowerCase();
                valueB = (b.wifi_ssid || '').toLowerCase();
                break;
            case 'pppoe_username':
                valueA = (a.pppoe_username || '').toLowerCase();
                valueB = (b.pppoe_username || '').toLowerCase();
                break;
            case 'rx_power':
                valueA = parseFloat(a.rx_power) || -999;
                valueB = parseFloat(b.rx_power) || -999;
                break;
            case 'temperature':
                valueA = parseFloat(a.temperature) || -999;
                valueB = parseFloat(b.temperature) || -999;
                break;
            case 'connected_clients':
                valueA = parseInt(a.connected_devices_count) || 0;
                valueB = parseInt(b.connected_devices_count) || 0;
                break;
            case 'status':
                valueA = a.status || '';
                valueB = b.status || '';
                break;
            case 'tags':
                // Sort by tags: join array to string, empty array goes to bottom
                valueA = (a.tags && Array.isArray(a.tags) && a.tags.length > 0) ? a.tags.join(', ').toLowerCase() : 'zzz'; // 'zzz' puts empty tags at bottom
                valueB = (b.tags && Array.isArray(b.tags) && b.tags.length > 0) ? b.tags.join(', ').toLowerCase() : 'zzz';
                break;
            default:
                return 0;
        }

        let comparison = 0;
        if (valueA > valueB) comparison = 1;
        if (valueA < valueB) comparison = -1;

        return direction === 'asc' ? comparison : -comparison;
    });

    return sortedDevices;
}

// Sorting functionality
function sortTable(column) {
    // Toggle sort direction if clicking same column
    if (currentSortColumn === column) {
        currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortColumn = column;
        currentSortDirection = 'asc';
    }

    // Get current filtered devices (in case search is active)
    const searchTerm = document.getElementById('search-input').value.toLowerCase().trim();
    let devicesToSort = searchTerm === '' ? [...allDevices] : allDevices.filter(device => {
        const serialNumber = (device.serial_number || '').toLowerCase();
        const macAddress = (device.mac_address || '').toLowerCase();
        const customerName = (device.customer_name || device.ont_name || '').toLowerCase();
        const oltName = (device.olt_name || '').toLowerCase();

        // Search in tags array
        let tagsMatch = false;
        if (device.tags && Array.isArray(device.tags) && device.tags.length > 0) {
            tagsMatch = device.tags.some(tag => tag.toLowerCase().includes(searchTerm));
        }

        return serialNumber.includes(searchTerm) || macAddress.includes(searchTerm) || customerName.includes(searchTerm) || oltName.includes(searchTerm) || tagsMatch;
    });

    // Use helper function to sort
    devicesToSort = applySorting(devicesToSort, column, currentSortDirection);

    renderDevices(devicesToSort);
    updateSortIcons(column, currentSortDirection);
}

function updateSortIcons(column, direction) {
    // Reset all sort icons
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'bi bi-chevron-expand sort-icon';
    });

    // Update active sort icon (indices shifted +1 due to checkbox column)
    const columnMap = {
        'product_class': 4,
        'ip': 5,
        'ssid': 6,
        'pppoe_username': 7,
        'rx_power': 8,
        'temperature': 9,
        'connected_clients': 10,
        'status': 11,
        'tags': 12
    };

    const columnIndex = columnMap[column];
    if (columnIndex) {
        const header = document.querySelector(`thead tr th:nth-child(${columnIndex}) .sort-icon`);
        if (header) {
            header.className = direction === 'asc' ? 'bi bi-chevron-up sort-icon' : 'bi bi-chevron-down sort-icon';
        }
    }
}

function resetSortIcons() {
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'bi bi-chevron-expand sort-icon';
    });
}

// currentSummonDeviceId defined in devices-state.js

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

// Pagination functions
function updatePaginationUI(total) {
    const paginationContainer = document.getElementById('pagination-container');
    const paginationInfo = document.getElementById('pagination-info');
    const paginationFirst = document.getElementById('pagination-first');
    const paginationPrev = document.getElementById('pagination-prev');
    const paginationNext = document.getElementById('pagination-next');
    const paginationLast = document.getElementById('pagination-last');

    // Hide pagination if showing all or no items
    if (itemsPerPage === 0 || total === 0) {
        paginationContainer.style.display = 'none';
        return;
    }

    const totalPages = Math.ceil(total / itemsPerPage);

    // Show pagination only if more than 1 page
    if (totalPages <= 1) {
        paginationContainer.style.display = 'none';
        return;
    }

    paginationContainer.style.display = 'block';

    // Update page info
    paginationInfo.textContent = `Page ${currentPage} of ${totalPages}`;

    // Update button states
    if (currentPage <= 1) {
        paginationFirst.classList.add('disabled');
        paginationPrev.classList.add('disabled');
    } else {
        paginationFirst.classList.remove('disabled');
        paginationPrev.classList.remove('disabled');
    }

    if (currentPage >= totalPages) {
        paginationNext.classList.add('disabled');
        paginationLast.classList.add('disabled');
    } else {
        paginationNext.classList.remove('disabled');
        paginationLast.classList.remove('disabled');
    }
}

function goToPage(page) {
    const totalPages = Math.ceil(totalDevices / itemsPerPage);

    if (page < 1 || page > totalPages) return;
    if (page === currentPage) return;

    currentPage = page;

    // Re-render devices with new page
    filterByType(currentFilterType);

    // Scroll to top of table
    document.getElementById('devices-table').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function changeItemsPerPage() {
    const selector = document.getElementById('items-per-page');
    itemsPerPage = parseInt(selector.value);

    // Reset to page 1 when changing items per page
    currentPage = 1;

    // Re-render devices
    filterByType(currentFilterType);
}

// Toggle Tags column visibility
function toggleTagsColumn() {
    tagsColumnVisible = !tagsColumnVisible;

    const tagColumns = document.querySelectorAll('.tags-column');
    const toggleBtn = document.getElementById('toggle-tags-btn');

    if (tagsColumnVisible) {
        // Show tags column
        tagColumns.forEach(col => {
            col.style.display = '';
        });
        toggleBtn.innerHTML = '<i class="bi bi-tags-fill"></i> Hide Tags';
        toggleBtn.classList.remove('btn-secondary');
        toggleBtn.classList.add('btn-primary');
    } else {
        // Hide tags column
        tagColumns.forEach(col => {
            col.style.display = 'none';
        });
        toggleBtn.innerHTML = '<i class="bi bi-tags"></i> Show Tags';
        toggleBtn.classList.remove('btn-primary');
        toggleBtn.classList.add('btn-secondary');
    }
}

// Bulk operations - Checkbox functions
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const deviceCheckboxes = document.querySelectorAll('.device-checkbox');

    deviceCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });

    updateBulkActionButtons();
}

function updateBulkActionButtons() {
    const selectedCheckboxes = document.querySelectorAll('.device-checkbox:checked');
    const bulkActionButtons = document.getElementById('bulk-action-buttons');
    const selectedCount = document.getElementById('selected-count');

    if (selectedCheckboxes.length > 0) {
        bulkActionButtons.style.display = 'inline-block';
        selectedCount.textContent = `${selectedCheckboxes.length} selected`;
    } else {
        bulkActionButtons.style.display = 'none';
    }

    // Update select-all checkbox state
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const allCheckboxes = document.querySelectorAll('.device-checkbox');
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        selectAllCheckbox.checked = selectedCheckboxes.length === allCheckboxes.length;
    }
}

function getSelectedDeviceIds() {
    const selectedCheckboxes = document.querySelectorAll('.device-checkbox:checked');
    return Array.from(selectedCheckboxes).map(cb => decodeURIComponent(cb.value));
}

// Bulk Add Tag
function showBulkAddTagModal() {
    const selectedIds = getSelectedDeviceIds();
    document.getElementById('add-tag-count').textContent = selectedIds.length;
    document.getElementById('new-tag-name').value = '';

    const modal = new bootstrap.Modal(document.getElementById('bulkAddTagModal'), {
        backdrop: false
    });
    modal.show();
}

async function confirmBulkAddTag() {
    const selectedIds = getSelectedDeviceIds();
    const tagName = document.getElementById('new-tag-name').value.trim();

    if (!tagName) {
        showToast('Please enter a tag name', 'warning');
        return;
    }

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkAddTagModal'));
    modal.hide();

    showLoading();

    const result = await fetchAPI('/api/bulk-tag.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add',
            device_ids: selectedIds,
            tag: tagName
        })
    });

    hideLoading();

    console.log('Bulk Add Tag Response:', result);

    // Show detailed debug info if available
    if (result && result.debug) {
        console.table(result.debug);
    }

    if (result && result.success) {
        showToast(`Tag "${tagName}" added to ${result.success_count || selectedIds.length} device(s)`, 'success');

        if (result.fail_count && result.fail_count > 0) {
            console.warn('Some devices failed:', result.errors);
            console.warn('Debug info for failures:', result.debug);
            showToast(`Warning: ${result.fail_count} device(s) failed`, 'warning');
        }

        loadDevices(); // Reload devices to show updated tags

        // Clear selections
        document.querySelectorAll('.device-checkbox:checked').forEach(cb => cb.checked = false);
        updateBulkActionButtons();
    } else {
        console.error('Add tag failed:', result);
        if (result && result.debug) {
            console.error('Debug details:', result.debug);
        }
        showToast(result?.message || 'Failed to add tags', 'error');
    }
}

// Bulk Untag
function showBulkUntagModal() {
    const selectedIds = getSelectedDeviceIds();
    document.getElementById('untag-count').textContent = selectedIds.length;

    // Clear input field
    const inputField = document.getElementById('remove-tag-name');
    inputField.value = '';

    const modal = new bootstrap.Modal(document.getElementById('bulkUntagModal'), {
        backdrop: false
    });
    modal.show();
}

async function confirmBulkUntag() {
    const selectedIds = getSelectedDeviceIds();
    const tagName = document.getElementById('remove-tag-name').value.trim();

    if (!tagName) {
        showToast('Please enter a tag name to remove', 'warning');
        return;
    }

    console.log('Bulk Untag Request:', {
        action: 'remove',
        device_ids: selectedIds,
        tag: tagName
    });

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkUntagModal'));
    modal.hide();

    showLoading();

    const result = await fetchAPI('/api/bulk-tag.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'remove',
            device_ids: selectedIds,
            tag: tagName
        })
    });

    hideLoading();

    console.log('Bulk Untag Response:', result);

    // Show detailed debug info if available
    if (result && result.debug) {
        console.table(result.debug);
    }

    if (result && result.success) {
        showToast(`Tag "${tagName}" removed from ${result.success_count || selectedIds.length} device(s)`, 'success');

        if (result.fail_count && result.fail_count > 0) {
            console.warn('Some devices failed:', result.errors);
            console.warn('Debug info for failures:', result.debug);
            showToast(`Warning: ${result.fail_count} device(s) failed`, 'warning');
        }

                loadDevices(); // Reload devices to show updated tags

        // Clear selections
        document.querySelectorAll('.device-checkbox:checked').forEach(cb => cb.checked = false);
        updateBulkActionButtons();
    } else {
        console.error('Untag failed:', result);
        if (result && result.debug) {
            console.error('Debug details:', result.debug);
        }
        showToast(result?.message || 'Failed to remove tags', 'error');
    }
}

// Bulk Delete
function showBulkDeleteModal() {
    const selectedIds = getSelectedDeviceIds();
    document.getElementById('delete-count').textContent = selectedIds.length;

    const modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'), {
        backdrop: false
    });
    modal.show();
}

async function confirmBulkDelete() {
    const selectedIds = getSelectedDeviceIds();

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal'));
    modal.hide();

    showLoading();

    const result = await fetchAPI('/api/bulk-delete-devices.php', {
        method: 'POST',
        body: JSON.stringify({
            device_ids: selectedIds
        })
    });

    hideLoading();

    if (result && result.success) {
        showToast(`${selectedIds.length} device(s) deleted successfully`, 'success');
                loadDevices(); // Reload devices

        // Clear selections
        document.querySelectorAll('.device-checkbox:checked').forEach(cb => cb.checked = false);
        updateBulkActionButtons();
    } else {
        showToast(result?.message || 'Failed to delete devices', 'error');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadSavedDeviceViews();
    renderSavedDeviceViews();
    updateQuickFilterButtons();
    
        if (window.GENIEACS_CONFIGURED) loadDevices(); // Initial load (manual)

        // Start auto-refresh timer
        if (window.GENIEACS_CONFIGURED) autoRefreshTimer = setInterval(() => loadDevices(true), 60000); // Auto-refresh every 60 seconds
    

    // Keyboard shortcuts for pagination (Left/Right arrow keys)
    document.addEventListener('keydown', function(e) {
        // Only work if not typing in input field
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }

        const totalPages = Math.ceil(totalDevices / itemsPerPage);

        if (e.key === 'ArrowLeft' && currentPage > 1) {
            goToPage(currentPage - 1);
        } else if (e.key === 'ArrowRight' && currentPage < totalPages) {
            goToPage(currentPage + 1);
        }
    });
});

// Cleanup: Stop auto-refresh when user navigates away
window.addEventListener('beforeunload', function() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
});

// Also cleanup on page visibility change (when tab becomes hidden)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden, stop auto-refresh to save resources
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    } else {
        // Page is visible again, restart auto-refresh
        
        if (!autoRefreshTimer) {
            if (window.GENIEACS_CONFIGURED) autoRefreshTimer = setInterval(() => loadDevices(true), 60000);
        }

    }
});
