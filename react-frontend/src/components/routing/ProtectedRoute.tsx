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
import { useLegalGate } from '@/hooks/useLegalGate';
import { LegalAcceptanceGate } from '@/components/legal/LegalAcceptanceGate';

interface ProtectedRouteProps {
  children?: React.ReactNode;
}

/** Path segments that must never be blocked by the legal gate */
const LEGAL_GATE_BYPASS_SEGMENTS = new Set([
  'terms', 'privacy', 'cookies', 'accessibility',
  'community-guidelines', 'acceptable-use',
  'legal', 'onboarding', 'platform',
]);

export function ProtectedRoute({ children }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading, status, user } = useAuth();
  const { tenantPath } = useTenant();
  const location = useLocation();
  const { hasPending, pendingDocs, acceptAll, isAccepting, isLoading: legalLoading } = useLegalGate();

  // Show loading while checking auth status
  if (isLoading || status === 'loading') {
    return <LoadingScreen message="Checking authentication..." />;
  }

  // Redirect to login if not authenticated, preserving tenant slug prefix
  if (!isAuthenticated) {
    return <Navigate to={tenantPath('/login')} state={{ from: location.pathname }} replace />;
  }

  // Redirect to onboarding only if the flag is not set.
  // onboarding_completed is the authoritative source of truth — the backend
  // already requires avatar+bio before it will set this flag, so we do not
  // need to re-check those fields here. Doing so caused a regression where
  // users who edited their profile (removed avatar or bio) were permanently
  // sent back to onboarding on every login.
  const pathSegments = location.pathname.replace(/\/+$/, '').split('/');
  const lastSegment = pathSegments[pathSegments.length - 1];
  const needsOnboarding = user && !user.onboarding_completed;
  if (needsOnboarding && lastSegment !== 'onboarding') {
    return <Navigate to={tenantPath('/onboarding')} replace />;
  }

  // Legal gate: block protected pages when user has unaccepted legal documents.
  // Skip the gate on legal/onboarding pages so users can read docs before accepting.
  const isLegalBypassPath = LEGAL_GATE_BYPASS_SEGMENTS.has(lastSegment);
  const showLegalGate = !legalLoading && hasPending && !isLegalBypassPath;

  return (
    <>
      {showLegalGate && (
        <LegalAcceptanceGate
          pendingDocs={pendingDocs}
          onAcceptAll={acceptAll}
          isAccepting={isAccepting}
        />
      )}
      {children ? <>{children}</> : <Outlet />}
    </>
  );
}

export default ProtectedRoute;
