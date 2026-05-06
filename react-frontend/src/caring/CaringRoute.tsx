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
  const hasFullCaringAccess =
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    role === 'god' ||
    userRecord?.is_admin === true ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true ||
    userRecord?.is_god === true;
  const hasSafeguardingAccess = hasFullCaringAccess || role === 'coordinator' || role === 'broker';

  if (!hasSafeguardingAccess) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  if (!hasFullCaringAccess && !hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  if (!hasFullCaringAccess) {
    const safeguardingPath = tenantPath('/caring/safeguarding');
    if (!location.pathname.startsWith(safeguardingPath)) {
      return <Navigate to={safeguardingPath} replace />;
    }
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
