<?php
/**
 * Enterprise Navigation Component - Gold Standard v2.0
 * Smart Tab Navigation for Enterprise Admin Interface
 *
 * Usage: Include at the top of enterprise pages after header
 * Required: $currentSection (e.g., 'dashboard', 'gdpr', 'monitoring', 'config')
 * Optional: $currentPage (e.g., 'requests', 'breaches', 'logs')
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$currentSection = $currentSection ?? 'dashboard';
$currentPage = $currentPage ?? '';

// Navigation structure
$navSections = [
    'dashboard' => [
        'label' => 'Overview',
        'icon' => 'fa-gauge-high',
        'url' => '/admin-legacy/enterprise',
        'pages' => []
    ],
    'roles' => [
        'label' => 'Access',
        'icon' => 'fa-user-shield',
        'url' => '/admin-legacy/enterprise/roles',
        'pages' => [
            'dashboard' => ['label' => 'Roles', 'icon' => 'fa-user-tag', 'url' => '/admin-legacy/enterprise/roles'],
            'permissions' => ['label' => 'Permissions', 'icon' => 'fa-key', 'url' => '/admin-legacy/enterprise/permissions'],
            'audit' => ['label' => 'Audit', 'icon' => 'fa-clipboard-list', 'url' => '/admin-legacy/enterprise/audit/permissions'],
        ]
    ],
    'gdpr' => [
        'label' => 'GDPR',
        'icon' => 'fa-shield-halved',
        'url' => '/admin-legacy/enterprise/gdpr',
        'pages' => [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-gauge', 'url' => '/admin-legacy/enterprise/gdpr'],
            'requests' => ['label' => 'Requests', 'icon' => 'fa-inbox', 'url' => '/admin-legacy/enterprise/gdpr/requests'],
            'breaches' => ['label' => 'Breaches', 'icon' => 'fa-triangle-exclamation', 'url' => '/admin-legacy/enterprise/gdpr/breaches'],
            'consents' => ['label' => 'Consents', 'icon' => 'fa-clipboard-check', 'url' => '/admin-legacy/enterprise/gdpr/consents'],
            'audit' => ['label' => 'Audit Log', 'icon' => 'fa-clock-rotate-left', 'url' => '/admin-legacy/enterprise/gdpr/audit'],
        ]
    ],
    'monitoring' => [
        'label' => 'Monitoring',
        'icon' => 'fa-chart-line',
        'url' => '/admin-legacy/enterprise/monitoring',
        'pages' => [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-gauge', 'url' => '/admin-legacy/enterprise/monitoring'],
            'health' => ['label' => 'Health', 'icon' => 'fa-heartbeat', 'url' => '/admin-legacy/enterprise/monitoring/health'],
            'requirements' => ['label' => 'Requirements', 'icon' => 'fa-list-check', 'url' => '/admin-legacy/enterprise/monitoring/requirements'],
            'logs' => ['label' => 'Logs', 'icon' => 'fa-file-lines', 'url' => '/admin-legacy/enterprise/monitoring/logs'],
        ]
    ],
    'config' => [
        'label' => 'Config',
        'icon' => 'fa-sliders',
        'url' => '/admin-legacy/enterprise/config',
        'pages' => [
            'dashboard' => ['label' => 'Settings', 'icon' => 'fa-gear', 'url' => '/admin-legacy/enterprise/config'],
            'secrets' => ['label' => 'Secrets', 'icon' => 'fa-vault', 'url' => '/admin-legacy/enterprise/config/secrets'],
        ]
    ],
];
?>

<style>
/* Enterprise Navigation - Dark Mode Optimized */
.enterprise-nav {
    background: var(--nav-bg, rgba(30, 41, 59, 0.95));
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--nav-border, rgba(99, 102, 241, 0.25));
    border-radius: 16px;
    margin-bottom: 24px;
    padding: 4px;
    position: relative;
    z-index: 90;
}

[data-theme="light"] .enterprise-nav {
    --nav-bg: rgba(255, 255, 255, 0.95);
    --nav-border: rgba(99, 102, 241, 0.15);
    --nav-text: #1e293b;
    --nav-text-muted: #64748b;
    --nav-active-bg: rgba(99, 102, 241, 0.1);
}

.enterprise-nav-inner {
    display: flex;
    align-items: center;
    padding: 0 8px;
    gap: 8px;
}

/* Primary Tabs */
.enterprise-nav-tabs {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
}

.enterprise-nav-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    color: var(--nav-text-muted, #94a3b8);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 12px;
    transition: all 0.2s;
    position: relative;
}

.enterprise-nav-tab:hover {
    color: var(--nav-text, #f1f5f9);
    background: var(--nav-active-bg, rgba(99, 102, 241, 0.15));
}

.enterprise-nav-tab.active {
    color: white;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);
}

.enterprise-nav-tab i {
    font-size: 1rem;
}

/* Secondary Navigation (Sub-tabs) */
.enterprise-subnav {
    display: flex;
    align-items: center;
    gap: 4px;
    padding-left: 24px;
    border-left: 1px solid var(--nav-border, rgba(99, 102, 241, 0.2));
    margin-left: auto;
}

.enterprise-subnav-tab {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    color: var(--nav-text-muted, #94a3b8);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s;
}

.enterprise-subnav-tab:hover {
    color: var(--nav-text, #f1f5f9);
    background: var(--nav-active-bg, rgba(99, 102, 241, 0.1));
}

.enterprise-subnav-tab.active {
    color: white;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.enterprise-subnav-tab i {
    font-size: 0.75rem;
}

/* Quick Actions */
.enterprise-nav-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-left: 16px;
    margin-left: 16px;
    border-left: 1px solid var(--nav-border, rgba(99, 102, 241, 0.2));
}

.nav-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    color: var(--nav-text-muted, #94a3b8);
    background: transparent;
    border: 1px solid var(--nav-border, rgba(99, 102, 241, 0.2));
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.nav-action-btn:hover {
    color: var(--nav-text, #f1f5f9);
    background: var(--nav-active-bg, rgba(99, 102, 241, 0.1));
    border-color: rgba(99, 102, 241, 0.4);
}

.nav-action-btn.primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
}

.nav-action-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}

/* Breadcrumb indicator */
.nav-breadcrumb {
    display: none;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--nav-text-muted, #94a3b8);
    padding: 0 16px;
}

.nav-breadcrumb i {
    font-size: 0.625rem;
}

/* Mobile Responsive */
@media (max-width: 1024px) {
    .enterprise-nav-inner {
        padding: 0 16px;
        flex-wrap: wrap;
    }

    .enterprise-nav-tab span {
        display: none;
    }

    .enterprise-nav-tab {
        padding: 12px 16px;
    }

    .enterprise-subnav {
        order: 3;
        width: 100%;
        border-left: none;
        border-top: 1px solid var(--nav-border, rgba(99, 102, 241, 0.2));
        padding: 8px 0;
        margin: 0;
        justify-content: flex-start;
        overflow-x: auto;
    }

    .enterprise-nav-actions {
        border-left: none;
        padding-left: 0;
        margin-left: auto;
    }
}

@media (max-width: 640px) {
    .enterprise-subnav-tab span {
        display: none;
    }

    .enterprise-subnav-tab {
        padding: 8px 12px;
    }
}

/* Notification Badge */
.nav-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 0.65rem;
    font-weight: 700;
    color: white;
    background: #ef4444;
    border-radius: 9px;
    margin-left: 6px;
}

.nav-badge.warning {
    background: #f59e0b;
}

.nav-badge.success {
    background: #10b981;
}
</style>

<nav role="navigation" aria-label="Main navigation" class="enterprise-nav">
    <div class="enterprise-nav-inner">
        <!-- Primary Navigation Tabs -->
        <div class="enterprise-nav-tabs">
            <?php foreach ($navSections as $sectionKey => $section): ?>
                <a href="<?= $basePath ?><?= $section['url'] ?>"
                   class="enterprise-nav-tab <?= $currentSection === $sectionKey ? 'active' : '' ?>">
                    <i class="fa-solid <?= $section['icon'] ?>"></i>
                    <span><?= $section['label'] ?></span>
                    <?php if ($sectionKey === 'gdpr' && !empty($gdprAlerts)): ?>
                        <span class="nav-badge"><?= $gdprAlerts ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Secondary Navigation (Context-specific) -->
        <?php if (!empty($navSections[$currentSection]['pages'])): ?>
            <div class="enterprise-subnav">
                <?php foreach ($navSections[$currentSection]['pages'] as $pageKey => $page): ?>
                    <a href="<?= $basePath ?><?= $page['url'] ?>"
                       class="enterprise-subnav-tab <?= $currentPage === $pageKey ? 'active' : '' ?>">
                        <i class="fa-solid <?= $page['icon'] ?>"></i>
                        <span><?= $page['label'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="enterprise-nav-actions">
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches/report" class="nav-action-btn primary" title="Report Breach">
                <i class="fa-solid fa-plus"></i>
            </a>
            <button type="button" class="nav-action-btn" onclick="refreshPage()" title="Refresh">
                <i class="fa-solid fa-rotate"></i>
            </button>
            <a href="<?= $basePath ?>/admin-legacy" class="nav-action-btn" title="Back to Admin">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>
    </div>
</nav>


<script>
function refreshPage() {
    window.location.reload();
}
</script>
