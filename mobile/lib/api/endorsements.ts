// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface Skill {
  id: number;
  name: string;
  category: string | null;
}

export interface Endorsement {
  id: number;
  skill: Skill;
  endorsed_by: {
    id: number;
    name: string;
    avatar: string | null;
  };
  message: string | null;
  created_at: string;
}

export interface EndorsementsResponse {
  data: Endorsement[];
  meta: {
    total: number;
    has_more: boolean;
    cursor: string | null;
  };
}

export interface UserSkillsResponse {
  data: {
    skills: Skill[];
    endorsements: Endorsement[];
  };
}

/**
 * GET /api/v2/users/{userId}/endorsements — paginated endorsements for a user.
 */
export function getUserEndorsements(
  userId: number,
  cursor?: string | null,
): Promise<EndorsementsResponse> {
  const params: Record<string, string> = {};
  if (cursor) params['cursor'] = cursor;
  return api.get<EndorsementsResponse>(`${API_V2}/users/${userId}/endorsements`, params);
}

/**
 * GET /api/v2/users/me/skills — current user's skills and endorsements received.
 */
export function getMySkills(): Promise<UserSkillsResponse> {
  return api.get<UserSkillsResponse>(`${API_V2}/users/me/skills`);
}

/**
 * POST /api/v2/users/{userId}/endorse — endorse a skill on another user's profile.
 */
export function endorseSkill(
  userId: number,
  skillId: number,
  message?: string,
): Promise<{ data: Endorsement }> {
  return api.post<{ data: Endorsement }>(`${API_V2}/users/${userId}/endorse`, {
    skill_id: skillId,
    message,
  });
}

/**
 * POST /api/v2/users/me/skills — add a new skill to the current user's profile.
 */
export function addSkill(skillName: string): Promise<{ data: Skill }> {
  return api.post<{ data: Skill }>(`${API_V2}/users/me/skills`, { name: skillName });
}

/**
 * DELETE /api/v2/users/me/skills/{skillId} — remove a skill from the current user's profile.
 */
export function removeSkill(skillId: number): Promise<void> {
  return api.delete<void>(`${API_V2}/users/me/skills/${skillId}`);
}

/**
 * GET /api/v2/skills — list all available skills for autocomplete/picker.
 */
export function getAvailableSkills(): Promise<{ data: Skill[] }> {
  return api.get<{ data: Skill[] }>(`${API_V2}/skills`);
}
