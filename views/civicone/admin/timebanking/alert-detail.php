<?php
/**
 * Admin Alert Detail - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Alert Details';
$adminPageSubtitle = 'TimeBanking';
$adminPageIcon = 'fa-triangle-exclamation';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$details = $alert['details_decoded'] ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/timebanking/alerts" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <?= ucwords(str_replace('_', ' ', $alert['alert_type'])) ?>
        </h1>
        <p class="admin-page-subtitle">
            <span class="alert-meta-badge severity-<?= $alert['severity'] ?>"><?= ucfirst($alert['severity']) ?> Severity</span>
            <span class="alert-meta-badge status-<?= $alert['status'] ?>"><?= ucfirst($alert['status']) ?></span>
            <span class="alert-meta-date"><?= date('M d, Y g:i A', strtotime($alert['created_at'])) ?></span>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <div class="alert-detail-header-icon <?= $alert['severity'] ?>">
            <?php if ($alert['alert_type'] === 'large_transfer'): ?>
                <i class="fa-solid fa-dollar-sign"></i>
            <?php elseif ($alert['alert_type'] === 'high_velocity'): ?>
                <i class="fa-solid fa-bolt"></i>
            <?php elseif ($alert['alert_type'] === 'circular_transfer'): ?>
                <i class="fa-solid fa-rotate"></i>
            <?php else: ?>
                <i class="fa-solid fa-clock"></i>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="alert-detail-grid">
    <!-- User Information -->
    <?php if ($alert['user_id']): ?>
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Related User</h3>
                <p class="admin-card-subtitle">Account associated with this alert</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="user-info-card">
                <div class="user-avatar-large">
                    <?= strtoupper(substr($alert['user_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($alert['user_name'] ?? 'Unknown') ?></h4>
                    <p>User ID: <?= $alert['user_id'] ?></p>
                </div>
                <a href="<?= $basePath ?>/admin/timebanking/user-report/<?= $alert['user_id'] ?>" class="admin-btn admin-btn-secondary" style="margin-left: auto;">
                    <i class="fa-solid fa-chart-line"></i> View Report
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alert Details -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fa-solid fa-info-circle"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Alert Details</h3>
                <p class="admin-card-subtitle">Specific information about this alert</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="info-grid">
                <?php if ($alert['alert_type'] === 'large_transfer'): ?>
                <div class="info-item">
                    <div class="info-label">Transfer Amount</div>
                    <div class="info-value highlight"><?= number_format($details['amount'] ?? 0, 1) ?> HRS</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Threshold</div>
                    <div class="info-value"><?= number_format($details['threshold'] ?? 50, 1) ?> HRS</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Sender</div>
                    <div class="info-value"><?= htmlspecialchars($details['sender_name'] ?? 'Unknown') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Receiver</div>
                    <div class="info-value"><?= htmlspecialchars($details['receiver_name'] ?? 'Unknown') ?></div>
                </div>

                <?php elseif ($alert['alert_type'] === 'high_velocity'): ?>
                <div class="info-item">
                    <div class="info-label">Transaction Count</div>
                    <div class="info-value highlight"><?= $details['transaction_count'] ?? 0 ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Time Window</div>
                    <div class="info-value"><?= $details['time_window'] ?? '1 hour' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Threshold</div>
                    <div class="info-value"><?= $details['threshold'] ?? 10 ?> transactions</div>
                </div>
                <div class="info-item">
                    <div class="info-label">User Name</div>
                    <div class="info-value"><?= htmlspecialchars($details['user_name'] ?? 'Unknown') ?></div>
                </div>

                <?php elseif ($alert['alert_type'] === 'circular_transfer'): ?>
                <div class="info-item">
                    <div class="info-label">User A</div>
                    <div class="info-value"><?= htmlspecialchars($details['user_a_name'] ?? 'Unknown') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">User B</div>
                    <div class="info-value"><?= htmlspecialchars($details['user_b_name'] ?? 'Unknown') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Initial Transfer</div>
                    <div class="info-value"><?= number_format($details['first_amount'] ?? 0, 1) ?> HRS</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Return Transfer</div>
                    <div class="info-value highlight"><?= number_format($details['return_amount'] ?? 0, 1) ?> HRS</div>
                </div>

                <?php elseif ($alert['alert_type'] === 'inactive_high_balance'): ?>
                <div class="info-item">
                    <div class="info-label">Current Balance</div>
                    <div class="info-value highlight"><?= number_format($details['balance'] ?? 0, 1) ?> HRS</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Transaction</div>
                    <div class="info-value"><?= $details['last_transaction'] ? date('M d, Y', strtotime($details['last_transaction'])) : 'Never' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Inactive Days Threshold</div>
                    <div class="info-value"><?= $details['inactive_days'] ?? 90 ?> days</div>
                </div>
                <div class="info-item">
                    <div class="info-label">User</div>
                    <div class="info-value"><?= htmlspecialchars($details['user_name'] ?? 'Unknown') ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resolution Info (if resolved) -->
    <?php if (in_array($alert['status'], ['resolved', 'dismissed'])): ?>
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Resolution</h3>
                <p class="admin-card-subtitle">How this alert was handled</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="resolution-info">
                <div class="resolution-header">
                    <span class="resolution-status <?= $alert['status'] ?>"><?= $alert['status'] === 'resolved' ? 'Resolved' : 'Dismissed' ?></span>
                </div>
                <div class="resolution-details">
                    <p><strong>By:</strong> <?= htmlspecialchars($alert['resolved_by_name'] ?? 'Unknown') ?></p>
                    <p><strong>At:</strong> <?= $alert['resolved_at'] ? date('M d, Y g:i A', strtotime($alert['resolved_at'])) : '-' ?></p>
                    <?php if ($alert['resolution_notes']): ?>
                    <p><strong>Notes:</strong> <?= htmlspecialchars($alert['resolution_notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Form -->
    <?php if (!in_array($alert['status'], ['resolved', 'dismissed'])): ?>
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                <i class="fa-solid fa-gavel"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Take Action</h3>
                <p class="admin-card-subtitle">Update this alert's status</p>
            </div>
        </div>
        <div class="admin-card-body">
            <form action="<?= $basePath ?>/admin/timebanking/alert/<?= $alert['id'] ?>/status" method="POST">
                <?= Csrf::input() ?>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" class="notes-textarea" placeholder="Add notes about this alert (optional)..."></textarea>
                </div>

                <div class="action-buttons">
                    <?php if ($alert['status'] === 'new'): ?>
                    <button type="submit" name="status" value="reviewing" class="admin-btn admin-btn-warning">
                        <i class="fa-solid fa-eye"></i> Mark as Reviewing
                    </button>
                    <?php endif; ?>
                    <button type="submit" name="status" value="resolved" class="admin-btn admin-btn-success">
                        <i class="fa-solid fa-check"></i> Mark as Resolved
                    </button>
                    <button type="submit" name="status" value="dismissed" class="admin-btn admin-btn-ghost">
                        <i class="fa-solid fa-times"></i> Dismiss Alert
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Page subtitle with badges */
.admin-page-subtitle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.alert-meta-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.alert-meta-badge.severity-high,
.alert-meta-badge.severity-critical {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.alert-meta-badge.severity-medium {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.alert-meta-badge.severity-low {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.alert-meta-badge.status-new {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.alert-meta-badge.status-reviewing {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.alert-meta-badge.status-resolved {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.alert-meta-badge.status-dismissed {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
}

.alert-meta-date {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Header Icon */
.alert-detail-header-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.alert-detail-header-icon.high,
.alert-detail-header-icon.critical {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.alert-detail-header-icon.medium {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.alert-detail-header-icon.low {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

/* Detail Grid */
.alert-detail-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    max-width: 900px;
}

/* User Info Card */
.user-info-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
}

.user-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.user-details h4 {
    margin: 0 0 0.25rem;
    color: #f1f5f9;
    font-size: 1.1rem;
}

.user-details p {
    margin: 0;
    color: #94a3b8;
    font-size: 0.9rem;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.info-item {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
}

.info-label {
    font-size: 0.8rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.4rem;
}

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #f1f5f9;
}

.info-value.highlight {
    color: #f87171;
}

.info-value.success {
    color: #34d399;
}

/* Resolution Info */
.resolution-info {
    padding: 1.25rem;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 12px;
}

.resolution-header {
    margin-bottom: 1rem;
}

.resolution-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
}

.resolution-status.resolved {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.resolution-status.dismissed {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
}

.resolution-details p {
    margin: 0 0 0.5rem;
    color: #94a3b8;
    font-size: 0.9rem;
}

.resolution-details p:last-child {
    margin-bottom: 0;
}

.resolution-details strong {
    color: #e2e8f0;
}

/* Form Group */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #fff;
}

.notes-textarea {
    width: 100%;
    padding: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.75rem;
    font-size: 0.95rem;
    background: rgba(0, 0, 0, 0.2);
    color: #f1f5f9;
    resize: vertical;
    min-height: 100px;
    transition: all 0.2s;
    font-family: inherit;
}

.notes-textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.notes-textarea::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border: none;
}

.admin-btn-warning:hover {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
}

.admin-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #34d399, #10b981);
}

.admin-btn-ghost {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.admin-btn-ghost:hover {
    background: rgba(107, 114, 128, 0.3);
}

/* Mobile */
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }

    .action-buttons {
        flex-direction: column;
    }

    .action-buttons .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .user-info-card {
        flex-direction: column;
        text-align: center;
    }

    .user-info-card .admin-btn {
        margin: 1rem auto 0;
    }

    .admin-page-subtitle {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
