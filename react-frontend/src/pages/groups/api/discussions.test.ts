// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/lib/api';
import {
  createGroupDiscussion,
  getGroupDiscussion,
  listGroupDiscussions,
  replyToGroupDiscussion,
} from './discussions';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

const discussion = {
  id: 3,
  title: 'Ideas',
  content: 'Share ideas',
  author: { id: 1, name: 'Member', avatar_url: null },
  reply_count: 0,
  is_pinned: 0 as const,
  last_reply_at: null,
  created_at: '2026-07-01T00:00:00Z',
};
const message = {
  id: 4,
  content: 'Reply',
  author: { id: 2, name: 'Other Member', avatar_url: null },
  is_own: 0 as const,
  created_at: '2026-07-02T00:00:00Z',
};

describe('group-discussions adapter', () => {
  beforeEach(() => vi.clearAllMocks());

  it('lists validated discussions with authoritative cursor metadata and cancellation', async () => {
    const controller = new AbortController();
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [discussion],
      meta: { has_more: true, cursor: 'next' },
    });

    await expect(listGroupDiscussions(8, { cursor: 'current', signal: controller.signal })).resolves.toEqual({
      discussions: [{ ...discussion, is_pinned: false }],
      nextCursor: 'next',
      hasMore: true,
    });
    expect(api.get).toHaveBeenCalledWith('/v2/groups/8/discussions?per_page=15&cursor=current', {
      signal: controller.signal,
    });
  });

  it('does not invent has-more state when pagination metadata is missing or contradictory', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: [discussion] })
      .mockResolvedValueOnce({ success: true, data: [discussion], meta: { has_more: true } })
      .mockResolvedValueOnce({
        success: true,
        data: [discussion],
        meta: { has_more: false, cursor: 'contradictory' },
      });

    await expect(listGroupDiscussions(8)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
    await expect(listGroupDiscussions(8)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
    await expect(listGroupDiscussions(8)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it('rejects malformed discussion records instead of leaking partial objects to the UI', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({
        success: true,
        data: [{ ...discussion, reply_count: 'zero' }],
        meta: { has_more: false },
      } as never)
      .mockResolvedValueOnce({
        success: true,
        data: [{ ...discussion, id: 3.5 }],
        meta: { has_more: false },
      } as never);

    await expect(listGroupDiscussions(8)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
    await expect(listGroupDiscussions(8)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it('validates create and reply response records', async () => {
    vi.mocked(api.post)
      .mockResolvedValueOnce({ success: true, data: discussion })
      .mockResolvedValueOnce({ success: true, data: message })
      .mockResolvedValueOnce({ success: true, data: { id: 9, title: 'Incomplete' } } as never);

    await expect(createGroupDiscussion(8, { title: 'Ideas', content: 'Share ideas' }))
      .resolves.toEqual({ ...discussion, is_pinned: false });
    await expect(replyToGroupDiscussion(8, 3, 'Reply'))
      .resolves.toEqual({ ...message, is_own: false });
    await expect(replyToGroupDiscussion(8, 3, 'Incomplete'))
      .rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it('normalizes reply-page metadata and sends composite cursors back unchanged', async () => {
    const controller = new AbortController();
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { discussion, messages: [message] },
      meta: { has_more: true, cursor: 'older-page' },
    });

    await expect(getGroupDiscussion(8, 3, {
      cursor: 'current-page',
      perPage: 2,
      signal: controller.signal,
    })).resolves.toEqual({
      ...discussion,
      is_pinned: false,
      messages: [{ ...message, is_own: false }],
      messagesNextCursor: 'older-page',
      messagesHasMore: true,
    });
    expect(api.get).toHaveBeenCalledWith(
      '/v2/groups/8/discussions/3?per_page=2&cursor=current-page',
      { signal: controller.signal },
    );
  });

  it('rejects malformed detail roots, messages, and pagination metadata', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({
        success: true,
        data: { discussion: { ...discussion, author: null }, messages: [message] },
        meta: { has_more: false },
      } as never)
      .mockResolvedValueOnce({
        success: true,
        data: { discussion, messages: [{ ...message, content: null }] },
        meta: { has_more: false },
      } as never)
      .mockResolvedValueOnce({
        success: true,
        data: { discussion, messages: [message] },
        meta: { has_more: 'yes' },
      } as never);

    await expect(getGroupDiscussion(8, 3)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
    await expect(getGroupDiscussion(8, 3)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
    await expect(getGroupDiscussion(8, 3)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it.each([
    [403, 'FORBIDDEN', 'FORBIDDEN'],
    [404, 'NOT_FOUND', 'NOT_FOUND'],
    [409, 'CONFLICT', 'DISCUSSION_LOCKED'],
    [422, 'VALIDATION_FAILED', 'INVALID_CURSOR'],
  ] as const)('normalizes HTTP %s failures to %s', async (status, code, sourceCode) => {
    vi.mocked(api.get).mockResolvedValue({
      success: false,
      status,
      errors: [{ code: sourceCode, message: 'Raw backend copy' }],
    });

    await expect(listGroupDiscussions(8)).rejects.toMatchObject({ code, sourceCode, status });
  });
});
