// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { deleteGroupMedia, listGroupMedia, uploadGroupMedia } from './media';

const { mockDelete, mockGet, mockUpload } = vi.hoisted(() => ({
  mockDelete: vi.fn(),
  mockGet: vi.fn(),
  mockUpload: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: {
    delete: mockDelete,
    get: mockGet,
    upload: mockUpload,
  },
}));

const mediaItem = (overrides: Record<string, unknown> = {}) => ({
  id: 6,
  type: 'image',
  original_name: 'photo.jpg',
  mime_type: 'image/jpeg',
  url: 'https://cdn.example.test/photo.jpg',
  thumbnail_url: null,
  caption: 'Group photo',
  file_size: 2048,
  width: null,
  height: null,
  uploaded_by: 4,
  uploader_name: 'Member',
  uploader_avatar: null,
  uploader: { id: 4, name: 'Member', avatar_url: null },
  created_at: '2026-07-11T10:00:00Z',
  updated_at: '2026-07-11T10:00:00Z',
  capabilities: { can_view: true, can_delete: false },
  ...overrides,
});

describe('group media adapter', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('lists a filtered cursor page, forwards AbortSignal, and fills the group id', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: { items: [mediaItem()], cursor: 'next', has_more: true },
    });

    await expect(listGroupMedia(9, {
      cursor: 'previous',
      type: 'image',
      signal: controller.signal,
    })).resolves.toEqual({
      items: [expect.objectContaining({ id: 6, group_id: 9, type: 'image' })],
      cursor: 'next',
      hasMore: true,
    });
    expect(mockGet).toHaveBeenCalledWith(
      '/v2/groups/9/media?per_page=20&cursor=previous&type=image',
      { signal: controller.signal },
    );
  });

  it('accepts the media_type field returned by the upload persistence model', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        items: [mediaItem({ type: undefined, media_type: 'video' })],
        cursor: null,
        has_more: false,
      },
    });
    await expect(listGroupMedia(9)).resolves.toEqual(expect.objectContaining({
      items: [expect.objectContaining({ type: 'video' })],
    }));
  });

  it('uploads media as multipart data and validates the created id', async () => {
    mockUpload.mockResolvedValue({ success: true, data: { id: 51 } });
    const file = new File(['photo'], 'photo.jpg', { type: 'image/jpeg' });
    await expect(uploadGroupMedia(9, file)).resolves.toBe(51);
    expect(mockUpload).toHaveBeenCalledWith('/v2/groups/9/media', expect.any(FormData));
    expect((mockUpload.mock.calls[0]?.[1] as FormData).get('file')).toBe(file);
  });

  it('deletes only after a successful response envelope', async () => {
    mockDelete.mockResolvedValue({ success: true, data: { message: 'deleted' } });
    await expect(deleteGroupMedia(9, 6)).resolves.toBeUndefined();
    expect(mockDelete).toHaveBeenCalledWith('/v2/groups/9/media/6');
  });

  it.each([
    [{ success: false, code: 'HTTP_403' }, 'FORBIDDEN', false],
    [{ success: true, data: [] }, 'INVALID_RESPONSE', true],
  ] as const)('normalizes resolved and malformed list failures', async (response, code, retryable) => {
    mockGet.mockResolvedValue(response);
    await expect(listGroupMedia(9)).rejects.toMatchObject({ code, retryable });
  });

  it.each([
    [new TypeError('Failed to fetch'), 'NETWORK_ERROR', true],
    [Object.assign(new Error('aborted'), { name: 'AbortError' }), 'CANCELLED', false],
  ] as const)('normalizes transport and cancellation failures', async (failure, code, retryable) => {
    mockGet.mockRejectedValue(failure);
    await expect(listGroupMedia(9)).rejects.toMatchObject({ code, retryable });
  });

  it('rejects malformed upload and resolved delete failures', async () => {
    const file = new File(['photo'], 'photo.jpg', { type: 'image/jpeg' });
    mockUpload.mockResolvedValue({ success: true, data: {} });
    await expect(uploadGroupMedia(9, file)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });

    mockDelete.mockResolvedValue({ success: false, code: 'HTTP_500' });
    await expect(deleteGroupMedia(9, 6)).rejects.toMatchObject({ code: 'SERVER_ERROR' });
  });
});
