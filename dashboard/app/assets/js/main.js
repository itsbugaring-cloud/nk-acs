// Netking Dashboard JavaScript Utilities

// Toast Notification
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.style.maxWidth = '500px';
    toast.style.animation = 'slideInRight 0.3s ease';
    toast.textContent = message;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// AJAX Helper
async function fetchAPI(url, options = {}) {
    try {
        // Add timeout support (default 15 seconds)
        const timeout = options.timeout || 15000;
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        const response = await fetch(url, {
            ...options,
            signal: controller.signal,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        });

        clearTimeout(timeoutId);

        // Check if response is OK
        if (!response.ok) {
            console.error('HTTP Error:', response.status, response.statusText, 'URL:', url);
        }

        // Get content type
        const contentType = response.headers.get('content-type');

        // Check if response is JSON
        if (contentType && contentType.includes('application/json')) {
            const raw = await response.text();
            if (!raw || !raw.trim()) {
                console.error('Empty JSON response from', url);
                return { success: false, error: 'empty_response', message: 'Empty JSON response' };
            }

            let data;
            try {
                data = JSON.parse(raw);
            } catch (parseError) {
                console.error('Invalid JSON response from', url, ':', raw.substring(0, 500));
                return { success: false, error: 'invalid_json', message: parseError.message };
            }
            return data;
        } else {
            // Response is not JSON (probably HTML error page or PHP error)
            const text = await response.text();
            console.error('Non-JSON response from', url, ':', text.substring(0, 500));

            // Show first 200 chars of error in toast for debugging
            const errorPreview = text.substring(0, 200).replace(/<[^>]*>/g, ''); // Remove HTML tags
            showToast('Server error: ' + errorPreview, 'danger');
            return null;
        }
    } catch (error) {
        // Check if error is due to abort (timeout or user navigated away)
        if (error.name === 'AbortError') {
            // Log but don't show toast for timeout/abort (will be handled by calling code)
            console.debug('Request aborted for', url);
            return { success: false, error: 'timeout', message: 'Request timeout' };
        }

        // Log other fetch errors
        console.error('Fetch error for', url, ':', error);

        // Only show toast for non-abort errors
        if (!url.includes('/api/get-hotspot-traffic.php')) {
            // Don't show toast for hotspot API errors (handled separately)
            showToast('Terjadi kesalahan koneksi: ' + error.message, 'danger');
        }
        return { success: false, error: error.name, message: error.message };
    }
}

// Format timestamp
function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleString('id-ID');
}

// Confirm dialog
function confirmAction(message) {
    return confirm(message);
}

// Loading overlay
function showLoading() {
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.background = 'rgba(0, 0, 0, 0.5)';
    overlay.style.zIndex = '9998';
    overlay.style.display = 'flex';
    overlay.style.justifyContent = 'center';
    overlay.style.alignItems = 'center';

    overlay.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(overlay);
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

// Auto refresh data
function autoRefresh(callback, interval = 30000) {
    setInterval(callback, interval);
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Berhasil disalin!', 'success');
    }).catch(() => {
        showToast('Gagal menyalin', 'danger');
    });
}

// Format uptime
function formatUptime(seconds) {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    let result = '';
    if (days > 0) result += days + ' hari ';
    if (hours > 0) result += hours + ' jam ';
    if (minutes > 0) result += minutes + ' menit';

    return result || '0 menit';
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (!sidebar || !mainContent) {
        return;
    }

    // Disable collapsible sidebar: always keep expanded layout.
    sidebar.classList.remove('collapsed');
    mainContent.classList.remove('collapsed');
    localStorage.removeItem('sidebarCollapsed');
});

let commandPaletteTimer = null;

function getCommandPalettePages() {
    return [
        { label: 'Dashboard', hint: 'Buka ringkasan utama', url: '/dashboard.php', icon: 'speedometer2' },
        { label: 'Perangkat', hint: 'Buka daftar ONT/ONU', url: '/devices.php', icon: 'router' },
        { label: 'OLT', hint: 'Buka registry OLT manual', url: '/olt-registry.php', icon: 'broadcast-pin' },
        { label: 'Alarm', hint: 'Buka alarm center', url: '/alarm-events.php', icon: 'bell' },
        { label: 'Integrasi', hint: 'Buka konfigurasi ACS/MikroTik/Bot', url: '/configuration.php', icon: 'gear' }
    ];
}

function openCommandPalette() {
    const modalEl = document.getElementById('commandPaletteModal');
    const input = document.getElementById('command-palette-input');
    const results = document.getElementById('command-palette-results');
    if (!modalEl || !input || !results) return;

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    input.value = '';
    results.innerHTML = renderCommandPaletteResults(getCommandPalettePages(), []);

    setTimeout(() => input.focus(), 150);
}

function renderCommandPaletteResults(pages, devices) {
    let html = '';
    if (pages.length > 0) {
        html += pages.map(item => `
            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="window.location.href='${item.url}'">
                <span><i class="bi bi-${item.icon} me-2"></i>${item.label}</span>
                <span class="text-muted small">${item.hint}</span>
            </button>
        `).join('');
    }

    if (devices.length > 0) {
        html += devices.map(device => `
            <button type="button" class="list-group-item list-group-item-action" onclick="window.location.href='/device-detail.php?id=${encodeURIComponent(device.device_id)}'">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-router me-2"></i>${device.serial_number}</span>
                    <span class="badge ${device.status === 'online' ? 'bg-success' : 'bg-danger'}">${device.status || 'unknown'}</span>
                </div>
                <div class="text-muted small mt-1">${device.customer_name || '-'} · ${device.olt_name || '-'} · ${device.pppoe_username || device.product_class || '-'}</div>
            </button>
        `).join('');
    }

    if (!html) {
        html = '<div class="list-group-item text-muted">Tidak ada hasil.</div>';
    }
    return html;
}

async function runCommandPaletteSearch() {
    const input = document.getElementById('command-palette-input');
    const results = document.getElementById('command-palette-results');
    if (!input || !results) return;

    const term = input.value.trim();
    const pages = getCommandPalettePages().filter(item =>
        item.label.toLowerCase().includes(term.toLowerCase()) ||
        item.hint.toLowerCase().includes(term.toLowerCase())
    );

    if (term.length < 2) {
        results.innerHTML = renderCommandPaletteResults(pages, []);
        return;
    }

    results.innerHTML = '<div class="list-group-item text-muted">Mencari perangkat...</div>';
    const result = await fetchAPI(`/api/search-devices.php?q=${encodeURIComponent(term)}&limit=8`, { timeout: 12000 });
    if (!result || !result.success) {
        results.innerHTML = renderCommandPaletteResults(pages, []);
        return;
    }

    results.innerHTML = renderCommandPaletteResults(pages, result.devices || []);
}

document.addEventListener('keydown', function(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        openCommandPalette();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('command-palette-input');
    if (!input) return;

    input.addEventListener('input', function() {
        clearTimeout(commandPaletteTimer);
        commandPaletteTimer = setTimeout(runCommandPaletteSearch, 200);
    });
});
