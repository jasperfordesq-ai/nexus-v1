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
