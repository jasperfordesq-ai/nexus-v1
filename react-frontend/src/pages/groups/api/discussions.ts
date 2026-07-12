// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export interface GroupDiscussionAuthor {
  id: number;
  name: string;
  avatar_url?: string | null;
}

export interface GroupDiscussion {
  id: number;
  title: string;
  content?: string;
  author: GroupDiscussionAuthor;
  reply_count: number;
  comments_count?: number;
  likes_count?: number;
  is_liked?: boolean;
  is_pinned?: boolean;
  last_reply_at?: string | null;
  created_at: string;
}

export interface GroupDiscussionMessage {
  id: number;
  content: string;
  author: GroupDiscussionAuthor;
  is_own?: boolean;
  created_at: string;
}

export interface GroupDiscussionDetail extends GroupDiscussion {
  messages: GroupDiscussionMessage[];
  messagesNextCursor?: string;
  messagesHasMore: boolean;
}

export interface GroupDiscussionPage {
  discussions: GroupDiscussion[];
  nextCursor?: string;
  hasMore: boolean;
}

export interface GroupDiscussionReadOptions {
  cursor?: string;
  perPage?: number;
  signal?: AbortSignal;
}

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function invalidResponse(): never {
  throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
}

function isBooleanFlag(value: unknown): value is boolean | 0 | 1 {
  return typeof value === 'boolean' || value === 0 || value === 1;
}

function isPositiveInteger(value: unknown): value is number {
  return typeof value === 'number' && Number.isInteger(value) && value > 0;
}

function normalizeAuthor(value: unknown): GroupDiscussionAuthor {
  if (!isRecord(value)
    || !isPositiveInteger(value.id)
    || typeof value.name !== 'string'
    || (value.avatar_url !== undefined && value.avatar_url !== null && typeof value.avatar_url !== 'string')) {
    return invalidResponse();
  }

  return {
    id: value.id,
    name: value.name,
    ...(value.avatar_url === undefined ? {} : { avatar_url: value.avatar_url }),
  };
}

function normalizeDiscussion(value: unknown): GroupDiscussion {
  if (!isRecord(value)
    || !isPositiveInteger(value.id)
    || typeof value.title !== 'string'
    || (value.content !== undefined && typeof value.content !== 'string')
    || !Number.isInteger(value.reply_count)
    || (value.reply_count as number) < 0
    || typeof value.created_at !== 'string'
    || (value.last_reply_at !== undefined && value.last_reply_at !== null && typeof value.last_reply_at !== 'string')
    || (value.is_pinned !== undefined && !isBooleanFlag(value.is_pinned))
    || (value.is_liked !== undefined && !isBooleanFlag(value.is_liked))
    || (value.comments_count !== undefined && (!Number.isInteger(value.comments_count) || (value.comments_count as number) < 0))
    || (value.likes_count !== undefined && (!Number.isInteger(value.likes_count) || (value.likes_count as number) < 0))) {
    return invalidResponse();
  }

  return {
    ...(value as unknown as GroupDiscussion),
    author: normalizeAuthor(value.author),
    ...(value.is_pinned === undefined ? {} : { is_pinned: Boolean(value.is_pinned) }),
    ...(value.is_liked === undefined ? {} : { is_liked: Boolean(value.is_liked) }),
  };
}

function normalizeMessage(value: unknown): GroupDiscussionMessage {
  if (!isRecord(value)
    || !isPositiveInteger(value.id)
    || typeof value.content !== 'string'
    || typeof value.created_at !== 'string'
    || (value.is_own !== undefined && !isBooleanFlag(value.is_own))) {
    return invalidResponse();
  }

  return {
    ...(value as unknown as GroupDiscussionMessage),
    author: normalizeAuthor(value.author),
    ...(value.is_own === undefined ? {} : { is_own: Boolean(value.is_own) }),
  };
}

function readPaginationMeta(response: unknown): { nextCursor?: string; hasMore: boolean } {
  if (!isRecord(response) || !isRecord(response.meta) || typeof response.meta.has_more !== 'boolean') {
    return invalidResponse();
  }

  const cursor = response.meta.cursor ?? response.meta.next_cursor;
  if (cursor !== undefined && cursor !== null && typeof cursor !== 'string') {
    return invalidResponse();
  }
  if (response.meta.has_more && (typeof cursor !== 'string' || cursor === '')) {
    return invalidResponse();
  }
  if (!response.meta.has_more && typeof cursor === 'string' && cursor !== '') {
    return invalidResponse();
  }

  return {
    ...(typeof cursor === 'string' && cursor !== '' ? { nextCursor: cursor } : {}),
    hasMore: response.meta.has_more,
  };
}

async function normalizeRequest(request: Promise<import('./core').GroupApiResponse<unknown>>): Promise<unknown> {
  try {
    return unwrapGroupResponse(await request);
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function listGroupDiscussions(
  groupId: number,
  options: GroupDiscussionReadOptions = {},
): Promise<GroupDiscussionPage> {
  const perPage = options.perPage ?? 15;
  const params = new URLSearchParams({ per_page: String(perPage) });
  if (options.cursor) params.set('cursor', options.cursor);

  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/discussions?${params.toString()}`,
      { signal: options.signal },
    );
    const payload = unwrapGroupResponse(response);
    if (!Array.isArray(payload)) return invalidResponse();

    return {
      discussions: payload.map(normalizeDiscussion),
      ...readPaginationMeta(response),
    };
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function createGroupDiscussion(
  groupId: number,
  input: { title: string; content: string },
): Promise<GroupDiscussion> {
  return normalizeDiscussion(await normalizeRequest(api.post<unknown>(
    `/v2/groups/${groupId}/discussions`,
    input,
  )));
}

export async function getGroupDiscussion(
  groupId: number,
  discussionId: number,
  options: GroupDiscussionReadOptions = {},
): Promise<GroupDiscussionDetail> {
  const perPage = options.perPage ?? 50;
  const params = new URLSearchParams({ per_page: String(perPage) });
  if (options.cursor) params.set('cursor', options.cursor);

  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/discussions/${discussionId}?${params.toString()}`,
      { signal: options.signal },
    );
    const payload = unwrapGroupResponse(response);
    if (!isRecord(payload) || !Array.isArray(payload.messages)) return invalidResponse();
    const pagination = readPaginationMeta(response);

    return {
      ...normalizeDiscussion(payload.discussion),
      messages: payload.messages.map(normalizeMessage),
      messagesNextCursor: pagination.nextCursor,
      messagesHasMore: pagination.hasMore,
    };
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function replyToGroupDiscussion(
  groupId: number,
  discussionId: number,
  content: string,
): Promise<GroupDiscussionMessage> {
  return normalizeMessage(await normalizeRequest(api.post<unknown>(
    `/v2/groups/${groupId}/discussions/${discussionId}/messages`,
    { content },
  )));
}
