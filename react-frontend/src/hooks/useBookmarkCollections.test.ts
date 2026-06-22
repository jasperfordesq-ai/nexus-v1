// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useBookmarkCollections, BookmarkCollection } from './useBookmarkCollections';

// Mock the API module
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

// Mock the logger so logError calls don't emit console noise in tests
vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

const mockGet = api.get as ReturnType<typeof vi.fn>;
const mockPost = api.post as ReturnType<typeof vi.fn>;

// The module uses a module-level cache variable `cachedCollections`.
// We need to reset it between tests so each test starts from a clean state.
// We do this by resetting the module between tests via vi.resetModules() OR
// by leveraging the fact that fetchCollections + re-render will override it.
// However since the cache is module-level, we use vi.isolateModules per test
// or simply clear it by calling the module reset. The simplest approach here
// is to use vi.resetModules() in beforeEach and re-import inside tests.
// But that would make the import() async — instead we'll use vi.resetModules
// combined with dynamic import in a helper. For simplicity in this test suite,
// we directly reach into the module internals by re-importing after reset.

// NOTE: The module-level `cachedCollections` is set to `null` at module load.
// Between tests we can't easily null it without module reloads. The approach
// here is to always mock `api.get` to return a fresh response, and accept that
// later tests that run after the cache is populated will exercise the "cache hit"
// path. We have explicit tests for both paths.

const MOCK_COLLECTIONS: BookmarkCollection[] = [
  { id: 1, name: 'Favourites', description: null, is_default: true, bookmarks_count: 5 },
  { id: 2, name: 'Later', description: 'Read later', is_default: false, bookmarks_count: 2 },
];

describe('useBookmarkCollections', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Reset the module-level cache by resetting modules so each describe block
    // starts fresh. We use vi.resetModules here combined with dynamic import below.
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('initial fetch', () => {
    it('starts with isLoading true and empty collections when no cache exists', async () => {
      // Reset module so cachedCollections starts as null
      vi.resetModules();
      // Re-mock after reset
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;

      freshGet.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });

      const { result } = renderHook(() => hook());

      // Before the async fetch completes: loading + empty
      expect(result.current.isLoading).toBe(true);
      expect(result.current.collections).toEqual([]);

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.collections).toEqual(MOCK_COLLECTIONS);
      expect(freshGet).toHaveBeenCalledWith('/v2/bookmark-collections');
    });

    it('sets isLoading to false and collections remain empty on API failure', async () => {
      vi.resetModules();
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;
      const { logError: freshLogError } = await import('@/lib/logger');
      const mockLogError = freshLogError as ReturnType<typeof vi.fn>;

      freshGet.mockRejectedValueOnce(new Error('Network error'));

      const { result } = renderHook(() => hook());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.collections).toEqual([]);
      expect(mockLogError).toHaveBeenCalledWith(
        'Failed to fetch bookmark collections',
        expect.any(Error)
      );
    });

    it('does not fetch again when success is false', async () => {
      vi.resetModules();
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;

      freshGet.mockResolvedValueOnce({ success: false, data: null });

      const { result } = renderHook(() => hook());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // When success=false, collections should remain empty (cache not populated)
      expect(result.current.collections).toEqual([]);
    });
  });

  describe('fetchCollections (manual refetch)', () => {
    it('re-fetches and updates collections', async () => {
      vi.resetModules();
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;

      const firstBatch: BookmarkCollection[] = [
        { id: 1, name: 'A', description: null, is_default: true, bookmarks_count: 0 },
      ];
      const secondBatch: BookmarkCollection[] = [
        { id: 1, name: 'A', description: null, is_default: true, bookmarks_count: 0 },
        { id: 3, name: 'B', description: null, is_default: false, bookmarks_count: 1 },
      ];

      freshGet.mockResolvedValueOnce({ success: true, data: firstBatch });

      const { result } = renderHook(() => hook());

      await waitFor(() => expect(result.current.isLoading).toBe(false));
      expect(result.current.collections).toEqual(firstBatch);

      freshGet.mockResolvedValueOnce({ success: true, data: secondBatch });

      await act(async () => {
        await result.current.fetchCollections();
      });

      expect(result.current.collections).toEqual(secondBatch);
      expect(freshGet).toHaveBeenCalledTimes(2);
    });
  });

  describe('createCollection', () => {
    it('posts to the API and appends the new collection to state', async () => {
      vi.resetModules();
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;
      const freshPost = freshApi.post as ReturnType<typeof vi.fn>;

      freshGet.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });

      const newCollection: BookmarkCollection = {
        id: 99,
        name: 'New',
        description: null,
        is_default: false,
        bookmarks_count: 0,
      };
      freshPost.mockResolvedValueOnce({ success: true, data: newCollection });

      const { result } = renderHook(() => hook());
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let created: BookmarkCollection | null = null;
      await act(async () => {
        created = await result.current.createCollection('New');
      });

      expect(created).toEqual(newCollection);
      expect(freshPost).toHaveBeenCalledWith('/v2/bookmark-collections', { name: 'New' });
      expect(result.current.collections).toContainEqual(newCollection);
      expect(result.current.collections).toHaveLength(MOCK_COLLECTIONS.length + 1);
    });

    it('returns null and logs when createCollection API call fails', async () => {
      vi.resetModules();
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;
      const freshPost = freshApi.post as ReturnType<typeof vi.fn>;
      const { logError: freshLogError } = await import('@/lib/logger');
      const mockLogError = freshLogError as ReturnType<typeof vi.fn>;

      freshGet.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });
      freshPost.mockRejectedValueOnce(new Error('Server error'));

      const { result } = renderHook(() => hook());
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let created: BookmarkCollection | null | undefined;
      await act(async () => {
        created = await result.current.createCollection('FailMe');
      });

      expect(created).toBeNull();
      expect(mockLogError).toHaveBeenCalledWith(
        'Failed to create bookmark collection',
        expect.any(Error)
      );
    });

    it('returns null when API responds success:false', async () => {
      vi.resetModules();
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;
      const freshPost = freshApi.post as ReturnType<typeof vi.fn>;

      freshGet.mockResolvedValueOnce({ success: true, data: MOCK_COLLECTIONS });
      freshPost.mockResolvedValueOnce({ success: false, data: null });

      const { result } = renderHook(() => hook());
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
      vi.resetModules();
      vi.doMock('@/lib/api', () => ({
        api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
      }));
      vi.doMock('@/lib/logger', () => ({ logError: vi.fn() }));

      const { useBookmarkCollections: hook } = await import('./useBookmarkCollections');
      const { api: freshApi } = await import('@/lib/api');
      const freshGet = freshApi.get as ReturnType<typeof vi.fn>;
      freshGet.mockResolvedValueOnce({ success: true, data: [] });

      const { result } = renderHook(() => hook());

      expect(typeof result.current.collections).toBe('object');
      expect(typeof result.current.isLoading).toBe('boolean');
      expect(typeof result.current.fetchCollections).toBe('function');
      expect(typeof result.current.createCollection).toBe('function');
    });
  });
});
