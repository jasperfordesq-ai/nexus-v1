// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { StrictMode, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';
import './i18n'; // Initialize i18n before App renders

// Log build version to console for deployment verification
console.info(`[NEXUS] Build: ${__BUILD_COMMIT__} | ${__BUILD_TIME__}`);

// Initialize Sentry error tracking (before React renders)
import { initSentry, SentryErrorBoundary } from '@/lib/sentry';
initSentry();

// Register PWA service worker (production only — dev uses Vite HMR)
// Uses "prompt" mode — new SW is installed but NOT activated until the user
// explicitly accepts the update. This prevents mid-typing page reloads.
if (import.meta.env.PROD) {
  // eslint-disable-next-line @typescript-eslint/ban-ts-comment
  // @ts-ignore — virtual:pwa-register is provided by vite-plugin-pwa at build time
  import('virtual:pwa-register').then(({ registerSW }: { registerSW: (opts?: {
    immediate?: boolean;
    onNeedRefresh?: () => void;
    onOfflineReady?: () => void;
  }) => (reloadPage?: boolean) => void }) => {
    const updateSW = registerSW({
      immediate: true,
      onNeedRefresh() {
        // Store the updateSW function BEFORE dispatching the event —
        // if a listener calls handleUpdate() synchronously, updateSW must exist.
        (window as NexusWindow).__nexus_updateSW = updateSW;
        // Set a flag so the banner can detect updates that fired before React mounted
        (window as NexusWindow).__nexus_updatePending = true;
        // Dispatch a custom event so the React app can show an update banner.
        // The banner lets the user choose when to reload — never interrupt them.
        window.dispatchEvent(new CustomEvent('nexus:sw_update_available'));
      },
      onOfflineReady() {
        console.info('[NEXUS] App ready for offline use');
      },
    });

    // Periodically check for SW updates (every 30 min) so long-lived sessions
    // (mobile PWA kept open for hours) eventually see the update banner.
    setInterval(() => {
      updateSW();
    }, 30 * 60 * 1000);
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
// Note: This renders BEFORE i18n loads — intentionally not translated
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
      backgroundColor: '#0f172a',
      color: '#e2e8f0',
    }}>
      <h1 style={{ fontSize: '2rem', marginBottom: '1rem' }}>Something went wrong</h1>
      <p style={{ marginBottom: '2rem', opacity: 0.8 }}>
        We've been notified and are looking into it. Please try refreshing the page.
      </p>
      <button
        onClick={() => window.location.reload()}
        style={{
          padding: '0.75rem 1.5rem',
          backgroundColor: '#6366f1',
          color: 'white',
          border: 'none',
          borderRadius: '0.5rem',
          fontSize: '1rem',
          cursor: 'pointer',
        }}
      >
        Reload Page
      </button>
    </div>
  );
}
