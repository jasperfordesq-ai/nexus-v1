// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { StrictMode, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';
import i18n from './i18n'; // Initialize i18n before App renders

// Log build version to console for deployment verification
console.info(`[NEXUS] Build: ${__BUILD_COMMIT__} | ${__BUILD_TIME__}`);

// Initialize Sentry error tracking (before React renders)
import { initSentry, SentryErrorBoundary, addSentryBreadcrumb } from '@/lib/sentry';
initSentry();

// Register PWA service worker (production only — dev uses Vite HMR).
//
// We register manually instead of using vite-plugin-pwa's `registerSW()` so we
// can pass `updateViaCache: 'none'`. The default `'imports'` lets mobile Chrome
// serve scripts loaded via importScripts() from HTTP cache — the byte-for-byte
// update check sees no change and no waiting worker is ever installed.
// `'none'` bypasses the HTTP cache for both the top-level SW and any imports.
// See https://developer.chrome.com/blog/fresher-sw.
//
// The page reload happens in the `controllerchange` listener below —
// `controllerchange` is the only event that mathematically guarantees the new
// SW has assumed control. The reload is deferred when the user is mid-edit
// (focused inside an input / textarea / contenteditable) so we don't lose
// their work; the deferred reload then fires on the next blur, tab hide, or
// 5-minute ceiling. Linear / Notion / Slack all do this; the immediate
// reload variant interrupted users mid-typing.
if (import.meta.env.PROD) {
  let _nexusRefreshing = false;

  function isUserEditing(): boolean {
    const el = document.activeElement as HTMLElement | null;
    if (!el || el === document.body) return false;
    const tag = el.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
    if (el.isContentEditable) return true;
    // Lexical / Slate / TipTap mount contenteditable inside a wrapper.
    return !!el.closest?.('[contenteditable="true"]');
  }

  function performReload(reason: string): void {
    if (_nexusRefreshing) return;
    _nexusRefreshing = true;
    addSentryBreadcrumb('SW controllerchange — reloading', 'pwa', { reason }, 'info');
    const url = new URL(window.location.href);
    url.searchParams.set('nexus_refresh', String(Date.now()));
    try {
      window.location.replace(url.href);
    } catch {
      window.location.href = url.href;
    }
  }

  function safeReload(): void {
    if (_nexusRefreshing) return;
    if (!isUserEditing()) {
      performReload('immediate');
      return;
    }
    addSentryBreadcrumb('SW controllerchange — reload deferred (user editing)', 'pwa', {}, 'info');
    // Reload at the next natural break-point: blur of the active element,
    // tab hidden, page hide, or a 5-minute ceiling so we don't wait forever.
    const ceiling = window.setTimeout(() => performReload('deferred-ceiling'), 5 * 60 * 1000);
    const fire = (reason: string) => () => {
      window.clearTimeout(ceiling);
      performReload(reason);
    };
    const onBlur = fire('deferred-blur');
    const onHidden = () => {
      if (document.visibilityState === 'hidden') {
        document.removeEventListener('visibilitychange', onHidden);
        fire('deferred-tab-hidden')();
      }
    };
    const onPageHide = fire('deferred-pagehide');
    const editingEl = document.activeElement as HTMLElement | null;
    editingEl?.addEventListener('blur', onBlur, { once: true });
    document.addEventListener('visibilitychange', onHidden);
    window.addEventListener('pagehide', onPageHide, { once: true });
  }

  navigator.serviceWorker?.addEventListener('controllerchange', safeReload);

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', {
      scope: '/',
      updateViaCache: 'none',
    }).then((registration) => {
      const updateSW = async () => {
        try { await registration.update(); } catch { /* non-blocking */ }
      };

      // Periodically poll for SW updates so long-lived sessions eventually
      // pick up new builds. updateViaCache:'none' on the registration
      // guarantees this hits the network, not HTTP cache. When a new SW
      // installs, skipWaiting + clientsClaim activate it immediately and the
      // controllerchange listener above runs safeReload (deferred if editing).
      setInterval(updateSW, 5 * 60 * 1000);
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') updateSW();
      });
    }).catch(() => {
      // PWA registration is optional — app works without it.
    });
  }
}

// One-shot cleanup: strip the `?nexus_refresh=…` cache-bust query param that
// the controllerchange reload added. Cosmetic only — it would otherwise
// linger in the address bar across navigations and look ugly when shared.
// `history.replaceState` doesn't fire popstate or trigger React Router so
// it's a pure URL rewrite. Same treatment for `?nexus_recovered=` from the
// /api/sw-reset recovery shell.
(() => {
  try {
    const url = new URL(window.location.href);
    const had = url.searchParams.has('nexus_refresh') || url.searchParams.has('nexus_recovered');
    if (!had) return;
    url.searchParams.delete('nexus_refresh');
    url.searchParams.delete('nexus_recovered');
    const next = `${url.pathname}${url.search}${url.hash}`;
    window.history.replaceState(window.history.state, document.title, next);
  } catch {
    // Cosmetic-only — never block boot on this.
  }
})();

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <SentryErrorBoundary fallback={<ErrorFallback />}>
      <Suspense fallback={null}>
        <App />
      </Suspense>
    </SentryErrorBoundary>
  </StrictMode>
);

// Error fallback component
function ErrorFallback() {
  return (
    <div style={{
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      minHeight: '100vh',
      padding: '2rem',
      textAlign: 'center',
      backgroundColor: 'var(--color-background, #0f172a)',
      color: 'var(--color-text, #e2e8f0)',
    }}>
      <h1 style={{ fontSize: '2rem', marginBottom: '1rem' }}>
        {i18n.t('common:error_boundary.title')}
      </h1>
      <p style={{ marginBottom: '2rem', opacity: 0.8 }}>
        {i18n.t('common:error_boundary.description')}
      </p>
      <button
        onClick={() => window.location.reload()}
        style={{
          padding: '0.75rem 1.5rem',
          backgroundColor: 'var(--color-primary, #6366f1)',
          color: 'white',
          border: 'none',
          borderRadius: '0.5rem',
          fontSize: '1rem',
          cursor: 'pointer',
        }}
      >
        {i18n.t('common:error_boundary.try_again')}
      </button>
    </div>
  );
}
