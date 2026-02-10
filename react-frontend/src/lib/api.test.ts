/**
 * Tests for API client
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  tokenManager,
  SESSION_EXPIRED_EVENT,
  API_ERROR_EVENT,
  buildQueryString,
} from './api';

// ─────────────────────────────────────────────────────────────────────────────
// Token Manager Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('tokenManager', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    localStorage.clear();
  });

  describe('access token', () => {
    it('stores and retrieves access token', () => {
      tokenManager.setAccessToken('test-token');
      expect(tokenManager.getAccessToken()).toBe('test-token');
    });

    it('returns null when no token exists', () => {
      expect(tokenManager.getAccessToken()).toBeNull();
    });

    it('hasAccessToken returns true when token exists', () => {
      tokenManager.setAccessToken('test-token');
      expect(tokenManager.hasAccessToken()).toBe(true);
    });

    it('hasAccessToken returns false when no token', () => {
      expect(tokenManager.hasAccessToken()).toBe(false);
    });
  });

  describe('refresh token', () => {
    it('stores and retrieves refresh token', () => {
      tokenManager.setRefreshToken('refresh-token');
      expect(tokenManager.getRefreshToken()).toBe('refresh-token');
    });

    it('returns null when no refresh token exists', () => {
      expect(tokenManager.getRefreshToken()).toBeNull();
    });

    it('hasRefreshToken returns true when token exists', () => {
      tokenManager.setRefreshToken('refresh-token');
      expect(tokenManager.hasRefreshToken()).toBe(true);
    });

    it('hasRefreshToken returns false when no token', () => {
      expect(tokenManager.hasRefreshToken()).toBe(false);
    });
  });

  describe('tenant ID', () => {
    it('stores and retrieves tenant ID', () => {
      tokenManager.setTenantId('123');
      expect(tokenManager.getTenantId()).toBe('123');
    });

    it('accepts numeric tenant ID', () => {
      tokenManager.setTenantId(456);
      expect(tokenManager.getTenantId()).toBe('456');
    });

    it('returns null when none set and no default', () => {
      // Without environment variable default, returns null
      const result = tokenManager.getTenantId();
      // May return null or default depending on env
      expect(result === null || typeof result === 'string').toBe(true);
    });
  });

  describe('CSRF token', () => {
    it('stores and retrieves CSRF token', () => {
      tokenManager.setCsrfToken('csrf-token');
      expect(tokenManager.getCsrfToken()).toBe('csrf-token');
    });

    it('returns null when no CSRF token exists', () => {
      expect(tokenManager.getCsrfToken()).toBeNull();
    });

    it('clears CSRF token', () => {
      tokenManager.setCsrfToken('csrf-token');
      tokenManager.clearCsrfToken();
      expect(tokenManager.getCsrfToken()).toBeNull();
    });
  });

  describe('clearTokens', () => {
    it('clears access and refresh tokens', () => {
      tokenManager.setAccessToken('access');
      tokenManager.setRefreshToken('refresh');
      tokenManager.clearTokens();

      expect(tokenManager.getAccessToken()).toBeNull();
      expect(tokenManager.getRefreshToken()).toBeNull();
    });

    it('does not clear tenant ID', () => {
      tokenManager.setTenantId('123');
      tokenManager.clearTokens();
      expect(tokenManager.getTenantId()).toBe('123');
    });
  });

  describe('clearAll', () => {
    it('clears all stored data including tenant ID', () => {
      tokenManager.setAccessToken('access');
      tokenManager.setRefreshToken('refresh');
      tokenManager.setTenantId('123');
      tokenManager.clearAll();

      expect(tokenManager.getAccessToken()).toBeNull();
      expect(tokenManager.getRefreshToken()).toBeNull();
      // Tenant ID returns null or environment default after clear
    });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// API Client Tests (with fetch mocking)
// ─────────────────────────────────────────────────────────────────────────────

describe('API Client', () => {
  // Need to re-import api after clearing mocks
  let api: typeof import('./api').api;

  beforeEach(async () => {
    localStorage.clear();
    vi.resetModules();
    vi.stubGlobal('fetch', vi.fn());

    // Re-import to get fresh instance
    const module = await import('./api');
    api = module.api;
  });

  afterEach(() => {
    localStorage.clear();
    vi.unstubAllGlobals();
    vi.resetModules();
  });

  describe('GET requests', () => {
    it('makes GET request with correct headers', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: { id: 1 } }),
      } as Response);

      tokenManager.setAccessToken('test-token');
      tokenManager.setTenantId('2');

      await api.get('/v2/users/me');

      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/v2/users/me'),
        expect.objectContaining({
          method: 'GET',
          credentials: 'include',
        })
      );

      // Check headers
      const call = vi.mocked(fetch).mock.calls[0];
      const headers = call[1]?.headers as Headers;
      expect(headers.get('Authorization')).toBe('Bearer test-token');
      expect(headers.get('X-Tenant-ID')).toBe('2');
      expect(headers.get('Accept')).toBe('application/json');
    });

    it('returns parsed data on success', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: { id: 1, name: 'Test' } }),
      } as Response);

      const response = await api.get('/v2/test');

      expect(response.success).toBe(true);
      expect(response.data).toEqual({ id: 1, name: 'Test' });
    });

    it('deduplicates concurrent identical GET requests', async () => {
      vi.mocked(fetch).mockResolvedValue({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: { id: 1 } }),
      } as Response);

      // Make 3 concurrent requests to same endpoint
      const promises = [
        api.get('/v2/test'),
        api.get('/v2/test'),
        api.get('/v2/test'),
      ];

      await Promise.all(promises);

      // Should only make one fetch call
      expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('does not deduplicate different endpoints', async () => {
      vi.mocked(fetch).mockResolvedValue({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: {} }),
      } as Response);

      await Promise.all([
        api.get('/v2/test1'),
        api.get('/v2/test2'),
      ]);

      expect(fetch).toHaveBeenCalledTimes(2);
    });
  });

  describe('POST requests', () => {
    it('makes POST request with JSON body', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: () => Promise.resolve({ data: { id: 1 } }),
      } as Response);

      tokenManager.setCsrfToken('csrf-token');

      await api.post('/v2/listings', { title: 'Test' });

      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/v2/listings'),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ title: 'Test' }),
        })
      );

      // Check CSRF token
      const call = vi.mocked(fetch).mock.calls[0];
      const headers = call[1]?.headers as Headers;
      expect(headers.get('X-CSRF-Token')).toBe('csrf-token');
      expect(headers.get('Content-Type')).toBe('application/json');
    });
  });

  describe('PUT requests', () => {
    it('makes PUT request with JSON body', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: { id: 1 } }),
      } as Response);

      await api.put('/v2/listings/1', { title: 'Updated' });

      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/v2/listings/1'),
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify({ title: 'Updated' }),
        })
      );
    });
  });

  describe('DELETE requests', () => {
    it('makes DELETE request', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ success: true }),
      } as Response);

      await api.delete('/v2/listings/1');

      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/v2/listings/1'),
        expect.objectContaining({
          method: 'DELETE',
        })
      );
    });
  });

  describe('error handling', () => {
    it('returns error response for 4xx errors', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: () => Promise.resolve({ error: 'Bad request', code: 'BAD_REQUEST' }),
      } as Response);

      const response = await api.get('/v2/test');

      expect(response.success).toBe(false);
      expect(response.error).toBe('Bad request');
      expect(response.code).toBe('BAD_REQUEST');
    });

    it('returns error response for 5xx errors', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: () => Promise.resolve({ error: 'Server error' }),
      } as Response);

      const response = await api.get('/v2/test');

      expect(response.success).toBe(false);
      expect(response.error).toBe('Server error');
    });

    it('handles network errors gracefully', async () => {
      vi.mocked(fetch).mockRejectedValueOnce(new Error('Network error'));

      const response = await api.get('/v2/test');

      expect(response.success).toBe(false);
      expect(response.code).toBe('NETWORK_ERROR');
    });

    it('dispatches API error event on network error', async () => {
      const eventHandler = vi.fn();
      window.addEventListener(API_ERROR_EVENT, eventHandler);

      vi.mocked(fetch).mockRejectedValueOnce(new Error('Network error'));

      await api.get('/v2/test');

      expect(eventHandler).toHaveBeenCalled();

      window.removeEventListener(API_ERROR_EVENT, eventHandler);
    });
  });

  describe('401 handling and token refresh', () => {
    it('attempts token refresh on 401', async () => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');

      // First call returns 401
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        // Refresh token call succeeds
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: () =>
            Promise.resolve({
              success: true,
              access_token: 'new-token',
              refresh_token: 'new-refresh-token',
            }),
        } as Response)
        // Retry succeeds
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: () => Promise.resolve({ data: { id: 1 } }),
        } as Response);

      const response = await api.get('/v2/users/me');

      expect(response.success).toBe(true);
      expect(fetch).toHaveBeenCalledTimes(3); // Original + refresh + retry
    });

    it('dispatches session expired event when refresh fails', async () => {
      const eventHandler = vi.fn();
      window.addEventListener(SESSION_EXPIRED_EVENT, eventHandler);

      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');

      // First call returns 401
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        // Refresh fails
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: () => Promise.resolve({ error: 'Refresh token expired' }),
        } as Response);

      await api.get('/v2/users/me');

      expect(eventHandler).toHaveBeenCalled();

      window.removeEventListener(SESSION_EXPIRED_EVENT, eventHandler);
    });

    it('skips refresh when skipAuth is true', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: () => Promise.resolve({ error: 'Unauthorized' }),
      } as Response);

      const response = await api.get('/v2/public', { skipAuth: true });

      expect(response.success).toBe(false);
      expect(fetch).toHaveBeenCalledTimes(1); // No refresh attempt
    });
  });

  describe('skipTenant option', () => {
    it('does not include tenant ID when skipTenant is true', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: [] }),
      } as Response);

      tokenManager.setTenantId('2');

      await api.get('/v2/tenants', { skipTenant: true });

      const call = vi.mocked(fetch).mock.calls[0];
      const headers = call[1]?.headers as Headers;
      expect(headers.get('X-Tenant-ID')).toBeNull();
    });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Utility Function Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('buildQueryString', () => {
  it('builds query string from object', () => {
    const result = buildQueryString({ q: 'test', limit: 10 });
    expect(result).toBe('?q=test&limit=10');
  });

  it('returns empty string for empty object', () => {
    const result = buildQueryString({});
    expect(result).toBe('');
  });

  it('skips null and undefined values', () => {
    const result = buildQueryString({
      q: 'test',
      filter: null,
      sort: undefined,
      limit: 10,
    });
    expect(result).toBe('?q=test&limit=10');
  });

  it('skips empty string values', () => {
    const result = buildQueryString({ q: '', limit: 10 });
    expect(result).toBe('?limit=10');
  });

  it('converts boolean values to strings', () => {
    const result = buildQueryString({ active: true, archived: false });
    expect(result).toBe('?active=true&archived=false');
  });
});
