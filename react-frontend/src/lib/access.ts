// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

type UserLike = {
  role?: unknown;
  is_admin?: unknown;
  is_super_admin?: unknown;
  is_tenant_super_admin?: unknown;
  is_god?: unknown;
} | null | undefined;

function userRole(user: UserLike): string {
  return typeof user?.role === 'string' ? user.role : '';
}

export function hasBrokerRole(user: UserLike): boolean {
  return userRole(user) === 'broker';
}

export function hasAdminPanelAccess(user: UserLike): boolean {
  if (hasBrokerRole(user)) return false;

  const role = userRole(user);
  return (
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    user?.is_admin === true ||
    user?.is_super_admin === true ||
    user?.is_tenant_super_admin === true ||
    user?.is_god === true
  );
}

export function hasBrokerPanelAccess(user: UserLike): boolean {
  const role = userRole(user);
  return (
    role === 'broker' ||
    role === 'coordinator' ||
    role === 'god' ||
    hasAdminPanelAccess(user)
  );
}
