/**
 * Project NEXUS - Shared Element Transitions
 * Morphs hero images, avatars, and cards between pages
 * Uses View Transitions API with progressive enhancement
 */

(function() {
    'use strict';

    // ============================================
    // FEATURE DETECTION
    // ============================================
    const supportsViewTransitions = 'startViewTransition' in document;

    // ============================================
    // TRANSITION ORCHESTRATOR
    // ============================================
    class SharedTransitions {
        constructor() {
            this.isTransitioning = false;
            this.transitionData = null;
            this.navigationHistory = [];

            if (supportsViewTransitions) {
                this.init();
            } else {
                console.log('[NEXUS] View Transitions not supported, using fallback');
                this.initFallback();
            }
        }

        init() {
            // Track navigation history for back detection
            this.navigationHistory.push(window.location.href);

            // Intercept clicks on cards and links
            document.addEventListener('click', (e) => this.handleClick(e));

            // Handle browser back/forward
            window.addEventListener('popstate', (e) => this.handlePopState(e));

            // Assign unique view-transition-names to cards
            this.assignTransitionNames();

            console.log('[NEXUS] Shared transitions initialized');
        }

        initFallback() {
            // Simple fade transitions for unsupported browsers
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (link && this.shouldTransition(link)) {
                    document.body.classList.add('page-transitioning');
                }
            });
        }

        /**
         * Assign unique view-transition-names to dynamic cards
         */
        assignTransitionNames() {
            // Listing cards
            document.querySelectorAll('.listing-card, [data-listing-id]').forEach((card, i) => {
                const id = card.dataset.listingId || i;
                card.style.viewTransitionName = `listing-${id}`;

                const image = card.querySelector('img, .card-image, .listing-image');
                if (image) {
                    image.style.viewTransitionName = `listing-image-${id}`;
                }
            });

            // Member cards
            document.querySelectorAll('.member-card, [data-member-id]').forEach((card, i) => {
                const id = card.dataset.memberId || i;
                card.style.viewTransitionName = `member-${id}`;

                const avatar = card.querySelector('.avatar, img');
                if (avatar) {
                    avatar.style.viewTransitionName = `member-avatar-${id}`;
                }
            });

            // Event cards
            document.querySelectorAll('.event-card, [data-event-id]').forEach((card, i) => {
                const id = card.dataset.eventId || i;
                card.style.viewTransitionName = `event-${id}`;
            });
        }

        /**
         * Handle link clicks
         */
        handleClick(e) {
            const link = e.target.closest('a');
            if (!link || !this.shouldTransition(link)) return;

            // Find the clicked card (if any)
            const card = e.target.closest('[style*="view-transition-name"]');

            if (card) {
                // Store card info for the destination page
                const transitionName = card.style.viewTransitionName;
                sessionStorage.setItem('nexus-transition-source', transitionName);

                // Store the card's position for hero morphing
                const rect = card.getBoundingClientRect();
                sessionStorage.setItem('nexus-transition-rect', JSON.stringify({
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height
                }));
            }

            e.preventDefault();
            this.navigateTo(link.href);
        }

        /**
         * Check if we should use view transitions for this link
         */
        shouldTransition(link) {
            // Skip external links
            if (link.hostname !== window.location.hostname) return false;

            // Skip anchors
            if (link.href.startsWith('#')) return false;

            // Skip downloads and special links
            if (link.hasAttribute('download')) return false;
            if (link.target === '_blank') return false;
            if (link.classList.contains('no-transition')) return false;

            // Skip OAuth and authentication redirects (they redirect to external providers)
            if (link.pathname.includes('/oauth/') || link.pathname.includes('/auth/')) return false;

            // Skip login/logout/register pages (may have redirects)
            if (link.pathname.match(/\/(login|logout|register)(\/|$)/)) return false;

            // Skip federation pages (complex PHP rendering that doesn't work well with fetch)
            if (link.pathname.includes('/federation')) return false;

            // Skip if same page
            if (link.pathname === window.location.pathname) return false;

            return true;
        }

        /**
         * Navigate with view transition
         */
        async navigateTo(url, isBack = false) {
            if (this.isTransitioning) return;
            this.isTransitioning = true;

            // Set direction for CSS
            document.documentElement.classList.toggle('navigating-back', isBack);

            // Haptic feedback (only on user-initiated navigation)
            try {
                if (window.NexusNative?.Haptics) {
                    window.NexusNative.Haptics.impact('light');
                } else if (navigator.vibrate && document.hasFocus()) {
                    navigator.vibrate(10);
                }
            } catch (e) {
                // Vibration blocked - user hasn't interacted yet
            }

            try {
                const transition = document.startViewTransition(async () => {
                    // Fetch new page
                    const response = await fetch(url, { credentials: 'include' });
                    const html = await response.text();

                    // Parse and update DOM
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Update the page
                    this.updatePage(doc);

                    // Update URL
                    if (!isBack) {
                        history.pushState({}, '', url);
                        this.navigationHistory.push(url);
                    }
                });

                // Wait for transition to complete
                await transition.finished;

                // Match transition names on destination
                this.matchDestinationElements();

                // Re-assign transition names for new content
                this.assignTransitionNames();

                // Reinitialize any JS that needs it
                this.reinitializeScripts();

            } catch (error) {
                console.error('Transition failed:', error);
                // Fallback to normal navigation
                window.location.href = url;
            } finally {
                this.isTransitioning = false;
                document.documentElement.classList.remove('navigating-back');
                document.body.classList.add('view-transition-complete');

                setTimeout(() => {
                    document.body.classList.remove('view-transition-complete');
                }, 100);
            }
        }

        /**
         * Update page content from fetched document
         */
        updatePage(doc) {
            // Update title
            document.title = doc.title;

            // Clean up page-specific classes from html element
            document.documentElement.classList.remove('messages-page', 'messages-fullscreen', 'chat-page');
            document.documentElement.style.overflow = '';

            // Update body classes
            document.body.className = doc.body.className;
            document.body.style.overflow = '';

            // Update meta tags
            const newMeta = doc.querySelectorAll('meta[name="description"], meta[property^="og:"]');
            newMeta.forEach(meta => {
                const existing = document.querySelector(`meta[name="${meta.name}"], meta[property="${meta.getAttribute('property')}"]`);
                if (existing) {
                    existing.setAttribute('content', meta.getAttribute('content'));
                }
            });

            // Update hero
            const oldHero = document.querySelector('.htb-hero-header');
            const newHero = doc.querySelector('.htb-hero-header');
            if (oldHero && newHero) {
                oldHero.outerHTML = newHero.outerHTML;
            } else if (newHero && !oldHero) {
                // Insert hero if it doesn't exist
                const navbar = document.querySelector('.nexus-navbar');
                if (navbar) {
                    navbar.insertAdjacentHTML('afterend', newHero.outerHTML);
                }
            } else if (oldHero && !newHero) {
                // Remove hero if not on new page
                oldHero.remove();
            }

            // Update main content
            const contentSelectors = [
                '.htb-container',
                '.htb-container-full',
                'main',
                '#content',
                '.main-content'
            ];

            for (const selector of contentSelectors) {
                const oldContent = document.querySelector(selector);
                const newContent = doc.querySelector(selector);

                if (oldContent && newContent) {
                    oldContent.innerHTML = newContent.innerHTML;
                    break;
                }
            }

            // Update active states in navigation
            this.updateActiveStates();
        }

        /**
         * Match view-transition-names on destination page
         */
        matchDestinationElements() {
            const sourceName = sessionStorage.getItem('nexus-transition-source');
            if (!sourceName) return;

            // Try to find matching element on destination
            // For listing detail pages
            if (sourceName.startsWith('listing-')) {
                const listingId = sourceName.replace('listing-', '');
                const detailImage = document.querySelector('.listing-detail-image, .listing-hero-image, .detail-header img');
                if (detailImage) {
                    detailImage.style.viewTransitionName = sourceName + '-image';
                }
            }

            // For profile pages
            if (sourceName.startsWith('member-')) {
                const profileAvatar = document.querySelector('.profile-avatar, .profile-header img');
                if (profileAvatar) {
                    profileAvatar.style.viewTransitionName = sourceName + '-avatar';
                }
            }

            // Clear stored data
            sessionStorage.removeItem('nexus-transition-source');
            sessionStorage.removeItem('nexus-transition-rect');
        }

        /**
         * Update active navigation states
         */
        updateActiveStates() {
            const currentPath = window.location.pathname;

            // Bottom nav
            document.querySelectorAll('.nexus-native-nav-item').forEach(item => {
                const href = item.getAttribute('href');
                const isActive = href === currentPath ||
                                 (href !== '/' && currentPath.startsWith(href));
                item.classList.toggle('active', isActive);
            });

            // Update pill position
            if (window.NexusNav?.updatePill) {
                const activeItem = document.querySelector('.nexus-native-nav-item.active');
                if (activeItem) {
                    window.NexusNav.updatePill(activeItem);
                }
            }

            // Drawer items
            document.querySelectorAll('.nexus-drawer-item').forEach(item => {
                const href = item.getAttribute('href');
                const isActive = href === currentPath;
                item.classList.toggle('active', isActive);
            });
        }

        /**
         * Reinitialize scripts after page transition
         */
        reinitializeScripts() {
            // Re-init tab icons
            if (window.NexusTabIcons) {
                window.NexusTabIcons = new window.NexusTabIcons.constructor();
            }

            // Re-init micro interactions
            if (window.NexusMicro) {
                window.NexusMicro = new window.NexusMicro.constructor();
            }

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'instant' });

            // Dispatch custom event for other scripts
            document.dispatchEvent(new CustomEvent('nexus:pagechange', {
                detail: { url: window.location.href }
            }));
        }

        /**
         * Handle browser back/forward
         */
        handlePopState(e) {
            const currentIndex = this.navigationHistory.indexOf(window.location.href);
            const isBack = currentIndex !== -1 && currentIndex < this.navigationHistory.length - 1;

            // Prevent default and use our transition
            if (supportsViewTransitions) {
                this.navigateTo(window.location.href, isBack);
            }
        }
    }

    // ============================================
    // HERO IMAGE PRELOADER
    // ============================================
    class HeroPreloader {
        constructor() {
            this.preloadedImages = new Map();
            this.init();
        }

        init() {
            // Preload hero images from links on hover
            document.addEventListener('mouseover', (e) => {
                const link = e.target.closest('a');
                if (link && !this.preloadedImages.has(link.href)) {
                    this.preloadHero(link.href);
                }
            }, { passive: true });

            // Also preload on touch start for mobile
            document.addEventListener('touchstart', (e) => {
                const link = e.target.closest('a');
                if (link && !this.preloadedImages.has(link.href)) {
                    this.preloadHero(link.href);
                }
            }, { passive: true });
        }

        async preloadHero(url) {
            try {
                // Mark as loading
                this.preloadedImages.set(url, 'loading');

                // Fetch the page HTML
                const response = await fetch(url, { priority: 'low' });
                const html = await response.text();

                // Parse and find hero image
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const heroImage = doc.querySelector('.htb-hero-header img, .hero-image, .listing-image');
                if (heroImage && heroImage.src) {
                    // Preload the image
                    const img = new Image();
                    img.src = heroImage.src;
                    this.preloadedImages.set(url, img.src);
                }
            } catch (e) {
                // Silently fail
                this.preloadedImages.delete(url);
            }
        }
    }

    // ============================================
    // INITIALIZE
    // ============================================
    function init() {
        window.NexusSharedTransitions = new SharedTransitions();

        if (supportsViewTransitions) {
            window.NexusHeroPreloader = new HeroPreloader();
        }

        console.log('[NEXUS] Shared element transitions ready');
    }

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
