// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef, useState } from 'react';

/**
 * useTurnstile — explicit Cloudflare Turnstile rendering hook.
 *
 * Background: Cloudflare's `api.js` auto-discovery scans the DOM exactly
 * ONCE on script load — it does NOT use MutationObserver (verified by
 * grep on the live api.js: zero occurrences). React Router SPAs that
 * mount form pages via lazy-loaded chunks therefore lose the widget,
 * because the `<div class="cf-turnstile">` appears in the DOM *after*
 * the script's one-shot scan has completed.
 *
 * Implicit rendering (the `data-callback` pattern) worked for pages in
 * the main bundle by accident — they raced the scan and sometimes won.
 *
 * This hook instead loads the script once, waits for `window.turnstile`
 * to be defined, then calls `turnstile.render()` explicitly against the
 * supplied container ref. Deterministic. Works on lazy-loaded chunks.
 *
 * Usage:
 *   const { token, siteKey, containerRef } = useTurnstile();
 *   // in render:
 *   {siteKey && <div ref={containerRef} />}
 */

interface TurnstileApi {
  render: (
    element: HTMLElement | string,
    options: {
      sitekey: string;
      callback: (token: string) => void;
      theme?: 'light' | 'dark' | 'auto';
      'error-callback'?: () => void;
      'expired-callback'?: () => void;
    },
  ) => string;
  remove: (widgetId: string) => void;
  reset: (widgetId?: string) => void;
}

declare global {
  interface Window {
    turnstile?: TurnstileApi;
  }
}

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

export function useTurnstile() {
  const [token, setToken] = useState<string>('');
  const containerRef = useRef<HTMLDivElement>(null);
  const widgetIdRef = useRef<string | null>(null);
  const siteKey = (import.meta.env.VITE_TURNSTILE_SITE_KEY as string | undefined) ?? '';

  useEffect(() => {
    if (!siteKey) return;

    // Inject api.js once per page.
    if (!document.getElementById(SCRIPT_ID)) {
      const s = document.createElement('script');
      s.id = SCRIPT_ID;
      s.src = SCRIPT_SRC;
      s.async = true;
      s.defer = true;
      document.head.appendChild(s);
    }

    let cancelled = false;
    const tryRender = () => {
      if (cancelled) return;
      if (!containerRef.current) return;
      if (!window.turnstile) {
        window.setTimeout(tryRender, 100);
        return;
      }
      try {
        widgetIdRef.current = window.turnstile.render(containerRef.current, {
          sitekey: siteKey,
          callback: (t: string) => setToken(t),
          theme: 'auto',
          'expired-callback': () => setToken(''),
        });
      } catch {
        // Already rendered or container detached — fall through silently.
      }
    };
    tryRender();

    return () => {
      cancelled = true;
      if (widgetIdRef.current && window.turnstile) {
        try {
          window.turnstile.remove(widgetIdRef.current);
        } catch {
          // ignore
        }
      }
      widgetIdRef.current = null;
      setToken('');
    };
  }, [siteKey]);

  return { token, siteKey, containerRef };
}
