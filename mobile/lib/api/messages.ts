// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface Conversation {
  id: number;
  other_user: {
    id: number;
    name: string;
    avatar_url: string | null;
    is_online: boolean;
  };
  last_message: {
    body: string;
    created_at: string;
    is_own: boolean;
  } | null;
  unread_count: number;
}

export interface Message {
  id: number;
  body: string;
  sender: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  created_at: string;
  is_own: boolean;
  is_voice: boolean;
  audio_url: string | null;
  reactions: Record<string, number>;
  is_read: boolean;
}

export interface ConversationListResponse {
  data: Conversation[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
    base_url?: string;
  };
}

export interface MessageListResponse {
  data: Message[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
    base_url?: string;
  };
}

/** GET /api/v2/messages — list conversations for current user */
export function getConversations(): Promise<ConversationListResponse> {
  return api.get<ConversationListResponse>(`${API_V2}/messages`);
}

/** GET /api/v2/messages/:otherUserId — message thread for a conversation */
export function getThread(otherUserId: number): Promise<MessageListResponse> {
  return api.get<MessageListResponse>(`${API_V2}/messages/${otherUserId}`);
}

/**
 * GET /api/v2/messages/:otherUserId — but returns an empty list on 404
 * (conversation doesn't exist yet). Used when navigating from a member profile
 * or exchange detail to start a new conversation.
 */
export async function getOrCreateThread(otherUserId: number): Promise<MessageListResponse> {
  try {
    return await getThread(otherUserId);
  } catch (err: unknown) {
    // If 404 (no conversation yet), return empty data so the UI shows an empty thread
    const status = (err as { status?: number })?.status;
    if (status === 404) {
      return {
        data: [],
        meta: { per_page: 50, has_more: false, cursor: null },
      };
    }
    throw err;
  }
}

/** POST /api/v2/messages — send a message to a recipient */
export function sendMessage(recipientId: number, body: string): Promise<{ data: Message }> {
  return api.post<{ data: Message }>(`${API_V2}/messages`, { recipient_id: recipientId, body });
}
