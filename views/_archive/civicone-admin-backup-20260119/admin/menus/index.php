<?php
/**
 * Admin Menu Manager - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Menus';
$adminPageSubtitle = 'Navigation';
$adminPageIcon = 'fa-bars';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Check menu manager status
$menuManagerConfig = \Nexus\Core\MenuManager::getConfig();
$isEnabled = \Nexus\Core\MenuManager::isEnabled();
?>

<?php if ($isEnabled && !empty($menuManagerConfig['show_warning'])): ?>
<!-- DEVELOPMENT WARNING BANNER -->
<div class="admin-alert admin-alert-danger" style="margin-bottom: 1.5rem;">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-exclamation-triangle"></i>
    </div>
    <div class="admin-alert-content">
        <strong>⚠️ MENU MANAGER IS UNDER DEVELOPMENT - UNSTABLE</strong>
        <p style="margin: 0.5rem 0 0;">
            <strong>Status:</strong> <?= htmlspecialchars($menuManagerConfig['status'] ?? 'EXPERIMENTAL') ?> |
            <strong>Version:</strong> <?= htmlspecialchars($menuManagerConfig['version'] ?? '0.1.0-alpha') ?>
        </p>
        <details style="margin-top: 0.75rem;">
            <summary style="cursor: pointer; font-weight: 600;">Known Issues (Click to expand)</summary>
            <ul style="margin: 0.5rem 0 0 1.5rem; line-height: 1.8;">
                <?php foreach ($menuManagerConfig['known_issues'] ?? [] as $issue): ?>
                    <li><?= htmlspecialchars($issue) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <div style="margin-top: 1rem; display: flex; gap: 1rem;">
            <a href="<?= $basePath ?>/admin-legacy/menus?enable_menu_manager=0" class="admin-btn admin-btn-danger admin-btn-sm">
                <i class="fa-solid fa-power-off"></i> Disable Menu Manager (Use Original Navigation)
            </a>
            <a href="<?= $basePath ?>/admin" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to Admin
            </a>
        </div>
    </div>
</div>
<?php elseif (!$isEnabled): ?>
<!-- MENU MANAGER DISABLED INFO -->
<div class="admin-alert admin-alert-info" style="margin-bottom: 1.5rem;">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-info-circle"></i>
    </div>
    <div class="admin-alert-content">
        <strong>Menu Manager is Currently Disabled</strong>
        <p style="margin: 0.5rem 0 0;">
            The site is using the original stable navigation system.
            The Menu Manager is under development and not recommended for production use.
        </p>
        <?php if (!empty($menuManagerConfig['allow_admin_override'])): ?>
        <div style="margin-top: 1rem;">
            <a href="<?= $basePath ?>/admin-legacy/menus?enable_menu_manager=1" class="admin-btn admin-btn-warning admin-btn-sm">
                <i class="fa-solid fa-flask"></i> Enable Menu Manager (Experimental - Testing Only)
            </a>
            <span style="margin-left: 1rem; color: #f59e0b;">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <strong>Warning:</strong> This is experimental and may break navigation
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="page-hero-content">
        <div class="page-hero-icon">
            <i class="fa-solid fa-bars"></i>
        </div>
        <div class="page-hero-text">
            <h1>Menu Manager</h1>
            <p>Create and manage navigation menus across different layouts</p>
        </div>
    </div>
    <div class="page-hero-actions">
        <?php if ($can_create_more['allowed']): ?>
            <a href="<?= $basePath ?>/admin-legacy/menus/create" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-plus"></i> New Menu
            </a>
        <?php else: ?>
            <button class="admin-btn admin-btn-disabled" disabled title="<?= htmlspecialchars($can_create_more['reason']) ?>">
                <i class="fa-solid fa-lock"></i> Upgrade to Create More
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Plan Status Card -->
<?php if (isset($plan_status['warning']) || isset($plan_status['error'])): ?>
<div class="admin-alert <?= isset($plan_status['error']) ? 'admin-alert-danger' : 'admin-alert-warning' ?>">
    <div class="admin-alert-icon">
        <i class="fa-solid <?= isset($plan_status['error']) ? 'fa-exclamation-circle' : 'fa-exclamation-triangle' ?>"></i>
    </div>
    <div class="admin-alert-content">
        <strong><?= isset($plan_status['error']) ? 'Plan Expired' : 'Plan Warning' ?></strong>
        <p><?= htmlspecialchars($plan_status['message'] ?? '') ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Plan Info Card -->
<div class="admin-glass-card" style="margin-bottom: 2rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-blue">
            <i class="fa-solid fa-crown"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Current Plan: <?= htmlspecialchars($plan_status['plan_name'] ?? 'None') ?></h3>
            <p class="admin-card-subtitle">
                Menus: <?= $can_create_more['current_count'] ?? 0 ?> / <?= $can_create_more['max_allowed'] ?? 1 ?>
                <?php if ($can_create_more['allowed']): ?>
                    <span style="color: var(--success-color);">
                        (<?= $can_create_more['remaining'] ?? 0 ?> remaining)
                    </span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="plan-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="plan-feature">
                <strong>Allowed Layouts:</strong>
                <p><?= implode(', ', $plan_status['allowed_layouts'] ?? ['modern']) ?></p>
            </div>
            <div class="plan-feature">
                <strong>Max Menu Items:</strong>
                <p><?= $plan_status['limits']['max_menu_items'] ?? 10 ?> per menu</p>
            </div>
            <?php if (!empty($plan_status['expires_at'])): ?>
            <div class="plan-feature">
                <strong>Expires:</strong>
                <p><?= date('M j, Y', strtotime($plan_status['expires_at'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Menus List -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Your Menus</h3>
            <p class="admin-card-subtitle">Click to edit menu items and settings</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($menus)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-bars"></i>
            </div>
            <h3 class="admin-empty-title">No Menus Yet</h3>
            <p class="admin-empty-text">Create your first navigation menu to get started.</p>
            <?php if ($can_create_more['allowed']): ?>
            <a href="<?= $basePath ?>/admin-legacy/menus/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i> Create First Menu
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="menus-list">
            <?php foreach ($menus as $menu): ?>
            <div class="menu-row" data-menu-id="<?= $menu['id'] ?>">
                <div class="menu-info">
                    <div class="menu-title-row">
                        <span class="menu-title">
                            <span class="menu-title-icon">
                                <i class="fa-solid fa-bars"></i>
                            </span>
                            <?= htmlspecialchars($menu['name']) ?>
                        </span>
                        <?php if ($menu['is_active']): ?>
                            <span class="menu-status menu-status-active">
                                <i class="fa-solid fa-check-circle"></i> Active
                            </span>
                        <?php else: ?>
                            <span class="menu-status menu-status-inactive">
                                <i class="fa-solid fa-circle-xmark"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="menu-meta">
                        <span class="menu-meta-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <strong>Location:</strong> <?= htmlspecialchars($menu['location']) ?>
                        </span>
                        <?php if ($menu['layout']): ?>
                        <span class="menu-meta-item">
                            <i class="fa-solid fa-palette"></i>
                            <strong>Layout:</strong> <?= htmlspecialchars($menu['layout']) ?>
                        </span>
                        <?php else: ?>
                        <span class="menu-meta-item">
                            <i class="fa-solid fa-palette"></i>
                            <strong>Layout:</strong> All layouts
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($menu['description'])): ?>
                        <span class="menu-meta-item">
                            <i class="fa-solid fa-info-circle"></i>
                            <?= htmlspecialchars($menu['description']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="menu-actions">
                    <a href="<?= $basePath ?>/admin-legacy/menus/builder/<?= $menu['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                        <i class="fa-solid fa-edit"></i> Edit
                    </a>
                    <?php if ($menu['is_active']): ?>
                        <button class="admin-btn admin-btn-warning admin-btn-sm" onclick="toggleMenuStatus(<?= $menu['id'] ?>, '<?= htmlspecialchars($menu['name']) ?>', false)">
                            <i class="fa-solid fa-eye-slash"></i> Deactivate
                        </button>
                    <?php else: ?>
                        <button class="admin-btn admin-btn-success admin-btn-sm" onclick="toggleMenuStatus(<?= $menu['id'] ?>, '<?= htmlspecialchars($menu['name']) ?>', true)">
                            <i class="fa-solid fa-eye"></i> Activate
                        </button>
                    <?php endif; ?>
                    <button class="admin-btn admin-btn-danger admin-btn-sm" onclick="deleteMenu(<?= $menu['id'] ?>, '<?= htmlspecialchars($menu['name']) ?>')">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Page Hero */
.page-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2rem;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}

.page-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
    animation: heroFloat 15s ease-in-out infinite;
}

@keyframes heroFloat {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-30px, 30px); }
}

.page-hero-content {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    position: relative;
    z-index: 1;
}

.page-hero-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.35);
}

.page-hero-text h1 {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.25rem;
}

.page-hero-text p {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.6);
    margin: 0;
}

.page-hero-actions {
    position: relative;
    z-index: 1;
}

/* Stats Summary */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-pill {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 14px;
    position: relative;
    overflow: hidden;
}

.stat-pill::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color, #6366f1), transparent);
}

.stat-pill.blue { --stat-color: #3b82f6; }
.stat-pill.green { --stat-color: #22c55e; }
.stat-pill.purple { --stat-color: #8b5cf6; }

.stat-pill-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--stat-color, #6366f1), color-mix(in srgb, var(--stat-color, #6366f1) 70%, #000));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.stat-pill-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.stat-pill-label {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    margin-top: 2px;
}

/* Menus List */
.menus-list {
    display: flex;
    flex-direction: column;
}

.menu-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.menu-row:hover {
    background: rgba(99, 102, 241, 0.05);
}

.menu-row:last-child {
    border-bottom: none;
}

.menu-info {
    flex: 1;
}

.menu-title-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.menu-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
}

.menu-title-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: white;
}

.menu-status {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.menu-status-active {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.25);
}

.menu-status-inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.menu-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin-left: calc(36px + 0.75rem);
}

.menu-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.menu-meta-item i {
    color: rgba(99, 102, 241, 0.6);
}

.menu-meta-item strong {
    color: rgba(255,255,255,0.7);
}

.menu-actions {
    display: flex;
    gap: 0.5rem;
}

/* Plan Card Enhanced */
.plan-features-grid {
    font-size: 0.9rem;
}

.plan-feature {
    padding: 0.75rem;
    background: rgba(99, 102, 241, 0.05);
    border-radius: 10px;
    border: 1px solid rgba(99, 102, 241, 0.1);
}

.plan-feature strong {
    display: block;
    margin-bottom: 0.25rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
}

.plan-feature p {
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-summary {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }

    .page-hero-actions {
        width: 100%;
    }

    .page-hero-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .menu-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .menu-actions {
        width: 100%;
    }

    .menu-actions .admin-btn {
        flex: 1;
        justify-content: center;
    }

    .menu-meta {
        margin-left: 0;
    }
}
</style>

<script>
function toggleMenuStatus(menuId, menuName, isActivating) {
    const action = isActivating ? 'activate' : 'deactivate';
    if (!confirm(`Are you sure you want to ${action} the menu "${menuName}"?`)) {
        return;
    }

    fetch('<?= $basePath ?>/admin-legacy/menus/toggle/' + menuId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'csrf_token=<?= Csrf::generate() ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to toggle menu status'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function deleteMenu(menuId, menuName) {
    if (!confirm(`Are you sure you want to delete the menu "${menuName}"? This will also delete all menu items.`)) {
        return;
    }

    fetch('<?= $basePath ?>/admin-legacy/menus/delete/' + menuId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'csrf_token=<?= Csrf::generate() ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete menu'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
