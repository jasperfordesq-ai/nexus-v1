<?php
/**
 * Template C: Detail Page
 * Show Group Discussion
 * WCAG 2.1 AA Compliant
 * Parent: Group Show
 * Path: views/civicone/groups/discussions/show.php
 */

$hTitle = 'Discussion';
$hSubtitle = htmlspecialchars($group['name']);
$hGradient = 'htb-hero-gradient-hub';

require __DIR__ . '/../../../layouts/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();

// Determine current user for bubble alignment
$currentUserId = $_SESSION['user_id'] ?? 0;
?>

<link rel="stylesheet" href="/assets/css/purged/civicone-groups-discussions-show.min.css?v=<?= time() ?>">

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">
        <div class="htb-container discussion-page-wrapper">

            <!-- Back Navigation -->
            <div class="discussion-back-nav">
                <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=discussions" class="discussion-back-btn">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Back to Hub</span>
                </a>
            </div>

            <!-- MAIN CHAT CARD -->
            <div class="htb-card discussion-chat-card">

                <!-- HEADER -->
                <div class="discussion-header">
                    <div class="discussion-header-info">
                        <h1 class="discussion-title"><?= htmlspecialchars($discussion['title']) ?></h1>
                        <div class="discussion-meta">
                            Started by <strong><?= htmlspecialchars($discussion['author_name']) ?></strong> &bull; <?= count($posts) ?> messages
                        </div>
                    </div>

                    <div class="discussion-header-actions">
                        <button class="discussion-action-btn" title="Notifications" aria-label="Notifications">
                            <i class="fa-solid fa-bell" aria-hidden="true"></i>
                        </button>
                        <button class="discussion-action-btn" title="More options" aria-label="More options">
                            <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <!-- CHAT STREAM -->
                <div id="chatStream" class="discussion-stream">
                    <?php foreach ($posts as $post):
                        $isMe = ($post['user_id'] == $currentUserId);
                        $msgClass = $isMe ? 'me' : 'others';
                    ?>
                        <div class="discussion-message <?= $msgClass ?>">
                            <?php if (!$isMe): ?>
                                <div>
                                    <?php if (!empty($post['author_avatar'])): ?>
                                        <img src="<?= htmlspecialchars($post['author_avatar']) ?>" loading="lazy" class="discussion-avatar" alt="">
                                    <?php else: ?>
                                        <div class="discussion-avatar-placeholder" aria-hidden="true">
                                            <?= strtoupper(substr($post['author_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="discussion-bubble-wrap">
                                <?php if (!$isMe): ?>
                                    <div class="discussion-author"><?= htmlspecialchars($post['author_name']) ?></div>
                                <?php endif; ?>

                                <div class="discussion-bubble <?= $msgClass ?>">
                                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                                    <div class="discussion-time">
                                        <?= date('g:i A', strtotime($post['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- REPLY DOCK -->
                <div class="discussion-reply-dock">
                    <?php if (isset($_SESSION['user_id']) && $isMember): ?>
                        <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/discussions/<?= $discussion['id'] ?>/reply" method="POST" class="discussion-reply-form">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <textarea name="content" id="reply-input" class="discussion-reply-input" rows="1" placeholder="Type your message..." required oninput="this.style.height = ''; this.style.height = Math.min(this.scrollHeight, 120) + 'px'" aria-label="Message reply"></textarea>
                            <button type="submit" class="discussion-reply-btn" aria-label="Send message">
                                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="discussion-locked-msg">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i> Only members can reply.
                            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>">Join Hub</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="/assets/js/civicone-groups-discussions-show.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/../../../layouts/footer.php'; ?>
