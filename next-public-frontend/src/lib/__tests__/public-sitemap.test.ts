// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildPublicSitemapEntries, buildRobotsMetadata } from '../public-sitemap';
import type {
  PublicEvent,
  PublicJob,
  PublicListing,
  PublicMarketplaceListing,
  PublicOrganisation,
  TenantBootstrap,
} from '../tenant-api';
import type { ResolvedTenantRequest } from '../tenant-request';

const request: ResolvedTenantRequest = {
  host: 'app.project-nexus.ie',
  origin: 'https://app.project-nexus.ie',
  routeSegments: [],
  tenantMode: 'path',
  tenantSlug: 'hour-timebank',
};

const tenant: TenantBootstrap = {
  default_language: 'en',
  id: 2,
  modules: {
    events: true,
    job_vacancies: true,
    listings: true,
    marketplace: true,
    organisations: true,
  },
  name: 'Hour Timebank',
  slug: 'hour-timebank',
};

describe('public sitemap metadata', () => {
  it('builds tenant sitemap URLs from the route manifest and public content contracts', () => {
    const entries = buildPublicSitemapEntries({
      content: {
        events: [eventFixture()],
        jobs: [jobFixture()],
        listings: [listingFixture()],
        marketplaceItems: [marketplaceFixture()],
        organisations: [organisationFixture()],
      },
      request,
      tenant,
    });
    const urls = entries.map((entry) => entry.url);

    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank');
    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank/about');
    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank/listings/7');
    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank/events/11');
    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank/jobs/21');
    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank/marketplace/31');
    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank/organisations/neighbourhood-care-collective');
    expect(urls).not.toContain('https://app.project-nexus.ie/hour-timebank/dashboard');
    expect(new Set(urls).size).toBe(urls.length);
  });

  it('honours tenant module gates for manifest routes', () => {
    const entries = buildPublicSitemapEntries({
      request,
      tenant: {
        ...tenant,
        modules: {
          ...tenant.modules,
          marketplace: false,
        },
      },
    });
    const urls = entries.map((entry) => entry.url);

    expect(urls).not.toContain('https://app.project-nexus.ie/hour-timebank/marketplace');
    expect(urls).toContain('https://app.project-nexus.ie/hour-timebank/about');
  });

  it('builds robots metadata with the shadow sitemap URL', () => {
    expect(buildRobotsMetadata('https://app.project-nexus.ie')).toEqual({
      rules: [
        {
          allow: '/',
          userAgent: '*',
        },
      ],
      sitemap: 'https://app.project-nexus.ie/sitemap.xml',
    });
  });
});

function listingFixture(): PublicListing {
  return {
    category: null,
    createdAt: '2026-06-01T10:00:00+00:00',
    description: 'A public listing.',
    excerpt: 'A public listing.',
    gallery: [],
    id: '7',
    location: {
      label: null,
      latitude: null,
      longitude: null,
    },
    primaryImage: null,
    provider: {
      displayName: null,
      id: null,
    },
    slug: '7',
    status: 'active',
    timeCreditValue: {
      hours: 1,
      unit: 'hour',
    },
    title: 'Public listing',
    updatedAt: '2026-06-02T10:00:00+00:00',
  };
}

function eventFixture(): PublicEvent {
  return {
    category: null,
    createdAt: '2026-06-01T10:00:00+00:00',
    description: 'A public event.',
    endAt: null,
    excerpt: 'A public event.',
    id: '11',
    location: {
      label: null,
      latitude: null,
      longitude: null,
    },
    organiser: {
      displayName: null,
      id: null,
    },
    primaryImage: null,
    slug: '11',
    startAt: '2026-07-01T10:00:00+00:00',
    status: 'active',
    title: 'Public event',
    updatedAt: '2026-06-02T10:00:00+00:00',
  };
}

function jobFixture(): PublicJob {
  return {
    category: null,
    commitment: null,
    compensation: {
      hoursPerWeek: null,
      salaryCurrency: null,
      salaryMax: null,
      salaryMin: null,
      salaryNegotiable: false,
      salaryType: null,
      timeCredits: null,
    },
    createdAt: '2026-06-01T10:00:00+00:00',
    deadlineAt: null,
    description: 'A public job.',
    employer: {
      displayName: null,
      id: null,
      logoUrl: null,
    },
    excerpt: 'A public job.',
    gallery: [],
    id: '21',
    jobType: null,
    location: {
      isRemote: false,
      label: null,
      latitude: null,
      longitude: null,
    },
    primaryImage: null,
    skills: [],
    slug: '21',
    status: 'open',
    title: 'Public job',
    updatedAt: '2026-06-02T10:00:00+00:00',
  };
}

function marketplaceFixture(): PublicMarketplaceListing {
  return {
    category: null,
    condition: 'good',
    createdAt: '2026-06-01T10:00:00+00:00',
    delivery: {
      localPickup: true,
      method: 'pickup',
      shippingAvailable: false,
    },
    description: 'A public marketplace item.',
    excerpt: 'A public marketplace item.',
    expiresAt: null,
    gallery: [],
    id: '31',
    location: {
      label: null,
      latitude: null,
      longitude: null,
    },
    price: {
      amount: null,
      currency: 'EUR',
      priceType: 'free',
      timeCredits: null,
    },
    primaryImage: null,
    quantity: 1,
    seller: {
      avatarUrl: null,
      displayName: null,
      id: null,
      isVerified: false,
      sellerType: null,
    },
    slug: '31',
    status: 'active',
    title: 'Public marketplace item',
    updatedAt: '2026-06-02T10:00:00+00:00',
  };
}

function organisationFixture(): PublicOrganisation {
  return {
    contactEmail: null,
    createdAt: '2026-06-01T10:00:00+00:00',
    description: 'A public organisation.',
    excerpt: 'A public organisation.',
    id: '41',
    location: {
      label: null,
    },
    logoImage: null,
    name: 'Neighbourhood Care Collective',
    orgType: 'organisation',
    owner: {
      avatarUrl: null,
      displayName: null,
      id: null,
    },
    slug: 'neighbourhood-care-collective',
    stats: {
      averageRating: 0,
      opportunityCount: 0,
      reviewCount: 0,
      totalHours: 0,
      volunteerCount: 0,
    },
    status: 'active',
    updatedAt: '2026-06-02T10:00:00+00:00',
    website: null,
  };
}
