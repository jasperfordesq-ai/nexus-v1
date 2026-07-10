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
import { recordApiDiagnostic } from '@/lib/supportDiagnostics';
import { safeLocalStorageSet } from '@/lib/safeStorage';
import { queueSentryApiCall, queueSentryBreadcrumb, queueSentryMessage } from '@/lib/telemetryQueue';

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
export const SESSION_EXPIRING_EVENT = 'nexus:session_expiring';
export const API_ERROR_EVENT = 'nexus:api_error';

function captureTelemetryApiCall(
  method: string,
  endpoint: string,
  status: number,
  duration: number,
): void {
  queueSentryApiCall(method, endpoint, status, duration);
}

function addTelemetryBreadcrumb(
  message: string,
  category: string,
  data: Record<string, unknown>,
  level: 'info' | 'warning' | 'error' = 'info',
): void {
  queueSentryBreadcrumb(message, category, data, level);
}

function captureTelemetryMessage(
  message: string,
  level: 'fatal' | 'error' | 'warning' | 'log' | 'info' | 'debug',
  context?: Record<string, unknown>,
): void {
  queueSentryMessage(message, level, context);
}

// ─────────────────────────────────────────────────────────────────────────────
// Stale-client gate
// ─────────────────────────────────────────────────────────────────────────────
// Every API response carries `X-Build: <sha>` (set by SecurityHeaders middleware
// on the server). If that doesn't match this client's __BUILD_COMMIT__, the
// client is running older code than the server. We give the silent recovery
// paths (NetworkFirst HTML refetch on next nav, SW skipWaiting +
// clientsClaim + controllerchange auto-reload from main.tsx) a 10-minute
// grace window to bring the client into sync. If the mismatch persists past
// the window, we force-redirect to /api/sw-reset — that URL bypasses every
// SW we've ever shipped (matches the long-standing /^\/api\// denylist) and
// the nginx response unregisters the SW + clears CacheStorage.
//
// No UI is fired during the grace window. The user-facing update banner has
// been removed; the wait is silent because the natural propagation path is
// expected to resolve >99% of mismatches without intervention.

const BUILD_HEADER = 'x-build';
const BUILD_MISMATCH_KEY = 'nexus_build_mismatch_since';
const BUILD_MISMATCH_GRACE_MS = 10 * 60 * 1000;
const RECOVERY_URL = '/api/sw-reset';

let staleRedirectFired = false;

function checkStaleBuild(response: Response): void {
  if (staleRedirectFired) return;

  const serverBuild = response.headers.get(BUILD_HEADER);
  if (!serverBuild) return; // server pre-dates the gate; treat as match

  // __BUILD_COMMIT__ is injected as a 12-char short SHA. Server may send
  // either short or full — compare on the prefix to stay tolerant.
  const clientBuild = __BUILD_COMMIT__;
  if (!clientBuild || clientBuild === 'dev') return;

  const matches =
    serverBuild === clientBuild ||
    serverBuild.startsWith(clientBuild) ||
    clientBuild.startsWith(serverBuild);

  if (matches) {
    // Healed (we caught up — likely after a soft-update reload). Clear tracker.
    try { localStorage.removeItem(BUILD_MISMATCH_KEY); } catch { /* non-blocking */ }
    return;
  }

  // First detection of this mismatch in the window — record the timestamp so
  // we can decide later whether the soft-update path has had enough chances.
  let firstSeen = 0;
  try {
    const raw = localStorage.getItem(BUILD_MISMATCH_KEY);
    if (raw) firstSeen = parseInt(raw, 10) || 0;
  } catch { /* non-blocking */ }

  const now = Date.now();
  if (firstSeen === 0) {
    // Silently start the grace timer. Within the next 10 minutes either
    // (a) the new SW activates + controllerchange auto-reloads,
    // (b) a user-initiated navigation hits NetworkFirst and pulls in the
    //     fresh shell, or (c) the timer below fires the hard recovery.
    safeLocalStorageSet(BUILD_MISMATCH_KEY, String(now));
    addTelemetryBreadcrumb(
      'Stale client detected',
      'pwa',
      { client_build: clientBuild, server_build: serverBuild },
      'warning',
    );
    return;
  }

  if (now - firstSeen >= BUILD_MISMATCH_GRACE_MS) {
    // Soft path has had 10 minutes and the client is still running old code.
    // Force-recover: nginx /api/sw-reset returns Clear-Site-Data + an inline
    // script that unregisters the SW and wipes CacheStorage, then redirects
    // to / where the browser fetches the fresh shell from network.
    staleRedirectFired = true;
    try { localStorage.removeItem(BUILD_MISMATCH_KEY); } catch { /* non-blocking */ }
    // Capture as a message (not just a breadcrumb) so we can count
    // force-recoveries in Sentry's discover/issues UI. This event is rare
    // by design — every occurrence means the soft-update path failed for
    // 10+ minutes, which is worth investigating.
    captureTelemetryMessage(
      'Stale client force-recovered via /api/sw-reset',
      'warning',
      {
        client_build: clientBuild,
        server_build: serverBuild,
        mismatch_age_ms: now - firstSeen,
      },
    );
    try {
      window.location.replace(RECOVERY_URL);
    } catch {
      window.location.href = RECOVERY_URL;
    }
  }
}

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
  errors?: ApiErrorDetail[];
  meta?: PaginationMeta;
}

export interface ApiErrorDetail {
  code?: string;
  message?: string;
  field?: string;
  [key: string]: unknown;
}

export interface ApiError {
  message: string;
  code: string;
  status: number;
  fieldErrors?: Record<string, string[]>;
}

type TokenRefreshOutcome = 'refreshed' | 'invalid' | 'transient';

interface PendingRefreshWaiter {
  accessTokenAtWait: string | null;
  resolve: (outcome: TokenRefreshOutcome) => void;
  timeoutId: ReturnType<typeof setTimeout>;
}

function isAbortError(error: unknown): boolean {
  return typeof error === 'object' && error !== null && 'name' in error && error.name === 'AbortError';
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
  pagination_scope?: 'internal_partners' | 'external_partner' | string;
  cursor_scope?: 'internal_partners' | 'external_partner' | string;
  load_more_scope?: 'internal_partners' | 'external_partner' | 'none' | string;
  external_pagination_scope?: 'first_page_enrichment' | 'single_partner_result_set' | 'none' | string;
  external_results_paginated?: boolean;
  external_results_included?: boolean;
  source_counts?: {
    internal_returned?: number;
    internal_total_items?: number;
    external_returned?: number;
    returned_total?: number;
  };
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
    // Server-authoritative safeguarding preflight state for a 1:1 conversation.
    // Present with restricted=true when direct contact with other_user is gated;
    // null/absent when contact is permitted. Rendering it never alerts staff.
    safeguarding?: {
      restricted: boolean;
      code: string;
      title?: string | null;
      message?: string | null;
      detail?: string | null;
      action_label?: string | null;
      required_vetting_types?: string[];
      required_vetting_labels?: string[];
      can_request_coordinator?: boolean;
    } | null;
  };
}

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  skipAuth?: boolean;
  skipTenant?: boolean;
  skipCsrf?: boolean;
  body?: unknown;
  timeout?: number; // Request timeout in ms (default 30000)
  responseType?: 'json' | 'blob' | 'text';
  /**
   * Optional in-memory GET cache TTL. When omitted, only known public static
   * endpoints such as tenant bootstrap and category metadata get a short TTL.
   */
  cacheTtlMs?: number;
  /** When provided, the upload is sent via XHR so byte progress (0-100) is reported. */
  onUploadProgress?: (percent: number) => void;
}

// localStorage writes go through `safeLocalStorageSet` (imported from
// lib/safeStorage) so a full store triggers eviction instead of throwing.

// ─────────────────────────────────────────────────────────────────────────────
// Token Management
// ─────────────────────────────────────────────────────────────────────────────

export const tokenManager = {
  getAccessToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  },

  setAccessToken(token: string): void {
    safeLocalStorageSet(TOKEN_KEY, token);
  },

  getRefreshToken(): string | null {
    return localStorage.getItem(REFRESH_TOKEN_KEY);
  },

  setRefreshToken(token: string): void {
    safeLocalStorageSet(REFRESH_TOKEN_KEY, token);
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
    safeLocalStorageSet(TENANT_ID_KEY, String(id));
  },

  getTenantSlug(): string | null {
    return localStorage.getItem(TENANT_SLUG_KEY);
  },

  setTenantSlug(slug: string): void {
    safeLocalStorageSet(TENANT_SLUG_KEY, slug);
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
    safeLocalStorageSet(TRUSTED_DEVICE_KEY, token);
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
    safeLocalStorageSet(CSRF_TOKEN_KEY, token);
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
  private refreshPromise: Promise<TokenRefreshOutcome> | null = null;
  private pendingRefreshWaiters = new Set<PendingRefreshWaiter>();

  // Request deduplication: track in-flight GET requests
  private inflightRequests: Map<string, Promise<ApiResponse<unknown>>> = new Map();
  private responseCache: Map<string, { expiresAt: number; response: ApiResponse<unknown> }> = new Map();

  // Cross-tab token refresh coordination
  private static readonly REFRESH_LOCK_KEY = 'nexus_token_refresh_lock';
  private static readonly REFRESH_LOCK_TTL = 15_000; // 15 seconds max

  constructor(baseUrl: string = API_BASE) {
    this.baseUrl = baseUrl;

    // Listen for cross-tab token updates
    if (typeof window !== 'undefined') {
      window.addEventListener('storage', (e) => {
        if (e.key === TOKEN_KEY && this.pendingRefreshWaiters.size > 0) {
          // A value means the other tab refreshed successfully. Removal means
          // it proved that the shared session is invalid.
          if (e.newValue) {
            this.inflightRequests.clear();
            this.resolvePendingRefreshWaiters('refreshed');
          } else {
            this.resolvePendingRefreshWaiters('invalid');
          }
          return;
        }

        if (
          e.key === ApiClient.REFRESH_LOCK_KEY &&
          e.newValue === null &&
          this.pendingRefreshWaiters.size > 0
        ) {
          // Refresh can finish without changing the token when its endpoint is
          // temporarily unavailable. Compare token generations so lock release
          // never masquerades as a successful refresh.
          const currentToken = tokenManager.getAccessToken();
          for (const waiter of [...this.pendingRefreshWaiters]) {
            const outcome = currentToken && currentToken !== waiter.accessTokenAtWait
              ? 'refreshed'
              : 'transient';
            this.resolvePendingRefreshWaiter(waiter, outcome);
          }
        }
      });
    }
  }

  private resolvePendingRefreshWaiter(
    waiter: PendingRefreshWaiter,
    outcome: TokenRefreshOutcome,
  ): void {
    if (!this.pendingRefreshWaiters.delete(waiter)) return;
    clearTimeout(waiter.timeoutId);
    waiter.resolve(outcome);
  }

  private resolvePendingRefreshWaiters(outcome: TokenRefreshOutcome): void {
    for (const waiter of [...this.pendingRefreshWaiters]) {
      this.resolvePendingRefreshWaiter(waiter, outcome);
    }
  }

  private waitForExternalTokenRefresh(accessTokenAtWait: string | null): Promise<TokenRefreshOutcome> {
    return new Promise<TokenRefreshOutcome>((resolve) => {
      const waiter = {} as PendingRefreshWaiter;
      waiter.accessTokenAtWait = accessTokenAtWait;
      waiter.resolve = resolve;
      waiter.timeoutId = setTimeout(() => {
        this.resolvePendingRefreshWaiter(waiter, 'transient');
      }, ApiClient.REFRESH_LOCK_TTL);
      this.pendingRefreshWaiters.add(waiter);

      // Close the gap between observing a held lock and registering this
      // waiter. The other tab may already have written its token and released
      // the lock before our storage listener was ready.
      const currentToken = tokenManager.getAccessToken();
      if (currentToken !== accessTokenAtWait) {
        this.resolvePendingRefreshWaiter(waiter, currentToken ? 'refreshed' : 'invalid');
      } else if (localStorage.getItem(ApiClient.REFRESH_LOCK_KEY) === null) {
        this.resolvePendingRefreshWaiter(waiter, 'transient');
      }
    });
  }

  private expireSession(): void {
    tokenManager.clearTokens();
    this.dispatchSessionExpired();
  }

  private sessionExpiredResponse<T>(): ApiResponse<T> {
    return {
      success: false,
      error: 'Session expired. Please log in again.',
      code: 'SESSION_EXPIRED',
    };
  }

  private refreshUnavailableResponse<T>(): ApiResponse<T> {
    return { success: false, code: 'AUTH_REFRESH_UNAVAILABLE' };
  }

  /**
   * Clear all in-flight request cache (call on tenant switch)
   */
  clearInflightRequests(): void {
    this.inflightRequests.clear();
    this.responseCache.clear();
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

  private getResponseCacheTtl(endpoint: string, options: RequestOptions): number {
    if (options.cacheTtlMs !== undefined) {
      return Math.max(0, options.cacheTtlMs);
    }

    const path = endpoint.split('?')[0] ?? endpoint;
    if (
      path === '/v2/tenant/bootstrap' ||
      path === '/v2/tenants' ||
      path === '/v2/platform/stats' ||
      path === '/v2/categories' ||
      path === '/v2/marketplace/categories' ||
      /^\/v2\/marketplace\/categories\/\d+\/template$/.test(path)
    ) {
      return 5 * 60 * 1000;
    }

    return 0;
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
  private async refreshAccessToken(): Promise<TokenRefreshOutcome> {
    const refreshToken = tokenManager.getRefreshToken();
    if (!refreshToken) {
      return 'invalid';
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
        // Credential and account-policy rejections are authoritative. Server,
        // rate-limit, and transport failures are temporary and must not delete
        // an otherwise valid refresh token.
        if (response.status >= 400 && response.status < 500 && response.status !== 408 && response.status !== 429) {
          return 'invalid';
        }
        return 'transient';
      }

      const data = await response.json();

      if (data.success && data.access_token) {
        tokenManager.setAccessToken(data.access_token);

        // Update refresh token if provided (token rotation)
        if (data.refresh_token) {
          tokenManager.setRefreshToken(data.refresh_token);
        }

        return 'refreshed';
      }

      return 'invalid';
    } catch {
      return 'transient';
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
    safeLocalStorageSet(ApiClient.REFRESH_LOCK_KEY, String(now));
    return true;
  }

  private releaseRefreshLock(): void {
    localStorage.removeItem(ApiClient.REFRESH_LOCK_KEY);
  }

  /**
   * Handle token refresh with request queuing and cross-tab coordination
   */
  private async handleTokenRefresh(): Promise<TokenRefreshOutcome> {
    // If this tab is already refreshing, wait for the result
    if (this.isRefreshing && this.refreshPromise) {
      return this.refreshPromise;
    }

    // Capture the token generation before checking the lock. If the other tab
    // completes between these operations and waiter registration, the waiter
    // performs an immediate generation/lock re-check.
    const accessTokenBeforeLock = tokenManager.getAccessToken();

    // Check if another tab is currently refreshing
    if (!this.acquireRefreshLock()) {
      // Another tab is refreshing. Wait for its token or lock storage event;
      // lock ownership is deliberately independent from waiter state.
      const outcome = await this.waitForExternalTokenRefresh(accessTokenBeforeLock);
      if (outcome === 'invalid') this.expireSession();
      return outcome;
    }

    this.isRefreshing = true;
    this.refreshPromise = this.refreshAccessToken();

    try {
      const outcome = await this.refreshPromise;

      if (outcome === 'refreshed') {
        // Clear the in-flight request cache so retries after token refresh
        // get a fresh response with the new token rather than the cached 401.
        this.inflightRequests.clear();
      } else if (outcome === 'invalid') {
        this.expireSession();
      }

      return outcome;
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
    let didTimeout = false;
    const timeoutId = setTimeout(() => {
      didTimeout = true;
      controller.abort();
    }, timeout);

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
      captureTelemetryApiCall(method, endpoint, response.status, duration);
      recordApiDiagnostic({ method, endpoint, status: response.status, durationMs: duration });

      // Stale-client gate — every response carries the server's build SHA.
      // Triggers the soft-update path on first mismatch and force-redirects
      // to /api/sw-reset if the mismatch persists past the grace window.
      checkStaleBuild(response);

      // Handle 401 Unauthorized with exactly one token refresh and retry.
      if (response.status === 401 && !options.skipAuth) {
        if (retryOnUnauthorized) {
          const outcome = await this.handleTokenRefresh();

          if (outcome === 'refreshed') {
            return this.request<T>(endpoint, options, false);
          }
          if (outcome === 'transient') {
            return this.refreshUnavailableResponse<T>();
          }
          return this.sessionExpiredResponse<T>();
        }

        // A freshly-issued access token was explicitly rejected. Do not enter
        // another refresh cycle; deterministically expire the session.
        this.expireSession();
        return this.sessionExpiredResponse<T>();
      }

      // Handle 503 Service Unavailable (maintenance mode — body may be HTML, not JSON)
      if (response.status === 503) {
        try {
          const maintenanceData = await response.json() as Record<string, unknown>;
          const errors = Array.isArray(maintenanceData.errors)
            ? maintenanceData.errors as ApiErrorDetail[]
            : undefined;
          const firstError = errors?.[0];
          const code = maintenanceData.code ?? firstError?.code;
          if (code === 'MAINTENANCE_MODE') {
            return {
              success: false,
              error: (maintenanceData.error ?? firstError?.message ?? maintenanceData.message) as string | undefined,
              code,
            };
          }
        } catch {
          // Apache/proxies may return an HTML 503. Use the generic retryable
          // service-unavailable result below in that case.
        }
        return {
          success: false,
          error: 'Service temporarily unavailable',
          code: 'SERVICE_UNAVAILABLE',
        };
      }

      if (response.ok && options.responseType === 'blob') {
        const data = await response.blob();
        return { success: true, data: data as T };
      }

      if (response.ok && options.responseType === 'text') {
        const data = await response.text();
        return { success: true, data: data as T };
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

        if (typeof data === 'object' && data !== null && 'success' in data && data.success === false) {
          // 2FA-required is a partial success: HTTP 200 with success:false but
          // requires_2fa:true. Pass the payload through as data so the caller
          // (AuthContext) can branch on requires_2fa instead of treating it as
          // a login error.
          if (data.requires_2fa === true) {
            return { success: true, data: data as T, message: data.message, meta: data.meta };
          }

          const errors = Array.isArray(data.errors) ? data.errors as ApiErrorDetail[] : undefined;
          const firstError = errors && errors.length > 0 ? errors[0] : null;
          const result: ApiResponse<T> = {
            success: false,
            error: data.error ?? firstError?.message ?? data.message ?? 'Request failed',
            code: data.code ?? firstError?.code ?? 'REQUEST_FAILED',
            errors,
            meta: data.meta,
          };

          validateResponse(apiResponseSchema, result, `${options.method || 'GET'} ${endpoint}`);

          return result;
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
      const errors = Array.isArray(data.errors) ? data.errors as ApiErrorDetail[] : undefined;
      const firstError = errors && errors.length > 0 ? errors[0] : null;
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
        errors,
      };
    } catch (error) {
      clearTimeout(timeoutId);

      // Caller cancellation is an expected control-flow outcome and must not
      // be surfaced as a timeout or dispatched to the global error UI.
      if (isAbortError(error)) {
        if (callerSignal?.aborted && !didTimeout) {
          return { success: false, code: 'CANCELLED' };
        }

        const duration = performance.now() - startTime;
        captureTelemetryApiCall(method, endpoint, 408, duration); // 408 Request Timeout
        recordApiDiagnostic({ method, endpoint, status: 408, durationMs: duration });
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
    // Abortable GET requests should not share an in-flight promise, because one caller
    // cancelling its request would poison every other caller that reused the same promise.
    if (options?.signal) {
      return this.request<T>(endpoint, { ...options, method: 'GET' });
    }

    const requestOptions = { ...options, method: 'GET' };
    const cacheKey = this.getCacheKey(endpoint, requestOptions);
    const cacheTtl = this.getResponseCacheTtl(endpoint, requestOptions);

    if (cacheTtl > 0) {
      const cached = this.responseCache.get(cacheKey);
      if (cached && cached.expiresAt > Date.now()) {
        return cached.response as ApiResponse<T>;
      }
      if (cached) {
        this.responseCache.delete(cacheKey);
      }
    }

    // Check for in-flight request
    const inflight = this.inflightRequests.get(cacheKey);
    if (inflight) {
      return inflight as Promise<ApiResponse<T>>;
    }

    // Create new request
    const promise = this.request<T>(endpoint, requestOptions).then((response) => {
      if (cacheTtl > 0 && response.success) {
        this.responseCache.set(cacheKey, {
          expiresAt: Date.now() + cacheTtl,
          response: response as ApiResponse<unknown>,
        });
      }
      return response;
    });

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
    return this.downloadWithRetry(endpoint, options, true);
  }

  private async downloadWithRetry(
    endpoint: string,
    options: RequestOptions & { filename?: string },
    retryOnUnauthorized: boolean,
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

    // Stale-client gate — same check as request(), gates blob downloads too.
    checkStaleBuild(response);

    // Handle 401 with exactly one token refresh and retry.
    if (response.status === 401 && !options.skipAuth) {
      if (retryOnUnauthorized) {
        const outcome = await this.handleTokenRefresh();
        if (outcome === 'refreshed') {
          return this.downloadWithRetry(endpoint, options, false);
        }
        if (outcome === 'transient') {
          throw new Error(`Download failed (HTTP ${response.status})`);
        }
      } else {
        this.expireSession();
      }
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
    return this.uploadWithRetry<T>(endpoint, files, fieldName, options, true);
  }

  private async uploadWithRetry<T>(
    endpoint: string,
    files: File | File[] | FormData,
    fieldName: string,
    options: RequestOptions | undefined,
    retryOnUnauthorized: boolean,
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

    // Progress-aware uploads use XHR — fetch() exposes no upload progress events.
    if (options?.onUploadProgress) {
      return this.xhrUpload<T>(
        endpoint,
        formData,
        headers,
        options,
        options.onUploadProgress,
        retryOnUnauthorized,
      );
    }

    // Uploads should not be subject to the 30s default timeout — use 2 minutes
    const uploadController = new AbortController();
    let uploadTimedOut = false;
    const uploadTimeoutId = setTimeout(() => {
      uploadTimedOut = true;
      uploadController.abort();
    }, 120000);

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

      // Stale-client gate — uploads count as API calls too.
      checkStaleBuild(response);

      if (response.status === 401 && !options?.skipAuth) {
        if (retryOnUnauthorized) {
          const outcome = await this.handleTokenRefresh();
          if (outcome === 'refreshed') {
            return this.uploadWithRetry<T>(endpoint, files, fieldName, options, false);
          }
          if (outcome === 'transient') {
            return this.refreshUnavailableResponse<T>();
          }
        } else {
          this.expireSession();
        }
        return this.sessionExpiredResponse<T>();
      }

      const data = await response.json();

      if (response.ok) {
        if (data === null || data === undefined) {
          return { success: true, data: undefined as T };
        }
        if (typeof data === 'object' && data !== null && 'success' in data && data.success === false) {
          const errors = Array.isArray(data.errors) ? data.errors as ApiErrorDetail[] : undefined;
          const firstError = errors && errors.length > 0 ? errors[0] : null;
          return {
            success: false,
            error: data.error ?? firstError?.message ?? data.message ?? 'Upload failed',
            code: data.code ?? firstError?.code ?? 'UPLOAD_ERROR',
            errors,
            meta: data.meta,
          };
        }
        return { success: true, data: typeof data === 'object' && 'data' in data ? data.data : data, meta: data.meta };
      }

      // Handle error response (v2 API uses {errors: [{code, message}]}, v1 uses {error, code})
      const errors = Array.isArray(data.errors) ? data.errors as ApiErrorDetail[] : undefined;
      const firstError = errors && errors.length > 0 ? errors[0] : null;
      return {
        success: false,
        error: data.error ?? firstError?.message ?? data.message ?? 'Upload failed',
        code: data.code ?? firstError?.code ?? 'UPLOAD_ERROR',
        errors,
      };
    } catch (error) {
      clearTimeout(uploadTimeoutId);
      if (isAbortError(error)) {
        if (callerSignal?.aborted && !uploadTimedOut) {
          return { success: false, error: 'Upload cancelled', code: 'UPLOAD_ABORTED' };
        }
        return { success: false, error: 'Upload timed out. Please try again.', code: 'UPLOAD_TIMEOUT' };
      }
      const rawMessage = error instanceof Error ? error.message : 'Upload failed';
      const message = import.meta.env.PROD
        ? 'Upload failed. Please try again.'
        : rawMessage;
      return { success: false, error: message, code: 'NETWORK_ERROR' };
    }
  }

  /**
   * XHR-based multipart upload that reports byte progress (0-100). fetch()
   * cannot surface upload progress, so progress-aware callers (e.g. large
   * podcast audio uploads) route here. Mirrors upload()'s auth/tenant/CSRF
   * headers, credentials, 401-refresh-retry, and response shaping. The
   * stale-build gate is intentionally skipped here — ordinary API calls cover it.
   */
  private xhrUpload<T>(
    endpoint: string,
    formData: FormData,
    headers: Headers,
    options: RequestOptions | undefined,
    onProgress: (percent: number) => void,
    retryOnUnauthorized: boolean,
  ): Promise<ApiResponse<T>> {
    return new Promise<ApiResponse<T>>((resolve) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', `${this.baseUrl}${endpoint}`);
      xhr.withCredentials = true;
      xhr.timeout = options?.timeout ?? 300000; // 5 min default for media uploads
      headers.forEach((value, key) => {
        // Content-Type must be set by the browser with the multipart boundary.
        if (key.toLowerCase() !== 'content-type') xhr.setRequestHeader(key, value);
      });

      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable && event.total > 0) {
          onProgress(Math.min(100, Math.round((event.loaded / event.total) * 100)));
        }
      };

      const callerSignal = options?.signal as AbortSignal | undefined;
      if (callerSignal) {
        if (callerSignal.aborted) {
          xhr.abort();
        } else {
          callerSignal.addEventListener('abort', () => xhr.abort(), { once: true });
        }
      }

      const parse = (): Record<string, unknown> | null => {
        try {
          return xhr.responseText ? (JSON.parse(xhr.responseText) as Record<string, unknown>) : null;
        } catch {
          return null;
        }
      };

      xhr.onload = () => {
        if (xhr.status === 401 && !options?.skipAuth) {
          if (!retryOnUnauthorized) {
            this.expireSession();
            resolve(this.sessionExpiredResponse<T>());
            return;
          }

          void this.handleTokenRefresh().then((outcome) => {
            if (outcome === 'refreshed') {
              resolve(this.uploadWithRetry<T>(endpoint, formData, 'file', options, false));
            } else if (outcome === 'transient') {
              resolve(this.refreshUnavailableResponse<T>());
            } else {
              resolve(this.sessionExpiredResponse<T>());
            }
          });
          return;
        }

        const data = parse();
        const errors = data && Array.isArray(data.errors) ? (data.errors as ApiErrorDetail[]) : undefined;
        const firstError = errors && errors.length > 0 ? errors[0] : null;
        const meta = (data?.meta ?? undefined) as PaginationMeta | undefined;

        if (xhr.status >= 200 && xhr.status < 300) {
          if (data === null) {
            resolve({ success: true, data: undefined as T });
            return;
          }
          if ('success' in data && data.success === false) {
            resolve({
              success: false,
              error: (data.error as string) ?? firstError?.message ?? (data.message as string) ?? 'Upload failed',
              code: (data.code as string) ?? firstError?.code ?? 'UPLOAD_ERROR',
              errors,
              meta,
            });
            return;
          }
          resolve({ success: true, data: ('data' in data ? data.data : data) as T, meta });
          return;
        }

        resolve({
          success: false,
          error: (data?.error as string) ?? firstError?.message ?? (data?.message as string) ?? 'Upload failed',
          code: (data?.code as string) ?? firstError?.code ?? 'UPLOAD_ERROR',
          errors,
        });
      };

      xhr.onerror = () => resolve({
        success: false,
        error: import.meta.env.PROD ? 'Upload failed. Please try again.' : 'Network error during upload',
        code: 'NETWORK_ERROR',
      });
      xhr.ontimeout = () => resolve({ success: false, error: 'Upload timed out. Please try again.', code: 'UPLOAD_TIMEOUT' });
      xhr.onabort = () => resolve({ success: false, error: 'Upload cancelled', code: 'UPLOAD_ABORTED' });

      xhr.send(formData);
    });
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
