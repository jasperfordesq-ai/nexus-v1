/**
 * Pull-to-Refresh Prevention
 * Disables PTR on chat and messages pages
 * Must be loaded inline in <body> for immediate effect
 */
(function() {
    'use strict';

    // Add no-ptr classes
    document.documentElement.classList.add('no-ptr');
    document.body.classList.add('no-ptr');

    // Apply chat-specific classes only for chat pages
    if (document.body.classList.contains('chat-page') || document.body.classList.contains('chat-fullscreen')) {
        document.documentElement.classList.add('chat-page');
    }

    // Disable overscroll on html/body immediately
    var style = 'overflow:hidden!important;overscroll-behavior:none!important;position:fixed!important;inset:0!important;';
    document.documentElement.style.cssText += style;
    document.body.style.cssText += style;

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
})();
