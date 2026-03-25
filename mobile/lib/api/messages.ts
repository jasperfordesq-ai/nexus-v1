// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

/** Helper to compute a display name from API user objects that may have
 *  `name`, `first_name`/`last_name`, or `organization_name`. */
export function displayName(user: {
  name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  organization_name?: string | null;
} | null | undefined): string {
  if (!user) return 'Unknown';
  if (user.name) return user.name;
  if (user.organization_name) return user.organization_name;
  const first = user.first_name ?? '';
  const last = user.last_name ?? '';
  const full = `${first} ${last}`.trim();
  return full || 'Unknown';
}

export interface ConversationOtherUser {
  id: number;
  name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  organization_name?: string | null;
  avatar_url: string | null;
  is_online?: boolean;
}

export interface Conversation {
  id: number;
  other_user: ConversationOtherUser;
  /** The API returns `last_message` with `content` (not `body`) and `sender_id` (not `is_own`). */
  last_message: {
    id?: number;
    body?: string;
    content?: string;
    sender_id?: number;
    created_at: string;
    is_own?: boolean;
    is_read?: boolean;
  } | null;
  unread_count: number;
  /** The authenticated user's ID, populated from conversation metadata. */
  sender_id?: number;
}

export interface MessageSender {
  id: number;
  name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  organization_name?: string | null;
  avatar_url?: string | null;
}

export interface Message {
  id: number;
  body: string;
  sender: MessageSender;
  sender_id?: number;
  receiver_id?: number;
  receiver?: MessageSender | null;
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
export function getConversations(cursor?: string | null): Promise<ConversationListResponse> {
  const params: Record<string, string> = {};
  if (cursor) params.cursor = cursor;
  return api.get<ConversationListResponse>(`${API_V2}/messages`, params);
}

/** GET /api/v2/messages/:otherUserId — message thread for a conversation */
export function getThread(otherUserId: number, cursor?: string | null): Promise<MessageListResponse> {
  const params: Record<string, string> = {};
  if (cursor) params.cursor = cursor;
  return api.get<MessageListResponse>(`${API_V2}/messages/${otherUserId}`, params);
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

/** DELETE /api/v2/messages/conversations/:conversationId — delete a conversation */
export function deleteConversation(conversationId: number): Promise<void> {
  return api.delete(`${API_V2}/messages/conversations/${conversationId}`);
}

/** POST /api/v2/messages — send a message to a recipient */
export function sendMessage(recipientId: number, body: string): Promise<{ data: Message }> {
  return api.post<{ data: Message }>(`${API_V2}/messages`, { recipient_id: recipientId, body });
}
