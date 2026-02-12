/**
 * Protected Route Component
 * Redirects to login if user is not authenticated.
 * Preserves tenant slug prefix in the redirect URL.
 */

import { Navigate, useLocation, Outlet } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';

interface ProtectedRouteProps {
  children?: React.ReactNode;
}

export function ProtectedRoute({ children }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading, status } = useAuth();
  const { tenantPath } = useTenant();
  const location = useLocation();

  // Show loading while checking auth status
  if (isLoading || status === 'loading') {
    return <LoadingScreen message="Checking authentication..." />;
  }

  // Redirect to login if not authenticated, preserving tenant slug prefix
  if (!isAuthenticated) {
    return <Navigate to={tenantPath('/login')} state={{ from: location.pathname }} replace />;
  }

  // Render children or outlet
  return children ? <>{children}</> : <Outlet />;
}

export default ProtectedRoute;
