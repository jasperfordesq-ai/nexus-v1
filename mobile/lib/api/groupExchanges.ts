// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type GroupExchangeStatus =
  | 'draft'
  | 'pending_participants'
  | 'pending_broker'
  | 'active'
  | 'pending_confirmation'
  | 'completed'
  | 'cancelled'
  | 'disputed';

export interface GroupExchange {
  id: number;
  title: string;
  description?: string | null;
  organizer_id: number;
  organizer_name?: string | null;
  organizer_avatar?: string | null;
  status: GroupExchangeStatus;
  split_type: 'equal' | 'custom' | 'weighted';
  total_hours: number;
  participant_count?: number;
  created_at: string;
  updated_at?: string;
  completed_at?: string | null;
}

export interface GroupExchangeParticipant {
  id: number;
  user_id: number;
  name: string;
  avatar_url: string | null;
  role: 'provider' | 'receiver' | string;
  hours: number;
  weight: number;
  confirmed: boolean;
  confirmed_at: string | null;
  notes: string | null;
}

export interface GroupExchangeDetail extends GroupExchange {
  tenant_id: number;
  listing_id: number | null;
  broker_id: number | null;
  broker_notes: string | null;
  participants: GroupExchangeParticipant[];
  calculated_split: Record<string, Record<string, number>>;
}

export interface GroupExchangeListResponse {
  data: {
    data: GroupExchange[];
    has_more: boolean;
  };
}

export function getGroupExchanges(params: { status?: string; limit?: number; offset?: number } = {}): Promise<GroupExchangeListResponse> {
  const query: Record<string, string> = {
    limit: String(params.limit ?? 20),
    offset: String(params.offset ?? 0),
  };
  if (params.status && params.status !== 'all') query.status = params.status;
  return api.get<GroupExchangeListResponse>(`${API_V2}/group-exchanges`, query);
}

export function getGroupExchange(id: number): Promise<{ data: GroupExchangeDetail }> {
  return api.get<{ data: GroupExchangeDetail }>(`${API_V2}/group-exchanges/${id}`);
}

export function confirmGroupExchange(id: number): Promise<{ data: GroupExchangeDetail }> {
  return api.post<{ data: GroupExchangeDetail }>(`${API_V2}/group-exchanges/${id}/confirm`);
}

export function completeGroupExchange(id: number): Promise<{ data: { message: string; transaction_ids: number[] } }> {
  return api.post<{ data: { message: string; transaction_ids: number[] } }>(`${API_V2}/group-exchanges/${id}/complete`);
}

export function cancelGroupExchange(id: number): Promise<{ data: { message: string } }> {
  return api.delete<{ data: { message: string } }>(`${API_V2}/group-exchanges/${id}`);
}
