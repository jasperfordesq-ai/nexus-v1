// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { GROUP_API_MESSAGE_KEYS } from './core';
import {
  acceptGroupAnswer,
  askGroupQuestion,
  getGroupQuestion,
  listGroupQuestions,
  postGroupAnswer,
  voteOnGroupQA,
} from './qa';

const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: { get: mockGet, post: mockPost },
}));

const makeQuestion = (overrides: Record<string, unknown> = {}) => ({
  id: 8,
  group_id: 3,
  title: 'How does this work?',
  body: 'Please explain.',
  vote_count: 2,
  user_vote: 0,
  answer_count: 1,
  has_accepted_answer: false,
  author: { id: 4, name: 'Alex', avatar: null },
  created_at: '2026-07-11T10:00:00Z',
  ...overrides,
});

const makeAnswer = (overrides: Record<string, unknown> = {}) => ({
  id: 12,
  question_id: 8,
  body: 'Like this.',
  vote_count: 1,
  user_vote: 0,
  is_accepted: false,
  author: { id: 5, name: 'Sam', avatar: null },
  created_at: '2026-07-11T11:00:00Z',
  ...overrides,
});

describe('group Q&A contract', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('builds list/search/sort/cursor parameters and forwards cancellation', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: { items: [makeQuestion()], cursor: 'next-1', has_more: true },
    });

    await expect(listGroupQuestions(3, {
      sort: 'most_voted',
      cursor: 'cursor-1',
      query: '  credits  ',
      perPage: 20,
      signal: controller.signal,
    })).resolves.toMatchObject({
      items: [expect.objectContaining({ id: 8 })],
      cursor: 'next-1',
      has_more: true,
    });
    expect(mockGet).toHaveBeenCalledWith(
      '/v2/groups/3/questions?sort=most_voted&per_page=20&cursor=cursor-1&q=credits',
      { signal: controller.signal },
    );
  });

  it('reads a question detail with answers', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: { ...makeQuestion(), answers: [makeAnswer()] },
    });

    await expect(getGroupQuestion(3, 8, { signal: controller.signal })).resolves.toMatchObject({
      id: 8,
      answers: [expect.objectContaining({ id: 12 })],
    });
    expect(mockGet).toHaveBeenCalledWith('/v2/groups/3/questions/8', {
      signal: controller.signal,
    });
  });

  it('normalizes database integer flags to booleans', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        ...makeQuestion({ has_accepted_answer: 1 }),
        answers: [makeAnswer({ is_accepted: 0 })],
      },
    });

    await expect(getGroupQuestion(3, 8)).resolves.toMatchObject({
      has_accepted_answer: true,
      answers: [{ is_accepted: false }],
    });
  });

  it('routes create, answer, vote, and accept operations', async () => {
    mockPost
      .mockResolvedValueOnce({ success: true, data: { id: 8, title: 'Question' } })
      .mockResolvedValueOnce({ success: true, data: { id: 12, question_id: 8 } })
      .mockResolvedValueOnce({ success: true, data: { message: 'recorded' } })
      .mockResolvedValueOnce({ success: true, data: { message: 'accepted' } });

    await expect(askGroupQuestion(3, { title: 'Question', body: 'Details' }))
      .resolves.toMatchObject({ id: 8 });
    await expect(postGroupAnswer(3, 8, { body: 'Answer' }))
      .resolves.toMatchObject({ id: 12 });
    await expect(voteOnGroupQA(3, { type: 'answer', target_id: 12, vote: -1 }))
      .resolves.toBeUndefined();
    await expect(acceptGroupAnswer(3, 12)).resolves.toBeUndefined();

    expect(mockPost).toHaveBeenNthCalledWith(1, '/v2/groups/3/questions', {
      title: 'Question', body: 'Details',
    });
    expect(mockPost).toHaveBeenNthCalledWith(2, '/v2/groups/3/questions/8/answers', {
      body: 'Answer',
    });
    expect(mockPost).toHaveBeenNthCalledWith(3, '/v2/groups/3/qa/vote', {
      type: 'answer', target_id: 12, vote: -1,
    });
    expect(mockPost).toHaveBeenNthCalledWith(4, '/v2/groups/3/answers/12/accept');
  });

  it.each([
    ['list', () => listGroupQuestions(3, { sort: 'newest' }), () => mockGet],
    ['ask', () => askGroupQuestion(3, { title: 'Q', body: 'B' }), () => mockPost],
    ['answer', () => postGroupAnswer(3, 8, { body: 'A' }), () => mockPost],
    ['vote', () => voteOnGroupQA(3, { type: 'question', target_id: 8, vote: 1 }), () => mockPost],
    ['accept', () => acceptGroupAnswer(3, 12), () => mockPost],
  ] as const)('turns resolved success:false %s responses into errors', async (_name, action, getMock) => {
    getMock().mockResolvedValue({ success: false, code: 'HTTP_403', error: 'Forbidden' });

    await expect(action()).rejects.toMatchObject({
      code: 'FORBIDDEN',
      status: 403,
      messageKey: GROUP_API_MESSAGE_KEYS.forbidden,
    });
  });

  it.each([
    [{ items: [{}] }, () => listGroupQuestions(3, { sort: 'newest' })],
    [{ ...makeQuestion(), answers: [{}] }, () => getGroupQuestion(3, 8)],
  ] as const)('rejects malformed successful reads', async (payload, action) => {
    mockGet.mockResolvedValue({ success: true, data: payload });

    await expect(action()).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
      messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse,
    });
  });

  it.each([
    [() => askGroupQuestion(3, { title: 'Question', body: 'Body' })],
    [() => postGroupAnswer(3, 8, { body: 'Answer' })],
  ])('rejects malformed successful create payloads', async (action) => {
    mockPost.mockResolvedValue({ success: true, data: {} });

    await expect(action()).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
      messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse,
    });
  });

  it.each([
    [new TypeError('Failed to fetch'), 'NETWORK_ERROR', GROUP_API_MESSAGE_KEYS.network],
    [Object.assign(new Error('aborted'), { name: 'AbortError' }), 'CANCELLED', GROUP_API_MESSAGE_KEYS.cancelled],
  ] as const)('normalizes transport and cancellation failures', async (failure, code, messageKey) => {
    mockGet.mockRejectedValue(failure);

    await expect(listGroupQuestions(3, { sort: 'newest' })).rejects.toMatchObject({
      code,
      messageKey,
    });
  });
});
