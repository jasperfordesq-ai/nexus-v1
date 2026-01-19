<?php
// My Groups/Hubs - Glassmorphism 2025
$pageTitle = "My Hubs";
$pageSubtitle = "Hubs you have joined";
$hideHero = true;

require __DIR__ . '/../../layouts/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
<div id="my-groups-glass-wrapper">


    <!-- Page Header -->
    <div class="page-header">
        <h1><span>👥</span> My Hubs</h1>
        <p>Community hubs you have joined</p>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="quick-action-btn">
            <i class="fa-solid fa-compass"></i> Browse All Hubs
        </a>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/dashboard?tab=groups" class="quick-action-btn">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
    </div>

    <!-- Groups Grid -->
    <div class="groups-grid">
        <?php if (empty($myGroups)): ?>
            <div class="empty-state">
                <span class="empty-icon">🏘️</span>
                <h3>No hubs yet</h3>
                <p>You haven't joined any community hubs. Explore and find groups that match your interests!</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="browse-btn">
                    <i class="fa-solid fa-compass"></i> Browse Hubs
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($myGroups as $group): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>" class="group-card">
                    <!-- Cover Image -->
                    <?php
                    $displayImage = !empty($group['cover_image_url']) ? $group['cover_image_url'] : ($group['image_url'] ?? '');
                    ?>
                    <?php if (!empty($displayImage)): ?>
                        <div class="card-cover">
                            <img src="<?= htmlspecialchars($displayImage) ?>" loading="lazy" alt="<?= htmlspecialchars($group['name']) ?>">
                            <span class="card-badge">MEMBER</span>
                        </div>
                    <?php else: ?>
                        <div class="card-cover card-cover-gradient">
                            <i class="fa-solid fa-users-rectangle"></i>
                            <span class="card-badge">MEMBER</span>
                        </div>
                    <?php endif; ?>

                    <!-- Card Body -->
                    <div class="card-body">
                        <h3><?= htmlspecialchars($group['name']) ?></h3>
                        <p><?= htmlspecialchars(substr($group['description'] ?? 'A community hub for members to connect and collaborate.', 0, 100)) ?>...</p>

                        <div class="card-meta">
                            <div class="member-count">
                                <i class="fa-solid fa-user-group"></i>
                                <span><?= $group['member_count'] ?? 0 ?> members</span>
                            </div>
                            <span class="visit-btn">
                                Enter <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- #my-groups-glass-wrapper -->
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
document.querySelectorAll('.quick-action-btn, .visit-btn, .browse-btn, button').forEach(btn => {
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

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
