<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <!-- Google Fonts - Inter (Professional UI Font) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <!-- Client-Side Logger -->
    <script src="/assets/js/client-logger.js?v=<?php echo time(); ?>"></script>
</head>
<body class="theme-shadcn">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="bi bi-hdd-network" style="font-size: 1.5rem;"></i>
                <h3>NETKING-ACS</h3>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="/dashboard.php" class="<?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>" data-tooltip="Dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="/devices.php" class="<?php echo ($currentPage ?? '') === 'devices' ? 'active' : ''; ?>" data-tooltip="Perangkat">
                    <i class="bi bi-router"></i>
                    <span>Perangkat</span>
                </a>
            </li>
            <li>
                <a href="/olt-registry.php" class="<?php echo ($currentPage ?? '') === 'olt-registry' ? 'active' : ''; ?>" data-tooltip="OLT">
                    <i class="bi bi-broadcast-pin"></i>
                    <span>OLT</span>
                </a>
            </li>
            <li>
                <a href="/configuration.php" class="<?php echo ($currentPage ?? '') === 'configuration' ? 'active' : ''; ?>" data-tooltip="Integrasi">
                    <i class="bi bi-gear"></i>
                    <span>Integrasi</span>
                </a>
            </li>
            <li>
                <a href="/alarm-events.php" class="<?php echo ($currentPage ?? '') === 'alarm-events' ? 'active' : ''; ?>" data-tooltip="Alarm">
                    <i class="bi bi-bell"></i>
                    <span>Alarm</span>
                </a>
            </li>
            <li>
                <a href="/logout.php" data-tooltip="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="main-shell">
            <!-- Topbar -->
            <div class="topbar">
                <h4><?php echo $pageTitle ?? APP_NAME; ?></h4>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="openCommandPalette()" title="Command Palette (Ctrl+K)">
                        <i class="bi bi-search"></i> <span class="d-none d-md-inline">Ctrl+K</span>
                    </button>
                    <div class="user-info">
                    <span><i class="bi bi-person-circle"></i> <?php echo $_SESSION['username'] ?? 'User'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
