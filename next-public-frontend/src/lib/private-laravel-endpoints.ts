// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export const privateLaravelV2EndpointPrefixes = [
  '/v2/admin',
  '/v2/auth',
  '/v2/broker',
  '/v2/coupons',
  '/v2/dashboard',
  '/v2/feed',
  '/v2/messages',
  '/v2/notifications',
  '/v2/settings',
  '/v2/super-admin',
  '/v2/wallet',
];

export function isPrivateLaravelV2Endpoint(endpoint: string): boolean {
  return privateLaravelV2EndpointPrefixes.some((prefix) => (
    endpoint === prefix || endpoint.startsWith(`${prefix}/`)
  ));
}
