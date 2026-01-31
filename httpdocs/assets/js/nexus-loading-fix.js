/**
 * Nexus Loading Fix
 * Prevents page loading glitches and FOUC
 */

(function() {
    'use strict';

    // ============================================
    // 1. PREVENT FOUC - CRITICAL
    // ============================================

    // Mark page as loading
    document.documentElement.classList.add('loading');

    // Wait for fonts to load before showing content
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function() {
            document.documentElement.classList.add('fonts-loaded');
            document.documentElement.classList.remove('loading');
        });
    } else {
        // Fallback: Show content after 100ms
        setTimeout(function() {
            document.documentElement.classList.add('fonts-loaded');
            document.documentElement.classList.remove('loading');
        }, 100);
    }

    // ============================================
    // 2. DISABLE PROBLEMATIC PAGE TRANSITIONS
    // ============================================

    // Remove page transition functionality entirely
    // It's causing too many glitches
    window.NEXUS_PAGE_TRANSITIONS_DISABLED = true;

    // ============================================
    // 3. SMOOTH IMAGE LOADING
    // ============================================

    function enhanceImageLoading() {
        const images = document.querySelectorAll('img[loading="lazy"]');

        images.forEach(function(img) {
            if (img.complete) {
                img.classList.add('loaded');
            } else {
                img.addEventListener('load', function() {
                    img.classList.add('loaded');
                });
            }
        });
    }

    // ============================================
    // 4. SKELETON SCREEN FADE OUT
    // ============================================

    function hideSkeletonScreens() {
        const skeletons = document.querySelectorAll('.skeleton-feed-container, [class*="skeleton"]');

        skeletons.forEach(function(skeleton) {
            // Check if actual content is loaded
            const parent = skeleton.parentElement;
            const hasContent = parent && parent.querySelectorAll('.feed-item, .post-card').length > 0;

            if (hasContent) {
                // Fade out skeleton
                skeleton.classList.add('hidden');

                // Remove from DOM after animation
                setTimeout(function() {
                    skeleton.classList.add('removed');
                    skeleton.remove();
                }, 300);
            }
        });

        // Fallback: Remove skeletons after 3 seconds
        setTimeout(function() {
            skeletons.forEach(function(skeleton) {
                if (skeleton.parentElement) {
                    skeleton.remove();
                }
            });
        }, 3000);
    }

    // ============================================
    // 5. FEED ANIMATION - ONE TIME ONLY
    // ============================================

    function initFeedAnimations() {
        // Only animate on first load
        if (sessionStorage.getItem('feed_animated')) {
            document.querySelectorAll('.feed-item, .post-card').forEach(function(item) {
                item.classList.add('js-opacity-100');
                // eslint-disable-next-line no-restricted-syntax -- disable animation for already-loaded state
                item.style.animation = 'none';
            });
            document.body.classList.add('feed-loaded');
            return;
        }

        // Mark as animated
        sessionStorage.setItem('feed_animated', 'true');
    }

    // ============================================
    // 6. PREVENT HEADER SCROLL GLITCH
    // ============================================

    function initHeaderBehavior() {
        const navbar = document.querySelector('.nexus-navbar');
        if (!navbar) return;

        let lastScroll = 0;
        let ticking = false;

        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    const currentScroll = window.pageYOffset;

                    // Add shadow when scrolled
                    if (currentScroll > 50) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }

                    // Don't hide header on initial load
                    if (!document.documentElement.classList.contains('loading')) {
                        // Hide on scroll down (optional - can be disabled)
                        if (currentScroll > lastScroll && currentScroll > 100) {
                            // navbar.classList.add('hide-on-scroll');
                        } else {
                            navbar.classList.remove('hide-on-scroll');
                        }
                    }

                    lastScroll = currentScroll;
                    ticking = false;
                });

                ticking = true;
            }
        }, { passive: true });
    }

    // ============================================
    // 7. SMOOTH MODAL BEHAVIOR
    // ============================================

    function enhanceModals() {
        // Find all modal triggers
        document.querySelectorAll('[data-modal-target]').forEach(function(trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-modal-target');
                const modal = document.getElementById(targetId);

                if (modal) {
                    openModal(modal);
                }
            });
        });

        // Close modal handlers
        document.querySelectorAll('.modal-overlay, [data-modal-close]').forEach(function(closer) {
            closer.addEventListener('click', function(e) {
                if (e.target === this) {
                    const modal = this.closest('.modal, [role="dialog"]');
                    if (modal) {
                        closeModal(modal);
                    }
                }
            });
        });

        // Close on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal.active');
                if (modal) {
                    closeModal(modal);
                }
            }
        });
    }

    function openModal(modal) {
        modal.classList.add('active');
        document.body.classList.add('js-overflow-hidden');
    }

    function closeModal(modal) {
        modal.classList.add('closing');
        setTimeout(function() {
            modal.classList.remove('active', 'closing');
            document.body.classList.remove('js-overflow-hidden');
        }, 200);
    }

    // ============================================
    // 8. RIPPLE EFFECT (OPTIMIZED)
    // ============================================

    function initRippleEffect() {
        let rippleCount = 0;
        const MAX_RIPPLES = 5;

        document.addEventListener('click', function(e) {
            const button = e.target.closest('button, .btn, .btn--glass');
            if (!button || button.classList.contains('no-ripple')) return;

            // Limit concurrent ripples
            if (rippleCount >= MAX_RIPPLES) return;

            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            // eslint-disable-next-line no-restricted-syntax -- dynamic ripple size/position
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple-effect');

            button.appendChild(ripple);
            rippleCount++;

            setTimeout(function() {
                ripple.remove();
                rippleCount--;
            }, 600);
        }, true);
    }

    // ============================================
    // 9. SMOOTH SCROLL (WITHOUT BREAKING LINKS)
    // ============================================

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#' || targetId === '#!') return;

                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();

                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                    // Update URL
                    if (history.pushState) {
                        history.pushState(null, null, targetId);
                    }
                }
            });
        });
    }

    // ============================================
    // 10. PREVENT CARD TILT JANK
    // ============================================

    function initCardTilt() {
        // Check for reduced motion preference
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const cards = document.querySelectorAll('.nexus-card, .glass-card, .post-card');
        let tiltCount = 0;
        const MAX_TILTS = 3; // Limit concurrent tilts

        cards.forEach(function(card) {
            card.addEventListener('mouseenter', function() {
                if (tiltCount < MAX_TILTS) {
                    this.classList.add('tilt-active');
                }
            });

            card.addEventListener('mousemove', function(e) {
                if (tiltCount >= MAX_TILTS) return;
                if (!this.classList.contains('tilt-active')) return;

                tiltCount++;

                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;

                this.style.transform = `
                    perspective(1000px)
                    rotateX(${rotateX}deg)
                    rotateY(${rotateY}deg)
                    scale3d(1.01, 1.01, 1.01)
                `;

                setTimeout(function() {
                    tiltCount--;
                }, 50);
            });

            card.addEventListener('mouseleave', function() {
                this.classList.remove('tilt-active');
                this.style.transform = '';
            });
        });
    }

    // ============================================
    // 11. INITIALIZATION
    // ============================================

    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    }

    function initAll() {
        // Mark page as loaded
        document.documentElement.classList.add('loaded');
        document.documentElement.classList.remove('loading');

        // Wait a tick for instant-load.js to show content first
        setTimeout(function() {
            // Initialize features
            enhanceImageLoading();
            hideSkeletonScreens();
            initFeedAnimations();
            initHeaderBehavior();
            enhanceModals();
            initRippleEffect();
            initSmoothScroll();

            // Only init card tilt if user doesn't prefer reduced motion
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                // Delay card tilt to prevent initial jank
                setTimeout(function() {
                    initCardTilt();
                }, 200);
            }

            console.warn('✅ Nexus loading enhancements initialized');
        }, 100);
    }

    // Start initialization
    init();

    // ============================================
    // 12. GLOBAL TOAST FUNCTION (FIXED)
    // ============================================

    window.showToast = function(message, type, duration) {
        type = type || 'success';
        duration = duration || 3000;

        const existingToast = document.getElementById('nexus-toast');
        if (existingToast) {
            existingToast.classList.add('hiding');
            setTimeout(function() {
                existingToast.remove();
            }, 300);
        }

        const toast = document.createElement('div');
        toast.id = 'nexus-toast';
        toast.className = 'nexus-toast toast-' + type;
        toast.textContent = message;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');

        document.body.appendChild(toast);

        // Auto-hide
        setTimeout(function() {
            toast.classList.add('hiding');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, duration);

        // Click to dismiss
        toast.addEventListener('click', function() {
            toast.classList.add('hiding');
            setTimeout(function() {
                toast.remove();
            }, 300);
        });
    };

    // ============================================
    // 13. PAGE VISIBILITY - PAUSE ANIMATIONS
    // ============================================

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden - pause heavy animations
            document.body.classList.add('page-hidden');
        } else {
            // Page is visible - resume animations
            document.body.classList.remove('page-hidden');
        }
    });

})();
