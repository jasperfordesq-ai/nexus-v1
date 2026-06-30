// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildListingMetadata } from '../listings-seo';
import type { PublicListing } from '../tenant-api';

const listing: PublicListing = {
  category: {
    id: '1',
    name: 'Repairs',
    slug: 'repairs',
  },
  createdAt: '2026-06-01T10:00:00+00:00',
  description: 'A friendly public offer to repair a community bike.',
  excerpt: 'A friendly public offer to repair a community bike.',
  gallery: [],
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

describe('listing SEO metadata', () => {
  it('builds canonical, OpenGraph, Twitter, and image metadata from the listing contract', () => {
    const metadata = buildListingMetadata({
      canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/listings/7',
      listing,
      locale: 'ga',
      platformName: 'Project NEXUS',
      tenantName: 'Hour Timebank',
    });

    expect(metadata).toMatchObject({
      alternates: {
        canonical: 'https://app.project-nexus.ie/hour-timebank/listings/7',
        languages: {
          ga: 'https://app.project-nexus.ie/hour-timebank/listings/7',
          'x-default': 'https://app.project-nexus.ie/hour-timebank/listings/7',
        },
      },
      description: 'A friendly public offer to repair a community bike.',
      openGraph: {
        description: 'A friendly public offer to repair a community bike.',
        images: ['https://cdn.example.test/bike.jpg'],
        locale: 'ga',
        title: 'Repair a community bike | Hour Timebank | Project NEXUS',
        url: 'https://app.project-nexus.ie/hour-timebank/listings/7',
      },
      title: 'Repair a community bike | Hour Timebank | Project NEXUS',
      twitter: {
        card: 'summary_large_image',
        description: 'A friendly public offer to repair a community bike.',
        images: ['https://cdn.example.test/bike.jpg'],
        title: 'Repair a community bike | Hour Timebank | Project NEXUS',
      },
    });
    expect(Object.keys(metadata.alternates?.languages ?? {})).toHaveLength(12);
  });

  it('falls back to the tenant social image when the listing has no primary image', () => {
    const metadata = buildListingMetadata({
      canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/listings/7',
      fallbackImageUrl: 'https://cdn.example.test/tenant/social.png',
      listing: {
        ...listing,
        primaryImage: null,
      },
      platformName: 'Project NEXUS',
      tenantName: 'Hour Timebank',
    });

    expect(metadata.openGraph?.images).toEqual(['https://cdn.example.test/tenant/social.png']);
    expect(metadata.twitter?.images).toEqual(['https://cdn.example.test/tenant/social.png']);
    expect(metadata.twitter).toMatchObject({ card: 'summary_large_image' });
  });
});
