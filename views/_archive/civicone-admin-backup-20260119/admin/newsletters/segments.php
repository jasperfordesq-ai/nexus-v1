<?php
/**
 * Admin Newsletter Segments - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$segments = $segments ?? [];
$stats = [
    'total' => count($segments),
    'active' => count(array_filter($segments, fn($s) => $s['is_active'] ?? false)),
    'inactive' => count(array_filter($segments, fn($s) => !($s['is_active'] ?? false))),
    'total_members' => array_sum(array_column($segments, 'member_count'))
];

// Admin header configuration
$adminPageTitle = 'Segments';
$adminPageSubtitle = 'Newsletters';
$adminPageIcon = 'fa-filter';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon"><i class="fa-solid fa-check-circle"></i></div>
    <div class="admin-alert-content"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="admin-alert admin-alert-error">
    <div class="admin-alert-icon"><i class="fa-solid fa-times-circle"></i></div>
    <div class="admin-alert-content"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-filter"></i>
            Audience Segments
        </h1>
        <p class="admin-page-subtitle">Create targeted audience groups for your newsletters</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/segments/create" class="admin-btn admin-btn-success">
            <i class="fa-solid fa-plus"></i>
            Create Segment
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-indigo">
        <div class="admin-stat-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total']) ?></div>
            <div class="admin-stat-label">Total Segments</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['active']) ?></div>
            <div class="admin-stat-label">Active</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-gray">
        <div class="admin-stat-icon"><i class="fa-solid fa-pause-circle"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['inactive']) ?></div>
            <div class="admin-stat-label">Inactive</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_members']) ?></div>
            <div class="admin-stat-label">Total Reach</div>
        </div>
    </div>
</div>

<!-- Segments List -->
<?php if (empty($segments)): ?>
<div class="admin-glass-card">
    <div class="admin-empty-state">
        <div class="admin-empty-icon">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <h3 class="admin-empty-title">No Segments Yet</h3>
        <p class="admin-empty-text">Create segments to target specific groups of members with your newsletters based on location, groups, activity, and more.</p>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/segments/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
            <i class="fa-solid fa-plus"></i>
            Create Your First Segment
        </a>
    </div>
</div>
<?php else: ?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-indigo">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">All Segments</h3>
            <p class="admin-card-subtitle"><?= count($segments) ?> segment<?= count($segments) !== 1 ? 's' : '' ?></p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Segment</th>
                        <th class="hide-mobile" style="text-align: center;">Members</th>
                        <th class="hide-tablet" style="text-align: center;">Status</th>
                        <th class="hide-mobile" style="text-align: center;">Criteria</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($segments as $segment): ?>
                    <tr>
                        <td>
                            <div class="segment-cell">
                                <div class="segment-icon">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div class="segment-info">
                                    <div class="segment-name"><?= htmlspecialchars($segment['name']) ?></div>
                                    <?php if (!empty($segment['description'])): ?>
                                    <div class="segment-description"><?= htmlspecialchars($segment['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile" style="text-align: center;">
                            <div class="segment-member-count">
                                <span class="segment-member-value"><?= number_format($segment['member_count'] ?? 0) ?></span>
                                <span class="segment-member-label">members</span>
                            </div>
                        </td>
                        <td class="hide-tablet" style="text-align: center;">
                            <?php if ($segment['is_active']): ?>
                            <span class="segment-status segment-status-active">
                                <i class="fa-solid fa-check-circle"></i> Active
                            </span>
                            <?php else: ?>
                            <span class="segment-status segment-status-inactive">
                                <i class="fa-solid fa-pause-circle"></i> Inactive
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile" style="text-align: center;">
                            <div class="segment-criteria">
                                <?php if (!empty($segment['rules']['conditions'])): ?>
                                    <?php
                                    $conditionIcons = [
                                        'county' => ['icon' => 'fa-map-marker-alt', 'color' => '#ef4444'],
                                        'town' => ['icon' => 'fa-city', 'color' => '#f59e0b'],
                                        'group' => ['icon' => 'fa-users', 'color' => '#22c55e'],
                                        'role' => ['icon' => 'fa-user-tag', 'color' => '#6366f1'],
                                        'type' => ['icon' => 'fa-id-badge', 'color' => '#8b5cf6'],
                                        'joined' => ['icon' => 'fa-calendar', 'color' => '#06b6d4'],
                                        'activity' => ['icon' => 'fa-chart-line', 'color' => '#ec4899']
                                    ];
                                    foreach (array_slice($segment['rules']['conditions'], 0, 3) as $condition):
                                        $field = strtolower($condition['field'] ?? '');
                                        $iconData = $conditionIcons[$field] ?? ['icon' => 'fa-filter', 'color' => '#6b7280'];
                                    ?>
                                    <span class="criteria-badge">
                                        <i class="fa-solid <?= $iconData['icon'] ?>" style="color: <?= $iconData['color'] ?>;"></i>
                                        <?= htmlspecialchars(ucfirst($condition['field'])) ?>
                                    </span>
                                    <?php endforeach; ?>
                                    <?php if (count($segment['rules']['conditions']) > 3): ?>
                                    <span class="criteria-badge criteria-badge-more">+<?= count($segment['rules']['conditions']) - 3 ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                <span class="criteria-none">No rules</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/admin-legacy/newsletters/segments/edit/<?= $segment['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="<?= $basePath ?>/admin-legacy/newsletters/segments/delete" method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this segment?')">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= $segment['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Targeting Options Help Card -->
<div class="admin-glass-card targeting-help-card">
    <div class="targeting-help-header">
        <div class="targeting-help-icon">
            <i class="fa-solid fa-bullseye"></i>
        </div>
        <div>
            <h3 class="targeting-help-title">Segment Targeting Options</h3>
            <p class="targeting-help-subtitle">Target your newsletters to the right audience using these criteria</p>
        </div>
    </div>

    <div class="targeting-options-grid">
        <div class="targeting-option">
            <div class="targeting-option-header">
                <div class="targeting-option-icon targeting-option-icon-amber">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <strong class="targeting-option-title">Geographic</strong>
            </div>
            <ul class="targeting-option-list">
                <li>Target by county</li>
                <li>Target by town</li>
                <li>Radius around a location</li>
            </ul>
        </div>

        <div class="targeting-option">
            <div class="targeting-option-header">
                <div class="targeting-option-icon targeting-option-icon-green">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <strong class="targeting-option-title">Groups</strong>
            </div>
            <ul class="targeting-option-list">
                <li>Members of specific groups</li>
                <li>Exclude group members</li>
                <li>Multiple group targeting</li>
            </ul>
        </div>

        <div class="targeting-option">
            <div class="targeting-option-header">
                <div class="targeting-option-icon targeting-option-icon-indigo">
                    <i class="fa-solid fa-chart-simple"></i>
                </div>
                <strong class="targeting-option-title">Activity</strong>
            </div>
            <ul class="targeting-option-list">
                <li>New vs long-term members</li>
                <li>Active sellers/listings</li>
                <li>Engagement level</li>
            </ul>
        </div>

        <div class="targeting-option">
            <div class="targeting-option-header">
                <div class="targeting-option-icon targeting-option-icon-pink">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <strong class="targeting-option-title">Profile</strong>
            </div>
            <ul class="targeting-option-list">
                <li>Individual vs Organisation</li>
                <li>User roles</li>
                <li>Profile completeness</li>
            </ul>
        </div>
    </div>
</div>

<style>
/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
    .admin-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .admin-stats-grid { grid-template-columns: 1fr 1fr; }
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-indigo { --stat-color: #6366f1; }
.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-gray { --stat-color: #94a3b8; }
.admin-stat-orange { --stat-color: #f59e0b; }

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
}

.admin-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
}

.admin-stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Card Header Icon */
.admin-card-header-icon-indigo {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

/* Segment Cell */
.segment-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.segment-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.segment-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.segment-name {
    font-weight: 700;
    color: #fff;
    font-size: 1rem;
}

.segment-description {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    max-width: 300px;
}

/* Segment Member Count */
.segment-member-count {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.segment-member-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #818cf8;
    line-height: 1;
}

.segment-member-label {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 2px;
}

/* Segment Status */
.segment-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.segment-status-active {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.segment-status-inactive {
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
}

.segment-status i {
    font-size: 0.7rem;
}

/* Segment Criteria */
.segment-criteria {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: center;
}

.criteria-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.criteria-badge i {
    font-size: 0.65rem;
}

.criteria-badge-more {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
    border-color: rgba(99, 102, 241, 0.3);
}

.criteria-none {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
    font-style: italic;
}

/* Targeting Help Card */
.targeting-help-card {
    margin-top: 1.5rem;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(99, 102, 241, 0.05)) !important;
    border-color: rgba(59, 130, 246, 0.2) !important;
}

.targeting-help-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.targeting-help-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.targeting-help-title {
    margin: 0 0 4px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #60a5fa;
}

.targeting-help-subtitle {
    margin: 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

/* Targeting Options Grid */
.targeting-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

.targeting-option {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.15);
    padding: 1.25rem;
    border-radius: 12px;
}

.targeting-option-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.targeting-option-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.targeting-option-icon-amber {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.targeting-option-icon-green {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.targeting-option-icon-indigo {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
}

.targeting-option-icon-pink {
    background: rgba(236, 72, 153, 0.2);
    color: #ec4899;
}

.targeting-option-title {
    color: #fff;
    font-size: 0.95rem;
}

.targeting-option-list {
    margin: 0;
    padding-left: 1.25rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    line-height: 1.8;
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.admin-alert-icon {
    font-size: 1.25rem;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
}

.admin-btn-sm {
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Action Buttons */
.admin-action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.75rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-page-header-actions {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .admin-action-buttons {
        flex-direction: column;
    }

    .targeting-options-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
