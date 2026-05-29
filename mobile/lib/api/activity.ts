// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface ActivityItem {
  id: number;
  activity_type: string;
  description: string;
  created_at: string;
}

export interface HoursSummary {
  hours_given: number;
  hours_received: number;
  transactions_given: number;
  transactions_received: number;
  net_balance: number;
}

export interface ConnectionStats {
  total_connections: number;
  pending_requests: number;
  groups_joined: number;
}

export interface EngagementMetrics {
  posts_count: number;
  comments_count: number;
  likes_given: number;
  likes_received: number;
}

export interface SkillEntry {
  skill_name: string;
  is_offering: boolean;
  is_requesting: boolean;
  proficiency: string | null;
  endorsements: number;
}

export interface SkillsBreakdown {
  skills: SkillEntry[];
  offering_count: number;
  requesting_count: number;
}

export interface MonthlyHoursEntry {
  month: string;
  label: string;
  given: number;
  received: number;
}

export interface ActivityDashboard {
  timeline: ActivityItem[];
  hours_summary: HoursSummary;
  connection_stats: ConnectionStats;
  engagement: EngagementMetrics;
  skills_breakdown: SkillsBreakdown;
  monthly_hours: MonthlyHoursEntry[];
}

export interface ActivityDashboardResponse {
  data: ActivityDashboard;
}

export function getActivityDashboard(): Promise<ActivityDashboardResponse> {
  return api.get<ActivityDashboardResponse>(`${API_V2}/users/me/activity/dashboard`);
}
