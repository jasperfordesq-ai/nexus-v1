/**
 * Federation Send Hours - JavaScript
 * Handles quick amount selection, form submission, and offline detection
 */
(function() {
    'use strict';

    // Quick amount setter for amount buttons
    window.setAmount = function(val) {
        const input = document.getElementById('amount-input');
        if (input) {
            input.value = val;
        }
    };

    // Form submission handler - show loading state
    function initFormSubmit() {
        const form = document.querySelector('.send-form');
        if (!form) return;

        form.addEventListener('submit', function() {
            const btn = document.getElementById('send-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            }
        });
    }

    // Offline indicator - handled by header.php verified-offline system
    // This is kept for backwards compatibility but the header's system is primary
    function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        window.addEventListener('online', function() {
            banner.classList.remove('visible');
        });

        window.addEventListener('offline', function() {
            banner.classList.add('visible');
        });

        if (!navigator.onLine) {
            banner.classList.add('visible');
        }
    }

    // Initialize on DOM ready
    function init() {
        initFormSubmit();
        initOfflineIndicator();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
