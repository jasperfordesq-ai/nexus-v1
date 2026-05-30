// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type AppreciationReactionType = 'heart' | 'clap' | 'star';

export interface Appreciation {
  id: number;
  sender_id: number;
  receiver_id: number;
  message: string;
  is_public: boolean;
  reactions_count: number;
  created_at: string;
  sender?: {
    id: number;
    name: string | null;
    avatar_url: string | null;
  } | null;
  my_reaction?: AppreciationReactionType | null;
}

export interface AppreciationListResponse {
  data: Appreciation[];
  meta?: {
    current_page?: number;
    last_page?: number;
    total_pages?: number;
    per_page?: number;
    total?: number;
  };
}

export interface AppreciationReactionResponse {
  data?: {
    reacted: boolean;
    reaction_type: AppreciationReactionType | null;
  };
}

export function getUserAppreciations(userId: number | string, page = 1, perPage = 20): Promise<AppreciationListResponse> {
  return api.get<AppreciationListResponse>(`${API_V2}/users/${userId}/appreciations`, {
    page: String(page),
    per_page: String(perPage),
  });
}

export function reactToAppreciation(id: number | string, reactionType: AppreciationReactionType): Promise<AppreciationReactionResponse> {
  return api.post<AppreciationReactionResponse>(`${API_V2}/appreciations/${id}/react`, {
    reaction_type: reactionType,
  });
}
