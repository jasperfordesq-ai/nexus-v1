// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface FederatedTenant {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  logo: string | null;
  member_count: number;
  location: string | null;
  website: string | null;
  connected_since: string;
}

export interface FederationResponse {
  data: FederatedTenant[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

export interface FederationStats {
  partner_count: number;
  federated_members: number;
  cross_community_exchanges: number;
}

/**
 * GET /api/v2/federation/partners — paginated list of federated partner communities.
 */
export function getFederationPartners(cursor?: string | null): Promise<FederationResponse> {
  const params: Record<string, string> = {};
  if (cursor) params['cursor'] = cursor;
  return api.get<FederationResponse>(`${API_V2}/federation/partners`, params);
}

/**
 * GET /api/v2/federation/stats — aggregate stats for the current tenant's federation.
 */
export function getFederationStats(): Promise<{ data: FederationStats }> {
  return api.get<{ data: FederationStats }>(`${API_V2}/federation/stats`);
}

/**
 * GET /api/v2/federation/partners/{id} — detail for a single federated partner.
 */
export function getFederationPartner(id: number): Promise<{ data: FederatedTenant }> {
  return api.get<{ data: FederatedTenant }>(`${API_V2}/federation/partners/${id}`);
}
