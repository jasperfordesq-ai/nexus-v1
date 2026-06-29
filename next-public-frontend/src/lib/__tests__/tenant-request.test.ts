// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildTenantBootstrapRequest, resolveTenantRequest } from '../tenant-request';

describe('tenant request resolution', () => {
  it('strips a tenant slug on the shared app host and passes it to Laravel bootstrap', () => {
    const request = resolveTenantRequest(['hour-timebank', 'about'], {
      host: 'app.project-nexus.ie',
      protocol: 'https',
    });

    expect(request).toEqual({
      host: 'app.project-nexus.ie',
      origin: 'https://app.project-nexus.ie',
      routeSegments: ['about'],
      tenantMode: 'path',
      tenantSlug: 'hour-timebank',
    });

    expect(
      buildTenantBootstrapRequest({
        apiBase: 'https://api.project-nexus.ie/api',
        origin: request.origin,
        tenantSlug: request.tenantSlug,
      }),
    ).toEqual({
      headers: {
        Accept: 'application/json',
        Origin: 'https://app.project-nexus.ie',
      },
      url: 'https://api.project-nexus.ie/api/v2/tenant/bootstrap?slug=hour-timebank',
    });
  });

  it('keeps custom-domain paths intact and forwards Origin for host-based tenant lookup', () => {
    const request = resolveTenantRequest(['about'], {
      host: 'community.example',
      protocol: 'https',
    });

    expect(request).toEqual({
      host: 'community.example',
      origin: 'https://community.example',
      routeSegments: ['about'],
      tenantMode: 'host',
      tenantSlug: undefined,
    });

    expect(
      buildTenantBootstrapRequest({
        apiBase: 'https://api.project-nexus.ie/api/',
        origin: request.origin,
        tenantSlug: request.tenantSlug,
      }),
    ).toEqual({
      headers: {
        Accept: 'application/json',
        Origin: 'https://community.example',
      },
      url: 'https://api.project-nexus.ie/api/v2/tenant/bootstrap',
    });
  });

  it('uses http origins for local shared-host development', () => {
    expect(
      resolveTenantRequest(['hour-timebank'], {
        host: '127.0.0.1:5175',
        protocol: 'http',
      }),
    ).toMatchObject({
      origin: 'http://127.0.0.1:5175',
      tenantMode: 'path',
      tenantSlug: 'hour-timebank',
    });
  });

  it('does not mistake reserved private route prefixes for tenant slugs on shared hosts', () => {
    for (const prefix of [
      'caring-community',
      'courses',
      'dashboard',
      'federation',
      'group-exchanges',
      'auth',
      'login',
      'onboarding',
      'password',
      'premium',
      'register',
      'verify-email',
      'verify-identity',
      'verify-identity-optional',
      'volunteering',
      'advertise',
      'developers',
      'explore',
      'ideation',
      'join',
      'municipality-calendar',
      'newsletter',
      'partner-analytics',
      'pilot-apply',
      'pilot-inquiry',
      'regional-analytics',
    ]) {
      expect(
        resolveTenantRequest([prefix], {
          host: 'app.project-nexus.ie',
          protocol: 'https',
        }),
      ).toEqual({
        host: 'app.project-nexus.ie',
        origin: 'https://app.project-nexus.ie',
        routeSegments: [prefix],
        tenantMode: 'host',
        tenantSlug: undefined,
      });
    }
  });

  it('does not throw on malformed encoded path segments', () => {
    expect(() => {
      resolveTenantRequest(['%E0%A4%A', 'about'], {
        host: 'app.project-nexus.ie',
        protocol: 'https',
      });
    }).not.toThrow();
  });
});
