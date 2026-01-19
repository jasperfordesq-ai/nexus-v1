<?php
// Resources Index - Glassmorphism 2025
$pageTitle = "Resource Library";
$pageSubtitle = "Tools, guides, and documents for the community";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Resource Library - Community Guides & Tools');
Nexus\Core\SEO::setDescription('Access community resources, guides, templates, and tools. Share knowledge and download helpful documents.');

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Main content wrapper (main tag opened in header.php) -->
<div class="htb-container-full">
<div id="resources-glass-wrapper">


    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Resource Library</h1>
        <p class="nexus-welcome-subtitle">Access community resources, guides, templates, and tools. Share knowledge and download helpful documents.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/resources" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-book"></i>
                <span>All Resources</span>
            </a>
            <a href="<?= $basePath ?>/resources?sort=popular" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-fire"></i>
                <span>Most Downloaded</span>
            </a>
            <a href="<?= $basePath ?>/resources?sort=recent" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-clock"></i>
                <span>Recently Added</span>
            </a>
            <a href="<?= $basePath ?>/resources/create" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-upload"></i>
                <span>Upload File</span>
            </a>
        </div>
    </div>

    <!-- Glass Search Card -->
    <div class="glass-search-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 8px 0;">Library Files</h2>
                <p style="font-size: 0.95rem; color: var(--htb-text-muted); margin: 0;">
                    <?= count($resources ?? []) ?> resources available
                </p>
            </div>
            <a href="<?= $basePath ?>/resources/create" class="glass-btn-primary">
                <i class="fa-solid fa-upload"></i> Upload File
            </a>
        </div>

        <!-- Category Pills -->
        <div class="category-pills">
            <a href="<?= $basePath ?>/resources" class="category-pill <?= !isset($_GET['cat']) ? 'active' : '' ?>">
                All
            </a>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <a href="?cat=<?= $cat['id'] ?>" class="category-pill <?= (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-folder-open" style="color: #6366f1; font-size: 1.1rem;"></i>
        <h2>Available Resources</h2>
    </div>

    <!-- Resources Grid -->
    <div class="resources-grid">
        <!-- Upload Card -->
        <div class="glass-upload-card">
            <div style="text-align: center; padding: 30px;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: #6366f1;"></i>
                </div>
                <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0 0 10px; color: var(--htb-text-main);">Share a Resource</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px; font-size: 0.95rem;">
                    Help the community with guides and tools.
                </p>
                <a href="<?= $basePath ?>/resources/create" class="glass-btn-primary">
                    <i class="fa-solid fa-upload"></i> Upload File
                </a>
            </div>
        </div>

        <?php if (!empty($resources)): ?>
            <?php foreach ($resources as $res): ?>
                <?php
                $icon = '📄';
                if (strpos($res['file_type'] ?? '', 'image') !== false) $icon = '🖼️';
                if (strpos($res['file_type'] ?? '', 'zip') !== false) $icon = '📦';
                if (strpos($res['file_type'] ?? '', 'pdf') !== false) $icon = '📕';
                if (strpos($res['file_type'] ?? '', 'doc') !== false) $icon = '📝';
                if (strpos($res['file_type'] ?? '', 'xls') !== false) $icon = '📊';
                if (strpos($res['file_type'] ?? '', 'video') !== false) $icon = '🎬';

                $size = round(($res['file_size'] ?? 0) / 1024) . ' KB';
                if (($res['file_size'] ?? 0) > 1024 * 1024) $size = round(($res['file_size'] ?? 0) / 1024 / 1024, 1) . ' MB';

                $isOwner = isset($_SESSION['user_id']) && ($res['user_id'] ?? 0) == $_SESSION['user_id'];
                $isAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'];
                ?>
                <article class="glass-resource-card">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="header-content">
                            <span class="file-icon"><?= $icon ?></span>
                            <div class="file-meta">
                                <div class="file-size-badge"><?= $size ?></div>
                                <?php if (!empty($res['category_name'])): ?>
                                <div class="file-category"><?= htmlspecialchars($res['category_name']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="resource-title">
                            <?= htmlspecialchars($res['title']) ?>
                        </h3>

                        <p class="resource-desc">
                            <?= htmlspecialchars(substr($res['description'] ?? '', 0, 100)) ?>...
                        </p>
                    </div>

                    <div class="card-footer">
                        <div class="uploader-info">
                            <i class="fa-solid fa-user"></i>
                            <?= htmlspecialchars($res['uploader_name'] ?? 'Unknown') ?>
                        </div>
                        <?php if ($isOwner || $isAdmin): ?>
                            <a href="<?= $basePath ?>/resources/<?= $res['id'] ?>/edit" style="color: #6366f1; text-decoration: none; font-weight: 600;">
                                <i class="fa-solid fa-pen"></i> Edit
                            </a>
                        <?php else: ?>
                            <div class="download-stats">
                                <i class="fa-solid fa-download"></i>
                                <?= $res['downloads'] ?? 0 ?> downloads
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= $basePath ?>/resources/<?= $res['id'] ?>/download" class="download-btn">
                        <i class="fa-solid fa-download"></i> Download File
                    </a>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">📚</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">Library is Empty</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Share the first guide or toolkit!</p>
                <a href="<?= $basePath ?>/resources/create" class="glass-btn-primary">
                    <i class="fa-solid fa-upload"></i> Upload Resource
                </a>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #resources-glass-wrapper -->
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
document.querySelectorAll('.htb-btn, .glass-btn-primary, .nexus-smart-btn, .quick-action-btn, .download-btn, button').forEach(btn => {
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
        meta.content = '#6366f1';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#6366f1');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// Download links now go to a dedicated download page with countdown
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
