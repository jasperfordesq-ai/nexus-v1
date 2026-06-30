// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { MetadataRoute } from 'next';
import { headers } from 'next/headers';

import { buildRobotsMetadata } from '../src/lib/public-sitemap';
import { resolveTenantRequest } from '../src/lib/tenant-request';

export default async function robots(): Promise<MetadataRoute.Robots> {
  const headerList = await headers();
  const request = resolveTenantRequest([], {
    host: headerList.get('x-forwarded-host') ?? headerList.get('host') ?? undefined,
    protocol: headerList.get('x-forwarded-proto') ?? undefined,
  });

  return buildRobotsMetadata(request.origin);
}
