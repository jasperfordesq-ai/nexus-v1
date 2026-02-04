/**
 * Service Worker Kill Switch
 * Emergency bypass for stale SW cache on mobile devices
 *
 * Usage: Add ?nosw=1 to any URL to:
 *   1. Unregister all service workers
 *   2. Clear all Cache Storage
 *   3. Clear SW-related localStorage/sessionStorage
 *   4. Force hard reload
 *
 * This file MUST be loaded early in <head> before other scripts.
 * TEMPORARY - remove when SW caching issues are resolved.
 */

(function() {
    'use strict';

    // Only run if ?nosw=1 is present
    if (window.location.search.indexOf('nosw=1') === -1) {
        return;
    }

    // Don't run twice (after reload)
    if (window.location.search.indexOf('nosw=done') !== -1) {
        return;
    }

    // Show loading message immediately
    var overlay = document.createElement('div');
    overlay.id = 'sw-killswitch-overlay';
    overlay.innerHTML = '<div style="text-align:center;"><div style="font-size:24px;margin-bottom:16px;">⏳</div><div>Refreshing site data…</div><div id="sw-killswitch-status" style="font-size:12px;margin-top:8px;opacity:0.7;">Starting...</div></div>';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999999;background:rgba(0,0,0,0.9);color:#fff;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,sans-serif;font-size:16px;';

    // Append to body or wait for it
    function showOverlay() {
        if (document.body) {
            document.body.appendChild(overlay);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.appendChild(overlay);
            });
        }
    }
    showOverlay();

    function updateStatus(msg) {
        var statusEl = document.getElementById('sw-killswitch-status');
        if (statusEl) {
            statusEl.textContent = msg;
        }
        console.log('[SW-KILLSWITCH] ' + msg);
    }

    async function killServiceWorkers() {
        var swCleared = false;
        var cachesCleared = false;
        var storageCleared = false;

        // 1. Unregister all service workers
        if ('serviceWorker' in navigator) {
            try {
                updateStatus('Unregistering service workers...');
                var registrations = await navigator.serviceWorker.getRegistrations();
                for (var i = 0; i < registrations.length; i++) {
                    await registrations[i].unregister();
                    console.log('[SW-KILLSWITCH] Unregistered SW:', registrations[i].scope);
                }
                swCleared = true;
                updateStatus('Service workers unregistered (' + registrations.length + ')');
            } catch (e) {
                console.error('[SW-KILLSWITCH] Error unregistering SW:', e);
                updateStatus('SW unregister error: ' + e.message);
            }
        } else {
            updateStatus('No service worker support');
            swCleared = true;
        }

        // 2. Clear all Cache Storage
        if ('caches' in window) {
            try {
                updateStatus('Clearing cache storage...');
                var cacheNames = await caches.keys();
                for (var j = 0; j < cacheNames.length; j++) {
                    await caches.delete(cacheNames[j]);
                    console.log('[SW-KILLSWITCH] Deleted cache:', cacheNames[j]);
                }
                cachesCleared = true;
                updateStatus('Cache storage cleared (' + cacheNames.length + ' caches)');
            } catch (e) {
                console.error('[SW-KILLSWITCH] Error clearing caches:', e);
                updateStatus('Cache clear error: ' + e.message);
            }
        } else {
            updateStatus('No Cache Storage support');
            cachesCleared = true;
        }

        // 3. Clear SW-related localStorage/sessionStorage keys
        try {
            updateStatus('Clearing SW-related storage...');
            var prefixes = ['nexus_', 'pwa_', 'sw_', 'workbox'];
            var clearedKeys = [];

            // localStorage
            if (window.localStorage) {
                var lsKeys = Object.keys(localStorage);
                for (var k = 0; k < lsKeys.length; k++) {
                    var key = lsKeys[k];
                    for (var p = 0; p < prefixes.length; p++) {
                        if (key.indexOf(prefixes[p]) === 0) {
                            localStorage.removeItem(key);
                            clearedKeys.push('ls:' + key);
                            break;
                        }
                    }
                }
            }

            // sessionStorage
            if (window.sessionStorage) {
                var ssKeys = Object.keys(sessionStorage);
                for (var m = 0; m < ssKeys.length; m++) {
                    var skey = ssKeys[m];
                    for (var q = 0; q < prefixes.length; q++) {
                        if (skey.indexOf(prefixes[q]) === 0) {
                            sessionStorage.removeItem(skey);
                            clearedKeys.push('ss:' + skey);
                            break;
                        }
                    }
                }
            }

            storageCleared = true;
            updateStatus('Storage cleared (' + clearedKeys.length + ' keys)');
            if (clearedKeys.length > 0) {
                console.log('[SW-KILLSWITCH] Cleared storage keys:', clearedKeys);
            }
        } catch (e) {
            console.error('[SW-KILLSWITCH] Error clearing storage:', e);
            storageCleared = true; // Non-fatal
        }

        return { swCleared: swCleared, cachesCleared: cachesCleared, storageCleared: storageCleared };
    }

    function forceReload() {
        updateStatus('Reloading page...');

        // Build new URL without nosw=1, with nosw=done to prevent loop
        var url = window.location.href;

        // Replace nosw=1 with nosw=done
        url = url.replace(/([?&])nosw=1/, '$1nosw=done');

        // Add cache-busting timestamp
        var separator = url.indexOf('?') !== -1 ? '&' : '?';
        url = url + separator + '_swkill=' + Date.now();

        // Small delay to ensure all async operations complete
        setTimeout(function() {
            // Try location.reload(true) first (deprecated but still works in some browsers)
            // Then fall back to navigating to the cache-busted URL
            try {
                // Navigate to new URL to ensure fresh fetch
                window.location.href = url;
            } catch (e) {
                window.location.reload();
            }
        }, 500);
    }

    // Run the kill switch
    console.log('[SW-KILLSWITCH] Starting emergency cache clear...');

    killServiceWorkers().then(function(result) {
        console.log('[SW-KILLSWITCH] Complete:', result);
        updateStatus('Complete! Reloading...');
        forceReload();
    }).catch(function(e) {
        console.error('[SW-KILLSWITCH] Fatal error:', e);
        updateStatus('Error: ' + e.message + ' - reloading anyway...');
        forceReload();
    });

})();
