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
import { getGroups, getGroup, joinGroup, leaveGroup } from './groups';
import type { GroupsResponse, GroupDetail } from './groups';

const mockGroupsResponse: GroupsResponse = {
  data: [
    {
      id: 1,
      name: 'Test Group',
      description: 'A test group',
      visibility: 'public',
      cover_image: null,
      is_featured: false,
      member_count: 5,
      posts_count: 10,
      is_member: false,
      created_at: '2026-01-01T00:00:00Z',
      recent_members: [],
    },
  ],
  meta: { has_more: false, cursor: null },
};

const mockGroupDetail: GroupDetail = {
  id: 1,
  name: 'Test Group',
  description: 'A test group',
  visibility: 'public',
  cover_image: null,
  is_featured: false,
  member_count: 5,
  posts_count: 10,
  is_member: false,
  created_at: '2026-01-01T00:00:00Z',
  recent_members: [],
  admin: { id: 99, name: 'Admin User', avatar_url: null },
  tags: ['community'],
};

describe('getGroups', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with per_page and no cursor on first page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockGroupsResponse);
    const result = await getGroups(null);
    expect(api.get).toHaveBeenCalledWith('/api/v2/groups', { per_page: '20' });
    expect(result.data).toHaveLength(1);
    expect(result.meta.has_more).toBe(false);
  });

  it('includes cursor when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockGroupsResponse);
    await getGroups('cursor-abc');
    expect(api.get).toHaveBeenCalledWith('/api/v2/groups', { per_page: '20', cursor: 'cursor-abc' });
  });

  it('includes search param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockGroupsResponse);
    await getGroups(null, { search: 'gardening' });
    expect(api.get).toHaveBeenCalledWith('/api/v2/groups', { per_page: '20', search: 'gardening' });
  });

  it('includes visibility param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockGroupsResponse);
    await getGroups(null, { visibility: 'public' });
    expect(api.get).toHaveBeenCalledWith('/api/v2/groups', { per_page: '20', visibility: 'public' });
  });

  it('includes all optional params together with cursor', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockGroupsResponse);
    await getGroups('next-cursor', { search: 'sport', visibility: 'private' });
    expect(api.get).toHaveBeenCalledWith('/api/v2/groups', {
      per_page: '20',
      cursor: 'next-cursor',
      search: 'sport',
      visibility: 'private',
    });
  });

  it('omits search and visibility when not provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockGroupsResponse);
    await getGroups(null, {});
    const call = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(call).not.toHaveProperty('search');
    expect(call).not.toHaveProperty('visibility');
  });
});

describe('getGroup', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with the group ID', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockGroupDetail });
    const result = await getGroup(42);
    expect(api.get).toHaveBeenCalledWith('/api/v2/groups/42');
    expect(result.data.id).toBe(1);
    expect(result.data.admin.name).toBe('Admin User');
  });
});

describe('joinGroup', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST to the correct join endpoint with empty body', async () => {
    (api.post as jest.Mock).mockResolvedValue({ message: 'Joined successfully' });
    const result = await joinGroup(7);
    expect(api.post).toHaveBeenCalledWith('/api/v2/groups/7/join', {});
    expect(result.message).toBe('Joined successfully');
  });
});

describe('leaveGroup', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends DELETE to the correct leave endpoint', async () => {
    (api.delete as jest.Mock).mockResolvedValue(undefined);
    await leaveGroup(7);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/groups/7/membership');
  });
});
