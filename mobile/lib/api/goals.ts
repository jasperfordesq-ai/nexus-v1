// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

// ─── Types ───────────────────────────────────────────────────────────────────

export interface Goal {
  id: number;
  title: string;
  description: string | null;
  status: 'active' | 'completed' | 'abandoned';
  target_hours: number | null;
  progress_hours: number;
  due_date: string | null;
  created_at: string;
}

export interface GoalsResponse {
  data: Goal[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

// ─── API Functions ────────────────────────────────────────────────────────────

/**
 * GET /api/v2/goals
 * Returns cursor-paginated goals for the current user.
 *
 * @param cursor  Opaque cursor string from the previous page's meta, or null for the first page
 */
export function getGoals(cursor: string | null): Promise<GoalsResponse> {
  const params: Record<string, string> = {};
  if (cursor) params['cursor'] = cursor;
  return api.get<GoalsResponse>(`${API_V2}/goals`, params);
}

/**
 * POST /api/v2/goals
 * Creates a new goal for the current user.
 */
export function createGoal(data: {
  title: string;
  description?: string;
  target_hours?: number;
  due_date?: string;
}): Promise<{ data: Goal }> {
  return api.post<{ data: Goal }>(`${API_V2}/goals`, data);
}

/**
 * PATCH /api/v2/goals/{id}
 * Updates the status of a goal (complete or abandon).
 */
export function updateGoalStatus(
  id: number,
  status: 'completed' | 'abandoned',
): Promise<{ data: Goal }> {
  return api.patch<{ data: Goal }>(`${API_V2}/goals/${id}`, { status });
}
