<?php
// Goals Index - Glassmorphism 2025
$pageTitle = "Community Goals";
$pageSubtitle = "Set goals and find accountability buddies";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Goals - Track Progress & Find Buddies');
Nexus\Core\SEO::setDescription('Set personal goals, track your progress, and connect with accountability buddies in your community.');

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<div class="htb-container-full">
<div id="goals-glass-wrapper">

    <!-- Offline Banner -->
    <div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
        <i class="fa-solid fa-wifi-slash"></i>
        <span>No internet connection</span>
    </div>


    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Goal Tracker</h1>
        <p class="nexus-welcome-subtitle">Set personal goals, track your progress, and connect with accountability buddies who can help you succeed.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/goals?view=my-goals" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-bullseye"></i>
                <span>My Goals</span>
            </a>
            <a href="<?= $basePath ?>/goals?view=finder" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-user-group"></i>
                <span>Find a Buddy</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/goals?view=completed" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-circle-check"></i>
                <span>Completed</span>
            </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/compose?type=goal" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-plus"></i>
                <span>Set Goal</span>
            </a>
        </div>
    </div>

    <!-- Glass Info Card -->
    <div class="glass-info-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 8px 0;">
                    <?= ($view ?? 'my-goals') === 'my-goals' ? 'Your Goals' : 'Find Accountability Buddies' ?>
                </h2>
                <p style="font-size: 0.95rem; color: var(--htb-text-muted); margin: 0;">
                    <?= ($view ?? 'my-goals') === 'my-goals'
                        ? count($goals ?? []) . ' goals in progress'
                        : 'Connect with others working on similar goals' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-bullseye" style="color: #84cc16; font-size: 1.1rem;"></i>
        <h2><?= ($view ?? 'my-goals') === 'my-goals' ? 'Active Goals' : 'Goals Looking for Buddies' ?></h2>
    </div>

    <!-- Goals Grid -->
    <div class="goals-grid">
        <?php if (!empty($goals)): ?>
            <?php foreach ($goals as $g): ?>
                <a href="<?= $basePath ?>/goals/<?= $g['id'] ?>" class="glass-goal-card">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="header-content">
                            <div class="goal-icon">
                                <i class="fa-solid fa-bullseye"></i>
                            </div>
                            <span class="status-badge <?= strtolower($g['status'] ?? 'active') ?>">
                                <?= ucfirst($g['status'] ?? 'Active') ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="goal-title">
                            <?= htmlspecialchars($g['title']) ?>
                        </h3>

                        <?php if (($view ?? '') === 'finder' && !empty($g['author_name'])): ?>
                        <div class="goal-author">
                            <i class="fa-solid fa-user"></i>
                            by <?= htmlspecialchars($g['author_name']) ?>
                        </div>
                        <?php endif; ?>

                        <p class="goal-desc">
                            <?= htmlspecialchars(substr($g['description'] ?? '', 0, 120)) ?>...
                        </p>
                    </div>

                    <div class="card-footer">
                        <?php if (($view ?? 'my-goals') === 'my-goals'): ?>
                        <div class="buddy-info">
                            <i class="fa-solid fa-handshake"></i>
                            <span>Buddy: </span>
                            <?php if (!empty($g['mentor_name'])): ?>
                                <strong><?= htmlspecialchars($g['mentor_name']) ?></strong>
                            <?php else: ?>
                                <span style="opacity: 0.7;">None yet</span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="buddy-info">
                            <i class="fa-solid fa-calendar"></i>
                            <span><?= !empty($g['created_at']) ? date('M d, Y', strtotime($g['created_at'])) : 'Recently' ?></span>
                        </div>
                        <?php endif; ?>
                        <span class="view-link">
                            View <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">🎯</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No Goals Found</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">
                    <?= ($view ?? 'my-goals') === 'my-goals'
                        ? 'Start your journey today. Set a new goal!'
                        : 'No active buddy requests found right now.' ?>
                </p>
                <?php if (($view ?? 'my-goals') === 'my-goals'): ?>
                <a href="<?= $basePath ?>/compose?type=goal" class="glass-btn-primary">
                    <i class="fa-solid fa-plus"></i> Get Started
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #goals-glass-wrapper -->
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

// Button Press States
document.querySelectorAll('.htb-btn, .glass-btn-primary, .nexus-smart-btn, .quick-action-btn, button').forEach(btn => {
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
        meta.content = '#84cc16';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#84cc16');
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

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
