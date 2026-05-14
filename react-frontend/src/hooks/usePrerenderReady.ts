// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';

declare global {
  interface Window {
    prerenderReady?: boolean;
  }
}

/**
 * App-level readiness signal for the prerender worker.
 *
 * The Playwright worker waits for `window.prerenderReady === true` before
 * snapshotting the page (see scripts/prerender-worker.mjs). If a route never
 * sets this, the worker falls back to a DOM-content heuristic — fine for
 * static pages but unreliable for data-driven pages (the snapshot can capture
 * a loading spinner instead of the real content).
 *
 * Usage:
 *
 *   // Mark NOT-READY immediately while data is loading.
 *   usePrerenderReady(!isLoading && data != null);
 *
 *   // Or imperatively:
 *   window.prerenderReady = true; // when content is rendered
 *
 * Sets `false` on mount (so the worker waits) and `true` once the `isReady`
 * prop transitions to true. Has no effect for real users — the worker is the
 * only consumer, and the signal is a plain window variable.
 */
export function usePrerenderReady(isReady: boolean): void {
  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (window.prerenderReady === undefined) {
      window.prerenderReady = false;
    }
    if (isReady) {
      window.prerenderReady = true;
    } else {
      window.prerenderReady = false;
    }
  }, [isReady]);
}

/**
 * Synchronously initialise the readiness flag at app boot. Call once from
 * the entry point so the worker always has a defined signal to read, even
 * before any route's component has mounted.
 */
export function initPrerenderReady(): void {
  if (typeof window === 'undefined') return;
  if (window.prerenderReady === undefined) {
    window.prerenderReady = false;
  }
}
