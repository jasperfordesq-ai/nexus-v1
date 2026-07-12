// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/lib/api';
import {
  deleteGroupFeedItem,
  hideGroupFeedItem,
  listGroupFeed,
  muteGroupFeedUser,
  reactToGroupFeedItem,
  reportGroupFeedItem,
  toggleGroupFeedLike,
  voteInGroupFeedPoll,
} from './feed';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

const item = {
  id: 5,
  type: 'post' as const,
  content: 'Update',
  created_at: '2026-07-01T00:00:00Z',
  likes_count: 1,
  comments_count: 0,
  is_liked: false,
};

describe('group-feed adapter', () => {
  beforeEach(() => vi.clearAllMocks());

  it('lists a cursor page and rejects a resolved failure', async () => {
    const controller = new AbortController();
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: [item], meta: { has_more: true, next_cursor: 'next' } })
      .mockResolvedValueOnce({ success: false, code: 'HTTP_500' });

    await expect(listGroupFeed(8, { cursor: 'current', signal: controller.signal })).resolves.toEqual({
      items: [item],
      nextCursor: 'next',
      hasMore: true,
    });
    expect(api.get).toHaveBeenCalledWith('/v2/feed?group_id=8&per_page=20&cursor=current', {
      signal: controller.signal,
    });
    await expect(listGroupFeed(8)).rejects.toMatchObject({ code: 'SERVER_ERROR' });
  });

  it('normalizes like and reaction payloads', async () => {
    const reactions = { counts: { love: 1 }, total: 1, user_reaction: 'love', top_reactors: [] };
    vi.mocked(api.post)
      .mockResolvedValueOnce({ success: true, data: { action: 'liked', likes_count: 2 } })
      .mockResolvedValueOnce({ success: true, data: { reactions } });

    await expect(toggleGroupFeedLike(item)).resolves.toEqual({ isLiked: true, likesCount: 2 });
    await expect(reactToGroupFeedItem(item, 'love')).resolves.toEqual(reactions);
  });

  it('confirms hide, mute, report, and delete before resolving', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true });

    await hideGroupFeedItem(item);
    await muteGroupFeedUser(12);
    await reportGroupFeedItem(5, 'Reason');
    await deleteGroupFeedItem(item);

    expect(api.post).toHaveBeenCalledWith('/v2/feed/posts/5/hide', { type: 'post' });
    expect(api.post).toHaveBeenCalledWith('/v2/feed/users/12/mute');
    expect(api.post).toHaveBeenCalledWith('/v2/feed/posts/5/report', { reason: 'Reason' });
    expect(api.post).toHaveBeenCalledWith('/v2/feed/posts/5/delete');
  });

  it('rejects resolved mutation failures instead of confirming them', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false, code: 'HTTP_409' });

    await expect(deleteGroupFeedItem(item)).rejects.toMatchObject({ code: 'CONFLICT' });
  });

  it('returns a poll update and rejects malformed data', async () => {
    const poll = { id: 4, question: 'Choose', options: [], total_votes: 1, user_vote_option_id: 2, is_active: true };
    vi.mocked(api.post)
      .mockResolvedValueOnce({ success: true, data: poll })
      .mockResolvedValueOnce({ success: true });

    await expect(voteInGroupFeedPoll(4, 2)).resolves.toEqual(poll);
    await expect(voteInGroupFeedPoll(4, 2)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });
});
