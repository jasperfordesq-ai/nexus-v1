// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
import { Platform } from 'react-native';

export type MarketplacePriceType = 'fixed' | 'negotiable' | 'free' | 'auction' | 'contact';
export type MarketplaceCondition = 'new' | 'like_new' | 'good' | 'fair' | 'poor';
export type MarketplaceDeliveryMethod = 'pickup' | 'shipping' | 'both' | 'community_delivery';

export interface MarketplaceCategory {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  icon?: string | null;
  listing_count: number;
}

export interface MarketplaceUser {
  id: number;
  name: string;
  avatar_url?: string | null;
  is_verified?: boolean;
  member_since?: string | null;
}

export interface MarketplaceImage {
  id?: number;
  url: string;
  thumbnail_url?: string | null;
  alt_text?: string | null;
  is_primary?: boolean;
}

export interface MarketplaceListingItem {
  id: number;
  title: string;
  tagline?: string | null;
  description?: string | null;
  price: number | null;
  price_currency: string;
  price_type: MarketplacePriceType;
  time_credit_price?: number | null;
  condition: MarketplaceCondition | null;
  quantity?: number | null;
  location?: string | null;
  delivery_method: MarketplaceDeliveryMethod | string;
  shipping_available?: boolean;
  local_pickup?: boolean;
  seller_type: string;
  status: string;
  image: MarketplaceImage | null;
  image_count: number;
  images?: MarketplaceImage[];
  video_url?: string | null;
  category?: MarketplaceCategory | null;
  user?: MarketplaceUser | null;
  is_saved: boolean;
  is_own: boolean;
  is_promoted: boolean;
  views_count: number;
  saves_count?: number;
  created_at: string;
  updated_at?: string | null;
  expires_at?: string | null;
  inventory_count?: number | null;
  low_stock_threshold?: number | null;
  is_oversold_protected?: boolean;
}

export interface MarketplaceListingDetail extends MarketplaceListingItem {
  description: string;
  quantity: number;
  shipping_available: boolean;
  local_pickup: boolean;
  images: MarketplaceImage[];
  saves_count: number;
}

export interface MarketplaceOffer {
  id: number;
  amount: number;
  currency: string;
  message?: string | null;
  status: 'pending' | 'accepted' | 'declined' | 'countered' | 'expired' | 'withdrawn';
  counter_amount?: number | null;
  counter_message?: string | null;
  created_at: string;
  listing?: Pick<MarketplaceListingItem, 'id' | 'title' | 'status' | 'price' | 'price_currency' | 'image'>;
  buyer?: MarketplaceUser;
  seller?: MarketplaceUser;
}

export interface MarketplaceOrder {
  id: number;
  order_number: string;
  buyer?: MarketplaceUser;
  seller?: MarketplaceUser;
  listing?: { id: number; title: string; image?: { url: string } | null };
  quantity: number;
  unit_price: number;
  total_price: number;
  currency: string;
  status: string;
  tracking_number?: string | null;
  tracking_url?: string | null;
  shipping_method?: string | null;
  created_at: string;
}

export interface MarketplaceSellerProfile {
  id: number;
  user_id: number;
  display_name: string;
  bio?: string | null;
  avatar_url?: string | null;
  seller_type: string;
  business_name?: string | null;
  business_verified: boolean;
  is_community_endorsed: boolean;
  community_trust_score?: number | null;
  avg_rating?: number | null;
  total_ratings: number;
  total_sales: number;
  active_listings: number;
  member_since?: string | null;
}

export interface MarketplaceDashboard {
  active_listings?: number;
  total_listings?: number;
  total_sales?: number;
  pending_offers?: number;
  views_30d?: number;
  saves_30d?: number;
}

export interface MarketplaceCollectionResponse<T> {
  data: T[];
  meta?: { cursor?: string | null; next_cursor?: string | null; has_more?: boolean };
}

export interface MarketplaceDataResponse<T> {
  data: T;
  meta?: Record<string, unknown> | null;
}

function getUploadFilename(uri: string): string {
  const cleanUri = uri.split('?')[0] ?? uri;
  const lastSegment = cleanUri.split('/').pop();
  return lastSegment && lastSegment.includes('.') ? lastSegment : 'marketplace.jpg';
}

function getMimeType(filename: string, fallback?: string | null): string {
  if (fallback?.startsWith('image/')) return fallback;
  const extension = filename.split('.').pop()?.toLowerCase();
  if (extension === 'png') return 'image/png';
  if (extension === 'webp') return 'image/webp';
  if (extension === 'gif') return 'image/gif';
  return 'image/jpeg';
}

async function appendMarketplaceImageFile(formData: FormData, uri: string, index: number): Promise<void> {
  const filename = getUploadFilename(uri);

  if (Platform.OS === 'web') {
    const response = await fetch(uri);
    const blob = await response.blob();
    const type = getMimeType(filename, blob.type);
    if (typeof File !== 'undefined') {
      formData.append(`image_${index}`, new File([blob], filename, { type }));
      return;
    }
    formData.append(`image_${index}`, blob, filename);
    return;
  }

  const type = getMimeType(filename);
  formData.append(`image_${index}`, { uri, name: filename, type } as unknown as Blob);
}

export interface MarketplaceListingFilters {
  q?: string;
  category_id?: number;
  price_type?: MarketplacePriceType | '';
  condition?: MarketplaceCondition | '';
  delivery_method?: MarketplaceDeliveryMethod | '';
  sort?: 'newest' | 'price_asc' | 'price_desc' | 'popular';
  cursor?: string | null;
  limit?: number;
  user_id?: number;
}

export interface MarketplaceListingPayload {
  title: string;
  description: string;
  tagline?: string | null;
  price?: number | null;
  price_currency?: string | null;
  price_type?: MarketplacePriceType;
  time_credit_price?: number | null;
  category_id?: number | null;
  condition?: MarketplaceCondition | null;
  quantity?: number | null;
  location?: string | null;
  shipping_available?: boolean;
  local_pickup?: boolean;
  delivery_method?: MarketplaceDeliveryMethod;
  seller_type?: 'private' | 'business';
  status?: 'draft' | 'active';
}

function addQueryValue(query: Record<string, string>, key: string, value: unknown): void {
  if (value === undefined || value === null || value === '') return;
  query[key] = String(value);
}

function collectionMeta<T>(response: MarketplaceCollectionResponse<T>) {
  return response.meta ?? {};
}

export function getMarketplaceListings(
  params: MarketplaceListingFilters = {},
): Promise<MarketplaceCollectionResponse<MarketplaceListingItem>> {
  const query: Record<string, string> = {};
  addQueryValue(query, 'q', params.q);
  addQueryValue(query, 'category_id', params.category_id);
  addQueryValue(query, 'price_type', params.price_type);
  addQueryValue(query, 'condition', params.condition);
  addQueryValue(query, 'delivery_method', params.delivery_method);
  addQueryValue(query, 'sort', params.sort);
  addQueryValue(query, 'cursor', params.cursor);
  addQueryValue(query, 'limit', params.limit ?? 20);
  addQueryValue(query, 'user_id', params.user_id);
  return api.get<MarketplaceCollectionResponse<MarketplaceListingItem>>(`${API_V2}/marketplace/listings`, query);
}

export function getFeaturedMarketplaceListings(): Promise<MarketplaceDataResponse<MarketplaceListingItem[]>> {
  return api.get<MarketplaceDataResponse<MarketplaceListingItem[]>>(`${API_V2}/marketplace/listings/featured`);
}

export function getFreeMarketplaceListings(
  cursor?: string | null,
): Promise<MarketplaceCollectionResponse<MarketplaceListingItem>> {
  const query: Record<string, string> = {};
  addQueryValue(query, 'cursor', cursor);
  return api.get<MarketplaceCollectionResponse<MarketplaceListingItem>>(`${API_V2}/marketplace/listings/free`, query);
}

export function getMarketplaceCategories(): Promise<MarketplaceDataResponse<MarketplaceCategory[]>> {
  return api.get<MarketplaceDataResponse<MarketplaceCategory[]>>(`${API_V2}/marketplace/categories`);
}

export function getMarketplaceListing(id: number): Promise<MarketplaceDataResponse<MarketplaceListingDetail>> {
  return api.get<MarketplaceDataResponse<MarketplaceListingDetail>>(`${API_V2}/marketplace/listings/${id}`);
}

export function createMarketplaceListing(
  payload: MarketplaceListingPayload,
): Promise<MarketplaceDataResponse<MarketplaceListingDetail>> {
  return api.post<MarketplaceDataResponse<MarketplaceListingDetail>>(`${API_V2}/marketplace/listings`, payload);
}

export function updateMarketplaceListing(
  id: number,
  payload: Partial<MarketplaceListingPayload>,
): Promise<MarketplaceDataResponse<MarketplaceListingDetail>> {
  return api.put<MarketplaceDataResponse<MarketplaceListingDetail>>(`${API_V2}/marketplace/listings/${id}`, payload);
}

export function deleteMarketplaceListing(id: number): Promise<void> {
  return api.delete<void>(`${API_V2}/marketplace/listings/${id}`);
}

export function saveMarketplaceListing(id: number): Promise<void> {
  return api.post<void>(`${API_V2}/marketplace/listings/${id}/save`);
}

export function unsaveMarketplaceListing(id: number): Promise<void> {
  return api.delete<void>(`${API_V2}/marketplace/listings/${id}/save`);
}

export function renewMarketplaceListing(id: number): Promise<MarketplaceDataResponse<MarketplaceListingDetail>> {
  return api.post<MarketplaceDataResponse<MarketplaceListingDetail>>(`${API_V2}/marketplace/listings/${id}/renew`, { duration_days: 30 });
}

export async function uploadMarketplaceImages(id: number, uris: string[]): Promise<MarketplaceDataResponse<MarketplaceImage[]>> {
  const formData = new FormData();
  await Promise.all(uris.map((uri, index) => appendMarketplaceImageFile(formData, uri, index)));
  return api.upload<MarketplaceDataResponse<MarketplaceImage[]>>(`${API_V2}/marketplace/listings/${id}/images`, formData);
}

export function getMyMarketplaceListings(
  cursor?: string | null,
  userId?: number,
): Promise<MarketplaceCollectionResponse<MarketplaceListingItem>> {
  return getMarketplaceListings({ cursor, limit: 20, sort: 'newest', user_id: userId });
}

export function makeMarketplaceOffer(
  listingId: number,
  payload: { amount: number; message?: string | null },
): Promise<MarketplaceDataResponse<MarketplaceOffer>> {
  return api.post<MarketplaceDataResponse<MarketplaceOffer>>(`${API_V2}/marketplace/listings/${listingId}/offers`, payload);
}

export function getMarketplaceOffers(
  mode: 'sent' | 'received',
  cursor?: string | null,
): Promise<MarketplaceCollectionResponse<MarketplaceOffer>> {
  const query: Record<string, string> = {};
  addQueryValue(query, 'cursor', cursor);
  addQueryValue(query, 'per_page', 20);
  return api.get<MarketplaceCollectionResponse<MarketplaceOffer>>(`${API_V2}/marketplace/my-offers/${mode}`, query);
}

export function acceptMarketplaceOffer(id: number): Promise<MarketplaceDataResponse<MarketplaceOffer>> {
  return api.put<MarketplaceDataResponse<MarketplaceOffer>>(`${API_V2}/marketplace/offers/${id}/accept`);
}

export function declineMarketplaceOffer(id: number): Promise<MarketplaceDataResponse<MarketplaceOffer>> {
  return api.put<MarketplaceDataResponse<MarketplaceOffer>>(`${API_V2}/marketplace/offers/${id}/decline`);
}

export function withdrawMarketplaceOffer(id: number): Promise<MarketplaceDataResponse<{ message: string }>> {
  return api.delete<MarketplaceDataResponse<{ message: string }>>(`${API_V2}/marketplace/offers/${id}`);
}

export function getMarketplaceOrders(
  mode: 'purchases' | 'sales',
  cursor?: string | null,
): Promise<MarketplaceCollectionResponse<MarketplaceOrder>> {
  const query: Record<string, string> = {};
  addQueryValue(query, 'cursor', cursor);
  addQueryValue(query, 'limit', 20);
  return api.get<MarketplaceCollectionResponse<MarketplaceOrder>>(`${API_V2}/marketplace/orders/${mode}`, query);
}

export function createMarketplaceOrder(payload: {
  listing_id: number;
  offer_id?: number;
  quantity?: number;
  shipping_method?: string | null;
}): Promise<MarketplaceDataResponse<MarketplaceOrder>> {
  return api.post<MarketplaceDataResponse<MarketplaceOrder>>(`${API_V2}/marketplace/orders`, payload);
}

export function getMarketplaceSeller(id: number): Promise<MarketplaceDataResponse<MarketplaceSellerProfile>> {
  return api.get<MarketplaceDataResponse<MarketplaceSellerProfile>>(`${API_V2}/marketplace/sellers/${id}`);
}

export function getMarketplaceSellerListings(
  id: number,
  cursor?: string | null,
): Promise<MarketplaceCollectionResponse<MarketplaceListingItem>> {
  const query: Record<string, string> = {};
  addQueryValue(query, 'cursor', cursor);
  addQueryValue(query, 'per_page', 20);
  return api.get<MarketplaceCollectionResponse<MarketplaceListingItem>>(`${API_V2}/marketplace/sellers/${id}/listings`, query);
}

export function getMarketplaceDashboard(): Promise<MarketplaceDataResponse<MarketplaceDashboard>> {
  return api.get<MarketplaceDataResponse<MarketplaceDashboard>>(`${API_V2}/marketplace/seller/dashboard`);
}

export function marketplaceNextCursor<T>(response: MarketplaceCollectionResponse<T>): string | null {
  const meta = collectionMeta(response);
  return meta.cursor ?? meta.next_cursor ?? null;
}

export function marketplaceHasMore<T>(response: MarketplaceCollectionResponse<T>): boolean {
  const meta = collectionMeta(response);
  return Boolean(meta.has_more);
}
