// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';

const AI_API = '/api/ai';

// ─── Types ───────────────────────────────────────────────────────────────────

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  created_at: string;
  is_error?: boolean;
  sources?: ChatSource[];
  trace_id?: number | null;
  message_id?: number | null;
}

export interface ChatSource {
  type: string;
  id: number | string;
  title: string;
  url?: string;
  audience?: string;
}

export interface ChatResponse {
  data: {
    message: ChatMessage;
    conversation_id: string;
    limits?: {
      daily_remaining: number;
      monthly_remaining: number;
    };
  };
}

interface RawChatResponse {
  data?: {
    message?: ChatMessage;
    conversation_id?: string | number;
    limits?: ChatResponse['data']['limits'];
  };
  message?: ChatMessage;
  conversation_id?: string | number;
  limits?: ChatResponse['data']['limits'];
  sources?: ChatSource[];
  trace_id?: number | null;
  error?: string;
}

export interface ChatStartersResponse {
  starters: string[];
}

// ─── API Functions ────────────────────────────────────────────────────────────

/**
 * POST /api/ai/chat
 * Sends a message to the AI assistant and returns the reply.
 * Pass null for conversationId to start a new conversation.
 * The response contains the new conversation_id to use for follow-up messages.
 */
export function sendChatMessage(
  message: string,
  conversationId: string | null,
): Promise<ChatResponse> {
  return api.post<RawChatResponse>(`${AI_API}/chat`, {
    message,
    conversation_id: conversationId,
  }).then((response) => {
    const body = response.data?.message ? response.data : response;
    const reply = body.message;
    if (!reply) {
      throw new Error(response.error ?? 'Missing AI response');
    }

    return {
      data: {
        message: {
          ...reply,
          id: String(reply.id),
          sources: reply.sources ?? response.sources,
          trace_id: reply.trace_id ?? response.trace_id ?? null,
        },
        conversation_id: String(body.conversation_id ?? ''),
        limits: body.limits ?? response.limits,
      },
    };
  });
}

/**
 * GET /api/ai/conversations/{conversationId}
 * Returns the full message history for a conversation.
 */
export function getChatHistory(conversationId: string): Promise<{ data: ChatMessage[] }> {
  return api.get<{ data: ChatMessage[] }>(`${AI_API}/conversations/${conversationId}`);
}

export function getChatStarters(): Promise<ChatStartersResponse> {
  return api.get<{ data?: ChatStartersResponse; starters?: string[] }>(`${AI_API}/chat/starters`)
    .then((response) => response.data ?? { starters: response.starters ?? [] });
}
