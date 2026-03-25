// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
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
 * Uses the centralized API client which handles auth token refresh,
 * tenant slug, error formatting, and the upload timeout (60s).
 * Returns the updated avatar URL.
 */
export function updateAvatar(uri: string): Promise<{ data: { avatar_url: string } }> {
  const formData = new FormData();
  const filename = uri.split('/').pop() ?? 'avatar.jpg';
  const match = /\.(\w+)$/.exec(filename);
  const type = match ? `image/${match[1]}` : 'image/jpeg';
  // FormData accepts this shape in React Native
  formData.append('avatar', { uri, name: filename, type } as unknown as Blob);

  return api.upload<{ data: { avatar_url: string } }>(`${API_V2}/users/me/avatar`, formData);
}
