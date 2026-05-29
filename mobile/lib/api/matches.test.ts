// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { dismissMatch, getMatches } from './matches';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
    post: jest.fn(),
  },
}));

describe('matches API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads cross-module matches from the V2 matches endpoint', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: {
        matches: [
          {
            id: 1,
            source_type: 'listing',
            source_id: 10,
            match_score: 87,
            title: 'Garden help',
            reasons: ['Shared skill'],
            matched_at: '2026-05-29T10:00:00Z',
          },
        ],
      },
    });

    const result = await getMatches();

    expect(api.get).toHaveBeenCalledWith('/api/v2/matches/all');
    expect(result.data).toHaveLength(1);
    expect(result.data[0].title).toBe('Garden help');
  });

  it('dismisses a listing match with the same not-relevant reason used by web', async () => {
    (api.post as jest.Mock).mockResolvedValue({});

    await dismissMatch(10);

    expect(api.post).toHaveBeenCalledWith('/api/v2/matches/10/dismiss', { reason: 'not_relevant' });
  });
});
