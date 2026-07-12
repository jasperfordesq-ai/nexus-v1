// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export type GroupDataExportStatus = 'queued' | 'processing' | 'completed' | 'failed' | 'expired';

export interface GroupDataExportRecord {
  id: string;
  status: GroupDataExportStatus;
  byte_size: number | null;
  created_at: string | null;
  completed_at: string | null;
  expires_at: string | null;
  download_url: string | null;
}

export interface GroupDataExportReadOptions {
  signal?: AbortSignal;
}

function normalizeExport(value: unknown): GroupDataExportRecord {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
  }
  const row = value as Record<string, unknown>;
  const statuses: GroupDataExportStatus[] = ['queued', 'processing', 'completed', 'failed', 'expired'];
  if (typeof row.id !== 'string' || !statuses.includes(row.status as GroupDataExportStatus)) {
    throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
  }

  return {
    id: row.id,
    status: row.status as GroupDataExportStatus,
    byte_size: typeof row.byte_size === 'number' ? row.byte_size : null,
    created_at: typeof row.created_at === 'string' ? row.created_at : null,
    completed_at: typeof row.completed_at === 'string' ? row.completed_at : null,
    expires_at: typeof row.expires_at === 'string' ? row.expires_at : null,
    download_url: typeof row.download_url === 'string' ? row.download_url : null,
  };
}

export async function requestGroupDataExport(groupId: number): Promise<GroupDataExportRecord> {
  try {
    return normalizeExport(unwrapGroupResponse(await api.post<unknown>(
      `/v2/groups/${groupId}/exports`,
      {},
    )));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function getGroupDataExport(
  groupId: number,
  exportId: string,
  options: GroupDataExportReadOptions = {},
): Promise<GroupDataExportRecord> {
  try {
    return normalizeExport(unwrapGroupResponse(await api.get<unknown>(
      `/v2/groups/${groupId}/exports/${encodeURIComponent(exportId)}`,
      { signal: options.signal },
    )));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function downloadGroupDataExport(groupId: number, exportId: string): Promise<void> {
  try {
    await api.download(
      `/v2/groups/${groupId}/exports/${encodeURIComponent(exportId)}/download`,
      { filename: `group-${groupId}-export.json` },
    );
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
