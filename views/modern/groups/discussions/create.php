<?php
// Phoenix View: Create Discussion - Mobile Optimized
// Parent: Group Show
// Path: views/modern/groups/discussions/create.php

$hTitle = 'Start a Discussion';
$hSubtitle = htmlspecialchars($group['name']);
$hGradient = 'htb-hero-gradient-hub';

require __DIR__ . '/../../../layouts/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>


<div class="htb-container discussion-create-wrapper">

    <!-- Back Navigation -->
    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=discussions" class="discussion-back-btn">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Hub</span>
    </a>

    <div class="htb-card discussion-create-card">
        <!-- Header -->
        <div class="discussion-create-header">
            <h2>
                <i class="fa-regular fa-comments"></i>
                New Discussion
            </h2>
            <p>Start a conversation with the community.</p>
        </div>

        <!-- Form Body -->
        <div class="discussion-create-body">
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/store" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>

                <!-- Title Input -->
                <div style="margin-bottom: 24px;">
                    <label class="discussion-form-label">Topic Title</label>
                    <input type="text" name="title" class="discussion-form-input"
                        placeholder="What's on your mind?" required autofocus>
                </div>

                <!-- Message Input -->
                <div style="margin-bottom: 24px;">
                    <label class="discussion-form-label">Your Message</label>
                    <textarea name="content" class="discussion-form-input" rows="6"
                        placeholder="Share your thoughts, ask a question, or start a debate..." required></textarea>
                    <div class="discussion-form-hint">Markdown supported</div>
                </div>

                <!-- Actions -->
                <div class="discussion-form-actions">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=discussions" class="discussion-cancel-btn">
                        Cancel
                    </a>
                    <button type="submit" class="discussion-submit-btn">
                        <i class="fa-regular fa-paper-plane"></i>
                        <span>Post Discussion</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
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
            alert('You are offline. Please connect to the internet to create a discussion.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.discussion-submit-btn, .discussion-back-btn, button').forEach(btn => {
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