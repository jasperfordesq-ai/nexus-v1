// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Navigate, useLocation, Outlet } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';
import { hasFullCaringAccess, hasSafeguardingAccess } from './access';

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

  const fullAccess = hasFullCaringAccess(user);
  const safeguardingAccess = hasSafeguardingAccess(user);

  if (!safeguardingAccess) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  if (!fullAccess && !hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  if (!fullAccess) {
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
