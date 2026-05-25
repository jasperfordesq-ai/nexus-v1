// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(status: number, message: string) { super(message); this.status = status; this.name = 'ApiResponseError'; }
  },
}));

import { renderHook, waitFor, act } from '@testing-library/react-native';
import { usePaginatedApi } from './usePaginatedApi';
import { ApiResponseError } from '@/lib/api/client';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

interface FakeResponse { data: string[]; meta: { cursor: string | null; has_more: boolean } }

function makeResponse(items: string[], cursor: string | null, hasMore: boolean): FakeResponse {
  return { data: items, meta: { cursor, has_more: hasMore } };
}

function extractor(r: FakeResponse) {
  return { items: r.data, cursor: r.meta.cursor, hasMore: r.meta.has_more };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('usePaginatedApi', () => {
  it('starts loading and calls fetchFn with null cursor on mount', async () => {
    const fetchFn = jest.fn().mockResolvedValue(makeResponse(['a', 'b'], null, false));
    const { result } = renderHook(() => usePaginatedApi(fetchFn, extractor));

    expect(result.current.isLoading).toBe(true);
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(fetchFn).toHaveBeenCalledWith(null);
    expect(result.current.items).toEqual(['a', 'b']);
    expect(result.current.error).toBeNull();
  });

  it('sets hasMore from the extractor response', async () => {
    const fetchFn = jest.fn().mockResolvedValue(makeResponse(['a'], 'cursor_1', true));
    const { result } = renderHook(() => usePaginatedApi(fetchFn, extractor));

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.hasMore).toBe(true);
  });

  it('loadMore appends next page using the cursor from the previous response', async () => {
    const fetchFn = jest.fn()
      .mockResolvedValueOnce(makeResponse(['a', 'b'], 'cur_1', true))
      .mockResolvedValueOnce(makeResponse(['c', 'd'], null, false));

    const { result } = renderHook(() => usePaginatedApi(fetchFn, extractor));
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    act(() => { result.current.loadMore(); });
    await waitFor(() => expect(result.current.isLoadingMore).toBe(false));

    expect(fetchFn).toHaveBeenCalledWith('cur_1');
    expect(result.current.items).toEqual(['a', 'b', 'c', 'd']);
    expect(result.current.hasMore).toBe(false);
  });

  it('refresh resets cursor to null and replaces items', async () => {
    const fetchFn = jest.fn()
      .mockResolvedValueOnce(makeResponse(['a', 'b'], 'cur_1', true))
      .mockResolvedValueOnce(makeResponse(['x', 'y'], null, false));

    const { result } = renderHook(() => usePaginatedApi(fetchFn, extractor));
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.items).toEqual(['a', 'b']);

    act(() => { result.current.refresh(); });
    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
      expect(result.current.items).toEqual(['x', 'y']);
    });

    expect(fetchFn).toHaveBeenCalledTimes(2);
    expect(fetchFn).toHaveBeenLastCalledWith(null);
  });

  it('sets ApiResponseError message on API failure', async () => {
    const fetchFn = jest.fn().mockRejectedValue(new ApiResponseError(500, 'Server error'));
    const { result } = renderHook(() => usePaginatedApi(fetchFn, extractor));

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.error).toBe('Server error');
    expect(result.current.items).toEqual([]);
  });

  it('sets generic error message on unexpected failure', async () => {
    const fetchFn = jest.fn().mockRejectedValue(new Error('Network error'));
    const { result } = renderHook(() => usePaginatedApi(fetchFn, extractor));

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.error).toBe('An unexpected error occurred.');
  });

  it('loadMore is a no-op when hasMore is false', async () => {
    const fetchFn = jest.fn().mockResolvedValue(makeResponse(['a'], null, false));
    const { result } = renderHook(() => usePaginatedApi(fetchFn, extractor));

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.hasMore).toBe(false);

    act(() => { result.current.loadMore(); });

    // Still only the initial call — loadMore is a no-op
    expect(fetchFn).toHaveBeenCalledTimes(1);
  });
});
