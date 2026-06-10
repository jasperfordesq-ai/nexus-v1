// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useIdleLogout } from '@/hooks/useIdleLogout';

/**
 * Renders nothing; activates the tenant-configurable inactivity auto-logout
 * for the authenticated session. Must be mounted inside TenantProvider and
 * AuthProvider (see TenantShell).
 */
export function IdleLogoutGuard(): null {
  useIdleLogout();
  return null;
}
