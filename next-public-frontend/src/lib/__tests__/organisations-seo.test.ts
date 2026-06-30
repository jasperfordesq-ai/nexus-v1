// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildOrganisationMetadata } from '../organisations-seo';
import type { PublicOrganisation } from '../tenant-api';

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

describe('organisation SEO metadata', () => {
  it('builds OpenGraph, Twitter, and image metadata from the organisation contract', () => {
    expect(
      buildOrganisationMetadata({
        canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/organisations/41',
        organisation,
        platformName: 'Project NEXUS',
        tenantName: 'Hour Timebank',
      }),
    ).toMatchObject({
      alternates: {
        canonical: 'https://app.project-nexus.ie/hour-timebank/organisations/41',
      },
      description: 'A public organisation profile for local care and volunteering.',
      openGraph: {
        description: 'A public organisation profile for local care and volunteering.',
        images: ['https://cdn.example.test/organisations/care-collective.png'],
        title: 'Neighbourhood Care Collective | Hour Timebank | Project NEXUS',
        url: 'https://app.project-nexus.ie/hour-timebank/organisations/41',
      },
      title: 'Neighbourhood Care Collective | Hour Timebank | Project NEXUS',
      twitter: {
        card: 'summary_large_image',
        images: ['https://cdn.example.test/organisations/care-collective.png'],
        title: 'Neighbourhood Care Collective | Hour Timebank | Project NEXUS',
      },
    });
  });
});
