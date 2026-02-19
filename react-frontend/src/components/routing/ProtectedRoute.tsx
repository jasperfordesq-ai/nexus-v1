// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
  const { isAuthenticated, isLoading, status, user } = useAuth();
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

  // Redirect to onboarding if not completed (skip if already on onboarding page)
  const pathSegments = location.pathname.replace(/\/+$/, '').split('/');
  const lastSegment = pathSegments[pathSegments.length - 1];
  if (user && user.onboarding_completed === false && lastSegment !== 'onboarding') {
    return <Navigate to={tenantPath('/onboarding')} replace />;
  }

  // Render children or outlet
  return children ? <>{children}</> : <Outlet />;
}

export default ProtectedRoute;
