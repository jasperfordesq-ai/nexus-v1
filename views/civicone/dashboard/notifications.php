<?php
/**
 * CivicOne Dashboard - Notifications Page
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Template: Account Area Template (Template G)
 * Dedicated page for managing notifications (no longer a tab)
 */

$hTitle = "Notifications";
$hSubtitle = "Manage your notification preferences";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
require_once dirname(__DIR__) . '/components/govuk/breadcrumbs.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Process notification settings for UI
$globalFreq = 'daily';
$groupSettings = [];
if (!empty($notifSettings)) {
    foreach ($notifSettings as $s) {
        if ($s['context_type'] === 'global') {
            $globalFreq = $s['frequency'];
        } elseif ($s['context_type'] === 'group') {
            $groupSettings[$s['context_id']] = $s['frequency'];
        }
    }
}
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Dashboard', 'href' => $basePath . '/dashboard'],
        ['text' => 'Notifications']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<!-- Account Area Secondary Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

<!-- NOTIFICATIONS CONTENT -->
<section aria-labelledby="all-notif-heading" class="govuk-!-margin-bottom-8">
    <div class="govuk-grid-row govuk-!-margin-bottom-4">
        <div class="govuk-grid-column-one-half">
            <h2 id="all-notif-heading" class="govuk-heading-l">
                <i class="fa-solid fa-bell govuk-!-margin-right-2" aria-hidden="true"></i>
                All Notifications
            </h2>
        </div>
        <div class="govuk-grid-column-one-half govuk-!-text-align-right">
            <div class="govuk-button-group civicone-justify-end">
                <button type="button" onclick="openEventsModal()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-list-ul govuk-!-margin-right-1" aria-hidden="true"></i> Events
                </button>
                <button type="button" onclick="toggleNotifSettings()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-gear govuk-!-margin-right-1" aria-hidden="true"></i> Settings
                </button>
                <button type="button" onclick="window.nexusNotifications.markAllRead(this)" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-check-double govuk-!-margin-right-1" aria-hidden="true"></i> Mark All Read
                </button>
            </div>
        </div>
    </div>

    <!-- Events Modal -->
    <dialog id="events-modal" class="govuk-!-padding-6 civicone-dialog" aria-labelledby="events-modal-title">
        <div class="govuk-!-margin-bottom-4">
            <h3 id="events-modal-title" class="govuk-heading-m">Notification Triggers</h3>
            <button type="button" onclick="document.getElementById('events-modal').close()" class="govuk-button govuk-button--secondary civicone-dialog-close" aria-label="Close">
                <i class="fa-solid fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Social Interactions</dt>
                <dd class="govuk-summary-list__value">Posts, Replies, Mentions</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Connections</dt>
                <dd class="govuk-summary-list__value">Friend Requests, Accepted</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Events</dt>
                <dd class="govuk-summary-list__value">Invitations</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Wallet</dt>
                <dd class="govuk-summary-list__value">Payments, Transfers</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Badges</dt>
                <dd class="govuk-summary-list__value">Volunteering milestones, Credits earned</dd>
            </div>
        </dl>
        <div class="govuk-!-margin-top-4">
            <button type="button" onclick="document.getElementById('events-modal').close()" class="govuk-button" data-module="govuk-button">Got it</button>
        </div>
    </dialog>

    <!-- Settings Panel -->
    <div id="notif-settings-panel" class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg" style="display: none;">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <h3 class="govuk-heading-s">Global Email Frequency</h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-2">Default for all notifications</p>
                    <label for="global-freq" class="govuk-visually-hidden">Email frequency</label>
                    <select id="global-freq" onchange="updateNotifSetting('global', 0, this.value)" class="govuk-select">
                        <option value="instant" <?= $globalFreq === 'instant' ? 'selected' : '' ?>>Instant (As it happens)</option>
                        <option value="daily" <?= $globalFreq === 'daily' ? 'selected' : '' ?>>Daily Digest</option>
                        <option value="weekly" <?= $globalFreq === 'weekly' ? 'selected' : '' ?>>Weekly Digest</option>
                        <option value="off" <?= $globalFreq === 'off' ? 'selected' : '' ?>>Off (In-App Only)</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($myGroups)): ?>
                <div class="govuk-grid-column-one-half">
                    <h3 class="govuk-heading-s">Hub Overrides</h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-2">Customize per hub</p>
                    <?php foreach ($myGroups as $grp):
                        $gFreq = $groupSettings[$grp['id']] ?? 'default';
                    ?>
                        <div class="govuk-form-group govuk-!-margin-bottom-2">
                            <label for="hub-freq-<?= $grp['id'] ?>" class="govuk-label govuk-!-font-size-14"><?= htmlspecialchars($grp['name']) ?></label>
                            <select id="hub-freq-<?= $grp['id'] ?>" onchange="updateNotifSetting('group', <?= $grp['id'] ?>, this.value)" class="govuk-select govuk-!-width-full">
                                <option value="default" <?= $gFreq === 'default' ? 'selected' : '' ?>>Use Global</option>
                                <option value="instant" <?= $gFreq === 'instant' ? 'selected' : '' ?>>Instant</option>
                                <option value="daily" <?= $gFreq === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="off" <?= $gFreq === 'off' ? 'selected' : '' ?>>Mute</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification List -->
    <?php
    $allNotifs = $notifications ?? [];
    ?>
    <?php if (empty($allNotifs)): ?>
        <div class="govuk-inset-text">
            <p class="govuk-body-l govuk-!-margin-bottom-2">
                <i class="fa-regular fa-bell-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                <strong>All caught up!</strong>
            </p>
            <p class="govuk-body">You have no notifications at this time.</p>
        </div>
    <?php else: ?>
        <ul class="govuk-list" role="list">
        <?php foreach ($allNotifs as $n): ?>
            <li class="govuk-!-margin-bottom-3 govuk-!-padding-4 <?= $n['is_read'] ? '' : 'govuk-!-font-weight-bold' ?>" style="border: 1px solid #b1b4b6; border-left: 5px solid <?= $n['is_read'] ? '#b1b4b6' : '#1d70b8' ?>;" data-notif-id="<?= $n['id'] ?>">
                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-three-quarters">
                        <p class="govuk-body govuk-!-margin-bottom-2">
                            <?= htmlspecialchars($n['message']) ?>
                        </p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                            <i class="fa-regular fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= date('M j, Y \a\t g:i A', strtotime($n['created_at'])) ?>
                        </p>
                    </div>
                    <div class="govuk-grid-column-one-quarter govuk-!-text-align-right">
                        <div class="govuk-button-group civicone-justify-end">
                            <?php if ($n['link']): ?>
                                <a href="<?= htmlspecialchars($n['link']) ?>" onclick="window.nexusNotifications.markOneRead(<?= $n['id'] ?>)" class="govuk-button govuk-button--secondary" data-module="govuk-button">View</a>
                            <?php endif; ?>
                            <?php if (!$n['is_read']): ?>
                                <button type="button" onclick="window.nexusNotifications.markOneRead(<?= $n['id'] ?>); this.closest('li').style.borderLeftColor='#b1b4b6'; this.closest('li').classList.remove('govuk-!-font-weight-bold'); this.remove();" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" onclick="deleteNotificationDashboard(<?= $n['id'] ?>)" class="govuk-button govuk-button--warning" data-module="govuk-button" aria-label="Delete notification">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script src="/assets/js/civicone-dashboard.js"></script>
<script>
// Toggle settings panel
function toggleNotifSettings() {
    var panel = document.getElementById('notif-settings-panel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
    } else {
        panel.style.display = 'none';
    }
}

// Open events modal
function openEventsModal() {
    document.getElementById('events-modal').showModal();
}

// Initialize dashboard with basePath
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initCivicOneDashboard === 'function') {
        initCivicOneDashboard('<?= $basePath ?>');
    }
});
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
