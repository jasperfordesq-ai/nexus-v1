// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface Member {
  id: number;
  name: string;
  first_name: string;
  last_name: string;
  avatar_url: string | null;
  tagline: string | null;
  location: string | null;
  latitude: number | null;
  longitude: number | null;
  created_at: string;
  is_verified: boolean;
  rating: number | null;
  total_hours_given: number;
  total_hours_received: number;
  offer_count: number;
  request_count: number;
}

export interface MemberListResponse {
  data: Member[];
  meta: {
    total_items: number;
    per_page: number;
    offset: number;
    has_more: boolean;
  };
}

/** GET /api/v2/users — paginated member directory for the current tenant */
export function getMembers(
  offset = 0,
  search?: string,
): Promise<MemberListResponse> {
  const params: Record<string, string> = { offset: String(offset) };
  if (search) params.search = search;
  return api.get<MemberListResponse>(`${API_V2}/users`, params);
}

/** GET /api/v2/users/:id */
export function getMember(id: number): Promise<{ data: Member }> {
  return api.get<{ data: Member }>(`${API_V2}/users/${id}`);
}
