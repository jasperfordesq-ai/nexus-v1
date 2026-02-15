/**
 * Super Admin Route Guard
 * Restricts access to super_admin/god users only.
 * Redirects non-super-admins to the regular admin dashboard.
 */

import { Navigate, Outlet } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';

export function SuperAdminRoute() {
  const { user, isLoading, status } = useAuth();
  const { tenantPath } = useTenant();

  if (isLoading || status === 'loading') {
    return <LoadingScreen message="Checking permissions..." />;
  }

  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true;

  if (!isSuperAdmin) {
    return <Navigate to={tenantPath('/admin')} replace />;
  }

  return <Outlet />;
}

export default SuperAdminRoute;
