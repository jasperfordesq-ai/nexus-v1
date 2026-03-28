// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useLinkPreview hook.
 *
 * Covers: URL extraction, debouncing, preview fetching, dismissal,
 * deduplication, and error handling.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useLinkPreview } from './useLinkPreview';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

import { api } from '@/lib/api';
const mockApi = api as unknown as { get: ReturnType<typeof vi.fn> };

describe('useLinkPreview', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('returns empty previews for text without URLs', async () => {
    const { result } = renderHook(() => useLinkPreview('Hello world, no links here'));

    // Advance debounce timer
    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    expect(result.current.previews).toEqual([]);
    expect(result.current.loading).toBe(false);
    expect(mockApi.get).not.toHaveBeenCalled();
  });

  it('fetches preview for a URL in text', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        url: 'https://example.com',
        title: 'Example Domain',
        description: 'Example description',
        image: null,
      },
    });

    const { result } = renderHook(() =>
      useLinkPreview('Check out https://example.com for details')
    );

    // Advance debounce timer
    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    // Wait for the async fetch
    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(mockApi.get).toHaveBeenCalledTimes(1);
    expect(mockApi.get).toHaveBeenCalledWith(
      expect.stringContaining('/v2/link-preview?url='),
      expect.any(Object)
    );
  });

  it('removes preview via removePreview callback', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        url: 'https://example.com',
        title: 'Example',
        description: 'Desc',
        image: null,
      },
    });

    const { result } = renderHook(() =>
      useLinkPreview('Visit https://example.com')
    );

    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    // Now dismiss the preview
    act(() => {
      result.current.removePreview('https://example.com');
    });

    expect(result.current.previews).toEqual([]);
  });

  it('does not refetch already-fetched URLs', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        url: 'https://example.com',
        title: 'Example',
        description: 'Desc',
        image: null,
      },
    });

    const { result, rerender } = renderHook(
      ({ text }) => useLinkPreview(text),
      { initialProps: { text: 'Visit https://example.com' } }
    );

    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    const callCount = mockApi.get.mock.calls.length;

    // Rerender with same URL (e.g., user typed more text)
    rerender({ text: 'Visit https://example.com today' });

    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    // Should not make another API call for the same URL
    expect(mockApi.get).toHaveBeenCalledTimes(callCount);
  });

  it('handles API failure gracefully', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));

    const { result } = renderHook(() =>
      useLinkPreview('Visit https://example.com')
    );

    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    // Should not throw and previews should remain empty
    expect(result.current.previews).toEqual([]);
  });

  it('limits to 3 URLs per fetch batch', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: { url: 'https://example.com', title: 'T', description: 'D', image: null },
    });

    const text = [
      'https://one.com',
      'https://two.com',
      'https://three.com',
      'https://four.com',
      'https://five.com',
    ].join(' ');

    renderHook(() => useLinkPreview(text));

    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    // Should only fetch first 3 URLs
    expect(mockApi.get).toHaveBeenCalledTimes(3);
  });
});
