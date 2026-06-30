// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildMarketplaceMetadata } from '../marketplace-seo';
import type { PublicMarketplaceListing } from '../tenant-api';

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

describe('marketplace SEO metadata', () => {
  it('builds OpenGraph, Twitter, and image metadata from the marketplace contract', () => {
    expect(
      buildMarketplaceMetadata({
        canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/marketplace/31',
        item,
        platformName: 'Project NEXUS',
        tenantName: 'Hour Timebank',
      }),
    ).toMatchObject({
      alternates: {
        canonical: 'https://app.project-nexus.ie/hour-timebank/marketplace/31',
      },
      description: 'A practical kit for local repair sessions.',
      openGraph: {
        description: 'A practical kit for local repair sessions.',
        images: ['https://cdn.example.test/marketplace/repair-kit.jpg'],
        title: 'Community repair kit | Hour Timebank | Project NEXUS',
        url: 'https://app.project-nexus.ie/hour-timebank/marketplace/31',
      },
      title: 'Community repair kit | Hour Timebank | Project NEXUS',
      twitter: {
        card: 'summary_large_image',
        images: ['https://cdn.example.test/marketplace/repair-kit.jpg'],
        title: 'Community repair kit | Hour Timebank | Project NEXUS',
      },
    });
  });
});
