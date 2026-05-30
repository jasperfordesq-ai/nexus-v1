// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { getExplore } from './explore';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
  },
}));

describe('explore api', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads the web-parity Explore payload from the v2 endpoint', async () => {
    const payload = {
      data: {
        community_stats: {
          total_members: 10,
          exchanges_this_month: 2,
          hours_exchanged: 40,
          active_listings: 5,
        },
        popular_listings: [],
        trending_posts: [],
        active_groups: [],
        upcoming_events: [],
        top_contributors: [],
        trending_hashtags: [],
        new_members: [],
        featured_challenges: [],
        recommended_listings: [],
        near_you_listings: [],
        suggested_connections: [],
        trending_blog_posts: [],
        volunteering_opportunities: [],
        active_organisations: [],
        active_polls: [],
        in_demand_skills: [],
        featured_resources: [],
        latest_jobs: [],
        categories: [],
      },
    };
    (api.get as jest.Mock).mockResolvedValue(payload);

    await expect(getExplore()).resolves.toBe(payload);
    expect(api.get).toHaveBeenCalledWith('/api/v2/explore');
  });
});
