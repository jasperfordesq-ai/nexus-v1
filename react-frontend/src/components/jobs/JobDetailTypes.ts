// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export interface JobVacancy {
  id: number;
  title: string;
  description: string;
  location: string | null;
  is_remote: boolean;
  type: 'paid' | 'volunteer' | 'timebank';
  commitment: 'full_time' | 'part_time' | 'flexible' | 'one_off';
  category: string | null;
  skills: string[];
  skills_required: string | null;
  hours_per_week: number | null;
  time_credits: number | null;
  contact_email: string | null;
  contact_phone: string | null;
  deadline: string | null;
  status: string;
  views_count: number;
  applications_count: number;
  created_at: string;
  user_id: number;
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
  has_applied: boolean;
  application_status: string | null;
  application_stage: string | null;
  is_saved: boolean;
  is_featured: boolean;
  featured_until: string | null;
  tagline: string | null;
  video_url: string | null;
  benefits: string[] | null;
  company_size: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_type: string | null;
  salary_currency: string | null;
  salary_negotiable: boolean;
  expired_at: string | null;
  renewed_at: string | null;
  renewal_count: number;
  blind_hiring: boolean;
}

export interface Application {
  id: number;
  vacancy_id: number;
  user_id: number;
  message: string | null;
  status: string;
  stage: string;
  reviewer_notes: string | null;
  created_at: string;
  applicant: {
    id: number;
    name: string;
    avatar_url: string | null;
    email: string | null;
  };
}

export interface MatchResult {
  percentage: number;
  matched: string[];
  missing: string[];
  user_skills: string[];
  required_skills: string[];
}

export interface QualificationResult {
  percentage: number;
  level: string;
  total_required: number;
  total_matched: number;
  total_missing: number;
  breakdown: Array<{ skill: string; matched: boolean }>;
  matched_skills: string[];
  missing_skills: string[];
}

export interface QualificationData {
  percentage: number;
  level: 'low' | 'moderate' | 'good' | 'excellent';
  ai_summary: string;
  matched_skills: string[];
  missing_skills: string[];
  dimensions: { label: string; score: number; detail: string }[];
}

export interface HistoryEntry {
  id: number;
  from_status: string | null;
  to_status: string;
  changed_by_name: string;
  changed_at: string;
  notes: string | null;
}

export interface InlineInterview {
  id: number;
  application_id: number;
  scheduled_at: string;
  interview_type: 'video' | 'phone' | 'in_person';
  status: 'proposed' | 'accepted' | 'declined';
  location_notes?: string | null;
  meeting_link?: string | null;
  duration_mins?: number;
}

export interface InlineOffer {
  id: number;
  application_id: number;
  salary_offered: string | null;
  salary_currency: string;
  salary_type: 'hourly' | 'monthly' | 'annual';
  start_date: string | null;
  message: string | null;
  status: 'pending' | 'accepted' | 'rejected';
}

export interface PipelineRule {
  id: number;
  name: string;
  trigger_stage: string;
  condition_days: number;
  action: string;
  action_target: string | null;
  is_active: boolean;
  last_run_at: string | null;
}

export const TYPE_CHIP_COLORS: Record<string, 'success' | 'secondary' | 'primary'> = {
  paid: 'success',
  volunteer: 'secondary',
  timebank: 'primary',
};

export const STATUS_COLORS: Record<string, 'warning' | 'primary' | 'success' | 'danger' | 'default' | 'secondary'> = {
  applied: 'warning',
  pending: 'warning',
  screening: 'primary',
  reviewed: 'primary',
  interview: 'secondary',
  offer: 'success',
  accepted: 'success',
  rejected: 'danger',
  withdrawn: 'default',
};

export function parseArrayResponse<T>(data: unknown): T[] {
  if (Array.isArray(data)) return data;
  if (data && typeof data === 'object' && 'data' in data) return (data as { data: T[] }).data ?? [];
  return [];
}
