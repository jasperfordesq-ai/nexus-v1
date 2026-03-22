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
import { getMembers, getMember } from './members';
import type { MemberListResponse, Member } from './members';

const mockMember: Member = {
  id: 3,
  name: 'Carol Jones',
  first_name: 'Carol',
  last_name: 'Jones',
  avatar: null,
  avatar_url: null,
  tagline: 'I love cooking',
  location: 'Cork',
  latitude: 51.9,
  longitude: -8.47,
  created_at: '2025-09-01T00:00:00Z',
  is_verified: true,
  rating: 4.5,
  total_hours_given: 15,
  total_hours_received: 10,
};

const mockMemberListResponse: MemberListResponse = {
  data: [mockMember],
  meta: { total_items: 1, per_page: 20, offset: 0, has_more: false },
};

describe('getMembers', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/users with default offset=0 and no search', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMemberListResponse);
    const result = await getMembers();
    expect(api.get).toHaveBeenCalledWith('/api/v2/users', { offset: '0' });
    expect(result.data).toHaveLength(1);
    expect(result.meta.total_items).toBe(1);
  });

  it('passes custom offset for pagination', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMemberListResponse);
    await getMembers(20);
    expect(api.get).toHaveBeenCalledWith('/api/v2/users', { offset: '20' });
  });

  it('includes search param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMemberListResponse);
    await getMembers(0, 'carol');
    expect(api.get).toHaveBeenCalledWith('/api/v2/users', { offset: '0', search: 'carol' });
  });

  it('omits search param when not provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMemberListResponse);
    await getMembers(0);
    const params = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(params).not.toHaveProperty('search');
  });

  it('passes offset and search together', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMemberListResponse);
    await getMembers(40, 'gardening');
    expect(api.get).toHaveBeenCalledWith('/api/v2/users', { offset: '40', search: 'gardening' });
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Server error'));
    await expect(getMembers()).rejects.toThrow('Server error');
  });

  it('returns correct member fields', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMemberListResponse);
    const result = await getMembers();
    expect(result.data[0].is_verified).toBe(true);
    expect(result.data[0].total_hours_given).toBe(15);
  });
});

describe('getMember', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with the member ID', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockMember });
    const result = await getMember(3);
    expect(api.get).toHaveBeenCalledWith('/api/v2/users/3');
    expect(result.data.name).toBe('Carol Jones');
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Not found'));
    await expect(getMember(999)).rejects.toThrow('Not found');
  });
});
