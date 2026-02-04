/**
 * API Client - Fetch wrapper with auth, tenant headers, and 401 refresh
 */

import type { ApiErrorResponse, LegacyErrorResponse } from './types';

// ===========================================
// CONFIGURATION
// ===========================================

const API_BASE = (import.meta.env.VITE_API_BASE || '').replace(/\/+$/, ''); // Strip trailing slashes
const TENANT_ID = import.meta.env.VITE_TENANT_ID || '';
const IS_DEV = import.meta.env.DEV;

// ===========================================
// ERROR HANDLING
// ===========================================

export class ApiClientError extends Error {
  constructor(
    message: string,
    public status: number,
    public code?: string,
    public errors?: Array<{ code: string; message: string; field?: string }>
  ) {
    super(message);
    this.name = 'ApiClientError';
  }
}

// ===========================================
// TOKEN STORAGE
// ===========================================

const TOKEN_KEY = 'nexus_access_token';
const REFRESH_TOKEN_KEY = 'nexus_refresh_token';

export function getAccessToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setAccessToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function getRefreshToken(): string | null {
  return localStorage.getItem(REFRESH_TOKEN_KEY);
}

export function setRefreshToken(token: string): void {
  localStorage.setItem(REFRESH_TOKEN_KEY, token);
}

export function clearTokens(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
}

// ===========================================
// SESSION EXPIRED EVENT
// ===========================================

// Custom event for session expiration (UI can listen)
export const SESSION_EXPIRED_EVENT = 'nexus:session-expired';

function emitSessionExpired(): void {
  window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
}

// ===========================================
// REQUEST HELPERS
// ===========================================

interface RequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  skipAuth?: boolean;
  _isRetry?: boolean; // Internal: prevents infinite retry loops
}

function buildHeaders(options: RequestOptions = {}): Headers {
  const headers = new Headers();

  // Always send JSON accept header
  headers.set('Accept', 'application/json');

  // Set content type for requests with body
  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json');
  }

  // Add auth token if available and not skipped
  if (!options.skipAuth) {
    const token = getAccessToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }
  }

  // In development with explicit tenant ID, add X-Tenant-ID header
  // This is needed for local dev where domain resolution won't work
  if (IS_DEV && TENANT_ID) {
    headers.set('X-Tenant-ID', TENANT_ID);
  }

  return headers;
}

function buildUrl(endpoint: string): string {
  // Ensure endpoint starts with /
  const path = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  // API_BASE already has trailing slashes stripped, so this is safe
  return `${API_BASE}${path}`;
}

async function parseResponse<T>(response: Response): Promise<T> {
  const text = await response.text();

  if (!text) {
    // Empty response (e.g., 204 No Content)
    return {} as T;
  }

  try {
    return JSON.parse(text) as T;
  } catch {
    throw new ApiClientError(
      'Invalid JSON response from server',
      response.status
    );
  }
}

async function handleErrorResponse(response: Response): Promise<never> {
  let errorMessage = `Request failed with status ${response.status}`;
  let errorCode: string | undefined;
  let errors: Array<{ code: string; message: string; field?: string }> | undefined;

  try {
    const text = await response.text();
    if (text) {
      const json = JSON.parse(text);

      // Handle v2 error format
      if ('errors' in json && Array.isArray(json.errors)) {
        const apiError = json as ApiErrorResponse;
        errors = apiError.errors;
        errorMessage = apiError.errors[0]?.message || errorMessage;
        errorCode = apiError.errors[0]?.code;
      }
      // Handle legacy error format
      else if ('error' in json) {
        const legacyError = json as LegacyErrorResponse;
        errorMessage = legacyError.error;
        errorCode = legacyError.code;
      }
    }
  } catch {
    // Couldn't parse error body, use default message
  }

  throw new ApiClientError(errorMessage, response.status, errorCode, errors);
}

// ===========================================
// TOKEN REFRESH
// ===========================================

let isRefreshing = false;
let refreshPromise: Promise<boolean> | null = null;

/**
 * Attempt to refresh the access token using the refresh token.
 * Returns true if successful, false otherwise.
 * Ensures only one refresh happens at a time.
 */
async function attemptTokenRefresh(): Promise<boolean> {
  // If already refreshing, wait for that to complete
  if (isRefreshing && refreshPromise) {
    return refreshPromise;
  }

  const refreshToken = getRefreshToken();
  if (!refreshToken) {
    return false;
  }

  isRefreshing = true;
  refreshPromise = (async () => {
    try {
      const url = buildUrl('/api/auth/refresh-token');
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(IS_DEV && TENANT_ID ? { 'X-Tenant-ID': TENANT_ID } : {}),
        },
        body: JSON.stringify({ refresh_token: refreshToken }),
        credentials: IS_DEV ? 'include' : 'same-origin',
      });

      if (!response.ok) {
        clearTokens();
        emitSessionExpired();
        return false;
      }

      const data = await response.json();
      if (data.success && data.access_token) {
        setAccessToken(data.access_token);
        return true;
      }

      clearTokens();
      emitSessionExpired();
      return false;
    } catch {
      clearTokens();
      emitSessionExpired();
      return false;
    } finally {
      isRefreshing = false;
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

// ===========================================
// CORE REQUEST FUNCTION
// ===========================================

interface FetchWithRetryOptions extends RequestOptions {
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
}

async function fetchWithRetry<T>(
  endpoint: string,
  options: FetchWithRetryOptions
): Promise<T> {
  const url = buildUrl(endpoint);
  const headers = buildHeaders(options);

  const fetchOptions: RequestInit = {
    method: options.method,
    headers,
    credentials: IS_DEV ? 'include' : 'same-origin',
  };

  if (options.body !== undefined) {
    fetchOptions.body = JSON.stringify(options.body);
  }

  const response = await fetch(url, fetchOptions);

  // Handle 401 with token refresh (only if not already a retry and not skipping auth)
  if (response.status === 401 && !options.skipAuth && !options._isRetry) {
    const refreshed = await attemptTokenRefresh();
    if (refreshed) {
      // Retry the original request once with new token
      return fetchWithRetry<T>(endpoint, { ...options, _isRetry: true });
    }
    // Refresh failed - throw session expired error
    throw new ApiClientError(
      'Session expired. Please log in again.',
      401,
      'SESSION_EXPIRED'
    );
  }

  if (!response.ok) {
    await handleErrorResponse(response);
  }

  return parseResponse<T>(response);
}

// ===========================================
// API CLIENT METHODS
// ===========================================

export async function apiGet<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
  return fetchWithRetry<T>(endpoint, { ...options, method: 'GET' });
}

export async function apiPost<T>(endpoint: string, body?: unknown, options: RequestOptions = {}): Promise<T> {
  return fetchWithRetry<T>(endpoint, { ...options, method: 'POST', body });
}

export async function apiPut<T>(endpoint: string, body?: unknown, options: RequestOptions = {}): Promise<T> {
  return fetchWithRetry<T>(endpoint, { ...options, method: 'PUT', body });
}

export async function apiDelete<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
  return fetchWithRetry<T>(endpoint, { ...options, method: 'DELETE' });
}
