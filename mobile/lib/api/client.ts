// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { API_BASE_URL, STORAGE_KEYS, TIMEOUTS } from '@/lib/constants';
import { storage } from '@/lib/storage';

/**
 * Generic API client for the Project NEXUS PHP backend.
 *
 * Every request automatically:
 *  - Prepends API_BASE_URL
 *  - Attaches Authorization: Bearer <token> when a token is stored
 *  - Attaches X-Tenant-Slug header for multi-tenant routing
 *  - Applies a configurable request timeout
 *  - Handles 401 responses by clearing credentials (logout is signalled
 *    via the onUnauthorized callback, which AuthContext sets at startup)
 */

type RequestMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface ApiError {
  status: number;
  message: string;
  errors?: Record<string, string[]>;
}

export class ApiResponseError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiResponseError';
  }
}

/** Called when the API returns 401 — registered by AuthContext */
let onUnauthorizedCallback: (() => void) | null = null;

export function registerUnauthorizedCallback(cb: () => void): void {
  onUnauthorizedCallback = cb;
}

async function request<T>(
  method: RequestMethod,
  endpoint: string,
  body?: unknown,
  params?: Record<string, string>,
): Promise<T> {
  // Build URL with optional query params
  const url = new URL(`${API_BASE_URL}${endpoint}`);
  if (params) {
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  }

  // Gather auth token and active tenant
  const [token, tenantSlug] = await Promise.all([
    storage.get(STORAGE_KEYS.AUTH_TOKEN),
    storage.get(STORAGE_KEYS.TENANT_SLUG),
  ]);

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  if (tenantSlug) {
    // The PHP API resolves the tenant from this header on non-subdomain routes
    headers['X-Tenant-Slug'] = tenantSlug;
  }

  // Abort controller for timeout support
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), TIMEOUTS.API_REQUEST);

  let response: Response;
  try {
    response = await fetch(url.toString(), {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (err) {
    clearTimeout(timeoutId);
    if (err instanceof Error && err.name === 'AbortError') {
      throw new ApiResponseError(0, 'Request timed out. Please check your connection.');
    }
    throw new ApiResponseError(0, 'Network error. Please check your connection.');
  }

  clearTimeout(timeoutId);

  // Handle 401: clear credentials and notify AuthContext
  if (response.status === 401) {
    await Promise.all([
      storage.remove(STORAGE_KEYS.AUTH_TOKEN),
      storage.remove(STORAGE_KEYS.REFRESH_TOKEN),
      storage.remove(STORAGE_KEYS.USER_DATA),
    ]);
    onUnauthorizedCallback?.();
    throw new ApiResponseError(401, 'Your session has expired. Please log in again.');
  }

  // Parse response body (some endpoints return no body on 204)
  let data: unknown;
  const contentType = response.headers.get('content-type') ?? '';
  if (contentType.includes('application/json') && response.status !== 204) {
    data = await response.json();
  } else {
    data = null;
  }

  if (!response.ok) {
    const errBody = data as { message?: string; errors?: Record<string, string[]> } | null;
    throw new ApiResponseError(
      response.status,
      errBody?.message ?? `Request failed with status ${response.status}`,
      errBody?.errors,
    );
  }

  return data as T;
}

/**
 * Typed API client — thin wrapper around fetch with auth & tenant headers.
 * Usage:
 *   const users = await api.get<User[]>('/api/v2/users');
 *   const result = await api.post<CreateResult>('/api/v2/exchanges', payload);
 */
export const api = {
  get<T>(endpoint: string, params?: Record<string, string>): Promise<T> {
    return request<T>('GET', endpoint, undefined, params);
  },

  post<T>(endpoint: string, body?: unknown): Promise<T> {
    return request<T>('POST', endpoint, body);
  },

  put<T>(endpoint: string, body?: unknown): Promise<T> {
    return request<T>('PUT', endpoint, body);
  },

  patch<T>(endpoint: string, body?: unknown): Promise<T> {
    return request<T>('PATCH', endpoint, body);
  },

  delete<T>(endpoint: string): Promise<T> {
    return request<T>('DELETE', endpoint);
  },
};
