// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS API Client
 * Handles all HTTP communication with the PHP backend
 *
 * Features:
 * - Automatic token refresh on 401
 * - Tenant ID header injection
 * - Session expiration events
 * - Request queuing during refresh
 */

// ─────────────────────────────────────────────────────────────────────────────
// Imports
// ─────────────────────────────────────────────────────────────────────────────

import { validateResponse } from '@/lib/api-validation';
import { apiResponseSchema } from '@/lib/api-schemas';
import { captureApiCall } from '@/lib/sentry';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

export const API_BASE = import.meta.env.VITE_API_BASE || '/api';
// Auth tokens use localStorage (standard SPA pattern, not HttpOnly cookies).
// Security depends on strict Content-Security-Policy headers on the server:
//   Content-Security-Policy: default-src 'self'; script-src 'self';
//     connect-src 'self' https://api.project-nexus.ie wss://api.project-nexus.ie;
//     img-src 'self' data: blob: https:;
//   X-Content-Type-Options: nosniff
//   X-Frame-Options: DENY
//   Referrer-Policy: strict-origin-when-cross-origin
// See docs/DEPLOYMENT.md for the full nginx/Cloudflare CSP configuration.
const TOKEN_KEY = 'nexus_access_token';
const REFRESH_TOKEN_KEY = 'nexus_refresh_token';
const TENANT_ID_KEY = 'nexus_tenant_id';
const TENANT_SLUG_KEY = 'nexus_tenant_slug';
const CSRF_TOKEN_KEY = 'nexus_csrf_token';
const TRUSTED_DEVICE_KEY = 'nexus_trusted_device';

// Default tenant ID - only used if nothing is in localStorage
// In production, tenant is detected from subdomain during bootstrap
// This fallback is for development only
const DEFAULT_TENANT_ID = import.meta.env.VITE_DEFAULT_TENANT_ID || null;

// Custom events
export const SESSION_EXPIRED_EVENT = 'nexus:session_expired';
export const API_ERROR_EVENT = 'nexus:api_error';

// Debounce SESSION_EXPIRED dispatches to prevent 401 cascade loops.
// If multiple in-flight requests all get 401 simultaneously, only one
// SESSION_EXPIRED event fires per 5-second window.
let lastSessionExpiredTime = 0;

// Event payload types
export interface ApiErrorEventDetail {
  message: string;
  code: string;
  endpoint: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
  code?: string;
  meta?: PaginationMeta;
}

export interface ApiError {
  message: string;
  code: string;
  status: number;
  fieldErrors?: Record<string, string[]>;
}

export interface PaginationMeta {
  per_page: number;
  has_more: boolean;
  cursor?: string | null;
  next_cursor?: string | null;
  previous_cursor?: string | null;
  current_page?: number;
  total_items?: number;
  total_pages?: number;
  total?: number;
  from?: number;
  to?: number;
  last_page?: number;
  has_next_page?: boolean;
  has_previous_page?: boolean;
  path?: string;
  // Gamification API returns available badge types in meta
  available_types?: string[];
  // Messages API returns conversation details in meta
  conversation?: {
    id: number;
    other_user: {
      id: number;
      name: string;
      first_name?: string;
      last_name?: string;
      avatar_url?: string | null;
      is_online?: boolean;
    };
    unread_count?: number;
    message_count?: number;
  };
}

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  skipAuth?: boolean;
  skipTenant?: boolean;
  skipCsrf?: boolean;
  body?: unknown;
  timeout?: number; // Request timeout in ms (default 30000)
}

// ─────────────────────────────────────────────────────────────────────────────
// Token Management
// ─────────────────────────────────────────────────────────────────────────────

export const tokenManager = {
  getAccessToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  },

  setAccessToken(token: string): void {
    localStorage.setItem(TOKEN_KEY, token);
  },

  getRefreshToken(): string | null {
    return localStorage.getItem(REFRESH_TOKEN_KEY);
  },

  setRefreshToken(token: string): void {
    localStorage.setItem(REFRESH_TOKEN_KEY, token);
  },

  getTenantId(): string | null {
    // First check localStorage (set during login or tenant bootstrap)
    const storedTenantId = localStorage.getItem(TENANT_ID_KEY);
    if (storedTenantId) {
      return storedTenantId;
    }
    // Fall back to environment variable (development only)
    return DEFAULT_TENANT_ID;
  },

  setTenantId(id: string | number): void {
    localStorage.setItem(TENANT_ID_KEY, String(id));
  },

  getTenantSlug(): string | null {
    return localStorage.getItem(TENANT_SLUG_KEY);
  },

  setTenantSlug(slug: string): void {
    localStorage.setItem(TENANT_SLUG_KEY, slug);
  },

  clearTokens(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(REFRESH_TOKEN_KEY);
    // NOTE: trusted device token is intentionally preserved across normal logouts
    // so users don't need to re-verify 2FA on every login from the same device.
    // If the user explicitly requests "forget this device", call clearTrustedDeviceToken() separately.
  },

  // Trusted device token for 2FA "Remember this device" feature
  getTrustedDeviceToken(): string | null {
    return localStorage.getItem(TRUSTED_DEVICE_KEY);
  },

  setTrustedDeviceToken(token: string): void {
    localStorage.setItem(TRUSTED_DEVICE_KEY, token);
  },

  clearTrustedDeviceToken(): void {
    localStorage.removeItem(TRUSTED_DEVICE_KEY);
  },

  clearAll(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(REFRESH_TOKEN_KEY);
    localStorage.removeItem(TENANT_ID_KEY);
    localStorage.removeItem(TENANT_SLUG_KEY);
    // Preserve trusted device token across logouts — it's meant to persist for 30 days
  },

  hasAccessToken(): boolean {
    return !!localStorage.getItem(TOKEN_KEY);
  },

  hasRefreshToken(): boolean {
    return !!localStorage.getItem(REFRESH_TOKEN_KEY);
  },

  // CSRF Token management
  getCsrfToken(): string | null {
    return localStorage.getItem(CSRF_TOKEN_KEY);
  },

  setCsrfToken(token: string): void {
    localStorage.setItem(CSRF_TOKEN_KEY, token);
  },

  clearCsrfToken(): void {
    localStorage.removeItem(CSRF_TOKEN_KEY);
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// API Client Class
// ─────────────────────────────────────────────────────────────────────────────

class ApiClient {
  private baseUrl: string;
  private isRefreshing = false;
  private refreshPromise: Promise<boolean> | null = null;
  private pendingRequests: Array<{
    resolve: (value: boolean) => void;
    reject: (error: Error) => void;
  }> = [];

  // Request deduplication: track in-flight GET requests
  private inflightRequests: Map<string, Promise<ApiResponse<unknown>>> = new Map();

  // Cross-tab token refresh coordination
  private static readonly REFRESH_LOCK_KEY = 'nexus_token_refresh_lock';
  private static readonly REFRESH_LOCK_TTL = 15_000; // 15 seconds max

  constructor(baseUrl: string = API_BASE) {
    this.baseUrl = baseUrl;

    // Listen for cross-tab token updates
    if (typeof window !== 'undefined') {
      window.addEventListener('storage', (e) => {
        if (e.key === TOKEN_KEY && e.newValue && this.isRefreshing) {
          // Another tab successfully refreshed — resolve our pending requests
          this.pendingRequests.forEach(({ resolve }) => resolve(true));
          this.pendingRequests = [];
          this.isRefreshing = false;
          this.refreshPromise = null;
        }
      });
    }
  }

  /**
   * Clear all in-flight request cache (call on tenant switch)
   */
  clearInflightRequests(): void {
    this.inflightRequests.clear();
  }

  /**
   * Dispatch session expired event (debounced — fires at most once per 5 seconds)
   */
  private dispatchSessionExpired(): void {
    const now = Date.now();
    if (now - lastSessionExpiredTime > 5000) {
      lastSessionExpiredTime = now;
      window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
    }
  }

  /**
   * Dispatch API error event for toast notifications
   */
  private dispatchApiError(message: string, code: string, endpoint: string): void {
    window.dispatchEvent(
      new CustomEvent<ApiErrorEventDetail>(API_ERROR_EVENT, {
        detail: { message, code, endpoint },
      })
    );
  }

  /**
   * Generate a cache key for request deduplication
   */
  private getCacheKey(endpoint: string, options: RequestOptions): string {
    // Include tenant id so an in-flight GET dispatched under tenant A
    // cannot be reused to serve a second caller under tenant B after a
    // rapid tenant switch. Without this, identical URLs share a cache
    // entry across tenants and leak the wrong tenant's data.
    const tenantId = tokenManager.getTenantId() ?? '-';
    return `${options.method || 'GET'}:${endpoint}#t=${tenantId}`;
  }

  /**
   * Build headers for API requests
   */
  private buildHeaders(options: RequestOptions = {}): Headers {
    const headers = new Headers(options.headers);

    // Set content type for JSON body
    if (options.body && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    // Accept JSON
    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json');
    }

    // Add auth token unless explicitly skipped
    if (!options.skipAuth) {
      const token = tokenManager.getAccessToken();
      if (token) {
        headers.set('Authorization', `Bearer ${token}`);
      }
    }

    // Add tenant ID unless explicitly skipped
    if (!options.skipTenant) {
      const tenantId = tokenManager.getTenantId();
      if (tenantId) {
        headers.set('X-Tenant-ID', tenantId);
      }
    }

    // Add trusted device token for 2FA "Remember this device" (persists across logouts)
    const trustedDeviceToken = tokenManager.getTrustedDeviceToken();
    if (trustedDeviceToken) {
      headers.set('X-Trusted-Device', trustedDeviceToken);
    }

    // Add CSRF token for state-changing requests (POST, PUT, PATCH, DELETE)
    const method = options.method?.toUpperCase() || 'GET';
    if (!options.skipCsrf && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      const csrfToken = tokenManager.getCsrfToken();
      if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
      }
    }

    return headers;
  }

  /**
   * Attempt to refresh the access token
   */
  private async refreshAccessToken(): Promise<boolean> {
    const refreshToken = tokenManager.getRefreshToken();
    if (!refreshToken) {
      return false;
    }

    try {
      const refreshHeaders: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };
      const tenantId = tokenManager.getTenantId();
      if (tenantId) {
        refreshHeaders['X-Tenant-ID'] = tenantId;
      }

      const response = await fetch(`${this.baseUrl}/auth/refresh-token`, {
        method: 'POST',
        headers: refreshHeaders,
        body: JSON.stringify({ refresh_token: refreshToken }),
        credentials: 'include',
      });

      if (!response.ok) {
        return false;
      }

      const data = await response.json();

      if (data.success && data.access_token) {
        tokenManager.setAccessToken(data.access_token);

        // Update refresh token if provided (token rotation)
        if (data.refresh_token) {
          tokenManager.setRefreshToken(data.refresh_token);
        }

        return true;
      }

      return false;
    } catch {
      return false;
    }
  }

  /**
   * Acquire a cross-tab lock via localStorage to prevent concurrent refreshes.
   * Returns true if this tab acquired the lock.
   */
  private acquireRefreshLock(): boolean {
    const now = Date.now();
    const existing = localStorage.getItem(ApiClient.REFRESH_LOCK_KEY);
    if (existing) {
      const lockTime = parseInt(existing, 10);
      if (now - lockTime < ApiClient.REFRESH_LOCK_TTL) {
        return false; // Another tab holds a valid lock
      }
    }
    localStorage.setItem(ApiClient.REFRESH_LOCK_KEY, String(now));
    return true;
  }

  private releaseRefreshLock(): void {
    localStorage.removeItem(ApiClient.REFRESH_LOCK_KEY);
  }

  /**
   * Handle token refresh with request queuing and cross-tab coordination
   */
  private async handleTokenRefresh(): Promise<boolean> {
    // If this tab is already refreshing, wait for the result
    if (this.isRefreshing && this.refreshPromise) {
      return this.refreshPromise;
    }

    // Check if another tab is currently refreshing
    if (!this.acquireRefreshLock()) {
      // Another tab is refreshing — wait for the storage event to signal completion
      return new Promise<boolean>((resolve, reject) => {
        this.pendingRequests.push({ resolve, reject });
        // Timeout fallback: if the other tab's refresh doesn't complete, retry ourselves
        setTimeout(() => {
          if (this.pendingRequests.length > 0) {
            this.pendingRequests.forEach(({ resolve: r }) => r(false));
            this.pendingRequests = [];
          }
        }, ApiClient.REFRESH_LOCK_TTL);
      });
    }

    this.isRefreshing = true;
    this.refreshPromise = this.refreshAccessToken();

    try {
      const success = await this.refreshPromise;

      // Resolve all pending requests
      this.pendingRequests.forEach(({ resolve }) => resolve(success));
      this.pendingRequests = [];

      if (success) {
        // Clear the in-flight request cache so retries after token refresh
        // get a fresh response with the new token rather than the cached 401.
        this.inflightRequests.clear();
      } else {
        tokenManager.clearTokens();
        this.dispatchSessionExpired();
      }

      return success;
    } finally {
      this.isRefreshing = false;
      this.refreshPromise = null;
      this.releaseRefreshLock();
    }
  }

  /**
   * Make an API request with automatic retry on 401
   */
  async request<T>(
    endpoint: string,
    options: RequestOptions = {},
    retryOnUnauthorized = true
  ): Promise<ApiResponse<T>> {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = this.buildHeaders(options);
    const body = options.body ? JSON.stringify(options.body) : undefined;
    const method = options.method?.toUpperCase() || 'GET';

    // Track request timing for Sentry
    const startTime = performance.now();

    // Request timeout (default 30s, configurable per-request)
    const timeout = options.timeout ?? 30000;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);

    // Combine timeout signal with caller-provided signal (if any) so that
    // either aborting the timeout OR the caller's AbortController cancels the fetch.
    const callerSignal = options.signal as AbortSignal | undefined;
    let combinedSignal: AbortSignal = controller.signal;
    if (callerSignal) {
      // AbortSignal.any() is supported in Chrome 116+, Safari 17.4+, Firefox 124+.
      // Fall back to linking signals manually for older browsers.
      if ('any' in AbortSignal) {
        combinedSignal = AbortSignal.any([controller.signal, callerSignal]);
      } else {
        // Legacy fallback: when the caller aborts, abort our controller too
        if (callerSignal.aborted) {
          controller.abort(callerSignal.reason);
        } else {
          callerSignal.addEventListener('abort', () => controller.abort(callerSignal.reason), { once: true });
        }
      }
    }

    try {
      const response = await fetch(url, {
        ...options,
        headers,
        body,
        credentials: 'include',
        signal: combinedSignal,
      });
      clearTimeout(timeoutId);

      // Capture API call in Sentry (success or error)
      const duration = performance.now() - startTime;
      captureApiCall(method, endpoint, response.status, duration);

      // Handle 401 Unauthorized with token refresh
      if (response.status === 401 && retryOnUnauthorized && !options.skipAuth) {
        const refreshed = await this.handleTokenRefresh();

        if (refreshed) {
          // Retry the request with new token
          return this.request<T>(endpoint, options, false);
        }

        return {
          success: false,
          error: 'Session expired. Please log in again.',
          code: 'SESSION_EXPIRED',
        };
      }

      // Handle 503 Service Unavailable (maintenance mode — body may be HTML, not JSON)
      if (response.status === 503) {
        return {
          success: false,
          error: 'Service temporarily unavailable',
          code: 'SERVICE_UNAVAILABLE',
        };
      }

      // Parse JSON response
      let data;
      try {
        data = await response.json();
      } catch {
        if (response.ok) {
          return { success: true, data: undefined as T };
        }
        return {
          success: false,
          error: 'Invalid response from server',
          code: 'PARSE_ERROR',
        };
      }

      // Handle successful response
      if (response.ok) {
        // Handle null/empty body (e.g. 204 No Content)
        if (data === null || data === undefined) {
          return { success: true, data: undefined as T };
        }

        const result: ApiResponse<T> = {
          success: true,
          data: typeof data === 'object' && data !== null && 'data' in data ? data.data : data,
          message: data.message,
          meta: data.meta,
        };

        // Dev-only: validate the response envelope structure
        validateResponse(apiResponseSchema, result, `${options.method || 'GET'} ${endpoint}`);

        return result;
      }

      // Handle error response (v2 API uses {errors: [{code, message}]}, v1 uses {error, code})
      const firstError = Array.isArray(data.errors) && data.errors.length > 0 ? data.errors[0] : null;
      const errorMessage = data.error ?? firstError?.message ?? data.message ?? 'Request failed';
      const errorCode = data.code ?? firstError?.code ?? `HTTP_${response.status}`;

      // Dispatch global error for server errors (5xx) so useApiErrorHandler can show toasts.
      // Client errors (4xx) are typically handled by the calling component.
      if (response.status >= 500) {
        this.dispatchApiError(errorMessage, errorCode, endpoint);
      }

      return {
        success: false,
        error: errorMessage,
        code: errorCode,
      };
    } catch (error) {
      clearTimeout(timeoutId);

      // Handle timeout abort
      if (error instanceof DOMException && error.name === 'AbortError') {
        const duration = performance.now() - startTime;
        captureApiCall(method, endpoint, 408, duration); // 408 Request Timeout
        const message = 'Request timed out. Please try again.';
        this.dispatchApiError(message, 'TIMEOUT', endpoint);
        return { success: false, error: message, code: 'TIMEOUT' };
      }

      // Network or other error - sanitize for production
      const rawMessage = error instanceof Error ? error.message : 'Network error';
      const message = import.meta.env.PROD
        ? 'Unable to connect. Please check your internet connection and try again.'
        : rawMessage;
      this.dispatchApiError(message, 'NETWORK_ERROR', endpoint);
      return {
        success: false,
        error: message,
        code: 'NETWORK_ERROR',
      };
    }
  }

  /**
   * GET request with deduplication
   * Concurrent identical GET requests will return the same promise
   */
  async get<T>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>> {
    const cacheKey = this.getCacheKey(endpoint, { ...options, method: 'GET' });

    // Check for in-flight request
    const inflight = this.inflightRequests.get(cacheKey);
    if (inflight) {
      return inflight as Promise<ApiResponse<T>>;
    }

    // Create new request
    const promise = this.request<T>(endpoint, { ...options, method: 'GET' });

    // Store in-flight request
    this.inflightRequests.set(cacheKey, promise as Promise<ApiResponse<unknown>>);

    // Clean up after completion
    promise.finally(() => {
      this.inflightRequests.delete(cacheKey);
    });

    return promise;
  }

  /**
   * POST request
   */
  async post<T>(
    endpoint: string,
    body?: unknown,
    options?: RequestOptions
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'POST', body });
  }

  /**
   * PUT request
   */
  async put<T>(
    endpoint: string,
    body?: unknown,
    options?: RequestOptions
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'PUT', body });
  }

  /**
   * PATCH request
   */
  async patch<T>(
    endpoint: string,
    body?: unknown,
    options?: RequestOptions
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'PATCH', body });
  }

  /**
   * DELETE request
   */
  async delete<T>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'DELETE' });
  }

  /**
   * Download a file (blob response) with full auth/tenant handling.
   * Returns the Blob on success, throws on failure.
   * Handles 401 with automatic token refresh + retry.
   */
  async download(
    endpoint: string,
    options: RequestOptions & { filename?: string } = {}
  ): Promise<Blob> {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = this.buildHeaders(options);
    // Override Accept to allow any content type from the server
    headers.delete('Accept');

    const response = await fetch(url, {
      ...options,
      method: options.method?.toUpperCase() || 'GET',
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'include',
    });

    // Handle 401 with token refresh
    if (response.status === 401 && !options.skipAuth) {
      const refreshed = await this.handleTokenRefresh();
      if (refreshed) {
        return this.download(endpoint, options);
      }
      this.dispatchSessionExpired();
      throw new Error('Session expired. Please log in again.');
    }

    if (!response.ok) {
      throw new Error(`Download failed (HTTP ${response.status})`);
    }

    const blob = await response.blob();

    // Auto-trigger download if filename is provided
    if (options.filename) {
      // Try to get filename from Content-Disposition header first
      const disposition = response.headers.get('Content-Disposition');
      let resolvedFilename = options.filename;
      if (disposition) {
        const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (match?.[1]) {
          resolvedFilename = match[1].replace(/['"]/g, '');
        }
      }
      const blobUrl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = blobUrl;
      a.download = resolvedFilename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(blobUrl);
    }

    return blob;
  }

  /**
   * Upload file(s) - multipart form data
   */
  async upload<T>(
    endpoint: string,
    files: File | File[] | FormData,
    fieldName = 'file',
    options?: RequestOptions
  ): Promise<ApiResponse<T>> {
    let formData: FormData;

    if (files instanceof FormData) {
      formData = files;
    } else {
      formData = new FormData();
      const fileArray = Array.isArray(files) ? files : [files];
      fileArray.forEach((file, index) => {
        const name = fileArray.length === 1 ? fieldName : `${fieldName}[${index}]`;
        formData.append(name, file);
      });
    }

    // H9/L10: Build headers WITHOUT Content-Type — browser must set it with the multipart boundary.
    // Explicitly delete it to guard against inherited values from options.headers.
    const headers = new Headers(options?.headers);
    headers.delete('Content-Type'); // L10: force-remove in case it was inherited
    headers.set('Accept', 'application/json');

    if (!options?.skipAuth) {
      const token = tokenManager.getAccessToken();
      if (token) {
        headers.set('Authorization', `Bearer ${token}`);
      }
    }

    if (!options?.skipTenant) {
      const tenantId = tokenManager.getTenantId();
      if (tenantId) {
        headers.set('X-Tenant-ID', tenantId);
      }
    }

    // Add CSRF token for file uploads (state-changing request)
    const csrfToken = tokenManager.getCsrfToken();
    if (csrfToken) {
      headers.set('X-CSRF-Token', csrfToken);
    }

    // Uploads should not be subject to the 30s default timeout — use 2 minutes
    const uploadController = new AbortController();
    const uploadTimeoutId = setTimeout(() => uploadController.abort(), 120000);

    // H9: Combine upload timeout signal with optional caller AbortSignal
    const callerSignal = options?.signal as AbortSignal | undefined;
    let uploadSignal: AbortSignal = uploadController.signal;
    if (callerSignal) {
      if ('any' in AbortSignal) {
        uploadSignal = AbortSignal.any([uploadController.signal, callerSignal]);
      } else {
        if (callerSignal.aborted) {
          uploadController.abort(callerSignal.reason);
        } else {
          callerSignal.addEventListener('abort', () => uploadController.abort(callerSignal.reason), { once: true });
        }
      }
    }

    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        method: 'POST',
        headers,
        body: formData,
        credentials: 'include',
        signal: uploadSignal,
      });
      clearTimeout(uploadTimeoutId);

      if (response.status === 401) {
        const refreshed = await this.handleTokenRefresh();
        if (refreshed) {
          return this.upload<T>(endpoint, files, fieldName, options);
        }
        return {
          success: false,
          error: 'Session expired',
          code: 'SESSION_EXPIRED',
        };
      }

      const data = await response.json();

      if (response.ok) {
        if (data === null || data === undefined) {
          return { success: true, data: undefined as T };
        }
        return { success: true, data: typeof data === 'object' && 'data' in data ? data.data : data, meta: data.meta };
      }

      // Handle error response (v2 API uses {errors: [{code, message}]}, v1 uses {error, code})
      const firstError = Array.isArray(data.errors) && data.errors.length > 0 ? data.errors[0] : null;
      return {
        success: false,
        error: data.error ?? firstError?.message ?? data.message ?? 'Upload failed',
        code: data.code ?? firstError?.code ?? 'UPLOAD_ERROR',
      };
    } catch (error) {
      clearTimeout(uploadTimeoutId);
      const rawMessage = error instanceof Error ? error.message : 'Upload failed';
      const message = import.meta.env.PROD
        ? 'Upload failed. Please try again.'
        : rawMessage;
      return { success: false, error: message, code: 'NETWORK_ERROR' };
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Singleton Instance
// ─────────────────────────────────────────────────────────────────────────────

export const api = new ApiClient();

// ─────────────────────────────────────────────────────────────────────────────
// Utility Functions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build query string from params object
 */
export function buildQueryString(params: Record<string, string | number | boolean | undefined | null>): string {
  const searchParams = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, String(value));
    }
  });

  const query = searchParams.toString();
  return query ? `?${query}` : '';
}

/**
 * Check backend health
 */
export async function checkBackendHealth(): Promise<{
  healthy: boolean;
  database: boolean;
  phpVersion?: string;
}> {
  try {
    const response = await fetch('/health.php');
    const data = await response.json();
    return {
      healthy: data.status === 'healthy',
      database: data.database === 'connected',
      phpVersion: data.php_version,
    };
  } catch {
    return { healthy: false, database: false };
  }
}

/**
 * Fetch and store CSRF token from the backend
 * Should be called once on app initialization
 */
export async function fetchCsrfToken(): Promise<string | null> {
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const response = await api.get<{ csrf_token: string }>('/csrf-token', { skipCsrf: true });
      // Backend returns { data: { csrf_token: "..." } }, unwrapped to { csrf_token: "..." }
      const token = response.data?.csrf_token;
      if (response.success && token) {
        tokenManager.setCsrfToken(token);
        return token;
      }
      return null;
    } catch {
      if (attempt < 2) {
        await new Promise((r) => setTimeout(r, 1000 * (attempt + 1)));
        continue;
      }
      return null;
    }
  }
  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Public Menu API
// ─────────────────────────────────────────────────────────────────────────────

import type { ApiMenu, MenusByLocation } from '@/types/menu';

export const menuApi = {
  /** Get all menus for the current tenant, optionally filtered by location */
  getMenus: (location?: string) => {
    const params = location ? `?location=${encodeURIComponent(location)}` : '';
    return api.get<ApiMenu[] | MenusByLocation>(`/menus${params}`);
  },
  /** Get mobile-optimized menu */
  getMobileMenu: () => api.get<ApiMenu[]>('/menus/mobile'),
};

export default api;
