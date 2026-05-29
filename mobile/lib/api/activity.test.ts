// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { getActivityDashboard } from './activity';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
  },
}));

describe('activity API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads the current member activity dashboard', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: {
        timeline: [],
        hours_summary: {
          hours_given: 2,
          hours_received: 3,
          transactions_given: 1,
          transactions_received: 2,
          net_balance: 1,
        },
        connection_stats: {
          total_connections: 4,
          pending_requests: 1,
          groups_joined: 2,
        },
        engagement: {
          posts_count: 5,
          comments_count: 6,
          likes_given: 7,
          likes_received: 8,
        },
        skills_breakdown: {
          skills: [],
          offering_count: 1,
          requesting_count: 2,
        },
        monthly_hours: [],
      },
    });

    const result = await getActivityDashboard();

    expect(api.get).toHaveBeenCalledWith('/api/v2/users/me/activity/dashboard');
    expect(result.data.hours_summary.net_balance).toBe(1);
    expect(result.data.connection_stats.total_connections).toBe(4);
  });
});
