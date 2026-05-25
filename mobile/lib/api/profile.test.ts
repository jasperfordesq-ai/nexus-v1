// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn(), put: jest.fn(), patch: jest.fn(), delete: jest.fn() },
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(status: number, message: string) { super(message); this.status = status; this.name = 'ApiResponseError'; }
  },
  registerUnauthorizedCallback: jest.fn(),
}));
jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
  API_BASE_URL: 'https://test.api',
  STORAGE_KEYS: { AUTH_TOKEN: 'auth_token', REFRESH_TOKEN: 'refresh_token', TENANT_SLUG: 'tenant_slug', USER_DATA: 'user_data' },
  TIMEOUTS: { API_REQUEST: 15_000 },
  DEFAULT_TENANT: 'test-tenant',
}));
jest.mock('@/lib/storage', () => ({
  storage: { get: jest.fn() },
}));

import { api, ApiResponseError } from '@/lib/api/client';
import { storage } from '@/lib/storage';
import { updateProfile, updatePassword, updateAvatar } from './profile';
import type { UpdateProfilePayload, UpdatePasswordPayload } from './profile';

const mockUser = {
  id: 1,
  name: 'Alice Smith',
  first_name: 'Alice',
  last_name: 'Smith',
  email: 'alice@example.com',
  avatar_url: null,
  bio: null,
  location: null,
  phone: null,
  balance: 5,
  role: 'member',
  tenant_id: 2,
  created_at: '2025-06-01T00:00:00Z',
};

describe('updateProfile', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends PUT with the payload to /api/v2/users/me', async () => {
    (api.put as jest.Mock).mockResolvedValue({ data: mockUser });
    const payload: UpdateProfilePayload = { first_name: 'Alice', bio: 'Hello' };
    const result = await updateProfile(payload);
    expect(api.put).toHaveBeenCalledWith('/api/v2/users/me', payload);
    expect(result.data.name).toBe('Alice Smith');
  });

  it('sends PUT with partial payload correctly', async () => {
    (api.put as jest.Mock).mockResolvedValue({ data: mockUser });
    await updateProfile({ location: 'Dublin' });
    expect(api.put).toHaveBeenCalledWith('/api/v2/users/me', { location: 'Dublin' });
  });

  it('propagates errors from the API', async () => {
    (api.put as jest.Mock).mockRejectedValue(new Error('Validation error'));
    await expect(updateProfile({ bio: '' })).rejects.toThrow('Validation error');
  });
});

describe('updatePassword', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST with the full password payload to /api/v2/users/me/password', async () => {
    (api.post as jest.Mock).mockResolvedValue(undefined);
    const payload: UpdatePasswordPayload = {
      current_password: 'oldpass',
      new_password: 'newpass1',
      new_password_confirmation: 'newpass1',
    };
    await updatePassword(payload);
    expect(api.post).toHaveBeenCalledWith('/api/v2/users/me/password', payload);
  });

  it('propagates errors from the API', async () => {
    (api.post as jest.Mock).mockRejectedValue(new Error('Wrong password'));
    await expect(
      updatePassword({ current_password: 'x', new_password: 'y', new_password_confirmation: 'y' }),
    ).rejects.toThrow('Wrong password');
  });
});

describe('updateAvatar', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (storage.get as jest.Mock).mockImplementation((key: string) => {
      if (key === 'auth_token') return Promise.resolve('test-token');
      if (key === 'tenant_slug') return Promise.resolve('test-tenant');
      return Promise.resolve(null);
    });
  });

  it('uses fetch to POST multipart form data to /api/v2/users/me/avatar', async () => {
    const mockFetch = jest.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ data: { avatar_url: 'https://cdn.example.com/avatar.jpg' } }),
    });
    global.fetch = mockFetch;

    const result = await updateAvatar('file:///local/path/avatar.jpg');

    expect(mockFetch).toHaveBeenCalledWith(
      'https://test.api/api/v2/users/me/avatar',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ Authorization: 'Bearer test-token' }),
      }),
    );
    expect(result.data.avatar_url).toBe('https://cdn.example.com/avatar.jpg');
  });

  it('throws ApiResponseError with 413 message when image is too large', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: false,
      status: 413,
      json: () => Promise.resolve({}),
    });

    await expect(updateAvatar('file:///large.jpg')).rejects.toMatchObject({
      status: 413,
      message: 'Image is too large. Please choose a smaller photo.',
    });
  });

  it('throws ApiResponseError with server message on non-413 failure', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: false,
      status: 422,
      json: () => Promise.resolve({ message: 'Invalid file type' }),
    });

    await expect(updateAvatar('file:///bad.gif')).rejects.toMatchObject({
      status: 422,
      message: 'Invalid file type',
    });
  });

  it('throws ApiResponseError on AbortError (timeout)', async () => {
    const abortErr = new Error('The operation was aborted');
    abortErr.name = 'AbortError';
    global.fetch = jest.fn().mockRejectedValue(abortErr);

    await expect(updateAvatar('file:///any.jpg')).rejects.toMatchObject({
      message: 'Upload timed out. Please try again.',
    });
  });

  it('throws ApiResponseError on network failure', async () => {
    global.fetch = jest.fn().mockRejectedValue(new Error('Failed to fetch'));

    await expect(updateAvatar('file:///any.jpg')).rejects.toMatchObject({
      message: 'Network error. Please check your connection.',
    });
  });
});
