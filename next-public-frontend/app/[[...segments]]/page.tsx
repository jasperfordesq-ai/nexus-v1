// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import type { ReactNode } from 'react';

import { createTranslator } from '../../src/lib/i18n';
import { buildCanonicalUrl, buildPageTitle } from '../../src/lib/metadata';
import { getRouteOwnership, type RouteOwnership } from '../../src/lib/public-routes';
import {
  fetchBlogPost,
  fetchBlogPosts,
  fetchCmsPage,
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

  if (context.route.owner !== 'next-public' || context.tenantBootstrap.status === 'not-found') {
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

  return {
    alternates: {
      canonical: context.canonicalUrl,
    },
    description,
    title: buildPageTitle({
      pageLabel,
      platformName: t('brand.platformName'),
      tenantName: context.tenant?.name,
    }),
  };
}

export default async function PublicRoutePage({ params }: PublicPageProps): Promise<ReactNode> {
  const context = await buildRouteContext((await params).segments ?? []);

  if (context.route.owner !== 'next-public' || context.tenantBootstrap.status === 'not-found') {
    notFound();
  }

  const t = createTranslator(context.tenant?.default_language ?? 'en');
  const content = await fetchRouteContent(context.route, context.request, context.tenant);

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

  return null;
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

  if (tenant?.seo?.description) {
    return tenant.seo.description;
  }

  return t(`pages.${route.routeKey}.lead`, {
    tenantName: tenant?.name ?? t('brand.platformName'),
  });
}
