<?php
/**
 * Super Admin Gold Standard Header Component
 * STANDALONE Platform Master interface - does NOT use main site header/footer
 * This is for managing ALL tenants across the NEXUS platform
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPathClean = strtok($currentPath, '?');
$currentUser = $_SESSION['user_name'] ?? 'Super Admin';
$userInitials = strtoupper(substr($currentUser, 0, 2));

$superAdminPageTitle = $superAdminPageTitle ?? 'Super Admin';
$superAdminPageSubtitle = $superAdminPageSubtitle ?? 'Platform Master';
$superAdminPageIcon = $superAdminPageIcon ?? 'fa-satellite-dish';

/**
 * Super Admin Navigation Structure
 */
$superAdminModules = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'url' => '/super-admin',
        'single' => true,
    ],
    'tenants' => [
        'label' => 'Tenants',
        'icon' => 'fa-city',
        'items' => [
            ['label' => 'All Communities', 'url' => '/super-admin', 'icon' => 'fa-buildings'],
            ['label' => 'Deploy New', 'url' => '/super-admin#deploy', 'icon' => 'fa-rocket'],
        ],
    ],
    'users' => [
        'label' => 'Users',
        'icon' => 'fa-users-gear',
        'items' => [
            ['label' => 'Global Directory', 'url' => '/super-admin/users', 'icon' => 'fa-users'],
            ['label' => 'Pending Approvals', 'url' => '/super-admin/users?filter=pending', 'icon' => 'fa-user-clock'],
        ],
    ],
    'system' => [
        'label' => 'System',
        'icon' => 'fa-server',
        'items' => [
            ['label' => 'Queue Health', 'url' => '/super-admin#queue', 'icon' => 'fa-layer-group'],
            ['label' => 'Cron Jobs', 'url' => '/super-admin#cron', 'icon' => 'fa-clock'],
        ],
    ],
];

if (!function_exists('isSuperAdminNavActive')) {
    function isSuperAdminNavActive($itemUrl, $currentPath, $basePath) {
        $fullUrl = $basePath . $itemUrl;
        $currentClean = strtok($currentPath, '?');
        // Remove hash from comparison
        $fullUrlClean = strtok($fullUrl, '#');
        if ($currentClean === $fullUrlClean) return true;
        if ($itemUrl === '/super-admin') return $currentClean === $fullUrlClean;
        return strpos($currentClean, $fullUrlClean) === 0;
    }
}

if (!function_exists('getActiveSuperAdminModule')) {
    function getActiveSuperAdminModule($modules, $currentPath, $basePath) {
        foreach ($modules as $key => $module) {
            if (isset($module['single']) && $module['single']) {
                if (isSuperAdminNavActive($module['url'], $currentPath, $basePath)) return $key;
            } elseif (isset($module['items'])) {
                foreach ($module['items'] as $item) {
                    if (isSuperAdminNavActive($item['url'], $currentPath, $basePath)) return $key;
                }
            }
        }
        return 'dashboard';
    }
}

$activeModule = getActiveSuperAdminModule($superAdminModules, $currentPath, $basePath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($superAdminPageTitle) ?> - Platform Master</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
    /* Base Reset */
    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    html, body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #0a0e1a;
        color: #fff;
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    /* Super Admin Wrapper - Distinct Purple/Indigo Theme */
    .super-admin-wrapper {
        position: relative;
        min-height: 100vh;
        padding: 1rem;
        background: linear-gradient(135deg, #0f0a1a 0%, #1a0f29 50%, #251532 100%);
    }

    .super-admin-bg {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
    }

    .super-admin-bg::before {
        content: '';
        position: absolute;
        width: 700px;
        height: 700px;
        background: radial-gradient(circle, rgba(147, 51, 234, 0.15) 0%, transparent 70%);
        top: -250px;
        right: -250px;
        animation: superAdminFloat 20s ease-in-out infinite;
    }

    .super-admin-bg::after {
        content: '';
        position: absolute;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(236, 72, 153, 0.1) 0%, transparent 70%);
        bottom: -200px;
        left: -200px;
        animation: superAdminFloat 25s ease-in-out infinite reverse;
    }

    @keyframes superAdminFloat {
        0%, 100% { transform: translate(0, 0); }
        50% { transform: translate(30px, -30px); }
    }

    /* Top Bar */
    .super-admin-header-bar {
        position: relative;
        z-index: 10;
        background: rgba(15, 10, 26, 0.95);
        border: 1px solid rgba(147, 51, 234, 0.3);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        max-width: 1600px;
        margin-left: auto;
        margin-right: auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .super-admin-header-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .super-admin-header-brand-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #9333ea, #ec4899);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
        font-size: 1.1rem;
    }

    .super-admin-header-brand-text {
        display: flex;
        flex-direction: column;
    }

    .super-admin-header-title {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
        letter-spacing: -0.01em;
    }

    .super-admin-header-subtitle {
        font-size: 0.65rem;
        color: rgba(236, 72, 153, 0.8);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .super-admin-header-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .super-admin-back-link {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        background: rgba(147, 51, 234, 0.15);
        border: 1px solid rgba(147, 51, 234, 0.3);
        border-radius: 6px;
        color: #c084fc;
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 600;
        transition: background 0.2s;
    }

    .super-admin-back-link:hover {
        background: rgba(147, 51, 234, 0.25);
    }

    .super-admin-header-avatar {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: linear-gradient(135deg, #ec4899, #f97316);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
    }

    .super-admin-badge {
        padding: 0.25rem 0.5rem;
        background: linear-gradient(135deg, #9333ea, #ec4899);
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: white;
    }

    /* Navigation Bar */
    .super-admin-nav {
        position: relative;
        z-index: 100;
        background: rgba(15, 10, 26, 0.95);
        border: 1px solid rgba(147, 51, 234, 0.3);
        border-radius: 12px;
        padding: 0.5rem;
        margin: 0 auto 1.5rem;
        max-width: 1600px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .super-admin-nav-scroll {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex: 1;
        flex-wrap: wrap;
    }

    /* Nav tabs */
    .super-admin-nav-tab {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.45rem 0.7rem;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        font-size: 0.78rem;
        font-weight: 500;
        border-radius: 6px;
        border: none;
        background: transparent;
        cursor: pointer;
        white-space: nowrap;
        font-family: inherit;
        transition: all 0.15s;
    }

    .super-admin-nav-tab:hover {
        color: #fff;
        background: rgba(147, 51, 234, 0.15);
    }

    .super-admin-nav-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #9333ea, #ec4899);
    }

    .super-admin-nav-tab .chevron {
        font-size: 0.55rem;
        opacity: 0.5;
        transition: transform 0.2s;
    }

    /* Dropdowns */
    .super-admin-nav-dropdown {
        position: relative;
        z-index: 10;
    }

    .super-admin-nav-dropdown:hover {
        z-index: 100;
    }

    .super-admin-nav-dropdown::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        height: 16px;
        background: transparent;
    }

    .super-admin-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        min-width: 200px;
        background: #1a0f29;
        border: 1px solid rgba(147, 51, 234, 0.4);
        border-radius: 10px;
        padding: 0.4rem;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.15s ease;
        z-index: 9999;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        pointer-events: none;
    }

    .super-admin-nav-dropdown:hover > .super-admin-dropdown-menu,
    .super-admin-nav-dropdown:focus-within > .super-admin-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        pointer-events: auto;
    }

    .super-admin-nav-dropdown:hover > .super-admin-nav-tab .chevron {
        transform: rotate(180deg);
    }

    .super-admin-dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.55rem 0.75rem;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        font-size: 0.82rem;
        border-radius: 6px;
        transition: all 0.1s;
    }

    .super-admin-dropdown-menu a:hover {
        color: #fff;
        background: rgba(147, 51, 234, 0.2);
    }

    .super-admin-dropdown-menu a.active {
        color: #e879f9;
        background: rgba(147, 51, 234, 0.15);
    }

    .super-admin-dropdown-menu a i {
        width: 16px;
        text-align: center;
        font-size: 0.8rem;
        opacity: 0.6;
    }

    .super-admin-dropdown-menu a:hover i {
        opacity: 1;
        color: #c084fc;
    }

    /* Mobile toggle */
    .super-admin-mobile-btn {
        display: none;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        background: rgba(147, 51, 234, 0.15);
        border: 1px solid rgba(147, 51, 234, 0.3);
        color: rgba(255,255,255,0.8);
        cursor: pointer;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* Content */
    .super-admin-content {
        position: relative;
        z-index: 5;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Glass cards - Purple theme */
    .super-admin-glass-card {
        background: rgba(26, 15, 41, 0.85);
        border: 1px solid rgba(147, 51, 234, 0.2);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .super-admin-card-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(147, 51, 234, 0.15);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .super-admin-card-header-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .super-admin-card-header-icon-purple {
        background: linear-gradient(135deg, #9333ea, #7c3aed);
        color: white;
    }

    .super-admin-card-header-icon-pink {
        background: linear-gradient(135deg, #ec4899, #db2777);
        color: white;
    }

    .super-admin-card-header-icon-emerald {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .super-admin-card-header-icon-amber {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .super-admin-card-header-icon-cyan {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        color: white;
    }

    .super-admin-card-header-content {
        flex: 1;
    }

    .super-admin-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }

    .super-admin-card-subtitle {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.5);
        margin: 0;
    }

    .super-admin-card-body {
        padding: 1.25rem;
    }

    /* Stats Grid */
    .super-admin-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .super-admin-stat-card {
        background: rgba(26, 15, 41, 0.85);
        border: 1px solid rgba(147, 51, 234, 0.2);
        border-radius: 12px;
        padding: 1.25rem;
        position: relative;
        overflow: hidden;
    }

    .super-admin-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--stat-color, linear-gradient(135deg, #9333ea, #ec4899));
    }

    .super-admin-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .super-admin-stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        margin-bottom: 0.25rem;
    }

    .super-admin-stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    /* Page Header */
    .super-admin-page-header {
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(147, 51, 234, 0.15), rgba(236, 72, 153, 0.1));
        border: 1px solid rgba(147, 51, 234, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .super-admin-page-header-content {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .super-admin-page-header-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #9333ea, #ec4899);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
    }

    .super-admin-page-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #fff;
        margin: 0;
    }

    .super-admin-page-subtitle {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.5);
        margin: 0;
    }

    /* Buttons */
    .super-admin-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        border: none;
        font-family: inherit;
    }

    .super-admin-btn-primary {
        background: linear-gradient(135deg, #9333ea, #ec4899);
        color: white;
        box-shadow: 0 4px 15px rgba(147, 51, 234, 0.3);
    }

    .super-admin-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(147, 51, 234, 0.4);
    }

    .super-admin-btn-secondary {
        background: rgba(147, 51, 234, 0.1);
        border: 1px solid rgba(147, 51, 234, 0.3);
        color: #c084fc;
    }

    .super-admin-btn-secondary:hover {
        background: rgba(147, 51, 234, 0.2);
    }

    .super-admin-btn-danger {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #f87171;
    }

    .super-admin-btn-danger:hover {
        background: rgba(239, 68, 68, 0.25);
    }

    .super-admin-btn-sm {
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
    }

    /* Tables */
    .super-admin-table-wrapper {
        overflow-x: auto;
    }

    .super-admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .super-admin-table th {
        text-align: left;
        padding: 0.75rem 1rem;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: rgba(255,255,255,0.5);
        border-bottom: 1px solid rgba(147, 51, 234, 0.15);
        background: rgba(147, 51, 234, 0.05);
    }

    .super-admin-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(147, 51, 234, 0.1);
        color: rgba(255,255,255,0.9);
        font-size: 0.9rem;
    }

    .super-admin-table tbody tr:hover {
        background: rgba(147, 51, 234, 0.05);
    }

    /* Form Elements */
    .super-admin-form-group {
        margin-bottom: 1rem;
    }

    .super-admin-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: rgba(255,255,255,0.7);
        margin-bottom: 0.4rem;
    }

    .super-admin-input,
    .super-admin-textarea,
    .super-admin-select {
        width: 100%;
        padding: 0.65rem 0.85rem;
        background: rgba(15, 10, 26, 0.6);
        border: 1px solid rgba(147, 51, 234, 0.2);
        border-radius: 8px;
        color: #fff;
        font-size: 0.9rem;
        font-family: inherit;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .super-admin-input:focus,
    .super-admin-textarea:focus,
    .super-admin-select:focus {
        outline: none;
        border-color: rgba(147, 51, 234, 0.5);
        box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
    }

    .super-admin-input::placeholder {
        color: rgba(255,255,255,0.3);
    }

    /* Status badges */
    .super-admin-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.6rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .super-admin-status-active {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
    }

    .super-admin-status-pending {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
    }

    .super-admin-status-inactive {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
    }

    .super-admin-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    /* Two Column Layout */
    .super-admin-two-col {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 1.5rem;
    }

    /* Mobile */
    @media (max-width: 1200px) {
        .super-admin-two-col {
            grid-template-columns: 1fr;
        }

        .super-admin-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .super-admin-mobile-btn {
            display: flex;
        }

        .super-admin-nav-scroll {
            display: none;
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            right: 0;
            background: #1a0f29;
            border: 1px solid rgba(147, 51, 234, 0.3);
            border-radius: 10px;
            padding: 0.75rem;
            flex-direction: column;
            gap: 0.25rem;
            max-height: 70vh;
            overflow-y: auto;
            z-index: 200;
        }

        .super-admin-nav-scroll.open {
            display: flex;
        }

        .super-admin-nav-tab {
            width: 100%;
            justify-content: flex-start;
        }

        .super-admin-nav-dropdown::after {
            display: none;
        }

        .super-admin-dropdown-menu {
            position: static !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
            margin: 0.25rem 0 0.25rem 1rem;
            padding: 0.5rem 0 0.5rem 0.75rem;
            background: transparent !important;
            border: none !important;
            border-left: 2px solid rgba(147, 51, 234, 0.3) !important;
            box-shadow: none !important;
            display: none;
            pointer-events: auto !important;
        }

        .super-admin-nav-dropdown.open .super-admin-dropdown-menu {
            display: block !important;
        }

        .super-admin-nav-dropdown:hover .super-admin-dropdown-menu,
        .super-admin-nav-dropdown:focus-within .super-admin-dropdown-menu {
            display: none;
        }

        .super-admin-nav-dropdown.open .super-admin-dropdown-menu,
        .super-admin-nav-dropdown.open:hover .super-admin-dropdown-menu {
            display: block !important;
        }

        .super-admin-header-brand-text {
            display: none;
        }

        .super-admin-back-link span {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .super-admin-stats-grid {
            grid-template-columns: 1fr;
        }

        .super-admin-page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 600px) {
        .super-admin-wrapper {
            padding: 0.5rem;
        }

        .super-admin-header-bar {
            padding: 0.5rem;
        }

        .super-admin-nav {
            padding: 0.4rem;
        }

        .super-admin-glass-card {
            border-radius: 10px;
        }

        .super-admin-card-body {
            padding: 0.75rem;
        }
    }
    </style>
</head>
<body>
<div class="super-admin-wrapper">
    <div class="super-admin-bg"></div>

    <!-- Top Bar -->
    <div class="super-admin-header-bar">
        <div class="super-admin-header-brand">
            <div class="super-admin-header-brand-icon">
                <i class="fa-solid <?= htmlspecialchars($superAdminPageIcon) ?>"></i>
            </div>
            <div class="super-admin-header-brand-text">
                <span class="super-admin-header-title">NEXUS Platform</span>
                <span class="super-admin-header-subtitle"><?= htmlspecialchars($superAdminPageSubtitle) ?></span>
            </div>
        </div>
        <div class="super-admin-header-actions">
            <span class="super-admin-badge">Super Admin</span>
            <a href="/admin" class="super-admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Tenant Admin</span>
            </a>
            <a href="/" class="super-admin-back-link">
                <i class="fa-solid fa-home"></i>
                <span>Site</span>
            </a>
            <div class="super-admin-header-avatar"><?= htmlspecialchars($userInitials) ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav role="navigation" aria-label="Main navigation" class="super-admin-nav">
        <div class="super-admin-nav-scroll" id="superAdminNavScroll">
            <?php foreach ($superAdminModules as $moduleKey => $module): ?>
                <?php if (isset($module['single']) && $module['single']): ?>
                    <a href="<?= $basePath . $module['url'] ?>" class="super-admin-nav-tab <?= $activeModule === $moduleKey ? 'active' : '' ?>">
                        <i class="fa-solid <?= $module['icon'] ?>"></i>
                        <span><?= $module['label'] ?></span>
                    </a>
                <?php else: ?>
                    <div class="super-admin-nav-dropdown" data-dropdown="<?= $moduleKey ?>">
                        <button type="button" class="super-admin-nav-tab <?= $activeModule === $moduleKey ? 'active' : '' ?>">
                            <i class="fa-solid <?= $module['icon'] ?>"></i>
                            <span><?= $module['label'] ?></span>
                            <i class="fa-solid fa-chevron-down chevron"></i>
                        </button>
                        <div class="super-admin-dropdown-menu">
                            <?php foreach ($module['items'] as $item): ?>
                                <a href="<?= $basePath . $item['url'] ?>" class="<?= isSuperAdminNavActive($item['url'], $currentPath, $basePath) ? 'active' : '' ?>">
                                    <i class="fa-solid <?= $item['icon'] ?>"></i>
                                    <?= $item['label'] ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="super-admin-mobile-btn" id="superAdminMobileBtn" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </nav>

    <!-- Content area starts here -->
    <div class="super-admin-content">

<script>
// Mobile menu handling
(function() {
    var mobileBtn = document.getElementById('superAdminMobileBtn');
    var navScroll = document.getElementById('superAdminNavScroll');
    var mobileBtnIcon = mobileBtn ? mobileBtn.querySelector('i') : null;

    function updateMobileIcon() {
        if (mobileBtnIcon) {
            mobileBtnIcon.className = navScroll && navScroll.classList.contains('open')
                ? 'fa-solid fa-times'
                : 'fa-solid fa-bars';
        }
    }

    function closeMobileMenu() {
        if (navScroll) navScroll.classList.remove('open');
        document.querySelectorAll('.super-admin-nav-dropdown.open').forEach(function(d) {
            d.classList.remove('open');
        });
        updateMobileIcon();
    }

    if (mobileBtn && navScroll) {
        mobileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navScroll.classList.toggle('open');
            updateMobileIcon();
        });
    }

    document.querySelectorAll('.super-admin-nav-dropdown > button').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                e.preventDefault();
                e.stopPropagation();
                var dropdown = this.parentElement;
                var wasOpen = dropdown.classList.contains('open');
                document.querySelectorAll('.super-admin-nav-dropdown.open').forEach(function(d) {
                    if (d !== dropdown) d.classList.remove('open');
                });
                dropdown.classList.toggle('open', !wasOpen);
            }
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.super-admin-nav')) {
            closeMobileMenu();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            closeMobileMenu();
        }
    });
})();
</script>
