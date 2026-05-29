// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type MatchSourceType = 'listing' | 'job' | 'volunteering' | 'group';
export type MatchStatus = 'pending' | 'accepted' | 'declined' | 'expired';

export interface MatchItem {
  id: number;
  source_type: MatchSourceType;
  source_id: number;
  match_score: number;
  title: string;
  description?: string | null;
  reasons: string[];
  matched_user?: {
    id: number;
    name: string;
    avatar_url?: string | null;
  } | null;
  matched_at: string;
  status?: MatchStatus;
  metadata?: {
    category?: string | null;
    location?: string | null;
    skills?: string[] | null;
  } | null;
}

interface MatchesPayload {
  matches?: MatchItem[];
}

type RawMatchesResponse = MatchItem[] | MatchesPayload | { data?: MatchItem[] | MatchesPayload };

export interface MatchesResponse {
  data: MatchItem[];
}

export async function getMatches(): Promise<MatchesResponse> {
  const response = await api.get<RawMatchesResponse>(`${API_V2}/matches/all`);
  const payload =
    !Array.isArray(response) && response && 'data' in response && response.data !== undefined
      ? response.data
      : response;

  return {
    data: Array.isArray(payload) ? payload : 'matches' in payload ? payload.matches ?? [] : [],
  };
}

export function dismissMatch(listingId: number): Promise<unknown> {
  return api.post(`${API_V2}/matches/${listingId}/dismiss`, { reason: 'not_relevant' });
}
