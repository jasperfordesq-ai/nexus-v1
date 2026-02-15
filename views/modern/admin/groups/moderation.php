<?php
/**
 * Groups Moderation Dashboard
 * Path: views/modern/admin-legacy/groups/moderation.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Groups Moderation';
$adminPageSubtitle = 'Review flagged content and manage violations';
$adminPageIcon = 'fa-flag';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <i class="fa-solid fa-flag" style="color: #a855f7;"></i>
            Content Moderation
        </h1>
        <p class="admin-page-subtitle">Review and moderate flagged content</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/groups" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Moderation Stats -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-flag"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['pending_flags'] ?? 0) ?></div>
            <div class="admin-stat-label">Pending Flags</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-check"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['resolved_30d'] ?? 0) ?></div>
            <div class="admin-stat-label">Resolved (30d)</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-red">
        <div class="admin-stat-icon"><i class="fa-solid fa-ban"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['active_bans'] ?? 0) ?></div>
            <div class="admin-stat-label">Active Bans</div>
        </div>
    </div>
</div>

<!-- Pending Flags -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-clock"></i> Pending Flags</h3>
        <span class="admin-badge admin-badge-warning"><?= count($pendingFlags ?? []) ?></span>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($pendingFlags)): ?>
            <div class="admin-moderation-list">
                <?php foreach ($pendingFlags as $flag): ?>
                    <div class="admin-moderation-item">
                        <div class="admin-moderation-header">
                            <div>
                                <span class="admin-badge admin-badge-<?= ['spam' => 'warning', 'harassment' => 'danger', 'inappropriate' => 'warning'][$flag['reason']] ?? 'secondary' ?>">
                                    <?= htmlspecialchars($flag['reason']) ?>
                                </span>
                                <span class="admin-badge admin-badge-secondary"><?= htmlspecialchars($flag['content_type']) ?></span>
                            </div>
                            <span class="admin-text-muted"><?= date('M j, Y g:i A', strtotime($flag['created_at'])) ?></span>
                        </div>
                        <div class="admin-moderation-content">
                            <p><strong>Reported by:</strong> <?= htmlspecialchars($flag['reporter_name']) ?></p>
                            <?php if ($flag['description']): ?>
                                <p><strong>Details:</strong> <?= htmlspecialchars($flag['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin-legacy/groups/moderate-flag" class="admin-moderation-actions">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="flag_id" value="<?= $flag['id'] ?>">
                            <textarea name="notes" class="admin-form-control admin-form-control-sm" placeholder="Moderation notes..."></textarea>
                            <button type="submit" name="action" value="approve" class="admin-btn admin-btn-sm admin-btn-success">
                                <i class="fa-solid fa-check"></i> Approve
                            </button>
                            <button type="submit" name="action" value="hide" class="admin-btn admin-btn-sm admin-btn-warning">
                                <i class="fa-solid fa-eye-slash"></i> Hide
                            </button>
                            <button type="submit" name="action" value="delete" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this content?')">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                            <button type="submit" name="action" value="ban" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Ban this user?')">
                                <i class="fa-solid fa-ban"></i> Ban User
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-check-circle"></i>
                <h3>All Clear!</h3>
                <p>No pending flags to review</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Moderation History -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-history"></i> Recent Actions</h3>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($moderationHistory)): ?>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Reason</th>
                            <th>Action</th>
                            <th>Moderator</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($moderationHistory as $history): ?>
                            <tr>
                                <td><span class="admin-badge admin-badge-secondary"><?= htmlspecialchars($history['content_type']) ?></span></td>
                                <td><?= htmlspecialchars($history['reason']) ?></td>
                                <td><span class="admin-badge admin-badge-<?= ['approve' => 'success', 'delete' => 'danger', 'hide' => 'warning'][$history['action']] ?? 'secondary' ?>"><?= htmlspecialchars($history['action']) ?></span></td>
                                <td><?= htmlspecialchars($history['moderator_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($history['resolved_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-solid fa-history"></i>
                <p>No moderation history</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/groups-admin-gold-standard.min.css">

<style>
/* Moderation List */
.admin-moderation-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.admin-moderation-item {
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    transition: all 0.2s ease;
}

.admin-moderation-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(99, 102, 241, 0.2);
}

.admin-moderation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.admin-moderation-header > div {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.admin-moderation-content {
    margin-bottom: 1rem;
}

.admin-moderation-content p {
    margin: 0.5rem 0;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.875rem;
    line-height: 1.5;
}

.admin-moderation-content strong {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.admin-moderation-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: flex-start;
}

.admin-moderation-actions textarea {
    flex: 1;
    min-width: 200px;
    margin-bottom: 0.5rem;
}

.admin-text-muted {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Empty State Enhancement */
.admin-empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.admin-empty-state h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
}

.admin-empty-state p {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

/* Responsive Moderation */
@media (max-width: 768px) {
    .admin-moderation-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-moderation-actions {
        flex-direction: column;
        width: 100%;
    }

    .admin-moderation-actions textarea {
        width: 100%;
        min-width: 100%;
    }

    .admin-moderation-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
