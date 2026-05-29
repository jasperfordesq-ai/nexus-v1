// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Route Guard
 * Restricts access to admin/super_admin/god users only.
 * Redirects non-admins to the dashboard.
 */

import { Navigate, useLocation, Outlet } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';
import { hasAdminPanelAccess } from '@/lib/access';

export function AdminRoute() {
  const { user, isAuthenticated, isLoading, status } = useAuth();
  const { tenantPath } = useTenant();
  const location = useLocation();

  if (isLoading || status === 'loading') {
    return <LoadingScreen message={"Checking Permissions"} />;
  }

  if (!isAuthenticated) {
    return <Navigate to={tenantPath('/login')} state={{ from: tenantPath(location.pathname) }} replace />;
  }

  const isAdmin = hasAdminPanelAccess(user);

  if (!isAdmin) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  return <Outlet />;
}

export default AdminRoute;
