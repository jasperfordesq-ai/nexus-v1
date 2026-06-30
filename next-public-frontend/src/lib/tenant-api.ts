// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { resolveAssetUrl } from './assets';
import { buildTenantBootstrapRequest, type ResolvedTenantRequest } from './tenant-request';
import { getPublicEndpointForRoute } from './content-sources';

export interface TenantBranding {
  logo_url?: string;
  og_image_url?: string;
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
    h1_headline?: string;
    hero_intro?: string;
    meta_description?: string;
    meta_title?: string;
    robots_directive?: string;
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

export interface PublicEventImage {
  altText: string;
  url: string;
}

export interface PublicEventCategory {
  id: string | null;
  name: string | null;
  slug: string | null;
}

export interface PublicEventLocation {
  label: string | null;
  latitude: number | null;
  longitude: number | null;
}

export interface PublicEventOrganiser {
  displayName: string | null;
  id: string | null;
}

export interface PublicEvent {
  category: PublicEventCategory | null;
  createdAt: string | null;
  description: string;
  endAt: string | null;
  excerpt: string;
  id: string;
  location: PublicEventLocation;
  organiser: PublicEventOrganiser;
  primaryImage: PublicEventImage | null;
  slug: string;
  startAt: string | null;
  status: string;
  title: string;
  updatedAt: string | null;
}

export interface PublicEventsIndex {
  events: PublicEvent[];
  pagination: PublicListingsPagination;
}

export interface PublicJobImage {
  altText: string;
  sortOrder?: number;
  url: string;
}

export interface PublicJobCategory {
  name: string | null;
  slug: string | null;
}

export interface PublicJobLocation {
  isRemote: boolean;
  label: string | null;
  latitude: number | null;
  longitude: number | null;
}

export interface PublicJobEmployer {
  displayName: string | null;
  id: string | null;
  logoUrl: string | null;
}

export interface PublicJobCompensation {
  hoursPerWeek: number | null;
  salaryCurrency: string | null;
  salaryMax: number | null;
  salaryMin: number | null;
  salaryNegotiable: boolean;
  salaryType: string | null;
  timeCredits: number | null;
}

export interface PublicJob {
  category: PublicJobCategory | null;
  commitment: string | null;
  compensation: PublicJobCompensation;
  createdAt: string | null;
  deadlineAt: string | null;
  description: string;
  employer: PublicJobEmployer;
  excerpt: string;
  gallery: PublicJobImage[];
  id: string;
  jobType: string | null;
  location: PublicJobLocation;
  primaryImage: PublicJobImage | null;
  skills: string[];
  slug: string;
  status: string;
  title: string;
  updatedAt: string | null;
}

export interface PublicJobsIndex {
  jobs: PublicJob[];
  pagination: PublicListingsPagination;
}

export interface PublicMarketplaceImage {
  altText: string;
  sortOrder?: number;
  url: string;
}

export interface PublicMarketplaceCategory {
  id: string | null;
  name: string | null;
  slug: string | null;
}

export interface PublicMarketplaceLocation {
  label: string | null;
  latitude: number | null;
  longitude: number | null;
}

export interface PublicMarketplacePrice {
  amount: number | null;
  currency: string | null;
  priceType: string;
  timeCredits: number | null;
}

export interface PublicMarketplaceSeller {
  avatarUrl: string | null;
  displayName: string | null;
  id: string | null;
  isVerified: boolean;
  sellerType: string | null;
}

export interface PublicMarketplaceDelivery {
  localPickup: boolean | null;
  method: string | null;
  shippingAvailable: boolean | null;
}

export interface PublicMarketplaceListing {
  category: PublicMarketplaceCategory | null;
  condition: string | null;
  createdAt: string | null;
  delivery: PublicMarketplaceDelivery;
  description: string;
  excerpt: string;
  expiresAt: string | null;
  gallery: PublicMarketplaceImage[];
  id: string;
  location: PublicMarketplaceLocation;
  price: PublicMarketplacePrice;
  primaryImage: PublicMarketplaceImage | null;
  quantity: number | null;
  seller: PublicMarketplaceSeller;
  slug: string;
  status: string;
  title: string;
  updatedAt: string | null;
}

export interface PublicMarketplaceIndex {
  items: PublicMarketplaceListing[];
  pagination: PublicListingsPagination;
}

export interface PublicOrganisationImage {
  altText: string;
  url: string;
}

export interface PublicOrganisationLocation {
  label: string | null;
}

export interface PublicOrganisationOwner {
  avatarUrl: string | null;
  displayName: string | null;
  id: string | null;
}

export interface PublicOrganisationStats {
  averageRating: number;
  opportunityCount: number;
  reviewCount: number;
  totalHours: number;
  volunteerCount: number;
}

export interface PublicOrganisation {
  contactEmail: string | null;
  createdAt: string | null;
  description: string;
  excerpt: string;
  id: string;
  location: PublicOrganisationLocation;
  logoImage: PublicOrganisationImage | null;
  name: string;
  orgType: string | null;
  owner: PublicOrganisationOwner;
  slug: string;
  stats: PublicOrganisationStats;
  status: string;
  updatedAt: string | null;
  website: string | null;
}

export interface PublicOrganisationsIndex {
  organisations: PublicOrganisation[];
  pagination: PublicListingsPagination;
}

export type PublicRouteContent =
  | { kind: 'blog-detail'; post: BlogPost | null }
  | { kind: 'blog-index'; posts: BlogPostSummary[] }
  | { kind: 'cms-page'; page: CmsPage | null }
  | { events: PublicEventsIndex; kind: 'events-index' }
  | { event: PublicEvent | null; kind: 'event-detail' }
  | { job: PublicJob | null; kind: 'job-detail' }
  | { jobs: PublicJobsIndex; kind: 'jobs-index' }
  | { kind: 'listing-detail'; listing: PublicListing | null }
  | { kind: 'listings-index'; listings: PublicListingsIndex }
  | { item: PublicMarketplaceListing | null; kind: 'marketplace-detail' }
  | { items: PublicMarketplaceIndex; kind: 'marketplace-index' }
  | { kind: 'organisation-detail'; organisation: PublicOrganisation | null }
  | { kind: 'organisations-index'; organisations: PublicOrganisationsIndex }
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

export async function fetchEventsIndex(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicEventsIndex> {
  const endpoint = getPublicEndpointForRoute('events') ?? '/v2/events';
  const url = buildApiUrl(endpoint, { per_page: '12' });
  const response = await fetchApiResponse<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));
  const events = normalizePublicEvents(response?.data);

  return {
    events,
    pagination: normalizeListingsPagination(response?.meta, events.length),
  };
}

export async function fetchJobsIndex(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicJobsIndex> {
  const endpoint = getPublicEndpointForRoute('jobs') ?? '/v2/jobs';
  const url = buildApiUrl(endpoint, { per_page: '12' });
  const response = await fetchApiResponse<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));
  const jobs = normalizePublicJobs(response?.data);

  return {
    jobs,
    pagination: normalizeListingsPagination(response?.meta, jobs.length),
  };
}

export async function fetchMarketplaceIndex(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicMarketplaceIndex> {
  const endpoint = getPublicEndpointForRoute('marketplace') ?? '/v2/marketplace/listings';
  const url = buildApiUrl(endpoint, { limit: '12' });
  const response = await fetchApiResponse<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));
  const items = normalizePublicMarketplaceListings(response?.data);

  return {
    items,
    pagination: normalizeListingsPagination(response?.meta, items.length),
  };
}

export async function fetchOrganisationsIndex(
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicOrganisationsIndex> {
  const endpoint = getPublicEndpointForRoute('organisations') ?? '/v2/volunteering/organisations';
  const url = buildApiUrl(endpoint, { per_page: '12' });
  const response = await fetchApiResponse<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));
  const organisations = normalizePublicOrganisations(response?.data);

  return {
    organisations,
    pagination: normalizeListingsPagination(response?.meta, organisations.length),
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

export async function fetchEventDetail(
  id: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicEvent | null> {
  const endpoint = getPublicEndpointForRoute('eventDetail', { id });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));

  return normalizePublicEvent(payload);
}

export async function fetchJobDetail(
  id: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicJob | null> {
  const endpoint = getPublicEndpointForRoute('jobDetail', { id });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));

  return normalizePublicJob(payload);
}

export async function fetchMarketplaceDetail(
  id: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicMarketplaceListing | null> {
  const endpoint = getPublicEndpointForRoute('marketplaceDetail', { id });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));

  return normalizePublicMarketplaceListing(payload);
}

export async function fetchOrganisationDetail(
  id: string,
  request: ResolvedTenantRequest,
  tenant: TenantBootstrap | null,
): Promise<PublicOrganisation | null> {
  const endpoint = getPublicEndpointForRoute('organisationDetail', { id });

  if (!endpoint) {
    return null;
  }

  const url = buildApiUrl(endpoint);
  const payload = await fetchApiPayload<unknown>(url, buildPublicHeaders(request, tenant, { publicContract: true }));

  return normalizePublicOrganisation(payload);
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

function normalizePublicEvents(payload: unknown): PublicEvent[] {
  return extractPublicItemArray(payload)
    .map(normalizePublicEvent)
    .filter((event): event is PublicEvent => event !== null);
}

function normalizePublicEvent(payload: unknown): PublicEvent | null {
  if (!isRecord(payload)) {
    return null;
  }

  const contract = isRecord(payload.public_contract) ? payload.public_contract : payload;
  const id = firstString(contract.id, contract.slug);
  const title = firstString(contract.title, contract.name);

  if (!id || !title) {
    return null;
  }

  return {
    category: normalizeEventCategory(contract.category),
    createdAt: firstString(contract.created_at) ?? null,
    description: firstString(contract.description) ?? '',
    endAt: firstString(contract.end_at) ?? null,
    excerpt: firstString(contract.excerpt, contract.description) ?? '',
    id,
    location: normalizeEventLocation(contract.location),
    organiser: normalizeEventOrganiser(contract.organiser),
    primaryImage: normalizeEventImage(contract.primary_image),
    slug: firstString(contract.slug, id) ?? id,
    startAt: firstString(contract.start_at) ?? null,
    status: firstString(contract.status) ?? 'active',
    title,
    updatedAt: firstString(contract.updated_at) ?? null,
  };
}

function normalizeEventImage(value: unknown): PublicEventImage | null {
  if (!isRecord(value)) {
    return null;
  }

  const url = resolveAssetUrl(firstString(value.url), getApiBase());

  if (!url) {
    return null;
  }

  return {
    altText: firstString(value.alt_text, value.altText) ?? '',
    url,
  };
}

function normalizeEventCategory(value: unknown): PublicEventCategory | null {
  if (!isRecord(value)) {
    return null;
  }

  return {
    id: firstString(value.id) ?? null,
    name: firstString(value.name) ?? null,
    slug: firstString(value.slug) ?? null,
  };
}

function normalizeEventLocation(value: unknown): PublicEventLocation {
  const location = isRecord(value) ? value : {};

  return {
    label: firstString(location.label) ?? null,
    latitude: firstNumber(location.latitude) ?? null,
    longitude: firstNumber(location.longitude) ?? null,
  };
}

function normalizeEventOrganiser(value: unknown): PublicEventOrganiser {
  const organiser = isRecord(value) ? value : {};

  return {
    displayName: firstString(organiser.display_name, organiser.displayName) ?? null,
    id: firstString(organiser.id) ?? null,
  };
}

function normalizePublicJobs(payload: unknown): PublicJob[] {
  return extractPublicItemArray(payload)
    .map(normalizePublicJob)
    .filter((job): job is PublicJob => job !== null);
}

function normalizePublicJob(payload: unknown): PublicJob | null {
  if (!isRecord(payload)) {
    return null;
  }

  const contract = isRecord(payload.public_contract) ? payload.public_contract : payload;
  const id = firstString(contract.id, contract.slug);
  const title = firstString(contract.title);

  if (!id || !title) {
    return null;
  }

  const gallery = normalizeJobGallery(contract.gallery);
  const primaryImage = normalizeJobImage(contract.primary_image) ?? gallery[0] ?? null;

  return {
    category: normalizeJobCategory(contract.category),
    commitment: firstString(contract.commitment) ?? null,
    compensation: normalizeJobCompensation(contract.compensation),
    createdAt: firstString(contract.created_at) ?? null,
    deadlineAt: firstString(contract.deadline_at) ?? null,
    description: firstString(contract.description) ?? '',
    employer: normalizeJobEmployer(contract.employer),
    excerpt: firstString(contract.excerpt, contract.description) ?? '',
    gallery,
    id,
    jobType: firstString(contract.job_type, contract.jobType) ?? null,
    location: normalizeJobLocation(contract.location),
    primaryImage,
    skills: normalizeStringList(contract.skills),
    slug: firstString(contract.slug, id) ?? id,
    status: firstString(contract.status) ?? 'open',
    title,
    updatedAt: firstString(contract.updated_at) ?? null,
  };
}

function normalizeJobGallery(value: unknown): PublicJobImage[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.map(normalizeJobImage).filter((image): image is PublicJobImage => image !== null);
}

function normalizeJobImage(value: unknown): PublicJobImage | null {
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

function normalizeJobCategory(value: unknown): PublicJobCategory | null {
  if (!isRecord(value)) {
    return null;
  }

  return {
    name: firstString(value.name) ?? null,
    slug: firstString(value.slug) ?? null,
  };
}

function normalizeJobLocation(value: unknown): PublicJobLocation {
  const location = isRecord(value) ? value : {};

  return {
    isRemote: firstBoolean(location.is_remote, location.isRemote) ?? false,
    label: firstString(location.label) ?? null,
    latitude: firstNumber(location.latitude) ?? null,
    longitude: firstNumber(location.longitude) ?? null,
  };
}

function normalizeJobEmployer(value: unknown): PublicJobEmployer {
  const employer = isRecord(value) ? value : {};
  const logoUrl = resolveAssetUrl(firstString(employer.logo_url, employer.logoUrl), getApiBase()) ?? null;

  return {
    displayName: firstString(employer.display_name, employer.displayName) ?? null,
    id: firstString(employer.id) ?? null,
    logoUrl,
  };
}

function normalizeJobCompensation(value: unknown): PublicJobCompensation {
  const compensation = isRecord(value) ? value : {};

  return {
    hoursPerWeek: firstNumber(compensation.hours_per_week, compensation.hoursPerWeek) ?? null,
    salaryCurrency: firstString(compensation.salary_currency, compensation.salaryCurrency) ?? null,
    salaryMax: firstNumber(compensation.salary_max, compensation.salaryMax) ?? null,
    salaryMin: firstNumber(compensation.salary_min, compensation.salaryMin) ?? null,
    salaryNegotiable: firstBoolean(compensation.salary_negotiable, compensation.salaryNegotiable) ?? false,
    salaryType: firstString(compensation.salary_type, compensation.salaryType) ?? null,
    timeCredits: firstNumber(compensation.time_credits, compensation.timeCredits) ?? null,
  };
}

function normalizePublicMarketplaceListings(payload: unknown): PublicMarketplaceListing[] {
  return extractPublicItemArray(payload)
    .map(normalizePublicMarketplaceListing)
    .filter((item): item is PublicMarketplaceListing => item !== null);
}

function normalizePublicMarketplaceListing(payload: unknown): PublicMarketplaceListing | null {
  if (!isRecord(payload)) {
    return null;
  }

  const contract = isRecord(payload.public_contract) ? payload.public_contract : payload;
  const id = firstString(contract.id, contract.slug);
  const title = firstString(contract.title);

  if (!id || !title) {
    return null;
  }

  const gallery = normalizeMarketplaceGallery(contract.gallery);
  const primaryImage = normalizeMarketplaceImage(contract.primary_image) ?? gallery[0] ?? null;

  return {
    category: normalizeMarketplaceCategory(contract.category),
    condition: firstString(contract.condition) ?? null,
    createdAt: firstString(contract.created_at) ?? null,
    delivery: normalizeMarketplaceDelivery(contract.delivery),
    description: firstString(contract.description) ?? '',
    excerpt: firstString(contract.excerpt, contract.description) ?? '',
    expiresAt: firstString(contract.expires_at) ?? null,
    gallery,
    id,
    location: normalizeMarketplaceLocation(contract.location),
    price: normalizeMarketplacePrice(contract.price),
    primaryImage,
    quantity: firstNumber(contract.quantity) ?? null,
    seller: normalizeMarketplaceSeller(contract.seller),
    slug: firstString(contract.slug, id) ?? id,
    status: firstString(contract.status) ?? 'active',
    title,
    updatedAt: firstString(contract.updated_at) ?? null,
  };
}

function normalizeMarketplaceGallery(value: unknown): PublicMarketplaceImage[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.map(normalizeMarketplaceImage).filter((image): image is PublicMarketplaceImage => image !== null);
}

function normalizeMarketplaceImage(value: unknown): PublicMarketplaceImage | null {
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

function normalizeMarketplaceCategory(value: unknown): PublicMarketplaceCategory | null {
  if (!isRecord(value)) {
    return null;
  }

  return {
    id: firstString(value.id) ?? null,
    name: firstString(value.name) ?? null,
    slug: firstString(value.slug) ?? null,
  };
}

function normalizeMarketplaceLocation(value: unknown): PublicMarketplaceLocation {
  const location = isRecord(value) ? value : {};

  return {
    label: firstString(location.label) ?? null,
    latitude: firstNumber(location.latitude) ?? null,
    longitude: firstNumber(location.longitude) ?? null,
  };
}

function normalizeMarketplacePrice(value: unknown): PublicMarketplacePrice {
  const price = isRecord(value) ? value : {};

  return {
    amount: firstNumber(price.amount) ?? null,
    currency: firstString(price.currency) ?? null,
    priceType: firstString(price.price_type, price.priceType) ?? 'fixed',
    timeCredits: firstNumber(price.time_credits, price.timeCredits) ?? null,
  };
}

function normalizeMarketplaceSeller(value: unknown): PublicMarketplaceSeller {
  const seller = isRecord(value) ? value : {};
  const avatarUrl = resolveAssetUrl(firstString(seller.avatar_url, seller.avatarUrl), getApiBase()) ?? null;

  return {
    avatarUrl,
    displayName: firstString(seller.display_name, seller.displayName) ?? null,
    id: firstString(seller.id) ?? null,
    isVerified: firstBoolean(seller.is_verified, seller.isVerified) ?? false,
    sellerType: firstString(seller.seller_type, seller.sellerType) ?? null,
  };
}

function normalizeMarketplaceDelivery(value: unknown): PublicMarketplaceDelivery {
  const delivery = isRecord(value) ? value : {};

  return {
    localPickup: firstBoolean(delivery.local_pickup, delivery.localPickup) ?? null,
    method: firstString(delivery.method) ?? null,
    shippingAvailable: firstBoolean(delivery.shipping_available, delivery.shippingAvailable) ?? null,
  };
}

function normalizePublicOrganisations(payload: unknown): PublicOrganisation[] {
  return extractPublicItemArray(payload)
    .map(normalizePublicOrganisation)
    .filter((organisation): organisation is PublicOrganisation => organisation !== null);
}

function normalizePublicOrganisation(payload: unknown): PublicOrganisation | null {
  if (!isRecord(payload)) {
    return null;
  }

  const contract = isRecord(payload.public_contract) ? payload.public_contract : payload;
  const id = firstString(contract.id, contract.slug);
  const name = firstString(contract.name, contract.title);

  if (!id || !name) {
    return null;
  }

  return {
    contactEmail: firstString(contract.contact_email, contract.contactEmail) ?? null,
    createdAt: firstString(contract.created_at) ?? null,
    description: firstString(contract.description) ?? '',
    excerpt: firstString(contract.excerpt, contract.description) ?? '',
    id,
    location: normalizeOrganisationLocation(contract.location),
    logoImage: normalizeOrganisationImage(contract.logo_image, name),
    name,
    orgType: firstString(contract.org_type, contract.orgType) ?? null,
    owner: normalizeOrganisationOwner(contract.owner),
    slug: firstString(contract.slug, id) ?? id,
    stats: normalizeOrganisationStats(contract.stats),
    status: firstString(contract.status) ?? 'active',
    updatedAt: firstString(contract.updated_at) ?? null,
    website: firstString(contract.website) ?? null,
  };
}

function normalizeOrganisationImage(value: unknown, fallbackAlt: string): PublicOrganisationImage | null {
  if (!isRecord(value)) {
    return null;
  }

  const url = resolveAssetUrl(firstString(value.url), getApiBase());

  if (!url) {
    return null;
  }

  return {
    altText: firstString(value.alt_text, value.altText) ?? fallbackAlt,
    url,
  };
}

function normalizeOrganisationLocation(value: unknown): PublicOrganisationLocation {
  const location = isRecord(value) ? value : {};

  return {
    label: firstString(location.label) ?? null,
  };
}

function normalizeOrganisationOwner(value: unknown): PublicOrganisationOwner {
  const owner = isRecord(value) ? value : {};
  const avatarUrl = resolveAssetUrl(firstString(owner.avatar_url, owner.avatarUrl), getApiBase()) ?? null;

  return {
    avatarUrl,
    displayName: firstString(owner.display_name, owner.displayName) ?? null,
    id: firstString(owner.id) ?? null,
  };
}

function normalizeOrganisationStats(value: unknown): PublicOrganisationStats {
  const stats = isRecord(value) ? value : {};

  return {
    averageRating: firstNumber(stats.average_rating, stats.averageRating) ?? 0,
    opportunityCount: firstNumber(stats.opportunity_count, stats.opportunityCount) ?? 0,
    reviewCount: firstNumber(stats.review_count, stats.reviewCount) ?? 0,
    totalHours: firstNumber(stats.total_hours, stats.totalHours) ?? 0,
    volunteerCount: firstNumber(stats.volunteer_count, stats.volunteerCount) ?? 0,
  };
}

function normalizeStringList(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.map((item) => firstString(item)).filter((item): item is string => item !== undefined);
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
