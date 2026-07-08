// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS React Frontend - Main App Component
 *
 * Routes structure:
 * - All routes work at both / and /:tenantSlug/ prefix (Phase 0-1 TRS-001)
 * - TenantShell provides TenantProvider + AuthProvider per route group
 * - Public routes (no auth required)
 * - Protected routes (auth required)
 * - Feature-gated routes (based on tenant config)
 *
 * @see docs/TRS-001-TENANT-RESOLUTION-SPEC.md
 */

import { Suspense } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { HelmetProvider } from 'react-helmet-async';

// Contexts (app-wide only — tenant-scoped contexts are inside TenantShell)
import { ToastProvider } from '@/contexts/ToastContext';
import { ConfirmDialogProvider } from '@/components/ui/ConfirmDialog';
import { ThemeProvider } from '@/contexts/ThemeContext';
import { CookieConsentProvider } from '@/contexts/CookieConsentContext';

// Layout Components
import { ScrollToTop } from '@/components/routing/ScrollToTop';
import { TenantShell } from '@/components/routing/TenantShell';
import { LoadingScreen } from '@/components/feedback/LoadingScreen';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
function App() {
  return (
    <HelmetProvider>
      <ThemeProvider>
        <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <ScrollToTop />
          <CookieConsentProvider>
            <ToastProvider>
              <ErrorBoundary>
                <ConfirmDialogProvider>
                <Suspense fallback={<LoadingScreen />}>
                  <Routes>
                    {/* Single catch-all route â€” TenantShell detects tenant slug from
                        the first path segment (if it's not reserved like "admin").
                        When a slug IS found, TenantShell renders a nested <Routes>
                        with the slug stripped so child routes match correctly.
                        This avoids the `:tenantSlug/*` dynamic param route which caused
                        React Router v6 to rank `/:tenantSlug/listings` higher than
                        `/admin/*` (splat routes rank lowest in RRv6). */}
                    <Route path="/*" element={<TenantShell />} />
                  </Routes>
                </Suspense>
                </ConfirmDialogProvider>
              </ErrorBoundary>
            </ToastProvider>
          </CookieConsentProvider>
        </BrowserRouter>
      </ThemeProvider>
    </HelmetProvider>
  );
}

export default App;
