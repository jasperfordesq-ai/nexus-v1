<?php
$hTitle = 'Delete Goal';
require __DIR__ . '/../../layouts/header.php';
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

    .htb-card {
        animation: fadeInUp 0.4s ease-out;
    }

    /* Button Press States */
    .htb-btn:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .htb-btn,
    button {
        min-height: 44px;
    }

    /* Focus Visible */
    .htb-btn:focus-visible,
    button:focus-visible,
    a:focus-visible {
        outline: 3px solid rgba(239, 68, 68, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive Enhancements */
    @media (max-width: 768px) {
        .htb-btn,
        button {
            min-height: 48px;
        }
    }

    /* ========================================
       DARK MODE FOR DELETE GOAL
       ======================================== */

    [data-theme="dark"] .htb-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .htb-card h2 {
        color: #f1f5f9;
    }

    [data-theme="dark"] .htb-card p[style*="color: #555"] {
        color: #94a3b8 !important;
    }

    [data-theme="dark"] .htb-btn-secondary {
        background: rgba(51, 65, 85, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #e2e8f0 !important;
    }
</style>

<div class="htb-wrapper" style="max-width: 600px; margin: 40px auto; padding: 0 20px;">
    <div class="htb-card">
        <div class="htb-card-body" style="text-align: center; padding: 40px 20px;">
            <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
            <h2 style="margin-top:0;">Delete Goal?</h2>
            <p style="font-size: 1.1em; color: #555; margin-bottom: 30px;">
                Are you sure you want to delete <strong>#<?= htmlspecialchars($goal['id']) ?> <?= htmlspecialchars($goal['title']) ?></strong>?
                <br>This action cannot be undone.
            </p>

            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>/delete" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>

                <div style="display: flex; justify-content: center; gap: 15px;">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>" class="htb-btn htb-btn-secondary">Cancel</a>
                    <button type="submit" class="htb-btn" style="background: #ef4444; color: white;">Yes, Delete Goal</button>
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
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.htb-btn, button').forEach(btn => {
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
        meta.content = '#ef4444';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#ef4444');
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

<?php require __DIR__ . '/../../layouts/footer.php'; ?>