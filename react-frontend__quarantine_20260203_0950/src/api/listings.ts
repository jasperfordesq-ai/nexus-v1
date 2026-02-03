/**
 * Listings API
 */

import { apiGet } from './client';
import type { ListingsResponse, ListingDetailResponse } from './types';

export interface ListingsParams {
  per_page?: number;
  cursor?: string;
  type?: 'offer' | 'request' | string;
  category_id?: number;
  q?: string;
  user_id?: number;
}

/**
 * Fetch listings with optional filters
 */
export async function getListings(params: ListingsParams = {}): Promise<ListingsResponse> {
  const searchParams = new URLSearchParams();

  if (params.per_page) {
    searchParams.set('per_page', String(params.per_page));
  }
  if (params.cursor) {
    searchParams.set('cursor', params.cursor);
  }
  if (params.type) {
    searchParams.set('type', params.type);
  }
  if (params.category_id) {
    searchParams.set('category_id', String(params.category_id));
  }
  if (params.q) {
    searchParams.set('q', params.q);
  }
  if (params.user_id) {
    searchParams.set('user_id', String(params.user_id));
  }

  const queryString = searchParams.toString();
  const endpoint = queryString
    ? `/api/v2/listings?${queryString}`
    : '/api/v2/listings';

  return apiGet<ListingsResponse>(endpoint);
}

/**
 * Fetch a single listing by ID
 */
export async function getListingById(id: number): Promise<ListingDetailResponse> {
  return apiGet<ListingDetailResponse>(`/api/v2/listings/${id}`);
}
