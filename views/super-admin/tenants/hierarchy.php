<?php
/**
 * Super Admin - Visual Hierarchy Tree
 *
 * Premium interactive tree view with drag-and-drop re-parenting,
 * search, filtering, stats, and smooth animations.
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Tenant Hierarchy';
require __DIR__ . '/../partials/header.php';

// Calculate stats
$totalTenants = count($tenants);
$activeTenants = count(array_filter($tenants, fn($t) => $t['is_active']));
$hubTenants = count(array_filter($tenants, fn($t) => $t['allows_subtenants']));
$maxDepth = max(array_column($tenants, 'depth') ?: [0]);
$totalUsers = array_sum(array_column($tenants, 'user_count'));
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/tenants">Tenants</a>
    <span class="super-breadcrumb-sep">/</span>
    <span>Hierarchy View</span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-sitemap"></i>
            Tenant Hierarchy
        </h1>
        <p class="super-page-subtitle">
            Visual organization structure with drag-and-drop management
        </p>
    </div>
    <div class="super-page-actions">
        <button type="button" class="super-btn super-btn-secondary" onclick="expandAll()">
            <i class="fa-solid fa-expand"></i>
            Expand All
        </button>
        <button type="button" class="super-btn super-btn-secondary" onclick="collapseAll()">
            <i class="fa-solid fa-compress"></i>
            Collapse All
        </button>
        <a href="/super-admin/tenants" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-table-list"></i>
            List View
        </a>
        <a href="/super-admin/tenants/create" class="super-btn super-btn-primary">
            <i class="fa-solid fa-plus"></i>
            Add Tenant
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="hierarchy-stats">
    <div class="hierarchy-stat-card">
        <div class="hierarchy-stat-icon purple">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="hierarchy-stat-info">
            <div class="hierarchy-stat-value"><?= $totalTenants ?></div>
            <div class="hierarchy-stat-label">Total Tenants</div>
        </div>
    </div>
    <div class="hierarchy-stat-card">
        <div class="hierarchy-stat-icon green">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="hierarchy-stat-info">
            <div class="hierarchy-stat-value"><?= $activeTenants ?></div>
            <div class="hierarchy-stat-label">Active</div>
        </div>
    </div>
    <div class="hierarchy-stat-card">
        <div class="hierarchy-stat-icon amber">
            <i class="fa-solid fa-network-wired"></i>
        </div>
        <div class="hierarchy-stat-info">
            <div class="hierarchy-stat-value"><?= $hubTenants ?></div>
            <div class="hierarchy-stat-label">Hub Tenants</div>
        </div>
    </div>
    <div class="hierarchy-stat-card">
        <div class="hierarchy-stat-icon blue">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="hierarchy-stat-info">
            <div class="hierarchy-stat-value"><?= $maxDepth ?></div>
            <div class="hierarchy-stat-label">Max Depth</div>
        </div>
    </div>
    <div class="hierarchy-stat-card">
        <div class="hierarchy-stat-icon cyan">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="hierarchy-stat-info">
            <div class="hierarchy-stat-value"><?= number_format($totalUsers) ?></div>
            <div class="hierarchy-stat-label">Total Users</div>
        </div>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="hierarchy-toolbar">
    <div class="hierarchy-search">
        <i class="fa-solid fa-search"></i>
        <input type="text" id="treeSearch" placeholder="Search tenants..." onkeyup="filterTree(this.value)">
        <button type="button" class="hierarchy-search-clear" onclick="clearSearch()" style="display: none;">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>
    <div class="hierarchy-filters">
        <button type="button" class="hierarchy-filter-btn active" data-filter="all" onclick="setFilter('all')">
            <i class="fa-solid fa-globe"></i> All
        </button>
        <button type="button" class="hierarchy-filter-btn" data-filter="hub" onclick="setFilter('hub')">
            <i class="fa-solid fa-network-wired"></i> Hubs Only
        </button>
        <button type="button" class="hierarchy-filter-btn" data-filter="active" onclick="setFilter('active')">
            <i class="fa-solid fa-circle-check"></i> Active
        </button>
        <button type="button" class="hierarchy-filter-btn" data-filter="inactive" onclick="setFilter('inactive')">
            <i class="fa-solid fa-circle-xmark"></i> Inactive
        </button>
    </div>
    <div class="hierarchy-legend">
        <span class="legend-item">
            <i class="fa-solid fa-crown" style="color: #fbbf24;"></i>
            <span>Master</span>
        </span>
        <span class="legend-item">
            <span class="legend-badge hub">Hub</span>
            <span>Can create sub-tenants</span>
        </span>
        <span class="legend-item">
            <i class="fa-solid fa-grip-vertical" style="color: var(--super-text-muted);"></i>
            <span>Drag to move</span>
        </span>
    </div>
</div>

<!-- Tree Container -->
<div class="hierarchy-container">
    <div class="hierarchy-tree-wrapper">
        <div id="hierarchy-tree" class="hierarchy-tree">
            <?php if (empty($tenants)): ?>
                <div class="hierarchy-empty">
                    <div class="hierarchy-empty-icon">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                    <h3>No Tenants Found</h3>
                    <p>Create your first tenant to get started.</p>
                    <a href="/super-admin/tenants/create" class="super-btn super-btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Create Tenant
                    </a>
                </div>
            <?php else: ?>
                <?php
                function renderTreeNode($tenant, $allTenants, $level = 0) {
                    $children = array_filter($allTenants, fn($t) => $t['parent_id'] == $tenant['id']);
                    usort($children, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                    $hasChildren = count($children) > 0;
                    $isMaster = $tenant['id'] == 1;
                    $childCount = count($children);
                    $descendantCount = 0;

                    // Count all descendants
                    $stack = $children;
                    while (!empty($stack)) {
                        $current = array_pop($stack);
                        $descendantCount++;
                        $currentChildren = array_filter($allTenants, fn($t) => $t['parent_id'] == $current['id']);
                        foreach ($currentChildren as $child) {
                            $stack[] = $child;
                        }
                    }
                    ?>
                    <div class="tree-node <?= $hasChildren ? 'has-children' : '' ?> <?= $isMaster ? 'is-master' : '' ?> <?= !$tenant['is_active'] ? 'is-inactive' : '' ?> <?= $tenant['allows_subtenants'] ? 'is-hub' : '' ?>"
                         data-id="<?= $tenant['id'] ?>"
                         data-name="<?= htmlspecialchars(strtolower($tenant['name'])) ?>"
                         data-slug="<?= htmlspecialchars(strtolower($tenant['slug'])) ?>"
                         data-parent-id="<?= $tenant['parent_id'] ?? '' ?>"
                         data-allows-subtenants="<?= $tenant['allows_subtenants'] ? '1' : '0' ?>"
                         data-is-active="<?= $tenant['is_active'] ? '1' : '0' ?>"
                         data-depth="<?= $tenant['depth'] ?>"
                         draggable="<?= $isMaster ? 'false' : 'true' ?>">

                        <div class="tree-node-content">
                            <div class="tree-node-left">
                                <?php if ($hasChildren): ?>
                                    <button class="tree-toggle" onclick="toggleNode(event, this)" title="<?= $childCount ?> sub-tenant<?= $childCount !== 1 ? 's' : '' ?>">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="tree-toggle-placeholder"></span>
                                <?php endif; ?>

                                <div class="tree-node-drag <?= $isMaster ? 'disabled' : '' ?>" title="<?= $isMaster ? 'Cannot move master tenant' : 'Drag to move' ?>">
                                    <i class="fa-solid fa-grip-vertical"></i>
                                </div>

                                <div class="tree-node-icon <?= $isMaster ? 'master' : ($tenant['allows_subtenants'] ? 'hub' : 'leaf') ?>">
                                    <?php if ($isMaster): ?>
                                        <i class="fa-solid fa-crown"></i>
                                    <?php elseif ($tenant['allows_subtenants']): ?>
                                        <i class="fa-solid fa-building"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-store"></i>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tree-node-center">
                                <div class="tree-node-title">
                                    <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="tree-node-name">
                                        <?= htmlspecialchars($tenant['name']) ?>
                                    </a>
                                    <div class="tree-node-badges">
                                        <?php if ($tenant['allows_subtenants']): ?>
                                            <span class="node-badge hub">Hub</span>
                                        <?php endif; ?>
                                        <?php if (!$tenant['is_active']): ?>
                                            <span class="node-badge inactive">Inactive</span>
                                        <?php endif; ?>
                                        <?php if ($hasChildren): ?>
                                            <span class="node-badge children"><?= $descendantCount ?> sub</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="tree-node-meta">
                                    <span class="meta-item">
                                        <i class="fa-solid fa-at"></i>
                                        <?= htmlspecialchars($tenant['slug']) ?>
                                    </span>
                                    <?php if ($tenant['user_count'] ?? 0): ?>
                                        <span class="meta-item">
                                            <i class="fa-solid fa-users"></i>
                                            <?= $tenant['user_count'] ?> user<?= $tenant['user_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($tenant['domain']): ?>
                                        <span class="meta-item">
                                            <i class="fa-solid fa-globe"></i>
                                            <?= htmlspecialchars($tenant['domain']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="meta-item depth">
                                        <i class="fa-solid fa-layer-group"></i>
                                        Level <?= $tenant['depth'] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="tree-node-right">
                                <div class="tree-node-actions">
                                    <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="node-action view" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="/super-admin/tenants/<?= $tenant['id'] ?>/edit" class="node-action edit" title="Edit Tenant">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <?php if ($tenant['allows_subtenants']): ?>
                                        <a href="/super-admin/tenants/create?parent_id=<?= $tenant['id'] ?>" class="node-action add" title="Add Sub-Tenant">
                                            <i class="fa-solid fa-plus"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($hasChildren): ?>
                            <div class="tree-children">
                                <?php foreach ($children as $child): ?>
                                    <?php renderTreeNode($child, $allTenants, $level + 1); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php
                }

                // Find root nodes (no parent or parent is not in visible list)
                $visibleIds = array_column($tenants, 'id');
                $rootNodes = array_filter($tenants, function($t) use ($visibleIds) {
                    return !$t['parent_id'] || !in_array($t['parent_id'], $visibleIds);
                });
                usort($rootNodes, fn($a, $b) => $a['id'] - $b['id']); // Master first

                foreach ($rootNodes as $root) {
                    renderTreeNode($root, $tenants, 0);
                }
                ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Info Panel (shown on hover) -->
    <div id="quickInfo" class="quick-info-panel">
        <div class="quick-info-header">
            <span class="quick-info-title"></span>
            <span class="quick-info-badge"></span>
        </div>
        <div class="quick-info-body">
            <div class="quick-info-row">
                <i class="fa-solid fa-fingerprint"></i>
                <span class="quick-info-id"></span>
            </div>
            <div class="quick-info-row">
                <i class="fa-solid fa-code-branch"></i>
                <span class="quick-info-path"></span>
            </div>
            <div class="quick-info-row">
                <i class="fa-solid fa-users"></i>
                <span class="quick-info-users"></span>
            </div>
        </div>
    </div>
</div>

<!-- Drop Confirmation Modal -->
<div id="dropModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-icon">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <h3>Move Tenant</h3>
        </div>
        <div class="modal-body">
            <div class="move-preview">
                <div class="move-item source">
                    <div class="move-item-icon">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <div class="move-item-info">
                        <span class="move-item-label">Moving</span>
                        <span class="move-item-name" id="dropSourceName"></span>
                    </div>
                </div>
                <div class="move-arrow">
                    <i class="fa-solid fa-arrow-down"></i>
                </div>
                <div class="move-item target">
                    <div class="move-item-icon">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                    <div class="move-item-info">
                        <span class="move-item-label">Under</span>
                        <span class="move-item-name" id="dropTargetName"></span>
                    </div>
                </div>
            </div>
            <div class="modal-warning">
                <i class="fa-solid fa-info-circle"></i>
                <span>All sub-tenants will move with their parent.</span>
            </div>
        </div>
        <div class="modal-footer">
            <form id="moveForm" method="POST" action="">
                <?= Csrf::input() ?>
                <input type="hidden" name="new_parent_id" id="moveNewParentId">
                <button type="button" class="super-btn super-btn-secondary" onclick="closeDropModal()">
                    <i class="fa-solid fa-times"></i>
                    Cancel
                </button>
                <button type="submit" class="super-btn super-btn-primary">
                    <i class="fa-solid fa-check"></i>
                    Confirm Move
                </button>
            </form>
        </div>
    </div>
</div>

<!-- No Results Message -->
<div id="noResults" class="no-results" style="display: none;">
    <i class="fa-solid fa-search"></i>
    <h4>No tenants found</h4>
    <p>Try adjusting your search or filter criteria.</p>
    <button type="button" class="super-btn super-btn-secondary" onclick="clearSearch(); setFilter('all');">
        <i class="fa-solid fa-refresh"></i>
        Reset Filters
    </button>
</div>

<style>
/* ============================================
   HIERARCHY PAGE STYLES
   ============================================ */

/* Stats Cards */
.hierarchy-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1200px) {
    .hierarchy-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .hierarchy-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

.hierarchy-stat-card {
    background: var(--super-surface);
    border: 1px solid var(--super-border);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.hierarchy-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border-color: var(--super-primary);
}

.hierarchy-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.hierarchy-stat-icon.purple { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
.hierarchy-stat-icon.green { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.hierarchy-stat-icon.amber { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.hierarchy-stat-icon.blue { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.hierarchy-stat-icon.cyan { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }

.hierarchy-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: var(--super-text);
}

.hierarchy-stat-label {
    font-size: 0.75rem;
    color: var(--super-text-muted);
    margin-top: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Toolbar */
.hierarchy-toolbar {
    background: var(--super-surface);
    border: 1px solid var(--super-border);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.hierarchy-search {
    position: relative;
    flex: 1;
    min-width: 250px;
    max-width: 400px;
}

.hierarchy-search i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--super-text-muted);
}

.hierarchy-search input {
    width: 100%;
    padding: 0.625rem 2.5rem 0.625rem 2.75rem;
    background: var(--super-bg);
    border: 1px solid var(--super-border);
    border-radius: 8px;
    color: var(--super-text);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.hierarchy-search input:focus {
    outline: none;
    border-color: var(--super-primary);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.hierarchy-search-clear {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--super-text-muted);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
}

.hierarchy-search-clear:hover {
    color: var(--super-text);
    background: var(--super-border);
}

.hierarchy-filters {
    display: flex;
    gap: 0.5rem;
}

.hierarchy-filter-btn {
    padding: 0.5rem 0.875rem;
    background: transparent;
    border: 1px solid var(--super-border);
    border-radius: 6px;
    color: var(--super-text-muted);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.hierarchy-filter-btn:hover {
    border-color: var(--super-primary);
    color: var(--super-text);
}

.hierarchy-filter-btn.active {
    background: var(--super-primary);
    border-color: var(--super-primary);
    color: white;
}

.hierarchy-legend {
    display: flex;
    gap: 1.25rem;
    margin-left: auto;
    font-size: 0.8rem;
    color: var(--super-text-muted);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.legend-badge {
    font-size: 0.65rem;
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
    font-weight: 600;
}

.legend-badge.hub {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

/* Tree Container */
.hierarchy-container {
    position: relative;
}

.hierarchy-tree-wrapper {
    background: var(--super-surface);
    border: 1px solid var(--super-border);
    border-radius: 12px;
    padding: 1.5rem;
    min-height: 400px;
}

.hierarchy-tree {
    position: relative;
}

/* Empty State */
.hierarchy-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--super-text-muted);
}

.hierarchy-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: var(--super-bg);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--super-text-muted);
}

.hierarchy-empty h3 {
    margin: 0 0 0.5rem;
    color: var(--super-text);
}

.hierarchy-empty p {
    margin: 0 0 1.5rem;
}

/* Tree Node */
.tree-node {
    user-select: none;
    margin-bottom: 2px;
}

.tree-node-content {
    display: flex;
    align-items: center;
    padding: 0.625rem 0.875rem;
    border-radius: 10px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    background: transparent;
}

.tree-node-content:hover {
    background: rgba(139, 92, 246, 0.05);
}

/* Master tenant special styling */
.tree-node.is-master > .tree-node-content {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.08), rgba(251, 191, 36, 0.02));
    border-left: 3px solid #fbbf24;
    border-radius: 0 10px 10px 0;
}

.tree-node.is-master > .tree-node-content:hover {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.12), rgba(251, 191, 36, 0.05));
}

/* Inactive tenant styling */
.tree-node.is-inactive > .tree-node-content {
    opacity: 0.6;
}

.tree-node.is-inactive > .tree-node-content:hover {
    opacity: 0.85;
}

/* Node sections */
.tree-node-left {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.tree-node-center {
    flex: 1;
    min-width: 0;
    padding: 0 1rem;
}

.tree-node-right {
    display: flex;
    align-items: center;
}

/* Toggle button */
.tree-toggle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    cursor: pointer;
    border-radius: 6px;
    color: var(--super-text-muted);
    transition: all 0.2s;
}

.tree-toggle:hover {
    background: var(--super-border);
    color: var(--super-text);
}

.tree-toggle i {
    font-size: 0.75rem;
    transition: transform 0.2s ease;
}

.tree-node:not(.collapsed) > .tree-node-content .tree-toggle i {
    transform: rotate(90deg);
}

.tree-toggle-placeholder {
    width: 28px;
    height: 28px;
}

/* Drag handle */
.tree-node-drag {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--super-text-muted);
    opacity: 0.4;
    cursor: grab;
    transition: all 0.2s;
}

.tree-node-content:hover .tree-node-drag:not(.disabled) {
    opacity: 1;
}

.tree-node-drag.disabled {
    cursor: not-allowed;
    opacity: 0.2;
}

.tree-node[draggable="true"]:active .tree-node-drag {
    cursor: grabbing;
}

/* Node icon */
.tree-node-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.2s;
}

.tree-node-icon.master {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(217, 119, 6, 0.2));
    color: #fbbf24;
}

.tree-node-icon.hub {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.tree-node-icon.leaf {
    background: var(--super-bg);
    color: var(--super-text-muted);
}

/* Node info */
.tree-node-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.tree-node-name {
    font-weight: 600;
    color: var(--super-text);
    text-decoration: none;
    font-size: 0.9375rem;
    transition: color 0.2s;
}

.tree-node-name:hover {
    color: var(--super-primary);
}

.tree-node-badges {
    display: flex;
    gap: 0.375rem;
}

.node-badge {
    font-size: 0.625rem;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.node-badge.hub {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.node-badge.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.node-badge.children {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

.tree-node-meta {
    display: flex;
    gap: 1rem;
    margin-top: 0.25rem;
    flex-wrap: wrap;
}

.meta-item {
    font-size: 0.75rem;
    color: var(--super-text-muted);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.meta-item i {
    font-size: 0.65rem;
    opacity: 0.7;
}

.meta-item.depth {
    color: var(--super-primary);
    opacity: 0.7;
}

/* Node actions */
.tree-node-actions {
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transform: translateX(10px);
    transition: all 0.2s ease;
}

.tree-node-content:hover .tree-node-actions {
    opacity: 1;
    transform: translateX(0);
}

.node-action {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: var(--super-text-muted);
    text-decoration: none;
    transition: all 0.2s;
}

.node-action:hover {
    transform: scale(1.1);
}

.node-action.view:hover {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.node-action.edit:hover {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.node-action.add:hover {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

/* Tree children (nested) */
.tree-children {
    margin-left: 20px;
    padding-left: 20px;
    border-left: 2px solid var(--super-border);
    margin-top: 2px;
    position: relative;
}

.tree-children::before {
    content: '';
    position: absolute;
    left: -2px;
    top: 0;
    width: 2px;
    height: 20px;
    background: linear-gradient(to bottom, var(--super-primary), var(--super-border));
}

.tree-node.collapsed > .tree-children {
    display: none;
}

/* Drag and drop states */
.tree-node.dragging > .tree-node-content {
    opacity: 0.5;
    background: var(--super-bg);
    border-style: dashed;
}

.tree-node.drop-target > .tree-node-content {
    border-color: var(--super-primary);
    background: rgba(139, 92, 246, 0.1);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
}

.tree-node.drop-invalid > .tree-node-content {
    border-color: var(--super-danger);
    background: rgba(239, 68, 68, 0.05);
}

/* Search highlight */
.tree-node.search-match > .tree-node-content {
    background: rgba(251, 191, 36, 0.1);
}

.tree-node.search-hidden {
    display: none;
}

/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.modal-overlay.show {
    display: flex;
    opacity: 1;
}

.modal-container {
    background: var(--super-surface);
    border: 1px solid var(--super-border);
    border-radius: 16px;
    max-width: 420px;
    width: 90%;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
    transform: scale(0.9) translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.show .modal-container {
    transform: scale(1) translateY(0);
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem;
    text-align: center;
}

.modal-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 1rem;
    background: rgba(139, 92, 246, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--super-primary);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
}

.modal-body {
    padding: 0 1.5rem 1.5rem;
}

.move-preview {
    background: var(--super-bg);
    border-radius: 12px;
    padding: 1rem;
}

.move-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    background: var(--super-surface);
}

.move-item.source {
    border-left: 3px solid #f59e0b;
}

.move-item.target {
    border-left: 3px solid var(--super-primary);
}

.move-item-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}

.move-item.source .move-item-icon {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.move-item.target .move-item-icon {
    background: rgba(139, 92, 246, 0.15);
    color: var(--super-primary);
}

.move-item-info {
    flex: 1;
}

.move-item-label {
    display: block;
    font-size: 0.7rem;
    color: var(--super-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.move-item-name {
    font-weight: 600;
    color: var(--super-text);
}

.move-arrow {
    text-align: center;
    padding: 0.5rem 0;
    color: var(--super-text-muted);
}

.modal-warning {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.75rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 8px;
    font-size: 0.8rem;
    color: #60a5fa;
}

.modal-footer {
    padding: 0 1.5rem 1.5rem;
}

.modal-footer form {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* No Results */
.no-results {
    text-align: center;
    padding: 3rem;
    color: var(--super-text-muted);
}

.no-results i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-results h4 {
    margin: 0 0 0.5rem;
    color: var(--super-text);
}

.no-results p {
    margin: 0 0 1.5rem;
}

/* Quick Info Panel (on hover - future enhancement) */
.quick-info-panel {
    display: none;
}

/* Animations */
@keyframes nodeHighlight {
    0%, 100% { background: transparent; }
    50% { background: rgba(139, 92, 246, 0.1); }
}

.tree-node.just-moved > .tree-node-content {
    animation: nodeHighlight 1s ease 2;
}

/* Responsive */
@media (max-width: 1024px) {
    .hierarchy-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .hierarchy-search {
        max-width: none;
    }

    .hierarchy-legend {
        margin-left: 0;
        justify-content: center;
    }

    .hierarchy-filters {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .tree-node-meta {
        display: none;
    }

    .tree-node-actions {
        opacity: 1;
        transform: none;
    }

    .legend-item span:last-child {
        display: none;
    }
}
</style>

<script>
// State
let draggedNode = null;
let currentFilter = 'all';
let searchQuery = '';

// Toggle node expand/collapse
function toggleNode(event, btn) {
    event.stopPropagation();
    const node = btn.closest('.tree-node');
    node.classList.toggle('collapsed');

    // Save state to localStorage
    saveTreeState();
}

// Expand all nodes
function expandAll() {
    document.querySelectorAll('.tree-node.collapsed').forEach(node => {
        node.classList.remove('collapsed');
    });
    saveTreeState();
}

// Collapse all nodes
function collapseAll() {
    document.querySelectorAll('.tree-node.has-children').forEach(node => {
        if (!node.classList.contains('is-master')) {
            node.classList.add('collapsed');
        }
    });
    saveTreeState();
}

// Save tree state to localStorage
function saveTreeState() {
    const collapsedIds = [];
    document.querySelectorAll('.tree-node.collapsed').forEach(node => {
        collapsedIds.push(node.dataset.id);
    });
    localStorage.setItem('hierarchyTreeState', JSON.stringify(collapsedIds));
}

// Restore tree state from localStorage
function restoreTreeState() {
    const saved = localStorage.getItem('hierarchyTreeState');
    if (saved) {
        const collapsedIds = JSON.parse(saved);
        collapsedIds.forEach(id => {
            const node = document.querySelector(`.tree-node[data-id="${id}"]`);
            if (node) node.classList.add('collapsed');
        });
    }
}

// Search/filter tree
function filterTree(query) {
    searchQuery = query.toLowerCase().trim();
    const clearBtn = document.querySelector('.hierarchy-search-clear');
    clearBtn.style.display = searchQuery ? 'block' : 'none';

    applyFilters();
}

// Clear search
function clearSearch() {
    document.getElementById('treeSearch').value = '';
    searchQuery = '';
    document.querySelector('.hierarchy-search-clear').style.display = 'none';
    applyFilters();
}

// Set filter
function setFilter(filter) {
    currentFilter = filter;

    // Update button states
    document.querySelectorAll('.hierarchy-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });

    applyFilters();
}

// Apply all filters
function applyFilters() {
    const nodes = document.querySelectorAll('.tree-node');
    let visibleCount = 0;

    nodes.forEach(node => {
        let show = true;

        // Search filter
        if (searchQuery) {
            const name = node.dataset.name || '';
            const slug = node.dataset.slug || '';
            const matches = name.includes(searchQuery) || slug.includes(searchQuery);

            if (matches) {
                node.classList.add('search-match');
                // Expand parents
                let parent = node.parentElement.closest('.tree-node');
                while (parent) {
                    parent.classList.remove('collapsed');
                    parent = parent.parentElement.closest('.tree-node');
                }
            } else {
                node.classList.remove('search-match');
                // Only hide if no children match
                const hasMatchingChild = node.querySelector('.tree-node.search-match');
                if (!hasMatchingChild) {
                    show = false;
                }
            }
        } else {
            node.classList.remove('search-match');
        }

        // Type filter
        if (show && currentFilter !== 'all') {
            switch (currentFilter) {
                case 'hub':
                    if (node.dataset.allowsSubtenants !== '1') {
                        const hasHubChild = node.querySelector('.tree-node[data-allows-subtenants="1"]');
                        if (!hasHubChild) show = false;
                    }
                    break;
                case 'active':
                    if (node.dataset.isActive !== '1') {
                        const hasActiveChild = node.querySelector('.tree-node[data-is-active="1"]');
                        if (!hasActiveChild) show = false;
                    }
                    break;
                case 'inactive':
                    if (node.dataset.isActive !== '0') {
                        const hasInactiveChild = node.querySelector('.tree-node[data-is-active="0"]');
                        if (!hasInactiveChild) show = false;
                    }
                    break;
            }
        }

        node.classList.toggle('search-hidden', !show);
        if (show) visibleCount++;
    });

    // Show/hide no results message
    const noResults = document.getElementById('noResults');
    const treeWrapper = document.querySelector('.hierarchy-tree-wrapper');

    if (visibleCount === 0 && (searchQuery || currentFilter !== 'all')) {
        noResults.style.display = 'block';
        treeWrapper.style.display = 'none';
    } else {
        noResults.style.display = 'none';
        treeWrapper.style.display = 'block';
    }
}

// Drag and Drop functionality
function initDragDrop() {
    document.querySelectorAll('.tree-node[draggable="true"]').forEach(node => {
        node.addEventListener('dragstart', function(e) {
            e.stopPropagation();
            draggedNode = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.id);

            // Add drag image
            const ghost = this.querySelector('.tree-node-content').cloneNode(true);
            ghost.style.cssText = 'position: absolute; top: -1000px; background: var(--super-surface); border-radius: 10px; padding: 0.5rem 1rem;';
            document.body.appendChild(ghost);
            e.dataTransfer.setDragImage(ghost, 20, 20);
            setTimeout(() => ghost.remove(), 0);
        });

        node.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
            document.querySelectorAll('.drop-target, .drop-invalid').forEach(el => {
                el.classList.remove('drop-target', 'drop-invalid');
            });
            draggedNode = null;
        });
    });

    // Drop targets
    document.querySelectorAll('.tree-node').forEach(node => {
        node.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!draggedNode || draggedNode === this) return;

            const isHub = this.dataset.allowsSubtenants === '1';
            const isSelf = this.dataset.id === draggedNode.dataset.id;
            const isDescendant = this.closest(`[data-id="${draggedNode.dataset.id}"]`);

            if (isHub && !isSelf && !isDescendant) {
                this.classList.add('drop-target');
                this.classList.remove('drop-invalid');
                e.dataTransfer.dropEffect = 'move';
            } else {
                this.classList.add('drop-invalid');
                this.classList.remove('drop-target');
                e.dataTransfer.dropEffect = 'none';
            }
        });

        node.addEventListener('dragleave', function(e) {
            this.classList.remove('drop-target', 'drop-invalid');
        });

        node.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drop-target', 'drop-invalid');

            if (!draggedNode || draggedNode === this) return;

            const isHub = this.dataset.allowsSubtenants === '1';
            const isDescendant = this.closest(`[data-id="${draggedNode.dataset.id}"]`);

            if (!isHub || isDescendant) return;

            // Show confirmation modal
            const sourceId = draggedNode.dataset.id;
            const targetId = this.dataset.id;
            const sourceName = draggedNode.querySelector('.tree-node-name').textContent.trim();
            const targetName = this.querySelector('.tree-node-name').textContent.trim();

            document.getElementById('dropSourceName').textContent = sourceName;
            document.getElementById('dropTargetName').textContent = targetName;
            document.getElementById('moveForm').action = '/super-admin/tenants/' + sourceId + '/move';
            document.getElementById('moveNewParentId').value = targetId;

            openDropModal();
        });
    });
}

// Modal functions
function openDropModal() {
    const modal = document.getElementById('dropModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDropModal() {
    const modal = document.getElementById('dropModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDropModal();
    }

    // Ctrl/Cmd + F to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('treeSearch').focus();
    }
});

// Click outside modal to close
document.getElementById('dropModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDropModal();
    }
});

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    restoreTreeState();
    initDragDrop();

    // Add smooth reveal animation
    document.querySelectorAll('.tree-node').forEach((node, index) => {
        node.style.opacity = '0';
        node.style.transform = 'translateX(-10px)';
        setTimeout(() => {
            node.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            node.style.opacity = '1';
            node.style.transform = 'translateX(0)';
        }, index * 20);
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
