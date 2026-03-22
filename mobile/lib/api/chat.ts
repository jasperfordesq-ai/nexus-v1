// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

// ─── Types ───────────────────────────────────────────────────────────────────

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  created_at: string;
}

export interface ChatResponse {
  data: {
    message: ChatMessage;
    conversation_id: string;
  };
}

// ─── API Functions ────────────────────────────────────────────────────────────

/**
 * POST /api/v2/ai/chat
 * Sends a message to the AI assistant and returns the reply.
 * Pass null for conversationId to start a new conversation.
 * The response contains the new conversation_id to use for follow-up messages.
 */
export function sendChatMessage(
  message: string,
  conversationId: string | null,
): Promise<ChatResponse> {
  return api.post<ChatResponse>(`${API_V2}/ai/chat`, {
    message,
    conversation_id: conversationId,
  });
}

/**
 * GET /api/v2/ai/chat/{conversationId}
 * Returns the full message history for a conversation.
 */
export function getChatHistory(conversationId: string): Promise<{ data: ChatMessage[] }> {
  return api.get<{ data: ChatMessage[] }>(`${API_V2}/ai/chat/${conversationId}`);
}
