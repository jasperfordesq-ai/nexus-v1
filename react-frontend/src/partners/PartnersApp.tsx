// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks App Entry Point (lazy-loaded)
 *
 * Bundles the super-admin Partner Timebanks panel — route guard, layout,
 * and all sub-routes — into a single code-split chunk. Loaded via
 * React.lazy() in App.tsx so the panel stays out of the main bundle.
 */

import { Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { PartnersRoute } from './PartnersRoute';
import { PartnersLayout } from './PartnersLayout';
import { PartnersRoutes } from './routes';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { LoadingScreen } from '@/components/feedback';

function PartnersAppInner() {
  // Ensure all namespaces used inside the panel are loaded before rendering.
  // Without this, useTranslation() in child components may render raw keys on
  // first paint because HttpBackend loads namespaces asynchronously. The
  // 'admin' namespace is needed because the embedded federation admin pages
  // use admin-side keys; 'caring_community' backs the embedded
  // FederationPeersAdminPage.
  const { ready } = useTranslation(['partners', 'admin', 'caring_community']);

  if (!ready) {
    return <LoadingScreen />;
  }

  return (
    <Routes>
      <Route element={<PartnersRoute />}>
        <Route element={<PartnersLayout />}>
          {PartnersRoutes()}
        </Route>
      </Route>
    </Routes>
  );
}

export default function PartnersApp() {
  return (
    <ErrorBoundary>
      <Suspense fallback={<LoadingScreen />}>
        <PartnersAppInner />
      </Suspense>
    </ErrorBoundary>
  );
}
