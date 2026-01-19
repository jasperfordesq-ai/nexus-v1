/**
 * Image Lazy Loading - Progressive Blur-Up Effect
 * Uses Intersection Observer for efficient lazy loading
 * Version: 1.0 - 2026-01-19
 *
 * Features:
 * - Blur-up effect from tiny placeholder to full image
 * - Intersection Observer for performance
 * - Native loading="lazy" enhancement
 * - Background image support
 * - Error handling with fallback
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        rootMargin: '50px 0px',      // Start loading 50px before viewport
        threshold: 0.01,              // Trigger when 1% visible
        placeholderQuality: 20,       // Tiny placeholder size
        retryAttempts: 2,             // Retry failed loads
        retryDelay: 1000              // Delay between retries (ms)
    };

    // Track loaded images to avoid reprocessing
    const loadedImages = new WeakSet();

    /**
     * Initialize lazy loading
     */
    function init() {
        // Check for Intersection Observer support
        if (!('IntersectionObserver' in window)) {
            // Fallback: load all images immediately
            loadAllImages();
            return;
        }

        // Create observer
        const observer = new IntersectionObserver(handleIntersection, {
            rootMargin: CONFIG.rootMargin,
            threshold: CONFIG.threshold
        });

        // Observe all lazy images
        observeLazyImages(observer);

        // Handle dynamically added images
        setupMutationObserver(observer);

        // Enhance native lazy loading
        enhanceNativeLazyLoading();
    }

    /**
     * Handle intersection events
     */
    function handleIntersection(entries, observer) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;
                observer.unobserve(target);

                if (target.tagName === 'IMG') {
                    loadImage(target);
                } else if (target.classList.contains('lazy-bg')) {
                    loadBackgroundImage(target);
                }
            }
        });
    }

    /**
     * Load a single image with blur-up effect
     */
    function loadImage(img, retryCount = 0) {
        if (loadedImages.has(img)) return;

        const src = img.dataset.src || img.dataset.lazySrc;
        if (!src) return;

        // Mark as loading
        img.classList.add('loading');
        img.classList.remove('loaded', 'error');

        // Mark container
        const container = img.closest('.lazy-image-container, .lazy-avatar-container, .lazy-card-image, .lazy-gallery-item');
        if (container) {
            container.classList.add('has-placeholder');
        }

        // Create temporary image to preload
        const tempImg = new Image();

        tempImg.onload = function() {
            // Swap to full image
            img.src = src;
            img.removeAttribute('data-src');
            img.removeAttribute('data-lazy-src');

            // Trigger reflow for smooth transition
            void img.offsetWidth;

            // Mark as loaded
            img.classList.remove('loading');
            img.classList.add('loaded');
            loadedImages.add(img);

            if (container) {
                container.classList.add('loaded');
            }

            // Dispatch custom event
            img.dispatchEvent(new CustomEvent('lazyloaded', { bubbles: true }));
        };

        tempImg.onerror = function() {
            if (retryCount < CONFIG.retryAttempts) {
                // Retry after delay
                setTimeout(() => {
                    loadImage(img, retryCount + 1);
                }, CONFIG.retryDelay);
            } else {
                // Mark as error
                img.classList.remove('loading');
                img.classList.add('error');

                // Try fallback image if available
                if (img.dataset.fallback) {
                    img.src = img.dataset.fallback;
                }

                // Dispatch error event
                img.dispatchEvent(new CustomEvent('lazyerror', { bubbles: true }));
            }
        };

        // Start loading
        tempImg.src = src;
    }

    /**
     * Load background image with blur-up
     */
    function loadBackgroundImage(element, retryCount = 0) {
        if (loadedImages.has(element)) return;

        const src = element.dataset.bgSrc || element.dataset.src;
        if (!src) return;

        element.classList.add('loading');

        const tempImg = new Image();

        tempImg.onload = function() {
            element.style.backgroundImage = `url('${src}')`;
            element.removeAttribute('data-bg-src');
            element.removeAttribute('data-src');

            void element.offsetWidth;

            element.classList.remove('loading');
            element.classList.add('loaded');
            loadedImages.add(element);

            element.dispatchEvent(new CustomEvent('lazyloaded', { bubbles: true }));
        };

        tempImg.onerror = function() {
            if (retryCount < CONFIG.retryAttempts) {
                setTimeout(() => {
                    loadBackgroundImage(element, retryCount + 1);
                }, CONFIG.retryDelay);
            } else {
                element.classList.remove('loading');
                element.classList.add('error');
            }
        };

        tempImg.src = src;
    }

    /**
     * Observe all lazy images on page
     */
    function observeLazyImages(observer) {
        // Standard lazy images
        document.querySelectorAll('[data-src]:not(.loaded), [data-lazy-src]:not(.loaded)').forEach(el => {
            if (!loadedImages.has(el)) {
                observer.observe(el);
            }
        });

        // Background images
        document.querySelectorAll('.lazy-bg[data-bg-src]:not(.loaded)').forEach(el => {
            if (!loadedImages.has(el)) {
                observer.observe(el);
            }
        });
    }

    /**
     * Setup mutation observer for dynamically added images
     */
    function setupMutationObserver(intersectionObserver) {
        const mutationObserver = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType !== 1) return;

                    // Check if node itself is a lazy image
                    if (node.matches && node.matches('[data-src], [data-lazy-src], .lazy-bg[data-bg-src]')) {
                        intersectionObserver.observe(node);
                    }

                    // Check children
                    if (node.querySelectorAll) {
                        node.querySelectorAll('[data-src], [data-lazy-src], .lazy-bg[data-bg-src]').forEach(el => {
                            if (!loadedImages.has(el)) {
                                intersectionObserver.observe(el);
                            }
                        });
                    }
                });
            });
        });

        mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Enhance native lazy loading
     */
    function enhanceNativeLazyLoading() {
        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
            if (img.complete) {
                img.classList.add('loaded');
            } else {
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                }, { once: true });
            }
        });
    }

    /**
     * Fallback: Load all images immediately
     */
    function loadAllImages() {
        document.querySelectorAll('[data-src], [data-lazy-src]').forEach(img => {
            loadImage(img);
        });

        document.querySelectorAll('.lazy-bg[data-bg-src]').forEach(el => {
            loadBackgroundImage(el);
        });
    }

    /**
     * Public API
     */
    window.LazyLoad = {
        /**
         * Manually trigger load for an image
         */
        load: function(element) {
            if (element.tagName === 'IMG') {
                loadImage(element);
            } else if (element.classList.contains('lazy-bg')) {
                loadBackgroundImage(element);
            }
        },

        /**
         * Refresh - observe new images
         */
        refresh: function() {
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver(handleIntersection, {
                    rootMargin: CONFIG.rootMargin,
                    threshold: CONFIG.threshold
                });
                observeLazyImages(observer);
            } else {
                loadAllImages();
            }
        },

        /**
         * Check if image is loaded
         */
        isLoaded: function(element) {
            return loadedImages.has(element) || element.classList.contains('loaded');
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also run on page show (for back/forward cache)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.LazyLoad.refresh();
        }
    });

})();
