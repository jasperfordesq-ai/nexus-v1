// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { GROUP_API_MESSAGE_KEYS } from './core';
import {
  createGroup,
  getEditableGroup,
  getGroupTemplates,
  updateGroup,
  uploadGroupImage,
  type SaveGroupPayload,
} from './createGroup';

const { mockGet, mockPost, mockPut, mockUpload } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
  mockUpload: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: mockGet,
    post: mockPost,
    put: mockPut,
    upload: mockUpload,
  },
}));

const payload: SaveGroupPayload = {
  name: 'Garden Crew',
  description: 'A sufficiently detailed group description.',
  visibility: 'private',
  location: 'Cork, Ireland',
  latitude: 51.8985,
  longitude: -8.4756,
};

describe('Create Group API', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('reads and projects templates with cancellation', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: [{ id: 2, name: 'Local group', icon: '🌱', default_visibility: 'public', ignored: true }],
    });

    await expect(getGroupTemplates({ signal: controller.signal })).resolves.toEqual([
      { id: 2, name: 'Local group', icon: '🌱', default_visibility: 'public' },
    ]);
    expect(mockGet).toHaveBeenCalledWith('/v2/group-templates', { signal: controller.signal });
  });

  it('reads the editable group projection with cancellation', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: {
        id: 9,
        name: 'Garden Crew',
        description: null,
        visibility: 'private',
        location: null,
        latitude: null,
        longitude: -8.4,
        image_url: '/group.png',
      },
    });

    await expect(getEditableGroup(9, { signal: controller.signal })).resolves.toMatchObject({
      id: 9,
      name: 'Garden Crew',
      description: '',
      visibility: 'private',
      location: '',
      latitude: undefined,
      longitude: -8.4,
      image_url: '/group.png',
    });
    expect(mockGet).toHaveBeenCalledWith('/v2/groups/9', { signal: controller.signal });
  });

  it('creates and updates with exact payloads and identifiers', async () => {
    mockPost.mockResolvedValue({ success: true, data: { id: 21, name: 'Garden Crew' } });
    mockPut.mockResolvedValue({ success: true, data: { id: 21, name: 'Garden Crew' } });

    await expect(createGroup(payload)).resolves.toEqual({ id: 21 });
    expect(mockPost).toHaveBeenCalledWith('/v2/groups', payload);
    await expect(updateGroup(21, payload)).resolves.toEqual({ id: 21 });
    expect(mockPut).toHaveBeenCalledWith('/v2/groups/21', payload);
  });

  it('uploads the selected image under the backend image field', async () => {
    const image = new File(['pixels'], 'group.png', { type: 'image/png' });
    mockUpload.mockResolvedValue({ success: true, data: { image_url: '/uploads/group.png' } });

    await expect(uploadGroupImage(21, image)).resolves.toEqual({ image_url: '/uploads/group.png' });
    expect(mockUpload).toHaveBeenCalledWith('/v2/groups/21/image', image, 'image');
  });

  it('rejects resolved create and upload failures instead of reporting success', async () => {
    mockPost.mockResolvedValue({ success: false, code: 'HTTP_422', error: 'Raw validation copy' });
    await expect(createGroup(payload)).rejects.toMatchObject({
      code: 'VALIDATION_FAILED',
      messageKey: GROUP_API_MESSAGE_KEYS.validation,
    });

    mockUpload.mockResolvedValue({ success: false, code: 'HTTP_500', error: 'Raw upload copy' });
    await expect(uploadGroupImage(21, new File(['x'], 'group.png'))).rejects.toMatchObject({
      code: 'SERVER_ERROR',
      messageKey: GROUP_API_MESSAGE_KEYS.server,
    });
  });

  it('rejects malformed successful mutations', async () => {
    mockPost.mockResolvedValue({ success: true, data: {} });
    await expect(createGroup(payload)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });

    mockUpload.mockResolvedValue({ success: true, data: { image_url: '' } });
    await expect(uploadGroupImage(21, new File(['x'], 'group.png'))).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
    });
  });

  it('normalizes cancellation from create-page reads', async () => {
    mockGet.mockRejectedValue(Object.assign(new Error('aborted'), { name: 'AbortError' }));

    await expect(getEditableGroup(9)).rejects.toMatchObject({
      code: 'CANCELLED',
      messageKey: GROUP_API_MESSAGE_KEYS.cancelled,
    });
  });

  it('normalizes thrown create transport failures', async () => {
    mockPost.mockRejectedValue(new TypeError('Private network detail'));

    await expect(createGroup(payload)).rejects.toMatchObject({
      code: 'NETWORK_ERROR',
      messageKey: GROUP_API_MESSAGE_KEYS.network,
      retryable: true,
    });
  });
});
