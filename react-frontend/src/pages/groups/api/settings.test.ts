// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { GROUP_API_MESSAGE_KEYS } from './core';
import {
  getGroupNotificationPreferences,
  getGroupWelcomeConfig,
  updateGroupNotificationPreferences,
  updateGroupWelcomeConfig,
} from './settings';

const { mockGet, mockPut } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPut: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: mockGet,
    put: mockPut,
  },
}));

describe('Groups settings API', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('reads notification preferences with cancellation and normalizes database flags', async () => {
    const controller = new AbortController();
    mockGet.mockResolvedValue({
      success: true,
      data: {
        frequency: 'digest',
        email_enabled: 0,
        push_enabled: '1',
        updated_at: '2026-07-11T16:20:30.000000Z',
      },
    });

    await expect(getGroupNotificationPreferences(12, { signal: controller.signal })).resolves.toEqual({
      frequency: 'digest',
      email_enabled: false,
      push_enabled: true,
      updated_at: '2026-07-11T16:20:30.000000Z',
    });
    expect(mockGet).toHaveBeenCalledWith(
      '/v2/groups/12/notification-prefs',
      { signal: controller.signal },
    );
  });

  it('writes notification preferences and returns the acknowledgement contract', async () => {
    const preferences = {
      frequency: 'muted' as const,
      email_enabled: false,
      push_enabled: false,
      updated_at: null,
    };
    const persisted = {
      ...preferences,
      updated_at: '2026-07-11T16:21:00.000000Z',
    };
    mockPut.mockResolvedValue({
      success: true,
      data: { message: 'Preferences updated', preferences: persisted },
    });

    await expect(updateGroupNotificationPreferences(7, preferences)).resolves.toEqual({
      message: 'Preferences updated',
      preferences: persisted,
    });
    expect(mockPut).toHaveBeenCalledWith('/v2/groups/7/notification-prefs', {
      frequency: 'muted',
      email_enabled: false,
      push_enabled: false,
    });
  });

  it('reads and writes the exact welcome configuration contract', async () => {
    const controller = new AbortController();
    const config = { enabled: true, message: 'Welcome, {member_name}!' };
    mockGet.mockResolvedValue({ success: true, data: config });
    mockPut.mockResolvedValue({ success: true, data: config });

    await expect(getGroupWelcomeConfig(9, { signal: controller.signal })).resolves.toEqual(config);
    expect(mockGet).toHaveBeenCalledWith('/v2/groups/9/welcome', { signal: controller.signal });
    await expect(updateGroupWelcomeConfig(9, config)).resolves.toEqual(config);
    expect(mockPut).toHaveBeenCalledWith('/v2/groups/9/welcome', config);
  });

  it('rejects resolved notification mutation failures as translated domain errors', async () => {
    mockPut.mockResolvedValue({
      success: false,
      code: 'HTTP_422',
      error: 'Raw validation copy',
    });

    await expect(updateGroupNotificationPreferences(7, {
      frequency: 'instant',
      email_enabled: true,
      push_enabled: true,
      updated_at: null,
    })).rejects.toMatchObject({
      code: 'VALIDATION_FAILED',
      status: 422,
      messageKey: GROUP_API_MESSAGE_KEYS.validation,
    });
  });

  it('rejects resolved welcome read failures instead of returning defaults as success', async () => {
    mockGet.mockResolvedValue({
      success: false,
      code: 'HTTP_403',
      error: 'Raw permission copy',
    });

    await expect(getGroupWelcomeConfig(9)).rejects.toMatchObject({
      code: 'FORBIDDEN',
      status: 403,
      messageKey: GROUP_API_MESSAGE_KEYS.forbidden,
    });
  });

  it('normalizes thrown cancellation and malformed success payloads', async () => {
    mockGet.mockRejectedValueOnce(Object.assign(new Error('Request aborted'), { name: 'AbortError' }));

    await expect(getGroupNotificationPreferences(1)).rejects.toMatchObject({
      code: 'CANCELLED',
      messageKey: GROUP_API_MESSAGE_KEYS.cancelled,
      retryable: false,
    });

    mockGet.mockResolvedValueOnce({ success: true });

    await expect(getGroupWelcomeConfig(1)).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
      messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse,
      retryable: true,
    });
  });

  it('rejects missing flags, non-ISO timestamps, and acknowledgement-only writes', async () => {
    mockGet
      .mockResolvedValueOnce({
        success: true,
        data: { frequency: 'instant', push_enabled: true, updated_at: null },
      })
      .mockResolvedValueOnce({
        success: true,
        data: {
          frequency: 'instant',
          email_enabled: true,
          push_enabled: true,
          updated_at: '2026-07-11 16:20:30',
        },
      });
    mockPut.mockResolvedValueOnce({
      success: true,
      data: { message: 'Preferences updated' },
    });

    await expect(getGroupNotificationPreferences(1)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
    await expect(getGroupNotificationPreferences(1)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
    await expect(updateGroupNotificationPreferences(1, {
      frequency: 'instant',
      email_enabled: true,
      push_enabled: true,
      updated_at: null,
    })).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });
});
