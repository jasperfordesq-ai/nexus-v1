/**
 * Mobile Overflow Debug Instrumentation
 * DEV ONLY - Remove before production deploy
 *
 * Purpose: Diagnose inset scrollbar issue on /listings mobile
 *
 * Usage: Add to footer.php before </body>:
 * <?php if ($_GET['debug_overflow'] ?? false): ?>
 *     <script src="/assets/js/debug-mobile-overflow.js"></script>
 * <?php endif; ?>
 *
 * Then visit: /listings?debug_overflow=1
 */

(function() {
    'use strict';

    // Only run on mobile viewport
    if (window.innerWidth > 768) {
        console.log('[OverflowDebug] Skipping - not mobile viewport');
        return;
    }

    const DEBUG_PREFIX = '[OverflowDebug]';

    // ========================================
    // 1. LOG DOCUMENT DIMENSIONS
    // ========================================
    function logDimensions() {
        const html = document.documentElement;
        const body = document.body;

        console.group(`${DEBUG_PREFIX} Document Dimensions`);
        console.log('window.innerWidth:', window.innerWidth);
        console.log('html.clientWidth:', html.clientWidth);
        console.log('html.scrollWidth:', html.scrollWidth);
        console.log('body.clientWidth:', body.clientWidth);
        console.log('body.scrollWidth:', body.scrollWidth);
        console.log('body.offsetWidth:', body.offsetWidth);
        console.log('Overflow X (html):', html.scrollWidth - html.clientWidth);
        console.log('Overflow X (body):', body.scrollWidth - body.clientWidth);
        console.groupEnd();

        // Highlight if overflow detected
        const htmlOverflow = html.scrollWidth - html.clientWidth;
        const bodyOverflow = body.scrollWidth - body.clientWidth;

        if (htmlOverflow > 0 || bodyOverflow > 0) {
            console.warn(`${DEBUG_PREFIX} ⚠️ HORIZONTAL OVERFLOW DETECTED!`);
            console.warn(`  HTML overflow: ${htmlOverflow}px`);
            console.warn(`  Body overflow: ${bodyOverflow}px`);
        }
    }

    // ========================================
    // 2. FIND ELEMENTS CAUSING OVERFLOW
    // ========================================
    function findOverflowingElements() {
        const viewportWidth = document.documentElement.clientWidth;
        const overflowing = [];

        document.querySelectorAll('*').forEach(el => {
            const rect = el.getBoundingClientRect();

            // Check if element extends past viewport
            if (rect.right > viewportWidth + 1) { // +1 for rounding tolerance
                overflowing.push({
                    element: el,
                    tagName: el.tagName,
                    className: el.className,
                    id: el.id,
                    right: rect.right,
                    overflow: rect.right - viewportWidth,
                    width: rect.width,
                    computedWidth: getComputedStyle(el).width,
                    computedMaxWidth: getComputedStyle(el).maxWidth
                });
            }
        });

        // Sort by overflow amount
        overflowing.sort((a, b) => b.overflow - a.overflow);

        if (overflowing.length > 0) {
            console.group(`${DEBUG_PREFIX} Overflowing Elements (${overflowing.length} found)`);
            overflowing.slice(0, 10).forEach((item, i) => {
                console.log(`#${i + 1}:`, {
                    tag: item.tagName,
                    class: item.className.substring(0, 60),
                    id: item.id,
                    overflow: `${item.overflow.toFixed(1)}px`,
                    computedWidth: item.computedWidth,
                    element: item.element
                });
            });
            console.groupEnd();
        } else {
            console.log(`${DEBUG_PREFIX} ✅ No overflowing elements found`);
        }

        return overflowing;
    }

    // ========================================
    // 3. DETECT WHICH ELEMENT IS SCROLLING
    // ========================================
    function detectScrollContainer() {
        let scrollingElement = null;
        let lastScrollTop = {};

        // Check these candidates
        const candidates = [
            document.documentElement,
            document.body,
            document.querySelector('main'),
            document.querySelector('#main-content'),
            document.querySelector('.htb-container-full'),
            document.querySelector('#listings-index-glass-wrapper')
        ].filter(Boolean);

        // Record initial scroll positions
        candidates.forEach((el, i) => {
            lastScrollTop[i] = el.scrollTop;
        });

        // Listen for scroll events
        function onScroll() {
            candidates.forEach((el, i) => {
                if (el.scrollTop !== lastScrollTop[i]) {
                    if (!scrollingElement || scrollingElement !== el) {
                        scrollingElement = el;
                        console.log(`${DEBUG_PREFIX} 📜 Scrolling element detected:`, {
                            tag: el.tagName,
                            class: el.className?.substring?.(0, 40) || '',
                            id: el.id,
                            scrollTop: el.scrollTop,
                            scrollHeight: el.scrollHeight,
                            clientHeight: el.clientHeight,
                            overflowY: getComputedStyle(el).overflowY
                        });
                    }
                    lastScrollTop[i] = el.scrollTop;
                }
            });
        }

        // Attach to window (captures bubbled events)
        window.addEventListener('scroll', onScroll, true);

        console.log(`${DEBUG_PREFIX} Scroll detection active - scroll to identify container`);
    }

    // ========================================
    // 4. CHECK OVERFLOW STYLES
    // ========================================
    function checkOverflowStyles() {
        const elements = [
            { name: 'html', el: document.documentElement },
            { name: 'body', el: document.body },
            { name: 'main', el: document.querySelector('main') },
            { name: '#main-content', el: document.querySelector('#main-content') },
            { name: '.htb-container-full', el: document.querySelector('.htb-container-full') },
            { name: '#listings-index-glass-wrapper', el: document.querySelector('#listings-index-glass-wrapper') }
        ];

        console.group(`${DEBUG_PREFIX} Overflow Styles`);
        elements.forEach(({ name, el }) => {
            if (!el) return;
            const style = getComputedStyle(el);
            console.log(name, {
                overflowX: style.overflowX,
                overflowY: style.overflowY,
                width: style.width,
                maxWidth: style.maxWidth,
                position: style.position
            });
        });
        console.groupEnd();
    }

    // ========================================
    // 5. WATCH FOR DYNAMIC OVERFLOW CHANGES
    // ========================================
    function watchOverflowChanges() {
        let lastHtmlOverflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
        let lastBodyOverflow = document.body.scrollWidth - document.body.clientWidth;

        const observer = new MutationObserver(() => {
            const htmlOverflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
            const bodyOverflow = document.body.scrollWidth - document.body.clientWidth;

            if (htmlOverflow !== lastHtmlOverflow || bodyOverflow !== lastBodyOverflow) {
                console.warn(`${DEBUG_PREFIX} ⚡ Overflow changed!`, {
                    html: { was: lastHtmlOverflow, now: htmlOverflow },
                    body: { was: lastBodyOverflow, now: bodyOverflow }
                });
                lastHtmlOverflow = htmlOverflow;
                lastBodyOverflow = bodyOverflow;

                // Re-scan for culprits
                findOverflowingElements();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });

        console.log(`${DEBUG_PREFIX} Mutation observer active - watching for DOM changes`);
    }

    // ========================================
    // 6. VISUAL HIGHLIGHT OVERFLOWING ELEMENTS
    // ========================================
    function highlightOverflowing() {
        const viewportWidth = document.documentElement.clientWidth;

        document.querySelectorAll('[data-overflow-debug]').forEach(el => {
            el.removeAttribute('data-overflow-debug');
            el.style.outline = '';
        });

        document.querySelectorAll('*').forEach(el => {
            const rect = el.getBoundingClientRect();
            if (rect.right > viewportWidth + 1) {
                el.setAttribute('data-overflow-debug', 'true');
                el.style.outline = '2px solid red';
            }
        });
    }

    // ========================================
    // 7. CHECK FOR 100vw USAGE
    // ========================================
    function check100vwUsage() {
        const all = document.querySelectorAll('*');
        const using100vw = [];

        all.forEach(el => {
            const style = getComputedStyle(el);
            // Can't directly detect 100vw from computed style (it becomes px)
            // But we can check inline styles and stylesheets
            const inline = el.getAttribute('style') || '';
            if (inline.includes('100vw')) {
                using100vw.push({
                    element: el,
                    tagName: el.tagName,
                    className: el.className,
                    inlineStyle: inline
                });
            }
        });

        if (using100vw.length > 0) {
            console.group(`${DEBUG_PREFIX} Elements with inline 100vw`);
            using100vw.forEach(item => {
                console.log(item.tagName, item.className, item.inlineStyle.substring(0, 100));
            });
            console.groupEnd();
        }
    }

    // ========================================
    // RUN ALL DIAGNOSTICS
    // ========================================
    function runDiagnostics() {
        console.log(`${DEBUG_PREFIX} ========================================`);
        console.log(`${DEBUG_PREFIX} Mobile Overflow Diagnostics Starting...`);
        console.log(`${DEBUG_PREFIX} URL: ${window.location.href}`);
        console.log(`${DEBUG_PREFIX} ========================================`);

        logDimensions();
        checkOverflowStyles();
        const overflowing = findOverflowingElements();
        check100vwUsage();
        detectScrollContainer();
        watchOverflowChanges();

        // Add global helper for console
        window.debugOverflow = {
            logDimensions,
            findOverflowingElements,
            checkOverflowStyles,
            highlightOverflowing,
            rescan: function() {
                console.log(`${DEBUG_PREFIX} Re-scanning...`);
                logDimensions();
                findOverflowingElements();
            }
        };

        console.log(`${DEBUG_PREFIX} ✅ Diagnostics loaded. Helpers available:`);
        console.log('  - debugOverflow.rescan() - Re-run dimension checks');
        console.log('  - debugOverflow.highlightOverflowing() - Outline overflowing elements in red');
        console.log('  - debugOverflow.findOverflowingElements() - List all overflowing elements');
    }

    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runDiagnostics);
    } else {
        runDiagnostics();
    }

    // Also run after full load (images, CSS)
    window.addEventListener('load', () => {
        console.log(`${DEBUG_PREFIX} Window load complete - re-checking...`);
        setTimeout(runDiagnostics, 500);
    });

})();
