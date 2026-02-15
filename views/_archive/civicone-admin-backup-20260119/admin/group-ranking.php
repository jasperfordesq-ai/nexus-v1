<?php
/**
 * Smart Group Ranking Dashboard - Admin FDS Gold Standard
 * Intelligent featured groups management with geographic diversity
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Group Ranking';
$adminPageSubtitle = 'Smart Featured Groups Intelligence';
$adminPageIcon = 'fa-chart-line';

// Include standalone admin header
require __DIR__ . '/partials/admin-header.php';

// Get current featured groups
$localHubs = $localHubs ?? [];
$communityGroups = $communityGroups ?? [];
$lastUpdate = $lastUpdate ?? null;
?>

<style>
/* Group Ranking Styles - Admin FDS Gold Standard */

/* Info Banner */
.ranking-info-banner {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-left: 4px solid #6366f1;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.ranking-info-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.ranking-info-content {
    flex: 1;
}

.ranking-info-title {
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.25rem;
}

.ranking-info-text {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

.ranking-info-time {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ranking-info-time i {
    color: #22d3ee;
}

/* Ranking Grid */
.ranking-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Algorithm Info Box */
.algorithm-box {
    background: rgba(6, 182, 212, 0.05);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
}

.algorithm-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #22d3ee;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.algorithm-formula {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
    background: rgba(0, 0, 0, 0.2);
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    border-left: 3px solid #22d3ee;
}

/* Ranking Table */
.ranking-table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
}

.ranking-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.ranking-table thead {
    background: rgba(6, 182, 212, 0.1);
}

.ranking-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(255, 255, 255, 0.6);
    border-bottom: 1px solid rgba(6, 182, 212, 0.2);
}

.ranking-table tbody tr {
    transition: all 0.2s;
    border-bottom: 1px solid rgba(6, 182, 212, 0.08);
}

.ranking-table tbody tr:hover {
    background: rgba(6, 182, 212, 0.05);
}

.ranking-table tbody tr:last-child {
    border-bottom: none;
}

.ranking-table td {
    padding: 1rem 1.25rem;
    color: rgba(255, 255, 255, 0.9);
}

/* Rank Badge */
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 0.9rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.rank-badge.rank-1 {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.rank-badge.rank-2 {
    background: linear-gradient(135deg, #94a3b8, #cbd5e1);
    color: #0f172a;
    box-shadow: 0 4px 12px rgba(148, 163, 184, 0.4);
}

.rank-badge.rank-3 {
    background: linear-gradient(135deg, #d97706, #f59e0b);
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.4);
}

/* Group Name */
.group-name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.group-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a5b4fc;
    font-size: 1rem;
    flex-shrink: 0;
}

.group-name-text {
    font-weight: 600;
    color: #fff;
}

.group-county {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 2px;
}

/* Member Count */
.member-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.member-count i {
    color: #22d3ee;
}

/* Status Badge */
.status-badge-featured {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.1));
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.status-badge-featured .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #22c55e;
    animation: statusPulse 2s ease-in-out infinite;
}

@keyframes statusPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Action Buttons */
.ranking-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn-pin {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(6, 182, 212, 0.3);
    background: rgba(6, 182, 212, 0.1);
    color: #22d3ee;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.action-btn-pin:hover {
    background: rgba(6, 182, 212, 0.2);
    border-color: rgba(6, 182, 212, 0.4);
    transform: translateY(-1px);
}

.action-btn-pin.pinned {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(251, 191, 36, 0.1));
    border-color: rgba(245, 158, 11, 0.4);
    color: #fbbf24;
}

.action-btn-pin.pinned:hover {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.3), rgba(251, 191, 36, 0.15));
}

/* Empty State */
.ranking-empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: rgba(255, 255, 255, 0.5);
}

.ranking-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(6, 182, 212, 0.1);
    border: 2px dashed rgba(6, 182, 212, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #22d3ee;
    font-size: 2rem;
}

.ranking-empty-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
}

.ranking-empty-text {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1.5rem;
}

/* Toast Container Fix - Ensure fully visible on screen */
.admin-toast-container {
    position: fixed !important;
    top: auto !important;
    bottom: 2rem !important;
    right: auto !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    z-index: 99999 !important;
    width: 90% !important;
    max-width: 500px !important;
    pointer-events: none !important;
}

.admin-toast {
    pointer-events: all !important;
    width: 100% !important;
    box-sizing: border-box !important;
    margin: 0 auto !important;
}

@media (max-width: 768px) {
    .admin-toast-container {
        width: calc(100% - 2rem) !important;
        max-width: none !important;
    }
}

/* Update Button Override */
.admin-page-header-actions .admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
    border: none !important;
    color: white !important;
    padding: 0.75rem 1.5rem !important;
    border-radius: 10px !important;
    font-weight: 600 !important;
    font-size: 0.9rem !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
    transition: all 0.2s !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.admin-page-header-actions .admin-btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4) !important;
}

.admin-page-header-actions .admin-btn-primary:disabled {
    opacity: 0.6 !important;
    cursor: not-allowed !important;
    transform: none !important;
}

/* Responsive */
@media (max-width: 1200px) {
    .ranking-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .ranking-table th,
    .ranking-table td {
        padding: 0.75rem 1rem;
    }

    .group-icon {
        width: 36px;
        height: 36px;
    }

    .admin-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-page-header-actions {
        width: 100%;
        margin-top: 1rem;
    }

    .admin-page-header-actions .admin-btn-primary {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Smart Group Ranking
        </h1>
        <p class="admin-page-subtitle">Intelligent featured groups management with geographic diversity</p>
    </div>
    <div class="admin-page-header-actions">
        <button onclick="updateFeaturedGroups()" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-rotate"></i>
            Update Featured Groups Now
        </button>
    </div>
</div>

<!-- Info Banner -->
<?php if ($lastUpdate): ?>
<div class="ranking-info-banner">
    <div class="ranking-info-icon">
        <i class="fa-solid fa-clock-rotate-left"></i>
    </div>
    <div class="ranking-info-content">
        <div class="ranking-info-title">Last Updated</div>
        <p class="ranking-info-text">
            Featured groups were automatically updated based on member count and geographic diversity rules.
        </p>
    </div>
    <div class="ranking-info-time">
        <i class="fa-solid fa-calendar"></i>
        <?= date('F j, Y \a\t g:i A', strtotime($lastUpdate)) ?>
    </div>
</div>
<?php endif; ?>

<!-- Ranking Grid -->
<div class="ranking-grid">

    <!-- Local Hubs -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-purple">
                <i class="fa-solid fa-location-dot"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Featured Local Hubs</h3>
                <p class="admin-card-subtitle">Top 6 most active geographic groups</p>
            </div>
        </div>

        <div class="admin-card-body">
            <!-- Algorithm Info -->
            <div class="algorithm-box">
                <div class="algorithm-title">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Ranking Algorithm
                </div>
                <div class="algorithm-formula">
                    Rank by: Member Count DESC | Max 2 per county
                </div>
            </div>

            <?php if (empty($localHubs)): ?>
            <!-- Empty State -->
            <div class="ranking-empty-state">
                <div class="ranking-empty-icon">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <h3 class="ranking-empty-title">No Featured Hubs</h3>
                <p class="ranking-empty-text">Click "Update Featured Groups Now" to rank groups by member count.</p>
                <button onclick="updateFeaturedGroups()" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-rotate"></i>
                    Run Ranking Now
                </button>
            </div>
            <?php else: ?>
            <!-- Ranking Table -->
            <div class="ranking-table-wrapper">
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Rank</th>
                            <th>Group</th>
                            <th style="width: 120px;">Members</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 100px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($localHubs as $index => $hub): ?>
                        <tr data-group-id="<?= $hub['id'] ?>">
                            <td>
                                <span class="rank-badge rank-<?= min($index + 1, 3) ?>">
                                    <?= $index + 1 ?>
                                </span>
                            </td>
                            <td>
                                <div class="group-name-cell">
                                    <div class="group-icon">
                                        <i class="fa-solid fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="group-name-text"><?= htmlspecialchars($hub['name']) ?></div>
                                        <?php if (!empty($hub['county_name'])): ?>
                                        <div class="group-county">
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?= htmlspecialchars($hub['county_name']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="member-count">
                                    <i class="fa-solid fa-user-group"></i>
                                    <?= number_format($hub['member_count'] ?? 0) ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge-featured">
                                    <span class="status-dot"></span>
                                    Featured
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="ranking-actions">
                                    <button
                                        class="action-btn-pin <?= !empty($hub['is_pinned']) ? 'pinned' : '' ?>"
                                        onclick="togglePin(<?= $hub['id'] ?>, this)"
                                        title="<?= !empty($hub['is_pinned']) ? 'Unpin this group' : 'Pin this group' ?>">
                                        <i class="fa-solid fa-thumbtack"></i>
                                        <?= !empty($hub['is_pinned']) ? 'Pinned' : 'Pin' ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Community Groups (Future Phase) -->
    <div class="admin-glass-card" style="opacity: 0.6;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-cyan">
                <i class="fa-solid fa-people-group"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Featured Community Groups</h3>
                <p class="admin-card-subtitle">Coming in Phase 2</p>
            </div>
        </div>

        <div class="admin-card-body">
            <!-- Algorithm Info -->
            <div class="algorithm-box">
                <div class="algorithm-title">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Future Algorithm
                </div>
                <div class="algorithm-formula">
                    Score = (Members × 3) + (Posts × 5) + (Activity × 10)
                </div>
            </div>

            <!-- Coming Soon State -->
            <div class="ranking-empty-state">
                <div class="ranking-empty-icon">
                    <i class="fa-solid fa-rocket"></i>
                </div>
                <h3 class="ranking-empty-title">Coming Soon</h3>
                <p class="ranking-empty-text">Interest-based community group ranking will be available in Phase 2.</p>
            </div>
        </div>
    </div>

</div>

<script>
// Update featured groups via AJAX
function updateFeaturedGroups() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

    fetch('<?= $basePath ?>/admin-legacy/group-ranking/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            AdminToast.show('success', 'Success!', 'Featured groups updated successfully! Refreshing page...', 8000);
            setTimeout(() => window.location.reload(), 6000);
        } else {
            throw new Error(data.error || 'Update failed');
        }
    })
    .catch(error => {
        console.error('Update error:', error);
        AdminToast.show('error', 'Error', 'Failed to update: ' + error.message, 10000);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

// Toggle pin status for a group
function togglePin(groupId, button) {
    const isPinned = button.classList.contains('pinned');
    const originalHTML = button.innerHTML;

    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    fetch('<?= $basePath ?>/admin-legacy/group-ranking/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            group_id: groupId,
            featured: !isPinned
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('pinned');
            button.innerHTML = button.classList.contains('pinned')
                ? '<i class="fa-solid fa-thumbtack"></i> Pinned'
                : '<i class="fa-solid fa-thumbtack"></i> Pin';
            button.title = button.classList.contains('pinned')
                ? 'Unpin this group'
                : 'Pin this group';

            AdminToast.show(
                'success',
                'Success!',
                button.classList.contains('pinned')
                    ? 'Group pinned successfully'
                    : 'Group unpinned successfully',
                8000
            );
        } else {
            throw new Error(data.error || 'Toggle failed');
        }
    })
    .catch(error => {
        console.error('Toggle error:', error);
        AdminToast.show('error', 'Error', 'Failed to toggle pin: ' + error.message, 10000);
        button.innerHTML = originalHTML;
    })
    .finally(() => {
        button.disabled = false;
    });
}
</script>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
