// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { User } from '@/types/api';

/** Minimal shape needed for role checks — accepts any user-like object. */
type AdminTierUser = Pick<
  User,
  'role' | 'is_admin' | 'is_super_admin' | 'is_tenant_super_admin' | 'is_god'
>;

/**
 * Admin-tier check — a user who can moderate/delete tenant content.
 *
 * MUST stay in sync with the backend
 * `app/Http/Controllers/Api/BaseApiController.php::callerIsAdminTier()`:
 * roles admin / tenant_admin / super_admin / god, OR any of the
 * is_admin / is_super_admin / is_tenant_super_admin / is_god flags.
 *
 * The frontend `User.role` union does not include `'god'`, so that role is
 * matched via a string cast (and the `is_god` flag catches it regardless).
 * Brokers are intentionally NOT admin-tier here — broker moderation lives in
 * the broker panel, mirroring the backend split.
 */
export function isAdminTier(user?: Partial<AdminTierUser> | null): boolean {
  if (!user) return false;

  const role = (user.role as string | undefined) ?? '';

  return (
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    role === 'god' ||
    user.is_admin === true ||
    user.is_super_admin === true ||
    user.is_tenant_super_admin === true ||
    user.is_god === true
  );
}
