// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  createIdeationChallenge,
  getIdeationCategories,
  getIdeationChallenge,
  getIdeationChallenges,
  getIdeationIdeas,
  submitIdeationIdea,
  voteIdeationIdea,
} from './ideation';
import { api } from './client';

jest.mock('./client', () => ({
  api: {
    get: jest.fn(),
    post: jest.fn(),
  },
}));

const mockGet = api.get as jest.Mock;
const mockPost = api.post as jest.Mock;

describe('ideation api', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads filtered challenges with cursor pagination', async () => {
    mockGet.mockResolvedValue({ data: [{ id: 1, title: 'Park ideas' }], meta: { next_cursor: 'abc', has_more: true } });

    const result = await getIdeationChallenges({ status: 'open', search: 'park', categoryId: 4 });

    expect(mockGet).toHaveBeenCalledWith('/api/v2/ideation-challenges', {
      per_page: '20',
      status: 'open',
      search: 'park',
      category_id: '4',
    });
    expect(result.items).toHaveLength(1);
    expect(result.cursor).toBe('abc');
    expect(result.hasMore).toBe(true);
  });

  it('normalizes categories, challenge detail, ideas, submit, and vote responses', async () => {
    mockGet
      .mockResolvedValueOnce({ data: [{ id: 2, name: 'Environment' }] })
      .mockResolvedValueOnce({ data: { id: 3, title: 'Cleaner streets' } })
      .mockResolvedValueOnce({ data: [{ id: 7, title: 'More bins' }] });
    mockPost
      .mockResolvedValueOnce({ data: { id: 9 } })
      .mockResolvedValueOnce({ data: { voted: true, votes_count: 5 } });

    await expect(getIdeationCategories()).resolves.toEqual([{ id: 2, name: 'Environment' }]);
    await expect(getIdeationChallenge(3)).resolves.toMatchObject({ id: 3 });
    await expect(getIdeationIdeas(3, 'newest')).resolves.toMatchObject({ items: [{ id: 7, title: 'More bins' }] });
    await expect(submitIdeationIdea(3, { title: 'More trees', description: 'Plant them' })).resolves.toEqual({ id: 9 });
    await expect(voteIdeationIdea(7)).resolves.toEqual({ voted: true, votes_count: 5 });
  });

  it('creates an ideation challenge with the native create payload', async () => {
    mockPost.mockResolvedValueOnce({ data: { id: 14, title: 'Community welcome challenge' } });

    const result = await createIdeationChallenge({
      title: 'Community welcome challenge',
      description: 'Gather practical ideas for helping new members feel welcome.',
      status: 'open',
      submission_deadline: '2026-06-15 09:00:00',
      voting_deadline: '2026-06-20 09:00:00',
    });

    expect(mockPost).toHaveBeenCalledWith('/api/v2/ideation-challenges', {
      title: 'Community welcome challenge',
      description: 'Gather practical ideas for helping new members feel welcome.',
      status: 'open',
      submission_deadline: '2026-06-15 09:00:00',
      voting_deadline: '2026-06-20 09:00:00',
    });
    expect(result).toEqual({ id: 14, title: 'Community welcome challenge' });
  });
});
