// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { resolveAssetUrl } from './assets';
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

export interface PublicListingImage {
  altText: string;
  sortOrder?: number;
  url: string;
}

export interface PublicListingCategory {
  id: string | null;
  name: string | null;
  slug: string | null;
}

export interface PublicListingLocation {
  label: string | null;
  latitude: number | null;
  longitude: number | null;
}

export interface PublicListingTimeCreditValue {
  hours: number | null;
  unit: string;
}

export interface PublicListingProvider {
  displayName: string | null;
  id: string | null;
}

export interface PublicListing {
  category: PublicListingCategory | null;
  createdAt: string | null;
  description: string;
  excerpt: string;
  gallery: PublicListingImage[];
  id: string;
  location: PublicListingLocation;
  primaryImage: PublicListingImage | null;
  provider: PublicListingProvider;
  slug: string;
  status: string;
  timeCreditValue: PublicListingTimeCreditValue;
  title: string;
  type?: string | null;
  updatedAt: string | null;
}

export interface PublicListingsPagination {
  cursor: string | null;
  hasMore: boolean;
  page: number;
  perPage: number;
  total: number;
}

export interface PublicListingsIndex {
  items: PublicListing[];
  pagination: PublicListingsPagination;
}

export type PublicRouteContent =
  | { kind: 'blog-detail'; post: BlogPost | null }
  | { kind: 'blog-index'; posts: BlogPostSummary[] }
  | { kind: 'cms-page'; page: CmsPage | null }
  | { kind: 'listing-detail'; listing: PublicListing | null }
  | { kind: 'listings-index'; listings: PublicListingsIndex }
  | { items: PublicContentItem[]; kind: 'public-collection' }
  | { item: PublicContentItem | null; kind: 'public-detail' };

export type TenantBootstrapResult =
  | { status: 'error'; tenant: null }
  | { status: 'not-found'; tenant: null }
  | { status: 'ok'; tenant: TenantBootstrap };

interface ApiEnvelope<T> {
  data?: T;
  errors?: Array<{ code?: string; message?: string }>;
  meta?: Record<string, unknown>;
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

export async function fetchListingsIndex(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicListingsIndex> {
  const endpoint = getPublicEndpointForRoute('listings') ?? '/v2/listings';
  const url = buildApiUrl(endpoint, { per_page: '12' });
  const response = await fetchApiResponse<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));
  const items = normalizePublicListings(response?.data);

  return {
    items,
    pagination: normalizeListingsPagination(response?.meta, items.length),
  };
}

export async function fetchPublicDetail(
  routeKey: string,
  paramsOrId: Record<string, string> | string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicContentItem | null> {
  const params = typeof paramsOrId === 'string' ? { id: paramsOrId } : paramsOrId;
  const endpoint = getPublicEndpointForRoute(routeKey, params);

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant));

  return normalizePublicItem(payload);
}

export async function fetchListingDetail(
  id: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicListing | null> {
  const endpoint = getPublicEndpointForRoute('listingDetail', { id });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));

  return normalizePublicListing(payload);
}

async function fetchApiEnvelope<T>(url: string, headers: HeadersInit): Promise<T | null> {
  const payload = await fetchApiPayload<T>(url, headers);

  return payload ?? null;
}

async function fetchApiPayload<T>(url: string, headers: HeadersInit): Promise<T | null> {
  const envelope = await fetchApiResponse<T>(url, headers);

  return envelope?.data ?? null;
}

async function fetchApiResponse<T>(url: string, headers: HeadersInit): Promise<ApiEnvelope<T> | null> {
  try {
    const response = await fetch(url, {
      headers,
      next: { revalidate: 300 },
    });

    if (!response.ok) {
      return null;
    }

    const envelope = (await response.json()) as ApiEnvelope<T>;

    return envelope;
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

function normalizePublicListings(payload: unknown): PublicListing[] {
  return extractPublicItemArray(payload)
    .map(normalizePublicListing)
    .filter((listing): listing is PublicListing => listing !== null);
}

function normalizePublicListing(payload: unknown): PublicListing | null {
  if (!isRecord(payload)) {
    return null;
  }

  const contract = isRecord(payload.public_contract) ? payload.public_contract : payload;
  const id = firstString(contract.id, contract.slug);
  const title = firstString(contract.title);

  if (!id || !title) {
    return null;
  }

  const gallery = normalizeListingGallery(contract.gallery);
  const primaryImage = normalizeListingImage(contract.primary_image) ?? gallery[0] ?? null;

  return {
    category: normalizeListingCategory(contract.category),
    createdAt: firstString(contract.created_at) ?? null,
    description: firstString(contract.description) ?? '',
    excerpt: firstString(contract.excerpt, contract.description) ?? '',
    gallery,
    id,
    location: normalizeListingLocation(contract.location),
    primaryImage,
    provider: normalizeListingProvider(contract.provider),
    slug: firstString(contract.slug, id) ?? id,
    status: firstString(contract.status) ?? 'active',
    timeCreditValue: normalizeListingTimeCreditValue(contract.time_credit_value),
    title,
    type: firstString(contract.type) ?? null,
    updatedAt: firstString(contract.updated_at) ?? null,
  };
}

function normalizeListingGallery(value: unknown): PublicListingImage[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.map(normalizeListingImage).filter((image): image is PublicListingImage => image !== null);
}

function normalizeListingImage(value: unknown): PublicListingImage | null {
  if (!isRecord(value)) {
    return null;
  }

  const url = resolveAssetUrl(firstString(value.url), getApiBase());

  if (!url) {
    return null;
  }

  return {
    altText: firstString(value.alt_text, value.altText) ?? '',
    sortOrder: firstNumber(value.sort_order, value.sortOrder),
    url,
  };
}

function normalizeListingCategory(value: unknown): PublicListingCategory | null {
  if (!isRecord(value)) {
    return null;
  }

  return {
    id: firstString(value.id) ?? null,
    name: firstString(value.name) ?? null,
    slug: firstString(value.slug) ?? null,
  };
}

function normalizeListingLocation(value: unknown): PublicListingLocation {
  const location = isRecord(value) ? value : {};

  return {
    label: firstString(location.label) ?? null,
    latitude: firstNumber(location.latitude) ?? null,
    longitude: firstNumber(location.longitude) ?? null,
  };
}

function normalizeListingTimeCreditValue(value: unknown): PublicListingTimeCreditValue {
  const timeValue = isRecord(value) ? value : {};

  return {
    hours: firstNumber(timeValue.hours) ?? null,
    unit: firstString(timeValue.unit) ?? 'hour',
  };
}

function normalizeListingProvider(value: unknown): PublicListingProvider {
  const provider = isRecord(value) ? value : {};

  return {
    displayName: firstString(provider.display_name, provider.displayName) ?? null,
    id: firstString(provider.id) ?? null,
  };
}

function normalizeListingsPagination(meta: Record<string, unknown> | undefined, fallbackTotal: number): PublicListingsPagination {
  return {
    cursor: firstString(meta?.cursor) ?? null,
    hasMore: firstBoolean(meta?.has_more, meta?.hasMore) ?? false,
    page: firstNumber(meta?.page, meta?.current_page) ?? 1,
    perPage: firstNumber(meta?.per_page, meta?.perPage) ?? 12,
    total: firstNumber(meta?.total, meta?.total_items, meta?.totalItems) ?? fallbackTotal,
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

function firstNumber(...values: unknown[]): number | undefined {
  for (const value of values) {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) {
      return Number(value);
    }
  }

  return undefined;
}

function firstBoolean(...values: unknown[]): boolean | undefined {
  for (const value of values) {
    if (typeof value === 'boolean') {
      return value;
    }

    if (typeof value === 'number') {
      return value > 0;
    }

    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();

      if (['1', 'true', 'yes'].includes(normalized)) {
        return true;
      }

      if (['0', 'false', 'no'].includes(normalized)) {
        return false;
      }
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
  options: { publicContract?: boolean } = {},
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

  if (tenant?.default_language) {
    headers['Accept-Language'] = tenant.default_language;
  }

  if (options.publicContract) {
    headers['X-Public-Contract'] = '1';
  }

  return headers;
}
