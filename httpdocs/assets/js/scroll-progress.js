/**
 * Scroll Progress Indicator
 * Top bar showing page scroll position
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Auto-initializes top bar on pages
 *   // Or customize:
 *   ScrollProgress.init({ type: 'bar', color: 'rainbow' });
 *   ScrollProgress.init({ type: 'circle', showPercent: true });
 *
 *   // For articles with reading progress:
 *   ScrollProgress.initReading(articleElement);
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        autoInit: true,
        type: 'bar', // 'bar' or 'circle'
        color: 'primary', // 'primary', 'rainbow', 'success', 'info', etc.
        thickness: 'normal', // 'thin', 'normal', 'thick'
        glow: false,
        showCircleAt: 10, // Show circle indicator after X% scroll
        showPercent: false, // Show percentage in circle
        scrollToTop: true, // Click circle to scroll to top
        belowHeader: false // Position below header
    };

    let progressBar = null;
    let progressCircle = null;
    let rafId = null;
    let lastScrollY = 0;

    /**
     * Calculate scroll progress
     */
    function getScrollProgress() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;

        if (docHeight <= 0) return 0;
        return Math.min(Math.max((scrollTop / docHeight) * 100, 0), 100);
    }

    /**
     * Create top bar progress indicator
     */
    function createProgressBar() {
        const container = document.createElement('div');
        container.className = `scroll-progress scroll-progress--${config.color}`;

        if (config.thickness !== 'normal') {
            container.classList.add(`scroll-progress--${config.thickness}`);
        }
        if (config.glow) {
            container.classList.add('scroll-progress--glow');
        }
        if (config.belowHeader) {
            container.classList.add('scroll-progress--below-header');
        }

        container.innerHTML = '<div class="scroll-progress-bar"></div>';
        container.setAttribute('role', 'progressbar');
        container.setAttribute('aria-valuemin', '0');
        container.setAttribute('aria-valuemax', '100');
        container.setAttribute('aria-valuenow', '0');
        container.setAttribute('aria-label', 'Page scroll progress');

        document.body.appendChild(container);

        return container.querySelector('.scroll-progress-bar');
    }

    /**
     * Create circular progress indicator
     */
    function createProgressCircle() {
        const container = document.createElement('div');
        container.className = 'scroll-progress-circle';
        container.setAttribute('role', 'button');
        container.setAttribute('aria-label', 'Scroll to top');
        container.setAttribute('tabindex', '0');

        container.innerHTML = `
            <svg viewBox="0 0 48 48">
                <defs>
                    <linearGradient id="scroll-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#6366f1"/>
                        <stop offset="100%" style="stop-color:#8b5cf6"/>
                    </linearGradient>
                </defs>
                <circle class="scroll-progress-circle-bg" cx="24" cy="24" r="20"/>
                <circle class="scroll-progress-circle-bar" cx="24" cy="24" r="20"/>
            </svg>
            <div class="scroll-progress-circle-inner">
                ${config.showPercent ?
                    '<span class="scroll-progress-percent">0%</span>' :
                    '<i class="fa-solid fa-arrow-up"></i>'
                }
            </div>
        `;

        document.body.appendChild(container);

        // Scroll to top on click
        if (config.scrollToTop) {
            container.addEventListener('click', scrollToTop);
            container.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    scrollToTop();
                }
            });
        }

        return container;
    }

    /**
     * Scroll to top smoothly
     */
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        // Haptic feedback
        if (navigator.vibrate) {
            navigator.vibrate(10);
        }
    }

    /**
     * Update progress indicators
     */
    function updateProgress() {
        const progress = getScrollProgress();

        // Update bar
        if (progressBar) {
            // eslint-disable-next-line no-restricted-syntax -- dynamic progress width
            progressBar.style.width = `${progress}%`;
            progressBar.parentElement.setAttribute('aria-valuenow', Math.round(progress));
        }

        // Update circle
        if (progressCircle) {
            const circleBar = progressCircle.querySelector('.scroll-progress-circle-bar');
            const percentEl = progressCircle.querySelector('.scroll-progress-percent');

            // Calculate stroke-dashoffset (126 is circumference of r=20 circle: 2*PI*20 ≈ 126)
            const circumference = 126;
            const offset = circumference - (progress / 100) * circumference;
            circleBar.style.strokeDashoffset = offset;

            // Update percentage text
            if (percentEl) {
                percentEl.textContent = `${Math.round(progress)}%`;
            }

            // Show/hide based on scroll position
            if (progress >= config.showCircleAt) {
                progressCircle.classList.add('visible');
            } else {
                progressCircle.classList.remove('visible');
            }
        }

        rafId = null;
    }

    /**
     * Throttled scroll handler
     */
    function handleScroll() {
        if (!rafId) {
            rafId = requestAnimationFrame(updateProgress);
        }
    }

    /**
     * Initialize scroll progress
     */
    function init(options = {}) {
        // Merge options
        Object.assign(config, options);

        // Don't initialize on very short pages
        const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        if (docHeight < 500) return;

        // Create indicators
        if (config.type === 'bar' || config.type === 'both') {
            progressBar = createProgressBar();
        }

        if (config.type === 'circle' || config.type === 'both') {
            progressCircle = createProgressCircle();
        }

        // Listen for scroll
        window.addEventListener('scroll', handleScroll, { passive: true });

        // Initial update
        updateProgress();

        console.log('[ScrollProgress] Initialized');
    }

    /**
     * Initialize reading progress for an article
     */
    function initReading(articleEl, options = {}) {
        if (!articleEl) return;

        const container = document.createElement('div');
        container.className = 'reading-progress';

        const bar = document.createElement('div');
        bar.className = 'reading-progress-bar';
        container.appendChild(bar);

        // Calculate reading time
        const text = articleEl.textContent || '';
        const wordCount = text.trim().split(/\s+/).length;
        const readingTime = Math.ceil(wordCount / 200); // 200 WPM average

        if (options.showTime !== false) {
            const timeEl = document.createElement('div');
            timeEl.className = 'reading-progress-time';
            timeEl.textContent = `${readingTime} min read`;
            container.appendChild(timeEl);
        }

        // Insert before article
        articleEl.parentNode.insertBefore(container, articleEl);

        // Calculate progress based on article position
        function updateReadingProgress() {
            const rect = articleEl.getBoundingClientRect();
            const articleTop = rect.top + window.pageYOffset;
            const articleHeight = rect.height;
            const scrollTop = window.pageYOffset;
            const viewportHeight = window.innerHeight;

            // Progress starts when article top reaches viewport top
            // and ends when article bottom leaves viewport
            const start = articleTop - viewportHeight;
            const end = articleTop + articleHeight;
            const current = scrollTop;

            let progress = 0;
            if (current > start && current < end) {
                progress = ((current - start) / (end - start)) * 100;
            } else if (current >= end) {
                progress = 100;
            }

            // eslint-disable-next-line no-restricted-syntax -- dynamic progress width
            bar.style.width = `${Math.min(Math.max(progress, 0), 100)}%`;
        }

        window.addEventListener('scroll', () => {
            requestAnimationFrame(updateReadingProgress);
        }, { passive: true });

        updateReadingProgress();

        return container;
    }

    /**
     * Destroy progress indicators
     */
    function destroy() {
        window.removeEventListener('scroll', handleScroll);

        if (progressBar) {
            progressBar.parentElement.remove();
            progressBar = null;
        }

        if (progressCircle) {
            progressCircle.remove();
            progressCircle = null;
        }

        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    /**
     * Get current scroll progress percentage
     */
    function getProgress() {
        return getScrollProgress();
    }

    // Public API
    window.ScrollProgress = {
        init: init,
        initReading: initReading,
        destroy: destroy,
        getProgress: getProgress,
        scrollToTop: scrollToTop,
        config: (newConfig) => Object.assign(config, newConfig)
    };

    // Auto-initialize on DOM ready
    if (config.autoInit) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => init());
        } else {
            setTimeout(() => init(), 100);
        }
    }

})();
