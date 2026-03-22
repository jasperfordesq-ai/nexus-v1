// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface VolunteeringOrganisation {
  id: number;
  name: string;
  avatar: string | null;
}

export interface VolunteerOpportunity {
  id: number;
  title: string;
  description: string | null;
  organisation: VolunteeringOrganisation | null;
  location: string | null;
  is_remote: boolean;
  hours_per_week: number | null;
  commitment: string | null;
  skills_needed: string[];
  status: 'open' | 'closed' | 'filled';
  spots_available: number | null;
  deadline: string | null;
  created_at: string;
}

export interface VolunteeringResponse {
  data: VolunteerOpportunity[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

/**
 * GET /api/v2/volunteering — list volunteering opportunities for the current tenant.
 * Supports cursor-based pagination and optional full-text search.
 */
export function getOpportunities(
  cursor: string | null,
  search?: string,
): Promise<VolunteeringResponse> {
  return api.get<VolunteeringResponse>(`${API_V2}/volunteering`, {
    ...(cursor ? { cursor } : {}),
    ...(search ? { search } : {}),
  });
}

/**
 * GET /api/v2/volunteering/{id} — get a single volunteering opportunity by ID.
 */
export function getOpportunity(id: number): Promise<{ data: VolunteerOpportunity }> {
  return api.get<{ data: VolunteerOpportunity }>(`${API_V2}/volunteering/${id}`);
}

/**
 * POST /api/v2/volunteering/{id}/interest — express interest in an opportunity.
 */
export function expressInterest(id: number): Promise<{ message: string }> {
  return api.post<{ message: string }>(`${API_V2}/volunteering/${id}/interest`, {});
}
