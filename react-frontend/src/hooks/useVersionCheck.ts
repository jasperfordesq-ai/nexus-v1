// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useVersionCheck — SW-independent deploy detection for all browsers.
 *
 * Polls /build-info.json (emitted at build time by vite.config.ts) and
 * compares the server's commit hash to the one baked into this bundle.
 * When they differ, dispatches 'nexus:sw_update_available' so the existing
 * UpdateAvailableBanner shows — same UX as the SW-based update, no auto-refresh.
 *
 * Why this works even for users with a stale/broken service worker:
 *   - build-info.json is a .json file — excluded from workbox precache patterns
 *     (*.{js,css,html,ico,png,svg,woff2}), so old SWs have no cached copy and
 *     pass the fetch straight to nginx.
 *   - fetch() is called with { cache: 'no-store' } to also bypass the HTTP cache.
 *   - nginx serves /build-info.json via the `location /` block which sets
 *     Cache-Control: no-store, no-cache on all non-asset responses.
 *
 * Triggers:
 *   - 15 s after mount (avoids competing with the page load critical path)
 *   - Every 5 minutes while the page is open
 *   - Immediately when the tab becomes visible (covers mobile app-switch)
 */

import { useEffect, useCallback, useRef } from 'react';

const CHECK_INTERVAL_MS = 5 * 60 * 1000;
const INITIAL_DELAY_MS = 15_000;

export function useVersionCheck() {
  // Once we've notified in this session, don't fire again — the banner is already showing.
  const hasNotified = useRef(false);

  const check = useCallback(async () => {
    if (hasNotified.current) return;
    try {
      const res = await fetch('/build-info.json', { cache: 'no-store' });
      if (!res.ok) return;
      const data: { commit?: string } = await res.json();
      // 'dev' is the fallback value used in local development — never treat it as a mismatch.
      if (data.commit && data.commit !== 'dev' && data.commit !== __BUILD_COMMIT__) {
        hasNotified.current = true;
        window.dispatchEvent(new CustomEvent('nexus:sw_update_available'));
      }
    } catch {
      // Offline or transient network error — non-blocking, retry on next interval.
    }
  }, []);

  useEffect(() => {
    const initialTimer = setTimeout(check, INITIAL_DELAY_MS);
    const interval = setInterval(check, CHECK_INTERVAL_MS);

    function handleVisibilityChange() {
      if (document.visibilityState === 'visible') check();
    }
    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      clearTimeout(initialTimer);
      clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [check]);
}
