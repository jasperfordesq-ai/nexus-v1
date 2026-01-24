<?php
/**
 * Federation Messages Thread
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federated Chat";
$hideHero = true;

Nexus\Core\SEO::setTitle($pageTitle);
Nexus\Core\SEO::setDescription('Private conversation with a member from a partner timebank.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$messages = $messages ?? [];
$otherUser = $otherUser ?? [];
$otherTenantId = $otherTenantId ?? 0;
$canMessage = $canMessage ?? false;
$cannotMessageReason = $cannotMessageReason ?? '';

$otherName = $otherUser['name'] ?? 'Member';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($otherName) . '&background=1d70b8&color=fff&size=200';
$otherAvatar = !empty($otherUser['avatar_url']) ? $otherUser['avatar_url'] : $fallbackAvatar;
$currentUserId = $_SESSION['user_id'] ?? 0;
?>

<div class="govuk-width-container">
    <!-- Offline Banner -->
    <div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-display-none" id="offlineBanner" role="alert" aria-live="polite" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                No internet connection
            </p>
        </div>
    </div>

    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/messages" class="govuk-back-link govuk-!-margin-top-4">
        Back to Inbox
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Thread Header -->
                <div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="background: #fff; border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <img src="<?= htmlspecialchars($otherAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt=""
                             style="width: 56px; height: 56px; border-radius: 50%; object-fit: cover;"
                             loading="lazy">
                        <div style="flex: 1;">
                            <h1 class="govuk-heading-m govuk-!-margin-bottom-1"><?= htmlspecialchars($otherName) ?></h1>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                <?= htmlspecialchars($otherUser['tenant_name'] ?? 'Partner Timebank') ?>
                            </p>
                        </div>
                        <a href="<?= $basePath ?>/federation/members/<?= $otherUser['id'] ?? 0 ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                            <i class="fa-solid fa-user govuk-!-margin-right-1" aria-hidden="true"></i>
                            View Profile
                        </a>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="govuk-!-padding-4 govuk-!-margin-bottom-4 civicone-panel-bg" style="min-height: 300px; max-height: 500px; overflow-y: auto;" id="messages-container" role="log" aria-label="Message history" aria-live="polite">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $msg):
                            $isSent = $msg['message_type'] === 'sent';
                            $msgTime = strtotime($msg['created_at']);
                            $isoTime = date('c', $msgTime);
                            $displayTime = date('M j, g:i a', $msgTime);
                            $bgColor = $isSent ? '#1d70b8' : '#fff';
                            $textColor = $isSent ? '#fff' : '#0b0c0c';
                            $align = $isSent ? 'flex-end' : 'flex-start';
                        ?>
                            <div style="display: flex; flex-direction: column; align-items: <?= $align ?>; margin-bottom: 16px;">
                                <div style="max-width: 80%; padding: 12px 16px; background: <?= $bgColor ?>; color: <?= $textColor ?>; border-radius: 8px; <?= $isSent ? '' : 'border: 1px solid #b1b4b6;' ?>">
                                    <?= nl2br(htmlspecialchars($msg['body'] ?? '')) ?>
                                </div>
                                <time class="govuk-body-s" style="color: #505a5f; margin-top: 4px;" datetime="<?= $isoTime ?>">
                                    <?= $displayTime ?>
                                </time>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #fff; border: 1px solid #b1b4b6;">
                            <i class="fa-solid fa-comment-dots fa-2x govuk-!-margin-bottom-2" style="color: #505a5f;" aria-hidden="true"></i>
                            <p class="govuk-body govuk-!-margin-bottom-0">No messages yet. Start the conversation!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Compose Form -->
                <?php if ($canMessage): ?>
                    <form action="<?= $basePath ?>/federation/messages/send" method="POST" class="govuk-!-margin-bottom-0">
                        <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                        <input type="hidden" name="receiver_tenant_id" value="<?= $otherTenantId ?>">

                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-visually-hidden" for="message-input">Type your message</label>
                            <div style="display: flex; gap: 12px;">
                                <textarea name="body"
                                          class="govuk-textarea govuk-!-margin-bottom-0"
                                          style="flex: 1; resize: none;"
                                          placeholder="Type your message..."
                                          required
                                          rows="2"
                                          id="message-input"></textarea>
                                <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" style="align-self: flex-end;">
                                    <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                                    Send
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="govuk-warning-text">
                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                        <strong class="govuk-warning-text__text">
                            <span class="govuk-visually-hidden">Warning</span>
                            <?= htmlspecialchars($cannotMessageReason ?: 'Messaging is not available with this member.') ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="/assets/js/federation-thread.js?v=<?= time() ?>"></script>
<script>
(function() {
    'use strict';
    var banner = document.getElementById('offlineBanner');
    function updateOffline(offline) {
        if (banner) banner.classList.toggle('govuk-!-display-none', !offline);
    }
    window.addEventListener('online', function() { updateOffline(false); });
    window.addEventListener('offline', function() { updateOffline(true); });
    if (!navigator.onLine) updateOffline(true);

    // Scroll to bottom of messages
    var container = document.getElementById('messages-container');
    if (container) container.scrollTop = container.scrollHeight;
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
