/**
 * Project NEXUS - Mobile Native Experience JavaScript
 * Includes: Service worker, pull-to-refresh, swipe gestures, page transitions, toasts
 */

(function() {
    'use strict';

    // ============================================
    // 1. SERVICE WORKER REGISTRATION
    // ============================================

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then((registration) => {
                    console.log('[NEXUS] Service Worker registered:', registration.scope);

                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New version available
                                NexusMobile.showToast('Update available! Refresh to update.', 'info');
                            }
                        });
                    });
                })
                .catch((error) => {
                    console.log('[NEXUS] Service Worker registration failed:', error);
                });
        });
    }


    // ============================================
    // 2. NEXUS MOBILE NAMESPACE
    // ============================================

    window.NexusMobile = {
        // Configuration
        config: {
            pullToRefreshThreshold: 80,
            swipeBackThreshold: 100,
            toastDuration: 3000
        },

        // ============================================
        // 3. TOAST NOTIFICATIONS
        // ============================================

        showToast: function(message, type = 'default', duration = null) {
            let container = document.querySelector('.nexus-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'nexus-toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `nexus-toast ${type}`;

            const icons = {
                success: '<i class="fa-solid fa-check-circle"></i>',
                error: '<i class="fa-solid fa-times-circle"></i>',
                warning: '<i class="fa-solid fa-exclamation-triangle"></i>',
                info: '<i class="fa-solid fa-info-circle"></i>',
                default: ''
            };

            toast.innerHTML = `${icons[type] || ''}<span>${message}</span>`;
            container.appendChild(toast);

            // Auto remove
            setTimeout(() => {
                toast.classList.add('exit');
                setTimeout(() => toast.remove(), 200);
            }, duration || this.config.toastDuration);

            return toast;
        },


        // ============================================
        // 4. PULL-TO-REFRESH (Scroll-Safe Implementation)
        // ============================================

        // Active PTR instances for cleanup
        // Pull-to-refresh removed - was causing conflicts with scrolling

        // Removed: initGlobalPullToRefresh method - no longer used
        initGlobalPullToRefresh: function() {
            // Pull-to-refresh feature removed
            console.log('[NEXUS] Pull-to-refresh has been permanently removed');
        },

        // Removed: resetPullIndicator method - no longer used
        resetPullIndicator: function(indicator) {
            // Pull-to-refresh feature removed
        },


        // ============================================
        // 5. SWIPE-TO-GO-BACK (Scroll-Safe Implementation)
        // ============================================

        /**
         * Initialize iOS-style swipe from left edge to go back
         * Uses careful gesture detection to avoid scroll conflicts
         */
        initSwipeBack: function() {
            // CRITICAL: Skip on chat pages
            if (document.body.classList.contains('no-ptr') ||
                document.body.classList.contains('chat-page') ||
                document.body.classList.contains('chat-fullscreen') ||
                document.documentElement.classList.contains('chat-page')) {
                console.log('[NEXUS SwipeBack] Skipped - chat page detected');
                return;
            }

            // Don't enable if no history to go back to
            if (window.history.length <= 1) return;

            const self = this;
            const edgeWidth = 25; // Only trigger from left 25px
            const threshold = 100; // Minimum swipe distance to trigger
            const velocityThreshold = 0.5; // Minimum velocity to trigger

            // State
            let startX = 0;
            let startY = 0;
            let currentX = 0;
            let startTime = 0;
            let isEdgeSwipe = false;
            let gestureConfirmed = false;
            let isNavigating = false;

            // Create visual indicator
            const indicator = document.createElement('div');
            indicator.className = 'nexus-swipe-back-indicator';
            indicator.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
            indicator.setAttribute('aria-hidden', 'true');
            document.body.appendChild(indicator);

            // Create page overlay for visual feedback
            const overlay = document.createElement('div');
            overlay.className = 'nexus-swipe-back-overlay';
            document.body.appendChild(overlay);

            function handleTouchStart(e) {
                if (isNavigating) return;

                const touch = e.touches[0];

                // Only activate if starting from left edge
                if (touch.clientX > edgeWidth) return;

                startX = touch.clientX;
                startY = touch.clientY;
                currentX = startX;
                startTime = Date.now();
                isEdgeSwipe = true;
                gestureConfirmed = false;
            }

            function handleTouchMove(e) {
                if (!isEdgeSwipe || isNavigating) return;

                const touch = e.touches[0];
                currentX = touch.clientX;
                const deltaX = currentX - startX;
                const deltaY = Math.abs(touch.clientY - startY);

                // Need some movement to determine intent
                if (!gestureConfirmed) {
                    const totalMove = deltaX + deltaY;
                    if (totalMove < 10) return;

                    // If vertical movement > horizontal, it's a scroll
                    if (deltaY > deltaX) {
                        resetState();
                        return;
                    }

                    // If swiping left (negative), cancel
                    if (deltaX < 0) {
                        resetState();
                        return;
                    }

                    // Confirmed as horizontal swipe from edge
                    gestureConfirmed = true;
                    indicator.classList.add('visible');
                    overlay.classList.add('visible');
                }

                // Only act on rightward swipe
                if (deltaX <= 0) {
                    resetVisuals();
                    return;
                }

                // Prevent scroll during swipe
                e.preventDefault();

                // Calculate progress with resistance
                const maxDistance = window.innerWidth * 0.4;
                const progress = Math.min(deltaX / threshold, 1);
                const visualProgress = Math.min(deltaX / maxDistance, 1);

                // Update indicator position
                const indicatorX = Math.min(deltaX * 0.3, 60);
                indicator.style.transform = `translateY(-50%) translateX(${indicatorX}px) scale(${0.8 + progress * 0.2})`;
                indicator.style.opacity = Math.min(progress * 1.5, 1);

                // Update overlay opacity
                overlay.style.opacity = visualProgress * 0.3;

                // Ready state when past threshold
                if (deltaX >= threshold) {
                    if (!indicator.classList.contains('ready')) {
                        indicator.classList.add('ready');
                        self.haptic('medium');
                    }
                } else {
                    indicator.classList.remove('ready');
                }
            }

            function handleTouchEnd() {
                if (!isEdgeSwipe || isNavigating) return;

                const deltaX = currentX - startX;
                const elapsed = Date.now() - startTime;
                const velocity = deltaX / elapsed;

                // Trigger if past threshold OR fast swipe
                if (gestureConfirmed && (deltaX >= threshold || velocity > velocityThreshold)) {
                    triggerNavigation();
                } else {
                    resetVisuals();
                }

                resetState();
            }

            function triggerNavigation() {
                isNavigating = true;
                indicator.classList.add('navigating');
                self.haptic('success');

                // Animate indicator off screen
                indicator.style.transform = 'translateY(-50%) translateX(80px) scale(1)';
                overlay.style.opacity = '0.5';

                // Small delay for visual feedback, then navigate
                setTimeout(() => {
                    window.history.back();
                }, 150);
            }

            function resetState() {
                isEdgeSwipe = false;
                gestureConfirmed = false;
                startX = 0;
                startY = 0;
                currentX = 0;
                startTime = 0;
            }

            function resetVisuals() {
                indicator.classList.remove('visible', 'ready', 'navigating');
                indicator.style.transform = 'translateY(-50%) translateX(-60px) scale(0.8)';
                indicator.style.opacity = '0';
                overlay.classList.remove('visible');
                overlay.style.opacity = '0';
            }

            // Attach event listeners
            document.addEventListener('touchstart', handleTouchStart, { passive: true });
            document.addEventListener('touchmove', handleTouchMove, { passive: false });
            document.addEventListener('touchend', handleTouchEnd, { passive: true });
            document.addEventListener('touchcancel', () => { resetState(); resetVisuals(); }, { passive: true });

            // Reset navigating state when page loads (in case of bfcache)
            window.addEventListener('pageshow', () => {
                isNavigating = false;
                resetVisuals();
            });

            console.log('[NEXUS] Swipe-back gesture initialized');
        },


        // ============================================
        // 6. SWIPEABLE LIST ITEMS
        // ============================================

        initSwipeableItems: function(selector) {
            // Skip on chat pages
            if (document.body.classList.contains('chat-page') ||
                document.body.classList.contains('chat-fullscreen')) {
                return;
            }

            const items = document.querySelectorAll(selector);

            items.forEach(item => {
                const content = item.querySelector('.nexus-swipe-item-content');
                if (!content) return;

                let startX = 0;
                let currentX = 0;
                let offsetX = 0;

                content.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    content.style.transition = 'none';
                }, { passive: true });

                content.addEventListener('touchmove', (e) => {
                    currentX = e.touches[0].clientX;
                    offsetX = Math.min(0, currentX - startX); // Only allow left swipe
                    offsetX = Math.max(-100, offsetX); // Limit swipe distance

                    content.style.transform = `translateX(${offsetX}px)`;
                }, { passive: true });

                content.addEventListener('touchend', () => {
                    content.style.transition = 'transform 0.2s';

                    if (offsetX < -50) {
                        content.style.transform = 'translateX(-80px)';
                    } else {
                        content.style.transform = 'translateX(0)';
                    }

                    startX = 0;
                    currentX = 0;
                }, { passive: true });

                // Reset on click elsewhere
                document.addEventListener('touchstart', (e) => {
                    if (!item.contains(e.target)) {
                        content.style.transition = 'transform 0.2s';
                        content.style.transform = 'translateX(0)';
                    }
                }, { passive: true });
            });
        },


        // ============================================
        // 7. INFINITE SCROLL
        // ============================================

        /**
         * Initialize infinite scroll on a feed container
         * @param {Object} options - Configuration options
         * @param {string} options.container - Selector for the scrollable container
         * @param {string} options.itemSelector - Selector for feed items
         * @param {string} options.loadMoreUrl - API endpoint for loading more items
         * @param {number} options.threshold - Distance from bottom to trigger load (default: 300)
         * @param {Function} options.onLoad - Callback when items are loaded
         * @param {Function} options.renderItem - Function to render new items (receives HTML string)
         */
        initInfiniteScroll: function(options = {}) {
            const defaults = {
                container: '[data-infinite-scroll]',
                itemSelector: '.feed-post, .fds-post, .listing-card',
                loadMoreUrl: null,
                threshold: 300,
                onLoad: null,
                renderItem: null,
                pageParam: 'page',
                perPage: 10
            };

            const config = { ...defaults, ...options };
            const container = typeof config.container === 'string'
                ? document.querySelector(config.container)
                : config.container;

            if (!container) return null;

            // Prevent duplicate initialization
            if (container.dataset.infiniteScrollInit) return null;
            container.dataset.infiniteScrollInit = 'true';

            const self = this;
            let currentPage = 1;
            let isLoading = false;
            let hasMore = true;

            // Try to get URL from data attribute if not provided
            const loadMoreUrl = config.loadMoreUrl || container.dataset.infiniteScrollUrl;
            if (!loadMoreUrl) {
                console.warn('[NEXUS Infinite] No load URL specified');
                return null;
            }

            // Create loading indicator
            const loader = document.createElement('div');
            loader.className = 'nexus-infinite-loader';
            loader.innerHTML = `
                <div class="nexus-infinite-spinner"></div>
                <span>Loading more...</span>
            `;
            loader.style.display = 'none';
            container.appendChild(loader);

            // Create end indicator
            const endIndicator = document.createElement('div');
            endIndicator.className = 'nexus-infinite-end';
            endIndicator.innerHTML = `<span>You've reached the end</span>`;
            endIndicator.style.display = 'none';
            container.appendChild(endIndicator);

            // Determine scroll target (container or window)
            const scrollTarget = container.scrollHeight > container.clientHeight ? container : window;
            const isWindowScroll = scrollTarget === window;

            function getScrollPosition() {
                if (isWindowScroll) {
                    return {
                        scrollTop: window.scrollY,
                        scrollHeight: document.documentElement.scrollHeight,
                        clientHeight: window.innerHeight
                    };
                }
                return {
                    scrollTop: container.scrollTop,
                    scrollHeight: container.scrollHeight,
                    clientHeight: container.clientHeight
                };
            }

            function checkAndLoad() {
                if (isLoading || !hasMore) return;

                const { scrollTop, scrollHeight, clientHeight } = getScrollPosition();
                const distanceFromBottom = scrollHeight - scrollTop - clientHeight;

                if (distanceFromBottom < config.threshold) {
                    loadMore();
                }
            }

            async function loadMore() {
                if (isLoading || !hasMore) return;

                isLoading = true;
                loader.style.display = 'flex';
                currentPage++;

                try {
                    // Build URL with page parameter
                    const url = new URL(loadMoreUrl, window.location.origin);
                    url.searchParams.set(config.pageParam, currentPage);
                    url.searchParams.set('per_page', config.perPage);

                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json, text/html'
                        },
                        credentials: 'include'
                    });

                    if (!response.ok) throw new Error('Failed to load more items');

                    const contentType = response.headers.get('content-type');
                    let items = [];
                    let html = '';

                    if (contentType && contentType.includes('application/json')) {
                        const data = await response.json();
                        items = data.items || data.posts || data.data || [];
                        html = data.html || '';
                        hasMore = data.hasMore !== false && items.length >= config.perPage;
                    } else {
                        html = await response.text();
                        // Check if we got content
                        hasMore = html.trim().length > 50;
                    }

                    // Insert new content
                    if (html) {
                        // Insert before loader
                        loader.insertAdjacentHTML('beforebegin', html);
                    } else if (items.length && config.renderItem) {
                        items.forEach(item => {
                            const itemHtml = config.renderItem(item);
                            loader.insertAdjacentHTML('beforebegin', itemHtml);
                        });
                    }

                    // Callback
                    if (config.onLoad) {
                        config.onLoad({ items, html, page: currentPage, hasMore });
                    }

                    // Re-initialize any needed features on new items
                    self.initSwipeableItems('.nexus-swipe-item:not([data-swipe-init])');

                    // Haptic feedback
                    if (items.length || html) {
                        self.haptic('light');
                    }

                } catch (error) {
                    console.error('[NEXUS Infinite] Load error:', error);
                    currentPage--; // Revert page increment on error
                    self.showToast('Failed to load more items', 'error');
                } finally {
                    isLoading = false;
                    loader.style.display = 'none';

                    if (!hasMore) {
                        endIndicator.style.display = 'flex';
                    }
                }
            }

            // Throttled scroll handler
            let scrollTimeout;
            function handleScroll() {
                if (scrollTimeout) return;
                scrollTimeout = setTimeout(() => {
                    scrollTimeout = null;
                    checkAndLoad();
                }, 100);
            }

            // Attach scroll listener
            scrollTarget.addEventListener('scroll', handleScroll, { passive: true });

            // Initial check (in case page is already scrolled)
            setTimeout(checkAndLoad, 500);

            console.log('[NEXUS Infinite] Initialized on', container.className || container.tagName);

            // Return control object
            return {
                container,
                loadMore,
                reset: () => {
                    currentPage = 1;
                    hasMore = true;
                    endIndicator.style.display = 'none';
                },
                destroy: () => {
                    scrollTarget.removeEventListener('scroll', handleScroll);
                    loader.remove();
                    endIndicator.remove();
                    delete container.dataset.infiniteScrollInit;
                }
            };
        },

        /**
         * Auto-initialize infinite scroll on marked containers
         */
        initGlobalInfiniteScroll: function() {
            const containers = document.querySelectorAll('[data-infinite-scroll]');
            containers.forEach(container => {
                this.initInfiniteScroll({ container });
            });
        },


        // ============================================
        // 8. LONG-PRESS CONTEXT MENU
        // ============================================

        /**
         * Initialize long-press context menus
         * Add data-longpress-menu="menuId" to elements
         * Define menus with data-menu-id="menuId"
         */
        initLongPressMenus: function() {
            // Skip on chat pages to avoid interference
            if (document.body.classList.contains('chat-page') ||
                document.body.classList.contains('chat-fullscreen')) {
                return null;
            }

            const self = this;
            const longPressDelay = 500; // ms
            const moveThreshold = 10; // pixels - cancel if moved more than this

            let pressTimer = null;
            let startX = 0;
            let startY = 0;
            let currentTarget = null;
            let isLongPress = false;

            // Create backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'nexus-context-backdrop';
            document.body.appendChild(backdrop);

            // Create menu container
            const menuContainer = document.createElement('div');
            menuContainer.className = 'nexus-context-menu';
            menuContainer.setAttribute('role', 'menu');
            menuContainer.setAttribute('aria-hidden', 'true');
            document.body.appendChild(menuContainer);

            function showMenu(target, x, y) {
                const menuId = target.dataset.longpressMenu;
                const menuTemplate = document.querySelector(`[data-menu-id="${menuId}"]`);

                let menuItems = [];

                if (menuTemplate) {
                    // Use predefined menu template
                    menuItems = Array.from(menuTemplate.querySelectorAll('[data-action]')).map(item => ({
                        action: item.dataset.action,
                        label: item.textContent || item.dataset.label,
                        icon: item.dataset.icon,
                        danger: item.dataset.danger === 'true'
                    }));
                } else {
                    // Generate default menu based on element type
                    menuItems = getDefaultMenuItems(target);
                }

                if (menuItems.length === 0) return;

                // Build menu HTML
                menuContainer.innerHTML = menuItems.map(item => `
                    <button class="nexus-context-item ${item.danger ? 'danger' : ''}"
                            data-action="${item.action}"
                            role="menuitem">
                        ${item.icon ? `<i class="${item.icon}"></i>` : ''}
                        <span>${item.label}</span>
                    </button>
                `).join('');

                // Position menu
                const menuRect = menuContainer.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;

                let menuX = x - (menuContainer.offsetWidth / 2);
                let menuY = y + 10;

                // Keep within viewport
                menuX = Math.max(10, Math.min(menuX, viewportWidth - menuContainer.offsetWidth - 10));
                menuY = Math.max(10, Math.min(menuY, viewportHeight - menuContainer.offsetHeight - 10));

                // If menu would be below viewport, show above touch point
                if (menuY + menuContainer.offsetHeight > viewportHeight - 20) {
                    menuY = y - menuContainer.offsetHeight - 10;
                }

                menuContainer.style.left = `${menuX}px`;
                menuContainer.style.top = `${menuY}px`;

                // Show menu with animation
                backdrop.classList.add('visible');
                menuContainer.classList.add('visible');
                menuContainer.setAttribute('aria-hidden', 'false');

                // Store target for action handling
                menuContainer.dataset.targetId = target.dataset.id || '';
                menuContainer.dataset.targetType = target.dataset.type || '';

                // Haptic feedback
                self.haptic('medium');

                // Prevent text selection
                document.body.style.userSelect = 'none';
                document.body.style.webkitUserSelect = 'none';
            }

            function getDefaultMenuItems(target) {
                const items = [];
                const type = target.dataset.type || guessElementType(target);

                switch (type) {
                    case 'post':
                        items.push(
                            { action: 'share', label: 'Share', icon: 'fa-solid fa-share' },
                            { action: 'copy-link', label: 'Copy Link', icon: 'fa-solid fa-link' },
                            { action: 'save', label: 'Save Post', icon: 'fa-solid fa-bookmark' }
                        );
                        if (target.dataset.isOwner === 'true') {
                            items.push(
                                { action: 'edit', label: 'Edit', icon: 'fa-solid fa-pen' },
                                { action: 'delete', label: 'Delete', icon: 'fa-solid fa-trash', danger: true }
                            );
                        } else {
                            items.push(
                                { action: 'report', label: 'Report', icon: 'fa-solid fa-flag', danger: true }
                            );
                        }
                        break;

                    case 'comment':
                        items.push(
                            { action: 'reply', label: 'Reply', icon: 'fa-solid fa-reply' },
                            { action: 'copy', label: 'Copy Text', icon: 'fa-solid fa-copy' }
                        );
                        if (target.dataset.isOwner === 'true') {
                            items.push(
                                { action: 'edit', label: 'Edit', icon: 'fa-solid fa-pen' },
                                { action: 'delete', label: 'Delete', icon: 'fa-solid fa-trash', danger: true }
                            );
                        }
                        break;

                    case 'user':
                        items.push(
                            { action: 'view-profile', label: 'View Profile', icon: 'fa-solid fa-user' },
                            { action: 'message', label: 'Send Message', icon: 'fa-solid fa-envelope' },
                            { action: 'copy-profile', label: 'Copy Profile Link', icon: 'fa-solid fa-link' }
                        );
                        break;

                    case 'image':
                        items.push(
                            { action: 'view', label: 'View Full Size', icon: 'fa-solid fa-expand' },
                            { action: 'download', label: 'Download', icon: 'fa-solid fa-download' },
                            { action: 'share', label: 'Share', icon: 'fa-solid fa-share' }
                        );
                        break;

                    case 'link':
                        items.push(
                            { action: 'open', label: 'Open Link', icon: 'fa-solid fa-external-link' },
                            { action: 'copy-link', label: 'Copy Link', icon: 'fa-solid fa-link' },
                            { action: 'share', label: 'Share', icon: 'fa-solid fa-share' }
                        );
                        break;

                    default:
                        items.push(
                            { action: 'copy', label: 'Copy', icon: 'fa-solid fa-copy' },
                            { action: 'share', label: 'Share', icon: 'fa-solid fa-share' }
                        );
                }

                return items;
            }

            function guessElementType(target) {
                if (target.closest('.fds-post, .feed-post, [data-post-id]')) return 'post';
                if (target.closest('.comment, [data-comment-id]')) return 'comment';
                if (target.closest('.user-card, .avatar, [data-user-id]')) return 'user';
                if (target.tagName === 'IMG' || target.closest('img')) return 'image';
                if (target.tagName === 'A' || target.closest('a[href^="http"]')) return 'link';
                return 'default';
            }

            function hideMenu() {
                backdrop.classList.remove('visible');
                menuContainer.classList.remove('visible');
                menuContainer.setAttribute('aria-hidden', 'true');
                document.body.style.userSelect = '';
                document.body.style.webkitUserSelect = '';
                currentTarget = null;
            }

            function handleAction(action) {
                const targetId = menuContainer.dataset.targetId;
                const targetType = menuContainer.dataset.targetType;

                self.haptic('light');
                hideMenu();

                // Dispatch custom event for handling
                const event = new CustomEvent('nexus:contextAction', {
                    bubbles: true,
                    detail: { action, targetId, targetType, target: currentTarget }
                });
                document.dispatchEvent(event);

                // Handle common actions
                switch (action) {
                    case 'copy-link':
                        const url = currentTarget?.dataset.url || window.location.href;
                        navigator.clipboard.writeText(url).then(() => {
                            self.showToast('Link copied!', 'success');
                        });
                        break;

                    case 'copy':
                        const text = currentTarget?.textContent || currentTarget?.innerText;
                        if (text) {
                            navigator.clipboard.writeText(text.trim()).then(() => {
                                self.showToast('Copied!', 'success');
                            });
                        }
                        break;

                    case 'share':
                        if (navigator.share) {
                            navigator.share({
                                title: document.title,
                                url: currentTarget?.dataset.url || window.location.href
                            });
                        }
                        break;
                }
            }

            // Touch start - begin timer
            function handleTouchStart(e) {
                const target = e.target.closest('[data-longpress-menu], .fds-post, .feed-post, [data-type]');
                if (!target) return;

                const touch = e.touches[0];
                startX = touch.clientX;
                startY = touch.clientY;
                currentTarget = target;
                isLongPress = false;

                pressTimer = setTimeout(() => {
                    isLongPress = true;
                    showMenu(target, startX, startY);
                }, longPressDelay);
            }

            // Touch move - cancel if moved too much
            function handleTouchMove(e) {
                if (!pressTimer) return;

                const touch = e.touches[0];
                const deltaX = Math.abs(touch.clientX - startX);
                const deltaY = Math.abs(touch.clientY - startY);

                if (deltaX > moveThreshold || deltaY > moveThreshold) {
                    clearTimeout(pressTimer);
                    pressTimer = null;
                }
            }

            // Touch end - cancel timer
            function handleTouchEnd(e) {
                if (pressTimer) {
                    clearTimeout(pressTimer);
                    pressTimer = null;
                }

                // Prevent click if long press occurred
                if (isLongPress) {
                    e.preventDefault();
                    isLongPress = false;
                }
            }

            // Menu item click
            function handleMenuClick(e) {
                const item = e.target.closest('.nexus-context-item');
                if (item) {
                    handleAction(item.dataset.action);
                }
            }

            // Backdrop click - close menu
            function handleBackdropClick() {
                hideMenu();
            }

            // Escape key - close menu
            function handleKeydown(e) {
                if (e.key === 'Escape' && menuContainer.classList.contains('visible')) {
                    hideMenu();
                }
            }

            // Attach event listeners
            document.addEventListener('touchstart', handleTouchStart, { passive: true });
            document.addEventListener('touchmove', handleTouchMove, { passive: true });
            document.addEventListener('touchend', handleTouchEnd, { passive: false });
            document.addEventListener('touchcancel', () => {
                clearTimeout(pressTimer);
                pressTimer = null;
            }, { passive: true });

            menuContainer.addEventListener('click', handleMenuClick);
            backdrop.addEventListener('click', handleBackdropClick);
            document.addEventListener('keydown', handleKeydown);

            console.log('[NEXUS] Long-press menus initialized');

            // Return control object
            return {
                show: showMenu,
                hide: hideMenu,
                destroy: () => {
                    document.removeEventListener('touchstart', handleTouchStart);
                    document.removeEventListener('touchmove', handleTouchMove);
                    document.removeEventListener('touchend', handleTouchEnd);
                    backdrop.remove();
                    menuContainer.remove();
                }
            };
        },


        // ============================================
        // 9. COLLAPSING HEADER ON SCROLL
        // ============================================

        /**
         * Initialize collapsing header behavior
         * Header shrinks/hides on scroll down, reappears on scroll up
         */
        initCollapsingHeader: function() {
            // Skip on chat pages - chat has its own fixed header
            if (document.body.classList.contains('chat-page') ||
                document.body.classList.contains('chat-fullscreen')) {
                return null;
            }

            const self = this;

            // Find the header element
            const header = document.querySelector('.htb-header, .nexus-header, header.site-header, [data-collapsing-header]');
            if (!header) {
                console.log('[NEXUS] No collapsing header found');
                return null;
            }

            // Don't initialize twice
            if (header.dataset.collapsingInit) return null;
            header.dataset.collapsingInit = 'true';

            // Configuration
            const config = {
                scrollThreshold: 10,        // Minimum scroll to start tracking
                hideThreshold: 60,          // Scroll distance to fully hide
                showOnScrollUp: true,       // Show header when scrolling up
                compactMode: true,          // Enable compact header mode
                compactThreshold: 100       // Scroll distance to enter compact mode
            };

            // State
            let lastScrollY = 0;
            let scrollDirection = 'up';
            let isHidden = false;
            let isCompact = false;
            let ticking = false;
            let accumulatedDelta = 0;

            // Get original header height for body padding
            const originalHeight = header.offsetHeight;

            // Add collapsing header class
            header.classList.add('nexus-collapsing-header');

            // Store original styles
            header.dataset.originalHeight = originalHeight;

            function getScrollY() {
                return window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
            }

            function updateHeader() {
                const currentScrollY = getScrollY();
                const delta = currentScrollY - lastScrollY;

                // Determine scroll direction
                if (Math.abs(delta) < config.scrollThreshold) {
                    ticking = false;
                    return;
                }

                scrollDirection = delta > 0 ? 'down' : 'up';
                accumulatedDelta = scrollDirection === 'down'
                    ? Math.min(accumulatedDelta + delta, config.hideThreshold)
                    : Math.max(accumulatedDelta + delta, 0);

                // At top of page - always show full header
                if (currentScrollY <= 0) {
                    showHeader();
                    removeCompact();
                    accumulatedDelta = 0;
                }
                // Scrolling down
                else if (scrollDirection === 'down' && currentScrollY > config.hideThreshold) {
                    // Enter compact mode first
                    if (config.compactMode && currentScrollY > config.compactThreshold && !isCompact) {
                        makeCompact();
                    }

                    // Hide header after scrolling past threshold
                    if (accumulatedDelta >= config.hideThreshold && !isHidden) {
                        hideHeader();
                    }
                }
                // Scrolling up
                else if (scrollDirection === 'up' && config.showOnScrollUp) {
                    // Show header immediately on scroll up
                    if (isHidden) {
                        showHeader();
                    }

                    // Remove compact mode near top
                    if (currentScrollY < config.compactThreshold && isCompact) {
                        removeCompact();
                    }
                }

                lastScrollY = currentScrollY;
                ticking = false;
            }

            function hideHeader() {
                if (isHidden) return;
                isHidden = true;
                header.classList.add('nexus-header-hidden');
                header.classList.remove('nexus-header-visible');
                document.body.classList.add('nexus-header-is-hidden');
            }

            function showHeader() {
                if (!isHidden) return;
                isHidden = false;
                header.classList.remove('nexus-header-hidden');
                header.classList.add('nexus-header-visible');
                document.body.classList.remove('nexus-header-is-hidden');
                self.haptic('light');
            }

            function makeCompact() {
                if (isCompact) return;
                isCompact = true;
                header.classList.add('nexus-header-compact');
                document.body.classList.add('nexus-header-is-compact');
            }

            function removeCompact() {
                if (!isCompact) return;
                isCompact = false;
                header.classList.remove('nexus-header-compact');
                document.body.classList.remove('nexus-header-is-compact');
            }

            function handleScroll() {
                if (!ticking) {
                    window.requestAnimationFrame(updateHeader);
                    ticking = true;
                }
            }

            // Attach scroll listener with passive flag for performance
            window.addEventListener('scroll', handleScroll, { passive: true });

            // Handle orientation changes
            window.addEventListener('orientationchange', () => {
                setTimeout(() => {
                    lastScrollY = getScrollY();
                }, 100);
            });

            // Reset on page visibility (for bfcache)
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    lastScrollY = getScrollY();
                    if (lastScrollY <= 0) {
                        showHeader();
                        removeCompact();
                    }
                }
            });

            console.log('[NEXUS] Collapsing header initialized');

            // Return control object
            return {
                header,
                hide: hideHeader,
                show: showHeader,
                compact: makeCompact,
                expand: removeCompact,
                isHidden: () => isHidden,
                isCompact: () => isCompact,
                destroy: () => {
                    window.removeEventListener('scroll', handleScroll);
                    header.classList.remove('nexus-collapsing-header', 'nexus-header-hidden', 'nexus-header-visible', 'nexus-header-compact');
                    document.body.classList.remove('nexus-header-is-hidden', 'nexus-header-is-compact');
                    delete header.dataset.collapsingInit;
                }
            };
        },


        // ============================================
        // 10. PAGE TRANSITIONS
        // ============================================

        initPageTransitions: function() {
            // Skip on chat pages to avoid link interception
            if (document.body.classList.contains('chat-page') ||
                document.body.classList.contains('chat-fullscreen')) {
                return;
            }

            // Use View Transitions API if supported
            if (document.startViewTransition) {
                document.addEventListener('click', (e) => {
                    const link = e.target.closest('a[href]');
                    if (!link) return;

                    const href = link.getAttribute('href');

                    // Skip external links, anchors, and special links
                    if (!href ||
                        href.startsWith('#') ||
                        href.startsWith('http') ||
                        href.startsWith('mailto:') ||
                        href.startsWith('tel:') ||
                        link.target === '_blank' ||
                        link.hasAttribute('download')) {
                        return;
                    }

                    e.preventDefault();

                    document.startViewTransition(() => {
                        window.location.href = href;
                    });
                });
            } else {
                // Fallback: Add fade animation on page load
                document.body.classList.add('page-transition-enter');
            }
        },


        // ============================================
        // 8. LOADING OVERLAY
        // ============================================

        showLoading: function(message = 'Loading...') {
            let overlay = document.querySelector('.nexus-loading-overlay');

            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'nexus-loading-overlay';
                overlay.innerHTML = `
                    <div class="nexus-loading-spinner"></div>
                    <div class="nexus-loading-text">${message}</div>
                `;
                document.body.appendChild(overlay);
            }

            overlay.querySelector('.nexus-loading-text').textContent = message;
            requestAnimationFrame(() => overlay.classList.add('visible'));

            return overlay;
        },

        hideLoading: function() {
            const overlay = document.querySelector('.nexus-loading-overlay');
            if (overlay) {
                overlay.classList.remove('visible');
            }
        },


        // ============================================
        // 9. SKELETON LOADERS
        // ============================================

        showSkeletons: function(container, count = 3, type = 'card') {
            if (!container) return;

            container.innerHTML = '';

            for (let i = 0; i < count; i++) {
                const skeleton = document.createElement('div');
                skeleton.className = 'skeleton-card-full';
                skeleton.innerHTML = `
                    <div class="skeleton-header">
                        <div class="skeleton skeleton-avatar"></div>
                        <div style="flex: 1;">
                            <div class="skeleton skeleton-text" style="width: 60%;"></div>
                            <div class="skeleton skeleton-text" style="width: 40%;"></div>
                        </div>
                    </div>
                    <div class="skeleton-body">
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text" style="width: 80%;"></div>
                    </div>
                `;
                container.appendChild(skeleton);
            }
        },


        // ============================================
        // 10. BOTTOM NAV HIGHLIGHTING
        // ============================================

        initBottomNav: function() {
            const nav = document.querySelector('.nexus-bottom-nav');
            if (!nav) return;

            const currentPath = window.location.pathname;
            const items = nav.querySelectorAll('.nexus-bottom-nav-item');

            items.forEach(item => {
                const href = item.getAttribute('href');
                if (currentPath === href || (href !== '/' && currentPath.startsWith(href))) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Add body class for padding
            document.body.classList.add('has-bottom-nav');
        },


        // ============================================
        // 11. HAPTIC FEEDBACK (where supported)
        // ============================================

        // Track if user has interacted (required for vibrate API)
        userHasInteracted: false,

        initHapticTracking: function() {
            const self = this;
            ['touchstart', 'mousedown', 'keydown'].forEach(event => {
                document.addEventListener(event, () => { self.userHasInteracted = true; }, { once: true, passive: true });
            });
        },

        haptic: function(type = 'light') {
            // Only vibrate after user interaction (browser requirement)
            if (!this.userHasInteracted || !('vibrate' in navigator)) return;

            const patterns = {
                light: [10],
                medium: [20],
                heavy: [30],
                success: [10, 50, 10],
                warning: [30, 50, 30],
                error: [50, 50, 50]
            };
            navigator.vibrate(patterns[type] || patterns.light);
        },


        // ============================================
        // 12. OFFLINE INDICATOR
        // ============================================

        offlineIndicator: null,
        onlineIndicator: null,

        initOfflineIndicator: function() {
            // Only enable offline indicator on mobile devices
            // Desktop browsers have unreliable navigator.onLine detection
            if (window.innerWidth > 768) {
                console.log('[NexusMobile] Offline indicator disabled on desktop');
                return;
            }

            // Create offline indicator
            this.offlineIndicator = document.createElement('div');
            this.offlineIndicator.className = 'nexus-offline-indicator';
            this.offlineIndicator.setAttribute('role', 'status');
            this.offlineIndicator.setAttribute('aria-live', 'polite');
            this.offlineIndicator.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                    <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path>
                    <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path>
                    <path d="M10.71 5.05A16 16 0 0 1 22.58 9"></path>
                    <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path>
                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                    <line x1="12" y1="20" x2="12.01" y2="20"></line>
                </svg>
                <span>You're offline</span>
            `;
            document.body.appendChild(this.offlineIndicator);

            // Create online indicator
            this.onlineIndicator = document.createElement('div');
            this.onlineIndicator.className = 'nexus-online-indicator';
            this.onlineIndicator.setAttribute('role', 'status');
            this.onlineIndicator.setAttribute('aria-live', 'polite');
            this.onlineIndicator.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>Back online!</span>
            `;
            document.body.appendChild(this.onlineIndicator);

            // Listen for online/offline events
            window.addEventListener('online', () => this.showOnlineIndicator());
            window.addEventListener('offline', () => this.showOfflineIndicator());

            // On mobile, check initial state after a short delay
            // This avoids false positives during page load while still catching truly offline state
            const self = this;
            setTimeout(function() {
                if (!navigator.onLine) {
                    self.showOfflineIndicator();
                }
            }, 1000);
        },

        showOfflineIndicator: function() {
            document.body.classList.add('is-offline');
            this.offlineIndicator.classList.add('visible');
            this.haptic('warning');
        },

        showOnlineIndicator: function() {
            // Hide offline indicator
            document.body.classList.remove('is-offline');
            this.offlineIndicator.classList.remove('visible');

            // Show online indicator briefly
            this.onlineIndicator.classList.add('visible');
            this.haptic('success');

            // Hide after 3 seconds
            setTimeout(() => {
                this.onlineIndicator.classList.remove('visible');
            }, 3000);
        },


        // ============================================
        // 13. REAL-TIME BADGE UPDATES
        // ============================================

        initBadgeUpdates: function() {
            // Poll for unread counts every 30 seconds
            this.updateBadges();
            setInterval(() => this.updateBadges(), 30000);

            // Also update on visibility change (when user returns to tab)
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.updateBadges();
                }
            });
        },

        updateBadges: async function() {
            try {
                const response = await fetch('/api/notifications/unread-count', {
                    credentials: 'include'
                });

                if (!response.ok) return;

                const data = await response.json();

                // Update messages badge
                const messagesBadge = document.querySelector('.nexus-native-nav-item[href*="messages"] .nexus-native-nav-badge');
                const messagesNav = document.querySelector('.nexus-native-nav-item[href*="messages"]');

                if (messagesNav) {
                    const unreadMessages = data.messages || 0;
                    if (unreadMessages > 0) {
                        if (messagesBadge) {
                            messagesBadge.textContent = unreadMessages > 9 ? '9+' : unreadMessages;
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'nexus-native-nav-badge';
                            badge.textContent = unreadMessages > 9 ? '9+' : unreadMessages;
                            messagesNav.appendChild(badge);
                        }
                    } else if (messagesBadge) {
                        messagesBadge.remove();
                    }
                }

                // Update notification count in document title if needed
                const totalUnread = (data.messages || 0) + (data.notifications || 0);
                if (totalUnread > 0) {
                    document.title = document.title.replace(/^\(\d+\) /, '');
                    document.title = `(${totalUnread}) ${document.title}`;
                } else {
                    document.title = document.title.replace(/^\(\d+\) /, '');
                }

            } catch (e) {
                // Silently fail - user may be offline
            }
        },


        // ============================================
        // 14. INITIALIZATION
        // ============================================

        init: function() {
            // Initialize haptic feedback tracking (must be first)
            this.initHapticTracking();

            // Only run on mobile/touch devices
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

            if (isTouchDevice || window.innerWidth <= 768) {
                // Hide desktop footer on mobile (JS fallback for CSS)
                const siteFooter = document.querySelector('.nexus-site-footer');
                if (siteFooter) {
                    siteFooter.style.display = 'none';
                    siteFooter.style.visibility = 'hidden';
                }

                // Initialize bottom nav
                this.initBottomNav();

                // Initialize pull-to-refresh on feed pages
                // DISABLED: Pull-to-refresh - temporarily disabled for troubleshooting
                // This feature was causing hard page reloads which could lead to logout
                // To re-enable: uncomment the following line
                // this.initGlobalPullToRefresh();
                console.log('[NEXUS] Pull-to-refresh disabled for troubleshooting');

                // Initialize swipe-to-go-back (iOS-style edge gesture)
                // DISABLED: Swipe-to-back - temporarily disabled for troubleshooting
                // This feature may conflict with scroll gestures and cause navigation issues
                // To re-enable: uncomment the following line
                // this.initSwipeBack();
                console.log('[NEXUS] Swipe-to-back disabled for troubleshooting');

                // Initialize long-press context menus
                this.initLongPressMenus();

                // Initialize collapsing header on scroll
                this.initCollapsingHeader();

                // Initialize swipeable items (for list items only, not global)
                this.initSwipeableItems('.nexus-swipe-item');
            }

            // Initialize infinite scroll on marked containers
            this.initGlobalInfiniteScroll();

            // Initialize page transitions (works on all devices)
            this.initPageTransitions();

            // Initialize offline indicator (all devices)
            this.initOfflineIndicator();

            // Initialize badge updates if logged in
            if (document.body.classList.contains('logged-in') || document.querySelector('.nexus-native-nav-item[href*="messages"]')) {
                this.initBadgeUpdates();
            }

            console.log('[NEXUS] Mobile experience initialized');
        }
    };


    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NexusMobile.init());
    } else {
        NexusMobile.init();
    }

})();
