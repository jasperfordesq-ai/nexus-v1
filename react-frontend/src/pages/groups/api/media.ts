// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export type GroupMediaType = 'all' | 'image' | 'video';

export interface GroupMediaItem {
  id: number;
  group_id: number;
  type: 'image' | 'video';
  original_name: string | null;
  mime_type: string | null;
  url: string;
  thumbnail_url: string | null;
  caption: string | null;
  file_size: number;
  width: number | null;
  height: number | null;
  uploaded_by: number;
  uploader_name: string;
  uploader_avatar: string | null;
  uploader: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  created_at: string;
  updated_at: string;
  capabilities: {
    can_view: boolean;
    can_delete: boolean;
  };
}

export interface GroupMediaPage {
  items: GroupMediaItem[];
  cursor: string | null;
  hasMore: boolean;
}

export interface ListGroupMediaOptions {
  cursor?: string | null;
  type?: GroupMediaType;
  perPage?: number;
  signal?: AbortSignal;
}

type UnknownRecord = Record<string, unknown>;

function asRecord(value: unknown): UnknownRecord | null {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
    ? value as UnknownRecord
    : null;
}

function readString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function readNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) return parsed;
  }
  return null;
}

function invalidResponse(): never {
  throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
}

function normalizeMediaItem(value: unknown, groupId: number): GroupMediaItem {
  const record = asRecord(value);
  if (!record) invalidResponse();

  const id = readNumber(record.id);
  const type = readString(record.type) ?? readString(record.media_type);
  const url = readString(record.url);
  const uploadedBy = readNumber(record.uploaded_by);
  const uploaderName = readString(record.uploader_name);
  const createdAt = readString(record.created_at);
  const updatedAt = readString(record.updated_at);
  const fileSize = readNumber(record.file_size);
  const uploader = asRecord(record.uploader);
  const capabilities = asRecord(record.capabilities);
  if (
    id === null
    || (type !== 'image' && type !== 'video')
    || !url
    || uploadedBy === null
    || !uploaderName
    || !createdAt
    || !updatedAt
    || fileSize === null
    || readNumber(uploader?.id) !== uploadedBy
    || readString(uploader?.name) !== uploaderName
    || typeof capabilities?.can_view !== 'boolean'
    || typeof capabilities?.can_delete !== 'boolean'
  ) {
    invalidResponse();
  }

  return {
    id,
    group_id: readNumber(record.group_id) ?? groupId,
    type,
    original_name: typeof record.original_name === 'string' ? record.original_name : null,
    mime_type: typeof record.mime_type === 'string' ? record.mime_type : null,
    url,
    thumbnail_url: typeof record.thumbnail_url === 'string' ? record.thumbnail_url : null,
    caption: typeof record.caption === 'string' ? record.caption : null,
    file_size: fileSize,
    width: readNumber(record.width),
    height: readNumber(record.height),
    uploaded_by: uploadedBy,
    uploader_name: uploaderName,
    uploader_avatar: typeof record.uploader_avatar === 'string' ? record.uploader_avatar : null,
    uploader: {
      id: uploadedBy,
      name: uploaderName,
      avatar_url: typeof uploader?.avatar_url === 'string' ? uploader.avatar_url : null,
    },
    created_at: createdAt,
    updated_at: updatedAt,
    capabilities: {
      can_view: capabilities.can_view,
      can_delete: capabilities.can_delete,
    },
  };
}

function normalizeMediaPage(payload: unknown, groupId: number): GroupMediaPage {
  const record = asRecord(payload);
  if (!record || !Array.isArray(record.items)) invalidResponse();
  if (record.cursor !== null && record.cursor !== undefined && typeof record.cursor !== 'string') {
    invalidResponse();
  }
  if (typeof record.has_more !== 'boolean') invalidResponse();

  return {
    items: record.items.map((item) => normalizeMediaItem(item, groupId)),
    cursor: typeof record.cursor === 'string' ? record.cursor : null,
    hasMore: record.has_more,
  };
}

/** List one cursor page of group media. */
export async function listGroupMedia(
  groupId: number,
  options: ListGroupMediaOptions = {},
): Promise<GroupMediaPage> {
  try {
    const params = new URLSearchParams({ per_page: String(options.perPage ?? 20) });
    if (options.cursor) params.set('cursor', options.cursor);
    if (options.type && options.type !== 'all') params.set('type', options.type);

    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/media?${params.toString()}`,
      { signal: options.signal },
    );
    return normalizeMediaPage(unwrapGroupResponse(response), groupId);
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Upload image or video media through the authenticated multipart client. */
export async function uploadGroupMedia(groupId: number, file: File, caption = ''): Promise<number> {
  try {
    const formData = new FormData();
    formData.append('file', file);
    if (caption.trim()) formData.append('caption', caption.trim());
    const payload = asRecord(unwrapGroupResponse(await api.upload<unknown>(
      `/v2/groups/${groupId}/media`,
      formData,
    )));
    const id = readNumber(payload?.id);
    if (id === null) invalidResponse();
    return id;
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Fetch a protected gallery asset using the authenticated tenant-aware client. */
export async function getGroupMediaBlob(url: string): Promise<Blob> {
  try {
    const endpoint = url.startsWith('/api/') ? url.slice('/api'.length) : url;
    return await api.download(endpoint);
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Delete group media after the caller has obtained user confirmation. */
export async function deleteGroupMedia(groupId: number, mediaId: number): Promise<void> {
  try {
    const payload = unwrapGroupResponse(await api.delete<unknown>(
      `/v2/groups/${groupId}/media/${mediaId}`,
    ));
    if (!asRecord(payload)) invalidResponse();
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
