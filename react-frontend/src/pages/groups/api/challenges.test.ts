// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { GROUP_API_MESSAGE_KEYS } from './core';
import {
  createGroupChallenge,
  deleteGroupChallenge,
  GROUP_CHALLENGE_METRICS,
  GROUP_CHALLENGE_REWARD_BANDS,
  listGroupChallenges,
  type CreateGroupChallengeInput,
} from './challenges';

const { mockDelete, mockGet, mockPost } = vi.hoisted(() => ({
  mockDelete: vi.fn(),
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: {
    delete: mockDelete,
    get: mockGet,
    post: mockPost,
  },
}));

const backendChallenge = (overrides: Record<string, unknown> = {}) => ({
  id: 7,
  group_id: 4,
  title: 'Welcome five members',
  description: 'Grow the group',
  metric: 'members',
  target_value: 5,
  current_value: 3,
  reward_xp: 50,
  status: 'active',
  progress_percentage: 60,
  starts_at: '2026-07-01T09:00:00+00:00',
  ends_at: '2099-08-01T09:00:00+00:00',
  completed_at: null,
  creator: { id: 19, name: 'Coordinator', avatar_url: null },
  created_at: '2026-07-01T09:00:00+00:00',
  updated_at: '2026-07-02T09:00:00+00:00',
  ...overrides,
});

describe('group challenge adapter', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('requests every state, forwards cancellation, and preserves the canonical DTO', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: [backendChallenge({ status: 'completed', completed_at: '2026-07-10T09:00:00+00:00' })],
    });

    await expect(listGroupChallenges(4, { signal: controller.signal })).resolves.toEqual([
      {
        id: 7,
        group_id: 4,
        title: 'Welcome five members',
        description: 'Grow the group',
        metric: 'members',
        target_value: 5,
        current_value: 3,
        reward_xp: 50,
        status: 'completed',
        progress_percentage: 60,
        starts_at: '2026-07-01T09:00:00+00:00',
        ends_at: '2099-08-01T09:00:00+00:00',
        completed_at: '2026-07-10T09:00:00+00:00',
        creator: { id: 19, name: 'Coordinator', avatar_url: null },
        created_at: '2026-07-01T09:00:00+00:00',
        updated_at: '2026-07-02T09:00:00+00:00',
      },
    ]);
    expect(mockGet).toHaveBeenCalledWith(
      '/v2/groups/4/challenges?all=1',
      { signal: controller.signal },
    );
  });

  it.each(GROUP_CHALLENGE_METRICS)('accepts the implemented %s metric', async (metric) => {
    mockGet.mockResolvedValue({ success: true, data: [backendChallenge({ metric })] });
    await expect(listGroupChallenges(4)).resolves.toEqual([
      expect.objectContaining({ metric }),
    ]);
  });

  it.each(GROUP_CHALLENGE_REWARD_BANDS)('accepts the server reward band %s', async (reward_xp) => {
    mockGet.mockResolvedValue({ success: true, data: [backendChallenge({ reward_xp })] });
    await expect(listGroupChallenges(4)).resolves.toEqual([
      expect.objectContaining({ reward_xp }),
    ]);
  });

  it.each([
    ['legacy events metric', { metric: 'events' }],
    ['arbitrary XP', { reward_xp: 75 }],
    ['legacy end_date field', { ends_at: undefined, end_date: '2099-08-01T09:00:00Z' }],
    ['legacy creator field', { creator: undefined, created_by: { id: 19, name: 'Coordinator' } }],
  ])('rejects %s instead of silently adapting it', async (_label, overrides) => {
    mockGet.mockResolvedValue({ success: true, data: [backendChallenge(overrides)] });
    await expect(listGroupChallenges(4)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it('posts canonical fields and validates the full created DTO', async () => {
    mockPost.mockResolvedValue({ success: true, data: backendChallenge({ id: 91, metric: 'files' }) });

    await expect(createGroupChallenge(4, {
      title: 'Share files',
      description: 'Upload useful resources',
      metric: 'files',
      target_value: 10,
      reward_xp: 50,
      ends_at: '2099-09-30',
    })).resolves.toEqual(expect.objectContaining({ id: 91, metric: 'files', reward_xp: 50 }));

    expect(mockPost).toHaveBeenCalledWith('/v2/groups/4/challenges', {
      title: 'Share files',
      description: 'Upload useful resources',
      metric: 'files',
      target_value: 10,
      reward_xp: 50,
      ends_at: '2099-09-30',
    });
  });

  it('blocks an arbitrary reward before it reaches the API', async () => {
    const invalidInput = {
      title: 'Share files',
      description: 'Upload useful resources',
      metric: 'files',
      target_value: 10,
      reward_xp: 75,
      ends_at: '2099-09-30',
    } as unknown as CreateGroupChallengeInput;

    await expect(createGroupChallenge(4, invalidInput)).rejects.toMatchObject({
      code: 'VALIDATION_FAILED',
    });
    expect(mockPost).not.toHaveBeenCalled();
  });

  it.each([
    [{ success: false, code: 'HTTP_403' }, 'FORBIDDEN', GROUP_API_MESSAGE_KEYS.forbidden],
    [{ success: true, data: { unexpected: [] } }, 'INVALID_RESPONSE', GROUP_API_MESSAGE_KEYS.invalidResponse],
  ] as const)('normalizes resolved list failures and malformed payloads', async (response, code, messageKey) => {
    mockGet.mockResolvedValue(response);
    await expect(listGroupChallenges(4)).rejects.toMatchObject({ code, messageKey });
  });

  it.each([
    [new TypeError('Failed to fetch'), 'NETWORK_ERROR', true],
    [Object.assign(new Error('aborted'), { name: 'AbortError' }), 'CANCELLED', false],
  ] as const)('normalizes transport and cancellation failures', async (failure, code, retryable) => {
    mockGet.mockRejectedValue(failure);
    await expect(listGroupChallenges(4)).rejects.toMatchObject({ code, retryable });
  });

  it('rejects a false-success create payload without reporting success', async () => {
    mockPost.mockResolvedValue({ success: true, data: {} });
    await expect(createGroupChallenge(4, {
      title: 'Challenge',
      description: 'A useful description',
      metric: 'posts',
      target_value: 1,
      reward_xp: 0,
      ends_at: '2099-09-30',
    })).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it('cancels through the compatibility DELETE route and validates the typed result', async () => {
    const cancelled = backendChallenge({ status: 'cancelled' });
    mockDelete
      .mockResolvedValueOnce({
        success: true,
        data: { challenge: cancelled, changed: true, message: 'cancelled' },
      })
      .mockResolvedValueOnce({
        success: false,
        status: 409,
        code: 'CHALLENGE_IMMUTABLE',
      });

    await expect(deleteGroupChallenge(4, 7)).resolves.toEqual({
      challenge: expect.objectContaining({ id: 7, group_id: 4, status: 'cancelled' }),
      changed: true,
    });
    expect(mockDelete).toHaveBeenNthCalledWith(1, '/v2/groups/4/challenges/7');

    await expect(deleteGroupChallenge(4, 7)).rejects.toMatchObject({
      code: 'CONFLICT',
      sourceCode: 'CHALLENGE_IMMUTABLE',
      messageKey: GROUP_API_MESSAGE_KEYS.conflict,
    });
  });

  it.each([
    ['active status', { success: true, data: { challenge: backendChallenge({ status: 'active' }), changed: true, message: 'bad' } }],
    ['non-boolean change flag', { success: true, data: { challenge: backendChallenge({ status: 'cancelled' }), changed: 'yes', message: 'bad' } }],
    ['wrong challenge id', { success: true, data: { challenge: backendChallenge({ id: 8, status: 'cancelled' }), changed: true, message: 'bad' } }],
  ] as const)('rejects a malformed cancel result with %s', async (_label, response) => {
    mockDelete.mockResolvedValue(response);
    await expect(deleteGroupChallenge(4, 7)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });
});
