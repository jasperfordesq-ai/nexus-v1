/**
 * CivicOne Blog Index
 * Offline indicator, infinite scroll, parallax effects
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // ============================================
    // Offline Indicator
    // ============================================
    function initOfflineIndicator() {
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
    }

    // ============================================
    // Button Press States (Micro-interactions)
    // ============================================
    function initButtonStates() {
        const elements = document.querySelectorAll('#news-holo-wrapper .news-btn, #news-holo-wrapper .news-card');

        elements.forEach(el => {
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
    }

    // ============================================
    // Dynamic Theme Color (PWA)
    // ============================================
    function initDynamicThemeColor() {
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
    }

    // ============================================
    // Infinite Scroll
    // ============================================
    function initInfiniteScroll() {
        let page = 1;
        let loading = false;
        let finished = false;

        const sentinel = document.getElementById('news-scroll-sentinel');
        const container = document.getElementById('news-grid-container');

        if (!sentinel || !container) return;

        const spinner = sentinel.querySelector('i');

        const observer = new IntersectionObserver(async (entries) => {
            if (entries[0].isIntersecting && !loading && !finished) {
                loading = true;
                if (spinner) spinner.classList.remove('hidden');
                page++;

                try {
                    const response = await fetch(`?page=${page}&partial=1`);
                    const html = await response.text();

                    if (!html.trim()) {
                        finished = true;
                        sentinel.classList.add('hidden');
                    } else {
                        const temp = document.createElement('div');
                        temp.innerHTML = html;

                        while (temp.firstChild) {
                            const node = temp.firstChild;
                            if (node.nodeType === 1) {
                                node.style.opacity = '0';
                                node.style.transition = 'opacity 0.5s ease';

                                // Handle image visibility
                                const images = node.querySelectorAll('img');
                                images.forEach(img => {
                                    if (img.complete) {
                                        img.classList.add('loaded');
                                    } else {
                                        img.onload = () => img.classList.add('loaded');
                                    }
                                });
                            }
                            container.appendChild(node);
                            if (node.nodeType === 1) {
                                setTimeout(() => node.style.opacity = '1', 50);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Infinite scroll error:', error);
                    finished = true;
                }

                loading = false;
                if (spinner) spinner.classList.add('hidden');
            }
        }, {
            rootMargin: '200px'
        });

        observer.observe(sentinel);
    }

    // ============================================
    // Parallax Effect on Orbs
    // ============================================
    function initParallaxOrbs() {
        const orbs = document.querySelectorAll('.holo-orb');
        if (orbs.length === 0) return;

        // Check reduced motion preference
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

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
    }

    // ============================================
    // Initialize All Features
    // ============================================
    function init() {
        initOfflineIndicator();
        initButtonStates();
        initDynamicThemeColor();
        initInfiniteScroll();
        initParallaxOrbs();
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
