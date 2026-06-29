// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import contentSourcesManifest from '../../content-sources.json';
import { isPrivateLaravelV2Endpoint } from './private-laravel-endpoints';

export interface PublicApiBackedRoute {
  endpoint: string;
  method: string;
  routeKey: string;
}

export interface PublicContentSources {
  apiBackedRoutes: PublicApiBackedRoute[];
  databaseQueriesFromNext: boolean;
  sourceOfTruth: string;
}

export const publicContentSources = contentSourcesManifest as PublicContentSources;

export function getPublicContentSource(
  routeKey: string,
  sources: PublicApiBackedRoute[] = publicContentSources.apiBackedRoutes,
): PublicApiBackedRoute | null {
  return sources.find((source) => source.routeKey === routeKey) ?? null;
}

export function getPublicEndpointForRoute(
  routeKey: string,
  params: Record<string, string> = {},
  sources: PublicApiBackedRoute[] = publicContentSources.apiBackedRoutes,
): string | null {
  const source = getPublicContentSource(routeKey, sources);

  if (!source || !isSafePublicEndpoint(source.endpoint)) {
    return null;
  }

  const requiredParams = [...source.endpoint.matchAll(/\{([^}]+)\}/g)].map((match) => match[1]);

  for (const paramName of requiredParams) {
    if (!params[paramName]) {
      return null;
    }
  }

  return source.endpoint.replace(/\{([^}]+)\}/g, (_match, paramName: string) => encodeURIComponent(params[paramName] ?? ''));
}

function isSafePublicEndpoint(endpoint: string): boolean {
  if (!endpoint.startsWith('/v2/') || endpoint.includes('?') || endpoint.includes('#')) {
    return false;
  }

  if (hasUnsafeEndpointPathSegments(endpoint)) {
    return false;
  }

  return !isPrivateLaravelV2Endpoint(endpoint);
}

function hasUnsafeEndpointPathSegments(endpoint: string): boolean {
  if (endpoint.includes('\\')) {
    return true;
  }

  return endpoint.split('/').some((segment) => {
    const normalizedSegment = segment.toLowerCase();

    return normalizedSegment === '.'
      || normalizedSegment === '..'
      || normalizedSegment === '%2e'
      || normalizedSegment === '%2e%2e'
      || normalizedSegment.includes('%2f')
      || normalizedSegment.includes('%5c');
  });
}
