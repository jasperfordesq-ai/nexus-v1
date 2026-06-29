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

  it('blocks removal of required Vite-owned private prefixes', () => {
    const result = validateShadowManifests(
      {
        ...routeOwnershipManifest,
        vitePrivatePrefixes: routeOwnershipManifest.vitePrivatePrefixes.filter((prefix) => prefix !== 'login'),
      },
      contentSourcesManifest,
    );

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'vite_private_prefix_missing_required',
      context: 'login',
      severity: 'blocker',
    });
  });

  it('blocks removal of required Vite-owned private mutation patterns', () => {
    const result = validateShadowManifests(
      {
        ...routeOwnershipManifest,
        vitePrivatePatterns: routeOwnershipManifest.vitePrivatePatterns.filter((pattern) => pattern !== '/events/create'),
      },
      contentSourcesManifest,
    );

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'vite_private_pattern_missing_required',
      context: '/events/create',
      severity: 'blocker',
    });
  });

  it('blocks public routes that collide with Vite-owned private mutation patterns', () => {
    const result = validateShadowManifests(
      {
        ...routeOwnershipManifest,
        nextPublicRoutes: [
          ...routeOwnershipManifest.nextPublicRoutes,
          { pattern: '/events/create', routeKey: 'eventCreate', labelKey: 'pages.events.title' },
        ],
      },
      contentSourcesManifest,
    );

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'public_route_collides_with_private_pattern',
      context: '/events/create',
      severity: 'blocker',
    });
  });

  it('blocks duplicate API-backed route keys', () => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      apiBackedRoutes: [
        ...contentSourcesManifest.apiBackedRoutes,
        { endpoint: '/v2/listings-copy', method: 'GET', routeKey: 'listings' },
      ],
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'api_backed_route_duplicate_key',
      context: 'listings',
      severity: 'blocker',
    });
  });

  it('blocks API-backed routes that are not relative Laravel v2 endpoints', () => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      apiBackedRoutes: contentSourcesManifest.apiBackedRoutes.map((source) => (
        source.routeKey === 'events'
          ? { ...source, endpoint: 'https://example.test/v2/events' }
          : source
      )),
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'api_backed_route_not_laravel_v2_endpoint',
      context: 'events',
      severity: 'blocker',
    });
  });

  it('blocks API-backed routes that point at private Laravel v2 namespaces', () => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      apiBackedRoutes: contentSourcesManifest.apiBackedRoutes.map((source) => (
        source.routeKey === 'events'
          ? { ...source, endpoint: '/v2/admin/events' }
          : source
      )),
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'api_backed_route_private_endpoint',
      context: 'events',
      severity: 'blocker',
    });
  });

  it('blocks API-backed route endpoints with query strings or fragments', () => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      apiBackedRoutes: contentSourcesManifest.apiBackedRoutes.map((source) => (
        source.routeKey === 'events'
          ? { ...source, endpoint: '/v2/events?include_private=1' }
          : source
      )),
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'api_backed_route_endpoint_not_plain_path',
      context: 'events',
      severity: 'blocker',
    });
  });

  it.each([
    '/v2/../admin/events',
    '/v2/%2e%2e/admin/events',
    '/v2/events%2fadmin',
  ])('blocks API-backed route endpoints with path traversal segments: %s', (endpoint) => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      apiBackedRoutes: contentSourcesManifest.apiBackedRoutes.map((source) => (
        source.routeKey === 'events'
          ? { ...source, endpoint }
          : source
      )),
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'api_backed_route_endpoint_has_path_traversal',
      context: 'events',
      severity: 'blocker',
    });
  });

  it('blocks API-backed route endpoints whose placeholders drift from the public route params', () => {
    const result = validateShadowManifests(routeOwnershipManifest, {
      ...contentSourcesManifest,
      apiBackedRoutes: contentSourcesManifest.apiBackedRoutes.map((source) => (
        source.routeKey === 'listingDetail'
          ? { ...source, endpoint: '/v2/listings/{slug}' }
          : source
      )),
    });

    expect(result.status).toBe('blocker');
    expect(result.issues).toContainEqual({
      code: 'api_backed_route_param_mismatch',
      context: 'listingDetail',
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
