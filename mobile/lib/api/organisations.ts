// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface Organisation {
  id: number;
  name: string;
  description: string | null;
  logo: string | null;
  website: string | null;
  location: string | null;
  members_count: number;
  listings_count: number;
  verified: boolean;
  created_at: string;
}

export interface OrganisationsResponse {
  data: Organisation[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

/**
 * GET /api/v2/organisations — list organisations for the current tenant.
 * Supports cursor-based pagination and optional full-text search.
 */
export function getOrganisations(
  cursor: string | null,
  search?: string,
): Promise<OrganisationsResponse> {
  return api.get<OrganisationsResponse>(`${API_V2}/organisations`, {
    ...(cursor ? { cursor } : {}),
    ...(search ? { search } : {}),
  });
}

/**
 * GET /api/v2/organisations/{id} — get a single organisation by ID.
 */
export function getOrganisation(id: number): Promise<{ data: Organisation }> {
  return api.get<{ data: Organisation }>(`${API_V2}/organisations/${id}`);
}
