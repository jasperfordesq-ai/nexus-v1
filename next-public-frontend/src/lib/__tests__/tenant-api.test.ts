// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, describe, expect, it, vi } from 'vitest';

import { fetchTenantBootstrap } from '../tenant-api';
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
});
