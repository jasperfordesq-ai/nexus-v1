/**
 * Protected Route Component
 * Redirects to login if user is not authenticated
 */

import { Navigate, useLocation, Outlet } from 'react-router-dom';
import { useAuth } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';

interface ProtectedRouteProps {
  children?: React.ReactNode;
}

export function ProtectedRoute({ children }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading, status } = useAuth();
  const location = useLocation();

  // Show loading while checking auth status
  if (isLoading || status === 'loading') {
    return <LoadingScreen message="Checking authentication..." />;
  }

  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  }

  // Render children or outlet
  return children ? <>{children}</> : <Outlet />;
}

export default ProtectedRoute;
