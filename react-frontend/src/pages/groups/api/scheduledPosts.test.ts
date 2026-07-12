// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/lib/api';
import {
  cancelScheduledGroupPost,
  createScheduledGroupPost,
  listScheduledGroupPosts,
} from './scheduledPosts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

const post = {
  id: 9,
  post_type: 'discussion' as const,
  title: 'Weekly update',
  content: 'News',
  scheduled_at: '2026-07-12T10:00:00Z',
  is_recurring: false,
  recurrence_pattern: null,
};

describe('scheduled-posts adapter', () => {
  beforeEach(() => vi.clearAllMocks());

  it('lists posts through the typed endpoint and forwards cancellation', async () => {
    const controller = new AbortController();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [post] });

    await expect(listScheduledGroupPosts(4, { signal: controller.signal })).resolves.toEqual([post]);
    expect(api.get).toHaveBeenCalledWith('/v2/groups/4/scheduled-posts', {
      signal: controller.signal,
    });
  });

  it('rejects resolved failures and malformed list payloads', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: false, code: 'HTTP_403' })
      .mockResolvedValueOnce({ success: true, data: { id: 1 } as never });

    await expect(listScheduledGroupPosts(4)).rejects.toMatchObject({ code: 'FORBIDDEN' });
    await expect(listScheduledGroupPosts(4)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it('creates a typed post and rejects a resolved mutation failure', async () => {
    vi.mocked(api.post)
      .mockResolvedValueOnce({ success: true, data: post })
      .mockResolvedValueOnce({ success: false, code: 'VALIDATION_ERROR' });
    const input = {
      post_type: 'discussion' as const,
      title: 'Weekly update',
      content: 'News',
      scheduled_at: '2026-07-12T10:00:00Z',
    };

    await expect(createScheduledGroupPost(4, input)).resolves.toEqual(post);
    expect(api.post).toHaveBeenCalledWith('/v2/groups/4/scheduled-posts', input);
    await expect(createScheduledGroupPost(4, input)).rejects.toMatchObject({
      code: 'VALIDATION_FAILED',
    });
  });

  it('cancels by exact parent/child route and normalizes transport errors', async () => {
    vi.mocked(api.delete)
      .mockResolvedValueOnce({ success: true })
      .mockRejectedValueOnce(new TypeError('Private network detail'));

    await expect(cancelScheduledGroupPost(4, 9)).resolves.toBeUndefined();
    expect(api.delete).toHaveBeenCalledWith('/v2/groups/4/scheduled-posts/9');
    await expect(cancelScheduledGroupPost(4, 9)).rejects.toMatchObject({
      code: 'NETWORK_ERROR',
      retryable: true,
    });
  });
});
