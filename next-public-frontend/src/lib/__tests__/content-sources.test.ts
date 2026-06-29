// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { getPublicEndpointForRoute, publicContentSources } from '../content-sources';

describe('public content sources', () => {
  it('resolves collection and detail endpoints from the shared manifest', () => {
    expect(publicContentSources.sourceOfTruth).toBe('laravel_public_api');
    expect(publicContentSources.databaseQueriesFromNext).toBe(false);
    expect(getPublicEndpointForRoute('listings')).toBe('/v2/listings');
    expect(getPublicEndpointForRoute('listingDetail', { id: '42' })).toBe('/v2/listings/42');
    expect(getPublicEndpointForRoute('explore')).toBe('/v2/explore');
    expect(getPublicEndpointForRoute('clubs')).toBe('/v2/clubs');
    expect(getPublicEndpointForRoute('marketplaceSearch')).toBe('/v2/marketplace/listings');
    expect(getPublicEndpointForRoute('marketplaceFree')).toBe('/v2/marketplace/listings/free');
    expect(getPublicEndpointForRoute('marketplaceMap')).toBe('/v2/marketplace/listings/nearby');
  });

  it('encodes route parameters before calling Laravel public APIs', () => {
    expect(getPublicEndpointForRoute('blog-detail', { slug: 'summer news' })).toBe('/v2/blog/summer%20news');
  });

  it('refuses manifest endpoints with query strings or fragments at runtime', () => {
    expect(
      getPublicEndpointForRoute(
        'events',
        {},
        [{ endpoint: '/v2/events?include_private=1', method: 'GET', routeKey: 'events' }],
      ),
    ).toBeNull();

    expect(
      getPublicEndpointForRoute(
        'events',
        {},
        [{ endpoint: '/v2/events#private', method: 'GET', routeKey: 'events' }],
      ),
    ).toBeNull();
  });

  it('refuses private Laravel API namespaces at runtime', () => {
    expect(
      getPublicEndpointForRoute(
        'events',
        {},
        [{ endpoint: '/v2/admin/events', method: 'GET', routeKey: 'events' }],
      ),
    ).toBeNull();
  });

  it('refuses auth-only coupon endpoints at runtime', () => {
    expect(
      getPublicEndpointForRoute(
        'couponDetail',
        { id: '42' },
        [{ endpoint: '/v2/coupons/{id}', method: 'GET', routeKey: 'couponDetail' }],
      ),
    ).toBeNull();
  });

  it.each([
    '/v2/../admin/events',
    '/v2/%2e%2e/admin/events',
    '/v2/events%2fadmin',
  ])('refuses manifest endpoints with path traversal segments at runtime: %s', (endpoint) => {
    expect(
      getPublicEndpointForRoute(
        'events',
        {},
        [{ endpoint, method: 'GET', routeKey: 'events' }],
      ),
    ).toBeNull();
  });
});
