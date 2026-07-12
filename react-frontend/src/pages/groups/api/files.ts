// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export interface GroupFile {
  id: number;
  group_id: number;
  file_name: string;
  file_type: string;
  file_size: number;
  uploaded_by: number;
  uploader_name: string;
  uploader_avatar: string | null;
  uploader: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  folder: string | null;
  description: string | null;
  download_count?: number;
  created_at: string;
  updated_at: string;
  capabilities: {
    can_download: boolean;
    can_delete: boolean;
  };
}

export interface GroupFileFolder {
  folder: string;
  file_count: number;
}

export interface GroupFilePage {
  items: GroupFile[];
  cursor: string | null;
  hasMore: boolean;
}

export interface ListGroupFilesOptions {
  cursor?: string | null;
  folder?: string | null;
  query?: string;
  perPage?: number;
  signal?: AbortSignal;
}

export interface ListGroupFileFoldersOptions {
  signal?: AbortSignal;
}

export interface UploadGroupFileInput {
  file: File;
  folder?: string;
  description?: string;
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

function normalizeFile(value: unknown, groupId: number): GroupFile {
  const record = asRecord(value);
  if (!record) invalidResponse();

  const id = readNumber(record.id);
  const fileName = readString(record.file_name);
  const fileType = readString(record.file_type);
  const fileSize = readNumber(record.file_size);
  const uploadedBy = readNumber(record.uploaded_by);
  const uploaderName = readString(record.uploader_name);
  const createdAt = readString(record.created_at);
  const updatedAt = readString(record.updated_at);
  const uploader = asRecord(record.uploader);
  const capabilities = asRecord(record.capabilities);
  if (
    id === null
    || !fileName
    || !fileType
    || fileSize === null
    || uploadedBy === null
    || !uploaderName
    || !createdAt
    || !updatedAt
    || readNumber(uploader?.id) !== uploadedBy
    || readString(uploader?.name) !== uploaderName
    || typeof capabilities?.can_download !== 'boolean'
    || typeof capabilities?.can_delete !== 'boolean'
  ) {
    invalidResponse();
  }

  const downloadCount = readNumber(record.download_count);
  return {
    id,
    group_id: readNumber(record.group_id) ?? groupId,
    file_name: fileName,
    file_type: fileType,
    file_size: fileSize,
    uploaded_by: uploadedBy,
    uploader_name: uploaderName,
    uploader_avatar: typeof record.uploader_avatar === 'string' ? record.uploader_avatar : null,
    uploader: {
      id: uploadedBy,
      name: uploaderName,
      avatar_url: typeof uploader?.avatar_url === 'string' ? uploader.avatar_url : null,
    },
    folder: typeof record.folder === 'string' ? record.folder : null,
    description: typeof record.description === 'string' ? record.description : null,
    ...(downloadCount === null ? {} : { download_count: downloadCount }),
    created_at: createdAt,
    updated_at: updatedAt,
    capabilities: {
      can_download: capabilities.can_download,
      can_delete: capabilities.can_delete,
    },
  };
}

function normalizeFilePage(payload: unknown, groupId: number): GroupFilePage {
  const record = asRecord(payload);
  if (!record || !Array.isArray(record.items)) invalidResponse();
  if (record.cursor !== null && record.cursor !== undefined && typeof record.cursor !== 'string') {
    invalidResponse();
  }
  if (typeof record.has_more !== 'boolean') invalidResponse();

  return {
    items: record.items.map((item) => normalizeFile(item, groupId)),
    cursor: typeof record.cursor === 'string' ? record.cursor : null,
    hasMore: record.has_more,
  };
}

/** List one cursor page of group files. */
export async function listGroupFiles(
  groupId: number,
  options: ListGroupFilesOptions = {},
): Promise<GroupFilePage> {
  try {
    const params = new URLSearchParams({ per_page: String(options.perPage ?? 20) });
    if (options.cursor) params.set('cursor', options.cursor);
    if (options.folder) params.set('folder', options.folder);
    if (options.query?.trim()) params.set('q', options.query.trim());

    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/files?${params.toString()}`,
      { signal: options.signal },
    );
    return normalizeFilePage(unwrapGroupResponse(response), groupId);
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** List the folder facets used by the Files filter. */
export async function listGroupFileFolders(
  groupId: number,
  options: ListGroupFileFoldersOptions = {},
): Promise<GroupFileFolder[]> {
  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/files/folders`,
      { signal: options.signal },
    );
    const payload = unwrapGroupResponse(response);
    if (!Array.isArray(payload)) invalidResponse();
    return payload.map((value) => {
      const record = asRecord(value);
      const folder = readString(record?.folder);
      const fileCount = readNumber(record?.file_count);
      if (!folder || fileCount === null) invalidResponse();
      return { folder, file_count: fileCount };
    });
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Upload a group file through the authenticated multipart client. */
export async function uploadGroupFile(
  groupId: number,
  input: UploadGroupFileInput,
): Promise<number> {
  try {
    const formData = new FormData();
    formData.append('file', input.file);
    if (input.folder?.trim()) formData.append('folder', input.folder.trim());
    if (input.description?.trim()) formData.append('description', input.description.trim());

    const payload = asRecord(unwrapGroupResponse(await api.upload<unknown>(
      `/v2/groups/${groupId}/files`,
      formData,
    )));
    const id = readNumber(payload?.id);
    if (id === null) invalidResponse();
    return id;
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Download a protected group file through the tenant-aware API client. */
export async function downloadGroupFile(
  groupId: number,
  fileId: number,
  filename: string,
): Promise<void> {
  try {
    await api.download(`/v2/groups/${groupId}/files/${fileId}/download`, { filename });
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Delete a group file after the caller has obtained user confirmation. */
export async function deleteGroupFile(groupId: number, fileId: number): Promise<void> {
  try {
    const payload = unwrapGroupResponse(await api.delete<unknown>(
      `/v2/groups/${groupId}/files/${fileId}`,
    ));
    if (!asRecord(payload)) invalidResponse();
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
