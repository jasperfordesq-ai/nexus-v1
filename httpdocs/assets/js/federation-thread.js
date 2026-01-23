/**
 * Federation Message Thread - JavaScript
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    // Auto-scroll to bottom of messages
    var container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }

    // Auto-expand textarea
    var textarea = document.getElementById('message-input');
    if (textarea) {
        textarea.addEventListener('input', function() {
            // eslint-disable-next-line no-restricted-syntax -- dynamic height for auto-resize
            this.style.height = 'auto';
            // eslint-disable-next-line no-restricted-syntax -- dynamic height for auto-resize
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });

        // Submit on Enter (but not Shift+Enter)
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    }

    // Offline indicator
    var offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        window.addEventListener('online', function() {
            offlineBanner.classList.remove('visible');
        });
        window.addEventListener('offline', function() {
            offlineBanner.classList.add('visible');
        });
        if (!navigator.onLine) {
            offlineBanner.classList.add('visible');
        }
    }
})();
