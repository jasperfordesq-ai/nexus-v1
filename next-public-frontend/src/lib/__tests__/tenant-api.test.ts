// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  fetchListingDetail,
  fetchListingsIndex,
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
