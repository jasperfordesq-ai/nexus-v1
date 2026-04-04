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

import { Routes, Route } from 'react-router-dom';
import { BrokerRoute } from './BrokerRoute';
import { BrokerLayout } from './BrokerLayout';
import { BrokerRoutes } from './routes';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';

export default function BrokerApp() {
  return (
    <ErrorBoundary>
      <Routes>
        <Route element={<BrokerRoute />}>
          <Route element={<BrokerLayout />}>
            {BrokerRoutes()}
          </Route>
        </Route>
      </Routes>
    </ErrorBoundary>
  );
}
