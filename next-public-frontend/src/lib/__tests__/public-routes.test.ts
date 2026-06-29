// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { getRouteOwnership } from '../public-routes';

describe('public route ownership', () => {
  it('keeps first-slice public pages in the Next shadow app', () => {
    expect(getRouteOwnership([])).toMatchObject({ owner: 'next-public', routeKey: 'home' });
    expect(getRouteOwnership(['about'])).toMatchObject({ owner: 'next-public', routeKey: 'about' });
    expect(getRouteOwnership(['features'])).toMatchObject({ owner: 'next-public', routeKey: 'features' });
    expect(getRouteOwnership(['changelog'])).toMatchObject({ owner: 'next-public', routeKey: 'changelog' });
    expect(getRouteOwnership(['help'])).toMatchObject({ owner: 'next-public', routeKey: 'help' });
    expect(getRouteOwnership(['contact'])).toMatchObject({ owner: 'next-public', routeKey: 'contact' });
    expect(getRouteOwnership(['faq'])).toMatchObject({ owner: 'next-public', routeKey: 'faq' });
    expect(getRouteOwnership(['privacy'])).toMatchObject({ owner: 'next-public', routeKey: 'privacy' });
    expect(getRouteOwnership(['privacy', 'versions'])).toMatchObject({ owner: 'next-public', routeKey: 'privacyVersions' });
    expect(getRouteOwnership(['terms'])).toMatchObject({ owner: 'next-public', routeKey: 'terms' });
    expect(getRouteOwnership(['terms', 'versions'])).toMatchObject({ owner: 'next-public', routeKey: 'termsVersions' });
    expect(getRouteOwnership(['accessibility'])).toMatchObject({ owner: 'next-public', routeKey: 'accessibility' });
    expect(getRouteOwnership(['accessibility', 'versions'])).toMatchObject({ owner: 'next-public', routeKey: 'accessibilityVersions' });
    expect(getRouteOwnership(['cookies'])).toMatchObject({ owner: 'next-public', routeKey: 'cookies' });
    expect(getRouteOwnership(['cookies', 'versions'])).toMatchObject({ owner: 'next-public', routeKey: 'cookiesVersions' });
    expect(getRouteOwnership(['community-guidelines'])).toMatchObject({ owner: 'next-public', routeKey: 'communityGuidelines' });
    expect(getRouteOwnership(['community-guidelines', 'versions'])).toMatchObject({ owner: 'next-public', routeKey: 'communityGuidelinesVersions' });
    expect(getRouteOwnership(['trust-and-safety'])).toMatchObject({ owner: 'next-public', routeKey: 'trustSafety' });
    expect(getRouteOwnership(['acceptable-use'])).toMatchObject({ owner: 'next-public', routeKey: 'acceptableUse' });
    expect(getRouteOwnership(['acceptable-use', 'versions'])).toMatchObject({ owner: 'next-public', routeKey: 'acceptableUseVersions' });
    expect(getRouteOwnership(['legal'])).toMatchObject({ owner: 'next-public', routeKey: 'legal' });
    expect(getRouteOwnership(['platform', 'terms'])).toMatchObject({ owner: 'next-public', routeKey: 'platformTerms' });
    expect(getRouteOwnership(['platform', 'privacy'])).toMatchObject({ owner: 'next-public', routeKey: 'platformPrivacy' });
    expect(getRouteOwnership(['platform', 'disclaimer'])).toMatchObject({ owner: 'next-public', routeKey: 'platformDisclaimer' });
    expect(getRouteOwnership(['timebanking-guide'])).toMatchObject({ owner: 'next-public', routeKey: 'timebankingGuide' });
  });

  it('keeps content routes public without claiming private product routes', () => {
    expect(getRouteOwnership(['blog'])).toMatchObject({ owner: 'next-public', routeKey: 'blog-index' });
    expect(getRouteOwnership(['blog', 'community-news'])).toMatchObject({
      owner: 'next-public',
      routeKey: 'blog-detail',
      params: { slug: 'community-news' },
    });
    expect(getRouteOwnership(['page', 'how-it-works'])).toMatchObject({
      owner: 'next-public',
      routeKey: 'cms-page',
      params: { slug: 'how-it-works' },
    });
  });

  it('keeps second-batch public discovery routes in the Next shadow app', () => {
    const publicRoutes = [
      { segments: ['listings'], routeKey: 'listings' },
      { segments: ['listings', '123'], routeKey: 'listingDetail', params: { id: '123' } },
      { segments: ['events'], routeKey: 'events' },
      { segments: ['events', 'summer-picnic'], routeKey: 'eventDetail', params: { id: 'summer-picnic' } },
      { segments: ['jobs'], routeKey: 'jobs' },
      { segments: ['jobs', '42'], routeKey: 'jobDetail', params: { id: '42' } },
      { segments: ['organisations'], routeKey: 'organisations' },
      { segments: ['organisations', 'local-hub'], routeKey: 'organisationDetail', params: { id: 'local-hub' } },
      { segments: ['resources'], routeKey: 'resources' },
      { segments: ['kb'], routeKey: 'kb' },
      { segments: ['kb', 'getting-started'], routeKey: 'kbDetail', params: { id: 'getting-started' } },
      { segments: ['marketplace'], routeKey: 'marketplace' },
      { segments: ['marketplace', 'search'], routeKey: 'marketplaceSearch' },
      { segments: ['marketplace', 'map'], routeKey: 'marketplaceMap' },
      { segments: ['marketplace', 'collections'], routeKey: 'marketplaceCollections' },
      { segments: ['marketplace', 'free'], routeKey: 'marketplaceFree' },
      { segments: ['marketplace', 'bike-repair'], routeKey: 'marketplaceDetail', params: { id: 'bike-repair' } },
    ];

    for (const route of publicRoutes) {
      expect(getRouteOwnership(route.segments)).toMatchObject({
        owner: 'next-public',
        routeKey: route.routeKey,
        ...(route.params ? { params: route.params } : {}),
      });
    }
  });

  it('marks logged-in and mutation routes as Vite-owned', () => {
    for (const segment of [
      'admin',
      'broker',
      'dashboard',
      'feed',
      'login',
      'messages',
      'onboarding',
      'register',
      'settings',
      'super-admin',
      'wallet',
    ]) {
      expect(getRouteOwnership([segment])).toMatchObject({ owner: 'vite-private' });
    }

    expect(getRouteOwnership(['events', 'create'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['events', 'edit', '123'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['listings', 'create'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['listings', 'edit', '123'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', 'create'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', 'alerts'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', 'my-applications'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', 'talent-search'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', 'bias-audit'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', 'employer-onboarding'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', '42', 'analytics'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', '42', 'kanban'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['marketplace', 'sell'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['marketplace', 'my-listings'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['marketplace', 'bike-repair', 'edit'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['organisations', 'register'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['volunteering', 'create'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['ideation', 'create'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['courses', 'instructor', 'new'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['courses', 'instructor', '123', 'edit'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['courses', 'my-learning'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['courses', 'instructor'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['group-exchanges', 'create'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['premium', 'manage'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['reviews', 'create'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['volunteering', 'my-applications'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['volunteering', 'my-organisations'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['volunteering', 'org', '12', 'dashboard'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['caring-community', 'my-relationships'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['caring-community', 'my-trust-tier'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['caring-community', 'my-data-export'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['caring-community', 'safeguarding', 'my-reports'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['federation', 'onboarding'])).toMatchObject({ owner: 'vite-private' });
  });
});
