// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TenantFeatures } from '@/types/api';

export const CARING_COMMUNITY_ROUTE = {
  path: 'caring-community',
  href: '/caring-community',
  feature: 'caring_community' satisfies keyof TenantFeatures,
} as const;

export const CARING_COMMUNITY_ADMIN_ROUTE = {
  path: 'caring',
  href: '/caring',
  feature: 'caring_community' satisfies keyof TenantFeatures,
} as const;
