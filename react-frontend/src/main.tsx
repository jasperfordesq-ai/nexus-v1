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
import { initSentry, SentryErrorBoundary } from '@/lib/sentry';
initSentry();

// Register PWA service worker (production only — dev uses Vite HMR).
//
// We register manually instead of using vite-plugin-pwa's `registerSW()` so we
// can pass `updateViaCache: 'none'`. With the default `'imports'`, mobile Chrome
// is allowed to serve `sw-rescue.js` (loaded via importScripts) from its HTTP
// cache — the byte-for-byte comparison then sees no change and no waiting
// worker is ever installed. `'none'` bypasses the HTTP cache for both the
// top-level SW script and all imported scripts. See:
// https://developer.chrome.com/blog/fresher-sw
//
// The actual page reload after a successful update fires from the
// `controllerchange` listener below — NOT from the banner's click handler.
// `controllerchange` is the only event that mathematically guarantees the new
// SW has assumed control. Reloading from a `finally` block in the click
// handler used to fire while the old SW was still in control (because
// skipWaiting() can deadlock on active fetches like Pusher's WebSocket on
// Android Chrome), serving stale code on the next nav and looping the banner.
if (import.meta.env.PROD) {
  navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data?.type !== 'NEXUS_SW_RESCUE_RELOAD_REQUIRED') return;
    const url = typeof event.data.url === 'string' ? event.data.url : window.location.href;
    try {
      window.location.replace(url);
    } catch {
      window.location.href = url;
    }
  });

  let _nexusRefreshing = false;
  navigator.serviceWorker?.addEventListener('controllerchange', () => {
    if (_nexusRefreshing) return;
    _nexusRefreshing = true;
    const url = new URL(window.location.href);
    url.searchParams.set('nexus_refresh', String(Date.now()));
    try {
      window.location.replace(url.href);
    } catch {
      window.location.href = url.href;
    }
  });

  if ('serviceWorker' in navigator) {
    const UPDATE_COMMIT_KEY = 'nexus_sw_update_from_commit';
    const UPDATE_TTL = 10 * 60 * 1000; // 10 minutes — matches UpdateAvailableBanner
    const updateAlreadyTriggered = () => {
      try {
        const raw = localStorage.getItem(UPDATE_COMMIT_KEY);
        if (!raw) return false;
        const colonIdx = raw.lastIndexOf(':');
        const commit = raw.slice(0, colonIdx);
        const ts = parseInt(raw.slice(colonIdx + 1), 10);
        if (commit !== __BUILD_COMMIT__) {
          localStorage.removeItem(UPDATE_COMMIT_KEY);
          return false;
        }
        return Date.now() - ts < UPDATE_TTL;
      } catch {
        return false;
      }
    };

    const fireUpdateAvailable = () => {
      if (updateAlreadyTriggered()) return;
      (window as NexusWindow).__nexus_updatePending = true;
      window.dispatchEvent(new CustomEvent('nexus:sw_update_available'));
    };

    navigator.serviceWorker.register('/sw.js', {
      scope: '/',
      updateViaCache: 'none',
    }).then((registration) => {
      const updateSW = async () => {
        try {
          await registration.update();
        } catch {
          // non-blocking
        }
      };
      (window as NexusWindow).__nexus_updateSW = updateSW;

      // If a waiting SW already exists from a prior session (mobile "killed
      // and reopened" case), surface the banner now.
      if (registration.waiting && registration.active) {
        fireUpdateAvailable();
      }

      // Watch for new SWs entering the 'installed' state while there's an
      // active controller — that's the "update available" condition.
      registration.addEventListener('updatefound', () => {
        const installing = registration.installing;
        if (!installing) return;
        installing.addEventListener('statechange', () => {
          if (installing.state === 'installed' && registration.active) {
            fireUpdateAvailable();
          }
        });
      });

      // Periodically poll for SW updates so long-lived sessions eventually
      // see the banner without needing a reload. updateViaCache:'none' on
      // the registration guarantees this hits the network, not HTTP cache.
      setInterval(updateSW, 5 * 60 * 1000);

      // Update on every visibility change (mobile app-switch).
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          updateSW();
        }
      });
    }).catch(() => {
      // PWA registration is optional — app works without it
    });
  }
}

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
