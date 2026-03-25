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

/** Called when the API returns 401 and refresh has failed — registered by AuthContext */
let onUnauthorizedCallback: (() => void) | null = null;

export function registerUnauthorizedCallback(cb: () => void): void {
  onUnauthorizedCallback = cb;
}

/**
 * Silently refresh the access token using the stored refresh token.
 * Concurrent refresh attempts are collapsed into a single request.
 * Returns the new access token, or null if refresh fails.
 */
let _isRefreshing = false;
let _refreshWaiters: Array<(token: string | null) => void> = [];

async function attemptTokenRefresh(): Promise<string | null> {
  // Collapse concurrent refresh calls into one
  if (_isRefreshing) {
    return new Promise<string | null>((resolve) => {
      _refreshWaiters.push(resolve);
    });
  }

  _isRefreshing = true;
  const notify = (token: string | null) => {
    _refreshWaiters.forEach((r) => r(token));
    _refreshWaiters = [];
    _isRefreshing = false;
  };

  try {
    const [storedRefresh, tenantSlug] = await Promise.all([
      storage.get(STORAGE_KEYS.REFRESH_TOKEN),
      storage.get(STORAGE_KEYS.TENANT_SLUG),
    ]);
    if (!storedRefresh) { notify(null); return null; }

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
    if (tenantSlug) headers['X-Tenant-Slug'] = tenantSlug;

    const res = await fetch(`${API_BASE_URL}/api/auth/refresh-token`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ refresh_token: storedRefresh }),
    });

    if (!res.ok) { notify(null); return null; }

    const data = await res.json() as { access_token?: string; token?: string; refresh_token?: string };
    const newToken = data.access_token ?? data.token ?? null;
    if (!newToken) { notify(null); return null; }

    const saves: Promise<void>[] = [storage.set(STORAGE_KEYS.AUTH_TOKEN, newToken)];
    if (data.refresh_token) saves.push(storage.set(STORAGE_KEYS.REFRESH_TOKEN, data.refresh_token));
    await Promise.all(saves);

    notify(newToken);
    return newToken;
  } catch {
    notify(null);
    return null;
  }
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

  if (tenantSlug && tenantSlug.trim()) {
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

  // Handle 401: try silent token refresh, then retry once
  if (response.status === 401) {
    const newToken = await attemptTokenRefresh();
    if (newToken) {
      // Retry the original request with the refreshed token
      const retryHeaders = { ...headers, Authorization: `Bearer ${newToken}` };
      const retryController = new AbortController();
      const retryTimeoutId = setTimeout(() => retryController.abort(), TIMEOUTS.API_REQUEST);
      let retryRes: Response;
      try {
        retryRes = await fetch(url.toString(), {
          method,
          headers: retryHeaders,
          body: body !== undefined ? JSON.stringify(body) : undefined,
          signal: retryController.signal,
        });
      } catch (retryErr) {
        clearTimeout(retryTimeoutId);
        throw retryErr instanceof Error && retryErr.name === 'AbortError'
          ? new ApiResponseError(0, 'Request timed out. Please check your connection.')
          : new ApiResponseError(0, 'Network error. Please check your connection.');
      }
      clearTimeout(retryTimeoutId);

      // If the retry succeeded (not another 401), process and return it
      if (retryRes.status !== 401) {
        const retryContentType = retryRes.headers.get('content-type') ?? '';
        const retryData: unknown =
          retryContentType.includes('application/json') && retryRes.status !== 204
            ? await retryRes.json()
            : null;
        if (!retryRes.ok) {
          const eb = retryData as { message?: string; errors?: Record<string, string[]> } | null;
          throw new ApiResponseError(retryRes.status, eb?.message ?? `Request failed with status ${retryRes.status}`, eb?.errors);
        }
        return retryData as T;
      }
    }

    // Refresh failed or retry still returned 401 — force logout
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
