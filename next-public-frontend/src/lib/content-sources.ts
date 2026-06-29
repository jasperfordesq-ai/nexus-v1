// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import contentSourcesManifest from '../../content-sources.json';

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

export function getPublicContentSource(routeKey: string): PublicApiBackedRoute | null {
  return publicContentSources.apiBackedRoutes.find((source) => source.routeKey === routeKey) ?? null;
}

export function getPublicEndpointForRoute(
  routeKey: string,
  params: Record<string, string> = {},
): string | null {
  const source = getPublicContentSource(routeKey);

  if (!source) {
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
