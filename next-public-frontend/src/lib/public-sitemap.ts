// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { MetadataRoute } from 'next';

import routeOwnershipManifest from '../../route-ownership.json';
import { buildCanonicalUrl } from './metadata';
import { isRouteEnabledForTenant } from './module-gates';
import type { RouteOwnership } from './public-routes';
import type {
  PublicEvent,
  PublicJob,
  PublicListing,
  PublicMarketplaceListing,
  PublicOrganisation,
  TenantBootstrap,
} from './tenant-api';
import type { ResolvedTenantRequest } from './tenant-request';

interface ManifestRoute {
  labelKey?: string;
  pattern: string;
  routeKey: string;
}

export interface PublicSitemapContent {
  events?: PublicEvent[];
  jobs?: PublicJob[];
  listings?: PublicListing[];
  marketplaceItems?: PublicMarketplaceListing[];
  organisations?: PublicOrganisation[];
}

export interface BuildPublicSitemapInput {
  content?: PublicSitemapContent;
  request: ResolvedTenantRequest;
  tenant: TenantBootstrap | null;
}

const manifest = routeOwnershipManifest as {
  nextPublicRoutes: ManifestRoute[];
};

export function buildPublicSitemapEntries(input: BuildPublicSitemapInput): MetadataRoute.Sitemap {
  const entries: MetadataRoute.Sitemap = [];
  const seen = new Set<string>();

  for (const route of manifest.nextPublicRoutes) {
    if (route.pattern.includes(':') || !isRouteEnabled(route, input.tenant)) {
      continue;
    }

    pushEntry(entries, seen, {
      priority: route.pattern === '/' ? 1 : 0.6,
      request: input.request,
      routeSegments: patternToSegments(route.pattern),
    });
  }

  for (const listing of input.content?.listings ?? []) {
    pushEntry(entries, seen, {
      lastModified: listing.updatedAt ?? listing.createdAt ?? undefined,
      priority: 0.7,
      request: input.request,
      routeSegments: ['listings', listing.slug],
    });
  }

  for (const event of input.content?.events ?? []) {
    pushEntry(entries, seen, {
      lastModified: event.updatedAt ?? event.createdAt ?? undefined,
      priority: 0.7,
      request: input.request,
      routeSegments: ['events', event.slug],
    });
  }

  for (const job of input.content?.jobs ?? []) {
    pushEntry(entries, seen, {
      lastModified: job.updatedAt ?? job.createdAt ?? undefined,
      priority: 0.7,
      request: input.request,
      routeSegments: ['jobs', job.slug],
    });
  }

  for (const item of input.content?.marketplaceItems ?? []) {
    pushEntry(entries, seen, {
      lastModified: item.updatedAt ?? item.createdAt ?? undefined,
      priority: 0.7,
      request: input.request,
      routeSegments: ['marketplace', item.slug],
    });
  }

  for (const organisation of input.content?.organisations ?? []) {
    pushEntry(entries, seen, {
      lastModified: organisation.updatedAt ?? organisation.createdAt ?? undefined,
      priority: 0.7,
      request: input.request,
      routeSegments: ['organisations', organisation.slug],
    });
  }

  return entries;
}

export function buildRobotsMetadata(origin: string): MetadataRoute.Robots {
  const normalizedOrigin = origin.replace(/\/+$/, '');

  return {
    rules: [
      {
        allow: '/',
        userAgent: '*',
      },
    ],
    sitemap: `${normalizedOrigin}/sitemap.xml`,
  };
}

function isRouteEnabled(route: ManifestRoute, tenant: TenantBootstrap | null): boolean {
  return isRouteEnabledForTenant(
    {
      labelKey: route.labelKey,
      owner: 'next-public',
      pattern: route.pattern,
      routeKey: route.routeKey,
    } satisfies RouteOwnership,
    tenant,
  );
}

function pushEntry(
  entries: MetadataRoute.Sitemap,
  seen: Set<string>,
  input: {
    lastModified?: string;
    priority: number;
    request: ResolvedTenantRequest;
    routeSegments: string[];
  },
): void {
  const url = buildCanonicalUrl({
    origin: input.request.origin,
    routeSegments: input.routeSegments,
    tenantMode: input.request.tenantMode,
    tenantSlug: input.request.tenantSlug,
  });

  if (seen.has(url)) {
    return;
  }

  seen.add(url);
  entries.push({
    changeFrequency: 'weekly',
    lastModified: input.lastModified,
    priority: input.priority,
    url,
  });
}

function patternToSegments(pattern: string): string[] {
  return pattern.split('/').map((segment) => segment.trim()).filter(Boolean);
}
