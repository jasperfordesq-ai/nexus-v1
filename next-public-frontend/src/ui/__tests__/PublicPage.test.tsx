// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { createTranslator } from '../../lib/i18n';
import type { RouteOwnership } from '../../lib/public-routes';
import type { TenantBootstrap } from '../../lib/tenant-api';
import { PublicPage } from '../PublicPage';

describe('PublicPage', () => {
  it('renders crawler-readable tenant HTML with attribution and public navigation', () => {
    const tenant: TenantBootstrap = {
      branding: {
        logo_url: 'https://cdn.example/logo.png',
        primary_color: '#146c94',
      },
      default_language: 'en',
      id: 2,
      name: 'Hour Timebank',
      slug: 'hour-timebank',
      tagline: 'Neighbour-powered support',
    };
    const route: RouteOwnership = {
      labelKey: 'pages.about.title',
      owner: 'next-public',
      pattern: '/about',
      routeKey: 'about',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/about"
        route={route}
        routeSegments={['about']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Hour Timebank');
    expect(html).toContain('Neighbour-powered support');
    expect(html).toContain('href="/hour-timebank/contact"');
    expect(html).toContain('AGPL-3.0-or-later');
    expect(html).toContain('https://app.project-nexus.ie/hour-timebank/about');
    expect(html).toContain('data-slot="card"');
    expect(html).toContain('data-nexus-ui="react-static-page"');
    expect(html).toContain('data-route-key="about"');
    expect(html).toContain('legal-content');
    expect(html).not.toContain('post-list');
  });

  it('renders the shared public chrome with tenant theme tokens and grouped footer links', () => {
    const tenant: TenantBootstrap = {
      branding: {
        logo_url: 'https://cdn.example/hour-logo.png',
        primary_color: '#0f766e',
        secondary_color: '#4338ca',
      },
      default_language: 'en',
      id: 2,
      name: 'Hour Timebank',
      slug: 'hour-timebank',
      tagline: 'Neighbour-powered support',
    };
    const route: RouteOwnership = {
      labelKey: 'pages.home.title',
      owner: 'next-public',
      pattern: '/',
      routeKey: 'home',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank"
        route={route}
        routeSegments={[]}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('data-nexus-ui="heroui-public"');
    expect(html).toContain('data-nexus-ui="react-public-navbar"');
    expect(html).toContain('data-nexus-ui="react-public-mobile-drawer"');
    expect(html).toContain('data-nexus-ui="react-public-footer"');
    expect(html).toContain('data-slot="surface"');
    expect(html).toContain('data-slot="button"');
    expect(html).toContain('utility-bar-action');
    expect(html).not.toContain('site-header');
    expect(html).not.toContain('public-shell');
    expect(html).not.toContain('hero-band');
    expect(html).not.toContain('home-hero-panel');
    expect(html).toContain('--nexus-accent:#0f766e');
    expect(html).toContain('--nexus-accent-secondary:#4338ca');
    expect(html).toContain('href="/hour-timebank/jobs"');
    expect(html).toContain('href="/hour-timebank/marketplace"');
    expect(html).toContain('href="/hour-timebank/organisations"');
    expect(html).toContain('href="/hour-timebank/login"');
    expect(html).toContain('href="/hour-timebank/register"');
    expect(html).toContain('aria-controls="mobile-drawer"');
    expect(html).toContain('aria-label="Platform"');
    expect(html).toContain('aria-label="Legal"');
    expect(html).toContain('AGPL-3.0-or-later');
    expect(html).not.toContain('public-panel');
    expect(html).not.toContain('post-list');
  });

  it('renders second-batch public discovery routes as no-JavaScript HTML', () => {
    const tenant: TenantBootstrap = {
      default_language: 'en',
      id: 2,
      name: 'Hour Timebank',
      slug: 'hour-timebank',
    };
    const route: RouteOwnership = {
      labelKey: 'pages.listings.title',
      owner: 'next-public',
      pattern: '/listings',
      routeKey: 'listings',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/listings"
        route={route}
        routeSegments={['listings']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Listings');
    expect(html).toContain('Browse public offers and requests from Hour Timebank.');
    expect(html).toContain('href="/hour-timebank/listings"');
    expect(html).toContain('AGPL-3.0-or-later');
  });

  it('renders public API collection content as crawler-readable links', () => {
    const tenant: TenantBootstrap = {
      default_language: 'en',
      id: 2,
      name: 'Hour Timebank',
      slug: 'hour-timebank',
    };
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
          items: [
            {
              description: 'Bring something small to repair with neighbours.',
              id: '42',
              title: 'Repair cafe',
            },
          ],
          kind: 'public-collection',
        }}
        route={route}
        routeSegments={['events']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Repair cafe');
    expect(html).toContain('Bring something small to repair with neighbours.');
    expect(html).toContain('href="/hour-timebank/events/42"');
    expect(html).toContain('data-nexus-ui="rich-index-card"');
    expect(html).not.toContain('post-list');
  });

  it('renders public API detail content as crawler-readable HTML', () => {
    const tenant: TenantBootstrap = {
      default_language: 'en',
      id: 2,
      name: 'Hour Timebank',
      slug: 'hour-timebank',
    };
    const route: RouteOwnership = {
      labelKey: 'pages.eventDetail.title',
      owner: 'next-public',
      params: { id: '42' },
      pattern: '/events/:id',
      routeKey: 'eventDetail',
    };

    const html = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/events/42"
        content={{
          item: {
            description: 'Bring something small to repair with neighbours.',
            id: '42',
            title: 'Repair cafe',
          },
          kind: 'public-detail',
        }}
        route={route}
        routeSegments={['events', '42']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(html).toContain('Repair cafe');
    expect(html).toContain('Bring something small to repair with neighbours.');
    expect(html).toContain('data-slot="card"');
    expect(html).toContain('data-nexus-ui="react-public-detail"');
    expect(html).toContain('legal-content');
  });

  it('renders blog index and content pages with React public prose layout hooks', () => {
    const tenant: TenantBootstrap = {
      default_language: 'en',
      id: 2,
      name: 'Hour Timebank',
      slug: 'hour-timebank',
    };
    const blogRoute: RouteOwnership = {
      labelKey: 'pages.blog.title',
      owner: 'next-public',
      pattern: '/blog',
      routeKey: 'blog-index',
    };

    const indexHtml = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/blog"
        content={{
          kind: 'blog-index',
          posts: [
            {
              author_name: 'Community Team',
              excerpt: 'A public update for neighbours.',
              published_at: '2026-06-01T10:00:00+00:00',
              slug: 'public-update',
              title: 'Public update',
            },
          ],
        }}
        route={blogRoute}
        routeSegments={['blog']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(indexHtml).toContain('data-nexus-ui="react-blog-card"');
    expect(indexHtml).toContain('href="/hour-timebank/blog/public-update"');
    expect(indexHtml).toContain('grid gap-4 sm:grid-cols-2 lg:grid-cols-3');

    const detailRoute: RouteOwnership = {
      labelKey: 'pages.blogDetail.title',
      owner: 'next-public',
      params: { slug: 'public-update' },
      pattern: '/blog/:slug',
      routeKey: 'blog-detail',
    };
    const detailHtml = renderToStaticMarkup(
      <PublicPage
        canonicalUrl="https://app.project-nexus.ie/hour-timebank/blog/public-update"
        content={{
          kind: 'blog-detail',
          post: {
            author_name: 'Community Team',
            content: '<p>A richer public update for neighbours.</p>',
            excerpt: 'A public update for neighbours.',
            published_at: '2026-06-01T10:00:00+00:00',
            slug: 'public-update',
            title: 'Public update',
          },
        }}
        route={detailRoute}
        routeSegments={['blog', 'public-update']}
        tenant={tenant}
        tenantBasePath="/hour-timebank"
        t={createTranslator('en')}
      />,
    );

    expect(detailHtml).toContain('data-nexus-ui="react-blog-detail"');
    expect(detailHtml).toContain('legal-content');
    expect(detailHtml).toContain('A richer public update for neighbours.');
  });
});
