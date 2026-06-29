// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import ownershipManifest from '../../route-ownership.json';

export type TenantMode = 'host' | 'path';

export interface ResolveTenantRequestInput {
  host?: string;
  protocol?: string;
}

export interface ResolvedTenantRequest {
  host: string;
  origin: string;
  routeSegments: string[];
  tenantMode: TenantMode;
  tenantSlug: string | undefined;
}

export interface BuildTenantBootstrapRequestInput {
  apiBase: string;
  origin: string;
  tenantSlug?: string;
}

export interface TenantBootstrapRequest {
  headers: {
    Accept: 'application/json';
    Origin: string;
  };
  url: string;
}

const sharedHosts = new Set(['127.0.0.1', 'app.project-nexus.ie', 'localhost']);

const manifest = ownershipManifest as {
  nextPublicRoutes: Array<{ pattern: string }>;
  vitePrivatePatterns: string[];
  vitePrivatePrefixes: string[];
};

const reservedSharedHostSegments = new Set([
  '_next',
  'about',
  'accessibility',
  'api',
  'blog',
  'contact',
  'faq',
  'help',
  'login',
  'page',
  'privacy',
  'register',
  'terms',
  ...manifest.vitePrivatePrefixes,
  ...manifest.vitePrivatePatterns.map((pattern) => pattern.split('/').filter(Boolean)[0]).filter(Boolean),
  ...manifest.nextPublicRoutes.map((route) => route.pattern.split('/').filter(Boolean)[0]).filter(Boolean),
]);

export function resolveTenantRequest(
  rawSegments: string[],
  input: ResolveTenantRequestInput,
): ResolvedTenantRequest {
  const host = normalizeHost(input.host);
  const protocol = input.protocol ?? inferProtocol(host);
  const normalizedSegments = normalizeSegments(rawSegments);
  const hostName = stripPort(host);
  const usesSharedHost = sharedHosts.has(hostName);

  if (usesSharedHost && normalizedSegments.length > 0) {
    const [firstSegment, ...remainingSegments] = normalizedSegments;

    if (!reservedSharedHostSegments.has(firstSegment)) {
      return {
        host,
        origin: `${protocol}://${host}`,
        routeSegments: remainingSegments,
        tenantMode: 'path',
        tenantSlug: firstSegment,
      };
    }
  }

  return {
    host,
    origin: `${protocol}://${host}`,
    routeSegments: normalizedSegments,
    tenantMode: 'host',
    tenantSlug: undefined,
  };
}

export function buildTenantBootstrapRequest(
  input: BuildTenantBootstrapRequestInput,
): TenantBootstrapRequest {
  const base = input.apiBase.replace(/\/+$/, '');
  const url = new URL(`${base}/v2/tenant/bootstrap`);

  if (input.tenantSlug) {
    url.searchParams.set('slug', input.tenantSlug);
  }

  return {
    headers: {
      Accept: 'application/json',
      Origin: input.origin,
    },
    url: url.toString(),
  };
}

function inferProtocol(host: string): string {
  const hostName = stripPort(host);

  if (hostName === 'localhost' || hostName === '127.0.0.1') {
    return 'http';
  }

  return 'https';
}

function normalizeHost(host: string | undefined): string {
  return (host ?? 'app.project-nexus.ie').trim().toLowerCase();
}

function normalizeSegments(segments: string[]): string[] {
  return segments.map((segment) => decodePathSegment(segment).trim().toLowerCase()).filter(Boolean);
}

function stripPort(host: string): string {
  return host.split(':')[0] ?? host;
}

function decodePathSegment(segment: string): string {
  try {
    return decodeURIComponent(segment);
  } catch {
    return segment;
  }
}
