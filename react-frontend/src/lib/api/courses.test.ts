// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/lib/api';
import { coursesApi } from './courses';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('coursesApi.browse', () => {
  beforeEach(() => {
    vi.mocked(api.get).mockReset();
  });

  it('normalizes the backend paginated collection into a BrowseResult', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [{ id: 7, title: 'Community teaching', slug: 'community-teaching' }],
      meta: {
        current_page: 2,
        per_page: 12,
        total: 30,
        total_pages: 3,
        has_more: true,
      },
    });

    const result = await coursesApi.browse({ page: 2, q: 'teach' });

    expect(api.get).toHaveBeenCalledWith('/v2/courses?page=2&q=teach');
    expect(result.success).toBe(true);
    expect(result.data).toEqual({
      items: [{ id: 7, title: 'Community teaching', slug: 'community-teaching' }],
      total: 30,
      page: 2,
      per_page: 12,
      total_pages: 3,
      has_more: true,
    });
  });
});
