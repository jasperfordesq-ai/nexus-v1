// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import routeOwnershipManifest from '../../../route-ownership.json';
import { createTranslator } from '../../lib/i18n';
import { buildCanonicalUrl } from '../../lib/metadata';
import { getRouteOwnership } from '../../lib/public-routes';
import type { TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

const tenant: TenantBootstrap = {
  default_language: 'en',
  id: 2,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  tagline: 'Neighbour-powered support',
};

describe('public route no-JavaScript HTML', () => {
  it('renders tenant-slug home routes as crawler-readable HTML', () => {
    const routeSegments: string[] = [];
    const route = getRouteOwnership(routeSegments);
    const canonicalUrl = buildCanonicalUrl({
      origin: 'https://app.project-nexus.ie',
      routeSegments,
      tenantMode: 'path',
      tenantSlug: tenant.slug,
    });

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl={canonicalUrl}
        route={route}
        routeSegments={routeSegments}
        tenant={tenant}
        tenantBasePath={`/${tenant.slug}`}
        t={createTranslator('en')}
      />,
    );

    expect(route).toMatchObject({ owner: 'next-public', routeKey: 'home' });
    expect(canonicalUrl).toBe('https://app.project-nexus.ie/hour-timebank');
    expect(html).toContain('Hour Timebank');
    expect(html).toContain('Neighbour-powered support');
    expect(html).toContain('href="/hour-timebank/about"');
    expect(html).toContain('href="/hour-timebank/contact"');
    expect(html).toContain('AGPL-3.0-or-later');
    expect(html).toContain(canonicalUrl);
    expect(html).toContain('type="application/ld+json"');
    expect(html).toContain('<h1>');
  });

  it('renders every shadow-owned public route as crawler-readable HTML', () => {
    for (const manifestRoute of routeOwnershipManifest.nextPublicRoutes) {
      const routeSegments = sampleSegments(manifestRoute.pattern);
      const route = getRouteOwnership(routeSegments);
      const canonicalUrl = buildCanonicalUrl({
        origin: 'https://app.project-nexus.ie',
        routeSegments,
        tenantMode: 'path',
        tenantSlug: tenant.slug,
      });

      const html = renderToStaticMarkup(
        <PublicPage
          canonicalUrl={canonicalUrl}
          route={route}
          routeSegments={routeSegments}
          tenant={tenant}
          tenantBasePath={`/${tenant.slug}`}
          t={createTranslator('en')}
        />,
      );

      expect(route.owner, manifestRoute.pattern).toBe('next-public');
      expect(html, manifestRoute.pattern).toContain('Hour Timebank');
      expect(html, manifestRoute.pattern).toContain('AGPL-3.0-or-later');
      expect(html, manifestRoute.pattern).toContain(canonicalUrl);
      expect(html, manifestRoute.pattern).toContain('type="application/ld+json"');
      expect(html, manifestRoute.pattern).toContain('<h1>');
    }
  });
});

function sampleSegments(pattern: string): string[] {
  const sampleValues: Record<string, string> = {
    id: 'sample-public-item',
    slug: 'sample-public-page',
  };

  return pattern
    .split('/')
    .filter(Boolean)
    .map((segment) => (segment.startsWith(':') ? sampleValues[segment.slice(1)] ?? 'sample' : segment));
}
