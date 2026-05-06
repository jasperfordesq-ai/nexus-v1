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

// Register PWA service worker (production only — dev uses Vite HMR)
// Uses "prompt" mode — new SW is installed but NOT activated until the user
// explicitly accepts the update. This prevents mid-typing page reloads.
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

  // eslint-disable-next-line @typescript-eslint/ban-ts-comment
  // @ts-ignore — virtual:pwa-register is provided by vite-plugin-pwa
  import('virtual:pwa-register').then(({ registerSW }: { registerSW: (opts?: {
    immediate?: boolean;
    onNeedRefresh?: () => void;
    onOfflineReady?: () => void;
  }) => (reloadPage?: boolean) => void }) => {
    // Check if the user already triggered an update from this exact build.
    // Uses localStorage (not sessionStorage) + TTL so the suppression survives
    // mobile app kills where sessionStorage is wiped between background/foreground.
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

    const updateSW = registerSW({
      immediate: true,
      onNeedRefresh() {
        (window as NexusWindow).__nexus_updateSW = updateSW;
        if (updateAlreadyTriggered()) return; // Don't re-show banner
        (window as NexusWindow).__nexus_updatePending = true;
        window.dispatchEvent(new CustomEvent('nexus:sw_update_available'));
      },
      onOfflineReady() {
        console.info('[NEXUS] App ready for offline use');
      },
    });

    // Store updateSW globally so the banner can call it even if onNeedRefresh
    // never fires in this session (e.g. app was killed and reopened on mobile —
    // the waiting SW persists but onNeedRefresh does not re-fire).
    (window as NexusWindow).__nexus_updateSW = updateSW;

    // On boot, check directly if a waiting SW already exists from a previous session.
    // Skip if we already triggered an update from this build — avoids the
    // "click 4-5 times" loop where the old code keeps re-showing the banner.
    if (!updateAlreadyTriggered()) {
      navigator.serviceWorker.getRegistration().then((reg) => {
        if (reg?.waiting) {
          (window as NexusWindow).__nexus_updatePending = true;
          window.dispatchEvent(new CustomEvent('nexus:sw_update_available'));
        }
      }).catch(() => { /* non-blocking */ });
    }

    // Periodically check for SW updates (every 5 min) so long-lived sessions
    // (mobile PWA kept open for hours) eventually see the update banner.
    setInterval(() => {
      updateSW();
    }, 5 * 60 * 1000);

    // Check for updates whenever the app comes back to the foreground.
    // This is the primary fix for mobile: users switch apps constantly, and
    // the 5-min interval only fires while the app is active. visibilitychange
    // fires on every app switch, triggering registration.update() which does
    // the actual network fetch for a new SW version.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        updateSW();
      }
    });
  }).catch(() => {
    // PWA registration is optional � app works without it
  });
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
