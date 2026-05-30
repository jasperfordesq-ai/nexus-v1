// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type SearchResultType = 'user' | 'listing' | 'event' | 'group' | 'blog_post';

export interface SearchResult {
  id: number;
  type: SearchResultType;
  title: string;
  subtitle: string | null;
  avatar: string | null;
  url: string | null;
  created_at: string;
}

export interface SearchResponse {
  data: SearchResult[];
  meta: {
    total: number;
    has_more: boolean;
    cursor: string | null;
  };
}

export interface SavedSearch {
  id: number;
  name: string;
  query_params: Record<string, string>;
  notify_on_new: boolean;
  last_run_at: string | null;
  last_result_count: number | null;
  created_at: string;
}

/**
 * GET /api/v2/search
 * Full-text search across users, listings, events, groups, and blog posts.
 * Supports cursor-based pagination and optional type filtering.
 */
export async function search(
  query: string,
  cursor: string | null,
  type?: SearchResultType,
): Promise<SearchResponse> {
  const response = await api.get<SearchResponse>(`${API_V2}/search`, {
    q: query,
    per_page: '20',
    ...(cursor ? { cursor } : {}),
    ...(type ? { type } : {}),
  });
  return {
    data: Array.isArray(response?.data) ? response.data : [],
    meta: response?.meta ?? { total: 0, has_more: false, cursor: null },
  };
}

export function getSavedSearches(): Promise<{ data: SavedSearch[] }> {
  return api.get<{ data: SavedSearch[] }>(`${API_V2}/search/saved`);
}

export function saveSearch(payload: {
  name: string;
  query_params: Record<string, string>;
  notify_on_new?: boolean;
}): Promise<{ data: SavedSearch }> {
  return api.post<{ data: SavedSearch }>(`${API_V2}/search/saved`, {
    notify_on_new: false,
    ...payload,
  });
}

export function deleteSavedSearch(id: number): Promise<{ data: { deleted: boolean } }> {
  return api.delete<{ data: { deleted: boolean } }>(`${API_V2}/search/saved/${id}`);
}

export function runSavedSearch(id: number, resultCount?: number): Promise<{ data: Pick<SavedSearch, 'id' | 'query_params' | 'last_run_at' | 'last_result_count'> }> {
  return api.post<{ data: Pick<SavedSearch, 'id' | 'query_params' | 'last_run_at' | 'last_result_count'> }>(
    `${API_V2}/search/saved/${id}/run`,
    typeof resultCount === 'number' ? { result_count: resultCount } : {},
  );
}
