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
  getExchanges,
  getExchange,
  createExchange,
  updateExchange,
  deleteExchange,
} from './exchanges';
import type { ExchangeListResponse, Exchange, CreateExchangePayload } from './exchanges';

const mockExchange: Exchange = {
  id: 5,
  title: 'Offer: Guitar lessons',
  description: 'I offer beginner guitar lessons',
  type: 'offer',
  status: 'active',
  hours_estimate: 2,
  category_name: 'Music',
  category_color: '#ff9900',
  image_url: null,
  location: null,
  user: { id: 1, name: 'Bob', avatar_url: null },
  created_at: '2026-01-15T12:00:00Z',
  is_favorited: false,
};

const mockListResponse: ExchangeListResponse = {
  data: [mockExchange],
  meta: { per_page: 20, has_more: false, cursor: null },
};

describe('getExchanges', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/listings with no cursor on first page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockListResponse);
    const result = await getExchanges();
    expect(api.get).toHaveBeenCalledWith('/api/v2/listings', {});
    expect(result.data).toHaveLength(1);
  });

  it('includes cursor param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockListResponse);
    await getExchanges('cursor-123');
    expect(api.get).toHaveBeenCalledWith('/api/v2/listings', { cursor: 'cursor-123' });
  });

  it('merges extra params with cursor', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockListResponse);
    await getExchanges('cursor-abc', { type: 'offer', search: 'guitar' });
    expect(api.get).toHaveBeenCalledWith('/api/v2/listings', {
      cursor: 'cursor-abc',
      type: 'offer',
      search: 'guitar',
    });
  });

  it('passes extra params without cursor when cursor is null', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockListResponse);
    await getExchanges(null, { type: 'request' });
    expect(api.get).toHaveBeenCalledWith('/api/v2/listings', { type: 'request' });
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Timeout'));
    await expect(getExchanges()).rejects.toThrow('Timeout');
  });
});

describe('getExchange', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with the listing ID', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockExchange });
    const result = await getExchange(5);
    expect(api.get).toHaveBeenCalledWith('/api/v2/listings/5');
    expect(result.data.title).toBe('Offer: Guitar lessons');
  });
});

describe('createExchange', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST with the full payload to /api/v2/listings', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: mockExchange });
    const payload: CreateExchangePayload = {
      title: 'Offer: Guitar lessons',
      description: 'I offer beginner guitar lessons',
      type: 'offer',
      hours_estimate: 2,
      category_id: 3,
    };
    const result = await createExchange(payload);
    expect(api.post).toHaveBeenCalledWith('/api/v2/listings', payload);
    expect(result.data.id).toBe(5);
  });

  it('propagates errors from the API', async () => {
    (api.post as jest.Mock).mockRejectedValue(new Error('Validation failed'));
    await expect(createExchange({ title: '', description: '', type: 'offer', category_id: 1 }))
      .rejects.toThrow('Validation failed');
  });
});

describe('updateExchange', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends PUT with the partial payload to the correct endpoint', async () => {
    (api.put as jest.Mock).mockResolvedValue({ data: { ...mockExchange, title: 'Updated title' } });
    const result = await updateExchange(5, { title: 'Updated title' });
    expect(api.put).toHaveBeenCalledWith('/api/v2/listings/5', { title: 'Updated title' });
    expect(result.data.title).toBe('Updated title');
  });
});

describe('deleteExchange', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends DELETE to the correct listing endpoint', async () => {
    (api.delete as jest.Mock).mockResolvedValue(undefined);
    await deleteExchange(5);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/listings/5');
  });

  it('propagates errors from the API', async () => {
    (api.delete as jest.Mock).mockRejectedValue(new Error('Not found'));
    await expect(deleteExchange(999)).rejects.toThrow('Not found');
  });
});
