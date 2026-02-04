/**
 * LIVE SCROLL DEBUG - Mobile Diagnostics
 * Shows on-screen overlay with computed styles and event tracking
 * Usage: Add ?debug_scroll=1 to any URL
 *
 * Created: 2026-02-01 for /listings scroll investigation
 */

(function() {
    'use strict';

    // Only run if debug param present
    if (window.location.search.indexOf('debug_scroll=1') === -1) {
        return;
    }

    // Create overlay
    var overlay = document.createElement('div');
    overlay.id = 'debug-scroll-overlay';
    overlay.style.cssText = [
        'position: fixed',
        'top: 0',
        'left: 0',
        'right: 0',
        'max-height: 50vh',
        'overflow-y: auto',
        'background: rgba(255,255,255,0.97)',
        'color: #000',
        'font: 11px/1.4 monospace',
        'padding: 8px',
        'z-index: 999999',
        'border-bottom: 3px solid #f00',
        'box-shadow: 0 2px 10px rgba(0,0,0,0.3)',
        '-webkit-overflow-scrolling: touch'
    ].join(';');

    // Log container
    var logContent = document.createElement('div');
    logContent.id = 'debug-scroll-log';
    overlay.appendChild(logContent);

    // Touch event log (separate, at bottom)
    var touchLog = document.createElement('div');
    touchLog.id = 'debug-touch-log';
    touchLog.style.cssText = 'margin-top:8px;padding-top:8px;border-top:1px solid #ccc;color:#666;';
    touchLog.innerHTML = '<b>Touch Events:</b> waiting...';
    overlay.appendChild(touchLog);

    // Close button
    var closeBtn = document.createElement('button');
    closeBtn.textContent = 'X';
    closeBtn.style.cssText = 'position:absolute;top:4px;right:4px;background:#f00;color:#fff;border:none;padding:4px 8px;font-weight:bold;cursor:pointer;';
    closeBtn.onclick = function() { overlay.style.display = 'none'; };
    overlay.appendChild(closeBtn);

    // Helper to get computed styles
    function getStyles(el, name) {
        if (!el) return { error: 'Element not found: ' + name };
        var cs = getComputedStyle(el);
        return {
            name: name,
            overflow: cs.overflow,
            overflowX: cs.overflowX,
            overflowY: cs.overflowY,
            touchAction: cs.touchAction,
            position: cs.position,
            height: cs.height,
            maxHeight: cs.maxHeight,
            top: cs.top,
            bottom: cs.bottom
        };
    }

    // Helper to format styles
    function formatStyles(s) {
        if (s.error) return '<span style="color:red">' + s.error + '</span>';
        var flags = [];
        if (s.overflow === 'hidden' || s.overflowY === 'hidden') flags.push('<span style="color:red">overflow:hidden!</span>');
        if (s.touchAction === 'none') flags.push('<span style="color:red">touch-action:none!</span>');
        if (s.position === 'fixed') flags.push('<span style="color:orange">position:fixed</span>');
        if (s.height === '100%' || s.height === '100vh') flags.push('<span style="color:orange">height:' + s.height + '</span>');

        var flagStr = flags.length ? ' <b>[' + flags.join(', ') + ']</b>' : '';
        return '<b>' + s.name + '</b>' + flagStr + '<br>' +
               '  overflow: ' + s.overflow + ' (x:' + s.overflowX + ', y:' + s.overflowY + ')<br>' +
               '  touchAction: ' + s.touchAction + '<br>' +
               '  position: ' + s.position + ', height: ' + s.height + ', maxHeight: ' + s.maxHeight;
    }

    // Main diagnostic function
    function runDiagnostics() {
        var html = [];

        // Header
        html.push('<b style="font-size:13px;color:#c00;">SCROLL DEBUG - ' + new Date().toLocaleTimeString() + '</b>');
        html.push('<hr style="margin:4px 0">');

        // 1-2. Body classes and inline styles
        html.push('<b>body.className:</b>');
        var bodyClasses = document.body.className.split(/\s+/).filter(Boolean);
        if (bodyClasses.length === 0) {
            html.push('  (none)');
        } else {
            bodyClasses.forEach(function(c) {
                var warn = '';
                if (['mobile-menu-open', 'mobile-notifications-open', 'modal-open', 'drawer-open',
                     'fds-sheet-open', 'menu-open', 'js-overflow-hidden', 'no-ptr'].indexOf(c) !== -1) {
                    warn = ' <span style="color:red;font-weight:bold">** SUSPECT **</span>';
                }
                html.push('  - ' + c + warn);
            });
        }

        html.push('');
        html.push('<b>body.style.cssText:</b>');
        html.push('  ' + (document.body.style.cssText || '(empty)'));

        html.push('');
        html.push('<b>html.style.cssText:</b>');
        html.push('  ' + (document.documentElement.style.cssText || '(empty)'));

        // 3. Scroll dimensions
        html.push('');
        html.push('<b>Scroll Dimensions:</b>');
        html.push('  body.scrollHeight: ' + document.body.scrollHeight + 'px');
        html.push('  documentElement.scrollHeight: ' + document.documentElement.scrollHeight + 'px');
        html.push('  window.innerHeight: ' + window.innerHeight + 'px');
        var canScroll = document.body.scrollHeight > window.innerHeight ||
                        document.documentElement.scrollHeight > window.innerHeight;
        html.push('  Content taller than viewport: ' + (canScroll ? '<span style="color:green">YES</span>' : '<span style="color:red">NO</span>'));

        // 4. Computed styles chain
        html.push('');
        html.push('<b style="font-size:12px">== COMPUTED STYLES CHAIN ==</b>');

        var elements = [
            { el: document.documentElement, name: 'html' },
            { el: document.body, name: 'body' },
            { el: document.getElementById('main-content'), name: '#main-content' },
            { el: document.querySelector('.htb-container-full'), name: '.htb-container-full' },
            { el: document.getElementById('listings-index-glass-wrapper'), name: '#listings-index-glass-wrapper' },
            { el: document.getElementById('listings-grid'), name: '#listings-grid' }
        ];

        elements.forEach(function(item) {
            html.push('');
            html.push(formatStyles(getStyles(item.el, item.name)));
        });

        // 5. Check for any element with problematic styles in the ancestor chain
        html.push('');
        html.push('<b style="font-size:12px">== FULL ANCESTOR SCAN ==</b>');
        var target = document.getElementById('listings-grid') || document.getElementById('listings-index-glass-wrapper');
        if (target) {
            var el = target;
            var depth = 0;
            while (el && el !== document.documentElement && depth < 20) {
                var cs = getComputedStyle(el);
                var issues = [];
                if (cs.overflow === 'hidden') issues.push('overflow:hidden');
                if (cs.overflowY === 'hidden') issues.push('overflowY:hidden');
                if (cs.touchAction === 'none') issues.push('touchAction:none');
                if (cs.position === 'fixed' && el !== document.body) issues.push('position:fixed');

                if (issues.length > 0) {
                    var tag = el.tagName.toLowerCase();
                    var id = el.id ? '#' + el.id : '';
                    var cls = el.className ? '.' + el.className.split(' ')[0] : '';
                    html.push('<span style="color:red">  ' + tag + id + cls + ': ' + issues.join(', ') + '</span>');
                }
                el = el.parentElement;
                depth++;
            }
        }

        // 6. Check html classes
        html.push('');
        html.push('<b>html.className:</b>');
        html.push('  ' + (document.documentElement.className || '(none)'));

        // 7. Active overlays/modals check
        html.push('');
        html.push('<b>Active Overlays Check:</b>');
        var mobileMenu = document.getElementById('mobileMenu');
        var mobileNotif = document.getElementById('mobileNotifications');
        var anyModal = document.querySelector('.modal.active, .modal.show');
        var anySheet = document.querySelector('.fds-sheet.active, .mobile-sheet.active');
        html.push('  #mobileMenu.active: ' + (mobileMenu && mobileMenu.classList.contains('active')));
        html.push('  #mobileNotifications.active: ' + (mobileNotif && mobileNotif.classList.contains('active')));
        html.push('  .modal.active/.show: ' + !!anyModal);
        html.push('  .fds-sheet.active: ' + !!anySheet);

        logContent.innerHTML = html.join('<br>');
    }

    // Touch event tracking
    var touchEvents = [];
    var maxTouchLogs = 10;

    function logTouch(type, e) {
        var prevented = e.defaultPrevented ? '<span style="color:red">PREVENTED</span>' : 'allowed';
        var target = e.target.tagName + (e.target.id ? '#' + e.target.id : '') + (e.target.className ? '.' + e.target.className.split(' ')[0] : '');
        touchEvents.unshift(new Date().toLocaleTimeString() + ' ' + type + ' on ' + target + ' - ' + prevented);
        if (touchEvents.length > maxTouchLogs) touchEvents.pop();
        touchLog.innerHTML = '<b>Touch Events (last ' + maxTouchLogs + '):</b><br>' + touchEvents.join('<br>');
    }

    // Attach touch listeners (capture phase to see before any handler)
    document.addEventListener('touchstart', function(e) {
        logTouch('touchstart', e);
    }, { capture: true, passive: true });

    document.addEventListener('touchmove', function(e) {
        logTouch('touchmove', e);
    }, { capture: true, passive: true });

    // Also check scroll events
    document.addEventListener('scroll', function(e) {
        logTouch('scroll', e);
    }, { capture: true, passive: true });

    // Add to page
    function init() {
        document.body.appendChild(overlay);
        runDiagnostics();

        // Re-run diagnostics after a delay (in case JS modifies things)
        setTimeout(runDiagnostics, 500);
        setTimeout(runDiagnostics, 1500);
        setTimeout(runDiagnostics, 3000);
    }

    // Initialize when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also run on window load
    window.addEventListener('load', function() {
        setTimeout(runDiagnostics, 100);
    });

    // Expose refresh function globally
    window.debugScrollRefresh = runDiagnostics;

})();
