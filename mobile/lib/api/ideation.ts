// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type IdeationStatus = 'draft' | 'open' | 'voting' | 'evaluating' | 'closed' | 'archived';
export type IdeationIdeaStatus = 'draft' | 'submitted' | 'shortlisted' | 'winner' | 'withdrawn';
export type IdeationSort = 'votes' | 'newest';

export interface IdeationChallenge {
  id: number;
  title: string;
  description: string;
  category?: string | null;
  status: IdeationStatus;
  ideas_count?: number;
  submission_deadline?: string | null;
  voting_deadline?: string | null;
  prize_description?: string | null;
  max_ideas_per_user?: number | null;
  user_idea_count?: number;
  tags?: string[];
  cover_image?: string | null;
  is_favorited?: boolean;
  favorites_count?: number;
  views_count?: number;
  creator?: {
    id: number;
    name: string;
    avatar_url?: string | null;
  } | null;
}

export interface IdeationCategory {
  id: number;
  name: string;
  slug?: string | null;
  icon?: string | null;
  color?: string | null;
  challenges_count?: number;
}

export interface IdeationIdea {
  id: number;
  challenge_id: number;
  title: string;
  description: string;
  votes_count?: number;
  comments_count?: number;
  status: IdeationIdeaStatus;
  has_voted?: boolean;
  created_at?: string;
  image_url?: string | null;
  creator?: {
    id: number;
    name: string;
    avatar_url?: string | null;
  } | null;
}

export interface IdeationVoteResult {
  voted: boolean;
  votes_count: number;
}

export interface CreateIdeationChallengePayload {
  title: string;
  description: string;
  status?: IdeationStatus;
  category?: string | null;
  submission_deadline?: string | null;
  voting_deadline?: string | null;
  prize_description?: string | null;
  max_ideas_per_user?: number | null;
}

export interface CursorPage<T> {
  items: T[];
  cursor: string | null;
  hasMore: boolean;
}

interface CollectionEnvelope<T> {
  data?: T[] | { items?: T[]; cursor?: string | null; has_more?: boolean };
  items?: T[];
  cursor?: string | null;
  has_more?: boolean;
  meta?: {
    cursor?: string | null;
    next_cursor?: string | null;
    has_more?: boolean;
  } | null;
}

type ArrayEnvelope<T> = T[] | { data?: T[] | { items?: T[] }; items?: T[] };

export async function getIdeationChallenges(options: {
  status?: IdeationStatus | 'all';
  search?: string;
  categoryId?: number | null;
  cursor?: string | null;
  perPage?: number;
} = {}): Promise<CursorPage<IdeationChallenge>> {
  const params: Record<string, string> = { per_page: String(options.perPage ?? 20) };
  if (options.status && options.status !== 'all') params.status = options.status;
  if (options.search?.trim()) params.search = options.search.trim();
  if (options.categoryId) params.category_id = String(options.categoryId);
  if (options.cursor) params.cursor = options.cursor;

  const response = await api.get<CollectionEnvelope<IdeationChallenge>>(`${API_V2}/ideation-challenges`, params);
  return normalizeCollection(response);
}

export async function getIdeationCategories(): Promise<IdeationCategory[]> {
  const response = await api.get<ArrayEnvelope<IdeationCategory>>(`${API_V2}/ideation-categories`);
  return normalizeArray(response);
}

export async function getIdeationChallenge(id: number): Promise<IdeationChallenge> {
  const response = await api.get<{ data?: IdeationChallenge } | IdeationChallenge>(`${API_V2}/ideation-challenges/${id}`);
  if (isObjectWithData(response) && response.data) {
    return response.data;
  }
  return response as IdeationChallenge;
}

export async function createIdeationChallenge(payload: CreateIdeationChallengePayload): Promise<IdeationChallenge> {
  const response = await api.post<{ data?: IdeationChallenge } | IdeationChallenge>(`${API_V2}/ideation-challenges`, payload);
  if (isObjectWithData(response) && response.data) {
    return response.data;
  }
  return response as IdeationChallenge;
}

export async function getIdeationIdeas(challengeId: number, sort: IdeationSort = 'votes'): Promise<CursorPage<IdeationIdea>> {
  const response = await api.get<CollectionEnvelope<IdeationIdea>>(`${API_V2}/ideation-challenges/${challengeId}/ideas`, {
    per_page: '20',
    sort,
  });
  return normalizeCollection(response);
}

export async function submitIdeationIdea(challengeId: number, payload: { title: string; description: string }): Promise<{ id: number }> {
  const response = await api.post<{ data?: { id: number } } | { id: number }>(`${API_V2}/ideation-challenges/${challengeId}/ideas`, payload);
  if (isObjectWithData(response) && response.data) {
    return response.data;
  }
  return response as { id: number };
}

export async function voteIdeationIdea(ideaId: number): Promise<IdeationVoteResult> {
  const response = await api.post<{ data?: IdeationVoteResult } | IdeationVoteResult>(`${API_V2}/ideation-ideas/${ideaId}/vote`);
  if (isObjectWithData(response) && response.data) {
    return response.data;
  }
  return response as IdeationVoteResult;
}

function normalizeCollection<T>(response: CollectionEnvelope<T>): CursorPage<T> {
  const payload = response.data;
  const dataObject = !Array.isArray(payload) && payload ? payload : response;
  return {
    items: Array.isArray(payload) ? payload : dataObject.items ?? [],
    cursor: dataObject.cursor ?? response.meta?.next_cursor ?? response.meta?.cursor ?? null,
    hasMore: dataObject.has_more ?? response.meta?.has_more ?? false,
  };
}

function normalizeArray<T>(response: ArrayEnvelope<T>): T[] {
  if (Array.isArray(response)) return response;
  const payload = response.data ?? response.items ?? [];
  return Array.isArray(payload) ? payload : payload.items ?? [];
}

function isObjectWithData<T>(response: { data?: T } | T): response is { data?: T } {
  return typeof response === 'object' && response !== null && 'data' in response;
}
