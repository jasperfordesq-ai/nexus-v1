// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Navigate, useLocation, Outlet } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';

export function CaringRoute() {
  const { user, isAuthenticated, isLoading, status } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const location = useLocation();

  if (isLoading || status === 'loading') {
    return <LoadingScreen />;
  }

  if (!isAuthenticated) {
    return <Navigate to={tenantPath('/login')} state={{ from: tenantPath(location.pathname) }} replace />;
  }

  const role = (user?.role as string) || '';
  const userRecord = user as Record<string, unknown> | null;
  const hasAdminAccess =
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    role === 'god' ||
    role === 'coordinator' ||
    role === 'broker' ||
    userRecord?.is_admin === true ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true ||
    userRecord?.is_god === true;

  if (!hasAdminAccess) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  if (!hasFeature('caring_community')) {
    const caringPath = tenantPath('/caring');
    const isCaringOverview =
      location.pathname === caringPath ||
      location.pathname === `${caringPath}/`;

    if (!isCaringOverview) {
      return <Navigate to={tenantPath('/caring')} replace />;
    }
  }

  return <Outlet />;
}

export default CaringRoute;
