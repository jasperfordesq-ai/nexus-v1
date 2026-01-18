/**
 * Project NEXUS - Turbo Navigation
 * SPA-like instant page transitions without full page reloads
 * Inspired by Turbo/HTMX but lightweight and custom-built
 */

(function() {
    'use strict';

    window.NexusTurbo = {
        // Configuration
        config: {
            cacheSize: 10,
            transitionDuration: 200,
            prefetchDelay: 100,
            excludeSelectors: [
                '[data-turbo="false"]',
                '[target="_blank"]',
                '[download]',
                'a[href^="mailto:"]',
                'a[href^="tel:"]',
                'a[href^="#"]',
                'a[href^="javascript:"]',
                'a[href*="logout"]',
                'form[data-turbo="false"]'
            ]
        },

        // Page cache
        cache: new Map(),
        cacheOrder: [],

        // State
        isNavigating: false,
        currentUrl: window.location.href,
        abortController: null,

        // ============================================
        // INITIALIZATION
        // ============================================

        init: function() {
            // Skip if View Transitions API is natively supported and preferred
            if (document.startViewTransition && !this.config.forceCustomTransitions) {
                console.log('[NexusTurbo] Using native View Transitions API');
                this.initNativeTransitions();
                return;
            }

            this.bindEvents();
            this.cacheCurrentPage();
            this.setupProgressBar();

            console.log('[NexusTurbo] Initialized with custom transitions');
        },

        // ============================================
        // NATIVE VIEW TRANSITIONS (Modern Browsers)
        // ============================================

        initNativeTransitions: function() {
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[href]');
                if (!link || this.shouldExclude(link)) return;

                const href = link.getAttribute('href');
                if (!this.isInternalLink(href)) return;

                e.preventDefault();

                // Clear view-transition-names before transition to avoid duplicates
                const mainContent = document.querySelector('main#main-content');
                if (mainContent) mainContent.style.viewTransitionName = 'none';

                document.startViewTransition(async () => {
                    await this.fetchAndReplace(href);
                    window.history.pushState({}, '', href);
                    // Re-enable view-transition-name for future transitions
                    const newMain = document.querySelector('main#main-content');
                    if (newMain) newMain.style.viewTransitionName = '';
                });
            });

            // Handle back/forward
            window.addEventListener('popstate', () => {
                // Clear view-transition-names before transition to avoid duplicates
                const mainContent = document.querySelector('main#main-content');
                if (mainContent) mainContent.style.viewTransitionName = 'none';

                document.startViewTransition(() => {
                    this.fetchAndReplace(window.location.href);
                    // Re-enable view-transition-name for future transitions
                    const newMain = document.querySelector('main#main-content');
                    if (newMain) newMain.style.viewTransitionName = '';
                });
            });
        },

        // ============================================
        // CUSTOM TRANSITIONS (Fallback)
        // ============================================

        bindEvents: function() {
            // Link clicks
            document.addEventListener('click', (e) => this.handleClick(e));

            // Form submissions
            document.addEventListener('submit', (e) => this.handleSubmit(e));

            // Prefetch on hover
            document.addEventListener('mouseover', (e) => this.handleHover(e));
            document.addEventListener('touchstart', (e) => this.handleHover(e), { passive: true });

            // Browser back/forward
            window.addEventListener('popstate', (e) => this.handlePopState(e));
        },

        handleClick: function(e) {
            const link = e.target.closest('a[href]');
            if (!link) return;

            // Check exclusions
            if (this.shouldExclude(link)) return;

            const href = link.getAttribute('href');
            if (!this.isInternalLink(href)) return;

            e.preventDefault();
            this.navigate(href, { pushState: true });
        },

        handleSubmit: function(e) {
            const form = e.target;
            if (this.shouldExclude(form)) return;
            // Safety check for form.method (can be undefined in some browsers/contexts)
            if (!form.method || form.method.toLowerCase() !== 'get') return; // Only handle GET forms

            e.preventDefault();

            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            const url = form.action + '?' + params.toString();

            this.navigate(url, { pushState: true });
        },

        handleHover: function(e) {
            const link = e.target.closest('a[href]');
            if (!link || this.shouldExclude(link)) return;

            const href = link.getAttribute('href');
            if (!this.isInternalLink(href)) return;

            // Prefetch after small delay
            clearTimeout(link._prefetchTimeout);
            link._prefetchTimeout = setTimeout(() => {
                this.prefetch(href);
            }, this.config.prefetchDelay);
        },

        handlePopState: function(e) {
            this.navigate(window.location.href, { pushState: false });
        },

        // ============================================
        // NAVIGATION
        // ============================================

        async navigate(url, options = {}) {
            if (this.isNavigating) {
                // Cancel previous navigation
                if (this.abortController) {
                    this.abortController.abort();
                }
            }

            this.isNavigating = true;
            this.showProgress();

            // Haptic feedback
            if (window.NexusMobile) {
                NexusMobile.haptic('light');
            }

            try {
                // Check cache first - DISABLED for members page
                const cached = this.cache.get(url);
                const isMembersPage = url.includes('/members');
                if (cached && !isMembersPage && Date.now() - cached.timestamp < 30000) {
                    await this.transitionTo(cached.html, url, options);
                    return;
                }

                // Fetch new page
                const html = await this.fetchPage(url);
                if (html) {
                    this.cachePage(url, html);
                    await this.transitionTo(html, url, options);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('[NexusTurbo] Navigation failed:', error);
                    // Fallback to regular navigation
                    window.location.href = url;
                }
            } finally {
                this.isNavigating = false;
                this.hideProgress();
            }
        },

        async fetchPage(url) {
            this.abortController = new AbortController();

            const response = await fetch(url, {
                signal: this.abortController.signal,
                headers: {
                    'X-Requested-With': 'NexusTurbo',
                    'Accept': 'text/html'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.text();
        },

        async fetchAndReplace(url) {
            const html = await this.fetchPage(url);
            await this.replaceContent(html);
            this.currentUrl = url;
        },

        async transitionTo(html, url, options) {
            // Exit animation
            const content = this.getContentElement();
            if (content) {
                content.style.opacity = '0';
                content.style.transform = 'translateX(-20px)';
                await this.sleep(this.config.transitionDuration / 2);
            }

            // Replace content
            this.replaceContent(html);

            // Update URL
            if (options.pushState) {
                window.history.pushState({ turbo: true }, '', url);
            }
            this.currentUrl = url;

            // Enter animation
            const newContent = this.getContentElement();
            if (newContent) {
                newContent.style.opacity = '0';
                newContent.style.transform = 'translateX(20px)';
                newContent.style.transition = `all ${this.config.transitionDuration}ms ease-out`;

                requestAnimationFrame(() => {
                    newContent.style.opacity = '1';
                    newContent.style.transform = 'translateX(0)';
                });
            }

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'instant' });

            // Dispatch event
            document.dispatchEvent(new CustomEvent('turbo:load', { detail: { url } }));
        },

        replaceContent: async function(html) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Update title
            const newTitle = doc.querySelector('title');
            if (newTitle) {
                document.title = newTitle.textContent;
            }

            // Update meta tags
            this.updateMetaTags(doc);

            // Replace body content (preserve scripts)
            const newBody = doc.body;
            const oldBody = document.body;

            // Keep certain elements
            const bottomNav = oldBody.querySelector('.nexus-bottom-nav');
            const toastContainer = oldBody.querySelector('.nexus-toast-container');
            const loadingOverlay = oldBody.querySelector('.nexus-loading-overlay');

            // Replace body content
            oldBody.innerHTML = newBody.innerHTML;

            // Restore preserved elements
            if (bottomNav) oldBody.appendChild(bottomNav);
            if (toastContainer) oldBody.appendChild(toastContainer);
            if (loadingOverlay) oldBody.appendChild(loadingOverlay);

            // CRITICAL: Clean up page-specific classes from html and body
            // These can persist from SPA navigation and break layouts
            const pageSpecificClasses = ['messages-page', 'messages-fullscreen', 'no-ptr', 'chat-page'];
            pageSpecificClasses.forEach(cls => {
                document.documentElement.classList.remove(cls);
                oldBody.classList.remove(cls);
            });
            // Reset overflow styles that messages page sets
            document.documentElement.style.overflow = '';
            oldBody.style.overflow = '';

            // Copy body attributes (this will set the correct classes for new page)
            Array.from(newBody.attributes).forEach(attr => {
                oldBody.setAttribute(attr.name, attr.value);
            });

            // Also sync html element classes if new page has them
            const newHtml = doc.documentElement;
            if (newHtml.className) {
                // Preserve theme and content-loaded, add new page classes
                const preserveClasses = ['dark', 'light', 'content-loaded', 'loading'];
                const currentHtmlClasses = Array.from(document.documentElement.classList);
                const preserved = currentHtmlClasses.filter(c => preserveClasses.includes(c));
                document.documentElement.className = newHtml.className;
                preserved.forEach(c => document.documentElement.classList.add(c));
            }

            // Re-execute scripts (external first, then inline)
            await this.executeScripts(oldBody);

            // Reinitialize components
            this.reinitializeComponents();
        },

        executeScripts: async function(container) {
            const scripts = container.querySelectorAll('script:not([data-turbo-permanent])');

            // Separate external and inline scripts
            const externalScripts = [];
            const inlineScripts = [];

            scripts.forEach(script => {
                if (script.src) {
                    externalScripts.push(script);
                } else if (script.textContent && script.textContent.trim()) {
                    inlineScripts.push(script);
                }
            });

            // Load external scripts first and wait for them
            for (const oldScript of externalScripts) {
                try {
                    // Check if already loaded (normalize URL for comparison)
                    const srcUrl = new URL(oldScript.src, window.location.origin);
                    const srcPath = srcUrl.pathname.split('?')[0];
                    const alreadyLoaded = Array.from(document.querySelectorAll('script[src]')).find(s => {
                        try {
                            const existingUrl = new URL(s.src, window.location.origin);
                            return existingUrl.pathname.split('?')[0] === srcPath;
                        } catch {
                            return false;
                        }
                    });

                    if (alreadyLoaded) {
                        continue;
                    }

                    // Load new external script and wait for it
                    await this.loadExternalScript(oldScript);
                } catch (e) {
                    console.warn('[NexusTurbo] External script load failed:', oldScript.src, e.message);
                }
            }

            // Now execute inline scripts
            for (const oldScript of inlineScripts) {
                const scriptContent = oldScript.textContent || '';

                // Skip scripts that declare known global classes/objects
                const knownGlobals = [
                    'NexusNotifications', 'NexusMobile', 'NexusNative', 'NexusTurbo',
                    'NexusMapbox', 'SkeletonLoader', 'OptimisticUI', 'EnhancedTransitions',
                    'IS_LOGGED_IN', 'NEXUS_MAPBOX_TOKEN', 'NEXUS_VAPID_PUBLIC_KEY'
                ];

                // Check for const/let/var/class declarations of known globals
                const hasKnownGlobal = knownGlobals.some(name => {
                    const patterns = [
                        `const ${name}`,
                        `let ${name}`,
                        `var ${name}`,
                        `class ${name}`,
                        `window.${name}`
                    ];
                    return patterns.some(p => scriptContent.includes(p));
                });

                if (hasKnownGlobal) {
                    // Skip - these are initialization scripts that should only run once
                    continue;
                }

                // For page-specific inline scripts, wrap in IIFE to avoid redeclaration errors
                try {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => {
                        newScript.setAttribute(attr.name, attr.value);
                    });

                    // Wrap in try-catch to prevent undefined library errors from breaking page
                    let wrappedContent = scriptContent;
                    if (scriptContent.includes('const ') || scriptContent.includes('let ')) {
                        wrappedContent = `(function() {
                            try { ${scriptContent} }
                            catch(e) { console.warn('[Script Error]', e.message); }
                        })();`;
                    } else {
                        wrappedContent = `try { ${scriptContent} } catch(e) { console.warn('[Script Error]', e.message); }`;
                    }

                    newScript.textContent = wrappedContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                } catch (e) {
                    console.warn('[NexusTurbo] Script execution skipped:', e.message);
                }
            }
        },

        loadExternalScript: function(oldScript) {
            return new Promise((resolve, reject) => {
                const newScript = document.createElement('script');

                // Copy all attributes
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });

                // Set up load handlers
                newScript.onload = () => resolve();
                newScript.onerror = (e) => {
                    // Don't reject - just resolve to continue with other scripts
                    console.warn('[NexusTurbo] Script load error (continuing):', oldScript.src);
                    resolve();
                };

                // Timeout fallback (important for CDN scripts that may fail on localhost)
                const timeout = setTimeout(() => {
                    console.warn('[NexusTurbo] Script load timeout (continuing):', oldScript.src);
                    resolve();
                }, 5000);

                newScript.onload = () => {
                    clearTimeout(timeout);
                    resolve();
                };

                // Append to head to start loading
                document.head.appendChild(newScript);
            });
        },

        reinitializeComponents: function() {
            // Reinitialize NexusMobile
            if (window.NexusMobile) {
                NexusMobile.initBottomNav();
            }

            // Reinitialize any other components
            if (window.nexusNotifications) {
                // Notification system reinit if needed
            }
        },

        updateMetaTags: function(doc) {
            // Update theme color if changed
            const newTheme = doc.querySelector('meta[name="theme-color"]');
            const oldTheme = document.querySelector('meta[name="theme-color"]');
            if (newTheme && oldTheme) {
                oldTheme.content = newTheme.content;
            }
        },

        // ============================================
        // CACHING
        // ============================================

        prefetch: function(url) {
            if (this.cache.has(url)) return;

            fetch(url, {
                headers: { 'X-Requested-With': 'NexusTurbo' }
            })
            .then(r => r.text())
            .then(html => this.cachePage(url, html))
            .catch(() => {}); // Ignore prefetch errors
        },

        cachePage: function(url, html) {
            // Remove oldest if cache is full
            if (this.cacheOrder.length >= this.config.cacheSize) {
                const oldest = this.cacheOrder.shift();
                this.cache.delete(oldest);
            }

            this.cache.set(url, {
                html: html,
                timestamp: Date.now()
            });
            this.cacheOrder.push(url);
        },

        cacheCurrentPage: function() {
            this.cachePage(window.location.href, document.documentElement.outerHTML);
        },

        // ============================================
        // PROGRESS BAR
        // ============================================

        setupProgressBar: function() {
            const bar = document.createElement('div');
            bar.id = 'nexus-turbo-progress';
            bar.innerHTML = `
                <style>
                    #nexus-turbo-progress {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 0;
                        height: 3px;
                        background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
                        z-index: 99999;
                        transition: width 0.2s ease-out;
                        box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
                    }
                    #nexus-turbo-progress.active {
                        animation: turboProgress 2s ease-in-out infinite;
                    }
                    @keyframes turboProgress {
                        0% { width: 0; }
                        50% { width: 70%; }
                        100% { width: 90%; }
                    }
                </style>
            `;
            document.body.appendChild(bar);
        },

        showProgress: function() {
            const bar = document.getElementById('nexus-turbo-progress');
            if (bar) {
                bar.classList.add('active');
            }
        },

        hideProgress: function() {
            const bar = document.getElementById('nexus-turbo-progress');
            if (bar) {
                bar.style.width = '100%';
                setTimeout(() => {
                    bar.classList.remove('active');
                    bar.style.width = '0';
                }, 200);
            }
        },

        // ============================================
        // UTILITIES
        // ============================================

        getContentElement: function() {
            return document.querySelector('main') ||
                   document.querySelector('.htb-container') ||
                   document.querySelector('.htb-container-full') ||
                   document.body;
        },

        shouldExclude: function(element) {
            return this.config.excludeSelectors.some(selector => {
                return element.matches(selector);
            });
        },

        isInternalLink: function(href) {
            if (!href) return false;
            if (href.startsWith('#')) return false;
            if (href.startsWith('javascript:')) return false;

            try {
                const url = new URL(href, window.location.origin);
                return url.origin === window.location.origin;
            } catch {
                return false;
            }
        },

        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NexusTurbo.init());
    } else {
        NexusTurbo.init();
    }

})();
