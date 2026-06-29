// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import contentSourcesManifest from '../../../content-sources.json';
import routeOwnershipManifest from '../../../route-ownership.json';
import { validateShadowManifests } from '../shadow-manifest-validation';

describe('shadow manifest validation', () => {
  it('accepts the current route ownership and content source manifests', () => {
    expect(validateShadowManifests(routeOwnershipManifest, contentSourcesManifest)).toEqual({
      issues: [],
      status: 'pass',
    });
  });

  it('blocks duplicate public route patterns', () => {
    const result = validateShadowManifests(
      {
        ...routeOwnershipManifest,
        nextPublicRoutes: [
          ...routeOwnershipManifest.nextPublicRoutes,
          { pattern: '/about', routeKey: 'about-copy', labelKey: 'pages.about.title' },
        ],
      },
      contentSourcesManifest,
    );

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'public_route_duplicate_pattern',
      context: '/about',
      severity: 'blocker',
    });
  });

  it('blocks API-backed route keys that are not in the route manifest', () => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      apiBackedRoutes: [
        ...contentSourcesManifest.apiBackedRoutes,
        { endpoint: '/v2/missing', method: 'GET', routeKey: 'missingRoute' },
      ],
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'api_backed_route_not_in_manifest',
      context: 'missingRoute',
      severity: 'blocker',
    });
  });

  it('blocks content sources that would query databases from Next', () => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      databaseQueriesFromNext: true,
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'content_sources_allow_next_database_queries',
      context: 'databaseQueriesFromNext',
      severity: 'blocker',
    });
  });
});
