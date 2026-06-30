// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { createTranslator } from '../../lib/i18n';
import type { RouteOwnership } from '../../lib/public-routes';
import type { PublicMarketplaceListing, TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

const tenant: TenantBootstrap = {
  default_language: 'en',
  id: 2,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  tagline: 'Neighbour-powered support',
};

const item: PublicMarketplaceListing = {
  category: {
    id: '4',
    name: 'Repair Tools',
    slug: 'repair-tools',
  },
  condition: 'good',
  createdAt: '2026-06-01T10:00:00+00:00',
  delivery: {
    localPickup: true,
    method: 'both',
    shippingAvailable: true,
  },
  description: 'A public marketplace item for a community repair kit.',
  excerpt: 'A practical kit for local repair sessions.',
  expiresAt: '2030-07-10T00:00:00+00:00',
  gallery: [
    {
      altText: 'Community repair kit',
      sortOrder: 0,
      url: 'https://cdn.example.test/marketplace/repair-kit.jpg',
    },
  ],
  id: '31',
  location: {
    label: 'Remote or local',
    latitude: null,
    longitude: null,
  },
  price: {
    amount: 25,
    currency: 'EUR',
    priceType: 'fixed',
    timeCredits: 2,
  },
  primaryImage: {
    altText: 'Community repair kit',
    url: 'https://cdn.example.test/marketplace/repair-kit.jpg',
  },
  quantity: 1,
  seller: {
    avatarUrl: null,
    displayName: 'Market Seller',
    id: '9',
    isVerified: true,
    sellerType: 'private',
  },
  slug: '31',
  status: 'active',
  title: 'Community repair kit',
  updatedAt: '2026-06-02T11:00:00+00:00',
};

describe('Public marketplace rendering', () => {
  it('renders marketplace index cards as meaningful no-JavaScript HTML', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.marketplace.title',
      owner: 'next-public',
      pattern: '/marketplace',
      routeKey: 'marketplace',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/marketplace"
        content={{
          items: {
            items: [item],
            pagination: {
              cursor: null,
              hasMore: false,
              page: 1,
              perPage: 12,
              total: 1,
            },
          },
          kind: 'marketplace-index',
        }}
        route={route}
        routeSegments={['marketplace']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Community repair kit');
    expect(html).toContain('A practical kit for local repair sessions.');
    expect(html).toContain('Repair Tools');
    expect(html).toContain('Remote or local');
    expect(html).toContain('Market Seller');
    expect(html).toContain('EUR 25');
    expect(html).toContain('src="https://cdn.example.test/marketplace/repair-kit.jpg"');
    expect(html).toContain('href="/hour-timebank/marketplace/31"');
    expect(html).toContain('"@type":"ItemList"');
    expect(html).toContain('data-nexus-ui="react-marketplace-card"');
    expect(html).toContain('grid grid-cols-1 min-[420px]:grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4');
    expect(html).toContain('data-slot="button"');
    expect(html).toContain('data-nexus-ui="marketplace-facts"');
  });

  it('renders marketplace detail with gallery, seller, price, and Product Offer JSON-LD', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.marketplaceDetail.title',
      owner: 'next-public',
      params: { id: '31' },
      pattern: '/marketplace/:id',
      routeKey: 'marketplaceDetail',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/marketplace/31"
        content={{
          item,
          kind: 'marketplace-detail',
        }}
        route={route}
        routeSegments={['marketplace', '31']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('href="/hour-timebank/marketplace"');
    expect(html).toContain('Community repair kit');
    expect(html).toContain('A public marketplace item for a community repair kit.');
    expect(html).toContain('Market Seller');
    expect(html).toContain('Remote or local');
    expect(html).toContain('src="https://cdn.example.test/marketplace/repair-kit.jpg"');
    expect(html).toContain('"@type":"Product"');
    expect(html).toContain('"offers":{"@type":"Offer","price":25,"priceCurrency":"EUR"');
    expect(html).toContain('data-nexus-ui="react-marketplace-detail"');
    expect(html).toContain('data-nexus-ui="public-detail-panel"');
  });
});
