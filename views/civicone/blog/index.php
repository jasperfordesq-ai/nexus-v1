<?php
// CivicOne View: Blog Index - WCAG 2.1 AA Compliant
// CSS extracted to civicone-blog.css
$pageTitle = 'Latest News';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div id="news-holo-wrapper">
    <!-- Holographic Orbs (decorative) -->
    <div class="holo-orb holo-orb-1" aria-hidden="true"></div>
    <div class="holo-orb holo-orb-2" aria-hidden="true"></div>
    <div class="holo-orb holo-orb-3" aria-hidden="true"></div>

    <div class="news-inner">

        <!-- Hero Section -->
        <section class="news-hero-section">
            <div class="news-hero-badge">
                <i class="fa-solid fa-newspaper" aria-hidden="true"></i>
                <span>News & Updates</span>
            </div>
            <h1 class="news-hero-title">Latest News</h1>
            <p class="news-hero-subtitle">Stay informed with the latest updates, stories, and announcements from our community.</p>
            <div class="news-hero-divider" aria-hidden="true"></div>
        </section>

        <?php if (empty($posts)): ?>
            <!-- Empty State -->
            <div class="news-empty-state">
                <div class="news-empty-icon">
                    <i class="fa-solid fa-newspaper" aria-hidden="true"></i>
                </div>
                <h3 class="news-empty-title">No updates yet</h3>
                <p class="news-empty-text">Check back soon for the latest news and announcements.</p>
            </div>
        <?php else: ?>
            <!-- News List (WCAG 2.1 AA - List layout for accessibility) -->
            <ul class="news-list" id="news-grid-container" role="list">
                <?php require __DIR__ . '/partials/feed-items.php'; ?>
            </ul>

            <!-- Infinite Scroll Sentinel -->
            <div id="news-scroll-sentinel" class="news-scroll-sentinel" aria-hidden="true">
                <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Page-specific JavaScript -->
<script src="/assets/js/civicone-blog-index.min.js" defer></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
