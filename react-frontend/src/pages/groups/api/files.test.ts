// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  deleteGroupFile,
  downloadGroupFile,
  listGroupFileFolders,
  listGroupFiles,
  uploadGroupFile,
} from './files';

const { mockDelete, mockDownload, mockGet, mockUpload } = vi.hoisted(() => ({
  mockDelete: vi.fn(),
  mockDownload: vi.fn(),
  mockGet: vi.fn(),
  mockUpload: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: {
    delete: mockDelete,
    download: mockDownload,
    get: mockGet,
    upload: mockUpload,
  },
}));

const fileItem = (overrides: Record<string, unknown> = {}) => ({
  id: 3,
  group_id: 8,
  file_name: 'guide.pdf',
  file_type: 'application/pdf',
  file_size: 1024,
  uploaded_by: 5,
  uploader_name: 'Member',
  uploader_avatar: null,
  uploader: { id: 5, name: 'Member', avatar_url: null },
  folder: 'Guides',
  description: null,
  download_count: 0,
  created_at: '2026-07-11T10:00:00Z',
  updated_at: '2026-07-11T10:00:00Z',
  capabilities: { can_download: true, can_delete: false },
  ...overrides,
});

describe('group files adapter', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('lists a filtered cursor page and forwards AbortSignal', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: { items: [fileItem()], cursor: 'next', has_more: true },
    });

    await expect(listGroupFiles(8, {
      cursor: 'previous',
      folder: 'Shared Guides',
      query: 'safety checklist',
      signal: controller.signal,
    })).resolves.toEqual({
      items: [fileItem()],
      cursor: 'next',
      hasMore: true,
    });
    expect(mockGet).toHaveBeenCalledWith(
      '/v2/groups/8/files?per_page=20&cursor=previous&folder=Shared+Guides&q=safety+checklist',
      { signal: controller.signal },
    );
  });

  it('lists folder facets through the normalized response contract', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: [{ folder: 'Guides', file_count: 2 }],
    });
    await expect(listGroupFileFolders(8)).resolves.toEqual([
      { folder: 'Guides', file_count: 2 },
    ]);
  });

  it('uploads file metadata as multipart data and validates the created id', async () => {
    mockUpload.mockResolvedValue({ success: true, data: { id: 44 } });
    const file = new File(['guide'], 'guide.pdf', { type: 'application/pdf' });

    await expect(uploadGroupFile(8, {
      file,
      folder: ' Guides ',
      description: ' Useful ',
    })).resolves.toBe(44);

    expect(mockUpload).toHaveBeenCalledWith('/v2/groups/8/files', expect.any(FormData));
    const formData = mockUpload.mock.calls[0]?.[1] as FormData;
    expect(formData.get('file')).toBe(file);
    expect(formData.get('folder')).toBe('Guides');
    expect(formData.get('description')).toBe('Useful');
  });

  it('uses the authenticated download client with the requested filename', async () => {
    mockDownload.mockResolvedValue(new Blob(['file']));
    await expect(downloadGroupFile(8, 3, 'guide.pdf')).resolves.toBeUndefined();
    expect(mockDownload).toHaveBeenCalledWith(
      '/v2/groups/8/files/3/download',
      { filename: 'guide.pdf' },
    );
  });

  it('deletes only after a successful API envelope', async () => {
    mockDelete.mockResolvedValue({ success: true, data: { message: 'deleted' } });
    await expect(deleteGroupFile(8, 3)).resolves.toBeUndefined();
    expect(mockDelete).toHaveBeenCalledWith('/v2/groups/8/files/3');
  });

  it.each([
    [{ success: false, code: 'HTTP_500' }, 'SERVER_ERROR'],
    [{ success: true, data: { items: 'not-an-array', cursor: null, has_more: false } }, 'INVALID_RESPONSE'],
  ] as const)('normalizes resolved and malformed list failures', async (response, code) => {
    mockGet.mockResolvedValue(response);
    await expect(listGroupFiles(8)).rejects.toMatchObject({ code, retryable: true });
  });

  it('normalizes cancellation without treating it as retryable', async () => {
    mockGet.mockRejectedValue(Object.assign(new Error('aborted'), { name: 'AbortError' }));
    await expect(listGroupFiles(8)).rejects.toMatchObject({
      code: 'CANCELLED',
      retryable: false,
    });
  });

  it('normalizes transport download failures', async () => {
    mockDownload.mockRejectedValue(new TypeError('Failed to fetch'));
    await expect(downloadGroupFile(8, 3, 'guide.pdf')).rejects.toMatchObject({
      code: 'NETWORK_ERROR',
      retryable: true,
    });
  });

  it('rejects malformed upload and resolved delete failures', async () => {
    mockUpload.mockResolvedValue({ success: true, data: {} });
    const file = new File(['guide'], 'guide.pdf', { type: 'application/pdf' });
    await expect(uploadGroupFile(8, { file })).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });

    mockDelete.mockResolvedValue({ success: false, code: 'HTTP_403' });
    await expect(deleteGroupFile(8, 3)).rejects.toMatchObject({ code: 'FORBIDDEN' });
  });
});
