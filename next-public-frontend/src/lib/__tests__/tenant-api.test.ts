// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, describe, expect, it, vi } from 'vitest';

import { fetchPublicCollection, fetchPublicDetail, fetchTenantBootstrap } from '../tenant-api';
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
});
