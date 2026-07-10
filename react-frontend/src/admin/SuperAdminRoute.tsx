// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin Route Guard
 * Restricts access to super_admin/god users only.
 * Redirects non-super-admins to the regular admin dashboard.
 */

import { Navigate, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';
import { LoadingScreen } from '@/components/feedback';
import { isPlatformSuperAdminUser } from '@/lib/access';

export function SuperAdminRoute() {
  const { t } = useTranslation('super_admin');
  const { user, isLoading, status } = useAuth();
  const { tenantPath } = useTenant();

  if (isLoading || status === 'loading') {
    return <LoadingScreen message={t('layout.loading')} />;
  }

  if (!isPlatformSuperAdminUser(user)) {
    return <Navigate to={tenantPath('/admin')} replace />;
  }

  return <Outlet />;
}

export default SuperAdminRoute;
