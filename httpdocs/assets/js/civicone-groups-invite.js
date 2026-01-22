/**
 * CivicOne Groups Invite - Member Selection Interactivity
 * WCAG 2.1 AA Compliant
 * Features: Search filter, selection count, offline detection, button states
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('userSearch');
        const userList = document.getElementById('userList');
        const selectedCount = document.getElementById('selectedCount');
        const submitBtn = document.getElementById('submitBtn');
        const checkboxes = document.querySelectorAll('input[name="user_ids[]"]');

        // Search filter
        if (searchInput && userList) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const items = userList.querySelectorAll('.user-item');

                items.forEach(item => {
                    const name = item.dataset.name;
                    item.style.display = name.includes(query) ? 'flex' : 'none';
                });
            });
        }

        // Selection count
        function updateCount() {
            const checked = document.querySelectorAll('input[name="user_ids[]"]:checked').length;
            if (selectedCount) {
                selectedCount.textContent = checked + ' member' + (checked !== 1 ? 's' : '') + ' selected';
            }
            if (submitBtn) {
                submitBtn.disabled = checked === 0;
            }
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                this.closest('.user-item').classList.toggle('selected', this.checked);
                updateCount();
            });
        });

        // Toggle button text based on "Add directly" checkbox
        const addDirectlyCheckbox = document.getElementById('addDirectlyCheckbox');
        if (addDirectlyCheckbox && submitBtn) {
            addDirectlyCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    submitBtn.textContent = 'Add Members Now';
                    submitBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                } else {
                    submitBtn.textContent = 'Send Invitations';
                    submitBtn.style.background = 'linear-gradient(135deg, #db2777, #ec4899)';
                }
            });
        }
    });

    // ============================================
    // GOLD STANDARD - Native App Features
    // ============================================

    // Offline Indicator
    (function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        function handleOffline() {
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }

        function handleOnline() {
            banner.classList.remove('visible');
        }

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        if (!navigator.onLine) {
            handleOffline();
        }
    })();

    // Form Submission Offline Protection
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to send invitations.');
                return;
            }
        });
    });

    // Button Press States
    document.querySelectorAll('.invite-submit, button').forEach(btn => {
        btn.addEventListener('pointerdown', function() {
            this.style.transform = 'scale(0.96)';
        });
        btn.addEventListener('pointerup', function() {
            this.style.transform = '';
        });
        btn.addEventListener('pointerleave', function() {
            this.style.transform = '';
        });
    });

    // Dynamic Theme Color
    (function initDynamicThemeColor() {
        const metaTheme = document.querySelector('meta[name="theme-color"]');
        if (!metaTheme) {
            const meta = document.createElement('meta');
            meta.name = 'theme-color';
            meta.content = '#db2777';
            document.head.appendChild(meta);
        }

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
            }
        }

        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        updateThemeColor();
    })();
})();
