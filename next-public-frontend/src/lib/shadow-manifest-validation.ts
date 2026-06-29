// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

interface RouteOwnershipManifest {
  mode?: unknown;
  nextPublicRoutes?: unknown;
  vitePrivatePrefixes?: unknown;
}

interface ContentSourcesManifest {
  apiBackedRoutes?: unknown;
  databaseQueriesFromNext?: unknown;
  sourceOfTruth?: unknown;
}

interface ManifestIssue {
  code: string;
  context: string;
  severity: 'blocker';
}

interface ManifestValidationResult {
  issues: ManifestIssue[];
  status: 'blocker' | 'pass';
}

export function validateShadowManifests(
  routeManifest: RouteOwnershipManifest,
  contentSources: ContentSourcesManifest,
): ManifestValidationResult {
  const issues: ManifestIssue[] = [];

  if (routeManifest.mode !== 'shadow') {
    issues.push({
      code: 'route_manifest_not_shadow',
      context: String(routeManifest.mode ?? 'missing'),
      severity: 'blocker',
    });
  }

  if (contentSources.sourceOfTruth !== 'laravel_public_api') {
    issues.push({
      code: 'content_sources_not_laravel_api',
      context: String(contentSources.sourceOfTruth ?? 'missing'),
      severity: 'blocker',
    });
  }

  if (contentSources.databaseQueriesFromNext !== false) {
    issues.push({
      code: 'content_sources_allow_next_database_queries',
      context: 'databaseQueriesFromNext',
      severity: 'blocker',
    });
  }

  const publicRoutes = Array.isArray(routeManifest.nextPublicRoutes) ? routeManifest.nextPublicRoutes : [];
  const privatePrefixes = new Set(
    Array.isArray(routeManifest.vitePrivatePrefixes)
      ? routeManifest.vitePrivatePrefixes.filter((prefix): prefix is string => typeof prefix === 'string')
      : [],
  );
  const routeKeys = new Set<string>();
  const routeParamsByKey = new Map<string, Set<string>>();
  const patterns = new Set<string>();

  for (const route of publicRoutes) {
    if (!isRecord(route)) {
      issues.push({ code: 'public_route_invalid', context: 'non-object', severity: 'blocker' });
      continue;
    }

    const pattern = typeof route.pattern === 'string' ? route.pattern : '';
    const routeKey = typeof route.routeKey === 'string' ? route.routeKey : '';
    const labelKey = typeof route.labelKey === 'string' ? route.labelKey : '';

    if (!pattern || !routeKey || !labelKey) {
      issues.push({ code: 'public_route_missing_fields', context: pattern || routeKey || 'unknown', severity: 'blocker' });
    }

    if (patterns.has(pattern)) {
      issues.push({ code: 'public_route_duplicate_pattern', context: pattern, severity: 'blocker' });
    }
    patterns.add(pattern);

    if (routeKeys.has(routeKey)) {
      issues.push({ code: 'public_route_duplicate_key', context: routeKey, severity: 'blocker' });
    }
    routeKeys.add(routeKey);
    routeParamsByKey.set(routeKey, extractPatternParams(pattern));

    const firstSegment = pattern.replace(/^\/+/, '').split('/').filter(Boolean).at(0);
    if (firstSegment && privatePrefixes.has(firstSegment)) {
      issues.push({ code: 'public_route_collides_with_private_prefix', context: pattern, severity: 'blocker' });
    }
  }

  const apiBackedRoutes = Array.isArray(contentSources.apiBackedRoutes) ? contentSources.apiBackedRoutes : [];
  const apiBackedRouteKeys = new Set<string>();

  for (const source of apiBackedRoutes) {
    if (!isRecord(source)) {
      issues.push({ code: 'api_backed_route_invalid', context: 'non-object', severity: 'blocker' });
      continue;
    }

    const routeKey = typeof source.routeKey === 'string' ? source.routeKey : '';
    const endpoint = typeof source.endpoint === 'string' ? source.endpoint : '';
    const method = typeof source.method === 'string' ? source.method : '';

    if (!routeKey || !endpoint || !method) {
      issues.push({ code: 'api_backed_route_missing_fields', context: routeKey || endpoint || 'unknown', severity: 'blocker' });
      continue;
    }

    if (apiBackedRouteKeys.has(routeKey)) {
      issues.push({ code: 'api_backed_route_duplicate_key', context: routeKey, severity: 'blocker' });
    }
    apiBackedRouteKeys.add(routeKey);

    if (!routeKeys.has(routeKey)) {
      issues.push({ code: 'api_backed_route_not_in_manifest', context: routeKey, severity: 'blocker' });
    }

    if (method.toUpperCase() !== 'GET') {
      issues.push({ code: 'api_backed_route_not_get', context: `${method} ${endpoint}`, severity: 'blocker' });
    }

    if (!sameSet(routeParamsByKey.get(routeKey) ?? new Set<string>(), extractEndpointParams(endpoint))) {
      issues.push({ code: 'api_backed_route_param_mismatch', context: routeKey, severity: 'blocker' });
    }
  }

  return {
    issues,
    status: issues.length === 0 ? 'pass' : 'blocker',
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function extractPatternParams(pattern: string): Set<string> {
  return new Set(
    pattern
      .split('/')
      .filter((segment) => segment.startsWith(':'))
      .map((segment) => segment.slice(1))
      .filter(Boolean),
  );
}

function extractEndpointParams(endpoint: string): Set<string> {
  return new Set([...endpoint.matchAll(/\{([^}]+)\}/g)].map((match) => match[1]).filter(Boolean));
}

function sameSet(left: Set<string>, right: Set<string>): boolean {
  if (left.size !== right.size) {
    return false;
  }

  return [...left].every((value) => right.has(value));
}
