// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  downloadGroupAnalyticsExport,
  getGroupAnalyticsDashboard,
} from './analytics';
import { GROUP_API_MESSAGE_KEYS } from './core';

const { mockGet, mockDownload } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockDownload: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: { get: mockGet, download: mockDownload },
}));

describe('group analytics contract', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockDownload.mockResolvedValue(new Blob());
  });

  it('forwards cancellation and maps backend dashboard field names', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: {
        overview: { total_members: 12 },
        member_growth: [{ date: '2026-07-01', total_members: '12', new_members: 2 }],
        engagement: {
          timeline: [{ date: '2026-07-01', posts: 4 }],
          summary: { active_members: '4', participation_rate: 33.3, avg_posts_per_day: 1.2 },
        },
        top_contributors: [{ user_id: 2, name: 'Alex', avatar_url: null, post_count: 4 }],
        activity_breakdown: { posts: 4, discussions: 1, total: 5 },
        retention: [{ month: '2026-07', joined: 2, still_active: 2, retention_rate: 100 }],
        comparative: { group_members: 12, avg_members: 8, percentile: 75 },
      },
    });

    await expect(getGroupAnalyticsDashboard(9, 30, {
      signal: controller.signal,
    })).resolves.toMatchObject({
      kpi: { total_members: 12, active_members: 4, participation_rate: 33.3 },
      growth: [{ total_members: 12 }],
      engagement: [{ posts: 4 }],
      top_contributors: [{ name: 'Alex' }],
      activity_breakdown: [{ type: 'posts', count: 4 }, { type: 'discussions', count: 1 }],
      retention: [{ retention_pct: 100 }],
      comparative: { your_members: 12, percentile_rank: 75 },
    });
    expect(mockGet).toHaveBeenCalledWith('/v2/groups/9/analytics?days=30', {
      signal: controller.signal,
    });
  });

  it('supports the legacy frontend aliases', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        kpi: { total_members: 3 },
        growth: [],
        engagement: [],
        activity: [{ type: 'discussion', count: 1 }],
      },
    });

    await expect(getGroupAnalyticsDashboard(1, 7)).resolves.toMatchObject({
      kpi: { total_members: 3 },
      growth: [],
      engagement: [],
      activity_breakdown: [{ type: 'discussion', count: 1 }],
    });
  });

  it('turns a resolved failure into a stable Groups error', async () => {
    mockGet.mockResolvedValue({
      success: false,
      code: 'HTTP_403',
      error: 'Raw backend message',
    });

    await expect(getGroupAnalyticsDashboard(9, 90)).rejects.toMatchObject({
      code: 'FORBIDDEN',
      status: 403,
      messageKey: GROUP_API_MESSAGE_KEYS.forbidden,
      retryable: false,
    });
  });

  it.each([
    [new TypeError('Failed to fetch'), 'NETWORK_ERROR', GROUP_API_MESSAGE_KEYS.network],
    [Object.assign(new Error('Request aborted'), { name: 'AbortError' }), 'CANCELLED', GROUP_API_MESSAGE_KEYS.cancelled],
  ] as const)('normalizes transport failures', async (failure, code, messageKey) => {
    mockGet.mockRejectedValue(failure);

    await expect(getGroupAnalyticsDashboard(9, 30)).rejects.toMatchObject({
      code,
      messageKey,
    });
  });

  it('rejects malformed successful payloads', async () => {
    mockGet.mockResolvedValue({ success: true, data: null });

    await expect(getGroupAnalyticsDashboard(9, 30)).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
      messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse,
    });
  });

  it.each(['members', 'activity'] as const)(
    'downloads the %s export through the authenticated client',
    async (type) => {
      await expect(downloadGroupAnalyticsExport(14, type)).resolves.toBeUndefined();
      expect(mockDownload).toHaveBeenCalledWith(
        `/v2/groups/14/analytics/export/${type}`,
        { filename: `group-14-${type}.csv` },
      );
    },
  );

  it('normalizes protected export failures', async () => {
    mockDownload.mockRejectedValue(new TypeError('Failed to fetch'));

    await expect(downloadGroupAnalyticsExport(14, 'members')).rejects.toMatchObject({
      code: 'NETWORK_ERROR',
      messageKey: GROUP_API_MESSAGE_KEYS.network,
      retryable: true,
    });
  });
});
