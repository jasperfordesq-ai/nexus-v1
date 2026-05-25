// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn(), delete: jest.fn(), patch: jest.fn() },
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
import { search } from './search';
import type { SearchResponse } from './search';

const mockSearchResponse: SearchResponse = {
  data: [
    {
      id: 1,
      type: 'listing',
      title: 'Guitar Lessons',
      subtitle: 'Music tuition',
      avatar: null,
      url: '/listings/1',
      created_at: '2026-01-01T00:00:00Z',
    },
  ],
  meta: { total: 1, has_more: false, cursor: null },
};

describe('search', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with query and per_page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockSearchResponse);
    const result = await search('guitar', null);
    expect(api.get).toHaveBeenCalledWith('/api/v2/search', { q: 'guitar', per_page: '20' });
    expect(result.data).toHaveLength(1);
    expect(result.meta.total).toBe(1);
  });

  it('includes cursor when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockSearchResponse);
    await search('guitar', 'cursor-xyz');
    expect(api.get).toHaveBeenCalledWith('/api/v2/search', {
      q: 'guitar',
      per_page: '20',
      cursor: 'cursor-xyz',
    });
  });

  it('omits cursor when null', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockSearchResponse);
    await search('guitar', null);
    const call = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(call).not.toHaveProperty('cursor');
  });

  it('includes type filter when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockSearchResponse);
    await search('meeting', null, 'event');
    expect(api.get).toHaveBeenCalledWith('/api/v2/search', {
      q: 'meeting',
      per_page: '20',
      type: 'event',
    });
  });

  it('omits type when not provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockSearchResponse);
    await search('meeting', null);
    const call = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(call).not.toHaveProperty('type');
  });

  it('includes cursor and type together', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockSearchResponse);
    await search('alice', 'cursor-1', 'user');
    expect(api.get).toHaveBeenCalledWith('/api/v2/search', {
      q: 'alice',
      per_page: '20',
      cursor: 'cursor-1',
      type: 'user',
    });
  });
});
