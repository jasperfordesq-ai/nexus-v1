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

export function AdminRoute() {
  const { user, isAuthenticated, isLoading, status } = useAuth();
  const { tenantPath } = useTenant();
  const location = useLocation();

  if (isLoading || status === 'loading') {
    return <LoadingScreen message="Checking permissions..." />;
  }

  if (!isAuthenticated) {
    return <Navigate to={tenantPath('/login')} state={{ from: location.pathname }} replace />;
  }

  // The User type only defines role as 'member' | 'admin' | 'moderator',
  // but the backend may return 'tenant_admin' or 'super_admin' at runtime.
  // We cast to string for safe comparison.
  const role = (user?.role as string) || '';
  const userRecord = user as Record<string, unknown> | null;
  const isAdmin =
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    userRecord?.is_admin === true ||
    userRecord?.is_super_admin === true;

  if (!isAdmin) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  return <Outlet />;
}

export default AdminRoute;
