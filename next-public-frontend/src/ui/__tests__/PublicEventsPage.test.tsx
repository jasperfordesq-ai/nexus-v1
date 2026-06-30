// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { createTranslator } from '../../lib/i18n';
import type { RouteOwnership } from '../../lib/public-routes';
import type { PublicEvent, TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

const tenant: TenantBootstrap = {
  default_language: 'en',
  id: 2,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  tagline: 'Neighbour-powered support',
};

const event: PublicEvent = {
  category: {
    id: '10',
    name: 'Community',
    slug: 'community',
  },
  createdAt: '2026-06-01T10:00:00+00:00',
  description: 'A public community event for sharing repair skills.',
  endAt: '2026-07-10T12:00:00+00:00',
  excerpt: 'A public community event for sharing repair skills.',
  id: '12',
  location: {
    label: 'Remote or local',
    latitude: null,
    longitude: null,
  },
  organiser: {
    displayName: 'Event Organiser',
    id: '9',
  },
  primaryImage: {
    altText: 'Community repair morning',
    url: 'https://cdn.example.test/events/repair.jpg',
  },
  slug: '12',
  startAt: '2026-07-10T10:00:00+00:00',
  status: 'active',
  title: 'Community repair morning',
  updatedAt: '2026-06-02T11:00:00+00:00',
};

describe('Public events rendering', () => {
  it('renders event index cards as meaningful no-JavaScript HTML', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.events.title',
      owner: 'next-public',
      pattern: '/events',
      routeKey: 'events',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/events"
        content={{
          events: {
            events: [event],
            pagination: {
              cursor: null,
              hasMore: false,
              page: 1,
              perPage: 12,
              total: 1,
            },
          },
          kind: 'events-index',
        }}
        route={route}
        routeSegments={['events']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Community repair morning');
    expect(html).toContain('A public community event for sharing repair skills.');
    expect(html).toContain('Community');
    expect(html).toContain('Remote or local');
    expect(html).toContain('Event Organiser');
    expect(html).toContain('src="https://cdn.example.test/events/repair.jpg"');
    expect(html).toContain('href="/hour-timebank/events/12"');
    expect(html).toContain('"@type":"ItemList"');
    expect(html).toContain('data-nexus-ui="rich-index-card"');
    expect(html).not.toContain('listing-card');
    expect(html).not.toContain('listing-facts');
  });

  it('renders event detail with organiser, date range, location, and Event JSON-LD', () => {
    const route: RouteOwnership = {
      labelKey: 'pages.eventDetail.title',
      owner: 'next-public',
      params: { id: '12' },
      pattern: '/events/:id',
      routeKey: 'eventDetail',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/events/12"
        content={{
          event,
          kind: 'event-detail',
        }}
        route={route}
        routeSegments={['events', '12']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('href="/hour-timebank/events"');
    expect(html).toContain('Community repair morning');
    expect(html).toContain('A public community event for sharing repair skills.');
    expect(html).toContain('Event Organiser');
    expect(html).toContain('Remote or local');
    expect(html).toContain('"@type":"Event"');
    expect(html).toContain('"organizer":{"@type":"Organization","name":"Event Organiser"}');
    expect(html).toContain('data-nexus-ui="rich-detail"');
    expect(html).not.toContain('listing-detail');
    expect(html).not.toContain('public-panel');
  });
});
