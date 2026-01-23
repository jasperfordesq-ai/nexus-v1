/**
 * Page Transitions - Smooth Navigation Effects
 * Handles fade/slide animations between page navigations
 * Version: 1.0 - 2026-01-19
 *
 * Features:
 * - Intercepts link clicks for smooth transitions
 * - Detects navigation direction (forward/back)
 * - Loading progress indicator
 * - Prefetching for faster loads
 * - View Transitions API support (Chrome 111+)
 * - Respects reduced motion preferences
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        transitionDuration: 200,
        prefetchOnHover: true,
        prefetchDelay: 100,
        showSpinnerAfter: 500,
        excludeSelectors: [
            '[data-no-transition]',
            '[target="_blank"]',
            '[download]',
            '[href^="#"]',
            '[href^="mailto:"]',
            '[href^="tel:"]',
            '[href^="javascript:"]',
            '.no-transition',
            '[data-turbo="false"]'
        ]
    };

    // State
    const navigationHistory = [];
    let currentIndex = -1;
    let isTransitioning = false;
    const prefetchedUrls = new Set();
    let loadingBar = null;
    let loadingOverlay = null;
    let spinnerTimeout = null;

    // Check for reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Initialize the page transitions system
     */
    function init() {
        // Skip if reduced motion is preferred
        if (prefersReducedMotion) {
            console.log('[PageTransitions] Disabled - user prefers reduced motion');
            return;
        }

        // Create loading indicator elements
        createLoadingElements();

        // Add current page to history
        navigationHistory.push(window.location.href);
        currentIndex = 0;

        // Intercept link clicks
        document.addEventListener('click', handleLinkClick, true);

        // Handle browser back/forward
        window.addEventListener('popstate', handlePopState);

        // Prefetch on hover
        if (config.prefetchOnHover) {
            document.addEventListener('mouseover', handleHover, { passive: true });
        }

        // Run enter animation on initial page load
        requestAnimationFrame(() => {
            document.documentElement.classList.add('page-entering');
            setTimeout(() => {
                document.documentElement.classList.remove('page-entering');
            }, config.transitionDuration + 100);
        });

        console.log('[PageTransitions] Initialized');
    }

    /**
     * Create loading bar and overlay elements
     */
    function createLoadingElements() {
        // Loading progress bar
        loadingBar = document.createElement('div');
        loadingBar.className = 'page-loading-bar';
        loadingBar.setAttribute('role', 'progressbar');
        loadingBar.setAttribute('aria-label', 'Page loading');
        document.body.appendChild(loadingBar);

        // Loading overlay (for slow loads)
        loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'page-loading-overlay';
        loadingOverlay.innerHTML = '<div class="page-loading-spinner"></div>';
        document.body.appendChild(loadingOverlay);
    }

    /**
     * Check if a link should be transitioned
     */
    function shouldTransition(link) {
        // Must be a link element
        if (!link || link.tagName !== 'A') return false;

        // Must have href
        const href = link.getAttribute('href');
        if (!href) return false;

        // Check exclude selectors
        for (const selector of config.excludeSelectors) {
            if (link.matches(selector)) return false;
        }

        // Must be same origin
        try {
            const url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return false;
        } catch (e) {
            return false;
        }

        return true;
    }

    /**
     * Handle link clicks
     */
    function handleLinkClick(e) {
        // Find the link element
        const link = e.target.closest('a');
        if (!shouldTransition(link)) return;

        // Don't intercept if modifier keys are pressed
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        // Prevent default navigation
        e.preventDefault();

        // Get the target URL
        const href = link.getAttribute('href');
        const url = new URL(href, window.location.origin);

        // Don't transition to same page
        if (url.href === window.location.href) return;

        // Determine navigation direction
        const direction = 'forward';

        // Navigate with transition
        navigateWithTransition(url.href, direction);
    }

    /**
     * Handle browser back/forward
     */
    function handlePopState(e) {
        if (isTransitioning) return;

        const newUrl = window.location.href;
        const oldIndex = currentIndex;
        const newIndex = navigationHistory.indexOf(newUrl);

        // Determine direction
        let direction = 'forward';
        if (newIndex !== -1 && newIndex < oldIndex) {
            direction = 'back';
        }

        // Update current index
        if (newIndex !== -1) {
            currentIndex = newIndex;
        }

        // Perform transition
        performExitTransition(direction);

        // The page will reload naturally, but we animate the exit
        setTimeout(() => {
            performEnterTransition(direction);
        }, config.transitionDuration);
    }

    /**
     * Navigate to a URL with transition
     */
    async function navigateWithTransition(url, direction = 'forward') {
        if (isTransitioning) return;
        isTransitioning = true;

        // Try View Transitions API first (Chrome 111+)
        if (document.startViewTransition && !prefersReducedMotion) {
            try {
                const transition = document.startViewTransition(async () => {
                    await loadPage(url);
                });
                await transition.finished;
                isTransitioning = false;
                return;
            } catch (e) {
                // Fall back to manual transitions
                console.log('[PageTransitions] View Transitions failed, using fallback');
            }
        }

        // Manual transition fallback
        showLoadingBar();

        // Start exit animation
        performExitTransition(direction);

        // Wait for animation
        await sleep(config.transitionDuration);

        // Navigate
        try {
            // Update history
            navigationHistory.push(url);
            currentIndex = navigationHistory.length - 1;

            // Actually navigate
            window.location.href = url;
        } catch (e) {
            console.error('[PageTransitions] Navigation failed:', e);
            hideLoadingBar();
            isTransitioning = false;
            document.documentElement.classList.remove('page-transitioning', 'nav-forward', 'nav-back');
        }
    }

    /**
     * Load page content (for View Transitions API)
     */
    async function loadPage(url) {
        const response = await fetch(url);
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Update the main content
        const newMain = doc.querySelector('main, #main-content');
        const currentMain = document.querySelector('main, #main-content');

        if (newMain && currentMain) {
            currentMain.innerHTML = newMain.innerHTML;
        }

        // Update title
        document.title = doc.title;

        // Update URL
        history.pushState({}, '', url);

        // Update history tracking
        navigationHistory.push(url);
        currentIndex = navigationHistory.length - 1;
    }

    /**
     * Perform exit transition
     */
    function performExitTransition(direction) {
        const html = document.documentElement;
        html.classList.add('page-transitioning');
        html.classList.add(direction === 'back' ? 'nav-back' : 'nav-forward');
    }

    /**
     * Perform enter transition
     */
    function performEnterTransition(direction) {
        const html = document.documentElement;
        html.classList.remove('page-transitioning');
        html.classList.add('page-entering');
        html.classList.add(direction === 'back' ? 'nav-back' : 'nav-forward');

        setTimeout(() => {
            html.classList.remove('page-entering', 'nav-forward', 'nav-back');
            hideLoadingBar();
            isTransitioning = false;
        }, config.transitionDuration + 50);
    }

    /**
     * Show loading progress bar
     */
    function showLoadingBar() {
        if (!loadingBar) return;
        loadingBar.classList.remove('complete');
        loadingBar.classList.add('active');

        // Show spinner for slow loads
        spinnerTimeout = setTimeout(() => {
            if (loadingOverlay) {
                loadingOverlay.classList.add('visible');
            }
        }, config.showSpinnerAfter);
    }

    /**
     * Hide loading progress bar
     */
    function hideLoadingBar() {
        if (!loadingBar) return;

        clearTimeout(spinnerTimeout);

        loadingBar.classList.remove('active');
        loadingBar.classList.add('complete');

        if (loadingOverlay) {
            loadingOverlay.classList.remove('visible');
        }

        setTimeout(() => {
            loadingBar.classList.remove('complete');
        }, 300);
    }

    /**
     * Handle hover for prefetching
     */
    let hoverTimeout = null;
    function handleHover(e) {
        const link = e.target.closest('a');
        if (!shouldTransition(link)) return;

        const href = link.getAttribute('href');
        if (prefetchedUrls.has(href)) return;

        clearTimeout(hoverTimeout);
        hoverTimeout = setTimeout(() => {
            prefetchUrl(href);
            link.classList.add('prefetched');
        }, config.prefetchDelay);
    }

    /**
     * Prefetch a URL
     */
    function prefetchUrl(url) {
        if (prefetchedUrls.has(url)) return;

        // Use link prefetch
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);

        prefetchedUrls.add(url);
        console.log('[PageTransitions] Prefetched:', url);
    }

    /**
     * Sleep helper
     */
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Public API
     */
    window.PageTransitions = {
        // Navigate programmatically
        navigate: function(url, direction = 'forward') {
            navigateWithTransition(url, direction);
        },

        // Prefetch a URL manually
        prefetch: prefetchUrl,

        // Check if transitioning
        isTransitioning: function() {
            return isTransitioning;
        },

        // Disable transitions for a navigation
        navigateInstant: function(url) {
            document.documentElement.classList.add('page-instant');
            window.location.href = url;
        },

        // Update config
        config: function(newConfig) {
            Object.assign(config, newConfig);
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also run enter animation on page show (bfcache)
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            // Page was restored from bfcache
            document.documentElement.classList.add('page-entering');
            setTimeout(() => {
                document.documentElement.classList.remove('page-entering', 'nav-forward', 'nav-back');
            }, config.transitionDuration + 50);
        }
    });

})();
