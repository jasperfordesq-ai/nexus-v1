// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { createTranslator } from '../../lib/i18n';
import type { RouteOwnership } from '../../lib/public-routes';
import type { TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

describe('PublicPage', () => {
  it('renders crawler-readable tenant HTML with attribution and public navigation', () => {
    const tenant: TenantBootstrap = {
      branding: {
        logo_url: 'https://cdn.example/logo.png',
        primary_color: '#146c94',
      },
      default_language: 'en',
      id: 2,
      name: 'Hour Timebank',
      slug: 'hour-timebank',
      tagline: 'Neighbour-powered support',
    };
    const route: RouteOwnership = {
      labelKey: 'pages.about.title',
      owner: 'next-public',
      pattern: '/about',
      routeKey: 'about',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/about"
        route={route}
        routeSegments={['about']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Hour Timebank');
    expect(html).toContain('Neighbour-powered support');
    expect(html).toContain('href="/hour-timebank/contact"');
    expect(html).toContain('AGPL-3.0-or-later');
    expect(html).toContain('https://app.project-nexus.ie/hour-timebank/about');
  });
});
