// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { isRouteEnabledForTenant } from '../module-gates';
import type { RouteOwnership } from '../public-routes';
import type { TenantBootstrap } from '../tenant-api';

const listingsRoute: RouteOwnership = {
  labelKey: 'pages.listings.title',
  owner: 'next-public',
  pattern: '/listings',
  routeKey: 'listings',
};

const eventsRoute: RouteOwnership = {
  labelKey: 'pages.events.title',
  owner: 'next-public',
  pattern: '/events',
  routeKey: 'events',
};

describe('module route gates', () => {
  it('keeps the Next listings route unavailable when the tenant listings module is disabled', () => {
    const tenant: TenantBootstrap = {
      default_language: 'en',
      id: 2,
      modules: {
        listings: false,
      },
      name: 'Hour Timebank',
      slug: 'hour-timebank',
    };

    expect(isRouteEnabledForTenant(listingsRoute, tenant)).toBe(false);
  });

  it('allows routes without a module owner to use the existing shadow renderer', () => {
    const tenant: TenantBootstrap = {
      default_language: 'en',
      id: 2,
      modules: {
        listings: false,
      },
      name: 'Hour Timebank',
      slug: 'hour-timebank',
    };

    expect(
      isRouteEnabledForTenant(
        {
          labelKey: 'pages.about.title',
          owner: 'next-public',
          pattern: '/about',
          routeKey: 'about',
        },
        tenant,
      ),
    ).toBe(true);
  });

  it('keeps the Next events route unavailable when the tenant events module is disabled', () => {
    const tenant: TenantBootstrap = {
      default_language: 'en',
      id: 2,
      modules: {
        events: false,
      },
      name: 'Hour Timebank',
      slug: 'hour-timebank',
    };

    expect(isRouteEnabledForTenant(eventsRoute, tenant)).toBe(false);
  });
});
