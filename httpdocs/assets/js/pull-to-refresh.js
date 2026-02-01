/**
 * Pull-to-Refresh - Native iOS/Android Style
 * Custom spinner with elastic pull feedback
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Auto-initializes on mobile devices
 *   // Or manually:
 *   PullToRefresh.init({
 *     onRefresh: async () => { await fetchNewData(); }
 *   });
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        threshold: 80,           // Pull distance to trigger refresh
        maxPull: 140,            // Maximum pull distance
        resistance: 2.5,         // Pull resistance factor
        refreshTimeout: 10000,   // Max refresh time before auto-reset
        hapticOnThreshold: true, // Vibrate when reaching threshold
        hapticOnRefresh: true,   // Vibrate on refresh start
        elasticContent: true,    // Move content with pull
        showText: true,          // Show pull/release text
        autoInit: true           // Auto-initialize on mobile
    };

    // State
    let isEnabled = false;
    let isPulling = false;
    let isRefreshing = false;
    let startY = 0;
    let currentY = 0;
    let pullDistance = 0;
    let indicator = null;
    let onRefreshCallback = null;

    // Check if we should enable PTR
    function shouldEnable() {
        // Only on mobile/tablet
        if (window.innerWidth > 1024) return false;

        // Skip if explicitly disabled
        if (document.body.classList.contains('no-ptr')) return false;
        if (document.documentElement.classList.contains('no-ptr')) return false;

        // Skip on /listings index route - causes scroll issues on real mobile devices
        // Route guard added 2026-02-01 for mobile scroll fix
        // Matches: /listings, /listings/, /tenant/listings, /tenant/listings/
        // Does NOT match: /listings/123, /tenant/listings/456
        const path = window.location.pathname;
        if (/\/listings\/?$/.test(path)) {
            return false;
        }

        // Skip on certain pages
        const skipPages = ['chat-page', 'messages-fullscreen', 'modal-open'];
        if (skipPages.some(cls => document.body.classList.contains(cls))) return false;

        // Check for reduced motion preference
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            // Still enable, but animations are handled in CSS
        }

        return true;
    }

    /**
     * Create the indicator element
     */
    function createIndicator() {
        if (indicator) return indicator;

        indicator = document.createElement('div');
        indicator.className = 'ptr-indicator';
        indicator.setAttribute('role', 'status');
        indicator.setAttribute('aria-live', 'polite');

        indicator.innerHTML = `
            <div class="ptr-spinner">
                <div class="ptr-spinner-track"></div>
                <div class="ptr-spinner-progress"></div>
                <div class="ptr-spinner-icon">
                    <i class="fa-solid fa-arrow-down"></i>
                </div>
            </div>
            ${config.showText ? '<span class="ptr-text">Pull to refresh</span>' : ''}
        `;

        document.body.appendChild(indicator);
        return indicator;
    }

    /**
     * Check if at top of page
     */
    function isAtTop() {
        return window.scrollY <= 0;
    }

    /**
     * Handle touch start
     */
    function handleTouchStart(e) {
        if (!isEnabled || isRefreshing) return;
        if (!isAtTop()) return;

        // Only respond to single touch
        if (e.touches.length !== 1) return;

        startY = e.touches[0].clientY;
        currentY = startY;

        // Prepare indicator
        if (!indicator) createIndicator();
    }

    /**
     * Handle touch move
     */
    function handleTouchMove(e) {
        if (!isEnabled || isRefreshing) return;
        if (startY === 0) return;

        currentY = e.touches[0].clientY;
        const deltaY = currentY - startY;

        // Only pull down
        if (deltaY <= 0) {
            if (isPulling) {
                resetPull();
            }
            return;
        }

        // Must be at top to start pulling
        if (!isPulling && !isAtTop()) {
            return;
        }

        // Prevent default scroll
        e.preventDefault();

        // Start pulling
        if (!isPulling) {
            isPulling = true;
            document.documentElement.classList.add('ptr-active');
            indicator.classList.add('ptr-visible', 'ptr-pulling');
        }

        // Calculate pull distance with resistance
        pullDistance = Math.min(deltaY / config.resistance, config.maxPull);

        // Update visual position
        updatePullVisuals();

        // Check threshold
        if (pullDistance >= config.threshold) {
            if (!indicator.classList.contains('ptr-ready')) {
                indicator.classList.add('ptr-ready');
                updateText('Release to refresh');
                haptic('medium');
            }
        } else {
            indicator.classList.remove('ptr-ready');
            updateText('Pull to refresh');
        }
    }

    /**
     * Handle touch end
     */
    function handleTouchEnd() {
        if (!isPulling) return;

        document.documentElement.classList.add('ptr-releasing');

        if (pullDistance >= config.threshold && !isRefreshing) {
            // Trigger refresh
            triggerRefresh();
        } else {
            // Cancel pull
            resetPull();
        }

        isPulling = false;
        startY = 0;
        currentY = 0;
    }

    /**
     * Update visual state during pull
     */
    function updatePullVisuals() {
        // Progress percentage (0-1)
        const progress = Math.min(pullDistance / config.threshold, 1);

        // Update indicator position
        const indicatorY = Math.min(pullDistance * 0.6, 50);
        indicator.style.transform = `translateX(-50%) translateY(${indicatorY}px)`;

        // Update spinner rotation (arrow follows pull)
        const rotation = progress * 180;
        indicator.style.setProperty('--ptr-rotation', `${rotation}deg`);

        // Update progress arc
        const progressEl = indicator.querySelector('.ptr-spinner-progress');
        if (progressEl) {
            progressEl.style.transform = `rotate(${-90 + (progress * 360)}deg)`;
        }

        // Elastic content effect
        if (config.elasticContent) {
            const contentPull = pullDistance * 0.3;
            document.documentElement.style.setProperty('--ptr-pull-distance', `${contentPull}px`);
        }
    }

    /**
     * Update text label
     */
    function updateText(text) {
        const textEl = indicator?.querySelector('.ptr-text');
        if (textEl) {
            textEl.textContent = text;
        }
    }

    /**
     * Trigger the refresh
     */
    async function triggerRefresh() {
        isRefreshing = true;

        indicator.classList.remove('ptr-pulling', 'ptr-ready');
        indicator.classList.add('ptr-refreshing');
        updateText('Refreshing...');

        // Reset content position but keep indicator visible
        document.documentElement.style.setProperty('--ptr-pull-distance', '0px');

        haptic('success');

        // Set timeout for stuck refreshes
        const timeoutId = setTimeout(() => {
            console.warn('[PTR] Refresh timeout - forcing reset');
            completeRefresh(false);
        }, config.refreshTimeout);

        try {
            // Call refresh callback
            if (onRefreshCallback) {
                await onRefreshCallback();
            } else {
                // Default: reload page
                window.location.reload();
                return; // Don't complete, page is reloading
            }

            clearTimeout(timeoutId);
            completeRefresh(true);
        } catch (error) {
            console.error('[PTR] Refresh error:', error);
            clearTimeout(timeoutId);
            completeRefresh(false);
        }
    }

    /**
     * Complete the refresh
     */
    function completeRefresh(success = true) {
        if (success) {
            indicator.classList.add('ptr-success');
            updateText('Updated!');

            // Brief success state
            setTimeout(() => {
                resetPull();
            }, 800);
        } else {
            resetPull();
        }
    }

    /**
     * Reset pull state
     */
    function resetPull() {
        isPulling = false;
        isRefreshing = false;
        pullDistance = 0;

        document.documentElement.classList.remove('ptr-active', 'ptr-releasing');
        document.documentElement.style.removeProperty('--ptr-pull-distance');

        if (indicator) {
            indicator.classList.remove(
                'ptr-visible', 'ptr-pulling', 'ptr-ready',
                'ptr-refreshing', 'ptr-success'
            );
            indicator.style.transform = '';
        }

        updateText('Pull to refresh');
    }

    /**
     * Haptic feedback
     */
    function haptic(type = 'light') {
        if (!navigator.vibrate) return;

        const patterns = {
            light: 10,
            medium: 20,
            success: [20, 50, 20]
        };

        const pattern = patterns[type] || 10;
        navigator.vibrate(pattern);
    }

    /**
     * Initialize PTR
     */
    function init(options = {}) {
        // Merge options
        Object.assign(config, options);

        if (options.onRefresh) {
            onRefreshCallback = options.onRefresh;
        }

        if (!shouldEnable()) {
            console.log('[PTR] Disabled on this device/page');
            return;
        }

        // Create indicator
        createIndicator();

        // Attach event listeners
        document.addEventListener('touchstart', handleTouchStart, { passive: true });
        document.addEventListener('touchmove', handleTouchMove, { passive: false });
        document.addEventListener('touchend', handleTouchEnd, { passive: true });
        document.addEventListener('touchcancel', resetPull, { passive: true });

        // Handle page show (bfcache)
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) {
                resetPull();
            }
        });

        // Handle resize (orientation change)
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                isEnabled = false;
                resetPull();
            } else {
                isEnabled = true;
            }
        });

        isEnabled = true;
        console.log('[PTR] Initialized');
    }

    /**
     * Destroy PTR
     */
    function destroy() {
        isEnabled = false;
        resetPull();

        document.removeEventListener('touchstart', handleTouchStart);
        document.removeEventListener('touchmove', handleTouchMove);
        document.removeEventListener('touchend', handleTouchEnd);
        document.removeEventListener('touchcancel', resetPull);

        if (indicator) {
            indicator.remove();
            indicator = null;
        }

        console.log('[PTR] Destroyed');
    }

    // Public API
    window.PullToRefresh = {
        init: init,
        destroy: destroy,
        refresh: triggerRefresh,
        reset: resetPull,
        isRefreshing: () => isRefreshing,
        setCallback: (fn) => { onRefreshCallback = fn; }
    };

    // Auto-initialize on DOM ready (mobile only)
    if (config.autoInit) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => init());
        } else {
            // Small delay to let other scripts set up
            setTimeout(() => init(), 100);
        }
    }

})();
