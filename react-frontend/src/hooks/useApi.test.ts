// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useApi, useMutation, usePaginatedApi hooks
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useApi, useMutation, usePaginatedApi } from './useApi';

// Mock the api module
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

import { api } from '@/lib/api';
const mockApi = api as unknown as {
  get: ReturnType<typeof vi.fn>;
  post: ReturnType<typeof vi.fn>;
  put: ReturnType<typeof vi.fn>;
  patch: ReturnType<typeof vi.fn>;
  delete: ReturnType<typeof vi.fn>;
};

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches data immediately when immediate is true (default)', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: { id: 1, name: 'Test' } });

    const { result } = renderHook(() => useApi<{ id: number; name: string }>('/v2/test'));

    expect(result.current.isLoading).toBe(true);

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.data).toEqual({ id: 1, name: 'Test' });
    expect(result.current.error).toBeNull();
    expect(mockApi.get).toHaveBeenCalledWith('/v2/test');
  });

  it('does not fetch when immediate is false', () => {
    const { result } = renderHook(() =>
      useApi('/v2/test', { immediate: false })
    );

    expect(result.current.isLoading).toBe(false);
    expect(result.current.data).toBeNull();
    expect(mockApi.get).not.toHaveBeenCalled();
  });

  it('handles API errors', async () => {
    mockApi.get.mockResolvedValue({ success: false, error: 'Not found' });

    const { result } = renderHook(() => useApi('/v2/test'));

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.data).toBeNull();
    expect(result.current.error).toBe('Not found');
  });

  it('defaults error message to "Request failed"', async () => {
    mockApi.get.mockResolvedValue({ success: false });

    const { result } = renderHook(() => useApi('/v2/test'));

    await waitFor(() => {
      expect(result.current.error).toBe('Request failed');
    });
  });

  it('resets state', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: 'hello' });

    const { result } = renderHook(() => useApi<string>('/v2/test'));

    await waitFor(() => {
      expect(result.current.data).toBe('hello');
    });

    act(() => {
      result.current.reset();
    });

    expect(result.current.data).toBeNull();
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('allows setting data manually', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: 'initial' });

    const { result } = renderHook(() => useApi<string>('/v2/test'));

    await waitFor(() => {
      expect(result.current.data).toBe('initial');
    });

    act(() => {
      result.current.setData('updated');
    });

    expect(result.current.data).toBe('updated');
  });

  it('re-executes when execute is called', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: 'first' })
      .mockResolvedValueOnce({ success: true, data: 'second' });

    const { result } = renderHook(() =>
      useApi<string>('/v2/test', { immediate: false })
    );

    await act(async () => {
      await result.current.execute();
    });

    expect(result.current.data).toBe('first');

    await act(async () => {
      await result.current.execute();
    });

    expect(result.current.data).toBe('second');
  });
});

describe('useMutation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('starts with no loading and no data', () => {
    const { result } = renderHook(() => useMutation('/v2/test'));
    expect(result.current.isLoading).toBe(false);
    expect(result.current.data).toBeNull();
    expect(result.current.error).toBeNull();
  });

  it('posts data and returns response', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { id: 1 } });

    const { result } = renderHook(() => useMutation<{ id: number }, { name: string }>('/v2/test'));

    await act(async () => {
      await result.current.mutate({ name: 'Test' });
    });

    expect(result.current.data).toEqual({ id: 1 });
    expect(result.current.isLoading).toBe(false);
    expect(mockApi.post).toHaveBeenCalledWith('/v2/test', { name: 'Test' });
  });

  it('uses PUT method', async () => {
    mockApi.put.mockResolvedValue({ success: true, data: 'ok' });

    const { result } = renderHook(() => useMutation('/v2/test', 'put'));

    await act(async () => {
      await result.current.mutate({ value: 42 });
    });

    expect(mockApi.put).toHaveBeenCalledWith('/v2/test', { value: 42 });
  });

  it('uses PATCH method', async () => {
    mockApi.patch.mockResolvedValue({ success: true, data: 'ok' });

    const { result } = renderHook(() => useMutation('/v2/test', 'patch'));

    await act(async () => {
      await result.current.mutate({ value: 42 });
    });

    expect(mockApi.patch).toHaveBeenCalledWith('/v2/test', { value: 42 });
  });

  it('uses DELETE method', async () => {
    mockApi.delete.mockResolvedValue({ success: true, data: null });

    const { result } = renderHook(() => useMutation('/v2/test', 'delete'));

    await act(async () => {
      await result.current.mutate();
    });

    expect(mockApi.delete).toHaveBeenCalledWith('/v2/test');
  });

  it('calls onSuccess callback', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { id: 1 } });
    const onSuccess = vi.fn();

    const { result } = renderHook(() =>
      useMutation('/v2/test', 'post', { onSuccess })
    );

    await act(async () => {
      await result.current.mutate();
    });

    expect(onSuccess).toHaveBeenCalledWith({ id: 1 });
  });

  it('calls onError callback', async () => {
    mockApi.post.mockResolvedValue({ success: false, error: 'Validation failed' });
    const onError = vi.fn();

    const { result } = renderHook(() =>
      useMutation('/v2/test', 'post', { onError })
    );

    await act(async () => {
      await result.current.mutate();
    });

    expect(onError).toHaveBeenCalledWith('Validation failed');
    expect(result.current.error).toBe('Validation failed');
  });

  it('resets mutation state', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { id: 1 } });

    const { result } = renderHook(() => useMutation('/v2/test'));

    await act(async () => {
      await result.current.mutate();
    });

    expect(result.current.data).toEqual({ id: 1 });

    act(() => {
      result.current.reset();
    });

    expect(result.current.data).toBeNull();
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });
});

describe('usePaginatedApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches first page on mount', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        data: [{ id: 1 }, { id: 2 }],
        meta: { current_page: 1, last_page: 3, total: 50 },
      },
    });

    const { result } = renderHook(() => usePaginatedApi('/v2/items'));

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.data.items).toHaveLength(2);
    expect(result.current.data.currentPage).toBe(1);
    expect(result.current.data.totalPages).toBe(3);
    expect(result.current.data.total).toBe(50);
    expect(result.current.data.hasMore).toBe(true);
    expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('page=1'));
  });

  it('loads more items', async () => {
    mockApi.get
      .mockResolvedValueOnce({
        success: true,
        data: {
          data: [{ id: 1 }],
          meta: { current_page: 1, last_page: 2, total: 2 },
        },
      })
      .mockResolvedValueOnce({
        success: true,
        data: {
          data: [{ id: 2 }],
          meta: { current_page: 2, last_page: 2, total: 2 },
        },
      });

    const { result } = renderHook(() => usePaginatedApi('/v2/items'));

    await waitFor(() => {
      expect(result.current.data.items).toHaveLength(1);
    });

    await act(async () => {
      await result.current.loadMore();
    });

    expect(result.current.data.items).toHaveLength(2);
    expect(result.current.data.hasMore).toBe(false);
  });

  it('handles API error', async () => {
    mockApi.get.mockResolvedValue({ success: false, error: 'Server error' });

    const { result } = renderHook(() => usePaginatedApi('/v2/items'));

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.error).toBe('Server error');
    expect(result.current.data.items).toHaveLength(0);
  });

  it('resets state', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        data: [{ id: 1 }],
        meta: { current_page: 1, last_page: 1, total: 1 },
      },
    });

    const { result } = renderHook(() => usePaginatedApi('/v2/items'));

    await waitFor(() => {
      expect(result.current.data.items).toHaveLength(1);
    });

    act(() => {
      result.current.reset();
    });

    expect(result.current.data.items).toHaveLength(0);
    expect(result.current.data.currentPage).toBe(0);
    expect(result.current.data.total).toBe(0);
  });

  it('uses custom page size', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        data: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
      },
    });

    renderHook(() => usePaginatedApi('/v2/items', 10));

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('limit=10'));
    });
  });

  it('appends query params correctly when endpoint has existing params', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        data: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
      },
    });

    renderHook(() => usePaginatedApi('/v2/items?type=offer'));

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('?type=offer&page=1'));
    });
  });
});
