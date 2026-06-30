// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import type { ReactNode } from 'react';

import { resolveAssetUrl } from '../../src/lib/assets';
import { createTranslator } from '../../src/lib/i18n';
import { buildEventMetadata } from '../../src/lib/events-seo';
import { buildJobMetadata } from '../../src/lib/jobs-seo';
import { buildListingMetadata } from '../../src/lib/listings-seo';
import { buildMarketplaceMetadata } from '../../src/lib/marketplace-seo';
import {
  buildCanonicalUrl,
  buildMetadataAlternates,
  buildPageTitle,
  formatOpenGraphLocale,
} from '../../src/lib/metadata';
import { buildOrganisationMetadata } from '../../src/lib/organisations-seo';
import { isRouteEnabledForTenant } from '../../src/lib/module-gates';
import { getRouteOwnership, type RouteOwnership } from '../../src/lib/public-routes';
import {
  fetchEventDetail,
  fetchEventsIndex,
  fetchJobDetail,
  fetchJobsIndex,
  fetchMarketplaceDetail,
  fetchMarketplaceIndex,
  fetchOrganisationDetail,
  fetchOrganisationsIndex,
  fetchListingDetail,
  fetchListingsIndex,
  fetchBlogPost,
  fetchBlogPosts,
  fetchCmsPage,
  fetchPublicCollection,
  fetchPublicDetail,
  fetchTenantBootstrap,
  getApiBase,
  type PublicRouteContent,
  type TenantBootstrapResult,
  type TenantBootstrap,
} from '../../src/lib/tenant-api';
import { resolveTenantRequest, type ResolvedTenantRequest } from '../../src/lib/tenant-request';
import { PublicPage } from '../../src/ui/PublicPage';
import { PublicChrome } from '../../src/ui/PublicChrome';
import { FaqHost } from '../../src/ui/FaqHost';

export const dynamic = 'force-dynamic';

interface PublicPageParams {
  segments?: string[];
}

interface PublicPageProps {
  params: Promise<PublicPageParams>;
}

export async function generateMetadata({ params }: PublicPageProps): Promise<Metadata> {
  const context = await buildRouteContext((await params).segments ?? []);
  const locale = context.tenant?.default_language ?? 'en';
  const t = createTranslator(locale);

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
  const fallbackImageUrl = getTenantSocialImageUrl(context.tenant);
  const title = buildPageTitle({
    pageLabel,
    platformName: t('brand.platformName'),
    tenantName: context.tenant?.name,
  });

  if (content?.kind === 'listing-detail' && content.listing) {
    return buildListingMetadata({
      canonicalUrl: context.canonicalUrl,
      fallbackImageUrl,
      listing: content.listing,
      locale,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  if (content?.kind === 'event-detail' && content.event) {
    return buildEventMetadata({
      canonicalUrl: context.canonicalUrl,
      event: content.event,
      fallbackImageUrl,
      locale,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  if (content?.kind === 'job-detail' && content.job) {
    return buildJobMetadata({
      canonicalUrl: context.canonicalUrl,
      fallbackImageUrl,
      job: content.job,
      locale,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  if (content?.kind === 'marketplace-detail' && content.item) {
    return buildMarketplaceMetadata({
      canonicalUrl: context.canonicalUrl,
      fallbackImageUrl,
      item: content.item,
      locale,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  if (content?.kind === 'organisation-detail' && content.organisation) {
    return buildOrganisationMetadata({
      canonicalUrl: context.canonicalUrl,
      fallbackImageUrl,
      locale,
      organisation: content.organisation,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    });
  }

  return {
    alternates: buildMetadataAlternates({ canonicalUrl: context.canonicalUrl, locale }),
    description,
    openGraph: {
      description,
      images: fallbackImageUrl ? [fallbackImageUrl] : undefined,
      locale: formatOpenGraphLocale(locale),
      title,
      type: 'website',
      url: context.canonicalUrl,
    },
    title,
    twitter: {
      card: fallbackImageUrl ? 'summary_large_image' : 'summary',
      description,
      images: fallbackImageUrl ? [fallbackImageUrl] : undefined,
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

  // FAQ is the first page served by the SHARED presentational core (one source of
  // truth, rendered identically here and in the React SPA). Real chrome + shared body.
  if (context.route.routeKey === 'faq') {
    return (
      <PublicChrome
        canonicalUrl={context.canonicalUrl}
        tenant={context.tenant}
        tenantBasePath={context.tenantBasePath}
        t={t}
      >
        <FaqHost
          locale={context.tenant?.default_language ?? 'en'}
          tenantBasePath={context.tenantBasePath}
        />
      </PublicChrome>
    );
  }

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

  if (content?.kind === 'marketplace-detail' && !content.item) {
    notFound();
  }

  if (content?.kind === 'organisation-detail' && !content.organisation) {
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

  if (route.routeKey === 'marketplace') {
    return {
      items: await fetchMarketplaceIndex(request, tenant),
      kind: 'marketplace-index',
    };
  }

  if (route.routeKey === 'marketplaceDetail' && route.params?.id) {
    return {
      item: await fetchMarketplaceDetail(route.params.id, request, tenant),
      kind: 'marketplace-detail',
    };
  }

  if (route.routeKey === 'organisations') {
    return {
      kind: 'organisations-index',
      organisations: await fetchOrganisationsIndex(request, tenant),
    };
  }

  if (route.routeKey === 'organisationDetail' && route.params?.id) {
    return {
      kind: 'organisation-detail',
      organisation: await fetchOrganisationDetail(route.params.id, request, tenant),
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

  if (content?.kind === 'marketplace-detail' && content.item?.title) {
    return content.item.title;
  }

  if (content?.kind === 'organisation-detail' && content.organisation?.name) {
    return content.organisation.name;
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

  if (content?.kind === 'marketplace-detail' && content.item?.excerpt) {
    return content.item.excerpt;
  }

  if (content?.kind === 'organisation-detail' && content.organisation?.excerpt) {
    return content.organisation.excerpt;
  }

  const seoDescription = tenant?.seo?.meta_description ?? tenant?.seo?.description;

  if (seoDescription) {
    return seoDescription;
  }

  return t(`pages.${route.routeKey}.lead`, {
    tenantName: tenant?.name ?? t('brand.platformName'),
  });
}

function getTenantSocialImageUrl(tenant: TenantBootstrap | null): string | undefined {
  return resolveAssetUrl(tenant?.branding?.og_image_url ?? tenant?.branding?.logo_url, getApiBase());
}
