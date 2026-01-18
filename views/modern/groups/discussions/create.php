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

<style>
    /* ============================================
       GOLD STANDARD - Native App Features
       ============================================ */

    /* Offline Banner */
    .offline-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 10001;
        padding: 12px 20px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transform: translateY(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .offline-banner.visible {
        transform: translateY(0);
    }

    /* Content Reveal Animation */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .discussion-create-card {
        animation: fadeInUp 0.4s ease-out;
    }

    /* Button Press States */
    .discussion-submit-btn:active,
    .discussion-back-btn:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .discussion-submit-btn,
    .discussion-back-btn,
    .discussion-cancel-btn,
    .discussion-form-input,
    button {
        min-height: 44px;
    }

    .discussion-form-input {
        font-size: 16px !important; /* Prevent iOS zoom */
    }

    /* Focus Visible */
    .discussion-submit-btn:focus-visible,
    .discussion-back-btn:focus-visible,
    .discussion-cancel-btn:focus-visible,
    .discussion-form-input:focus-visible,
    button:focus-visible,
    a:focus-visible {
        outline: 3px solid rgba(219, 39, 119, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive - Gold Standard */
    @media (max-width: 768px) {
        .discussion-submit-btn,
        .discussion-back-btn,
        .discussion-cancel-btn,
        button {
            min-height: 48px;
        }
    }

    /* ============================================
       CREATE DISCUSSION - MOBILE FIRST
       ============================================ */
    .discussion-create-wrapper {
        padding-top: 120px;
        padding-bottom: 40px;
        max-width: 800px;
        position: relative;
        z-index: 20;
    }

    .discussion-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 12px;
        color: #64748b;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 16px;
        transition: all 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .discussion-back-btn:hover {
        background: white;
        color: #db2777;
    }

    .discussion-create-card {
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-radius: 20px;
    }

    .discussion-create-header {
        background: linear-gradient(135deg, #fbcfe8 0%, #f472b6 100%);
        padding: 24px;
        color: #831843;
    }

    .discussion-create-header h2 {
        margin: 0;
        font-weight: 800;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .discussion-create-header p {
        margin: 6px 0 0 0;
        opacity: 0.8;
        font-size: 0.9rem;
    }

    .discussion-create-body {
        padding: 24px;
    }

    .discussion-form-label {
        display: block;
        font-weight: 600;
        color: var(--htb-text-main);
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .discussion-form-input {
        width: 100%;
        padding: 14px 16px;
        font-size: 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background: white;
        color: var(--htb-text-main);
        -webkit-appearance: none;
    }

    .discussion-form-input:focus {
        outline: none;
        border-color: #db2777;
        box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.1);
    }

    .discussion-form-input::placeholder {
        color: #9ca3af;
    }

    textarea.discussion-form-input {
        resize: vertical;
        min-height: 140px;
        line-height: 1.6;
    }

    .discussion-form-hint {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 6px;
        text-align: right;
    }

    .discussion-form-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 12px;
        padding-top: 20px;
        border-top: 1px solid #f3f4f6;
    }

    .discussion-cancel-btn {
        text-decoration: none;
        color: #6b7280;
        font-weight: 600;
        padding: 12px 20px;
        border-radius: 12px;
        transition: all 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .discussion-cancel-btn:hover {
        background: #f3f4f6;
    }

    .discussion-submit-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #db2777, #ec4899);
        border: none;
        padding: 14px 28px;
        font-size: 1rem;
        font-weight: 600;
        color: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(219, 39, 119, 0.3);
        cursor: pointer;
        transition: all 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .discussion-submit-btn:active {
        transform: scale(0.98);
    }

    [data-theme="dark"] .discussion-form-input {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    [data-theme="dark"] .discussion-back-btn {
        background: rgba(30, 41, 59, 0.9);
        color: #cbd5e1;
    }

    /* Mobile Optimizations */
    @media (max-width: 768px) {
        .discussion-create-wrapper {
            padding: 100px 12px 100px 12px;
        }

        .discussion-create-card {
            border-radius: 16px;
        }

        .discussion-create-header {
            padding: 20px 16px;
        }

        .discussion-create-header h2 {
            font-size: 1.25rem;
        }

        .discussion-create-body {
            padding: 20px 16px;
        }

        .discussion-form-input {
            padding: 12px 14px;
            font-size: 16px; /* Prevents iOS zoom */
        }

        .discussion-form-actions {
            flex-direction: column-reverse;
            gap: 10px;
        }

        .discussion-cancel-btn,
        .discussion-submit-btn {
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .discussion-back-btn {
            padding: 8px 14px;
            font-size: 0.85rem;
        }
    }
</style>

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