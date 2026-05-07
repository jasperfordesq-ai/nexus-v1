// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

type CaringUser = {
  role?: unknown;
  permissions?: unknown;
  admin_permissions?: unknown;
  capabilities?: unknown;
  is_admin?: unknown;
  is_super_admin?: unknown;
  is_tenant_super_admin?: unknown;
  is_god?: unknown;
  is_view_only?: unknown;
  is_read_only?: unknown;
  view_only?: unknown;
  read_only?: unknown;
} | null | undefined;

const FULL_ACCESS_ROLES = new Set(['admin', 'tenant_admin', 'super_admin', 'god']);
const SAFEGUARDING_ROLES = new Set(['coordinator', 'broker']);
const VIEW_ONLY_ROLES = new Set(['view_only', 'read_only', 'viewer', 'admin_viewer']);

function normaliseRole(user: CaringUser): string {
  return typeof user?.role === 'string' ? user.role : '';
}

function hasToken(value: unknown, tokens: Set<string>): boolean {
  if (typeof value === 'string') {
    return tokens.has(value);
  }

  if (Array.isArray(value)) {
    return value.some((entry) => typeof entry === 'string' && tokens.has(entry));
  }

  return false;
}

export function isCaringViewOnly(user: CaringUser): boolean {
  if (!user) {
    return false;
  }

  return (
    VIEW_ONLY_ROLES.has(normaliseRole(user)) ||
    user.is_view_only === true ||
    user.is_read_only === true ||
    user.view_only === true ||
    user.read_only === true ||
    hasToken(user.permissions, VIEW_ONLY_ROLES) ||
    hasToken(user.admin_permissions, VIEW_ONLY_ROLES) ||
    hasToken(user.capabilities, VIEW_ONLY_ROLES)
  );
}

export function hasFullCaringAccess(user: CaringUser): boolean {
  if (!user) {
    return false;
  }

  return (
    FULL_ACCESS_ROLES.has(normaliseRole(user)) ||
    user.is_admin === true ||
    user.is_super_admin === true ||
    user.is_tenant_super_admin === true ||
    user.is_god === true
  );
}

export function hasSafeguardingAccess(user: CaringUser): boolean {
  return hasFullCaringAccess(user) || SAFEGUARDING_ROLES.has(normaliseRole(user));
}

export function canManageCaring(user: CaringUser): boolean {
  return hasFullCaringAccess(user) && !isCaringViewOnly(user);
}
