// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import { CaringRoute } from './CaringRoute';
import { CaringLayout } from './CaringLayout';
import { CaringRoutes } from './routes';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { LoadingScreen } from '@/components/feedback';

function CaringAppInner() {
  return (
    <Routes>
      <Route element={<CaringRoute />}>
        <Route element={<CaringLayout />}>
          {CaringRoutes()}
        </Route>
      </Route>
    </Routes>
  );
}

export default function CaringApp() {
  return (
    <ErrorBoundary>
      <Suspense fallback={<LoadingScreen />}>
        <CaringAppInner />
      </Suspense>
    </ErrorBoundary>
  );
}
