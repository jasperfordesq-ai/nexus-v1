// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface VolunteeringOrganisation {
  id: number;
  name: string;
  avatar?: string | null;
  logo_url?: string | null;
  status?: string;
  member_role?: string;
}

export interface VolunteerOpportunity {
  id: number;
  title: string;
  description: string | null;
  organisation: VolunteeringOrganisation | null;
  organization?: VolunteeringOrganisation | null;
  location: string | null;
  is_remote: boolean;
  hours_per_week: number | null;
  commitment: string | null;
  skills_needed: string[] | string | null;
  status: 'open' | 'closed' | 'filled';
  spots_available: number | null;
  deadline: string | null;
  created_at: string;
  has_applied?: boolean;
  is_active?: boolean;
  category?: string | null;
  start_date?: string | null;
  end_date?: string | null;
  shifts?: VolunteerShift[];
  application?: {
    id: number;
    status: 'pending' | 'approved' | 'declined' | string;
    message?: string | null;
    created_at?: string | null;
  } | null;
  is_owner?: boolean;
}

export interface VolunteerShift {
  id: number;
  start_time: string;
  end_time: string;
  capacity: number | null;
  signup_count: number;
  spots_available: number | null;
}

export interface VolunteeringResponse {
  data: VolunteerOpportunity[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

export interface VolunteerApplication {
  id: number;
  status: 'pending' | 'approved' | 'declined';
  message?: string | null;
  opportunity: {
    id: number;
    title: string;
    location?: string | null;
  };
  organization: {
    id: number;
    name: string;
    logo_url?: string | null;
  };
  shift?: {
    id: number;
    start_time: string;
    end_time: string;
  } | null;
  org_note?: string | null;
  created_at: string;
}

export interface OpportunityApplication {
  id: number;
  status: 'pending' | 'approved' | 'declined' | string;
  message?: string | null;
  org_note?: string | null;
  user: {
    id: number;
    name: string;
    email?: string | null;
    avatar_url?: string | null;
  };
  shift?: {
    id?: number;
    start_time: string;
    end_time: string;
  } | null;
  created_at: string;
}

export interface VolunteerApplicationsResponse {
  data: VolunteerApplication[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

export interface OpportunityApplicationsResponse {
  data: {
    items: OpportunityApplication[];
    cursor: string | null;
    has_more: boolean;
  };
}

export interface VolunteerHoursSummary {
  total_verified: number;
  total_pending: number;
  total_declined: number;
  by_organization: { name: string; hours: number }[];
  by_month: { month: string; hours: number }[];
}

export interface MyOrganisationsResponse {
  data: VolunteeringOrganisation[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

export interface CreateOpportunityPayload {
  organization_id: number;
  title: string;
  description: string;
  location?: string | null;
  is_remote?: boolean;
  skills_needed?: string;
  start_date?: string | null;
  end_date?: string | null;
  category_id?: number | null;
}

export type UpdateOpportunityPayload = Omit<CreateOpportunityPayload, 'organization_id'>;

/**
 * GET /api/v2/volunteering/opportunities — list volunteering opportunities for the current tenant.
 * Supports cursor-based pagination and optional full-text search.
 */
export function getOpportunities(
  cursor: string | null,
  search?: string,
): Promise<VolunteeringResponse> {
  return api.get<VolunteeringResponse>(`${API_V2}/volunteering/opportunities`, {
    ...(cursor ? { cursor } : {}),
    ...(search ? { search } : {}),
  });
}

/**
 * GET /api/v2/volunteering/opportunities/{id} — get a single volunteering opportunity by ID.
 */
export function getOpportunity(id: number): Promise<{ data: VolunteerOpportunity }> {
  return api.get<{ data: VolunteerOpportunity }>(`${API_V2}/volunteering/opportunities/${id}`);
}

/**
 * POST /api/v2/volunteering/opportunities/{id}/apply — apply for an opportunity.
 */
export function getMyApplications(status?: string): Promise<VolunteerApplicationsResponse> {
  return api.get<VolunteerApplicationsResponse>(`${API_V2}/volunteering/applications`, {
    per_page: '20',
    ...(status ? { status } : {}),
  });
}

export function getOpportunityApplications(
  opportunityId: number,
  status?: string,
): Promise<OpportunityApplicationsResponse> {
  return api.get<OpportunityApplicationsResponse>(
    `${API_V2}/volunteering/opportunities/${opportunityId}/applications`,
    {
      per_page: '20',
      ...(status ? { status } : {}),
    },
  );
}

export function handleVolunteerApplication(
  id: number,
  action: 'approve' | 'decline',
): Promise<{ data: unknown }> {
  return api.put<{ data: unknown }>(`${API_V2}/volunteering/applications/${id}`, { action });
}

export function withdrawApplication(id: number): Promise<void> {
  return api.delete<void>(`${API_V2}/volunteering/applications/${id}`);
}

export function getHoursSummary(): Promise<{ data: VolunteerHoursSummary }> {
  return api.get<{ data: VolunteerHoursSummary }>(`${API_V2}/volunteering/hours/summary`);
}

export function getMyOrganisations(): Promise<MyOrganisationsResponse> {
  return api.get<MyOrganisationsResponse>(`${API_V2}/volunteering/my-organisations`, { per_page: '50' });
}

export function createOpportunity(payload: CreateOpportunityPayload): Promise<{ data: VolunteerOpportunity }> {
  return api.post<{ data: VolunteerOpportunity }>(`${API_V2}/volunteering/opportunities`, payload);
}

export function updateOpportunity(id: number, payload: UpdateOpportunityPayload): Promise<{ data: VolunteerOpportunity }> {
  return api.put<{ data: VolunteerOpportunity }>(`${API_V2}/volunteering/opportunities/${id}`, payload);
}

export function logVolunteerHours(payload: {
  organization_id: number;
  date: string;
  hours: number;
  description?: string;
}): Promise<{ data: { id: number; status: string; message: string } }> {
  return api.post<{ data: { id: number; status: string; message: string } }>(`${API_V2}/volunteering/hours`, payload);
}

export function expressInterest(id: number, message?: string): Promise<{ message: string }> {
  return api.post<{ message: string }>(`${API_V2}/volunteering/opportunities/${id}/apply`, message ? { message } : {});
}

export function signUpForShift(id: number): Promise<{ data: { shift_id: number; message: string } }> {
  return api.post<{ data: { shift_id: number; message: string } }>(`${API_V2}/volunteering/shifts/${id}/signup`, {});
}
