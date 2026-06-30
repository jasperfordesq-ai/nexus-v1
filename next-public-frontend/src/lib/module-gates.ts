// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TenantBootstrap } from './tenant-api';
import type { RouteOwnership } from './public-routes';

const routeModuleKeys: Record<string, string> = {
  listingDetail: 'listings',
  listings: 'listings',
};

export function isFeatureEnabled(tenant: TenantBootstrap | null, key: string): boolean {
  return normalizeBoolean(tenant?.features?.[key]);
}

export function isModuleEnabled(tenant: TenantBootstrap | null, key: string): boolean {
  return normalizeBoolean(tenant?.modules?.[key] ?? tenant?.features?.[key]);
}

export function isRouteEnabledForTenant(route: RouteOwnership, tenant: TenantBootstrap | null): boolean {
  const moduleKey = routeModuleKeys[route.routeKey];

  if (!moduleKey) {
    return true;
  }

  return isModuleEnabled(tenant, moduleKey);
}

function normalizeBoolean(value: unknown): boolean {
  if (value === undefined || value === null) {
    return false;
  }

  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value > 0;
  }

  if (typeof value === 'string') {
    return ['1', 'enabled', 'true', 'yes'].includes(value.toLowerCase());
  }

  return Boolean(value);
}
