// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import type { ReactNode } from 'react';

import { createTranslator } from '../../src/lib/i18n';
import { buildEventMetadata } from '../../src/lib/events-seo';
import { buildJobMetadata } from '../../src/lib/jobs-seo';
import { buildListingMetadata } from '../../src/lib/listings-seo';
import { buildCanonicalUrl, buildPageTitle } from '../../src/lib/metadata';
import { isRouteEnabledForTenant } from '../../src/lib/module-gates';
import { getRouteOwnership, type RouteOwnership } from '../../src/lib/public-routes';
import {
  fetchEventDetail,
  fetchEventsIndex,
  fetchJobDetail,
  fetchJobsIndex,
  fetchListingDetail,
  fetchListingsIndex,
  fetchBlogPost,
  fetchBlogPosts,
  fetchCmsPage,
  fetchPublicCollection,
  fetchPublicDetail,
  fetchTenantBootstrap,
  type PublicRouteContent,
  type TenantBootstrapResult,
  type TenantBootstrap,
} from '../../src/lib/tenant-api';
import { resolveTenantRequest, type ResolvedTenantRequest } from '../../src/lib/tenant-request';
import { PublicPage } from '../../src/ui/PublicPage';

export const dynamic = 'force-dynamic';

interface PublicPageParams {
  segments?: string[];
}

interface PublicPageProps {
  params: Promise<PublicPageParams>;
}

export async function generateMetadata({ params }: PublicPageProps): Promise<Metadata> {
  const context = await buildRouteContext((await params).segments ?? []);
  const t = createTranslator(context.tenant?.default_language ?? 'en');

  if (
    context.route.owner !== 'next-public'
    || context.tenantBootstrap.status === 'not-found'
    || isModuleUnavailable(context.route, context.tenant)
  ) {
    return {
      title: buildPageTitle({
        pageLabel: t('pages.notFound.title'),
        platformName: t('brand.platformName'),
      }),
    };
  }

  const content = await fetchRouteContent(context.route, context.request, context.tenant);
  const pageLabel = getMetadataLabel(context.route, content, t);
  const description = getMetadataDescription(context.route, content, context.tenant, t);
  const title = buildPageTitle({
    pageLabel,
    platformName: t('brand.platformName'),
    tenantName: context.tenant?.name,
  });

  if (content?.kind === 'listing-detail' && content.listing) {
    return buildListingMetadata({
      canonicalUrl: context.canonicalUrl,
      listing: content.listing,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  if (content?.kind === 'event-detail' && content.event) {
    return buildEventMetadata({
      canonicalUrl: context.canonicalUrl,
      event: content.event,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  if (content?.kind === 'job-detail' && content.job) {
    return buildJobMetadata({
      canonicalUrl: context.canonicalUrl,
      job: content.job,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  return {
    alternates: {
      canonical: context.canonicalUrl,
    },
    description,
    openGraph: {
      description,
      title,
      type: 'website',
      url: context.canonicalUrl,
    },
    title,
    twitter: {
      card: 'summary',
      description,
      title,
    },
  };
}

export default async function PublicRoutePage({ params }: PublicPageProps): Promise<ReactNode> {
  const context = await buildRouteContext((await params).segments ?? []);

  if (
    context.route.owner !== 'next-public'
    || context.tenantBootstrap.status === 'not-found'
    || isModuleUnavailable(context.route, context.tenant)
  ) {
    notFound();
  }

  const t = createTranslator(context.tenant?.default_language ?? 'en');
  const content = await fetchRouteContent(context.route, context.request, context.tenant);

  if (content?.kind === 'listing-detail' && !content.listing) {
    notFound();
  }

  if (content?.kind === 'event-detail' && !content.event) {
    notFound();
  }

  if (content?.kind === 'job-detail' && !content.job) {
    notFound();
  }

  return (
    <PublicPage
      canonicalUrl={context.canonicalUrl}
      content={content}
      route={context.route}
      routeSegments={context.request.routeSegments}
      tenant={context.tenant}
      tenantBasePath={context.tenantBasePath}
      t={t}
    />
  );
}

async function buildRouteContext(rawSegments: string[]): Promise<{
  canonicalUrl: string;
  request: ResolvedTenantRequest;
  route: RouteOwnership;
  tenantBootstrap: TenantBootstrapResult;
  tenant: TenantBootstrap | null;
  tenantBasePath: string;
}> {
  const headerList = await headers();
  const request = resolveTenantRequest(rawSegments, {
    host: headerList.get('x-forwarded-host') ?? headerList.get('host') ?? undefined,
    protocol: headerList.get('x-forwarded-proto') ?? undefined,
  });
  const route = getRouteOwnership(request.routeSegments);
  const tenantBootstrap =
    route.owner === 'next-public'
      ? await fetchTenantBootstrap(request)
      : ({ status: 'error', tenant: null } satisfies TenantBootstrapResult);
  const tenant = tenantBootstrap.tenant;
  const canonicalUrl = buildCanonicalUrl({
    origin: request.origin,
    routeSegments: request.routeSegments,
    tenantMode: request.tenantMode,
    tenantSlug: request.tenantSlug,
  });

  return {
    canonicalUrl,
    request,
    route,
    tenantBootstrap,
    tenant,
    tenantBasePath: request.tenantMode === 'path' && request.tenantSlug ? `/${request.tenantSlug}` : '',
  };
}

async function fetchRouteContent(
  route: RouteOwnership,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicRouteContent | null> {
  if (route.routeKey === 'blog-index') {
    return {
      kind: 'blog-index',
      posts: await fetchBlogPosts(request, tenant),
    };
  }

  if (route.routeKey === 'blog-detail' && route.params?.slug) {
    return {
      kind: 'blog-detail',
      post: await fetchBlogPost(route.params.slug, request, tenant),
    };
  }

  if (route.routeKey === 'cms-page' && route.params?.slug) {
    return {
      kind: 'cms-page',
      page: await fetchCmsPage(route.params.slug, request, tenant),
    };
  }

  if (route.routeKey === 'listings') {
    return {
      kind: 'listings-index',
      listings: await fetchListingsIndex(request, tenant),
    };
  }

  if (route.routeKey === 'listingDetail' && route.params?.id) {
    return {
      kind: 'listing-detail',
      listing: await fetchListingDetail(route.params.id, request, tenant),
    };
  }

  if (route.routeKey === 'events') {
    return {
      events: await fetchEventsIndex(request, tenant),
      kind: 'events-index',
    };
  }

  if (route.routeKey === 'eventDetail' && route.params?.id) {
    return {
      event: await fetchEventDetail(route.params.id, request, tenant),
      kind: 'event-detail',
    };
  }

  if (route.routeKey === 'jobs') {
    return {
      jobs: await fetchJobsIndex(request, tenant),
      kind: 'jobs-index',
    };
  }

  if (route.routeKey === 'jobDetail' && route.params?.id) {
    return {
      job: await fetchJobDetail(route.params.id, request, tenant),
      kind: 'job-detail',
    };
  }

  if (route.params && Object.keys(route.params).length > 0) {
    return {
      item: await fetchPublicDetail(route.routeKey, route.params, request, tenant),
      kind: 'public-detail',
    };
  }

  const items = await fetchPublicCollection(route.routeKey, request, tenant);

  if (items.length > 0) {
    return {
      items,
      kind: 'public-collection',
    };
  }

  return null;
}

function isModuleUnavailable(route: RouteOwnership, tenant: TenantBootstrap | null): boolean {
  return tenant !== null && !isRouteEnabledForTenant(route, tenant);
}

function getMetadataLabel(
  route: RouteOwnership,
  content: PublicRouteContent | null,
  t: ReturnType<typeof createTranslator>,
): string {
  if (content?.kind === 'blog-detail' && content.post?.title) {
    return content.post.title;
  }

  if (content?.kind === 'cms-page' && content.page?.title) {
    return content.page.title;
  }

  if (content?.kind === 'public-detail' && content.item?.title) {
    return content.item.title;
  }

  if (content?.kind === 'listing-detail' && content.listing?.title) {
    return content.listing.title;
  }

  if (content?.kind === 'event-detail' && content.event?.title) {
    return content.event.title;
  }

  if (content?.kind === 'job-detail' && content.job?.title) {
    return content.job.title;
  }

  return t(route.labelKey ?? 'pages.home.title');
}

function getMetadataDescription(
  route: RouteOwnership,
  content: PublicRouteContent | null,
  tenant: TenantBootstrap | null,
  t: ReturnType<typeof createTranslator>,
): string {
  if (content?.kind === 'blog-detail' && content.post?.meta_description) {
    return content.post.meta_description;
  }

  if (content?.kind === 'cms-page' && content.page?.meta_description) {
    return content.page.meta_description;
  }

  if (content?.kind === 'public-detail' && content.item?.description) {
    return content.item.description;
  }

  if (content?.kind === 'listing-detail' && content.listing?.excerpt) {
    return content.listing.excerpt;
  }

  if (content?.kind === 'event-detail' && content.event?.excerpt) {
    return content.event.excerpt;
  }

  if (content?.kind === 'job-detail' && content.job?.excerpt) {
    return content.job.excerpt;
  }

  if (tenant?.seo?.description) {
    return tenant.seo.description;
  }

  return t(`pages.${route.routeKey}.lead`, {
    tenantName: tenant?.name ?? t('brand.platformName'),
  });
}
