/**
 * CivicOne Native Experience JavaScript
 * Advanced features for 10/10 mobile experience
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    window.CivicOneNative = {

        // Configuration
        config: {
            hapticEnabled: true,
            swipeBackThreshold: 100,
            longPressDelay: 500,
            animationObserverThreshold: 0.1
        },

        // ============================================
        // 1. INITIALIZATION
        // ============================================

        init: function() {
            this.initProgressBar();
            this.initHaptics();
            // DISABLED: Swipe navigation was causing conflicts with scroll and navigation issues
            // this.initSwipeNavigation();
            this.initLongPress();
            this.initRippleEffect();
            this.initScrollAnimations();
            this.initBottomSheet();
            this.initShareButtons();
            this.initFormEnhancements();
            this.initTurboEvents();
            this.initViewTransitions();
            this.initOfflineIndicator();

            console.warn('[CivicOne Native] Advanced features initialized');
        },

        // ============================================
        // 2. PROGRESS BAR (Page Loading)
        // ============================================

        progressBar: null,

        initProgressBar: function() {
            // Create progress bar
            const bar = document.createElement('div');
            bar.className = 'civic-progress-bar';
            bar.innerHTML = '<div class="civic-progress-bar-inner"></div>';
            document.body.appendChild(bar);
            this.progressBar = bar.querySelector('.civic-progress-bar-inner');

            // Listen for Turbo events
            document.addEventListener('turbo:visit', () => this.startProgress());
            document.addEventListener('turbo:load', () => this.completeProgress());

            // Fallback for non-Turbo navigation
            window.addEventListener('beforeunload', () => this.startProgress());
        },

        startProgress: function() {
            if (!this.progressBar) return;
            // eslint-disable-next-line no-restricted-syntax -- dynamic progress width
            this.progressBar.style.width = '0';
            this.progressBar.classList.add('loading');

            // Simulate progress
            let progress = 0;
            this.progressInterval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                // eslint-disable-next-line no-restricted-syntax -- dynamic progress width
                this.progressBar.style.width = progress + '%';
            }, 200);
        },

        completeProgress: function() {
            if (!this.progressBar) return;
            clearInterval(this.progressInterval);
            this.progressBar.classList.remove('loading');
            // eslint-disable-next-line no-restricted-syntax -- dynamic progress width
            this.progressBar.style.width = '100%';

            setTimeout(() => {
                // eslint-disable-next-line no-restricted-syntax -- dynamic progress width
                this.progressBar.style.width = '0';
            }, 300);
        },

        // ============================================
        // 3. HAPTIC FEEDBACK
        // ============================================

        initHaptics: function() {
            if (!this.config.hapticEnabled) return;
            if (!('vibrate' in navigator)) return;

            // Add haptics to buttons
            document.addEventListener('click', (e) => {
                const target = e.target.closest('.civic-btn, .civic-bottom-nav-item, [data-haptic]');
                if (target) {
                    this.haptic(target.dataset.haptic || 'light');
                }
            });

            // Add haptics to toggle switches
            document.addEventListener('change', (e) => {
                if (e.target.type === 'checkbox' || e.target.type === 'radio') {
                    this.haptic('light');
                }
            });
        },

        haptic: function(type = 'light') {
            if (!('vibrate' in navigator)) return;

            const patterns = {
                light: [10],
                medium: [20],
                heavy: [30],
                success: [10, 30, 10],
                error: [50, 30, 50, 30, 50],
                warning: [30, 50, 30],
                selection: [5]
            };

            navigator.vibrate(patterns[type] || patterns.light);
        },

        // ============================================
        // 4. SWIPE NAVIGATION (Back Gesture)
        // ============================================

        initSwipeNavigation: function() {
            // Only on mobile
            if (window.innerWidth > 768) return;
            if (!('ontouchstart' in window)) return;
            if (window.history.length <= 1) return;

            let startX = 0;
            let currentX = 0;
            let swiping = false;

            // Create indicator
            const indicator = document.createElement('div');
            indicator.className = 'civic-swipe-indicator';
            indicator.innerHTML = `
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
            `;
            document.body.appendChild(indicator);

            document.addEventListener('touchstart', (e) => {
                // Only from left edge (within 20px)
                if (e.touches[0].clientX < 20) {
                    startX = e.touches[0].clientX;
                    swiping = true;
                }
            }, { passive: true });

            document.addEventListener('touchmove', (e) => {
                if (!swiping) return;

                currentX = e.touches[0].clientX;
                const diff = currentX - startX;

                if (diff > 10) {
                    const progress = Math.min(diff / this.config.swipeBackThreshold, 1);
                    indicator.classList.add('visible');

                    if (diff >= this.config.swipeBackThreshold) {
                        indicator.classList.add('ready');
                    } else {
                        indicator.classList.remove('ready');
                    }
                }
            }, { passive: true });

            document.addEventListener('touchend', () => {
                if (!swiping) return;

                const diff = currentX - startX;

                if (diff >= this.config.swipeBackThreshold) {
                    this.haptic('medium');
                    window.history.back();
                }

                indicator.classList.remove('visible', 'ready');
                swiping = false;
                startX = 0;
                currentX = 0;
            }, { passive: true });
        },

        // ============================================
        // 5. LONG PRESS ACTIONS
        // ============================================

        initLongPress: function() {
            let pressTimer = null;
            let pressTarget = null;

            document.addEventListener('touchstart', (e) => {
                const target = e.target.closest('[data-longpress], .civic-card');
                if (!target) return;

                pressTarget = target;

                pressTimer = setTimeout(() => {
                    this.haptic('heavy');
                    target.classList.add('long-press');

                    // Dispatch custom event
                    target.dispatchEvent(new CustomEvent('longpress', {
                        bubbles: true,
                        detail: { target }
                    }));

                    // Show context menu if defined
                    const action = target.dataset.longpressAction;
                    if (action === 'share') {
                        this.shareContent(target);
                    } else if (action === 'menu') {
                        this.showContextMenu(target);
                    }

                }, this.config.longPressDelay);
            }, { passive: true });

            document.addEventListener('touchend', () => {
                clearTimeout(pressTimer);
                if (pressTarget) {
                    pressTarget.classList.remove('long-press');
                    pressTarget = null;
                }
            }, { passive: true });

            document.addEventListener('touchmove', () => {
                clearTimeout(pressTimer);
            }, { passive: true });
        },

        // ============================================
        // 6. RIPPLE EFFECT
        // ============================================

        initRippleEffect: function() {
            document.addEventListener('click', (e) => {
                const target = e.target.closest('.civic-btn, [data-ripple]');
                if (!target) return;

                // Remove any existing ripple
                target.classList.remove('ripple');

                // Trigger reflow
                void target.offsetWidth;

                // Add ripple
                target.classList.add('ripple');

                // Remove after animation
                setTimeout(() => {
                    target.classList.remove('ripple');
                }, 600);
            });
        },

        // ============================================
        // 7. SCROLL ANIMATIONS (Intersection Observer)
        // ============================================

        initScrollAnimations: function() {
            // Check for reduced motion preference
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: this.config.animationObserverThreshold,
                rootMargin: '0px 0px -50px 0px'
            });

            // Observe elements
            document.querySelectorAll('.civic-grid, .civic-hero-content, [data-animate]').forEach(el => {
                observer.observe(el);
            });

            // Re-observe after Turbo navigation
            document.addEventListener('turbo:load', () => {
                document.querySelectorAll('.civic-grid:not(.animated), .civic-hero-content:not(.animated), [data-animate]:not(.animated)').forEach(el => {
                    observer.observe(el);
                });
            });
        },

        // ============================================
        // 8. BOTTOM SHEET
        // ============================================

        initBottomSheet: function() {
            // Handle bottom sheet triggers
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-bottom-sheet]');
                if (trigger) {
                    e.preventDefault();
                    const sheetId = trigger.dataset.bottomSheet;
                    this.showBottomSheet(sheetId);
                }
            });
        },

        showBottomSheet: function(contentOrId) {
            this.haptic('medium');

            // Create backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'civic-bottom-sheet-backdrop';
            document.body.appendChild(backdrop);

            // Create sheet
            const sheet = document.createElement('div');
            sheet.className = 'civic-bottom-sheet';
            sheet.setAttribute('role', 'dialog');
            sheet.setAttribute('aria-modal', 'true');

            // Get content
            let content = '';
            if (typeof contentOrId === 'string' && document.getElementById(contentOrId)) {
                content = document.getElementById(contentOrId).innerHTML;
            } else if (typeof contentOrId === 'object') {
                content = contentOrId.content || '';
            }

            sheet.innerHTML = `
                <div class="civic-bottom-sheet-handle"></div>
                <div class="civic-bottom-sheet-content">
                    ${content}
                </div>
            `;

            document.body.appendChild(sheet);

            // Animate in
            requestAnimationFrame(() => {
                backdrop.classList.add('active');
                sheet.classList.add('active');
            });

            // Handle close
            const close = () => {
                backdrop.classList.remove('active');
                sheet.classList.remove('active');
                setTimeout(() => {
                    backdrop.remove();
                    sheet.remove();
                }, 300);
            };

            backdrop.addEventListener('click', close);

            // Swipe down to close
            let startY = 0;
            let currentY = 0;

            sheet.addEventListener('touchstart', (e) => {
                startY = e.touches[0].clientY;
            }, { passive: true });

            sheet.addEventListener('touchmove', (e) => {
                currentY = e.touches[0].clientY;
                const diff = currentY - startY;

                if (diff > 0) {
                    sheet.style.transform = `translateY(${diff}px)`;
                }
            }, { passive: true });

            sheet.addEventListener('touchend', () => {
                const diff = currentY - startY;
                if (diff > 100) {
                    close();
                } else {
                    sheet.style.transform = '';
                }
            }, { passive: true });

            return { close };
        },

        // ============================================
        // 9. NATIVE SHARE
        // ============================================

        initShareButtons: function() {
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-share]');
                if (!btn) return;

                e.preventDefault();
                this.shareContent(btn);
            });
        },

        async shareContent(element) {
            this.haptic('medium');

            const data = {
                title: element.dataset.shareTitle || document.title,
                text: element.dataset.shareText || '',
                url: element.dataset.shareUrl || window.location.href
            };

            // Try native share first
            if (navigator.share) {
                try {
                    await navigator.share(data);
                    return;
                } catch (err) {
                    if (err.name === 'AbortError') return;
                }
            }

            // Fallback to NexusNative share sheet if available
            if (window.NexusNative && NexusNative.showFallbackShare) {
                NexusNative.showFallbackShare(data);
            } else {
                // Simple fallback: copy to clipboard
                await navigator.clipboard.writeText(data.url);
                if (window.CivicOneMobile) {
                    CivicOneMobile.showToast('Link copied to clipboard!', 'success');
                }
            }
        },

        // ============================================
        // 10. FORM ENHANCEMENTS
        // ============================================

        initFormEnhancements: function() {
            // Real-time validation feedback
            document.addEventListener('input', (e) => {
                const input = e.target;
                if (!input.matches('input, textarea, select')) return;

                // Remove previous states
                input.classList.remove('valid', 'invalid', 'shake');

                // Check validity
                if (input.value && input.checkValidity) {
                    if (input.checkValidity()) {
                        input.classList.add('valid');
                    } else if (input.value.length > 0) {
                        input.classList.add('invalid');
                    }
                }
            });

            // Shake on invalid submit
            document.addEventListener('invalid', (e) => {
                const input = e.target;
                input.classList.add('invalid', 'shake');
                this.haptic('error');

                setTimeout(() => {
                    input.classList.remove('shake');
                }, 500);
            }, true);

            // Success feedback on valid form submit
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (form.checkValidity && form.checkValidity()) {
                    this.haptic('success');
                }
            });
        },

        // ============================================
        // 11. TURBO INTEGRATION
        // ============================================

        initTurboEvents: function() {
            // Re-initialize on Turbo page loads
            document.addEventListener('turbo:load', () => {
                // Re-run scroll animations
                this.initScrollAnimations();

                // Haptic on navigation
                this.haptic('selection');
            });

            // Show loading state
            document.addEventListener('turbo:visit', () => {
                document.body.classList.add('turbo-loading');
            });

            document.addEventListener('turbo:load', () => {
                document.body.classList.remove('turbo-loading');
            });
        },

        // ============================================
        // 12. CONTEXT MENU
        // ============================================

        showContextMenu: function(target) {
            const rect = target.getBoundingClientRect();

            const menu = document.createElement('div');
            menu.className = 'civic-context-menu';
            menu.setAttribute('role', 'menu');
            menu.innerHTML = `
                <button role="menuitem" data-action="share">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
                    </svg>
                    Share
                </button>
                <button role="menuitem" data-action="copy">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                    Copy Link
                </button>
            `;

            // Position menu
            menu.style.cssText = `
                position: fixed;
                top: ${rect.top + rect.height / 2}px;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 10002;
            `;

            document.body.appendChild(menu);

            // Handle actions
            menu.addEventListener('click', async (e) => {
                const action = e.target.closest('[data-action]')?.dataset.action;

                if (action === 'share') {
                    this.shareContent(target);
                } else if (action === 'copy') {
                    const url = target.querySelector('a')?.href || window.location.href;
                    await navigator.clipboard.writeText(url);
                    if (window.CivicOneMobile) {
                        CivicOneMobile.showToast('Link copied!', 'success');
                    }
                }

                menu.remove();
            });

            // Close on outside click
            setTimeout(() => {
                document.addEventListener('click', function closeMenu(e) {
                    if (!menu.contains(e.target)) {
                        menu.remove();
                        document.removeEventListener('click', closeMenu);
                    }
                });
            }, 100);
        },

        // ============================================
        // 13. VIEW TRANSITIONS API
        // ============================================

        initViewTransitions: function() {
            // Check for View Transitions support
            if (!document.startViewTransition) {
                console.warn('[CivicOne Native] View Transitions API not supported');
                return;
            }

            // Track navigation direction for back animation
            let navigationDirection = 'forward';

            // Intercept Turbo navigation for View Transitions
            document.addEventListener('turbo:before-visit', (e) => {
                // Determine direction based on history
                if (performance.navigation && performance.navigation.type === 2) {
                    navigationDirection = 'back';
                } else {
                    navigationDirection = 'forward';
                }
            });

            // Handle back button
            window.addEventListener('popstate', () => {
                navigationDirection = 'back';
            });

            // Apply transition class based on direction
            document.addEventListener('turbo:before-render', (e) => {
                if (navigationDirection === 'back') {
                    document.documentElement.classList.add('back-nav');
                } else {
                    document.documentElement.classList.remove('back-nav');
                }
            });

            document.addEventListener('turbo:load', () => {
                // Reset after transition
                setTimeout(() => {
                    document.documentElement.classList.remove('back-nav');
                    navigationDirection = 'forward';
                }, 300);
            });

            console.warn('[CivicOne Native] View Transitions enabled');
        },

        // ============================================
        // 14. OFFLINE INDICATOR
        // ============================================

        offlineIndicator: null,
        onlineIndicator: null,

        initOfflineIndicator: function() {
            // Only enable offline indicator on mobile devices
            // Desktop browsers have unreliable navigator.onLine detection
            if (window.innerWidth > 768) {
                console.warn('[CivicOne Native] Offline indicator disabled on desktop');
                return;
            }

            // Create offline indicator with inline fallback styles
            this.offlineIndicator = document.createElement('div');
            this.offlineIndicator.className = 'civic-offline-indicator';
            this.offlineIndicator.setAttribute('role', 'status');
            this.offlineIndicator.setAttribute('aria-live', 'polite');
            // Inline styles as fallback if CSS doesn't load
            this.offlineIndicator.style.cssText = 'position:fixed;top:0;left:0;right:0;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;text-align:center;padding:8px 16px;font-size:13px;font-weight:600;z-index:10000;transform:translateY(-100%);transition:transform 0.3s ease;display:flex;align-items:center;justify-content:center;gap:8px;';
            this.offlineIndicator.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:16px;height:16px;flex-shrink:0;">
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

            // Create online indicator with inline fallback styles
            this.onlineIndicator = document.createElement('div');
            this.onlineIndicator.className = 'civic-online-indicator';
            this.onlineIndicator.setAttribute('role', 'status');
            this.onlineIndicator.setAttribute('aria-live', 'polite');
            // Inline styles as fallback if CSS doesn't load
            this.onlineIndicator.style.cssText = 'position:fixed;top:0;left:0;right:0;background:linear-gradient(135deg,#10b981,#059669);color:white;text-align:center;padding:8px 16px;font-size:13px;font-weight:600;z-index:10000;transform:translateY(-100%);transition:transform 0.3s ease;display:flex;align-items:center;justify-content:center;gap:8px;';
            this.onlineIndicator.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:16px;height:16px;flex-shrink:0;">
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
            // Use inline style for visibility (fallback if CSS not loaded)
            this.offlineIndicator.style.transform = 'translateY(0)';
            this.haptic('warning');

            // Announce to screen readers
            this.announceToScreenReader('You are now offline. Some features may be unavailable.');
        },

        hideOfflineIndicator: function() {
            document.body.classList.remove('is-offline');
            this.offlineIndicator.classList.remove('visible');
            this.offlineIndicator.style.transform = 'translateY(-100%)';
        },

        showOnlineIndicator: function() {
            // Hide offline indicator
            this.hideOfflineIndicator();

            // Show online indicator briefly
            this.onlineIndicator.classList.add('visible');
            this.onlineIndicator.style.transform = 'translateY(0)';
            this.haptic('success');

            // Announce to screen readers
            this.announceToScreenReader('You are back online.');

            // Hide after 3 seconds
            setTimeout(() => {
                this.onlineIndicator.classList.remove('visible');
                this.onlineIndicator.style.transform = 'translateY(-100%)';
            }, 3000);
        },

        announceToScreenReader: function(message) {
            let announcer = document.getElementById('civic-sr-announcer');
            if (!announcer) {
                announcer = document.createElement('div');
                announcer.id = 'civic-sr-announcer';
                announcer.setAttribute('role', 'status');
                announcer.setAttribute('aria-live', 'polite');
                announcer.setAttribute('aria-atomic', 'true');
                announcer.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
                document.body.appendChild(announcer);
            }

            announcer.textContent = '';
            requestAnimationFrame(() => {
                announcer.textContent = message;
            });
        }
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => CivicOneNative.init());
    } else {
        CivicOneNative.init();
    }

})();
