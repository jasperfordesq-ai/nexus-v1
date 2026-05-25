// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn(), put: jest.fn(), patch: jest.fn(), delete: jest.fn() },
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(status: number, message: string) { super(message); this.status = status; this.name = 'ApiResponseError'; }
  },
  registerUnauthorizedCallback: jest.fn(),
}));
jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
  API_BASE_URL: 'https://test.api',
  STORAGE_KEYS: { AUTH_TOKEN: 'auth_token', REFRESH_TOKEN: 'refresh_token', TENANT_SLUG: 'tenant_slug', USER_DATA: 'user_data' },
  TIMEOUTS: { API_REQUEST: 15_000 },
  DEFAULT_TENANT: 'test-tenant',
}));

import { api } from '@/lib/api/client';
import { getFeed, toggleLike } from './feed';
import type { FeedResponse, FeedItem, LikeResult } from './feed';

const mockFeedItem: FeedItem = {
  id: 1,
  type: 'post',
  title: 'Looking for help with moving',
  content: 'I need help moving boxes on Saturday.',
  image_url: null,
  user_id: 5,
  author_name: 'Dave',
  author_avatar: null,
  is_liked: false,
  likes_count: 3,
  comments_count: 1,
  created_at: '2026-03-10T11:00:00Z',
  location: null,
  rating: null,
  start_date: null,
  job_type: null,
  commitment: null,
  submission_deadline: null,
  receiver: null,
};

const mockFeedResponse: FeedResponse = {
  data: [mockFeedItem],
  meta: { per_page: 20, has_more: true, cursor: 'next-cursor' },
};

describe('getFeed', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/feed with page=1 and no cursor by default', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFeedResponse);
    const result = await getFeed();
    expect(api.get).toHaveBeenCalledWith('/api/v2/feed', { page: '1' });
    expect(result.data).toHaveLength(1);
    expect(result.meta.has_more).toBe(true);
  });

  it('passes custom page number', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFeedResponse);
    await getFeed(3);
    expect(api.get).toHaveBeenCalledWith('/api/v2/feed', { page: '3' });
  });

  it('includes cursor param when provided, alongside page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFeedResponse);
    await getFeed(1, 'next-cursor');
    expect(api.get).toHaveBeenCalledWith('/api/v2/feed', {
      page: '1',
      cursor: 'next-cursor',
    });
  });

  it('omits cursor param when null is passed', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFeedResponse);
    await getFeed(1, null);
    const params = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(params).not.toHaveProperty('cursor');
  });

  it('returns the correct meta cursor from the response', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFeedResponse);
    const result = await getFeed();
    expect(result.meta.cursor).toBe('next-cursor');
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Unauthorized'));
    await expect(getFeed()).rejects.toThrow('Unauthorized');
  });
});

describe('toggleLike', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST with target_type and target_id to /api/v2/feed/like', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { liked: true, likes_count: 4 } });
    const result = await toggleLike('post', 1);
    expect(api.post).toHaveBeenCalledWith('/api/v2/feed/like', {
      target_type: 'post',
      target_id: 1,
    });
    expect(result.data.liked).toBe(true);
    expect(result.data.likes_count).toBe(4);
  });

  it('handles unlike (liked=false) response correctly', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { liked: false, likes_count: 2 } });
    const result = await toggleLike('listing', 55);
    expect(api.post).toHaveBeenCalledWith('/api/v2/feed/like', {
      target_type: 'listing',
      target_id: 55,
    });
    expect(result.data.liked).toBe(false);
  });

  it('propagates errors from the API', async () => {
    (api.post as jest.Mock).mockRejectedValue(new Error('Forbidden'));
    await expect(toggleLike('event', 7)).rejects.toThrow('Forbidden');
  });
});
