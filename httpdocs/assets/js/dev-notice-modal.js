/**
 * Development Notice Modal
 * Shows a one-time notice to users about the site being in preview mode
 */

(function() {
    'use strict';

    const STORAGE_KEY = 'dev_notice_dismissed';
    const STORAGE_VERSION = '2.1'; // Increment to show again to all users

    function hasSeenNotice() {
        try {
            // Use localStorage for persistence across app restarts (mobile apps clear sessionStorage)
            const dismissed = localStorage.getItem(STORAGE_KEY);
            return dismissed === STORAGE_VERSION;
        } catch (e) {
            // localStorage not available, show notice
            return false;
        }
    }

    function markNoticeSeen() {
        try {
            // Use localStorage for persistence across app restarts (mobile apps clear sessionStorage)
            localStorage.setItem(STORAGE_KEY, STORAGE_VERSION);
        } catch (e) {
            // localStorage not available, ignore
            console.warn('Could not save dev notice preference');
        }
    }

    function createModal() {
        const overlay = document.createElement('div');
        overlay.className = 'dev-notice-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'dev-notice-title');

        overlay.innerHTML = `
            <div class="dev-notice-modal">
                <div class="dev-notice-header">
                    <div class="dev-notice-icon">🚧</div>
                    <h2 id="dev-notice-title">Preview Mode</h2>
                </div>
                <div class="dev-notice-body">
                    <p><strong>Welcome!</strong> This site is still under active development and you're viewing it in preview mode.</p>

                    <div class="dev-notice-highlight">
                        <strong>Currently Working On:</strong> We're actively improving the mobile experience. If you're on a phone or tablet, you may notice some layout changes and improvements rolling out.
                    </div>

                    <p>Your feedback is invaluable! If you notice any issues, please use the <strong>bug report link</strong> at the bottom of the page. It helps us track and fix problems much faster.</p>

                    <p>Thank you for your patience and support as we continue improving the platform!</p>
                </div>
                <div class="dev-notice-footer">
                    <button class="dev-notice-btn dev-notice-btn-primary" id="dev-notice-continue">
                        Continue to Site
                    </button>
                    <button class="dev-notice-btn dev-notice-btn-secondary" id="dev-notice-report">
                        View Bug Report Link
                    </button>
                </div>
            </div>
        `;

        return overlay;
    }

    function scrollToBugReport() {
        // Try to find the bug report link in the footer
        const footer = document.querySelector('footer');
        if (footer) {
            footer.scrollIntoView({ behavior: 'smooth', block: 'end' });

            // Try to highlight the bug report link
            setTimeout(() => {
                const bugLink = footer.querySelector('a[href*="bug"], a[href*="report"], a[href*="feedback"]');
                if (bugLink) {
                    bugLink.style.outline = '3px solid var(--color-warning)';
                    bugLink.style.outlineOffset = '4px';
                    setTimeout(() => {
                        bugLink.style.outline = '';
                        bugLink.style.outlineOffset = '';
                    }, 3000);
                }
            }, 500);
        }
    }

    function closeModal(overlay) {
        overlay.style.animation = 'fadeOut 0.2s ease-out';
        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
        }, 200);
        markNoticeSeen();
    }

    function showDevNotice() {
        // Check if user has already seen this version of the notice
        if (hasSeenNotice()) {
            return;
        }

        // Wait for DOM to be fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showDevNotice);
            return;
        }

        // Create and show modal
        const overlay = createModal();
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        // Add fadeOut animation to CSS if needed
        if (!document.querySelector('#dev-notice-fadeout-style')) {
            const style = document.createElement('style');
            style.id = 'dev-notice-fadeout-style';
            style.textContent = `
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        // Event listeners
        const continueBtn = overlay.querySelector('#dev-notice-continue');
        const reportBtn = overlay.querySelector('#dev-notice-report');

        continueBtn.addEventListener('click', () => {
            closeModal(overlay);
        });

        reportBtn.addEventListener('click', () => {
            closeModal(overlay);
            setTimeout(() => scrollToBugReport(), 300);
        });

        // Close on overlay click (but not on modal click)
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });

        // Close on Escape key
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                closeModal(overlay);
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
    }

    // Initialize
    showDevNotice();
})();
