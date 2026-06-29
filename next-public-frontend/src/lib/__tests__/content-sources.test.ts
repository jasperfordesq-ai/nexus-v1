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
    expect(getPublicEndpointForRoute('home')).toBe('/v2/tenant/bootstrap');
    expect(getPublicEndpointForRoute('changelog')).toBe('/v2/public-changelog');
    expect(getPublicEndpointForRoute('help')).toBe('/v2/help/faqs');
    expect(getPublicEndpointForRoute('faq')).toBe('/v2/help/faqs');
    expect(getPublicEndpointForRoute('about')).toBe('/v2/public-page-content/about');
    expect(getPublicEndpointForRoute('features')).toBe('/v2/public-page-content/features');
    expect(getPublicEndpointForRoute('developmentStatus')).toBe('/v2/public-page-content/features');
    expect(getPublicEndpointForRoute('contact')).toBe('/v2/public-page-content/contact');
    expect(getPublicEndpointForRoute('trustSafety')).toBe('/v2/public-page-content/trust-safety');
    expect(getPublicEndpointForRoute('timebankingGuide')).toBe('/v2/public-page-content/timebanking-guide');
    expect(getPublicEndpointForRoute('legal')).toBe('/v2/public-page-content/legal');
    expect(getPublicEndpointForRoute('terms')).toBe('/v2/legal/terms');
    expect(getPublicEndpointForRoute('termsVersions')).toBe('/v2/legal/terms/versions');
    expect(getPublicEndpointForRoute('privacy')).toBe('/v2/legal/privacy');
    expect(getPublicEndpointForRoute('privacyVersions')).toBe('/v2/legal/privacy/versions');
    expect(getPublicEndpointForRoute('communityGuidelines')).toBe('/v2/legal/community_guidelines');
    expect(getPublicEndpointForRoute('acceptableUseVersions')).toBe('/v2/legal/acceptable_use/versions');
    expect(getPublicEndpointForRoute('listings')).toBe('/v2/listings');
    expect(getPublicEndpointForRoute('listingDetail', { id: '42' })).toBe('/v2/listings/42');
    expect(getPublicEndpointForRoute('explore')).toBe('/v2/explore');
    expect(getPublicEndpointForRoute('clubs')).toBe('/v2/clubs');
    expect(getPublicEndpointForRoute('marketplaceSearch')).toBe('/v2/marketplace/listings');
    expect(getPublicEndpointForRoute('marketplaceFree')).toBe('/v2/marketplace/listings/free');
    expect(getPublicEndpointForRoute('marketplaceMap')).toBe('/v2/marketplace/listings/nearby');
    expect(getPublicEndpointForRoute('marketplaceCategory', { slug: 'repair-tools' })).toBe(
      '/v2/marketplace/categories/repair-tools/listings',
    );
    expect(getPublicEndpointForRoute('municipalityCalendar')).toBe('/v2/municipality/events-calendar');
    expect(getPublicEndpointForRoute('developers')).toBe('/v2/public-static-route-content/developers');
    expect(getPublicEndpointForRoute('developersAuth')).toBe('/v2/public-static-route-content/developers-auth');
    expect(getPublicEndpointForRoute('developersEndpoints')).toBe('/v2/public-static-route-content/developers-endpoints');
    expect(getPublicEndpointForRoute('developersWebhooks')).toBe('/v2/public-static-route-content/developers-webhooks');
    expect(getPublicEndpointForRoute('regionalAnalytics')).toBe('/v2/public-static-route-content/regional-analytics');
    expect(getPublicEndpointForRoute('caringCommunity')).toBe('/v2/public-static-route-content/caring-community');
    expect(getPublicEndpointForRoute('hourPartner')).toBe('/v2/public-static-route-content/partner');
    expect(getPublicEndpointForRoute('hourSocialPrescribing')).toBe('/v2/public-static-route-content/social-prescribing');
    expect(getPublicEndpointForRoute('hourImpactSummary')).toBe('/v2/public-static-route-content/impact-summary');
    expect(getPublicEndpointForRoute('hourImpactReport')).toBe('/v2/public-static-route-content/impact-report');
    expect(getPublicEndpointForRoute('hourStrategicPlan')).toBe('/v2/public-static-route-content/strategic-plan');
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
