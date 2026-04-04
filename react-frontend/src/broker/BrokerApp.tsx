// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker App Entry Point (lazy-loaded)
 *
 * This component bundles the broker panel — route guard, layout, and all
 * sub-routes — into a single code-split chunk. Loaded via React.lazy()
 * in App.tsx so the broker UI stays out of the main application bundle.
 */

import { Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { BrokerRoute } from './BrokerRoute';
import { BrokerLayout } from './BrokerLayout';
import { BrokerRoutes } from './routes';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { LoadingScreen } from '@/components/feedback';

function BrokerAppInner() {
  // Ensure 'broker' namespace is loaded before rendering any broker components.
  // Without this, useTranslation('broker') in child components may render raw
  // keys on first paint because HttpBackend loads namespaces asynchronously.
  const { ready } = useTranslation('broker');

  if (!ready) {
    return <LoadingScreen />;
  }

  return (
    <Routes>
      <Route element={<BrokerRoute />}>
        <Route element={<BrokerLayout />}>
          {BrokerRoutes()}
        </Route>
      </Route>
    </Routes>
  );
}

export default function BrokerApp() {
  return (
    <ErrorBoundary>
      <Suspense fallback={<LoadingScreen />}>
        <BrokerAppInner />
      </Suspense>
    </ErrorBoundary>
  );
}
