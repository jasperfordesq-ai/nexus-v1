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
import { getOpportunities, getOpportunity, expressInterest } from './volunteering';
import type { VolunteeringResponse, VolunteerOpportunity } from './volunteering';

const mockOpportunity: VolunteerOpportunity = {
  id: 3,
  title: 'Community Garden Helper',
  description: 'Help maintain the community garden.',
  organisation: { id: 2, name: 'Green Spaces Co-op', avatar: null },
  location: 'Dublin',
  is_remote: false,
  hours_per_week: 3,
  commitment: 'Weekly',
  skills_needed: ['gardening'],
  status: 'open',
  spots_available: 5,
  deadline: '2026-04-30T00:00:00Z',
  created_at: '2026-03-01T00:00:00Z',
};

const mockVolunteeringResponse: VolunteeringResponse = {
  data: [mockOpportunity],
  meta: { has_more: false, cursor: null },
};

describe('getOpportunities', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with no params on first page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockVolunteeringResponse);
    const result = await getOpportunities(null);
    expect(api.get).toHaveBeenCalledWith('/api/v2/volunteering', {});
    expect(result.data).toHaveLength(1);
    expect(result.meta.has_more).toBe(false);
  });

  it('includes cursor when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockVolunteeringResponse);
    await getOpportunities('cursor-vol-1');
    expect(api.get).toHaveBeenCalledWith('/api/v2/volunteering', { cursor: 'cursor-vol-1' });
  });

  it('omits cursor when null', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockVolunteeringResponse);
    await getOpportunities(null);
    const call = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(call).not.toHaveProperty('cursor');
  });

  it('includes search param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockVolunteeringResponse);
    await getOpportunities(null, 'gardening');
    expect(api.get).toHaveBeenCalledWith('/api/v2/volunteering', { search: 'gardening' });
  });

  it('omits search when not provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockVolunteeringResponse);
    await getOpportunities(null);
    const call = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(call).not.toHaveProperty('search');
  });

  it('includes cursor and search together', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockVolunteeringResponse);
    await getOpportunities('cursor-2', 'teaching');
    expect(api.get).toHaveBeenCalledWith('/api/v2/volunteering', {
      cursor: 'cursor-2',
      search: 'teaching',
    });
  });
});

describe('getOpportunity', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with the opportunity ID', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockOpportunity });
    const result = await getOpportunity(3);
    expect(api.get).toHaveBeenCalledWith('/api/v2/volunteering/3');
    expect(result.data.title).toBe('Community Garden Helper');
    expect(result.data.status).toBe('open');
  });
});

describe('expressInterest', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST to the correct interest endpoint with empty body', async () => {
    (api.post as jest.Mock).mockResolvedValue({ message: 'Interest registered' });
    const result = await expressInterest(3);
    expect(api.post).toHaveBeenCalledWith('/api/v2/volunteering/3/interest', {});
    expect(result.message).toBe('Interest registered');
  });
});
