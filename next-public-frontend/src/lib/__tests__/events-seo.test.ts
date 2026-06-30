// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildEventMetadata } from '../events-seo';
import type { PublicEvent } from '../tenant-api';

const event: PublicEvent = {
  category: {
    id: '10',
    name: 'Community',
    slug: 'community',
  },
  createdAt: '2026-06-01T10:00:00+00:00',
  description: 'A public community event for sharing repair skills.',
  endAt: '2026-07-10T12:00:00+00:00',
  excerpt: 'A public community event for sharing repair skills.',
  id: '12',
  location: {
    label: 'Remote or local',
    latitude: null,
    longitude: null,
  },
  organiser: {
    displayName: 'Event Organiser',
    id: '9',
  },
  primaryImage: {
    altText: 'Community repair morning',
    url: 'https://cdn.example.test/events/repair.jpg',
  },
  slug: '12',
  startAt: '2026-07-10T10:00:00+00:00',
  status: 'active',
  title: 'Community repair morning',
  updatedAt: '2026-06-02T11:00:00+00:00',
};

describe('event SEO metadata', () => {
  it('builds OpenGraph, Twitter, and image metadata from the event contract', () => {
    expect(
      buildEventMetadata({
        canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/events/12',
        event,
        platformName: 'Project NEXUS',
        tenantName: 'Hour Timebank',
      }),
    ).toMatchObject({
      alternates: {
        canonical: 'https://app.project-nexus.ie/hour-timebank/events/12',
      },
      description: 'A public community event for sharing repair skills.',
      openGraph: {
        description: 'A public community event for sharing repair skills.',
        images: ['https://cdn.example.test/events/repair.jpg'],
        title: 'Community repair morning | Hour Timebank | Project NEXUS',
        url: 'https://app.project-nexus.ie/hour-timebank/events/12',
      },
      title: 'Community repair morning | Hour Timebank | Project NEXUS',
      twitter: {
        card: 'summary_large_image',
        images: ['https://cdn.example.test/events/repair.jpg'],
        title: 'Community repair morning | Hour Timebank | Project NEXUS',
      },
    });
  });
});
