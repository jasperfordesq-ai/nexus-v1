// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export interface MarketplaceReportCase {
  id: number;
  marketplace_listing_id: number;
  reason: string;
  description: string | null;
  evidence_urls?: string[] | null;
  status: string;
  acknowledged_at?: string | null;
  resolved_at?: string | null;
  resolution_reason?: string | null;
  action_taken?: string | null;
  appeal_text?: string | null;
  appeal_resolved_at?: string | null;
  can_appeal?: boolean;
  viewer_role?: 'reporter' | 'seller';
  listing?: { id: number; title: string; status?: string } | null;
  reporter?: { id: number; name: string; avatar_url?: string | null } | null;
  handler?: { id: number; name: string } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface MarketplaceDisputeCase {
  id: number;
  order_id: number;
  reason: string;
  description: string | null;
  evidence_urls?: string[] | null;
  status: string;
  resolution_notes?: string | null;
  refund_amount?: number | string | null;
  resolved_at?: string | null;
  created_at?: string | null;
  order?: {
    id: number;
    order_number?: string | null;
    total_price?: number | string | null;
    time_credits_used?: number | string | null;
    currency?: string | null;
    buyer?: { id: number; name: string } | null;
    seller?: { id: number; name: string } | null;
  } | null;
  opened_by_user?: { id: number; name: string; avatar_url?: string | null } | null;
  opened_by?: { id: number; name: string; avatar_url?: string | null } | number | null;
}

export interface MarketplaceCasePage<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
}

export function normalizeMarketplaceCasePage<T>(value: unknown): MarketplaceCasePage<T> {
  if (Array.isArray(value)) {
    return { items: value as T[], total: value.length, page: 1, per_page: value.length || 20 };
  }

  if (!value || typeof value !== 'object') {
    return { items: [], total: 0, page: 1, per_page: 20 };
  }

  const payload = value as Record<string, unknown>;
  const nested = payload.data;
  const items = Array.isArray(payload.items)
    ? payload.items as T[]
    : Array.isArray(nested)
      ? nested as T[]
      : [];

  return {
    items,
    total: typeof payload.total === 'number' ? payload.total : items.length,
    page: typeof payload.page === 'number' ? payload.page : 1,
    per_page: typeof payload.per_page === 'number' ? payload.per_page : 20,
  };
}
