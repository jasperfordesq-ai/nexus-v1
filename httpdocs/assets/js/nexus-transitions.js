/**
 * Nexus Page Transitions & Visual Enhancements
 * Smooth animations and micro-interactions
 */

(function() {
    'use strict';

    // ============================================
    // 1. SMOOTH PAGE TRANSITIONS
    // ============================================

    /**
     * Add fade transition between pages
     */
    function initPageTransitions() {
        // Check if page transitions are disabled
        if (window.NEXUS_PAGE_TRANSITIONS_DISABLED) {
            console.warn('⏭️  Page transitions disabled (prevents glitches)');
            return;
        }

        // Fade out on link click
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');

            // Skip if:
            // - Not a link
            // - External link
            // - Has download attribute
            // - Opens in new tab
            // - Has special classes
            if (!link ||
                link.hostname !== window.location.hostname ||
                link.hasAttribute('download') ||
                link.target === '_blank' ||
                link.classList.contains('no-transition') ||
                link.href.includes('#')) {
                return;
            }

            e.preventDefault();

            // Add transition class
            document.body.classList.add('page-transitioning');

            // Navigate after animation
            setTimeout(() => {
                window.location.href = link.href;
            }, 300);
        });

        // Fade in on page load
        window.addEventListener('pageshow', function() {
            document.body.classList.remove('page-transitioning');
            document.body.classList.add('page-loaded');
        });
    }

    // ============================================
    // 2. IMAGE LAZY LOADING ENHANCEMENTS
    // ============================================

    /**
     * Add fade-in animation to lazy loaded images
     */
    function enhanceLazyLoading() {
        const images = document.querySelectorAll('img[loading="lazy"]');

        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;

                    // Add loaded class when image loads
                    img.addEventListener('load', function() {
                        img.classList.add('loaded');
                    });

                    // If already loaded
                    if (img.complete) {
                        img.classList.add('loaded');
                    }

                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // ============================================
    // 3. SMOOTH SCROLL BEHAVIOR
    // ============================================

    /**
     * Add smooth scrolling to anchor links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');

                // Skip if it's just "#"
                if (targetId === '#' || targetId === '#!') return;

                const target = document.querySelector(targetId);

                if (target) {
                    e.preventDefault();

                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                    // Update URL without jumping
                    if (history.pushState) {
                        history.pushState(null, null, targetId);
                    }

                    // Focus target for accessibility
                    target.focus({
                        preventScroll: true
                    });
                }
            });
        });
    }

    // ============================================
    // 4. ENHANCED TOAST NOTIFICATIONS
    // ============================================

    /**
     * Show toast with smooth animations
     */
    window.showToast = function(message, type = 'success', duration = 3000) {
        const existingToast = document.getElementById('nexus-toast');

        // Remove existing toast
        if (existingToast) {
            existingToast.classList.add('hiding');
            setTimeout(() => existingToast.remove(), 300);
        }

        // Create new toast
        const toast = document.createElement('div');
        toast.id = 'nexus-toast';
        toast.className = `nexus-toast toast-${type}`;
        toast.textContent = message;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');

        document.body.appendChild(toast);

        // Auto-hide
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, duration);

        // Click to dismiss
        toast.addEventListener('click', function() {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        });
    };

    // ============================================
    // 5. ENHANCED MODAL ANIMATIONS
    // ============================================

    /**
     * Add smooth open/close animations to modals
     */
    function enhanceModals() {
        // Find all modal triggers
        document.querySelectorAll('[data-modal-target]').forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-modal-target');
                const modal = document.getElementById(targetId);

                if (modal) {
                    openModal(modal);
                }
            });
        });

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay, [data-modal-close]').forEach(closer => {
            closer.addEventListener('click', function(e) {
                if (e.target === this) {
                    const modal = this.closest('.modal, [role="dialog"]');
                    if (modal) {
                        closeModal(modal);
                    }
                }
            });
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal.active, [role="dialog"].active');
                if (modal) {
                    closeModal(modal);
                }
            }
        });
    }

    function openModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Focus first focusable element
        const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            setTimeout(() => firstFocusable.focus(), 100);
        }

        // Trap focus
        trapFocus(modal);
    }

    function closeModal(modal) {
        modal.classList.add('closing');

        setTimeout(() => {
            modal.classList.remove('active', 'closing');
            document.body.style.overflow = '';
        }, 200);
    }

    function trapFocus(element) {
        const focusableElements = element.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];

        element.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    lastFocusable.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    firstFocusable.focus();
                    e.preventDefault();
                }
            }
        });
    }

    // ============================================
    // 6. CARD HOVER 3D TILT EFFECT
    // ============================================

    /**
     * Add subtle 3D tilt to cards on mouse move
     */
    function initCardTilt() {
        const cards = document.querySelectorAll('.nexus-card, .glass-card, .post-card');

        cards.forEach(card => {
            card.addEventListener('mousemove', function(e) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;

                card.style.transform = `
                    perspective(1000px)
                    rotateX(${rotateX}deg)
                    rotateY(${rotateY}deg)
                    scale3d(1.01, 1.01, 1.01)
                `;
            });

            card.addEventListener('mouseleave', function() {
                card.style.transform = '';
            });
        });
    }

    // ============================================
    // 7. RIPPLE EFFECT ON BUTTONS
    // ============================================

    /**
     * Create ripple effect on button clicks
     */
    function initRippleEffect() {
        document.addEventListener('click', function(e) {
            const button = e.target.closest('button, .btn, .glass-btn');

            if (!button || button.classList.contains('no-ripple')) return;

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

            setTimeout(() => ripple.remove(), 600);
        }, true);
    }

    // ============================================
    // 8. PARALLAX SCROLL EFFECT
    // ============================================

    /**
     * Add subtle parallax to hero sections
     */
    function initParallax() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');

        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;

            parallaxElements.forEach(element => {
                const speed = element.getAttribute('data-parallax') || 0.5;
                const yPos = -(scrolled * speed);

                element.style.transform = `translateY(${yPos}px)`;
            });
        }, {
            passive: true
        });
    }

    // ============================================
    // 9. SCROLL REVEAL ANIMATIONS
    // ============================================

    /**
     * Reveal elements as they scroll into view
     */
    function initScrollReveal() {
        const revealElements = document.querySelectorAll('[data-reveal]');

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        revealElements.forEach(el => {
            el.classList.add('reveal-hidden');
            revealObserver.observe(el);
        });
    }

    // ============================================
    // 10. INITIALIZATION
    // ============================================

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Check if loading fix is already handling these
        if (window.NEXUS_PAGE_TRANSITIONS_DISABLED) {
            console.warn('⏭️  Using optimized loading system');
            // Don't duplicate functionality - loading-fix.js handles it
            return;
        }

        // Check for reduced motion preference
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!prefersReducedMotion) {
            initPageTransitions();
            initCardTilt();
            initParallax();
            initScrollReveal();
        }

        // Always initialize these (they have reduced-motion fallbacks)
        enhanceLazyLoading();
        initSmoothScroll();
        enhanceModals();
        initRippleEffect();

        console.warn('✨ Nexus visual enhancements initialized');
    }

    // Add CSS for transitions
    const style = document.createElement('style');
    style.textContent = `
        body.page-transitioning {
            opacity: 0;
            transition: opacity 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        body.page-loaded {
            animation: fadeIn 0.5s cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        .ripple-effect {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            pointer-events: none;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
        }

        .reveal-hidden {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s cubic-bezier(0.4, 0.0, 0.2, 1),
                        transform 0.6s cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        .revealed {
            opacity: 1;
            transform: translateY(0);
        }

        #nexus-toast {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 16px 24px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            cursor: pointer;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            max-width: 350px;
        }

        #nexus-toast.toast-success {
            border-color: rgba(34, 197, 94, 0.5);
            box-shadow: 0 8px 32px rgba(34, 197, 94, 0.2);
        }

        #nexus-toast.toast-error {
            border-color: rgba(239, 68, 68, 0.5);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.2);
        }

        #nexus-toast.toast-warning {
            border-color: rgba(245, 158, 11, 0.5);
            box-shadow: 0 8px 32px rgba(245, 158, 11, 0.2);
        }

        @media (max-width: 768px) {
            #nexus-toast {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }
    `;
    document.head.appendChild(style);

})();
