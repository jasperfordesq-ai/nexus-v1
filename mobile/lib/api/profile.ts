// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2, API_BASE_URL, DEFAULT_TENANT, STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';
import { type User } from '@/lib/api/auth';

export interface UpdateProfilePayload {
  first_name?: string;
  last_name?: string;
  bio?: string;
  location?: string;
  phone?: string;
}

/**
 * PUT /api/v2/users/me — update the authenticated user's profile.
 * Returns the updated full User.
 */
export function updateProfile(payload: UpdateProfilePayload): Promise<{ data: User }> {
  return api.put<{ data: User }>(`${API_V2}/users/me`, payload);
}

export interface UpdatePasswordPayload {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

/**
 * POST /api/v2/users/me/password — change the authenticated user's password.
 */
export function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
  return api.post<void>(`${API_V2}/users/me/password`, payload);
}

/**
 * POST /api/v2/users/me/avatar — multipart form data upload.
 * Returns the updated avatar URL.
 */
export async function updateAvatar(uri: string): Promise<{ data: { avatar_url: string } }> {
  const formData = new FormData();
  const filename = uri.split('/').pop() ?? 'avatar.jpg';
  const match = /\.(\w+)$/.exec(filename);
  const type = match ? `image/${match[1]}` : 'image/jpeg';
  // FormData accepts this shape in React Native
  formData.append('avatar', { uri, name: filename, type } as unknown as Blob);

  // Use raw fetch — Content-Type must NOT be set so React Native sets multipart boundary
  const token = await storage.get(STORAGE_KEYS.AUTH_TOKEN);
  const tenant = await storage.get(STORAGE_KEYS.TENANT_SLUG) ?? DEFAULT_TENANT;

  const response = await fetch(`${API_BASE_URL}/api/v2/users/me/avatar`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'X-Tenant-Slug': tenant,
    },
    body: formData,
  });

  if (!response.ok) {
    let message = 'Avatar upload failed';
    try {
      const errBody = await response.json() as { message?: string };
      if (errBody?.message) message = errBody.message;
    } catch { /* response may not be JSON */ }
    if (response.status === 413) message = 'Image is too large. Please choose a smaller photo.';
    throw new Error(message);
  }
  return response.json() as Promise<{ data: { avatar_url: string } }>;
}
