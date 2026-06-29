// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { buildTenantBootstrapRequest, type ResolvedTenantRequest } from './tenant-request';
import { getPublicEndpointForRoute } from './content-sources';

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

export interface PublicContentItem {
  description?: string;
  id: string;
  slug?: string;
  title: string;
}

export type PublicRouteContent =
  | { kind: 'blog-detail'; post: BlogPost | null }
  | { kind: 'blog-index'; posts: BlogPostSummary[] }
  | { kind: 'cms-page'; page: CmsPage | null }
  | { items: PublicContentItem[]; kind: 'public-collection' }
  | { item: PublicContentItem | null; kind: 'public-detail' };

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
  const endpoint = getPublicEndpointForRoute('blog-index') ?? '/v2/blog';
  const url = buildApiUrl(endpoint, { per_page: '12' });
  const posts = await fetchApiEnvelope<BlogPostSummary[]>(url, buildPublicHeaders(request, tenant));

  return posts ?? [];
}

export async function fetchBlogPost(
  slug: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<BlogPost | null> {
  const endpoint = getPublicEndpointForRoute('blog-detail', { slug });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);

  return fetchApiEnvelope<BlogPost>(url, buildPublicHeaders(request, tenant));
}

export async function fetchCmsPage(
  slug: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<CmsPage | null> {
  const query = tenant?.id && tenant.id > 1 ? { context_tenant: String(tenant.id) } : undefined;
  const endpoint = getPublicEndpointForRoute('cms-page', { slug });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint, query);

  return fetchApiEnvelope<CmsPage>(url, buildPublicHeaders(request, tenant));
}

export async function fetchPublicCollection(
  routeKey: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicContentItem[]> {
  const endpoint = getPublicEndpointForRoute(routeKey);

  if (!endpoint) {
    return [];
  }

  const url = buildApiUrl(endpoint, { per_page: '12' });
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant));

  return normalizePublicItems(payload);
}

export async function fetchPublicDetail(
  routeKey: string,
  id: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicContentItem | null> {
  const endpoint = getPublicEndpointForRoute(routeKey, { id });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant));

  return normalizePublicItem(payload);
}

async function fetchApiEnvelope<T>(url: string, headers: HeadersInit): Promise<T | null> {
  const payload = await fetchApiPayload<T>(url, headers);

  return payload ?? null;
}

async function fetchApiPayload<T>(url: string, headers: HeadersInit): Promise<T | null> {
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

function normalizePublicItems(payload: unknown): PublicContentItem[] {
  const candidates = extractPublicItemArray(payload);

  return candidates.map(normalizePublicItem).filter((item): item is PublicContentItem => item !== null);
}

function extractPublicItemArray(payload: unknown): unknown[] {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (!isRecord(payload)) {
    return [];
  }

  for (const key of ['data', 'items', 'listings', 'events', 'jobs', 'resources', 'articles', 'organisations']) {
    const value = payload[key];

    if (Array.isArray(value)) {
      return value;
    }
  }

  return [];
}

function normalizePublicItem(payload: unknown): PublicContentItem | null {
  if (!isRecord(payload)) {
    return null;
  }

  const id = firstString(payload.id, payload.slug);
  const title = firstString(payload.title, payload.name, payload.subject, payload.heading);

  if (!id || !title) {
    return null;
  }

  return {
    description: firstString(payload.description, payload.excerpt, payload.summary, payload.body, payload.content),
    id,
    slug: firstString(payload.slug),
    title,
  };
}

function firstString(...values: unknown[]): string | undefined {
  for (const value of values) {
    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
      return String(value);
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
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
