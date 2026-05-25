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
  getConversations,
  getThread,
  getOrCreateThread,
  sendMessage,
} from './messages';
import type { ConversationListResponse, MessageListResponse, Message } from './messages';

const mockMessage: Message = {
  id: 100,
  body: 'Hello there!',
  sender: { id: 1, name: 'Alice', avatar_url: null },
  created_at: '2026-01-10T09:00:00Z',
  is_own: true,
  is_voice: false,
  audio_url: null,
  reactions: {},
  is_read: true,
};

const mockConversationListResponse: ConversationListResponse = {
  data: [
    {
      id: 1,
      other_user: { id: 2, name: 'Bob', avatar_url: null, is_online: false },
      last_message: { body: 'Hello there!', created_at: '2026-01-10T09:00:00Z', is_own: true },
      unread_count: 0,
    },
  ],
  meta: { per_page: 20, has_more: false, cursor: null },
};

const mockMessageListResponse: MessageListResponse = {
  data: [mockMessage],
  meta: { per_page: 50, has_more: false, cursor: null },
};

describe('getConversations', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/messages with no cursor on first page', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockConversationListResponse);
    const result = await getConversations();
    expect(api.get).toHaveBeenCalledWith('/api/v2/messages', {});
    expect(result.data).toHaveLength(1);
  });

  it('includes cursor param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockConversationListResponse);
    await getConversations('cursor-abc');
    expect(api.get).toHaveBeenCalledWith('/api/v2/messages', { cursor: 'cursor-abc' });
  });

  it('omits cursor when null is passed', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockConversationListResponse);
    await getConversations(null);
    const params = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(params).not.toHaveProperty('cursor');
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Unauthorized'));
    await expect(getConversations()).rejects.toThrow('Unauthorized');
  });
});

describe('getThread', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct thread endpoint for a user', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMessageListResponse);
    const result = await getThread(2);
    expect(api.get).toHaveBeenCalledWith('/api/v2/messages/2', {});
    expect(result.data).toHaveLength(1);
    expect(result.data[0].body).toBe('Hello there!');
  });

  it('includes cursor param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMessageListResponse);
    await getThread(2, 'cursor-xyz');
    expect(api.get).toHaveBeenCalledWith('/api/v2/messages/2', { cursor: 'cursor-xyz' });
  });
});

describe('getOrCreateThread', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('returns thread data when the conversation exists', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockMessageListResponse);
    const result = await getOrCreateThread(2);
    expect(result.data).toHaveLength(1);
  });

  it('returns empty thread when API responds with 404', async () => {
    const err = Object.assign(new Error('Not found'), { status: 404 });
    (api.get as jest.Mock).mockRejectedValue(err);
    const result = await getOrCreateThread(99);
    expect(result.data).toHaveLength(0);
    expect(result.meta.has_more).toBe(false);
    expect(result.meta.cursor).toBeNull();
  });

  it('rethrows non-404 errors', async () => {
    const err = Object.assign(new Error('Server error'), { status: 500 });
    (api.get as jest.Mock).mockRejectedValue(err);
    await expect(getOrCreateThread(99)).rejects.toThrow('Server error');
  });
});

describe('sendMessage', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST with recipient_id and body to /api/v2/messages', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: mockMessage });
    const result = await sendMessage(2, 'Hello there!');
    expect(api.post).toHaveBeenCalledWith('/api/v2/messages', {
      recipient_id: 2,
      body: 'Hello there!',
    });
    expect(result.data.body).toBe('Hello there!');
  });

  it('propagates errors from the API', async () => {
    (api.post as jest.Mock).mockRejectedValue(new Error('Forbidden'));
    await expect(sendMessage(2, 'Hi')).rejects.toThrow('Forbidden');
  });
});
