// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin App Entry Point (lazy-loaded)
 *
 * Keeps platform-wide super-admin operations in their own route area and
 * bundle, separate from the tenant admin panel.
 */

import { Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { LoadingScreen } from '@/components/feedback';
import { SuperAdminRoute } from '@/admin/SuperAdminRoute';
import { SuperAdminLayout } from './SuperAdminLayout';
import { SuperAdminRoutes } from './SuperAdminRoutes';

function SuperAdminAppInner() {
  const { ready, t } = useTranslation(['super_admin', 'admin', 'admin_nav']);

  if (!ready) {
    return <LoadingScreen message={t('super_admin:layout.loading')} />;
  }

  return (
    <Routes>
      <Route element={<SuperAdminRoute />}>
        <Route element={<SuperAdminLayout />}>
          {SuperAdminRoutes()}
        </Route>
      </Route>
    </Routes>
  );
}

export default function SuperAdminApp() {
  return (
    <ErrorBoundary>
      <Suspense fallback={<LoadingScreen />}>
        <SuperAdminAppInner />
      </Suspense>
    </ErrorBoundary>
  );
}
