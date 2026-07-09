// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useBookmarkCollections.
 *
 * The hook keeps a module-level cache keyed by the authenticated user (and
 * tenant). Because that cache lives at module scope, each test loads a fresh
 * copy of the module via vi.resetModules() + dynamic import (see loadHook).
 *
 * Includes the P2 privacy regression tests: after a user switch or logout the
 * hook must refetch instead of serving the previous user's cached private
 * collections.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import type { BookmarkCollection } from './useBookmarkCollections';

type MockUser = { id: number } | null;

interface Harness {
  hook: typeof import('./useBookmarkCollections')['useBookmarkCollections'];
  get: ReturnType<typeof vi.fn>;
  post: ReturnType<typeof vi.fn>;
  logError: ReturnType<typeof vi.fn>;
  /** Change the mocked auth user; call rerender() afterwards to apply. */
  setUser: (user: MockUser) => void;
}

/**
 * Loads a fresh copy of the hook module with fresh api/logger/auth mocks so
 * the module-level cache starts empty for every test.
 */
async function loadHook(initialUser: MockUser = { id: 1 }): Promise<Harness> {
  vi.resetModules();

  let currentUser: MockUser = initialUser;
  const get = vi.fn();
  const post = vi.fn();
  const logError = vi.fn();

  vi.doMock('@/lib/api', () => ({
    api: { get, post, put: vi.fn(), delete: vi.fn() },
  }));
  vi.doMock('@/lib/logger', () => ({ logError }));
  vi.doMock('@/contexts', () => ({
    useAuth: () => ({ user: currentUser, isAuthenticated: currentUser !== null }),
  }));

  const mod = await import('./useBookmarkCollections');

  return {
    hook: mod.useBookmarkCollections,
    get,
    post,
    logError,
    setUser: (user) => {
      currentUser = user;
    },
  };
}

const MOCK_COLLECTIONS: BookmarkCollection[] = [
  { id: 1, name: 'Favourites', description: null, is_default: true, bookmarks_count: 5 },
  { id: 2, name: 'Later', description: 'Read later', is_default: false, bookmarks_count: 2 },
];

const USER_B_COLLECTIONS: BookmarkCollection[] = [
  { id: 7, name: 'B own list', description: null, is_default: true, bookmarks_count: 1 },
];

describe('useBookmarkCollections', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  describe('initial fetch', () => {
    it('starts with isLoading true and empty collections when no cache exists', async () => {
      const h = await loadHook();
      h.get.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });

      const { result } = renderHook(() => h.hook());

      // Before the async fetch completes: loading + empty
      expect(result.current.isLoading).toBe(true);
      expect(result.current.collections).toEqual([]);

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.collections).toEqual(MOCK_COLLECTIONS);
      expect(h.get).toHaveBeenCalledWith('/v2/bookmark-collections');
    });

    it('sets isLoading to false and collections remain empty on API failure', async () => {
      const h = await loadHook();
      h.get.mockRejectedValueOnce(new Error('Network error'));

      const { result } = renderHook(() => h.hook());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.collections).toEqual([]);
      expect(h.logError).toHaveBeenCalledWith(
        'Failed to fetch bookmark collections',
        expect.any(Error)
      );
    });

    it('leaves collections empty when success is false', async () => {
      const h = await loadHook();
      h.get.mockResolvedValueOnce({ success: false, data: null });

      const { result } = renderHook(() => h.hook());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // When success=false, collections should remain empty (cache not populated)
      expect(result.current.collections).toEqual([]);
    });

    it('does not fetch at all when there is no authenticated user', async () => {
      const h = await loadHook(null);

      const { result } = renderHook(() => h.hook());

      expect(result.current.isLoading).toBe(false);
      expect(result.current.collections).toEqual([]);
      expect(h.get).not.toHaveBeenCalled();
    });
  });

  describe('cache scoping by user (privacy regression, P2)', () => {
    it('serves the cache to the same user across hook instances without refetching', async () => {
      const h = await loadHook({ id: 1 });
      h.get.mockResolvedValue({ success: true, data: MOCK_COLLECTIONS });

      const first = renderHook(() => h.hook());
      await waitFor(() => expect(first.result.current.isLoading).toBe(false));
      expect(h.get).toHaveBeenCalledTimes(1);
      first.unmount();

      const second = renderHook(() => h.hook());
      // Cache hit: data available immediately, no second GET
      expect(second.result.current.collections).toEqual(MOCK_COLLECTIONS);
      expect(second.result.current.isLoading).toBe(false);
      expect(h.get).toHaveBeenCalledTimes(1);
    });

    it("refetches instead of serving the previous user's cache when the auth user changes", async () => {
      const h = await loadHook({ id: 1 });
      h.get.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });

      const { result, rerender } = renderHook(() => h.hook());
      await waitFor(() => expect(result.current.isLoading).toBe(false));
      expect(result.current.collections).toEqual(MOCK_COLLECTIONS);
      expect(h.get).toHaveBeenCalledTimes(1);

      // Simulate a user switch in the same JS session (shared browser)
      h.get.mockResolvedValueOnce({ success: true, data: USER_B_COLLECTIONS });
      h.setUser({ id: 2 });
      rerender();

      // User A's private collections must not be shown, even transiently
      expect(result.current.collections).toEqual([]);

      await waitFor(() => {
        expect(result.current.collections).toEqual(USER_B_COLLECTIONS);
      });
      expect(h.get).toHaveBeenCalledTimes(2);
    });

    it("clears the cache on logout so a later user never sees the previous user's collections", async () => {
      const h = await loadHook({ id: 1 });
      h.get.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });

      const first = renderHook(() => h.hook());
      await waitFor(() => expect(first.result.current.isLoading).toBe(false));
      expect(first.result.current.collections).toEqual(MOCK_COLLECTIONS);

      // Logout: user becomes null
      h.setUser(null);
      first.rerender();
      expect(first.result.current.collections).toEqual([]);
      expect(first.result.current.isLoading).toBe(false);
      first.unmount();

      // User B logs in and a fresh component mounts
      h.get.mockResolvedValueOnce({ success: true, data: USER_B_COLLECTIONS });
      h.setUser({ id: 2 });
      const second = renderHook(() => h.hook());

      // Never user A's data — starts empty, then B's own collections
      expect(second.result.current.collections).toEqual([]);
      await waitFor(() => {
        expect(second.result.current.collections).toEqual(USER_B_COLLECTIONS);
      });
      expect(h.get).toHaveBeenCalledTimes(2);
    });

    it('scopes the cache by tenant id as well as user id', async () => {
      window.localStorage.setItem('nexus_tenant_id', '2');
      const h = await loadHook({ id: 1 });
      h.get.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });

      const { result, rerender } = renderHook(() => h.hook());
      await waitFor(() => expect(result.current.isLoading).toBe(false));
      expect(h.get).toHaveBeenCalledTimes(1);

      // Same user id, different tenant — must not reuse the cached entry
      window.localStorage.setItem('nexus_tenant_id', '3');
      h.get.mockResolvedValueOnce({ success: true, data: USER_B_COLLECTIONS });
      rerender();

      expect(result.current.collections).toEqual([]);
      await waitFor(() => {
        expect(result.current.collections).toEqual(USER_B_COLLECTIONS);
      });
      expect(h.get).toHaveBeenCalledTimes(2);
    });
  });

  describe('fetchCollections (manual refetch)', () => {
    it('re-fetches and updates collections', async () => {
      const h = await loadHook();

      const firstBatch: BookmarkCollection[] = [
        { id: 1, name: 'A', description: null, is_default: true, bookmarks_count: 0 },
      ];
      const secondBatch: BookmarkCollection[] = [
        { id: 1, name: 'A', description: null, is_default: true, bookmarks_count: 0 },
        { id: 3, name: 'B', description: null, is_default: false, bookmarks_count: 1 },
      ];

      h.get.mockResolvedValueOnce({ success: true, data: firstBatch });

      const { result } = renderHook(() => h.hook());

      await waitFor(() => expect(result.current.isLoading).toBe(false));
      expect(result.current.collections).toEqual(firstBatch);

      h.get.mockResolvedValueOnce({ success: true, data: secondBatch });

      await act(async () => {
        await result.current.fetchCollections();
      });

      expect(result.current.collections).toEqual(secondBatch);
      expect(h.get).toHaveBeenCalledTimes(2);
    });
  });

  describe('createCollection', () => {
    it('posts to the API and appends the new collection to state', async () => {
      const h = await loadHook();
      h.get.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });

      const newCollection: BookmarkCollection = {
        id: 99,
        name: 'New',
        description: null,
        is_default: false,
        bookmarks_count: 0,
      };
      h.post.mockResolvedValueOnce({ success: true, data: newCollection });

      const { result } = renderHook(() => h.hook());
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let created: BookmarkCollection | null = null;
      await act(async () => {
        created = await result.current.createCollection('New');
      });

      expect(created).toEqual(newCollection);
      expect(h.post).toHaveBeenCalledWith('/v2/bookmark-collections', { name: 'New' });
      expect(result.current.collections).toContainEqual(newCollection);
      expect(result.current.collections).toHaveLength(MOCK_COLLECTIONS.length + 1);
    });

    it('returns null and logs when createCollection API call fails', async () => {
      const h = await loadHook();
      h.get.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });
      h.post.mockRejectedValueOnce(new Error('Server error'));

      const { result } = renderHook(() => h.hook());
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let created: BookmarkCollection | null | undefined;
      await act(async () => {
        created = await result.current.createCollection('FailMe');
      });

      expect(created).toBeNull();
      expect(h.logError).toHaveBeenCalledWith(
        'Failed to create bookmark collection',
        expect.any(Error)
      );
    });

    it('returns null when API responds success:false', async () => {
      const h = await loadHook();
      h.get.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });
      h.post.mockResolvedValueOnce({ success: false, data: null });

      const { result } = renderHook(() => h.hook());
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let created: BookmarkCollection | null | undefined;
      await act(async () => {
        created = await result.current.createCollection('Fail');
      });

      expect(created).toBeNull();
    });
  });

  describe('return shape', () => {
    it('exposes collections, isLoading, fetchCollections, createCollection', async () => {
      const h = await loadHook();
      h.get.mockResolvedValueOnce({ success: true, data: [] });

      const { result } = renderHook(() => h.hook());

      expect(typeof result.current.collections).toBe('object');
      expect(typeof result.current.isLoading).toBe('boolean');
      expect(typeof result.current.fetchCollections).toBe('function');
      expect(typeof result.current.createCollection).toBe('function');

      await waitFor(() => expect(result.current.isLoading).toBe(false));
    });
  });
});
