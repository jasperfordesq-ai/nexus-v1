/**
 * Header Scripts - Extracted from header.php inline scripts
 * Handles: error trapping, offline banner, messages cleanup
 *
 * Note: NEXUS_BASE must be set before this script loads
 */

(function() {
    'use strict';

    // =============================================================================
    // SAFETY WRAPPER FOR toLowerCase
    // Prevents undefined errors - MUST BE FIRST!
    // =============================================================================
    var originalToLowerCase = String.prototype.toLowerCase;
    String.prototype.toLowerCase = function() {
        if (this === undefined || this === null) {
            console.warn('[SAFETY] toLowerCase called on undefined/null, returning empty string');
            return '';
        }
        return originalToLowerCase.call(this);
    };

    // =============================================================================
    // GLOBAL ERROR HANDLER
    // =============================================================================
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        var errorMsg = 'ERROR TRAPPED!\n\n' +
            'Message: ' + msg + '\n' +
            'URL: ' + url + '\n' +
            'Line: ' + lineNo + '\n' +
            'Column: ' + columnNo + '\n' +
            'Error: ' + (error ? error.stack : 'N/A');

        // Save to localStorage
        try {
            localStorage.setItem('LAST_JS_ERROR', errorMsg);
            localStorage.setItem('LAST_JS_ERROR_TIME', new Date().toISOString());
        } catch (e) {
            console.error('Failed to save error to localStorage:', e);
        }

        // Show alert
        alert(errorMsg);

        // Log to console
        console.error('ERROR TRAPPED:', errorMsg);

        // Try to prevent page reload
        if (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        }

        // Return true to prevent default browser error handling
        return true;
    };

    // =============================================================================
    // PROMISE REJECTION HANDLER
    // =============================================================================
    window.addEventListener('unhandledrejection', function(event) {
        var errorMsg = 'PROMISE REJECTION TRAPPED!\n\n' +
            'Reason: ' + (event.reason ? event.reason.message || event.reason : 'Unknown');

        try {
            localStorage.setItem('LAST_PROMISE_ERROR', errorMsg);
            localStorage.setItem('LAST_PROMISE_ERROR_TIME', new Date().toISOString());
        } catch (e) {
            // Ignore storage errors
        }

        alert(errorMsg);
        console.error('TRAPPED PROMISE REJECTION:', event.reason);

        event.preventDefault();
    });

    // Check for errors from previous page load
    try {
        var lastError = localStorage.getItem('LAST_JS_ERROR');
        var lastErrorTime = localStorage.getItem('LAST_JS_ERROR_TIME');
        if (lastError && lastErrorTime) {
            console.warn('ERROR FROM PREVIOUS PAGE LOAD:', lastError);
            console.warn('Time:', lastErrorTime);
        }
    } catch (e) {
        // Ignore storage errors
    }

    console.warn('ERROR TRAP ACTIVE - Any errors will be caught and displayed');

    // =============================================================================
    // OFFLINE BANNER HANDLER
    // Only show after verifying truly offline (not just page load flicker)
    // =============================================================================
    var verified = false;

    function verifyOffline() {
        if (verified) return;
        setTimeout(function() {
            if (!navigator.onLine) {
                var banner = document.getElementById('offlineBanner') || document.querySelector('.offline-banner');
                if (banner) {
                    banner.classList.add('verified-offline');
                    banner.classList.add('visible');
                }
            }
            verified = true;
        }, 2000); // Wait 2 seconds to avoid false positives
    }

    // Run after DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', verifyOffline);
    } else {
        verifyOffline();
    }

    // Handle actual offline events immediately
    window.addEventListener('offline', function() {
        var banner = document.getElementById('offlineBanner') || document.querySelector('.offline-banner');
        if (banner) {
            banner.classList.add('verified-offline');
            banner.classList.add('visible');
        }
    });

    window.addEventListener('online', function() {
        var banner = document.getElementById('offlineBanner') || document.querySelector('.offline-banner');
        if (banner) {
            banner.classList.remove('verified-offline');
            banner.classList.remove('visible');
        }
    });

    // =============================================================================
    // BFCACHE CLEANUP
    // Fix: clean up messages-page classes when on non-messages pages
    // =============================================================================
    function cleanupMessagesClasses() {
        if (!window.location.pathname.match(/\/messages(\/|$)/)) {
            document.documentElement.classList.remove('messages-page');
            // Guard against body not being ready yet
            if (document.body) {
                document.body.classList.remove('messages-page', 'messages-fullscreen', 'no-ptr', 'js-overflow-hidden');
            }
            document.documentElement.classList.remove('js-overflow-hidden');
        }
    }

    // Run on DOMContentLoaded to ensure body exists
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cleanupMessagesClasses);
    } else {
        cleanupMessagesClasses();
    }

    // Also run on pageshow (for bfcache)
    window.addEventListener('pageshow', cleanupMessagesClasses);

})();
