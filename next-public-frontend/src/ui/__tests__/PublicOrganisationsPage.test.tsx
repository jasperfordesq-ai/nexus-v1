// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { createTranslator } from '../../lib/i18n';
import type { RouteOwnership } from '../../lib/public-routes';
import type { PublicOrganisation, TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

const tenant: TenantBootstrap = {
  default_language: 'en',
  id: 2,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  tagline: 'Neighbour-powered support',
};

const organisation: PublicOrganisation = {
  contactEmail: 'hello@example.test',
  createdAt: '2026-06-01T10:00:00+00:00',
  description: 'A public organisation profile for local care and volunteering.',
  excerpt: 'A public organisation profile for local care and volunteering.',
  id: '41',
  location: {
    label: null,
  },
  logoImage: {
    altText: 'Neighbourhood Care Collective',
    url: 'https://cdn.example.test/organisations/care-collective.png',
  },
  name: 'Neighbourhood Care Collective',
  orgType: 'organisation',
  owner: {
    avatarUrl: null,
    displayName: 'Organisation Owner',
    id: '9',
  },
  slug: 'neighbourhood-care-collective',
  stats: {
    averageRating: 4.5,
    opportunityCount: 3,
    reviewCount: 2,
    totalHours: 42.5,
    volunteerCount: 12,
  },
  status: 'active',
  updatedAt: '2026-06-02T11:00:00+00:00',
  website: 'https://example.test/care',
};

describe('Public organisations rendering', () => {
  it('renders organisation index cards as meaningful no-JavaScript HTML', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.organisations.title',
      owner: 'next-public',
      pattern: '/organisations',
      routeKey: 'organisations',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/organisations"
        content={{
          kind: 'organisations-index',
          organisations: {
            organisations: [organisation],
            pagination: {
              cursor: null,
              hasMore: false,
              page: 1,
              perPage: 12,
              total: 1,
            },
          },
        }}
        route={route}
        routeSegments={['organisations']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Neighbourhood Care Collective');
    expect(html).toContain('A public organisation profile for local care and volunteering.');
    expect(html).toContain('Organisation Owner');
    expect(html).toContain('3 opportunities');
    expect(html).toContain('12 volunteers');
    expect(html).toContain('src="https://cdn.example.test/organisations/care-collective.png"');
    expect(html).toContain('href="/hour-timebank/organisations/neighbourhood-care-collective"');
    expect(html).toContain('"@type":"ItemList"');
  });

  it('renders organisation detail with public stats and Organization JSON-LD', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.organisationDetail.title',
      owner: 'next-public',
      params: { id: '41' },
      pattern: '/organisations/:id',
      routeKey: 'organisationDetail',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/organisations/41"
        content={{
          kind: 'organisation-detail',
          organisation,
        }}
        route={route}
        routeSegments={['organisations', '41']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('href="/hour-timebank/organisations"');
    expect(html).toContain('Neighbourhood Care Collective');
    expect(html).toContain('hello@example.test');
    expect(html).toContain('https://example.test/care');
    expect(html).toContain('42.5 hours');
    expect(html).toContain('"@type":"Organization"');
    expect(html).toContain('"name":"Neighbourhood Care Collective"');
  });
});
