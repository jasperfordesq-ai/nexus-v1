/**
 * Visual Polish Helpers - Easy integration of loading skeletons and micro-interactions
 * Created: 2026-01-19
 * Usage: Include after DOM is ready
 */

(function() {
    'use strict';

    // Namespace for polish helpers
    window.NexusPolish = window.NexusPolish || {};

    /**
     * Auto-apply micro-interactions to elements
     */
    function applyMicroInteractions() {
        // Add ripple effect to primary buttons
        document.querySelectorAll('button.primary, .btn-primary, .match-action-btn.primary, .org-submit-btn').forEach(btn => {
            if (!btn.classList.contains('ripple')) {
                btn.classList.add('ripple');
            }
        });

        // Add card-lift to match cards
        document.querySelectorAll('.match-card, .member-card, .org-request-item').forEach(card => {
            if (!card.classList.contains('card-lift')) {
                card.classList.add('card-lift');
            }
        });

        // Add btn-press to all buttons
        document.querySelectorAll('button, .btn, [role="button"]').forEach(btn => {
            if (!btn.classList.contains('btn-press')) {
                btn.classList.add('btn-press');
            }
        });

        // Add smooth-color to interactive elements
        document.querySelectorAll('a, button, .clickable').forEach(el => {
            if (!el.classList.contains('smooth-color')) {
                el.classList.add('smooth-color');
            }
        });
    }

    /**
     * Show success animation on element
     * @param {Element} element - The element to animate
     * @param {string} message - Optional success message
     */
    function showSuccess(element, message) {
        element.classList.add('success-pulse');

        if (message) {
            const icon = element.querySelector('i') || element;
            const originalContent = icon.textContent;
            icon.textContent = '✓ ' + message;

            setTimeout(() => {
                icon.textContent = originalContent;
                element.classList.remove('success-pulse');
            }, 2000);
        } else {
            setTimeout(() => {
                element.classList.remove('success-pulse');
            }, 600);
        }
    }

    /**
     * Show confetti celebration
     * @param {number} count - Number of confetti pieces (default: 50)
     */
    function showConfetti(count = 50) {
        // Check if confetti container exists
        let container = document.querySelector('.confetti-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'confetti-container';
            document.body.appendChild(container);
        }

        const colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#f97316', '#14b8a6', '#a855f7'];

        for (let i = 0; i < count; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti-piece';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = (Math.random() * 0.5) + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            container.appendChild(confetti);
        }

        // Clean up after animation
        setTimeout(() => {
            container.innerHTML = '';
        }, 4000);
    }

    /**
     * Show shake animation on element (for errors)
     * @param {Element} element - The element to shake
     */
    function showError(element) {
        element.classList.add('shake');
        setTimeout(() => {
            element.classList.remove('shake');
        }, 500);
    }

    /**
     * Create a loading skeleton for an element
     * @param {Element} element - The element to show skeleton for
     * @param {string} type - Type of skeleton: 'text', 'avatar', 'card', 'feed'
     */
    function showSkeleton(element, type = 'text') {
        const skeletonMap = {
            'text': '<div class="skeleton skeleton-text"></div>',
            'avatar': '<div class="skeleton skeleton-avatar"></div>',
            'card': '<div class="skeleton skeleton-card"></div>',
            'button': '<div class="skeleton skeleton-button"></div>',
            'feed': `
                <div class="feed-skeleton">
                    <div class="feed-skeleton-header">
                        <div class="skeleton skeleton-avatar"></div>
                        <div style="flex:1">
                            <div class="skeleton skeleton-text" style="width:60%"></div>
                            <div class="skeleton skeleton-text small" style="width:40%"></div>
                        </div>
                    </div>
                    <div class="feed-skeleton-content">
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text" style="width:80%"></div>
                    </div>
                </div>
            `,
            'match': `
                <div class="match-skeleton">
                    <div class="match-skeleton-header">
                        <div class="skeleton skeleton-avatar"></div>
                        <div style="flex:1">
                            <div class="skeleton skeleton-text" style="width:70%"></div>
                            <div class="skeleton skeleton-text small" style="width:50%"></div>
                        </div>
                    </div>
                </div>
            `
        };

        element.innerHTML = skeletonMap[type] || skeletonMap['text'];
        element.setAttribute('data-skeleton-shown', 'true');
    }

    /**
     * Hide skeleton and show content with fade-in
     * @param {Element} element - The element with skeleton
     * @param {string} content - The actual content to show
     */
    function hideSkeleton(element, content) {
        if (element.getAttribute('data-skeleton-shown') === 'true') {
            element.innerHTML = content;
            element.classList.add('skeleton-loaded');
            element.removeAttribute('data-skeleton-shown');
        }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to show
     * @param {string} type - Type: 'success', 'error', 'info'
     */
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} notification-slide`;
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'success' ? 'linear-gradient(135deg, #10b981, #059669)' :
                        type === 'error' ? 'linear-gradient(135deg, #ef4444, #dc2626)' :
                        'linear-gradient(135deg, #6366f1, #4f46e5)'};
            color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 10000;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        `;

        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
        toast.innerHTML = `<span style="font-size: 1.2rem;">${icon}</span> ${message}`;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'toastSlideOut 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Add heartbeat animation to element
     * @param {Element} element - The element to animate
     */
    function heartbeat(element) {
        element.classList.add('heart-beat');
        setTimeout(() => {
            element.classList.remove('heart-beat');
        }, 800);
    }

    /**
     * Add wiggle animation to element (for attention)
     * @param {Element} element - The element to wiggle
     */
    function wiggle(element) {
        element.classList.add('icon-wiggle');
        setTimeout(() => {
            element.classList.remove('icon-wiggle');
        }, 500);
    }

    // Export functions to global namespace
    window.NexusPolish = {
        applyMicroInteractions,
        showSuccess,
        showConfetti,
        showError,
        showSkeleton,
        hideSkeleton,
        showToast,
        heartbeat,
        wiggle
    };

    // Auto-apply micro-interactions on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyMicroInteractions);
    } else {
        applyMicroInteractions();
    }

    // Re-apply on AJAX content loads
    const observer = new MutationObserver((mutations) => {
        let shouldReapply = false;
        mutations.forEach(mutation => {
            if (mutation.addedNodes.length > 0) {
                shouldReapply = true;
            }
        });
        if (shouldReapply) {
            applyMicroInteractions();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    console.log('✨ Nexus Polish Helpers loaded');
})();
