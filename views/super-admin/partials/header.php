<?php
/**
 * Super Admin Panel Header
 * Distinct from Platform Admin - Infrastructure Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;
use Nexus\Middleware\SuperPanelAccess;

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPathClean = strtok($currentPath, '?');
$currentUser = $_SESSION['user_name'] ?? 'Super Admin';
$userInitials = strtoupper(substr($currentUser, 0, 2));

$pageTitle = $pageTitle ?? 'Super Admin';
$access = $access ?? SuperPanelAccess::getAccess();

// Determine scope badge
$scopeBadge = $access['level'] === 'master' ? 'MASTER' : 'REGIONAL';
$scopeColor = $access['level'] === 'master' ? '#dc2626' : '#2563eb';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> - Super Admin Panel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --super-primary: #7c3aed;
            --super-primary-dark: #6d28d9;
            --super-secondary: #1e293b;
            --super-accent: #f59e0b;
            --super-success: #10b981;
            --super-danger: #ef4444;
            --super-warning: #f59e0b;
            --super-info: #3b82f6;
            --super-bg: #0f172a;
            --super-surface: #1e293b;
            --super-border: #334155;
            --super-text: #f1f5f9;
            --super-text-muted: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--super-bg);
            color: var(--super-text);
            min-height: 100vh;
        }

        /* Top Navigation */
        .super-topnav {
            background: linear-gradient(135deg, var(--super-secondary) 0%, #0f172a 100%);
            border-bottom: 1px solid var(--super-border);
            padding: 0 1.5rem;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .super-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--super-text);
        }

        .super-brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--super-primary) 0%, var(--super-primary-dark) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .super-brand-text {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .super-brand-badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-left: 0.5rem;
        }

        .super-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .super-nav-link {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            color: var(--super-text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .super-nav-link:hover {
            background: rgba(124, 58, 237, 0.1);
            color: var(--super-text);
        }

        .super-nav-link.active {
            background: var(--super-primary);
            color: white;
        }

        /* Dropdown Navigation */
        .super-nav-dropdown {
            position: relative;
        }

        .super-nav-dropdown-toggle {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            color: var(--super-text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            background: transparent;
            border: none;
        }

        .super-nav-dropdown-toggle:hover {
            background: rgba(124, 58, 237, 0.1);
            color: var(--super-text);
        }

        .super-nav-dropdown-toggle.active {
            background: var(--super-primary);
            color: white;
        }

        .super-nav-dropdown-toggle .chevron {
            font-size: 0.6rem;
            transition: transform 0.2s;
        }

        .super-nav-dropdown:hover .chevron {
            transform: rotate(180deg);
        }

        .super-nav-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: var(--super-surface);
            border: 1px solid var(--super-border);
            border-radius: 8px;
            padding: 0.5rem;
            margin-top: 0.25rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.2s ease;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .super-nav-dropdown:hover .super-nav-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .super-nav-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 0.75rem;
            color: var(--super-text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: all 0.15s;
        }

        .super-nav-dropdown-menu a:hover {
            background: rgba(124, 58, 237, 0.15);
            color: var(--super-text);
        }

        .super-nav-dropdown-menu a.active {
            background: rgba(124, 58, 237, 0.2);
            color: var(--super-primary);
        }

        .super-nav-dropdown-menu a i {
            width: 16px;
            text-align: center;
            font-size: 0.8rem;
        }

        .super-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .super-scope-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-weight: 600;
            letter-spacing: 0.05em;
            background: <?= $scopeColor ?>;
            color: white;
        }

        .super-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--super-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .super-user-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            color: var(--super-text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .super-user-menu:hover {
            background: rgba(255,255,255,0.05);
            color: var(--super-text);
        }

        /* Main Content */
        .super-main {
            padding: 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Page Header */
        .super-page-header {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .super-page-title {
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .super-page-title i {
            color: var(--super-primary);
        }

        .super-page-subtitle {
            color: var(--super-text-muted);
            margin-top: 0.25rem;
            font-size: 0.9rem;
        }

        .super-page-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Buttons */
        .super-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .super-btn-primary {
            background: var(--super-primary);
            color: white;
        }

        .super-btn-primary:hover {
            background: var(--super-primary-dark);
        }

        .super-btn-secondary {
            background: var(--super-surface);
            color: var(--super-text);
            border: 1px solid var(--super-border);
        }

        .super-btn-secondary:hover {
            background: var(--super-border);
        }

        .super-btn-success {
            background: var(--super-success);
            color: white;
        }

        .super-btn-danger {
            background: var(--super-danger);
            color: white;
        }

        .super-btn-warning {
            background: var(--super-warning);
            color: #1e293b;
        }

        .super-btn-warning:hover {
            background: #d97706;
        }

        .super-btn-info {
            background: var(--super-info);
            color: white;
        }

        .super-btn-sm {
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
        }

        /* Cards */
        .super-card {
            background: var(--super-surface);
            border: 1px solid var(--super-border);
            border-radius: 12px;
            overflow: hidden;
        }

        .super-card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--super-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .super-card-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .super-card-body {
            padding: 1.25rem;
        }

        /* Stats Grid */
        .super-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .super-stat-card {
            background: var(--super-surface);
            border: 1px solid var(--super-border);
            border-radius: 10px;
            padding: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .super-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .super-stat-icon.purple { background: rgba(124, 58, 237, 0.15); color: var(--super-primary); }
        .super-stat-icon.green { background: rgba(16, 185, 129, 0.15); color: var(--super-success); }
        .super-stat-icon.blue { background: rgba(59, 130, 246, 0.15); color: var(--super-info); }
        .super-stat-icon.amber { background: rgba(245, 158, 11, 0.15); color: var(--super-warning); }

        .super-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1;
        }

        .super-stat-label {
            color: var(--super-text-muted);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        /* Table */
        .super-table {
            width: 100%;
            border-collapse: collapse;
        }

        .super-table th,
        .super-table td {
            padding: 0.875rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--super-border);
        }

        .super-table th {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--super-text-muted);
            background: rgba(0,0,0,0.2);
        }

        .super-table tr:hover td {
            background: rgba(124, 58, 237, 0.05);
        }

        .super-table-link {
            color: var(--super-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .super-table-link:hover {
            text-decoration: underline;
        }

        /* Badges */
        .super-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .super-badge-success { background: rgba(16, 185, 129, 0.15); color: var(--super-success); }
        .super-badge-warning { background: rgba(245, 158, 11, 0.15); color: var(--super-warning); }
        .super-badge-danger { background: rgba(239, 68, 68, 0.15); color: var(--super-danger); }
        .super-badge-info { background: rgba(59, 130, 246, 0.15); color: var(--super-info); }
        .super-badge-purple { background: rgba(124, 58, 237, 0.15); color: var(--super-primary); }

        /* Forms */
        .super-form-group {
            margin-bottom: 1rem;
        }

        .super-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--super-text);
        }

        .super-input,
        .super-select,
        .super-textarea {
            width: 100%;
            padding: 0.6rem 0.875rem;
            background: var(--super-bg);
            border: 1px solid var(--super-border);
            border-radius: 6px;
            color: var(--super-text);
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .super-input:focus,
        .super-select:focus,
        .super-textarea:focus {
            outline: none;
            border-color: var(--super-primary);
        }

        .super-checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .super-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--super-primary);
        }

        /* Form aliases for consistency */
        .super-form-group {
            margin-bottom: 1rem;
        }

        .super-form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--super-text);
        }

        .super-form-input,
        .super-form-select,
        .super-form-textarea {
            width: 100%;
            padding: 0.6rem 0.875rem;
            background: var(--super-bg);
            border: 1px solid var(--super-border);
            border-radius: 6px;
            color: var(--super-text);
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .super-form-input:focus,
        .super-form-select:focus,
        .super-form-textarea:focus {
            outline: none;
            border-color: var(--super-primary);
        }

        .super-form-help {
            font-size: 0.75rem;
            color: var(--super-text-muted);
            margin-top: 0.25rem;
        }

        .super-form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .super-form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--super-primary);
        }

        /* Badge variants */
        .super-badge-secondary {
            background: rgba(148, 163, 184, 0.15);
            color: var(--super-text-muted);
        }

        /* Alert danger alias */
        .super-alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--super-danger);
        }

        /* Alerts */
        .super-alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .super-alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--super-success);
        }

        .super-alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--super-danger);
        }

        /* Breadcrumb */
        .super-breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--super-text-muted);
            margin-bottom: 1rem;
        }

        .super-breadcrumb a {
            color: var(--super-text-muted);
            text-decoration: none;
        }

        .super-breadcrumb a:hover {
            color: var(--super-primary);
        }

        .super-breadcrumb-sep {
            color: var(--super-border);
        }

        /* Hierarchy Tree */
        .super-tree {
            list-style: none;
        }

        .super-tree-item {
            padding: 0.5rem 0;
        }

        .super-tree-item-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .super-tree-item-content:hover {
            background: rgba(124, 58, 237, 0.1);
        }

        .super-tree-children {
            margin-left: 1.5rem;
            border-left: 2px solid var(--super-border);
            padding-left: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .super-topnav {
                padding: 0 1rem;
            }

            .super-nav {
                display: none;
            }

            .super-main {
                padding: 1rem;
            }

            .super-page-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="super-topnav">
        <a href="<?= $basePath ?>/super-admin" class="super-brand">
            <div class="super-brand-icon">
                <i class="fa-solid fa-crown"></i>
            </div>
            <span class="super-brand-text">Super Admin</span>
        </a>

        <div class="super-nav">
            <a href="<?= $basePath ?>/super-admin" class="super-nav-link <?= $currentPathClean === $basePath . '/super-admin' || $currentPathClean === $basePath . '/super-admin/dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                Dashboard
            </a>
            <a href="<?= $basePath ?>/super-admin/tenants" class="super-nav-link <?= str_starts_with($currentPathClean, $basePath . '/super-admin/tenants') && $currentPathClean !== $basePath . '/super-admin/tenants/hierarchy' ? 'active' : '' ?>">
                <i class="fa-solid fa-building"></i>
                Tenants
            </a>
            <a href="<?= $basePath ?>/super-admin/tenants/hierarchy" class="super-nav-link <?= $currentPathClean === $basePath . '/super-admin/tenants/hierarchy' ? 'active' : '' ?>">
                <i class="fa-solid fa-sitemap"></i>
                Hierarchy
            </a>
            <a href="<?= $basePath ?>/super-admin/users" class="super-nav-link <?= str_starts_with($currentPathClean, $basePath . '/super-admin/users') ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i>
                Users
            </a>
            <a href="<?= $basePath ?>/super-admin/bulk" class="super-nav-link <?= str_starts_with($currentPathClean, $basePath . '/super-admin/bulk') ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group"></i>
                Bulk Ops
            </a>
            <a href="<?= $basePath ?>/super-admin/audit" class="super-nav-link <?= str_starts_with($currentPathClean, $basePath . '/super-admin/audit') ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-list"></i>
                Audit Log
            </a>
            <div class="super-nav-dropdown">
                <button class="super-nav-dropdown-toggle <?= str_starts_with($currentPathClean, $basePath . '/super-admin/federation') ? 'active' : '' ?>">
                    <i class="fa-solid fa-globe"></i>
                    Partner Timebanks
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </button>
                <div class="super-nav-dropdown-menu">
                    <a href="<?= $basePath ?>/super-admin/federation" class="<?= $currentPathClean === $basePath . '/super-admin/federation' ? 'active' : '' ?>">
                        <i class="fa-solid fa-gauge-high"></i>
                        Dashboard
                    </a>
                    <a href="<?= $basePath ?>/super-admin/federation/system-controls" class="<?= $currentPathClean === $basePath . '/super-admin/federation/system-controls' ? 'active' : '' ?>">
                        <i class="fa-solid fa-sliders"></i>
                        System Controls
                    </a>
                    <a href="<?= $basePath ?>/super-admin/federation/whitelist" class="<?= $currentPathClean === $basePath . '/super-admin/federation/whitelist' ? 'active' : '' ?>">
                        <i class="fa-solid fa-shield-check"></i>
                        Whitelist
                    </a>
                    <a href="<?= $basePath ?>/super-admin/federation/partnerships" class="<?= $currentPathClean === $basePath . '/super-admin/federation/partnerships' ? 'active' : '' ?>">
                        <i class="fa-solid fa-handshake"></i>
                        Partnerships
                    </a>
                    <a href="<?= $basePath ?>/super-admin/federation/audit" class="<?= $currentPathClean === $basePath . '/super-admin/federation/audit' ? 'active' : '' ?>">
                        <i class="fa-solid fa-clipboard-list"></i>
                        Federation Audit
                    </a>
                </div>
            </div>
        </div>

        <div class="super-user">
            <span class="super-scope-badge"><?= $scopeBadge ?></span>
            <a href="<?= $basePath ?>/admin-legacy" class="super-user-menu" title="Back to Platform Admin">
                <i class="fa-solid fa-arrow-left"></i>
                Platform Admin
            </a>
            <div class="super-user-avatar"><?= htmlspecialchars($userInitials) ?></div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="super-main">
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="super-alert super-alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="super-alert super-alert-error">
                <i class="fa-solid fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
