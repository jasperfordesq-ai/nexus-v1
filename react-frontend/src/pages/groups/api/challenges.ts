// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export const GROUP_CHALLENGE_METRICS = [
  'posts',
  'discussions',
  'members',
  'files',
] as const;

export type GroupChallengeMetric = typeof GROUP_CHALLENGE_METRICS[number];

export const GROUP_CHALLENGE_REWARD_BANDS = [0, 25, 50, 100] as const;
export type GroupChallengeReward = typeof GROUP_CHALLENGE_REWARD_BANDS[number];

export const GROUP_CHALLENGE_LIMITS = Object.freeze({
  titleMin: 3,
  titleMax: 120,
  descriptionMin: 10,
  descriptionMax: 2_000,
  targetMin: 1,
  targetMax: 1_000_000,
});

export type GroupChallengeStatus = 'active' | 'completed' | 'expired' | 'cancelled';

export interface GroupChallengeCreator {
  id: number;
  name: string;
  avatar_url: string | null;
}

/** Canonical Laravel challenge DTO. */
export interface GroupChallenge {
  id: number;
  group_id: number;
  title: string;
  description: string;
  metric: GroupChallengeMetric;
  target_value: number;
  current_value: number;
  reward_xp: GroupChallengeReward;
  status: GroupChallengeStatus;
  progress_percentage: number;
  starts_at: string;
  ends_at: string;
  completed_at: string | null;
  creator: GroupChallengeCreator;
  created_at: string;
  updated_at: string;
}

export interface CreateGroupChallengeInput {
  title: string;
  description: string;
  metric: GroupChallengeMetric;
  target_value: number;
  reward_xp: GroupChallengeReward;
  ends_at: string;
}

export interface CancelGroupChallengeResult {
  challenge: GroupChallenge;
  changed: boolean;
}

export interface ListGroupChallengesOptions {
  signal?: AbortSignal;
}

type UnknownRecord = Record<string, unknown>;

const METRICS = new Set<string>(GROUP_CHALLENGE_METRICS);
const REWARD_BANDS = new Set<number>(GROUP_CHALLENGE_REWARD_BANDS);
const STATUSES = new Set<GroupChallengeStatus>(['active', 'completed', 'expired', 'cancelled']);

function asRecord(value: unknown): UnknownRecord | null {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
    ? value as UnknownRecord
    : null;
}

function readString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function readInteger(value: unknown): number | null {
  if (typeof value === 'number' && Number.isSafeInteger(value)) return value;
  if (typeof value === 'string' && /^-?\d+$/.test(value.trim())) {
    const parsed = Number(value);
    if (Number.isSafeInteger(parsed)) return parsed;
  }
  return null;
}

function readNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) return parsed;
  }
  return null;
}

function isValidDate(value: string): boolean {
  return Number.isFinite(Date.parse(value));
}

function invalidResponse(): never {
  throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
}

function invalidInput(): never {
  throw normalizeGroupApiError({ code: 'VALIDATION_ERROR', status: 422 });
}

function normalizeChallenge(value: unknown): GroupChallenge {
  const record = asRecord(value);
  if (!record) invalidResponse();

  const id = readInteger(record.id);
  const groupId = readInteger(record.group_id);
  const title = readString(record.title);
  const metric = readString(record.metric);
  const targetValue = readInteger(record.target_value);
  const currentValue = readInteger(record.current_value);
  const rewardXp = readInteger(record.reward_xp);
  const status = readString(record.status)?.toLowerCase();
  const progressPercentage = readNumber(record.progress_percentage);
  const startsAt = readString(record.starts_at);
  const endsAt = readString(record.ends_at);
  const createdAt = readString(record.created_at);
  const updatedAt = readString(record.updated_at);
  const creator = asRecord(record.creator);
  const creatorId = readInteger(creator?.id);
  const creatorName = typeof creator?.name === 'string' ? creator.name : null;
  const avatarUrl = creator?.avatar_url === null
    ? null
    : typeof creator?.avatar_url === 'string'
      ? creator.avatar_url
      : undefined;
  const completedAt = record.completed_at === null
    ? null
    : readString(record.completed_at);

  if (
    id === null || id <= 0
    || groupId === null || groupId <= 0
    || !title
    || typeof record.description !== 'string'
    || !metric || !METRICS.has(metric)
    || targetValue === null
    || targetValue < GROUP_CHALLENGE_LIMITS.targetMin
    || targetValue > GROUP_CHALLENGE_LIMITS.targetMax
    || currentValue === null || currentValue < 0 || currentValue > targetValue
    || rewardXp === null || !REWARD_BANDS.has(rewardXp)
    || !status || !STATUSES.has(status as GroupChallengeStatus)
    || progressPercentage === null || progressPercentage < 0 || progressPercentage > 100
    || !startsAt || !isValidDate(startsAt)
    || !endsAt || !isValidDate(endsAt)
    || !createdAt || !isValidDate(createdAt)
    || !updatedAt || !isValidDate(updatedAt)
    || !creator
    || creatorId === null || creatorId <= 0
    || creatorName === null
    || avatarUrl === undefined
    || (record.completed_at !== null && completedAt === null)
  ) {
    invalidResponse();
  }

  return {
    id,
    group_id: groupId,
    title,
    description: record.description,
    metric: metric as GroupChallengeMetric,
    target_value: targetValue,
    current_value: currentValue,
    reward_xp: rewardXp as GroupChallengeReward,
    status: status as GroupChallengeStatus,
    progress_percentage: progressPercentage,
    starts_at: startsAt,
    ends_at: endsAt,
    completed_at: completedAt,
    creator: {
      id: creatorId,
      name: creatorName,
      avatar_url: avatarUrl,
    },
    created_at: createdAt,
    updated_at: updatedAt,
  };
}

function normalizeChallengeList(payload: unknown): GroupChallenge[] {
  if (!Array.isArray(payload)) invalidResponse();
  return payload.map(normalizeChallenge);
}

function assertCreateInput(input: CreateGroupChallengeInput): void {
  const titleLength = input.title.trim().length;
  const descriptionLength = input.description.trim().length;
  if (
    titleLength < GROUP_CHALLENGE_LIMITS.titleMin
    || titleLength > GROUP_CHALLENGE_LIMITS.titleMax
    || (
      descriptionLength > 0
      && (
        descriptionLength < GROUP_CHALLENGE_LIMITS.descriptionMin
        || descriptionLength > GROUP_CHALLENGE_LIMITS.descriptionMax
      )
    )
    || !METRICS.has(input.metric)
    || !Number.isSafeInteger(input.target_value)
    || input.target_value < GROUP_CHALLENGE_LIMITS.targetMin
    || input.target_value > GROUP_CHALLENGE_LIMITS.targetMax
    || !REWARD_BANDS.has(input.reward_xp)
    || !isValidDate(input.ends_at)
    || Date.parse(input.ends_at) <= Date.now()
  ) {
    invalidInput();
  }
}

/** List active, completed, expired, and cancelled challenges. */
export async function listGroupChallenges(
  groupId: number,
  options: ListGroupChallengesOptions = {},
): Promise<GroupChallenge[]> {
  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/challenges?all=1`,
      { signal: options.signal },
    );
    return normalizeChallengeList(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Create a challenge with the canonical Laravel request/response contract. */
export async function createGroupChallenge(
  groupId: number,
  input: CreateGroupChallengeInput,
): Promise<GroupChallenge> {
  try {
    assertCreateInput(input);
    const response = await api.post<unknown>(`/v2/groups/${groupId}/challenges`, input);
    return normalizeChallenge(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Cancel an active challenge through the compatibility DELETE route. */
export async function deleteGroupChallenge(
  groupId: number,
  challengeId: number,
): Promise<CancelGroupChallengeResult> {
  try {
    const response = await api.delete<unknown>(
      `/v2/groups/${groupId}/challenges/${challengeId}`,
    );
    const payload = asRecord(unwrapGroupResponse(response));
    if (!payload || typeof payload.changed !== 'boolean' || typeof payload.message !== 'string') {
      invalidResponse();
    }
    const challenge = normalizeChallenge(payload.challenge);
    if (challenge.group_id !== groupId || challenge.id !== challengeId || challenge.status !== 'cancelled') {
      invalidResponse();
    }
    return { challenge, changed: payload.changed };
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
