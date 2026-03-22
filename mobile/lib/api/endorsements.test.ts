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
import {
  getUserEndorsements,
  getMySkills,
  endorseSkill,
  addSkill,
  removeSkill,
  getAvailableSkills,
} from './endorsements';
import type { EndorsementsResponse, UserSkillsResponse, Skill, Endorsement } from './endorsements';

const mockSkill: Skill = { id: 10, name: 'Gardening', category: 'Outdoors' };

const mockEndorsement: Endorsement = {
  id: 1,
  skill: mockSkill,
  endorsed_by: { id: 99, name: 'Alice', avatar: null },
  message: 'Great work!',
  created_at: '2026-01-01T00:00:00Z',
};

const mockEndorsementsResponse: EndorsementsResponse = {
  data: [mockEndorsement],
  meta: { total: 1, has_more: false, cursor: null },
};

const mockUserSkillsResponse: UserSkillsResponse = {
  data: { skills: [mockSkill], endorsements: [mockEndorsement] },
};

describe('getUserEndorsements', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with no cursor on first page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEndorsementsResponse);
    const result = await getUserEndorsements(42);
    expect(api.get).toHaveBeenCalledWith('/api/v2/users/42/endorsements', {});
    expect(result.data).toHaveLength(1);
    expect(result.meta.has_more).toBe(false);
  });

  it('includes cursor param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEndorsementsResponse);
    await getUserEndorsements(42, 'cursor-xyz');
    expect(api.get).toHaveBeenCalledWith('/api/v2/users/42/endorsements', { cursor: 'cursor-xyz' });
  });

  it('omits cursor when null is passed', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEndorsementsResponse);
    await getUserEndorsements(7, null);
    const params = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(params).not.toHaveProperty('cursor');
  });

  it('rejects with the underlying error on API failure', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Network error'));
    await expect(getUserEndorsements(1)).rejects.toThrow('Network error');
  });
});

describe('getMySkills', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/users/me/skills and returns skills + endorsements', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockUserSkillsResponse);
    const result = await getMySkills();
    expect(api.get).toHaveBeenCalledWith('/api/v2/users/me/skills');
    expect(result.data.skills).toHaveLength(1);
    expect(result.data.endorsements).toHaveLength(1);
  });
});

describe('endorseSkill', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST with skill_id and message to the correct endpoint', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: mockEndorsement });
    const result = await endorseSkill(5, 10, 'Great help!');
    expect(api.post).toHaveBeenCalledWith('/api/v2/users/5/endorse', {
      skill_id: 10,
      message: 'Great help!',
    });
    expect(result.data.id).toBe(1);
  });

  it('sends POST with undefined message when not provided', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: mockEndorsement });
    await endorseSkill(5, 10);
    const body = (api.post as jest.Mock).mock.calls[0][1] as Record<string, unknown>;
    expect(body.skill_id).toBe(10);
    expect(body.message).toBeUndefined();
  });
});

describe('addSkill', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST with skill name to /api/v2/users/me/skills', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: mockSkill });
    const result = await addSkill('Cooking');
    expect(api.post).toHaveBeenCalledWith('/api/v2/users/me/skills', { name: 'Cooking' });
    expect(result.data.name).toBe('Gardening');
  });
});

describe('removeSkill', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends DELETE to the correct skill endpoint', async () => {
    (api.delete as jest.Mock).mockResolvedValue(undefined);
    await removeSkill(10);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/users/me/skills/10');
  });

  it('propagates errors from the API', async () => {
    (api.delete as jest.Mock).mockRejectedValue(new Error('Forbidden'));
    await expect(removeSkill(10)).rejects.toThrow('Forbidden');
  });
});

describe('getAvailableSkills', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/skills and returns skill list', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [mockSkill] });
    const result = await getAvailableSkills();
    expect(api.get).toHaveBeenCalledWith('/api/v2/skills');
    expect(result.data).toHaveLength(1);
    expect(result.data[0].name).toBe('Gardening');
  });
});
