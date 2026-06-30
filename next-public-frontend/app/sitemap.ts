// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { MetadataRoute } from 'next';
import { headers } from 'next/headers';

import { buildPublicSitemapEntries, type PublicSitemapContent } from '../src/lib/public-sitemap';
import { isRouteEnabledForTenant } from '../src/lib/module-gates';
import {
  fetchEventsIndex,
  fetchJobsIndex,
  fetchListingsIndex,
  fetchMarketplaceIndex,
  fetchOrganisationsIndex,
  fetchTenantBootstrap,
  type TenantBootstrap,
} from '../src/lib/tenant-api';
import { resolveTenantRequest, type ResolvedTenantRequest } from '../src/lib/tenant-request';

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const request = await buildSitemapTenantRequest();
  const tenantResult = await fetchTenantBootstrap(request);
  const tenant = tenantResult.tenant;
  const content = await fetchSitemapContent(request, tenant);

  return buildPublicSitemapEntries({
    content,
    request,
    tenant,
  });
}

async function buildSitemapTenantRequest(): Promise<ResolvedTenantRequest> {
  const headerList = await headers();
  const request = resolveTenantRequest([], {
    host: headerList.get('x-forwarded-host') ?? headerList.get('host') ?? undefined,
    protocol: headerList.get('x-forwarded-proto') ?? undefined,
  });
  const tenantSlug = headerList.get('x-tenant-slug') ?? process.env.NEXUS_PUBLIC_SITEMAP_TENANT_SLUG ?? undefined;

  if (!tenantSlug) {
    return request;
  }

  return {
    ...request,
    routeSegments: [],
    tenantMode: 'path',
    tenantSlug,
  };
}

async function fetchSitemapContent(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicSitemapContent> {
  const content: PublicSitemapContent = {};

  if (isSitemapRouteEnabled('listings', tenant)) {
    content.listings = (await fetchListingsIndex(request, tenant)).items;
  }

  if (isSitemapRouteEnabled('events', tenant)) {
    content.events = (await fetchEventsIndex(request, tenant)).events;
  }

  if (isSitemapRouteEnabled('jobs', tenant)) {
    content.jobs = (await fetchJobsIndex(request, tenant)).jobs;
  }

  if (isSitemapRouteEnabled('marketplace', tenant)) {
    content.marketplaceItems = (await fetchMarketplaceIndex(request, tenant)).items;
  }

  if (isSitemapRouteEnabled('organisations', tenant)) {
    content.organisations = (await fetchOrganisationsIndex(request, tenant)).organisations;
  }

  return content;
}

function isSitemapRouteEnabled(routeKey: string, tenant: TenantBootstrap | null): boolean {
  return isRouteEnabledForTenant(
    {
      owner: 'next-public',
      routeKey,
    },
    tenant,
  );
}
