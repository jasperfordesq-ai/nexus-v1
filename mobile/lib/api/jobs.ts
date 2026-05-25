// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface JobVacancy {
  id: number;
  title: string;
  description: string;
  location: string | null;
  is_remote: boolean;
  type: 'paid' | 'volunteer' | 'timebank';
  commitment: 'full_time' | 'part_time' | 'flexible' | 'one_off';
  category: string | null;
  skills_required: string[];
  hours_per_week: number | null;
  time_credits: number | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  salary_type: 'hourly' | 'monthly' | 'annual' | null;
  salary_negotiable: boolean;
  deadline: string | null;
  status: 'open' | 'closed' | 'filled' | 'draft';
  views_count: number;
  applications_count: number;
  is_featured: boolean;
  created_at: string;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  organization: {
    id: number;
    name: string;
    logo_url: string | null;
  } | null;
  is_saved?: boolean;
  has_applied?: boolean;
  match_percentage?: number;
}

export interface JobInterview {
  id: number;
  scheduled_at: string;
  interview_type: 'video' | 'phone' | 'in_person';
  status: 'proposed' | 'accepted' | 'declined' | 'cancelled';
  duration_mins: number;
  location_notes: string | null;
}

export interface JobOffer {
  id: number;
  salary_offered: string | null;
  salary_currency: string;
  salary_type: string;
  start_date: string | null;
  message: string | null;
  status: 'pending' | 'accepted' | 'rejected';
}

export interface JobApplication {
  id: number;
  vacancy_id: number;
  vacancy: Partial<JobVacancy>;
  message: string;
  status: 'pending' | 'screening' | 'reviewed' | 'interview' | 'offer' | 'accepted' | 'rejected' | 'withdrawn';
  reviewer_notes: string | null;
  created_at: string;
  updated_at: string;
  interview?: JobInterview | null;
  offer?: JobOffer | null;
}

export interface JobsResponse {
  data: JobVacancy[];
  meta: { has_more: boolean; cursor: string | null };
}

export interface ApplicationsResponse {
  data: JobApplication[];
  meta: { has_more: boolean; cursor: string | null };
}

/**
 * GET /api/v2/jobs — list job vacancies for the current tenant.
 * Supports cursor-based pagination, full-text search, and filters.
 */
export function getJobs(params: {
  search?: string;
  type?: string;
  commitment?: string;
  cursor?: string | null;
}): Promise<JobsResponse> {
  const query: Record<string, string> = {};
  if (params.cursor) query.cursor = params.cursor;
  if (params.search) query.search = params.search;
  if (params.type) query.type = params.type;
  if (params.commitment) query.commitment = params.commitment;
  return api.get<JobsResponse>(`${API_V2}/jobs`, query);
}

/**
 * GET /api/v2/jobs/recommended — recommended job vacancies for the authenticated user.
 */
export function getRecommendedJobs(): Promise<{ data: JobVacancy[] }> {
  return api.get<{ data: JobVacancy[] }>(`${API_V2}/jobs/recommended`);
}

/**
 * GET /api/v2/jobs/{id} — get a single job vacancy by ID.
 */
export function getJobDetail(id: number): Promise<{ data: JobVacancy }> {
  return api.get<{ data: JobVacancy }>(`${API_V2}/jobs/${id}`);
}

/**
 * POST /api/v2/jobs/{id}/apply — submit an application for a job vacancy.
 */
export function applyToJob(
  id: number,
  message: string,
): Promise<{ success: boolean; message: string }> {
  return api.post<{ success: boolean; message: string }>(`${API_V2}/jobs/${id}/apply`, { message });
}

/**
 * POST /api/v2/jobs/{id}/save — save a job vacancy for later.
 */
export function saveJob(id: number): Promise<void> {
  return api.post<void>(`${API_V2}/jobs/${id}/save`);
}

/**
 * DELETE /api/v2/jobs/{id}/save — unsave a previously saved job vacancy.
 */
export function unsaveJob(id: number): Promise<void> {
  return api.delete<void>(`${API_V2}/jobs/${id}/save`);
}

/**
 * GET /api/v2/jobs/my-applications — list the authenticated user's job applications.
 */
export function getMyApplications(params: {
  status?: string;
  cursor?: string | null;
}): Promise<ApplicationsResponse> {
  const query: Record<string, string> = {};
  if (params.cursor) query.cursor = params.cursor;
  if (params.status) query.status = params.status;
  return api.get<ApplicationsResponse>(`${API_V2}/jobs/my-applications`, query);
}

/**
 * GET /api/v2/jobs/{id}/match — get the match percentage between the user and the job.
 */
export function getMatchPercentage(
  id: number,
): Promise<{ percentage: number; assessment: string }> {
  return api.get<{ percentage: number; assessment: string }>(`${API_V2}/jobs/${id}/match`);
}

/**
 * GET /api/v2/jobs/my-interviews — list the authenticated user's scheduled interviews.
 */
export async function getMyInterviews(): Promise<JobInterview[]> {
  const response = await api.get<{ data: JobInterview[] }>(`${API_V2}/jobs/my-interviews`);
  return Array.isArray(response?.data) ? response.data : [];
}

/**
 * PUT /api/v2/jobs/interviews/{id}/accept — accept an interview invitation.
 */
export async function acceptInterview(interviewId: number): Promise<boolean> {
  try {
    await api.put<void>(`${API_V2}/jobs/interviews/${interviewId}/accept`);
    return true;
  } catch {
    return false;
  }
}

/**
 * PUT /api/v2/jobs/interviews/{id}/decline — decline an interview invitation.
 */
export async function declineInterview(interviewId: number): Promise<boolean> {
  try {
    await api.put<void>(`${API_V2}/jobs/interviews/${interviewId}/decline`);
    return true;
  } catch {
    return false;
  }
}

/**
 * GET /api/v2/jobs/my-offers — list the authenticated user's pending job offers.
 */
export async function getMyOffers(): Promise<JobOffer[]> {
  const response = await api.get<{ data: JobOffer[] }>(`${API_V2}/jobs/my-offers`);
  return Array.isArray(response?.data) ? response.data : [];
}

/**
 * PUT /api/v2/jobs/offers/{id}/accept — accept a job offer.
 */
export async function acceptOffer(offerId: number): Promise<boolean> {
  try {
    await api.put<void>(`${API_V2}/jobs/offers/${offerId}/accept`);
    return true;
  } catch {
    return false;
  }
}

/**
 * PUT /api/v2/jobs/offers/{id}/reject — reject a job offer.
 */
export async function rejectOffer(offerId: number): Promise<boolean> {
  try {
    await api.put<void>(`${API_V2}/jobs/offers/${offerId}/reject`);
    return true;
  } catch {
    return false;
  }
}

/**
 * GET /api/v2/jobs/saved-profile — retrieve the user's saved application profile (CV / cover letter).
 */
export async function getSavedProfile(): Promise<{ cv_filename?: string; cover_text?: string } | null> {
  try {
    const response = await api.get<{ profile?: { cv_filename?: string; cover_text?: string } }>(`${API_V2}/jobs/saved-profile`);
    return response?.profile ?? null;
  } catch {
    return null;
  }
}
