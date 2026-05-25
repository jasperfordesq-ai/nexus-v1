// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type ExchangeType = 'offer' | 'request';
export type ExchangeStatus = 'active' | 'deleted' | null;

export interface Exchange {
  id: number;
  title: string;
  description: string;
  type: ExchangeType;
  status: ExchangeStatus;
  /** Time estimate in hours (hours_estimate from backend) */
  hours_estimate: number | null;
  category_name: string | null;
  category_color: string | null;
  image_url: string | null;
  location: string | null;
  user: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  created_at: string;
  is_favorited: boolean;
}

export interface ExchangeListResponse {
  data: Exchange[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
    base_url?: string;
  };
}

export interface CreateExchangePayload {
  title: string;
  description: string;
  type: ExchangeType;
  hours_estimate?: number;
  category_id: number;
}

/** GET /api/v2/listings — all open exchanges for the current tenant (cursor-based) */
export function getExchanges(
  cursor: string | null = null,
  params?: Record<string, string>,
): Promise<ExchangeListResponse> {
  return api.get<ExchangeListResponse>(`${API_V2}/listings`, {
    ...(cursor ? { cursor } : {}),
    ...params,
  });
}

/** GET /api/v2/listings/:id */
export function getExchange(id: number): Promise<{ data: Exchange }> {
  return api.get<{ data: Exchange }>(`${API_V2}/listings/${id}`);
}

/** POST /api/v2/listings */
export function createExchange(payload: CreateExchangePayload): Promise<{ data: Exchange }> {
  return api.post<{ data: Exchange }>(`${API_V2}/listings`, payload);
}

/** PUT /api/v2/listings/:id */
export function updateExchange(
  id: number,
  payload: Partial<CreateExchangePayload>,
): Promise<{ data: Exchange }> {
  return api.put<{ data: Exchange }>(`${API_V2}/listings/${id}`, payload);
}

/** DELETE /api/v2/listings/:id */
export function deleteExchange(id: number): Promise<void> {
  return api.delete<void>(`${API_V2}/listings/${id}`);
}
