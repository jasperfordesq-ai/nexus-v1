// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { createTranslator } from '../../lib/i18n';
import type { RouteOwnership } from '../../lib/public-routes';
import type { PublicListing, TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

const tenant: TenantBootstrap = {
  branding: {
    logo_url: 'https://cdn.example.test/logo.png',
    primary_color: '#146c94',
  },
  default_language: 'en',
  id: 2,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  tagline: 'Neighbour-powered support',
};

const listing: PublicListing = {
  category: {
    id: '1',
    name: 'Repairs',
    slug: 'repairs',
  },
  createdAt: '2026-06-01T10:00:00+00:00',
  description: 'A friendly public offer to repair a community bike.',
  excerpt: 'A friendly public offer to repair a community bike.',
  gallery: [
    {
      altText: 'Repair a community bike',
      sortOrder: 0,
      url: 'https://cdn.example.test/bike.jpg',
    },
  ],
  id: '7',
  location: {
    label: 'Remote or local',
    latitude: null,
    longitude: null,
  },
  primaryImage: {
    altText: 'Repair a community bike',
    url: 'https://cdn.example.test/bike.jpg',
  },
  provider: {
    displayName: 'Public Provider',
    id: '9',
  },
  slug: '7',
  status: 'active',
  timeCreditValue: {
    hours: 2.5,
    unit: 'hour',
  },
  title: 'Repair a community bike',
  updatedAt: '2026-06-02T11:00:00+00:00',
};

describe('Public listings rendering', () => {
  it('renders listings index cards as meaningful no-JavaScript HTML', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.listings.title',
      owner: 'next-public',
      pattern: '/listings',
      routeKey: 'listings',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/listings"
        content={{
          kind: 'listings-index',
          listings: {
            items: [listing],
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
        routeSegments={['listings']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Repair a community bike');
    expect(html).toContain('A friendly public offer to repair a community bike.');
    expect(html).toContain('Repairs');
    expect(html).toContain('2.5 hours');
    expect(html).toContain('Remote or local');
    expect(html).toContain('Public Provider');
    expect(html).toContain('src="https://cdn.example.test/bike.jpg"');
    expect(html).toContain('href="/hour-timebank/listings/7"');
    expect(html).toContain('"@type":"ItemList"');
    expect(html).toContain('data-nexus-ui="react-listing-card"');
    expect(html).toContain('border-l-emerald-500/70');
    expect(html).toContain('grid gap-4 sm:grid-cols-2 lg:grid-cols-3');
    expect(html).toContain('data-slot="button"');
    expect(html).toContain('data-nexus-ui="listing-facts"');
  });

  it('renders listing detail with gallery, breadcrumb, provider, and Service JSON-LD', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.listingDetail.title',
      owner: 'next-public',
      params: { id: '7' },
      pattern: '/listings/:id',
      routeKey: 'listingDetail',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/listings/7"
        content={{
          kind: 'listing-detail',
          listing,
        }}
        route={route}
        routeSegments={['listings', '7']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('href="/hour-timebank/listings"');
    expect(html).toContain('Repair a community bike');
    expect(html).toContain('A friendly public offer to repair a community bike.');
    expect(html).toContain('src="https://cdn.example.test/bike.jpg"');
    expect(html).toContain('Gallery');
    expect(html).toContain('Provider');
    expect(html).toContain('Public Provider');
    expect(html).toContain('"@type":"Service"');
    expect(html).toContain('"provider":{"@type":"Organization","name":"Public Provider"}');
    expect(html).toContain('data-nexus-ui="react-listing-detail"');
    expect(html).toContain('data-nexus-ui="public-detail-panel"');
  });
});
