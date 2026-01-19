<?php
// Phoenix View: Blog Show - Holographic Glassmorphism 2025
$pageTitle = $post['title'];
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Calculate reading time
$wordCount = str_word_count(strip_tags($post['content']));
$readingTime = max(1, ceil($wordCount / 200));
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="article-holo-wrapper">
    <!-- Holographic Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="article-inner">

        <!-- Article Header -->
        <header class="article-header">
            <a href="<?= $basePath ?>/blog" class="article-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to News
            </a>

            <div class="article-meta-top">
                <span class="article-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= date('F j, Y', strtotime($post['created_at'])) ?>
                </span>
                <span class="article-reading-time">
                    <i class="fa-regular fa-clock"></i>
                    <?= $readingTime ?> min read
                </span>
                <?php if (!empty($post['author_name'])): ?>
                    <span class="article-author">
                        <span class="article-author-avatar">
                            <?= strtoupper(substr($post['author_name'], 0, 1)) ?>
                        </span>
                        <?= htmlspecialchars($post['author_name']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <h1 class="article-title"><?= htmlspecialchars($post['title']) ?></h1>
        </header>

        <!-- Featured Image -->
        <?php if (!empty($post['featured_image'])): ?>
            <div class="article-featured-image">
                <img src="<?= htmlspecialchars($post['featured_image']) ?>" loading="lazy" alt="<?= htmlspecialchars($post['title']) ?>">
            </div>
        <?php endif; ?>

        <!-- Content Card -->
        <article class="article-content-card">
            <div class="article-body">
                <?= $post['content'] ?>
            </div>

            <!-- Article Footer -->
            <footer class="article-footer">
                <div class="article-footer-inner">
                    <a href="<?= $basePath ?>/blog" class="article-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to News
                    </a>

                    <?php
                    $shareUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    $shareTitle = urlencode($post['title']);
                    $shareUrlEncoded = urlencode($shareUrl);
                    ?>

                    <div class="share-section">
                        <span class="share-label">Share:</span>
                        <div class="share-buttons">
                            <!-- Facebook -->
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn facebook" title="Share on Facebook">
                                <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>

                            <!-- Twitter/X -->
                            <a href="https://twitter.com/intent/tweet?text=<?= $shareTitle ?>&url=<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn twitter" title="Share on X">
                                <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>

                            <!-- LinkedIn -->
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn linkedin" title="Share on LinkedIn">
                                <svg viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            </a>

                            <!-- WhatsApp -->
                            <a href="https://api.whatsapp.com/send?text=<?= $shareTitle ?>%20<?= $shareUrlEncoded ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="share-btn whatsapp" title="Share on WhatsApp">
                                <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </a>

                            <!-- Email -->
                            <a href="mailto:?subject=<?= $shareTitle ?>&body=Check%20this%20out:%20<?= $shareUrlEncoded ?>"
                               class="share-btn email" title="Share via Email">
                                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            </a>

                            <!-- Copy Link -->
                            <button class="share-btn copy" title="Copy link" id="copyLinkBtn">
                                <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </footer>
        </article>

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

// Button Press States
document.querySelectorAll('#article-holo-wrapper .article-btn, #article-holo-wrapper .share-btn').forEach(btn => {
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

// Copy Link Functionality
document.getElementById('copyLinkBtn')?.addEventListener('click', function() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        const originalSVG = this.innerHTML;
        this.innerHTML = '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
        this.style.background = '#22c55e';
        this.style.borderColor = '#22c55e';
        this.style.color = 'white';

        setTimeout(() => {
            this.innerHTML = originalSVG;
            this.style.background = '';
            this.style.borderColor = '';
            this.style.color = '';
        }, 2000);
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#302b63';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#302b63');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// Parallax effect on orbs
(function initParallaxOrbs() {
    const orbs = document.querySelectorAll('.holo-orb');
    if (orbs.length === 0) return;

    let ticking = false;

    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                const scrollY = window.scrollY;
                orbs.forEach((orb, index) => {
                    const speed = 0.03 * (index + 1);
                    orb.style.transform = `translateY(${scrollY * speed}px)`;
                });
                ticking = false;
            });
            ticking = true;
        }
    });
})();
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
