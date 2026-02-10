/**
 * Development Notice Modal
 * Shows a one-time notice to users about the site being in preview mode
 * On mobile: Always shows with prompt to try new React frontend
 * On desktop: Shows once per version
 */

(function() {
    'use strict';

    const STORAGE_KEY = 'dev_notice_dismissed';
    const STORAGE_VERSION = '2.3'; // Increment to show again to all users

    function isMobileDevice() {
        // Check for mobile via user agent or screen width
        const isMobileUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const isMobileWidth = window.innerWidth <= 768;
        // Check if running in Capacitor native app
        const isNativeApp = window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform();
        return isMobileUA || isMobileWidth || isNativeApp;
    }

    function hasSeenNotice() {
        // On mobile, always show the notice
        if (isMobileDevice()) {
            return false;
        }
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
        // On mobile, use sessionStorage so it shows again next visit
        if (isMobileDevice()) {
            try {
                sessionStorage.setItem(STORAGE_KEY + '_session', '1');
            } catch (e) {
                // Ignore
            }
            return;
        }
        try {
            // Use localStorage for persistence across app restarts (mobile apps clear sessionStorage)
            localStorage.setItem(STORAGE_KEY, STORAGE_VERSION);
        } catch (e) {
            // localStorage not available, ignore
            console.warn('Could not save dev notice preference');
        }
    }

    function hasSeenThisSession() {
        // For mobile, check if already shown this session
        try {
            return sessionStorage.getItem(STORAGE_KEY + '_session') === '1';
        } catch (e) {
            return false;
        }
    }

    function createModal() {
        const overlay = document.createElement('div');
        overlay.className = 'dev-notice-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'dev-notice-title');

        const isMobile = isMobileDevice();

        if (isMobile) {
            // Mobile-specific content with new frontend prompt
            overlay.innerHTML = `
                <div class="dev-notice-modal">
                    <div class="dev-notice-header">
                        <div class="dev-notice-icon">📱</div>
                        <h2 id="dev-notice-title">Better Mobile Experience Available!</h2>
                    </div>
                    <div class="dev-notice-body">
                        <p><strong>Great news!</strong> We've built a brand new mobile-optimised version of the platform that works much better on phones and tablets.</p>

                        <div class="dev-notice-highlight">
                            <strong>Try our new app:</strong> Faster loading, smoother navigation, and designed specifically for mobile devices. Give it a try at <strong>app.project-nexus.ie</strong>
                        </div>

                        <p>The current site you're viewing is still under development for mobile. For the best experience, we recommend switching to our new frontend.</p>
                    </div>
                    <div class="dev-notice-footer">
                        <a href="https://app.project-nexus.ie/" class="dev-notice-btn dev-notice-btn-primary" id="dev-notice-try-new">
                            <i class="fa-solid fa-sparkles" style="margin-right: 6px;"></i> Try New Mobile App
                        </a>
                        <button class="dev-notice-btn dev-notice-btn-secondary" id="dev-notice-continue">
                            Continue with Current Site
                        </button>
                    </div>
                </div>
            `;
        } else {
            // Desktop content (original)
            overlay.innerHTML = `
                <div class="dev-notice-modal">
                    <div class="dev-notice-header">
                        <div class="dev-notice-icon">🚧</div>
                        <h2 id="dev-notice-title">Preview Mode</h2>
                    </div>
                    <div class="dev-notice-body">
                        <p><strong>Welcome!</strong> This site is still under active development and you're viewing it in preview mode.</p>

                        <div class="dev-notice-highlight">
                            <strong>New Frontend Available:</strong> We're working on a brand new frontend with a better experience. Try it out at <a href="https://app.project-nexus.ie/" style="color: var(--color-primary-400); font-weight: 600;">app.project-nexus.ie</a>
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
        }

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
        // eslint-disable-next-line no-restricted-syntax -- animation property for fade-out
        overlay.style.animation = 'fadeOut 0.2s ease-out';
        setTimeout(() => {
            overlay.remove();
            document.body.classList.remove('js-overflow-hidden');
        }, 200);
        markNoticeSeen();
    }

    function showDevNotice() {
        // For mobile: check if already shown this session
        if (isMobileDevice() && hasSeenThisSession()) {
            return;
        }

        // For desktop: check if user has already seen this version of the notice
        if (!isMobileDevice() && hasSeenNotice()) {
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
        document.body.classList.add('js-overflow-hidden');

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
        const tryNewBtn = overlay.querySelector('#dev-notice-try-new');

        if (continueBtn) {
            continueBtn.addEventListener('click', () => {
                closeModal(overlay);
            });
        }

        if (reportBtn) {
            reportBtn.addEventListener('click', () => {
                closeModal(overlay);
                setTimeout(() => scrollToBugReport(), 300);
            });
        }

        // "Try New Mobile App" link - just let it navigate, but mark as seen
        if (tryNewBtn) {
            tryNewBtn.addEventListener('click', () => {
                markNoticeSeen();
            });
        }

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
