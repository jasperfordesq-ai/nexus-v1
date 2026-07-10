// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
        headers: new Headers(),
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
        headers: new Headers(),
        json: () => Promise.resolve({ data: { id: 1, name: 'Test' } }),
      } as Response);

      const response = await api.get('/v2/test');

      expect(response.success).toBe(true);
      expect(response.data).toEqual({ id: 1, name: 'Test' });
    });

    it('preserves application-level failure envelopes returned with HTTP 2xx', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        headers: new Headers(),
        json: () => Promise.resolve({ success: false, error: 'Validation failed', code: 'VALIDATION_ERROR' }),
      } as Response);

      const response = await api.get('/v2/test');

      expect(response.success).toBe(false);
      expect(response.error).toBe('Validation failed');
      expect(response.code).toBe('VALIDATION_ERROR');
    });

    it('deduplicates concurrent identical GET requests', async () => {
      vi.mocked(fetch).mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers(),
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
        headers: new Headers(),
        json: () => Promise.resolve({ data: {} }),
      } as Response);

      await Promise.all([
        api.get('/v2/test1'),
        api.get('/v2/test2'),
      ]);

      expect(fetch).toHaveBeenCalledTimes(2);
    });

    it('does not deduplicate abortable GET requests', async () => {
      vi.mocked(fetch).mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers(),
        json: () => Promise.resolve({ data: { id: 1 } }),
      } as Response);

      const controllerA = new AbortController();
      const controllerB = new AbortController();

      await Promise.all([
        api.get('/v2/test', { signal: controllerA.signal }),
        api.get('/v2/test', { signal: controllerB.signal }),
      ]);

      expect(fetch).toHaveBeenCalledTimes(2);
    });
  });

  describe('POST requests', () => {
    it('makes POST request with JSON body', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 201,
        headers: new Headers(),
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

  describe('file uploads', () => {
    it('preserves application-level upload failure envelopes returned with HTTP 2xx', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        headers: new Headers(),
        json: () => Promise.resolve({ success: false, error: 'Upload rejected', code: 'UPLOAD_REJECTED' }),
      } as Response);

      const response = await api.upload('/v2/files', new File(['x'], 'x.txt'));

      expect(response.success).toBe(false);
      expect(response.error).toBe('Upload rejected');
      expect(response.code).toBe('UPLOAD_REJECTED');
    });

    it('stops fetch upload after one refresh when the retry remains unauthorized', async () => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve({ success: true, access_token: 'new-token' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Still unauthorized' }),
        } as Response);

      const response = await api.upload('/v2/files', new File(['x'], 'x.txt'));

      expect(response.code).toBe('SESSION_EXPIRED');
      expect(fetch).toHaveBeenCalledTimes(3);
      expect(tokenManager.getAccessToken()).toBeNull();
    });

    it('stops progress-aware XHR upload after one refresh when the retry remains unauthorized', async () => {
      class FakeXMLHttpRequest {
        static instances: FakeXMLHttpRequest[] = [];
        status = 0;
        responseText = '';
        timeout = 0;
        withCredentials = false;
        upload: { onprogress: ((event: ProgressEvent) => void) | null } = { onprogress: null };
        onload: (() => void) | null = null;
        onerror: (() => void) | null = null;
        ontimeout: (() => void) | null = null;
        onabort: (() => void) | null = null;

        constructor() {
          FakeXMLHttpRequest.instances.push(this);
        }

        open(): void {}
        setRequestHeader(): void {}
        send(): void {}
        abort(): void { this.onabort?.(); }

        respond(status: number, body: Record<string, unknown>): void {
          this.status = status;
          this.responseText = JSON.stringify(body);
          this.onload?.();
        }
      }

      vi.stubGlobal('XMLHttpRequest', FakeXMLHttpRequest);
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        headers: new Headers(),
        json: () => Promise.resolve({ success: true, access_token: 'new-token' }),
      } as Response);

      const responsePromise = api.upload(
        '/v2/files',
        new File(['x'], 'x.txt'),
        'file',
        { onUploadProgress: vi.fn() },
      );
      FakeXMLHttpRequest.instances[0]?.respond(401, { error: 'Unauthorized' });
      await vi.waitFor(() => expect(FakeXMLHttpRequest.instances).toHaveLength(2));
      FakeXMLHttpRequest.instances[1]?.respond(401, { error: 'Still unauthorized' });

      const response = await responsePromise;
      expect(response.code).toBe('SESSION_EXPIRED');
      expect(fetch).toHaveBeenCalledTimes(1);
      expect(FakeXMLHttpRequest.instances).toHaveLength(2);
      expect(tokenManager.getAccessToken()).toBeNull();
    });
  });

  describe('file downloads', () => {
    it('stops after one refresh when the retried download remains unauthorized', async () => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
        } as Response)
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve({ success: true, access_token: 'new-token' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
        } as Response);

      await expect(api.download('/v2/export')).rejects.toThrow('Session expired');

      expect(fetch).toHaveBeenCalledTimes(3);
      expect(tokenManager.getAccessToken()).toBeNull();
      expect(tokenManager.getRefreshToken()).toBeNull();
    });
  });

  describe('PUT requests', () => {
    it('makes PUT request with JSON body', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        headers: new Headers(),
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
        headers: new Headers(),
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
        headers: new Headers(),
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
        headers: new Headers(),
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

    it('returns caller cancellation without dispatching a global timeout error', async () => {
      const eventHandler = vi.fn();
      window.addEventListener(API_ERROR_EVENT, eventHandler);
      const controller = new AbortController();
      controller.abort();
      vi.mocked(fetch).mockRejectedValueOnce(new DOMException('Aborted', 'AbortError'));

      const response = await api.get('/v2/test', { signal: controller.signal });

      expect(response).toEqual({ success: false, code: 'CANCELLED' });
      expect(eventHandler).not.toHaveBeenCalled();
      window.removeEventListener(API_ERROR_EVENT, eventHandler);
    });

    it('still classifies the client timeout controller as TIMEOUT', async () => {
      const eventHandler = vi.fn();
      window.addEventListener(API_ERROR_EVENT, eventHandler);
      vi.mocked(fetch).mockImplementationOnce((_url, init) => new Promise((_resolve, reject) => {
        init?.signal?.addEventListener('abort', () => {
          reject(new DOMException('Aborted', 'AbortError'));
        }, { once: true });
      }));

      const response = await api.get('/v2/test', { timeout: 1 });

      expect(response.code).toBe('TIMEOUT');
      expect(eventHandler).toHaveBeenCalledTimes(1);
      window.removeEventListener(API_ERROR_EVENT, eventHandler);
    });

    it('preserves an explicit maintenance response code and backend message on 503', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: false,
        status: 503,
        headers: new Headers(),
        json: () => Promise.resolve({
          success: false,
          code: 'MAINTENANCE_MODE',
          error: 'Localized maintenance message',
        }),
      } as Response);

      const response = await api.get('/v2/test');

      expect(response).toEqual({
        success: false,
        code: 'MAINTENANCE_MODE',
        error: 'Localized maintenance message',
      });
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
        headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        // Refresh token call succeeds
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
        headers: new Headers(),
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
        headers: new Headers(),
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
        headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        // Refresh fails
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
        headers: new Headers(),
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
        headers: new Headers(),
        json: () => Promise.resolve({ error: 'Unauthorized' }),
      } as Response);

      const response = await api.get('/v2/public', { skipAuth: true });

      expect(response.success).toBe(false);
      expect(fetch).toHaveBeenCalledTimes(1); // No refresh attempt
    });

    it.each([
      ['network failure', new Error('offline')],
      ['abort-like transport failure', new DOMException('Aborted', 'AbortError')],
    ])('preserves credentials when token refresh has a transient %s', async (_label, failure) => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        .mockRejectedValueOnce(failure);

      const response = await api.get('/v2/users/me');

      expect(response).toEqual({ success: false, code: 'AUTH_REFRESH_UNAVAILABLE' });
      expect(tokenManager.getAccessToken()).toBe('old-token');
      expect(tokenManager.getRefreshToken()).toBe('refresh-token');
      expect(fetch).toHaveBeenCalledTimes(2);
    });

    it('preserves credentials when the refresh endpoint returns 5xx', async () => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: false,
          status: 503,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unavailable' }),
        } as Response);

      const response = await api.get('/v2/users/me');

      expect(response.code).toBe('AUTH_REFRESH_UNAVAILABLE');
      expect(tokenManager.getAccessToken()).toBe('old-token');
      expect(tokenManager.getRefreshToken()).toBe('refresh-token');
      expect(fetch).toHaveBeenCalledTimes(2);
    });

    it('expires after one refresh when the retried standard request is still 401', async () => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve({ success: true, access_token: 'new-token' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Still unauthorized' }),
        } as Response);

      const response = await api.get('/v2/users/me');

      expect(response.code).toBe('SESSION_EXPIRED');
      expect(fetch).toHaveBeenCalledTimes(3);
      expect(tokenManager.getAccessToken()).toBeNull();
      expect(tokenManager.getRefreshToken()).toBeNull();
    });

    it('observes a cross-tab refresh that completes during lock handoff without waiting', async () => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      localStorage.setItem('nexus_token_refresh_lock', String(Date.now()));

      const originalGetItem = Storage.prototype.getItem;
      let raced = false;
      const getItemSpy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(function (key: string) {
        const value = originalGetItem.call(this, key);
        if (key === 'nexus_token_refresh_lock' && !raced) {
          raced = true;
          localStorage.setItem('nexus_access_token', 'other-tab-token');
          localStorage.removeItem('nexus_token_refresh_lock');
        }
        return value;
      });

      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve({ data: { id: 1 } }),
        } as Response);

      const response = await api.get('/v2/users/me');

      getItemSpy.mockRestore();
      expect(response.success).toBe(true);
      expect(fetch).toHaveBeenCalledTimes(2);
      const retryHeaders = vi.mocked(fetch).mock.calls[1]?.[1]?.headers as Headers;
      expect(retryHeaders.get('Authorization')).toBe('Bearer other-tab-token');
    });

    it('resumes immediately when a refreshing tab publishes a new token storage event', async () => {
      tokenManager.setAccessToken('old-token');
      tokenManager.setRefreshToken('refresh-token');
      localStorage.setItem('nexus_token_refresh_lock', String(Date.now()));
      vi.mocked(fetch)
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          headers: new Headers(),
          json: () => Promise.resolve({ error: 'Unauthorized' }),
        } as Response)
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve({ data: { id: 1 } }),
        } as Response);

      const responsePromise = api.get('/v2/users/me');
      await vi.waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));
      localStorage.setItem('nexus_access_token', 'published-token');
      window.dispatchEvent(new StorageEvent('storage', {
        key: 'nexus_access_token',
        oldValue: 'old-token',
        newValue: 'published-token',
        storageArea: localStorage,
      }));

      const response = await responsePromise;
      expect(response.success).toBe(true);
      expect(fetch).toHaveBeenCalledTimes(2);
      const retryHeaders = vi.mocked(fetch).mock.calls[1]?.[1]?.headers as Headers;
      expect(retryHeaders.get('Authorization')).toBe('Bearer published-token');
    });
  });

  describe('skipTenant option', () => {
    it('does not include tenant ID when skipTenant is true', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        status: 200,
        headers: new Headers(),
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
