// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import ownershipManifest from '../../route-ownership.json';

export type RouteOwner = 'next-public' | 'vite-private' | 'unknown';

export interface RouteOwnership {
  labelKey?: string;
  owner: RouteOwner;
  params?: Record<string, string>;
  pattern?: string;
  routeKey: string;
}

interface ManifestRoute {
  labelKey?: string;
  pattern: string;
  routeKey: string;
}

const manifest = ownershipManifest as {
  nextPublicRoutes: ManifestRoute[];
  vitePrivatePatterns: string[];
  vitePrivatePrefixes: string[];
};

const privatePrefixes = new Set(manifest.vitePrivatePrefixes);

export function getRouteOwnership(routeSegments: string[]): RouteOwnership {
  const normalizedSegments = normalizeSegments(routeSegments);

  if (isPrivateRoute(normalizedSegments)) {
    return {
      owner: 'vite-private',
      routeKey: 'private',
    };
  }

  for (const route of manifest.nextPublicRoutes) {
    const match = matchPattern(route.pattern, normalizedSegments);
    if (match) {
      return {
        labelKey: route.labelKey,
        owner: 'next-public',
        params: match.params,
        pattern: route.pattern,
        routeKey: route.routeKey,
      };
    }
  }

  return {
    owner: 'unknown',
    routeKey: 'unknown',
  };
}

export function isNextPublicRoute(routeSegments: string[]): boolean {
  return getRouteOwnership(routeSegments).owner === 'next-public';
}

function isPrivateRoute(routeSegments: string[]): boolean {
  const [firstSegment] = routeSegments;

  if (firstSegment && privatePrefixes.has(firstSegment)) {
    return true;
  }

  return manifest.vitePrivatePatterns.some((pattern) => Boolean(matchPattern(pattern, routeSegments)));
}

function matchPattern(
  pattern: string,
  routeSegments: string[],
): { params: Record<string, string> } | null {
  const patternSegments = normalizeSegments(pattern.split('/'));

  if (patternSegments.length !== routeSegments.length) {
    return null;
  }

  const params: Record<string, string> = {};

  for (let index = 0; index < patternSegments.length; index += 1) {
    const patternSegment = patternSegments[index];
    const routeSegment = routeSegments[index];

    if (patternSegment?.startsWith(':')) {
      params[patternSegment.slice(1)] = routeSegment;
      continue;
    }

    if (patternSegment !== routeSegment) {
      return null;
    }
  }

  return { params };
}

function normalizeSegments(segments: string[]): string[] {
  return segments.map((segment) => segment.trim().toLowerCase()).filter(Boolean);
}
