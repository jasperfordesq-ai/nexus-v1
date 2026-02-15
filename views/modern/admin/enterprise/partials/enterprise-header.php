<?php
/**
 * Enterprise Module Header - Gold Standard
 * STANDALONE admin interface with role-based access control
 *
 * Access Levels:
 * - Regular Admin: GDPR (view/manage requests), Monitoring (view only)
 * - Super Admin: Full access including Config/Secrets
 */

use Nexus\Core\TenantContext;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentUser = $_SESSION['user_name'] ?? $_SESSION['first_name'] ?? 'Admin';
$userInitials = strtoupper(substr($currentUser, 0, 2));
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Page configuration (set by including page)
$enterprisePageTitle = $enterprisePageTitle ?? 'Enterprise';
$enterprisePageSubtitle = $enterprisePageSubtitle ?? 'Command Center';
$enterprisePageIcon = $enterprisePageIcon ?? 'fa-building-shield';
$enterpriseSection = $enterpriseSection ?? 'dashboard';
$enterpriseSubpage = $enterpriseSubpage ?? '';

// Check if current page requires Super Admin
$superAdminOnlyPages = [
    'config' => true,
    'secrets' => true,
];

$currentRequiresSuperAdmin = isset($superAdminOnlyPages[$enterpriseSection]) ||
                              isset($superAdminOnlyPages[$enterpriseSubpage]);

// Block access if Super Admin required but user isn't
if ($currentRequiresSuperAdmin && !$isSuperAdmin) {
    header('HTTP/1.0 403 Forbidden');
    echo '<h1>Access Denied</h1><p>This section requires Super Admin privileges.</p>';
    echo '<p><a href="' . $basePath . '/admin-legacy/enterprise">Return to Enterprise Dashboard</a></p>';
    exit;
}

/**
 * Navigation Structure with Access Control
 */
$enterpriseNav = [
    'dashboard' => [
        'label' => 'Overview',
        'icon' => 'fa-gauge-high',
        'url' => '/admin-legacy/enterprise',
        'superAdminOnly' => false,
    ],
    'gdpr' => [
        'label' => 'GDPR',
        'icon' => 'fa-shield-halved',
        'url' => '/admin-legacy/enterprise/gdpr',
        'superAdminOnly' => false,
        'items' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge', 'url' => '/admin-legacy/enterprise/gdpr'],
            ['id' => 'requests', 'label' => 'Requests', 'icon' => 'fa-inbox', 'url' => '/admin-legacy/enterprise/gdpr/requests'],
            ['id' => 'breaches', 'label' => 'Breaches', 'icon' => 'fa-triangle-exclamation', 'url' => '/admin-legacy/enterprise/gdpr/breaches'],
            ['id' => 'consents', 'label' => 'Consents', 'icon' => 'fa-clipboard-check', 'url' => '/admin-legacy/enterprise/gdpr/consents'],
            ['id' => 'audit', 'label' => 'Audit Log', 'icon' => 'fa-clock-rotate-left', 'url' => '/admin-legacy/enterprise/gdpr/audit'],
        ],
    ],
    'monitoring' => [
        'label' => 'Monitoring',
        'icon' => 'fa-chart-line',
        'url' => '/admin-legacy/enterprise/monitoring',
        'superAdminOnly' => false,
        'items' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge', 'url' => '/admin-legacy/enterprise/monitoring'],
            ['id' => 'logs', 'label' => 'System Logs', 'icon' => 'fa-file-lines', 'url' => '/admin-legacy/enterprise/monitoring/logs', 'superAdminOnly' => true],
        ],
    ],
    'config' => [
        'label' => 'Config',
        'icon' => 'fa-sliders',
        'url' => '/admin-legacy/enterprise/config',
        'superAdminOnly' => true,
        'items' => [
            ['id' => 'dashboard', 'label' => 'Settings', 'icon' => 'fa-gear', 'url' => '/admin-legacy/enterprise/config'],
            ['id' => 'secrets', 'label' => 'Secrets Vault', 'icon' => 'fa-key', 'url' => '/admin-legacy/enterprise/config/secrets'],
        ],
    ],
];

// Filter navigation based on access level
function filterNavForAccess($nav, $isSuperAdmin) {
    $filtered = [];
    foreach ($nav as $key => $section) {
        // Skip entire section if super admin only and user isn't
        if (!empty($section['superAdminOnly']) && !$isSuperAdmin) {
            continue;
        }

        // Filter sub-items
        if (!empty($section['items'])) {
            $section['items'] = array_filter($section['items'], function($item) use ($isSuperAdmin) {
                return empty($item['superAdminOnly']) || $isSuperAdmin;
            });
        }

        $filtered[$key] = $section;
    }
    return $filtered;
}

$filteredNav = filterNavForAccess($enterpriseNav, $isSuperAdmin);

// Check if nav item is active
function isEnterpriseNavActive($url, $currentPath, $basePath) {
    $fullUrl = $basePath . $url;
    $currentClean = strtok($currentPath, '?');
    if ($currentClean === $fullUrl) return true;
    if ($url !== '/admin-legacy/enterprise' && strpos($currentClean, $fullUrl) === 0) return true;
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($enterprisePageTitle) ?> - Enterprise Admin</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
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

    /* Enterprise Wrapper - Cyan/Teal Theme for distinction */
    .enterprise-wrapper {
        position: relative;
        min-height: 100vh;
        padding: 1rem;
        background: linear-gradient(135deg, #0a1628 0%, #0f2030 50%, #0a1a2e 100%);
    }

    .enterprise-bg-effects {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden;
    }

    .enterprise-bg-effects::before {
        content: '';
        position: absolute;
        width: 800px;
        height: 800px;
        background: radial-gradient(circle, rgba(6, 182, 212, 0.12) 0%, transparent 70%);
        top: -300px;
        right: -300px;
        animation: enterpriseFloat 25s ease-in-out infinite;
    }

    .enterprise-bg-effects::after {
        content: '';
        position: absolute;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
        bottom: -200px;
        left: -200px;
        animation: enterpriseFloat 30s ease-in-out infinite reverse;
    }

    @keyframes enterpriseFloat {
        0%, 100% { transform: translate(0, 0); }
        50% { transform: translate(40px, -40px); }
    }

    /* Grid pattern overlay */
    .enterprise-grid-overlay {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        background:
            repeating-linear-gradient(90deg, transparent 0px, rgba(6, 182, 212, 0.02) 1px, transparent 2px, transparent 100px),
            repeating-linear-gradient(0deg, transparent 0px, rgba(6, 182, 212, 0.02) 1px, transparent 2px, transparent 100px);
    }

    /* Top Header Bar */
    .enterprise-header-bar {
        position: relative;
        z-index: 10;
        background: rgba(10, 22, 40, 0.95);
        border: 1px solid rgba(6, 182, 212, 0.25);
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
        backdrop-filter: blur(20px);
    }

    .enterprise-header-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .enterprise-header-brand-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
        box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
    }

    .enterprise-header-brand-text {
        display: flex;
        flex-direction: column;
    }

    .enterprise-header-title {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
    }

    .enterprise-header-subtitle {
        font-size: 0.65rem;
        color: rgba(6, 182, 212, 0.8);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .enterprise-header-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .enterprise-badge {
        padding: 0.25rem 0.6rem;
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .enterprise-badge-admin {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        color: white;
    }

    .enterprise-badge-super {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .enterprise-back-link {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        background: rgba(6, 182, 212, 0.1);
        border: 1px solid rgba(6, 182, 212, 0.25);
        border-radius: 6px;
        color: #22d3ee;
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.2s;
    }

    .enterprise-back-link:hover {
        background: rgba(6, 182, 212, 0.2);
    }

    .enterprise-header-avatar {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: linear-gradient(135deg, #06b6d4, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
    }

    /* Navigation Bar */
    .enterprise-nav {
        position: relative;
        z-index: 100;
        background: rgba(10, 22, 40, 0.95);
        border: 1px solid rgba(6, 182, 212, 0.25);
        border-radius: 12px;
        padding: 0.5rem;
        margin: 0 auto 1.5rem;
        max-width: 1600px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        backdrop-filter: blur(20px);
    }

    .enterprise-nav-main {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex: 1;
    }

    .enterprise-nav-tab {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 0.85rem;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.15s;
        white-space: nowrap;
    }

    .enterprise-nav-tab:hover {
        color: #fff;
        background: rgba(6, 182, 212, 0.15);
    }

    .enterprise-nav-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        box-shadow: 0 2px 10px rgba(6, 182, 212, 0.3);
    }

    .enterprise-nav-tab i {
        font-size: 0.85rem;
    }

    .enterprise-nav-tab .super-only-badge {
        font-size: 0.55rem;
        background: rgba(245, 158, 11, 0.3);
        color: #fbbf24;
        padding: 2px 5px;
        border-radius: 3px;
        margin-left: 4px;
    }

    /* Sub-navigation */
    .enterprise-subnav {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding-left: 1rem;
        margin-left: auto;
        border-left: 1px solid rgba(6, 182, 212, 0.2);
    }

    .enterprise-subnav-tab {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.4rem 0.7rem;
        color: rgba(255,255,255,0.5);
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.15s;
    }

    .enterprise-subnav-tab:hover {
        color: #fff;
        background: rgba(6, 182, 212, 0.1);
    }

    .enterprise-subnav-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #06b6d4, #0891b2);
    }

    .enterprise-subnav-tab i {
        font-size: 0.7rem;
    }

    /* Quick Actions */
    .enterprise-nav-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-left: 1rem;
        border-left: 1px solid rgba(6, 182, 212, 0.2);
    }

    .enterprise-action-btn {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 1px solid rgba(6, 182, 212, 0.25);
        background: transparent;
        color: rgba(255,255,255,0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .enterprise-action-btn:hover {
        color: #fff;
        background: rgba(6, 182, 212, 0.15);
        border-color: rgba(6, 182, 212, 0.4);
    }

    .enterprise-action-btn.primary {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        border: none;
        color: white;
    }

    .enterprise-action-btn.primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4);
    }

    /* Mobile toggle */
    .enterprise-mobile-btn {
        display: none;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        background: rgba(6, 182, 212, 0.1);
        border: 1px solid rgba(6, 182, 212, 0.25);
        color: rgba(255,255,255,0.7);
        cursor: pointer;
        align-items: center;
        justify-content: center;
    }

    /* Content Area */
    .enterprise-content {
        position: relative;
        z-index: 5;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Glass Cards */
    .enterprise-glass-card {
        background: rgba(10, 22, 40, 0.8);
        border: 1px solid rgba(6, 182, 212, 0.15);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 1.5rem;
        backdrop-filter: blur(20px);
    }

    .enterprise-card-header {
        padding: 1.25rem;
        border-bottom: 1px solid rgba(6, 182, 212, 0.1);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .enterprise-card-header-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .enterprise-card-header-icon-cyan {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        color: white;
        box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
    }

    .enterprise-card-header-icon-indigo {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .enterprise-card-header-icon-emerald {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .enterprise-card-header-icon-amber {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .enterprise-card-header-icon-red {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .enterprise-card-header-content {
        flex: 1;
    }

    .enterprise-card-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }

    .enterprise-card-subtitle {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.5);
        margin: 0;
    }

    .enterprise-card-body {
        padding: 1.25rem;
    }

    /* Stats Grid */
    .enterprise-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .enterprise-stat-card {
        background: rgba(10, 22, 40, 0.8);
        border: 1px solid rgba(6, 182, 212, 0.15);
        border-radius: 14px;
        padding: 1.25rem;
        position: relative;
        overflow: hidden;
        transition: all 0.2s;
        text-decoration: none;
        display: block;
    }

    .enterprise-stat-card:hover {
        transform: translateY(-2px);
        border-color: rgba(6, 182, 212, 0.3);
        box-shadow: 0 8px 25px rgba(6, 182, 212, 0.15);
    }

    .enterprise-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--stat-gradient, linear-gradient(135deg, #06b6d4, #0891b2));
    }

    .enterprise-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }

    .enterprise-stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        margin-bottom: 0.25rem;
    }

    .enterprise-stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    /* Page Header */
    .enterprise-page-header {
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(99, 102, 241, 0.05));
        border: 1px solid rgba(6, 182, 212, 0.15);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .enterprise-page-header-content {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .enterprise-page-header-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        box-shadow: 0 4px 20px rgba(6, 182, 212, 0.3);
    }

    .enterprise-page-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #fff;
        margin: 0;
    }

    .enterprise-page-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.5);
        margin: 0;
    }

    /* Buttons */
    .enterprise-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        border: none;
        font-family: inherit;
    }

    .enterprise-btn-primary {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        color: white;
        box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
    }

    .enterprise-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4);
    }

    .enterprise-btn-secondary {
        background: rgba(6, 182, 212, 0.1);
        border: 1px solid rgba(6, 182, 212, 0.25);
        color: #22d3ee;
    }

    .enterprise-btn-secondary:hover {
        background: rgba(6, 182, 212, 0.2);
    }

    .enterprise-btn-danger {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #f87171;
    }

    .enterprise-btn-danger:hover {
        background: rgba(239, 68, 68, 0.25);
    }

    .enterprise-btn-sm {
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
    }

    /* Tables */
    .enterprise-table-wrapper {
        overflow-x: auto;
    }

    .enterprise-table {
        width: 100%;
        border-collapse: collapse;
    }

    .enterprise-table th {
        text-align: left;
        padding: 0.85rem 1.25rem;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: rgba(255,255,255,0.5);
        border-bottom: 1px solid rgba(6, 182, 212, 0.15);
        background: rgba(6, 182, 212, 0.05);
    }

    .enterprise-table td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(6, 182, 212, 0.08);
        color: rgba(255,255,255,0.9);
        font-size: 0.9rem;
    }

    .enterprise-table tbody tr:hover {
        background: rgba(6, 182, 212, 0.05);
    }

    /* Status Badges */
    .enterprise-status {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.65rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .enterprise-status-success {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
    }

    .enterprise-status-warning {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
    }

    .enterprise-status-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
    }

    .enterprise-status-info {
        background: rgba(6, 182, 212, 0.15);
        color: #22d3ee;
    }

    .enterprise-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    /* Form Elements */
    .enterprise-form-group {
        margin-bottom: 1rem;
    }

    .enterprise-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: rgba(255,255,255,0.7);
        margin-bottom: 0.4rem;
    }

    .enterprise-input,
    .enterprise-textarea,
    .enterprise-select {
        width: 100%;
        padding: 0.65rem 0.9rem;
        background: rgba(10, 22, 40, 0.6);
        border: 1px solid rgba(6, 182, 212, 0.2);
        border-radius: 8px;
        color: #fff;
        font-size: 0.9rem;
        font-family: inherit;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .enterprise-input:focus,
    .enterprise-textarea:focus,
    .enterprise-select:focus {
        outline: none;
        border-color: rgba(6, 182, 212, 0.5);
        box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
    }

    .enterprise-input::placeholder {
        color: rgba(255,255,255,0.3);
    }

    /* Two Column Layout */
    .enterprise-two-col {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 1.5rem;
    }

    /* Alert Banners */
    .enterprise-alert {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }

    .enterprise-alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-left: 4px solid #ef4444;
    }

    .enterprise-alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-left: 4px solid #f59e0b;
    }

    .enterprise-alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-left: 4px solid #10b981;
    }

    .enterprise-alert-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .enterprise-alert-danger .enterprise-alert-icon {
        background: rgba(239, 68, 68, 0.2);
        color: #f87171;
    }

    .enterprise-alert-content {
        flex: 1;
    }

    .enterprise-alert-title {
        font-weight: 700;
        color: #fff;
        margin-bottom: 2px;
    }

    .enterprise-alert-message {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.6);
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .enterprise-two-col {
            grid-template-columns: 1fr;
        }

        .enterprise-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .enterprise-mobile-btn {
            display: flex;
        }

        .enterprise-nav-main,
        .enterprise-subnav {
            display: none;
        }

        .enterprise-nav.open .enterprise-nav-main {
            display: flex;
            flex-direction: column;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(10, 22, 40, 0.98);
            border: 1px solid rgba(6, 182, 212, 0.25);
            border-radius: 12px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            z-index: 200;
        }

        .enterprise-nav.open .enterprise-subnav {
            display: flex;
            flex-wrap: wrap;
            border-left: none;
            border-top: 1px solid rgba(6, 182, 212, 0.2);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            padding-left: 0;
        }

        .enterprise-header-brand-text {
            display: none;
        }

        .enterprise-back-link span {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .enterprise-stats-grid {
            grid-template-columns: 1fr;
        }

        .enterprise-page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 600px) {
        .enterprise-wrapper {
            padding: 0.5rem;
        }

        .enterprise-header-bar,
        .enterprise-nav {
            padding: 0.5rem;
        }

        .enterprise-glass-card {
            border-radius: 12px;
        }

        .enterprise-card-body {
            padding: 1rem;
        }
    }
    </style>
</head>
<body>
<div class="enterprise-wrapper">
    <div class="enterprise-bg-effects"></div>
    <div class="enterprise-grid-overlay"></div>

    <!-- Top Header Bar -->
    <div class="enterprise-header-bar">
        <div class="enterprise-header-brand">
            <div class="enterprise-header-brand-icon">
                <i class="fa-solid fa-building-shield"></i>
            </div>
            <div class="enterprise-header-brand-text">
                <span class="enterprise-header-title">Enterprise Console</span>
                <span class="enterprise-header-subtitle"><?= htmlspecialchars($enterprisePageSubtitle) ?></span>
            </div>
        </div>
        <div class="enterprise-header-actions">
            <span class="enterprise-badge <?= $isSuperAdmin ? 'enterprise-badge-super' : 'enterprise-badge-admin' ?>">
                <?= $isSuperAdmin ? 'Super Admin' : 'Admin' ?>
            </span>
            <a href="<?= $basePath ?>/admin-legacy" class="enterprise-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Admin</span>
            </a>
            <div class="enterprise-header-avatar"><?= htmlspecialchars($userInitials) ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav role="navigation" aria-label="Main navigation" class="enterprise-nav" id="enterpriseNav">
        <div class="enterprise-nav-main">
            <?php foreach ($filteredNav as $sectionKey => $section): ?>
                <a href="<?= $basePath . $section['url'] ?>"
                   class="enterprise-nav-tab <?= $enterpriseSection === $sectionKey ? 'active' : '' ?>">
                    <i class="fa-solid <?= $section['icon'] ?>"></i>
                    <span><?= $section['label'] ?></span>
                    <?php if (!empty($section['superAdminOnly'])): ?>
                        <span class="super-only-badge">SA</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($filteredNav[$enterpriseSection]['items'])): ?>
        <div class="enterprise-subnav">
            <?php foreach ($filteredNav[$enterpriseSection]['items'] as $item): ?>
                <a href="<?= $basePath . $item['url'] ?>"
                   class="enterprise-subnav-tab <?= $enterpriseSubpage === $item['id'] ? 'active' : '' ?>">
                    <i class="fa-solid <?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="enterprise-nav-actions">
            <?php if ($isSuperAdmin): ?>
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/create" class="enterprise-action-btn primary" title="New GDPR Request">
                <i class="fa-solid fa-plus"></i>
            </a>
            <?php endif; ?>
            <button type="button" class="enterprise-action-btn" onclick="location.reload()" title="Refresh">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>

        <button type="button" class="enterprise-mobile-btn" id="enterpriseMobileBtn">
            <i class="fa-solid fa-bars"></i>
        </button>
    </nav>

    <!-- Content -->
    <div class="enterprise-content">

<script>
// Mobile menu
document.getElementById('enterpriseMobileBtn')?.addEventListener('click', function() {
    document.getElementById('enterpriseNav').classList.toggle('open');
    this.querySelector('i').classList.toggle('fa-bars');
    this.querySelector('i').classList.toggle('fa-times');
});
</script>
