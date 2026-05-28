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
  getFederationPartners,
  getFederationStats,
  getFederationPartner,
  getFederationMemberReviews,
  markFederationMessageRead,
  markFederationMessagesReadBatch,
  optInFederation,
  optOutFederation,
  sendFederationTransaction,
} from './federation';
import type { FederationResponse, FederatedTenant, FederationStats } from './federation';

const mockTenant: FederatedTenant = {
  id: 1,
  name: 'Community A',
  slug: 'community-a',
  description: 'A test community',
  logo: null,
  member_count: 120,
  location: 'Dublin',
  website: 'https://community-a.example.com',
  connected_since: '2025-01-01T00:00:00Z',
};

const mockFederationResponse: FederationResponse = {
  data: [mockTenant],
  meta: { has_more: false, cursor: null },
};

const mockStats: FederationStats = {
  partner_count: 5,
  federated_members: 450,
  cross_community_exchanges: 32,
};

describe('getFederationPartners', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/federation/partners with no cursor on first page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFederationResponse);
    const result = await getFederationPartners();
    expect(api.get).toHaveBeenCalledWith('/api/v2/federation/partners', {});
    expect(result.data).toHaveLength(1);
    expect(result.meta.has_more).toBe(false);
  });

  it('includes cursor param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFederationResponse);
    await getFederationPartners('cursor-abc');
    expect(api.get).toHaveBeenCalledWith('/api/v2/federation/partners', { cursor: 'cursor-abc' });
  });

  it('omits cursor param when null is passed', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFederationResponse);
    await getFederationPartners(null);
    const params = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(params).not.toHaveProperty('cursor');
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Service unavailable'));
    await expect(getFederationPartners()).rejects.toThrow('Service unavailable');
  });

  it('returns the correct tenant fields', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockFederationResponse);
    const result = await getFederationPartners();
    expect(result.data[0].slug).toBe('community-a');
    expect(result.data[0].member_count).toBe(120);
  });
});

describe('getFederationStats', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/federation/stats and returns stats', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockStats });
    const result = await getFederationStats();
    expect(api.get).toHaveBeenCalledWith('/api/v2/federation/stats');
    expect(result.data.partner_count).toBe(5);
    expect(result.data.federated_members).toBe(450);
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Unauthorized'));
    await expect(getFederationStats()).rejects.toThrow('Unauthorized');
  });
});

describe('getFederationPartner', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with the partner ID', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockTenant });
    const result = await getFederationPartner(1);
    expect(api.get).toHaveBeenCalledWith('/api/v2/federation/partners/1');
    expect(result.data.name).toBe('Community A');
  });
});

describe('getFederationMemberReviews', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the federated reviews endpoint with the partner tenant scope', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });

    await getFederationMemberReviews(272, 5);

    expect(api.get).toHaveBeenCalledWith('/api/v2/federation/members/272/reviews', { tenant_id: '5' });
  });
});

describe('sendFederationTransaction', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('posts a cross-community time credit transfer payload', async () => {
    const payload = {
      receiver_id: 272,
      receiver_tenant_id: 5,
      amount: 2,
      description: 'Repair help',
    };
    (api.post as jest.Mock).mockResolvedValue({ data: { transaction_id: 9, status: 'completed' } });

    await sendFederationTransaction(payload);

    expect(api.post).toHaveBeenCalledWith('/api/v2/federation/transactions', payload);
  });
});

describe('federation opt-in status', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('posts to the opt-in route with an empty payload', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { success: true } });

    await optInFederation();

    expect(api.post).toHaveBeenCalledWith('/api/v2/federation/opt-in', {});
  });

  it('posts to the opt-out route with an empty payload', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { success: true } });

    await optOutFederation();

    expect(api.post).toHaveBeenCalledWith('/api/v2/federation/opt-out', {});
  });
});

describe('markFederationMessageRead', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('uses the backend mark-read route for federated messages', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { success: true } });

    await markFederationMessageRead(230);

    expect(api.post).toHaveBeenCalledWith('/api/v2/federation/messages/230/mark-read', {});
  });
});

describe('markFederationMessagesReadBatch', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('uses the backend batch mark-read route for federated messages', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { updated: 2 } });

    await markFederationMessagesReadBatch([230, '231']);

    expect(api.post).toHaveBeenCalledWith('/api/v2/federation/messages/mark-read-batch', { ids: [230, '231'] });
  });
});
