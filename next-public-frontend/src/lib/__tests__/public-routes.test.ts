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
    expect(getRouteOwnership(['help'])).toMatchObject({ owner: 'next-public', routeKey: 'help' });
    expect(getRouteOwnership(['contact'])).toMatchObject({ owner: 'next-public', routeKey: 'contact' });
    expect(getRouteOwnership(['faq'])).toMatchObject({ owner: 'next-public', routeKey: 'faq' });
    expect(getRouteOwnership(['privacy'])).toMatchObject({ owner: 'next-public', routeKey: 'privacy' });
    expect(getRouteOwnership(['terms'])).toMatchObject({ owner: 'next-public', routeKey: 'terms' });
    expect(getRouteOwnership(['accessibility'])).toMatchObject({ owner: 'next-public', routeKey: 'accessibility' });
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
      'messages',
      'settings',
      'super-admin',
      'wallet',
    ]) {
      expect(getRouteOwnership([segment])).toMatchObject({ owner: 'vite-private' });
    }

    expect(getRouteOwnership(['events', 'new'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['listings', '123', 'edit'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['jobs', 'new'])).toMatchObject({ owner: 'vite-private' });
    expect(getRouteOwnership(['marketplace', 'bike-repair', 'edit'])).toMatchObject({ owner: 'vite-private' });
  });
});
