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
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const API_BASE = import.meta.env.VITE_API_BASE || '/api';
const TOKEN_KEY = 'nexus_access_token';
const REFRESH_TOKEN_KEY = 'nexus_refresh_token';
const TENANT_ID_KEY = 'nexus_tenant_id';
const CSRF_TOKEN_KEY = 'nexus_csrf_token';

// Default tenant ID for development (will be overwritten by tenant bootstrap)
const DEFAULT_TENANT_ID = import.meta.env.VITE_DEFAULT_TENANT_ID || '1';

// Custom events
export const SESSION_EXPIRED_EVENT = 'nexus:session_expired';
export const API_ERROR_EVENT = 'nexus:api_error';

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
  cursor?: string;
  current_page?: number;
  total_items?: number;
  total_pages?: number;
  // Messages API returns conversation details in meta
  conversation?: {
    id: number;
    other_user: {
      id: number;
      name: string;
      avatar?: string | null;
      tagline?: string;
    };
  };
}

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  skipAuth?: boolean;
  skipTenant?: boolean;
  skipCsrf?: boolean;
  body?: unknown;
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
    return localStorage.getItem(TENANT_ID_KEY) || DEFAULT_TENANT_ID;
  },

  setTenantId(id: string | number): void {
    localStorage.setItem(TENANT_ID_KEY, String(id));
  },

  clearTokens(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(REFRESH_TOKEN_KEY);
  },

  clearAll(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(REFRESH_TOKEN_KEY);
    localStorage.removeItem(TENANT_ID_KEY);
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

  constructor(baseUrl: string = API_BASE) {
    this.baseUrl = baseUrl;
  }

  /**
   * Dispatch session expired event
   */
  private dispatchSessionExpired(): void {
    window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
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
    return `${options.method || 'GET'}:${endpoint}`;
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
      const response = await fetch(`${this.baseUrl}/auth/refresh-token`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
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
   * Handle token refresh with request queuing
   */
  private async handleTokenRefresh(): Promise<boolean> {
    // If already refreshing, wait for the result
    if (this.isRefreshing && this.refreshPromise) {
      return this.refreshPromise;
    }

    this.isRefreshing = true;
    this.refreshPromise = this.refreshAccessToken();

    try {
      const success = await this.refreshPromise;

      // Resolve all pending requests
      this.pendingRequests.forEach(({ resolve }) => resolve(success));
      this.pendingRequests = [];

      if (!success) {
        tokenManager.clearTokens();
        this.dispatchSessionExpired();
      }

      return success;
    } finally {
      this.isRefreshing = false;
      this.refreshPromise = null;
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

    try {
      const response = await fetch(url, {
        ...options,
        headers,
        body,
        credentials: 'include',
      });

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
        return {
          success: true,
          data: data.data ?? data,
          message: data.message,
          meta: data.meta,
        };
      }

      // Handle error response
      return {
        success: false,
        error: data.error ?? data.message ?? 'Request failed',
        code: data.code ?? `HTTP_${response.status}`,
      };
    } catch (error) {
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

    // Build headers without Content-Type (browser sets it with boundary)
    const headers = new Headers(options?.headers);
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

    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        method: 'POST',
        headers,
        body: formData,
        credentials: 'include',
      });

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
        return { success: true, data: data.data ?? data };
      }

      return {
        success: false,
        error: data.error ?? 'Upload failed',
        code: data.code ?? 'UPLOAD_ERROR',
      };
    } catch (error) {
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
  try {
    const response = await api.get<{ token: string }>('/csrf-token', { skipCsrf: true });
    if (response.success && response.data?.token) {
      tokenManager.setCsrfToken(response.data.token);
      return response.data.token;
    }
    return null;
  } catch {
    return null;
  }
}

export default api;
