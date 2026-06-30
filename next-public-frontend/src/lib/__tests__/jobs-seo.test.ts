// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildJobMetadata } from '../jobs-seo';
import type { PublicJob } from '../tenant-api';

const job: PublicJob = {
  category: {
    name: 'community',
    slug: 'community',
  },
  commitment: 'part_time',
  compensation: {
    hoursPerWeek: 12.5,
    salaryCurrency: 'EUR',
    salaryMax: 35000,
    salaryMin: 25000,
    salaryNegotiable: false,
    salaryType: 'annual',
    timeCredits: 4,
  },
  createdAt: '2026-06-01T10:00:00+00:00',
  deadlineAt: '2030-07-10T00:00:00+00:00',
  description: 'Coordinate public outreach and community partner events.',
  employer: {
    displayName: 'Civic Works Co-op',
    id: '9',
    logoUrl: 'https://cdn.example.test/jobs/civic-works.png',
  },
  excerpt: 'Help neighbours discover practical support.',
  gallery: [
    {
      altText: 'Community outreach coordinator',
      sortOrder: 0,
      url: 'https://cdn.example.test/jobs/outreach-team.jpg',
    },
  ],
  id: '21',
  jobType: 'paid',
  location: {
    isRemote: true,
    label: 'Remote or local',
    latitude: null,
    longitude: null,
  },
  primaryImage: {
    altText: 'Civic Works Co-op',
    url: 'https://cdn.example.test/jobs/civic-works.png',
  },
  skills: ['coordination', 'outreach'],
  slug: '21',
  status: 'open',
  title: 'Community outreach coordinator',
  updatedAt: '2026-06-02T11:00:00+00:00',
};

describe('job SEO metadata', () => {
  it('builds OpenGraph, Twitter, and image metadata from the job contract', () => {
    expect(
      buildJobMetadata({
        canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/jobs/21',
        job,
        platformName: 'Project NEXUS',
        tenantName: 'Hour Timebank',
      }),
    ).toMatchObject({
      alternates: {
        canonical: 'https://app.project-nexus.ie/hour-timebank/jobs/21',
      },
      description: 'Help neighbours discover practical support.',
      openGraph: {
        description: 'Help neighbours discover practical support.',
        images: ['https://cdn.example.test/jobs/civic-works.png'],
        title: 'Community outreach coordinator | Hour Timebank | Project NEXUS',
        url: 'https://app.project-nexus.ie/hour-timebank/jobs/21',
      },
      title: 'Community outreach coordinator | Hour Timebank | Project NEXUS',
      twitter: {
        card: 'summary_large_image',
        images: ['https://cdn.example.test/jobs/civic-works.png'],
        title: 'Community outreach coordinator | Hour Timebank | Project NEXUS',
      },
    });
  });
});
