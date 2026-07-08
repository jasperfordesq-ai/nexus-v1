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
import { LoadingScreen } from '@/components/feedback/LoadingScreen';

function PartnersAppInner() {
  // Keep the partners shell scoped to its own namespace. Embedded admin/caring
  // pages lazy-load their own larger namespaces when their routes render.
  // Without this, useTranslation() in child components may render raw keys on
  // first paint because HttpBackend loads namespaces asynchronously.
  const { ready } = useTranslation('partners');

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
