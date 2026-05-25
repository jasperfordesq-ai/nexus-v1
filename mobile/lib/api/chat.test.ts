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
import { sendChatMessage, getChatHistory } from './chat';
import type { ChatResponse, ChatMessage } from './chat';

const mockUserMessage: ChatMessage = {
  id: 'msg-1',
  role: 'user',
  content: 'How do I earn time credits?',
  created_at: '2026-03-01T10:00:00Z',
};

const mockAssistantMessage: ChatMessage = {
  id: 'msg-2',
  role: 'assistant',
  content: 'You earn time credits by offering and completing services for other members.',
  created_at: '2026-03-01T10:00:05Z',
};

const mockChatResponse: ChatResponse = {
  data: {
    message: mockAssistantMessage,
    conversation_id: 'conv-abc-123',
  },
};

describe('sendChatMessage', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST to the correct endpoint with message and null conversationId', async () => {
    (api.post as jest.Mock).mockResolvedValue(mockChatResponse);
    const result = await sendChatMessage('How do I earn time credits?', null);
    expect(api.post).toHaveBeenCalledWith('/api/v2/ai/chat', {
      message: 'How do I earn time credits?',
      conversation_id: null,
    });
    expect(result.data.conversation_id).toBe('conv-abc-123');
  });

  it('sends POST with existing conversationId for follow-up messages', async () => {
    (api.post as jest.Mock).mockResolvedValue(mockChatResponse);
    await sendChatMessage('Tell me more', 'conv-abc-123');
    expect(api.post).toHaveBeenCalledWith('/api/v2/ai/chat', {
      message: 'Tell me more',
      conversation_id: 'conv-abc-123',
    });
  });

  it('returns the assistant reply message in the response', async () => {
    (api.post as jest.Mock).mockResolvedValue(mockChatResponse);
    const result = await sendChatMessage('Hello', null);
    expect(result.data.message.role).toBe('assistant');
    expect(result.data.message.content).toContain('time credits');
  });
});

describe('getChatHistory', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with the conversationId', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [mockUserMessage, mockAssistantMessage] });
    const result = await getChatHistory('conv-abc-123');
    expect(api.get).toHaveBeenCalledWith('/api/v2/ai/chat/conv-abc-123');
    expect(result.data).toHaveLength(2);
  });

  it('returns messages in the correct order', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [mockUserMessage, mockAssistantMessage] });
    const result = await getChatHistory('conv-abc-123');
    expect(result.data[0].role).toBe('user');
    expect(result.data[1].role).toBe('assistant');
  });
});
