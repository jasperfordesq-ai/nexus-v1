/**
 * Pull-to-Refresh Prevention
 * Disables PTR on chat and messages pages
 * Must be loaded inline in <body> for immediate effect
 *
 * EMERGENCY FIX 2026-02-01: Disabled inline style manipulation
 * This was breaking sitewide scroll on mobile devices.
 *
 * FIX 2026-02-01: Scoped to pages that opt-in via data-ptr-disable attribute
 * Previously applied globally, which added overscroll-behavior-y: none to all pages.
 * On some Android devices this interferes with scroll momentum.
 */
(function() {
    'use strict';

    // FIX 2026-02-01: Only disable pull-to-refresh on pages that opt in
    // Pages that need PTR disabled should add data-ptr-disable attribute to a wrapper element
    // or have chat-page/chat-fullscreen class on body
    var needsPtrDisable = document.body.classList.contains('chat-page') ||
                          document.body.classList.contains('chat-fullscreen') ||
                          document.body.classList.contains('messages-fullscreen') ||
                          document.querySelector('[data-ptr-disable]');

    if (needsPtrDisable) {
        document.documentElement.classList.add('no-ptr');
        document.body.classList.add('no-ptr');

        // Apply chat-specific classes only for chat pages
        if (document.body.classList.contains('chat-page') || document.body.classList.contains('chat-fullscreen')) {
            document.documentElement.classList.add('chat-page');
        }
    }

    // DISABLED 2026-02-01: Inline style manipulation breaks mobile scroll
    // Let CSS handle overscroll-behavior via .no-ptr class instead
    // ROLLBACK: Uncomment lines below if PTR issues resurface
    // var style = 'overscroll-behavior:none!important;';
    // document.documentElement.style.cssText += style;
    // document.body.style.cssText += style;

    // Only observe for PTR indicators on pages that need PTR disabled
    if (needsPtrDisable) {
        // Intercept and remove any PTR indicators that might be created
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && (node.classList.contains('nexus-ptr-indicator') || node.classList.contains('ptr-indicator'))) {
                        node.remove();
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Stop observing after 5 seconds (scripts should be loaded by then)
        setTimeout(function() {
            observer.disconnect();
        }, 5000);
    }
})();
