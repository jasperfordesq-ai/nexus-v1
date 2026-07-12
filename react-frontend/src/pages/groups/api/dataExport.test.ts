// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';

const { api } = vi.hoisted(() => ({
  api: {
    post: vi.fn(),
    get: vi.fn(),
    download: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api }));

import {
  downloadGroupDataExport,
  getGroupDataExport,
  requestGroupDataExport,
} from './dataExport';

const exportRecord = {
  id: '8dc00f9c-09b7-42f1-a9de-ff246c839843',
  status: 'completed',
  byte_size: 1024,
  created_at: '2026-07-11T10:00:00Z',
  completed_at: '2026-07-11T10:00:02Z',
  expires_at: '2026-07-12T10:00:00Z',
  download_url: '/api/v2/groups/9/exports/8dc00f9c-09b7-42f1-a9de-ff246c839843/download',
};

describe('group data export adapter', () => {
  beforeEach(() => vi.resetAllMocks());

  it('requests, polls, and downloads through authenticated transports', async () => {
    api.post.mockResolvedValue({ success: true, data: exportRecord });
    api.get.mockResolvedValue({ success: true, data: exportRecord });
    api.download.mockResolvedValue(undefined);

    await expect(requestGroupDataExport(9)).resolves.toEqual(exportRecord);
    await expect(getGroupDataExport(9, exportRecord.id)).resolves.toEqual(exportRecord);
    await expect(downloadGroupDataExport(9, exportRecord.id)).resolves.toBeUndefined();

    expect(api.post).toHaveBeenCalledWith('/v2/groups/9/exports', {});
    expect(api.get).toHaveBeenCalledWith(`/v2/groups/9/exports/${exportRecord.id}`, { signal: undefined });
    expect(api.download).toHaveBeenCalledWith(
      `/v2/groups/9/exports/${exportRecord.id}/download`,
      { filename: 'group-9-export.json' },
    );
  });

  it('rejects malformed and resolved failure envelopes', async () => {
    api.post.mockResolvedValueOnce({ success: true, data: { id: 3, status: 'done' } });
    await expect(requestGroupDataExport(9)).rejects.toMatchObject({ sourceCode: 'INVALID_RESPONSE' });

    api.get.mockResolvedValueOnce({ success: false, code: 'FORBIDDEN', status: 403 });
    await expect(getGroupDataExport(9, exportRecord.id)).rejects.toMatchObject({ status: 403 });
  });
});
