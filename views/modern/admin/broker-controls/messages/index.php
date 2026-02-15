<?php
/**
 * Message Review Queue
 * Review messages copied for broker visibility
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Message Review';
$adminPageSubtitle = 'Review copied messages for compliance';
$adminPageIcon = 'fa-envelope-open-text';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$messages = $messages ?? [];
$filter = $filter ?? 'unreviewed';
$page = $page ?? 1;
$totalCount = $total_count ?? 0;
$totalPages = $total_pages ?? 1;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Message Review
        </h1>
        <p class="admin-page-subtitle">Review messages copied for broker visibility</p>
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

<!-- Filter Tabs -->
<div class="admin-tabs">
    <a href="?filter=unreviewed" class="admin-tab <?= $filter === 'unreviewed' ? 'active' : '' ?>">
        <i class="fa-solid fa-inbox"></i> Unreviewed
        <?php if (($unreviewed_count ?? 0) > 0): ?>
        <span class="tab-badge"><?= $unreviewed_count ?></span>
        <?php endif; ?>
    </a>
    <a href="?filter=flagged" class="admin-tab admin-tab-warning <?= $filter === 'flagged' ? 'active' : '' ?>">
        <i class="fa-solid fa-flag"></i> Flagged
    </a>
    <a href="?filter=reviewed" class="admin-tab <?= $filter === 'reviewed' ? 'active' : '' ?>">
        <i class="fa-solid fa-check-circle"></i> Reviewed
    </a>
    <a href="?filter=all" class="admin-tab <?= $filter === 'all' ? 'active' : '' ?>">
        <i class="fa-solid fa-list"></i> All
    </a>
</div>

<div class="admin-glass-card">
    <div class="admin-card-body">
        <?php if (empty($messages)): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-envelope-open"></i>
            <h3>No Messages to Review</h3>
            <p>
                <?php if ($filter === 'unreviewed'): ?>
                All messages have been reviewed. Great job!
                <?php elseif ($filter === 'flagged'): ?>
                No messages have been flagged for concern.
                <?php else: ?>
                No messages match this filter.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="message-list">
            <?php foreach ($messages as $message): ?>
            <div class="message-item <?= $message['flagged'] ? 'message-item-flagged' : '' ?>">
                <div class="message-header">
                    <div class="message-participants">
                        <div class="participant from">
                            <?php if (!empty($message['sender_avatar'])): ?>
                            <img src="<?= htmlspecialchars($message['sender_avatar']) ?>" class="participant-avatar" alt="">
                            <?php endif; ?>
                            <span><?= htmlspecialchars($message['sender_name'] ?? 'Unknown') ?></span>
                        </div>
                        <i class="fa-solid fa-arrow-right participant-arrow"></i>
                        <div class="participant to">
                            <?php if (!empty($message['receiver_avatar'])): ?>
                            <img src="<?= htmlspecialchars($message['receiver_avatar']) ?>" class="participant-avatar" alt="">
                            <?php endif; ?>
                            <span><?= htmlspecialchars($message['receiver_name'] ?? 'Unknown') ?></span>
                        </div>
                    </div>
                    <div class="message-meta">
                        <?php
                        $reasonLabels = [
                            'first_contact' => ['First Contact', 'info'],
                            'high_risk_listing' => ['High Risk Listing', 'warning'],
                            'new_member' => ['New Member', 'secondary'],
                            'flagged_user' => ['Flagged User', 'danger'],
                            'monitoring' => ['Monitoring', 'primary'],
                        ];
                        $reason = $message['copy_reason'] ?? 'monitoring';
                        $reasonInfo = $reasonLabels[$reason] ?? ['Unknown', 'secondary'];
                        ?>
                        <span class="admin-badge admin-badge-<?= $reasonInfo[1] ?> admin-badge-sm">
                            <?= $reasonInfo[0] ?>
                        </span>
                        <span class="message-time">
                            <?= date('M j, Y \a\t g:i A', strtotime($message['sent_at'])) ?>
                        </span>
                    </div>
                </div>

                <div class="message-body">
                    <?= nl2br(htmlspecialchars($message['message_body'] ?? '')) ?>
                </div>

                <div class="message-footer">
                    <div class="message-status">
                        <?php if ($message['flagged']): ?>
                        <span class="status-flagged">
                            <i class="fa-solid fa-flag"></i> Flagged
                        </span>
                        <?php elseif (!empty($message['reviewed_at'])): ?>
                        <span class="status-reviewed">
                            <i class="fa-solid fa-check"></i>
                            Reviewed by <?= htmlspecialchars($message['reviewer_name'] ?? 'Unknown') ?>
                            on <?= date('M j', strtotime($message['reviewed_at'])) ?>
                        </span>
                        <?php else: ?>
                        <span class="status-pending">
                            <i class="fa-solid fa-clock"></i> Pending Review
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="message-actions">
                        <?php if (empty($message['reviewed_at'])): ?>
                        <form action="<?= $basePath ?>/admin-legacy/broker-controls/messages/<?= $message['id'] ?>/review" method="POST" style="display:inline;">
                            <?= Csrf::input() ?>
                            <button type="submit" class="admin-btn admin-btn-success admin-btn-sm">
                                <i class="fa-solid fa-check"></i> Mark Reviewed
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if (!$message['flagged']): ?>
                        <button type="button" class="admin-btn admin-btn-warning admin-btn-sm" onclick="showFlagModal(<?= $message['id'] ?>)">
                            <i class="fa-solid fa-flag"></i> Flag
                        </button>
                        <?php endif; ?>

                        <a href="<?= $basePath ?>/admin-legacy/messages/thread/<?= $message['original_message_id'] ?? '' ?>"
                           class="admin-btn admin-btn-secondary admin-btn-sm" target="_blank">
                            <i class="fa-solid fa-external-link"></i> View Thread
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="admin-pagination">
            <?php if ($page > 1): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Flag Modal -->
<div id="flagModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-flag"></i> Flag Message</h3>
            <button type="button" class="modal-close" onclick="closeFlagModal()">&times;</button>
        </div>
        <form id="flagForm" method="POST">
            <?= Csrf::input() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label for="flag_reason" class="form-label">Reason for flagging <span class="required">*</span></label>
                    <textarea name="reason" id="flag_reason" class="admin-input" rows="3"
                              placeholder="Describe the concern with this message..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="closeFlagModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-warning">
                    <i class="fa-solid fa-flag"></i> Flag Message
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showFlagModal(messageId) {
    const modal = document.getElementById('flagModal');
    const form = document.getElementById('flagForm');
    form.action = '<?= $basePath ?>/admin-legacy/broker-controls/messages/' + messageId + '/flag';
    modal.style.display = 'flex';
}

function closeFlagModal() {
    document.getElementById('flagModal').style.display = 'none';
    document.getElementById('flag_reason').value = '';
}

document.getElementById('flagModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeFlagModal();
    }
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
