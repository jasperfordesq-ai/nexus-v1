// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';

// Log build version to console for deployment verification
console.info(`[NEXUS] Build: ${__BUILD_COMMIT__} | ${__BUILD_TIME__}`);

// Initialize Sentry error tracking (before React renders)
import { initSentry, SentryErrorBoundary } from '@/lib/sentry';
initSentry();

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <SentryErrorBoundary fallback={<ErrorFallback />}>
      <App />
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
