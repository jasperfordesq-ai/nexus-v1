// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks Route Guard
 * Restricts access to super admins only (the same gate as the admin
 * Communications sections). Normal admins and tenant admins are bounced
 * to the admin dashboard; unauthenticated users go to login.
 */

import { Navigate, useLocation, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';
import { hasPartnerPanelAccess } from '@/lib/access';

export function PartnersRoute() {
  const { t } = useTranslation('partners');
  const { user, isAuthenticated, isLoading, status } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const location = useLocation();

  if (isLoading || status === 'loading') {
    return <LoadingScreen message={t('checking_permissions')} />;
  }

  if (!isAuthenticated) {
    return <Navigate to={tenantPath('/login')} state={{ from: tenantPath(location.pathname) }} replace />;
  }

  // Super admins only — external-partner setup is deliberately hidden from
  // normal admins and tenant admins (see hasPartnerPanelAccess).
  if (!hasPartnerPanelAccess(user)) {
    return <Navigate to={tenantPath('/admin')} replace />;
  }

  // The panel is pointless on a tenant with none of the partnering surfaces
  // enabled; bounce back to admin rather than showing an empty shell.
  if (!hasFeature('federation') && !hasFeature('partner_api') && !hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/admin')} replace />;
  }

  return <Outlet />;
}

export default PartnersRoute;
