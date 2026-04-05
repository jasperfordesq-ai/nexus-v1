// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Route Guard
 * Restricts access to broker + admin/super_admin users.
 * Redirects non-authorized users to the dashboard.
 */

import { Navigate, useLocation, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';

export function BrokerRoute() {
  const { t } = useTranslation('broker');
  const { user, isAuthenticated, isLoading, status } = useAuth();
  const { tenantPath } = useTenant();
  const location = useLocation();

  if (isLoading || status === 'loading') {
    return <LoadingScreen message={t('checking_permissions')} />;
  }

  if (!isAuthenticated) {
    return <Navigate to={tenantPath('/login')} state={{ from: tenantPath(location.pathname) }} replace />;
  }

  const role = (user?.role as string) || '';
  const userRecord = user as Record<string, unknown> | null;
  const hasBrokerAccess =
    role === 'broker' ||
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    userRecord?.is_admin === true ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  if (!hasBrokerAccess) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  return <Outlet />;
}

export default BrokerRoute;
