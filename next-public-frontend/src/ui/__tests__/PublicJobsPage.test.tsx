// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { createTranslator } from '../../lib/i18n';
import type { RouteOwnership } from '../../lib/public-routes';
import type { PublicJob, TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

const tenant: TenantBootstrap = {
  default_language: 'en',
  id: 2,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  tagline: 'Neighbour-powered support',
};

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

describe('Public jobs rendering', () => {
  it('renders job index cards as meaningful no-JavaScript HTML', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.jobs.title',
      owner: 'next-public',
      pattern: '/jobs',
      routeKey: 'jobs',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/jobs"
        content={{
          jobs: {
            jobs: [job],
            pagination: {
              cursor: null,
              hasMore: false,
              page: 1,
              perPage: 12,
              total: 1,
            },
          },
          kind: 'jobs-index',
        }}
        route={route}
        routeSegments={['jobs']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Community outreach coordinator');
    expect(html).toContain('Help neighbours discover practical support.');
    expect(html).toContain('community');
    expect(html).toContain('Remote or local');
    expect(html).toContain('Civic Works Co-op');
    expect(html).toContain('EUR 25,000 - 35,000');
    expect(html).toContain('src="https://cdn.example.test/jobs/civic-works.png"');
    expect(html).toContain('href="/hour-timebank/jobs/21"');
    expect(html).toContain('"@type":"ItemList"');
    expect(html).toContain('data-nexus-ui="react-job-card"');
    expect(html).toContain('hover:scale-[1.01]');
    expect(html).toContain('grid gap-4 sm:grid-cols-2 lg:grid-cols-3');
    expect(html).toContain('data-slot="button"');
    expect(html).toContain('data-nexus-ui="job-facts"');
  });

  it('renders job detail with employer, compensation, gallery, and JobPosting JSON-LD', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.jobDetail.title',
      owner: 'next-public',
      params: { id: '21' },
      pattern: '/jobs/:id',
      routeKey: 'jobDetail',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/jobs/21"
        content={{
          job,
          kind: 'job-detail',
        }}
        route={route}
        routeSegments={['jobs', '21']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('href="/hour-timebank/jobs"');
    expect(html).toContain('Community outreach coordinator');
    expect(html).toContain('Coordinate public outreach and community partner events.');
    expect(html).toContain('Civic Works Co-op');
    expect(html).toContain('coordination');
    expect(html).toContain('src="https://cdn.example.test/jobs/outreach-team.jpg"');
    expect(html).toContain('"@type":"JobPosting"');
    expect(html).toContain('"hiringOrganization":{"@type":"Organization","name":"Civic Works Co-op"');
    expect(html).toContain('data-nexus-ui="react-job-detail"');
    expect(html).toContain('data-nexus-ui="public-detail-panel"');
  });
});
