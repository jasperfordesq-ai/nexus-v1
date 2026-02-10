<?php
/**
 * Modern View: Exchange Detail
 * Single exchange view with actions and history
 */
$hTitle = 'Exchange #' . $exchange['id'];
$hSubtitle = htmlspecialchars($exchange['listing_title']);
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Exchange';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

// Status labels for display
$statusLabels = [
    'pending_provider' => 'Awaiting Provider',
    'pending_broker' => 'Under Broker Review',
    'accepted' => 'Accepted',
    'in_progress' => 'In Progress',
    'pending_confirmation' => 'Awaiting Confirmation',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'disputed' => 'Disputed',
    'expired' => 'Expired',
];

$statusClass = strtolower($exchange['status']);
?>

<div class="exchange-detail">
    <!-- Back Button -->
    <a href="<?= $basePath ?>/exchanges" class="glass-button glass-button--ghost glass-button--sm" style="margin-bottom: var(--space-4);">
        <i class="fa-solid fa-arrow-left"></i> Back to Exchanges
    </a>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="glass-alert glass-alert--success">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="glass-alert glass-alert--danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Exchange Detail Card -->
    <div class="exchange-detail-card">
        <div class="exchange-detail-header">
            <h1>Exchange #<?= $exchange['id'] ?></h1>
            <span class="exchange-status-badge exchange-status-badge--<?= $statusClass ?>">
                <?= $statusLabels[$exchange['status']] ?? ucfirst(str_replace('_', ' ', $exchange['status'])) ?>
            </span>
        </div>

        <!-- Listing Info -->
        <div class="exchange-detail-section">
            <h2>Listing</h2>
            <div class="exchange-listing-preview">
                <div class="exchange-listing-preview-info">
                    <div class="exchange-listing-preview-title">
                        <a href="<?= $basePath ?>/listings/<?= $exchange['listing_id'] ?>">
                            <?= htmlspecialchars($exchange['listing_title']) ?>
                        </a>
                    </div>
                    <span class="exchange-listing-preview-type exchange-listing-preview-type--<?= $exchange['listing_type'] ?>">
                        <?= ucfirst($exchange['listing_type']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Participants -->
        <div class="exchange-detail-section">
            <h2>Participants</h2>
            <div class="exchange-info-grid">
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Requester</span>
                    <span class="exchange-info-value">
                        <a href="<?= $basePath ?>/profile/<?= $exchange['requester_id'] ?>">
                            <?= htmlspecialchars($exchange['requester_name']) ?>
                        </a>
                        <?php if ($isRequester): ?>
                            <span class="glass-badge glass-badge--sm">(You)</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Provider</span>
                    <span class="exchange-info-value">
                        <a href="<?= $basePath ?>/profile/<?= $exchange['provider_id'] ?>">
                            <?= htmlspecialchars($exchange['provider_name']) ?>
                        </a>
                        <?php if ($isProvider): ?>
                            <span class="glass-badge glass-badge--sm">(You)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Exchange Details -->
        <div class="exchange-detail-section">
            <h2>Exchange Details</h2>
            <div class="exchange-info-grid">
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Proposed Hours</span>
                    <span class="exchange-info-value"><?= number_format($exchange['proposed_hours'], 1) ?> hours</span>
                </div>
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Created</span>
                    <span class="exchange-info-value"><?= date('F j, Y g:i A', strtotime($exchange['created_at'])) ?></span>
                </div>
                <?php if (!empty($exchange['final_hours'])): ?>
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Final Hours</span>
                    <span class="exchange-info-value"><?= number_format($exchange['final_hours'], 1) ?> hours</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exchange['broker_name'])): ?>
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Reviewed By</span>
                    <span class="exchange-info-value"><?= htmlspecialchars($exchange['broker_name']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Confirmation Status -->
            <?php if (in_array($exchange['status'], ['in_progress', 'pending_confirmation', 'completed'])): ?>
            <div class="exchange-info-grid" style="margin-top: var(--space-4);">
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Requester Confirmed</span>
                    <span class="exchange-info-value">
                        <?php if (!empty($exchange['requester_confirmed_at'])): ?>
                            <i class="fa-solid fa-check-circle" style="color: var(--color-success);"></i>
                            <?= number_format($exchange['requester_confirmed_hours'], 1) ?> hours
                            <span style="color: var(--color-text-muted); font-size: var(--font-size-sm);">
                                (<?= date('M j', strtotime($exchange['requester_confirmed_at'])) ?>)
                            </span>
                        <?php else: ?>
                            <i class="fa-solid fa-clock" style="color: var(--color-warning);"></i> Pending
                        <?php endif; ?>
                    </span>
                </div>
                <div class="exchange-info-item">
                    <span class="exchange-info-label">Provider Confirmed</span>
                    <span class="exchange-info-value">
                        <?php if (!empty($exchange['provider_confirmed_at'])): ?>
                            <i class="fa-solid fa-check-circle" style="color: var(--color-success);"></i>
                            <?= number_format($exchange['provider_confirmed_hours'], 1) ?> hours
                            <span style="color: var(--color-text-muted); font-size: var(--font-size-sm);">
                                (<?= date('M j', strtotime($exchange['provider_confirmed_at'])) ?>)
                            </span>
                        <?php else: ?>
                            <i class="fa-solid fa-clock" style="color: var(--color-warning);"></i> Pending
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Risk Warning -->
        <?php if (!empty($exchange['risk_level']) && in_array($exchange['risk_level'], ['high', 'critical'])): ?>
        <div class="glass-alert glass-alert--warning" style="margin-top: var(--space-4);">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>
                <strong>Safety Notice:</strong>
                This listing has been flagged as <?= ucfirst($exchange['risk_level']) ?> risk.
                Please take appropriate precautions when meeting for this exchange.
            </span>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <?php if (!empty($actions)): ?>
        <div class="exchange-actions">
            <?php foreach ($actions as $action): ?>
                <?php if (!empty($action['needsHours'])): ?>
                    <!-- Hours Confirmation Form -->
                    <form action="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>/confirm" method="POST" class="exchange-confirm-form" style="display: flex; gap: var(--space-3); align-items: center; flex: 1;">
                        <?= Nexus\Core\Csrf::input() ?>
                        <input type="number"
                               name="hours"
                               value="<?= number_format($exchange['proposed_hours'], 1) ?>"
                               min="0.25"
                               max="24"
                               step="0.25"
                               class="exchange-form-input"
                               style="max-width: 120px;"
                               required>
                        <span style="color: var(--color-text-muted);">hours</span>
                        <button type="submit" class="exchange-action-btn exchange-action-btn--<?= $action['style'] ?>">
                            <?= $action['label'] ?>
                        </button>
                    </form>
                <?php elseif (!empty($action['confirm'])): ?>
                    <!-- Actions requiring confirmation -->
                    <button type="button"
                            class="exchange-action-btn exchange-action-btn--<?= $action['style'] ?>"
                            onclick="showConfirmModal('<?= $action['action'] ?>', '<?= $action['label'] ?>')">
                        <?= $action['label'] ?>
                    </button>
                <?php else: ?>
                    <!-- Simple form actions -->
                    <form action="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>/<?= $action['action'] ?>" method="POST" style="display: inline;">
                        <?= Nexus\Core\Csrf::input() ?>
                        <button type="submit" class="exchange-action-btn exchange-action-btn--<?= $action['style'] ?>">
                            <?= $action['label'] ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Message Link -->
        <div style="margin-top: var(--space-4); text-align: center;">
            <?php
            $otherUserId = $isRequester ? $exchange['provider_id'] : $exchange['requester_id'];
            $otherUserName = $isRequester ? $exchange['provider_name'] : $exchange['requester_name'];
            ?>
            <a href="<?= $basePath ?>/messages/<?= $otherUserId ?>" class="glass-button glass-button--outline">
                <i class="fa-solid fa-comment"></i>
                Message <?= htmlspecialchars($otherUserName) ?>
            </a>
        </div>
    </div>

    <!-- History Timeline -->
    <?php if (!empty($history)): ?>
    <div class="exchange-detail-card" style="margin-top: var(--space-6);">
        <h2 style="margin-bottom: var(--space-4);">History</h2>
        <div class="exchange-history">
            <?php foreach ($history as $entry): ?>
            <div class="exchange-history-item">
                <div class="exchange-history-time">
                    <?= date('M j, Y g:i A', strtotime($entry['created_at'])) ?>
                </div>
                <div class="exchange-history-action">
                    <?php
                    $actionText = match($entry['action']) {
                        'request_created' => 'Exchange requested',
                        'status_changed' => 'Status changed to ' . ucfirst(str_replace('_', ' ', $entry['new_status'] ?? '')),
                        'requester_confirmed' => 'Requester confirmed hours',
                        'provider_confirmed' => 'Provider confirmed hours',
                        default => ucfirst(str_replace('_', ' ', $entry['action']))
                    };
                    ?>
                    <strong><?= $actionText ?></strong>
                    <?php if (!empty($entry['actor_name'])): ?>
                        by <?= htmlspecialchars($entry['actor_name']) ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($entry['notes'])): ?>
                <div class="exchange-history-notes">
                    <?= htmlspecialchars($entry['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Confirmation Modal -->
<div class="exchange-confirm-modal" id="confirmModal">
    <div class="exchange-confirm-content">
        <h3 class="exchange-confirm-title" id="confirmTitle">Confirm Action</h3>
        <p class="exchange-confirm-text" id="confirmText">Are you sure you want to proceed?</p>
        <form id="confirmForm" method="POST">
            <?= Nexus\Core\Csrf::input() ?>
            <div id="reasonField" style="display: none; margin-bottom: var(--space-4);">
                <label class="exchange-form-label">Reason (optional)</label>
                <textarea name="reason" class="exchange-form-input" rows="3" placeholder="Enter a reason..."></textarea>
            </div>
            <div class="exchange-confirm-actions">
                <button type="button" class="exchange-action-btn exchange-action-btn--secondary" onclick="closeConfirmModal()">
                    Cancel
                </button>
                <button type="submit" class="exchange-action-btn exchange-action-btn--danger" id="confirmBtn">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showConfirmModal(action, label) {
    const modal = document.getElementById('confirmModal');
    const form = document.getElementById('confirmForm');
    const title = document.getElementById('confirmTitle');
    const text = document.getElementById('confirmText');
    const reasonField = document.getElementById('reasonField');
    const confirmBtn = document.getElementById('confirmBtn');

    form.action = '<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>/' + action;
    title.textContent = label;

    if (action === 'decline' || action === 'cancel') {
        text.textContent = 'Are you sure? Please provide a reason.';
        reasonField.style.display = 'block';
        confirmBtn.textContent = label;
    } else {
        text.textContent = 'Are you sure you want to proceed?';
        reasonField.style.display = 'none';
        confirmBtn.textContent = 'Confirm';
    }

    modal.classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
}

// Close modal on backdrop click
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirmModal();
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
