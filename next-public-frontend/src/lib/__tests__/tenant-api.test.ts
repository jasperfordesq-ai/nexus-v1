// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  fetchListingDetail,
  fetchListingsIndex,
  fetchEventDetail,
  fetchEventsIndex,
  fetchJobDetail,
  fetchJobsIndex,
  fetchMarketplaceDetail,
  fetchMarketplaceIndex,
  fetchPublicCollection,
  fetchPublicDetail,
  fetchTenantBootstrap,
} from '../tenant-api';
import type { ResolvedTenantRequest } from '../tenant-request';

const request: ResolvedTenantRequest = {
  host: 'app.project-nexus.ie',
  origin: 'https://app.project-nexus.ie',
  routeSegments: [],
  tenantMode: 'path',
  tenantSlug: 'hour-timebank',
};

describe('tenant API client', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    delete process.env.NEXUS_API_BASE;
  });

  it('distinguishes an unknown tenant slug from a transient fetch failure', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ errors: [{ code: 'TENANT_NOT_FOUND' }] }), { status: 404 }),
    );

    await expect(fetchTenantBootstrap(request)).resolves.toEqual({
      status: 'not-found',
      tenant: null,
    });
  });

  it('keeps network failures as recoverable shadow-mode errors', async () => {
    vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('offline'));

    await expect(fetchTenantBootstrap(request)).resolves.toEqual({
      status: 'error',
      tenant: null,
    });
  });

  it('fetches public collection routes from Laravel public APIs with tenant headers', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            data: [
              {
                description: 'Public request for garden help.',
                id: 7,
                title: 'Garden help',
              },
            ],
          },
        }),
      ),
    );

    await expect(fetchPublicCollection('listings', request, null)).resolves.toEqual([
      {
        description: 'Public request for garden help.',
        id: '7',
        title: 'Garden help',
      },
    ]);

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/listings?per_page=12',
      expect.objectContaining({
        headers: expect.objectContaining({
          Accept: 'application/json',
          Origin: 'https://app.project-nexus.ie',
          'X-Tenant-Slug': 'hour-timebank',
        }),
      }),
    );
  });

  it('maps the full public listings contract and forwards tenant locale to Laravel', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [
            {
              id: 7,
              public_contract: {
                id: 7,
                slug: '7',
                title: 'Repair a community bike',
                description: 'A friendly public offer to repair a community bike.',
                excerpt: 'A friendly public offer to repair a community bike.',
                primary_image: {
                  url: '/uploads/tenants/hour-timebank/listings/bike.jpg',
                  alt_text: 'Repair a community bike',
                },
                gallery: [
                  {
                    url: '/uploads/tenants/hour-timebank/listings/bike.jpg',
                    alt_text: 'Repair a community bike',
                    sort_order: 0,
                  },
                ],
                category: {
                  id: 1,
                  name: 'Repairs',
                  slug: 'repairs',
                },
                location: {
                  label: 'Remote or local',
                  latitude: null,
                  longitude: null,
                },
                time_credit_value: {
                  hours: 2.5,
                  unit: 'hour',
                },
                provider: {
                  id: 9,
                  display_name: 'Public Provider',
                },
                created_at: '2026-06-01T10:00:00+00:00',
                updated_at: '2026-06-02T11:00:00+00:00',
                status: 'active',
              },
            },
          ],
          meta: {
            cursor: null,
            has_more: false,
            page: 1,
            per_page: 12,
            total: 1,
          },
        }),
      ),
    );

    await expect(
      fetchListingsIndex(request, {
        default_language: 'ga',
        id: 2,
        name: 'Hour Timebank',
        slug: 'hour-timebank',
      }),
    ).resolves.toMatchObject({
      items: [
        {
          category: {
            name: 'Repairs',
            slug: 'repairs',
          },
          id: '7',
          location: {
            label: 'Remote or local',
          },
          primaryImage: {
            url: 'https://api.example.test/uploads/tenants/hour-timebank/listings/bike.jpg',
          },
          provider: {
            displayName: 'Public Provider',
          },
          slug: '7',
          timeCreditValue: {
            hours: 2.5,
            unit: 'hour',
          },
          title: 'Repair a community bike',
        },
      ],
      pagination: {
        hasMore: false,
        page: 1,
        perPage: 12,
        total: 1,
      },
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/listings?per_page=12',
      expect.objectContaining({
        headers: expect.objectContaining({
          'Accept-Language': 'ga',
          Origin: 'https://app.project-nexus.ie',
          'X-Public-Contract': '1',
          'X-Tenant-Slug': 'hour-timebank',
        }),
      }),
    );
  });

  it('fetches public detail routes from Laravel public APIs', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            excerpt: 'A public event for neighbours.',
            id: 42,
            name: 'Repair cafe',
          },
        }),
      ),
    );

    await expect(fetchPublicDetail('eventDetail', '42', request, null)).resolves.toEqual({
      description: 'A public event for neighbours.',
      id: '42',
      title: 'Repair cafe',
    });
  });

  it('maps public listing detail through the dedicated contract path', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            public_contract: {
              id: 42,
              slug: '42',
              title: 'Garden mentoring',
              description: 'Detailed public description for a gardening skills exchange.',
              excerpt: 'Detailed public description for a gardening skills exchange.',
              primary_image: {
                url: '/uploads/tenants/hour-timebank/listings/garden-1.jpg',
                alt_text: 'Raised beds',
              },
              gallery: [
                {
                  url: '/uploads/tenants/hour-timebank/listings/garden-1.jpg',
                  alt_text: 'Raised beds',
                  sort_order: 0,
                },
              ],
              category: {
                id: 1,
                name: 'Gardening',
                slug: 'gardening',
              },
              location: {
                label: 'Online',
                latitude: null,
                longitude: null,
              },
              time_credit_value: {
                hours: 1.25,
                unit: 'hour',
              },
              provider: {
                id: 10,
                display_name: 'Gallery Provider',
              },
              created_at: '2026-06-01T10:00:00+00:00',
              updated_at: '2026-06-02T11:00:00+00:00',
              status: 'active',
            },
          },
        }),
      ),
    );

    await expect(fetchListingDetail('42', request, null)).resolves.toMatchObject({
      gallery: [
        {
          altText: 'Raised beds',
          url: 'https://api.example.test/uploads/tenants/hour-timebank/listings/garden-1.jpg',
        },
      ],
      id: '42',
      provider: {
        displayName: 'Gallery Provider',
      },
      title: 'Garden mentoring',
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/listings/42',
      expect.objectContaining({
        headers: expect.objectContaining({
          Accept: 'application/json',
          Origin: 'https://app.project-nexus.ie',
          'X-Public-Contract': '1',
        }),
      }),
    );
  });

  it('maps the full public events contract and opts in to Laravel public contracts', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [
            {
              public_contract: {
                id: 12,
                slug: '12',
                title: 'Community repair morning',
                description: 'A public community event for sharing repair skills.',
                excerpt: 'A public community event for sharing repair skills.',
                primary_image: {
                  url: '/uploads/tenants/hour-timebank/events/repair.jpg',
                  alt_text: 'Community repair morning',
                },
                category: {
                  id: 10,
                  name: 'Community',
                  slug: 'community',
                },
                location: {
                  label: 'Remote or local',
                  latitude: null,
                  longitude: null,
                },
                organiser: {
                  id: 9,
                  display_name: 'Event Organiser',
                },
                start_at: '2026-07-10T10:00:00+00:00',
                end_at: '2026-07-10T12:00:00+00:00',
                created_at: '2026-06-01T10:00:00+00:00',
                updated_at: '2026-06-02T11:00:00+00:00',
                status: 'active',
              },
            },
          ],
          meta: {
            cursor: null,
            has_more: false,
            page: 1,
            per_page: 12,
            total: 1,
          },
        }),
      ),
    );

    await expect(fetchEventsIndex(request, null)).resolves.toMatchObject({
      events: [
        {
          id: '12',
          location: { label: 'Remote or local' },
          organiser: { displayName: 'Event Organiser' },
          primaryImage: {
            url: 'https://api.example.test/uploads/tenants/hour-timebank/events/repair.jpg',
          },
          startAt: '2026-07-10T10:00:00+00:00',
          title: 'Community repair morning',
        },
      ],
      pagination: {
        hasMore: false,
        page: 1,
        perPage: 12,
        total: 1,
      },
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/events?per_page=12',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Public-Contract': '1',
        }),
      }),
    );
  });

  it('maps public event detail through the dedicated contract path', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            public_contract: {
              id: 12,
              slug: '12',
              title: 'Community repair morning',
              description: 'A public community event for sharing repair skills.',
              excerpt: 'A public community event for sharing repair skills.',
              primary_image: {
                url: '/uploads/tenants/hour-timebank/events/repair.jpg',
                alt_text: 'Community repair morning',
              },
              category: {
                id: 10,
                name: 'Community',
                slug: 'community',
              },
              location: {
                label: 'Remote or local',
                latitude: null,
                longitude: null,
              },
              organiser: {
                id: 9,
                display_name: 'Event Organiser',
              },
              start_at: '2026-07-10T10:00:00+00:00',
              end_at: '2026-07-10T12:00:00+00:00',
              created_at: '2026-06-01T10:00:00+00:00',
              updated_at: '2026-06-02T11:00:00+00:00',
              status: 'active',
            },
          },
        }),
      ),
    );

    await expect(fetchEventDetail('12', request, null)).resolves.toMatchObject({
      id: '12',
      organiser: { displayName: 'Event Organiser' },
      startAt: '2026-07-10T10:00:00+00:00',
      title: 'Community repair morning',
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/events/12',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Public-Contract': '1',
        }),
      }),
    );
  });

  it('maps the full public jobs contract and opts in to Laravel public contracts', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [
            {
              public_contract: {
                id: 21,
                slug: '21',
                title: 'Community outreach coordinator',
                description: 'Coordinate public outreach and community partner events.',
                excerpt: 'Help neighbours discover practical support.',
                primary_image: {
                  url: '/uploads/tenants/hour-timebank/jobs/civic-works.png',
                  alt_text: 'Civic Works Co-op',
                },
                gallery: [
                  {
                    url: '/uploads/tenants/hour-timebank/jobs/outreach-team.jpg',
                    alt_text: 'Community outreach coordinator',
                    sort_order: 0,
                  },
                ],
                category: {
                  name: 'community',
                  slug: 'community',
                },
                location: {
                  label: 'Remote or local',
                  latitude: null,
                  longitude: null,
                  is_remote: true,
                },
                employer: {
                  id: 9,
                  display_name: 'Civic Works Co-op',
                  logo_url: '/uploads/tenants/hour-timebank/jobs/civic-works.png',
                },
                job_type: 'paid',
                commitment: 'part_time',
                skills: ['coordination', 'outreach'],
                compensation: {
                  salary_min: 25000,
                  salary_max: 35000,
                  salary_currency: 'EUR',
                  salary_type: 'annual',
                  salary_negotiable: false,
                  time_credits: 4,
                  hours_per_week: 12.5,
                },
                deadline_at: '2030-07-10T00:00:00+00:00',
                created_at: '2026-06-01T10:00:00+00:00',
                updated_at: '2026-06-02T11:00:00+00:00',
                status: 'open',
              },
            },
          ],
          meta: {
            cursor: null,
            has_more: false,
            page: 1,
            per_page: 12,
            total: 1,
          },
        }),
      ),
    );

    await expect(fetchJobsIndex(request, null)).resolves.toMatchObject({
      jobs: [
        {
          employer: { displayName: 'Civic Works Co-op' },
          id: '21',
          jobType: 'paid',
          location: { isRemote: true, label: 'Remote or local' },
          primaryImage: {
            url: 'https://api.example.test/uploads/tenants/hour-timebank/jobs/civic-works.png',
          },
          title: 'Community outreach coordinator',
        },
      ],
      pagination: {
        hasMore: false,
        page: 1,
        perPage: 12,
        total: 1,
      },
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/jobs?per_page=12',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Public-Contract': '1',
        }),
      }),
    );
  });

  it('maps public job detail through the dedicated contract path', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            public_contract: {
              id: 22,
              slug: '22',
              title: 'Repair cafe coordinator',
              description: 'Lead a public repair cafe and welcome new volunteers.',
              excerpt: 'Coordinate a weekly repair cafe.',
              primary_image: null,
              gallery: [],
              category: {
                name: 'repairs',
                slug: 'repairs',
              },
              location: {
                label: 'Online',
                latitude: null,
                longitude: null,
                is_remote: true,
              },
              employer: {
                id: 10,
                display_name: 'Detail Employer',
                logo_url: null,
              },
              job_type: 'timebank',
              commitment: 'flexible',
              skills: ['repair', 'facilitation'],
              compensation: {
                salary_min: null,
                salary_max: null,
                salary_currency: null,
                salary_type: null,
                salary_negotiable: false,
                time_credits: 3,
                hours_per_week: null,
              },
              deadline_at: '2030-08-01T17:00:00+00:00',
              created_at: '2026-06-01T10:00:00+00:00',
              updated_at: '2026-06-02T11:00:00+00:00',
              status: 'open',
            },
          },
        }),
      ),
    );

    await expect(fetchJobDetail('22', request, null)).resolves.toMatchObject({
      commitment: 'flexible',
      employer: { displayName: 'Detail Employer' },
      id: '22',
      jobType: 'timebank',
      location: { isRemote: true, label: 'Online' },
      title: 'Repair cafe coordinator',
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/jobs/22',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Public-Contract': '1',
        }),
      }),
    );
  });

  it('maps the full public marketplace contract and opts in to Laravel public contracts', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [
            {
              public_contract: {
                id: 31,
                slug: '31',
                title: 'Community repair kit',
                description: 'A public marketplace item for a community repair kit.',
                excerpt: 'A practical kit for local repair sessions.',
                primary_image: {
                  url: '/uploads/tenants/hour-timebank/marketplace/repair-kit.jpg',
                  alt_text: 'Community repair kit',
                },
                gallery: [
                  {
                    url: '/uploads/tenants/hour-timebank/marketplace/repair-kit.jpg',
                    alt_text: 'Community repair kit',
                    sort_order: 0,
                  },
                ],
                category: {
                  id: 4,
                  name: 'Repair Tools',
                  slug: 'repair-tools',
                },
                location: {
                  label: 'Remote or local',
                  latitude: null,
                  longitude: null,
                },
                price: {
                  amount: 25,
                  currency: 'EUR',
                  price_type: 'fixed',
                  time_credits: 2,
                },
                seller: {
                  id: 9,
                  display_name: 'Market Seller',
                  avatar_url: '/uploads/tenants/hour-timebank/avatar.jpg',
                  is_verified: true,
                  seller_type: 'private',
                },
                delivery: {
                  method: 'both',
                  shipping_available: true,
                  local_pickup: true,
                },
                condition: 'good',
                quantity: 1,
                expires_at: '2030-07-10T00:00:00+00:00',
                created_at: '2026-06-01T10:00:00+00:00',
                updated_at: '2026-06-02T11:00:00+00:00',
                status: 'active',
              },
            },
          ],
          meta: {
            cursor: null,
            has_more: false,
            page: 1,
            per_page: 12,
            total: 1,
          },
        }),
      ),
    );

    await expect(fetchMarketplaceIndex(request, null)).resolves.toMatchObject({
      items: [
        {
          id: '31',
          price: {
            amount: 25,
            currency: 'EUR',
            priceType: 'fixed',
            timeCredits: 2,
          },
          primaryImage: {
            url: 'https://api.example.test/uploads/tenants/hour-timebank/marketplace/repair-kit.jpg',
          },
          seller: {
            displayName: 'Market Seller',
          },
          title: 'Community repair kit',
        },
      ],
      pagination: {
        hasMore: false,
        page: 1,
        perPage: 12,
        total: 1,
      },
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/marketplace/listings?limit=12',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Public-Contract': '1',
        }),
      }),
    );
  });

  it('maps public marketplace detail through the dedicated contract path', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            public_contract: {
              id: 32,
              slug: '32',
              title: 'Shared cordless drill',
              description: 'A detailed public marketplace description for a cordless drill.',
              excerpt: 'Borrow a drill for local projects.',
              primary_image: {
                url: '/uploads/tenants/hour-timebank/marketplace/drill.jpg',
                alt_text: 'Shared cordless drill',
              },
              gallery: [],
              category: {
                id: 5,
                name: 'Shared Tools',
                slug: 'shared-tools',
              },
              location: {
                label: 'Online',
                latitude: null,
                longitude: null,
              },
              price: {
                amount: null,
                currency: 'EUR',
                price_type: 'free',
                time_credits: null,
              },
              seller: {
                id: 10,
                display_name: 'Detail Seller',
                avatar_url: null,
                is_verified: false,
                seller_type: 'private',
              },
              delivery: {
                method: 'pickup',
                shipping_available: false,
                local_pickup: true,
              },
              condition: 'good',
              quantity: 1,
              expires_at: '2030-08-01T00:00:00+00:00',
              created_at: '2026-06-01T10:00:00+00:00',
              updated_at: '2026-06-02T11:00:00+00:00',
              status: 'active',
            },
          },
        }),
      ),
    );

    await expect(fetchMarketplaceDetail('32', request, null)).resolves.toMatchObject({
      id: '32',
      price: {
        priceType: 'free',
      },
      seller: {
        displayName: 'Detail Seller',
      },
      title: 'Shared cordless drill',
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/marketplace/listings/32',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Public-Contract': '1',
        }),
      }),
    );
  });

  it('fetches public detail routes with named manifest parameters', async () => {
    process.env.NEXUS_API_BASE = 'https://api.example.test/api';
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            description: 'A practical course for neighbours.',
            id: 12,
            slug: 'garden-skills',
            title: 'Garden skills',
          },
        }),
      ),
    );

    await expect(fetchPublicDetail('courseDetail', { idOrSlug: 'garden skills' }, request, null)).resolves.toEqual({
      description: 'A practical course for neighbours.',
      id: '12',
      slug: 'garden-skills',
      title: 'Garden skills',
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.example.test/api/v2/courses/garden%20skills',
      expect.objectContaining({
        headers: expect.objectContaining({
          Accept: 'application/json',
          Origin: 'https://app.project-nexus.ie',
          'X-Tenant-Slug': 'hour-timebank',
        }),
      }),
    );
  });
});
