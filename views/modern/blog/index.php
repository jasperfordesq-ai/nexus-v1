<?php
// Phoenix View: Blog Index - Holographic Glassmorphism 2025
$pageTitle = 'Latest News';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="news-holo-wrapper" class="nexus-news-holo-wrapper">
    <!-- Holographic Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="news-inner">

        <!-- Hero Section -->
        <section class="news-hero-section">
            <div class="news-hero-badge">
                <i class="fa-solid fa-newspaper"></i>
                <span>News & Updates</span>
            </div>
            <h1 class="news-hero-title">Latest News</h1>
            <p class="news-hero-subtitle">Stay informed with the latest updates, stories, and announcements from our community.</p>
            <div class="news-hero-divider"></div>
        </section>

        <!-- Phase 11 (2026-01-27): Initial skeleton removed for content-first load.
             Server-rendered content displays immediately without swap.
             Infinite scroll has its own loading indicator below. -->

        <?php if (empty($posts)): ?>
            <!-- Empty State -->
            <div class="news-empty-state">
                <div class="news-empty-icon">
                    <i class="fa-solid fa-newspaper"></i>
                </div>
                <h3 class="news-empty-title">No updates yet</h3>
                <p class="news-empty-text">Check back soon for the latest news and announcements.</p>
            </div>
        <?php else: ?>
            <!-- News Grid -->
            <div class="news-grid" id="news-grid-container">
                <?php require __DIR__ . '/partials/feed-items.php'; ?>
            </div>

            <!-- Infinite Scroll Sentinel -->
            <div id="news-scroll-sentinel">
                <i class="fa-solid fa-circle-notch fa-spin fa-2x scroll-spinner"></i>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    // ============================================
    // GOLD STANDARD - Native App Features
    // ============================================

    // Phase 11 (2026-01-27): initSkeletonLoader() removed for content-first load.
    // Server-rendered content is visible immediately without skeleton swap.

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
    document.querySelectorAll('#news-holo-wrapper .news-btn, #news-holo-wrapper .news-card').forEach(el => {
        el.addEventListener('pointerdown', function() {
            if (this.classList.contains('news-card')) {
                this.style.transform = 'scale(0.98)';
            } else {
                this.style.transform = 'scale(0.96)';
            }
        });
        el.addEventListener('pointerup', function() {
            this.style.transform = '';
        });
        el.addEventListener('pointerleave', function() {
            this.style.transform = '';
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

    // Infinite Scroll
    (function() {
        let p = 1,
            l = false,
            f = false;
        const s = document.getElementById('news-scroll-sentinel');
        const c = document.getElementById('news-grid-container');
        if (!s || !c) return;
        const i = s.querySelector('i');
        new IntersectionObserver(async (e) => {
            if (e[0].isIntersecting && !l && !f) {
                l = true;
                i.style.display = 'block';
                p++;
                try {
                    const r = await fetch(`?page=${p}&partial=1`);
                    const h = await r.text();
                    if (!h.trim()) {
                        f = true;
                        s.style.display = 'none';
                    } else {
                        const t = document.createElement('div');
                        t.innerHTML = h;
                        while (t.firstChild) {
                            const n = t.firstChild;
                            if (n.nodeType === 1) {
                                n.style.opacity = '0';
                                n.style.transition = 'opacity 0.5s ease';

                                // Handle image visibility
                                const imgs = n.querySelectorAll('img');
                                imgs.forEach(img => {
                                    if (img.complete) img.classList.add('loaded');
                                    else img.onload = () => img.classList.add('loaded');
                                });
                            }
                            c.appendChild(n);
                            if (n.nodeType === 1) setTimeout(() => n.style.opacity = '1', 50);
                        }
                    }
                } catch (x) {
                    f = true;
                }
                l = false;
                i.style.display = 'none';
            }
        }, {
            rootMargin: '200px'
        }).observe(s);
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
                        const speed = 0.05 * (index + 1);
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