// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

/**
 * User shape as returned by /api/v2/users/me (own profile, private fields included).
 * The login endpoint returns a slimmer subset — see LoginUser below.
 */
export interface User {
  id: number;
  /** Computed full name (first_name + last_name) */
  name: string;
  first_name: string | null;
  last_name: string | null;
  email: string;
  avatar_url: string | null;
  bio: string | null;
  location: string | null;
  phone: string | null;
  /** Time credit balance in hours */
  balance: number;
  role: string;
  tenant_id: number;
  created_at: string | null;
}

/**
 * Slim user object embedded in the login/register response.
 * Does NOT include balance — fetch /users/me for the full profile.
 */
export interface LoginUser {
  id: number;
  first_name: string | null;
  last_name: string | null;
  email: string;
  avatar_url: string | null;
  tenant_id: number;
  role: string;
  is_admin: boolean;
  onboarding_completed: boolean;
}

/** Response from POST /api/auth/login and POST /api/v2/auth/register */
export interface AuthResponse {
  success: boolean;
  /** Primary token field (new name) */
  access_token: string;
  /** Legacy alias for access_token — always the same value */
  token?: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
  user: LoginUser;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

/** POST /api/auth/login — token-based auth (Bearer, mobile-safe, 1-year token) */
export function login(payload: LoginPayload): Promise<AuthResponse> {
  return api.post<AuthResponse>('/api/auth/login', {
    ...payload,
    // Tell the server this is a mobile client so it issues a long-lived token
    platform: 'mobile',
  });
}

/** POST /api/v2/auth/register — V2 registration (returns token immediately) */
export function register(payload: RegisterPayload): Promise<AuthResponse> {
  return api.post<AuthResponse>(`${API_V2}/auth/register`, payload);
}

/** POST /api/auth/logout */
export function logout(): Promise<void> {
  return api.post<void>('/api/auth/logout');
}

/** GET /api/v2/users/me — full profile with balance, validates token */
export function getMe(): Promise<{ data: User }> {
  return api.get<{ data: User }>(`${API_V2}/users/me`);
}

/** POST /api/auth/refresh-token */
export function refreshToken(token: string): Promise<AuthResponse> {
  return api.post<AuthResponse>('/api/auth/refresh-token', { refresh_token: token });
}

/**
 * Extract the bearer token string from an AuthResponse.
 * Throws if no token is present — callers must not proceed with an empty token.
 */
export function extractToken(response: AuthResponse): string {
  const token = response.access_token ?? response.token;
  if (!token) {
    throw new Error('Auth response did not contain a token');
  }
  return token;
}

/** Helper: build a display name from first/last name fields */
export function buildDisplayName(user: LoginUser | User): string {
  const parts = [user.first_name, user.last_name].filter(Boolean);
  return parts.length > 0 ? parts.join(' ') : 'Member';
}
