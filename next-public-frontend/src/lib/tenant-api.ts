// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { buildTenantBootstrapRequest, type ResolvedTenantRequest } from './tenant-request';

export interface TenantBranding {
  logo_url?: string;
  primary_color?: string;
  secondary_color?: string;
}

export interface TenantBootstrap {
  accessible_domain?: string;
  branding?: TenantBranding;
  default_language?: string;
  domain?: string;
  features?: Record<string, unknown>;
  id: number;
  menu_pages?: Record<string, Array<{ slug: string; title: string }>>;
  modules?: Record<string, unknown>;
  name: string;
  seo?: {
    description?: string;
    title?: string;
  };
  slug: string;
  tagline?: string;
}

export interface BlogPostSummary {
  author_name?: string;
  excerpt?: string;
  id?: number;
  published_at?: string;
  slug: string;
  title: string;
}

export interface BlogPost extends BlogPostSummary {
  content?: string;
  meta_description?: string;
}

export interface CmsPage {
  content?: string;
  id: number;
  meta_description?: string;
  slug: string;
  title: string;
  updated_at?: string;
}

export type PublicRouteContent =
  | { kind: 'blog-detail'; post: BlogPost | null }
  | { kind: 'blog-index'; posts: BlogPostSummary[] }
  | { kind: 'cms-page'; page: CmsPage | null };

export type TenantBootstrapResult =
  | { status: 'error'; tenant: null }
  | { status: 'not-found'; tenant: null }
  | { status: 'ok'; tenant: TenantBootstrap };

interface ApiEnvelope<T> {
  data?: T;
  errors?: Array<{ code?: string; message?: string }>;
}

const defaultApiBase = 'https://api.project-nexus.ie/api';

export function getApiBase(): string {
  return (process.env.NEXUS_API_BASE ?? process.env.NEXT_PUBLIC_API_BASE ?? defaultApiBase).replace(/\/+$/, '');
}

export async function fetchTenantBootstrap(request: ResolvedTenantRequest): Promise<TenantBootstrapResult> {
  const bootstrapRequest = buildTenantBootstrapRequest({
    apiBase: getApiBase(),
    origin: request.origin,
    tenantSlug: request.tenantSlug,
  });

  try {
    const response = await fetch(bootstrapRequest.url, {
      headers: bootstrapRequest.headers,
      next: { revalidate: 300 },
    });

    if (response.status === 404) {
      return { status: 'not-found', tenant: null };
    }

    if (!response.ok) {
      return { status: 'error', tenant: null };
    }

    const envelope = (await response.json()) as ApiEnvelope<TenantBootstrap>;

    return envelope.data ? { status: 'ok', tenant: envelope.data } : { status: 'error', tenant: null };
  } catch {
    return { status: 'error', tenant: null };
  }
}

export async function fetchBlogPosts(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<BlogPostSummary[]> {
  const url = buildApiUrl('/v2/blog', { per_page: '12' });
  const posts = await fetchApiEnvelope<BlogPostSummary[]>(url, buildPublicHeaders(request, tenant));

  return posts ?? [];
}

export async function fetchBlogPost(
  slug: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<BlogPost | null> {
  const url = buildApiUrl(`/v2/blog/${encodeURIComponent(slug)}`);

  return fetchApiEnvelope<BlogPost>(url, buildPublicHeaders(request, tenant));
}

export async function fetchCmsPage(
  slug: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<CmsPage | null> {
  const query = tenant?.id && tenant.id > 1 ? { context_tenant: String(tenant.id) } : undefined;
  const url = buildApiUrl(`/v2/pages/${encodeURIComponent(slug)}`, query);

  return fetchApiEnvelope<CmsPage>(url, buildPublicHeaders(request, tenant));
}

async function fetchApiEnvelope<T>(url: string, headers: HeadersInit): Promise<T | null> {
  try {
    const response = await fetch(url, {
      headers,
      next: { revalidate: 300 },
    });

    if (!response.ok) {
      return null;
    }

    const envelope = (await response.json()) as ApiEnvelope<T>;

    return envelope.data ?? null;
  } catch {
    return null;
  }
}

function buildApiUrl(path: string, query: Record<string, string> = {}): string {
  const url = new URL(`${getApiBase()}${path.startsWith('/') ? path : `/${path}`}`);

  for (const [key, value] of Object.entries(query)) {
    url.searchParams.set(key, value);
  }

  return url.toString();
}

function buildPublicHeaders(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): HeadersInit {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    Origin: request.origin,
  };

  if (request.tenantSlug) {
    headers['X-Tenant-Slug'] = request.tenantSlug;
  } else if (tenant?.slug) {
    headers['X-Tenant-Slug'] = tenant.slug;
  }

  return headers;
}
