<?php
// Phoenix View: Show Discussion (Mobile-First Design)
// Path: views/modern/groups/discussions/show.php

$hTitle = 'Discussion';
$hSubtitle = htmlspecialchars($group['name']);
$hGradient = 'htb-hero-gradient-hub';

require __DIR__ . '/../../../layouts/header.php';

// Determine current user for bubble alignment
$currentUserId = $_SESSION['user_id'] ?? 0;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Mobile-First Discussion Styles -->

<div class="htb-container discussion-page-wrapper">

    <!-- Back Navigation -->
    <div class="discussion-back-nav">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=discussions" class="discussion-back-btn">
            <i class="fa-solid fa-arrow-left"></i>
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
                <button class="discussion-action-btn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </button>
                <button class="discussion-action-btn" title="More options">
                    <i class="fa-solid fa-ellipsis"></i>
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
                                <div class="discussion-avatar-placeholder">
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
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/<?= $discussion['id'] ?>/reply" method="POST" class="discussion-reply-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <textarea name="content" class="discussion-reply-input" rows="1" placeholder="Type your message..." required oninput="this.style.height = ''; this.style.height = Math.min(this.scrollHeight, 120) + 'px'"></textarea>
                    <button type="submit" class="discussion-reply-btn">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            <?php else: ?>
                <div class="discussion-locked-msg">
                    <i class="fa-solid fa-lock"></i> Only members can reply.
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>">Join Hub</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Auto-scroll to bottom -->
<script>
    const stream = document.getElementById('chatStream');
    if (stream) {
        stream.scrollTop = stream.scrollHeight;
    }

// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to post a reply.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.discussion-reply-btn, .discussion-action-btn, .discussion-back-btn, button').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#db2777';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../../layouts/footer.php'; ?>
