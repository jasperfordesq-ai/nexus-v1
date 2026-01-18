<?php
// Groups/Hubs Index - Glassmorphism 2025
$pageTitle = "Community Hubs";
$pageSubtitle = "Connect with local groups and communities";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Hubs - Connect with Local Groups');
Nexus\Core\SEO::setDescription('Discover and join local community hubs. Connect with neighbors, share resources, and build stronger communities together.');

// Add groups CSS to header with preload hint for faster loading
$cssVersion = time();
$additionalCSS = '
<link rel="preload" href="/assets/css/nexus-groups.min.css?v=' . $cssVersion . '" as="style">
<link rel="stylesheet" href="/assets/css/nexus-groups.min.css?v=' . $cssVersion . '">';

require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Main content wrapper (main tag opened in header.php) -->
<div class="htb-container-full">
<div id="groups-glass-wrapper" data-page-type="<?= $isHubsPage ? 'hubs' : 'community' ?>">

    <!-- Header Section -->
    <div class="glass-header-card">
        <div class="header-content">
            <div>
                <h1 class="page-title">
                    <?= $isHubsPage ? '🏘️ Local Hubs' : '🎨 Community Groups' ?>
                </h1>
                <p class="page-subtitle">
                    <?= $isHubsPage
                        ? 'Connect with your neighborhood and local community'
                        : 'Join interest-based groups and meet like-minded people' ?>
                </p>
            </div>
            <?php if ($canCreateGroup ?? false): ?>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/create" class="glass-btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    Create <?= $isHubsPage ? 'Hub' : 'Group' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="glass-search-card">
        <form method="GET" class="search-form">
            <div class="search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input
                    type="text"
                    name="q"
                    class="glass-search-input"
                    placeholder="Search <?= $isHubsPage ? 'hubs' : 'groups' ?>..."
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                >
            </div>
            <?php if (!$isHubsPage && !empty($groupTypes)): ?>
                <select name="type" class="glass-select" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($groupTypes as $type): ?>
                        <option value="<?= $type['id'] ?>" <?= ($selectedType ?? '') == $type['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button type="submit" class="glass-btn-primary">
                <i class="fa-solid fa-search"></i> Search
            </button>
        </form>
    </div>

    <!-- Featured Groups (Hubs only) -->
    <?php if ($isHubsPage && !empty($featuredGroups)): ?>
        <div class="featured-section">
            <h2 class="section-title">
                <i class="fa-solid fa-star"></i> Featured Hubs
            </h2>
            <div class="groups-grid">
                <?php foreach ($featuredGroups as $group): ?>
                    <?= renderGroupCard($group, \Nexus\Core\TenantContext::getBasePath()) ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- All Groups -->
    <div class="groups-section">
        <?php if (!empty($groups)): ?>
            <h2 class="section-title">
                <?= $isHubsPage ? 'All Local Hubs' : 'All Community Groups' ?>
                <span class="count-badge"><?= count($groups) ?></span>
            </h2>
            <div class="groups-grid">
                <?php foreach ($groups as $group): ?>
                    <?= renderGroupCard($group, \Nexus\Core\TenantContext::getBasePath()) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <?= $isHubsPage ? '🏘️' : '🎨' ?>
                </div>
                <h3>No <?= $isHubsPage ? 'hubs' : 'groups' ?> found</h3>
                <p>
                    <?php if (!empty($_GET['q'])): ?>
                        Try adjusting your search terms
                    <?php else: ?>
                        Be the first to create one!
                    <?php endif; ?>
                </p>
                <?php if ($canCreateGroup ?? false): ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/create" class="glass-btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Create <?= $isHubsPage ? 'Hub' : 'Group' ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</div>
<!-- End htb-container-full (main tag closed in footer.php) -->

<?php
// Render group cards function
function renderGroupCard($group, $basePath) {
    ob_start();
?>
    <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="glass-group-card">
        <div class="group-card-image-container">
            <?php if (!empty($group['image_url'])): ?>
                <img src="<?= htmlspecialchars($group['image_url']) ?>" alt="<?= htmlspecialchars($group['name']) ?>" class="group-image" loading="lazy">
            <?php else: ?>
                <div class="group-image-placeholder">
                    <i class="fa-solid fa-users"></i>
                </div>
            <?php endif; ?>

            <?php if (($group['is_featured'] ?? false) || (($group['privacy'] ?? 'public') === 'private')): ?>
                <div class="badge-overlay">
                    <?php if ($group['is_featured'] ?? false): ?>
                        <span class="badge-featured">⭐ Featured</span>
                    <?php endif; ?>
                    <?php if (($group['privacy'] ?? 'public') === 'private'): ?>
                        <span class="badge-private"><i class="fa-solid fa-lock"></i> Private</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="group-card-content">
            <h3 class="group-title"><?= htmlspecialchars($group['name']) ?></h3>

            <?php if (!empty($group['description'])): ?>
                <p class="group-description">
                    <?= htmlspecialchars(mb_strlen($group['description']) > 100 ? mb_substr($group['description'], 0, 100) . '...' : $group['description']) ?>
                </p>
            <?php endif; ?>

            <div class="group-meta">
                <div class="member-count">
                    <i class="fa-solid fa-user-group"></i>
                    <span><?= $group['member_count'] ?? 0 ?> Member<?= ($group['member_count'] ?? 0) !== 1 ? 's' : '' ?></span>
                </div>
                <span class="visit-link">
                    Visit <i class="fa-solid fa-arrow-right"></i>
                </span>
            </div>
        </div>
    </a>
<?php
    return ob_get_clean();
}
?>

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
            alert('You are offline. Please connect to the internet to search.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.glass-btn-primary, .nexus-smart-btn, .quick-action-btn, button').forEach(btn => {
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

// Debug: Verify styles are loaded
console.log('✅ Groups v3.1 Balanced & Elegant Styles Loaded');
const wrapper = document.getElementById('groups-glass-wrapper');
if (wrapper) {
    const pageType = wrapper.getAttribute('data-page-type');
    const styles = getComputedStyle(wrapper);
    const primaryColor = styles.getPropertyValue('--primary-color').trim();
    const primaryGradientStart = styles.getPropertyValue('--primary-gradient-start').trim();
    console.log(`🎨 Page Type: ${pageType}`);
    console.log(`🎨 Primary Color: ${primaryColor}`);
    console.log(`🎨 Gradient Start: ${primaryGradientStart}`);
    console.log(`🎨 Theme: ${document.documentElement.getAttribute('data-theme')}`);
    console.log(`💎 Elegant Frosted Glass: ENABLED`);
    console.log(`🌈 Subtle Tri-Color Gradients: ENABLED`);
}
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
