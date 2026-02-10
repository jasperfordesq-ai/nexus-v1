<?php
/**
 * Exchange Request Detail View
 * View detailed information about a single exchange request
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Exchange Request #' . ($exchange['id'] ?? '');
$adminPageSubtitle = 'View exchange request details';
$adminPageIcon = 'fa-handshake';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$exchange = $exchange ?? [];
$history = $history ?? [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$statusClass = match($exchange['status'] ?? '') {
    'completed' => 'success',
    'cancelled', 'expired' => 'danger',
    'disputed' => 'danger',
    'pending_broker' => 'warning',
    'pending_provider', 'pending_confirmation' => 'info',
    'accepted', 'in_progress' => 'primary',
    default => 'secondary'
};
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/broker-controls/exchanges" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Exchange Request #<?= $exchange['id'] ?? '' ?>
        </h1>
        <p class="admin-page-subtitle">
            Created <?= isset($exchange['created_at']) ? date('M j, Y \a\t g:i A', strtotime($exchange['created_at'])) : '' ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <span class="admin-badge admin-badge-<?= $statusClass ?> admin-badge-lg">
            <?= ucwords(str_replace('_', ' ', $exchange['status'] ?? 'Unknown')) ?>
        </span>
    </div>
</div>

<?php if ($flashSuccess): ?>
<div class="config-flash config-flash-success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="config-flash config-flash-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<div class="exchange-detail-grid">
    <!-- Main Info Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-info-circle"></i> Exchange Details</h2>
        </div>
        <div class="admin-card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Listing</span>
                    <div class="detail-value">
                        <a href="<?= $basePath ?>/listings/<?= $exchange['listing_id'] ?? '' ?>" target="_blank">
                            <?= htmlspecialchars($exchange['listing_title'] ?? 'Unknown Listing') ?>
                        </a>
                        <span class="admin-badge admin-badge-<?= ($exchange['listing_type'] ?? '') === 'offer' ? 'success' : 'info' ?> admin-badge-sm">
                            <?= ucfirst($exchange['listing_type'] ?? '') ?>
                        </span>
                    </div>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Proposed Hours</span>
                    <span class="detail-value detail-value-lg"><?= number_format($exchange['proposed_hours'] ?? 0, 1) ?>h</span>
                </div>
                <?php if (!empty($exchange['final_hours'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Final Hours</span>
                    <span class="detail-value detail-value-lg"><?= number_format($exchange['final_hours'], 1) ?>h</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exchange['risk_level'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Risk Level</span>
                    <?php
                    $riskClass = match($exchange['risk_level']) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'secondary'
                    };
                    ?>
                    <span class="admin-badge admin-badge-<?= $riskClass ?>"><?= ucfirst($exchange['risk_level']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Participants Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-users"></i> Participants</h2>
        </div>
        <div class="admin-card-body">
            <div class="participants-grid">
                <div class="participant-card">
                    <div class="participant-label">Requester</div>
                    <div class="participant-info">
                        <?php if (!empty($exchange['requester_avatar'])): ?>
                        <img src="<?= htmlspecialchars($exchange['requester_avatar']) ?>" class="participant-avatar" alt="">
                        <?php else: ?>
                        <div class="participant-avatar participant-avatar-placeholder">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div class="participant-name"><?= htmlspecialchars($exchange['requester_name'] ?? 'Unknown') ?></div>
                            <div class="participant-email"><?= htmlspecialchars($exchange['requester_email'] ?? '') ?></div>
                        </div>
                    </div>
                    <?php if (!empty($exchange['requester_confirmed_at'])): ?>
                    <div class="confirmation-status confirmed">
                        <i class="fa-solid fa-check-circle"></i>
                        Confirmed <?= number_format($exchange['requester_confirmed_hours'] ?? 0, 1) ?>h
                        <small><?= date('M j, g:i A', strtotime($exchange['requester_confirmed_at'])) ?></small>
                    </div>
                    <?php else: ?>
                    <div class="confirmation-status pending">
                        <i class="fa-solid fa-clock"></i>
                        Awaiting confirmation
                    </div>
                    <?php endif; ?>
                </div>

                <div class="participant-divider">
                    <i class="fa-solid fa-arrows-left-right"></i>
                </div>

                <div class="participant-card">
                    <div class="participant-label">Provider</div>
                    <div class="participant-info">
                        <?php if (!empty($exchange['provider_avatar'])): ?>
                        <img src="<?= htmlspecialchars($exchange['provider_avatar']) ?>" class="participant-avatar" alt="">
                        <?php else: ?>
                        <div class="participant-avatar participant-avatar-placeholder">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div class="participant-name"><?= htmlspecialchars($exchange['provider_name'] ?? 'Unknown') ?></div>
                            <div class="participant-email"><?= htmlspecialchars($exchange['provider_email'] ?? '') ?></div>
                        </div>
                    </div>
                    <?php if (!empty($exchange['provider_confirmed_at'])): ?>
                    <div class="confirmation-status confirmed">
                        <i class="fa-solid fa-check-circle"></i>
                        Confirmed <?= number_format($exchange['provider_confirmed_hours'] ?? 0, 1) ?>h
                        <small><?= date('M j, g:i A', strtotime($exchange['provider_confirmed_at'])) ?></small>
                    </div>
                    <?php else: ?>
                    <div class="confirmation-status pending">
                        <i class="fa-solid fa-clock"></i>
                        Awaiting confirmation
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Broker Actions Card -->
    <?php if ($exchange['status'] === 'pending_broker'): ?>
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-gavel"></i> Broker Action Required</h2>
        </div>
        <div class="admin-card-body">
            <p class="action-description">
                This exchange request requires broker approval before it can proceed.
            </p>

            <div class="broker-action-forms">
                <form action="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>/approve" method="POST" class="action-form">
                    <?= Csrf::input() ?>
                    <div class="form-group">
                        <label for="approve_notes">Approval Notes (optional)</label>
                        <textarea name="notes" id="approve_notes" class="admin-input" rows="2" placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    <button type="submit" class="admin-btn admin-btn-success">
                        <i class="fa-solid fa-check"></i> Approve Exchange
                    </button>
                </form>

                <form action="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $exchange['id'] ?>/reject" method="POST" class="action-form">
                    <?= Csrf::input() ?>
                    <div class="form-group">
                        <label for="reject_reason">Rejection Reason <span class="required">*</span></label>
                        <textarea name="reason" id="reject_reason" class="admin-input" rows="2" placeholder="Explain why this exchange is being rejected..." required></textarea>
                    </div>
                    <button type="submit" class="admin-btn admin-btn-danger">
                        <i class="fa-solid fa-times"></i> Reject Exchange
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Broker Notes Card -->
    <?php if (!empty($exchange['broker_notes']) || !empty($exchange['broker_id'])): ?>
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-clipboard"></i> Broker Notes</h2>
        </div>
        <div class="admin-card-body">
            <?php if (!empty($exchange['broker_name'])): ?>
            <div class="broker-info">
                <span class="detail-label">Reviewed by</span>
                <span class="detail-value"><?= htmlspecialchars($exchange['broker_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($exchange['broker_notes'])): ?>
            <div class="broker-notes-content">
                <?= nl2br(htmlspecialchars($exchange['broker_notes'])) ?>
            </div>
            <?php else: ?>
            <p class="text-muted">No notes recorded.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- History Timeline -->
    <div class="admin-glass-card admin-glass-card-full">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-history"></i> Exchange History</h2>
        </div>
        <div class="admin-card-body">
            <?php if (empty($history)): ?>
            <p class="text-muted">No history recorded yet.</p>
            <?php else: ?>
            <div class="history-timeline">
                <?php foreach ($history as $item): ?>
                <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-action"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $item['action']))) ?></span>
                            <span class="timeline-time"><?= date('M j, Y \a\t g:i A', strtotime($item['created_at'])) ?></span>
                        </div>
                        <div class="timeline-meta">
                            <span class="admin-badge admin-badge-secondary admin-badge-sm"><?= ucfirst($item['actor_role']) ?></span>
                            <?php if (!empty($item['old_status']) && !empty($item['new_status'])): ?>
                            <span class="status-change">
                                <?= ucwords(str_replace('_', ' ', $item['old_status'])) ?>
                                <i class="fa-solid fa-arrow-right"></i>
                                <?= ucwords(str_replace('_', ' ', $item['new_status'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($item['notes'])): ?>
                        <div class="timeline-notes"><?= nl2br(htmlspecialchars($item['notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.exchange-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}
.admin-glass-card-full {
    grid-column: 1 / -1;
}
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}
.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.detail-label {
    font-size: 0.8rem;
    color: var(--text-secondary, rgba(255,255,255,0.6));
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.detail-value {
    font-size: 1rem;
    color: var(--text-primary, #fff);
}
.detail-value a {
    color: var(--color-primary-400, #818cf8);
    text-decoration: none;
}
.detail-value a:hover {
    text-decoration: underline;
}
.detail-value-lg {
    font-size: 1.5rem;
    font-weight: 600;
}
.participants-grid {
    display: flex;
    align-items: stretch;
    gap: 1.5rem;
}
.participant-card {
    flex: 1;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    padding: 1.25rem;
}
.participant-label {
    font-size: 0.75rem;
    color: var(--text-secondary, rgba(255,255,255,0.6));
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 1rem;
}
.participant-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}
.participant-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}
.participant-avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.1);
    color: var(--text-secondary);
}
.participant-name {
    font-weight: 600;
    font-size: 1rem;
}
.participant-email {
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.participant-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    font-size: 1.5rem;
}
.confirmation-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
}
.confirmation-status.confirmed {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}
.confirmation-status.pending {
    background: rgba(251, 191, 36, 0.15);
    color: #fbbf24;
}
.confirmation-status small {
    margin-left: auto;
    opacity: 0.7;
}
.action-description {
    margin-bottom: 1.5rem;
    color: var(--text-secondary);
}
.broker-action-forms {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}
.action-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.form-group label {
    font-size: 0.9rem;
    font-weight: 500;
}
.form-group .required {
    color: #ef4444;
}
.admin-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    color: var(--text-primary, #fff);
    font-family: inherit;
    font-size: 0.95rem;
    resize: vertical;
}
.admin-input:focus {
    outline: none;
    border-color: var(--color-primary-500, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}
.broker-info {
    margin-bottom: 1rem;
}
.broker-notes-content {
    background: rgba(255,255,255,0.03);
    border-radius: 8px;
    padding: 1rem;
    line-height: 1.6;
}
.history-timeline {
    position: relative;
    padding-left: 2rem;
}
.history-timeline::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(255,255,255,0.1);
}
.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-marker {
    position: absolute;
    left: -1.75rem;
    top: 0.25rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--color-primary-500, #6366f1);
    border: 2px solid var(--color-background, #1a1a2e);
}
.timeline-content {
    background: rgba(255,255,255,0.03);
    border-radius: 8px;
    padding: 1rem;
}
.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.timeline-action {
    font-weight: 600;
}
.timeline-time {
    font-size: 0.8rem;
    color: var(--text-secondary);
}
.timeline-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.status-change {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.status-change i {
    font-size: 0.7rem;
}
.timeline-notes {
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
}
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 0.75rem;
    opacity: 0.7;
}
.back-link:hover { opacity: 1; }
.config-flash {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}
.config-flash-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
}
.config-flash-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}
.admin-badge-lg {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}
.text-muted {
    color: var(--text-secondary, rgba(255,255,255,0.6));
}

@media (max-width: 1024px) {
    .exchange-detail-grid {
        grid-template-columns: 1fr;
    }
    .broker-action-forms {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .participants-grid {
        flex-direction: column;
    }
    .participant-divider {
        transform: rotate(90deg);
        padding: 0.5rem 0;
    }
}
</style>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
