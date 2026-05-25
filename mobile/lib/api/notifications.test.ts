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
  getNotifications,
  getNotificationCounts,
  markRead,
  markAllRead,
} from './notifications';
import type { NotificationListResponse, NotificationCounts } from './notifications';

const mockNotification = {
  id: 7,
  type: 'exchange.completed',
  category: 'transaction' as const,
  title: 'Exchange completed',
  message: 'Your exchange with Bob is complete.',
  body: 'Your exchange with Bob is complete.',
  is_read: false,
  read_at: null,
  actor: { id: 2, name: 'Bob', avatar_url: null },
  link: '/exchanges/10',
  created_at: '2026-03-01T08:00:00Z',
};

const mockNotificationsListResponse: NotificationListResponse = {
  data: [mockNotification],
  meta: { per_page: 25, has_more: false, cursor: null },
};

const mockCounts: NotificationCounts = {
  total: 3,
  messages: 1,
  transactions: 1,
  social: 1,
  system: 0,
};

describe('getNotifications', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/notifications with per_page=25 and no cursor by default', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockNotificationsListResponse);
    const result = await getNotifications();
    expect(api.get).toHaveBeenCalledWith('/api/v2/notifications', { per_page: '25' });
    expect(result.data).toHaveLength(1);
    expect(result.meta.per_page).toBe(25);
  });

  it('includes cursor param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockNotificationsListResponse);
    await getNotifications('cursor-abc');
    expect(api.get).toHaveBeenCalledWith('/api/v2/notifications', {
      per_page: '25',
      cursor: 'cursor-abc',
    });
  });

  it('omits cursor when null is passed', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockNotificationsListResponse);
    await getNotifications(null);
    const params = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(params).not.toHaveProperty('cursor');
    expect(params.per_page).toBe('25');
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Unauthorized'));
    await expect(getNotifications()).rejects.toThrow('Unauthorized');
  });

  it('returns correct notification fields', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockNotificationsListResponse);
    const result = await getNotifications();
    expect(result.data[0].category).toBe('transaction');
    expect(result.data[0].is_read).toBe(false);
    expect(result.data[0].link).toBe('/exchanges/10');
  });
});

describe('getNotificationCounts', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/notifications/counts and returns counts', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockCounts });
    const result = await getNotificationCounts();
    expect(api.get).toHaveBeenCalledWith('/api/v2/notifications/counts');
    expect(result.data.total).toBe(3);
    expect(result.data.messages).toBe(1);
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Service error'));
    await expect(getNotificationCounts()).rejects.toThrow('Service error');
  });
});

describe('markRead', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST to /api/v2/notifications/{id}/read', async () => {
    (api.post as jest.Mock).mockResolvedValue(undefined);
    await markRead(7);
    expect(api.post).toHaveBeenCalledWith('/api/v2/notifications/7/read');
  });

  it('propagates errors from the API', async () => {
    (api.post as jest.Mock).mockRejectedValue(new Error('Not found'));
    await expect(markRead(999)).rejects.toThrow('Not found');
  });
});

describe('markAllRead', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST to /api/v2/notifications/read-all', async () => {
    (api.post as jest.Mock).mockResolvedValue(undefined);
    await markAllRead();
    expect(api.post).toHaveBeenCalledWith('/api/v2/notifications/read-all');
  });
});
