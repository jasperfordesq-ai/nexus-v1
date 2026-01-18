<?php
/**
 * Skeleton Layout - Notifications
 * View user notifications
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $basePath . '/login');
    exit;
}

$notifications = $notifications ?? [];
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div class="sk-flex-between" style="margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 700;">Notifications</h1>
        <p style="color: #888;">Stay updated with your activity</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <button class="sk-btn sk-btn-outline" onclick="markAllRead()">
            <i class="fas fa-check-double"></i> Mark All Read
        </button>
    <?php endif; ?>
</div>

<!-- Notification Filters -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <div class="sk-flex" style="gap: 0.5rem; flex-wrap: wrap;">
        <button class="sk-btn sk-btn-outline active">All</button>
        <button class="sk-btn sk-btn-outline">Unread</button>
        <button class="sk-btn sk-btn-outline">Messages</button>
        <button class="sk-btn sk-btn-outline">Mentions</button>
    </div>
</div>

<!-- Notifications List -->
<?php if (!empty($notifications)): ?>
    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <?php foreach ($notifications as $notif): ?>
            <div class="sk-card" style="padding: 1rem; <?= empty($notif['read']) ? 'background: rgba(99, 102, 241, 0.05); border-left: 3px solid var(--sk-link);' : '' ?>">
                <div class="sk-flex-between">
                    <div class="sk-flex" style="gap: 1rem; flex: 1;">
                        <!-- Avatar -->
                        <?php if (!empty($notif['avatar'])): ?>
                            <img src="<?= htmlspecialchars($notif['avatar']) ?>" alt="Avatar" style="width: 48px; height: 48px; border-radius: 50%;">
                        <?php else: ?>
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--sk-border); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-bell"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Content -->
                        <div style="flex: 1;">
                            <div style="font-weight: 600; margin-bottom: 0.25rem;">
                                <?= htmlspecialchars($notif['title'] ?? 'Notification') ?>
                            </div>
                            <p style="color: #666; margin-bottom: 0.5rem; font-size: 0.875rem;">
                                <?= htmlspecialchars($notif['message'] ?? '') ?>
                            </p>
                            <div style="color: #888; font-size: 0.75rem;">
                                <?php
                                if (!empty($notif['created_at'])) {
                                    $date = new DateTime($notif['created_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($date);
                                    if ($diff->d > 0) {
                                        echo $diff->d . ' days ago';
                                    } elseif ($diff->h > 0) {
                                        echo $diff->h . ' hours ago';
                                    } else {
                                        echo $diff->i . ' minutes ago';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if (empty($notif['read'])): ?>
                            <button class="sk-btn sk-btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                Mark Read
                            </button>
                        <?php endif; ?>
                        <button class="sk-btn sk-btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Load More -->
    <div style="text-align: center; margin-top: 2rem;">
        <button class="sk-btn sk-btn-outline">Load More</button>
    </div>
<?php else: ?>
    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="far fa-bell"></i></div>
        <h3>No notifications</h3>
        <p>You're all caught up!</p>
    </div>
<?php endif; ?>

<script>
function markAllRead() {
    if (confirm('Mark all notifications as read?')) {
        // AJAX call to mark all as read
        fetch('<?= $basePath ?>/api/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}
</script>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
