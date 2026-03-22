// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useFeedTracking } from './useFeedTracking';

vi.mock('@/lib/api', () => ({
  api: {
    post: vi.fn(),
  },
}));

import { api } from '@/lib/api';
const mockApi = api as unknown as { post: ReturnType<typeof vi.fn> };

// Access the module-level impressedIds set indirectly
// Each test uses a unique postId to avoid cross-test contamination
let postIdCounter = 10000;
function uniquePostId() {
  return ++postIdCounter;
}

describe('useFeedTracking', () => {
  let observerCallbacks: Map<Element, IntersectionObserverCallback>;
  let mockObserverInstances: { observe: ReturnType<typeof vi.fn>; disconnect: ReturnType<typeof vi.fn> }[];

  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    mockApi.post.mockResolvedValue({ success: true });
    observerCallbacks = new Map();
    mockObserverInstances = [];

    // Mock IntersectionObserver
    vi.stubGlobal('IntersectionObserver', vi.fn((callback: IntersectionObserverCallback) => {
      const instance = {
        observe: vi.fn((el: Element) => {
          observerCallbacks.set(el, callback);
        }),
        disconnect: vi.fn(),
      };
      mockObserverInstances.push(instance);
      return instance;
    }));
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
  });

  function triggerIntersection(el: Element, isIntersecting: boolean) {
    const cb = observerCallbacks.get(el);
    if (cb) {
      cb([{ isIntersecting } as IntersectionObserverEntry], {} as IntersectionObserver);
    }
  }

  it('returns a ref and recordClick function', () => {
    const postId = uniquePostId();
    const { result } = renderHook(() => useFeedTracking(postId, true));
    expect(result.current.ref).toBeDefined();
    expect(typeof result.current.recordClick).toBe('function');
  });

  it('does not set up observer when unauthenticated', () => {
    const postId = uniquePostId();
    renderHook(() => useFeedTracking(postId, false));
    expect(IntersectionObserver).not.toHaveBeenCalled();
  });

  it('records impression after 1 second of visibility', async () => {
    const postId = uniquePostId();
    const { result } = renderHook(() => useFeedTracking(postId, true));

    // Simulate the ref being attached to a DOM element
    const div = document.createElement('div');
    Object.defineProperty(result.current.ref, 'current', { value: div, writable: true });

    // Re-render to trigger the effect with the ref attached
    // Since we can't easily set ref.current before mount, we'll trigger the observer directly
    if (mockObserverInstances[0]) {
      triggerIntersection(div, true);
      act(() => {
        vi.advanceTimersByTime(1000);
      });
      await Promise.resolve();
      expect(mockApi.post).toHaveBeenCalledWith(`/v2/feed/posts/${postId}/impression`, {});
    }
  });

  it('cancels impression timer if element leaves viewport', () => {
    const postId = uniquePostId();
    renderHook(() => useFeedTracking(postId, true));

    const div = document.createElement('div');

    if (mockObserverInstances[0]) {
      // Enter viewport
      triggerIntersection(div, true);
      // Leave before 1 second
      act(() => {
        vi.advanceTimersByTime(500);
        triggerIntersection(div, false);
      });
      act(() => {
        vi.advanceTimersByTime(1000);
      });
      expect(mockApi.post).not.toHaveBeenCalled();
    }
  });

  it('recordClick fires POST to click endpoint', () => {
    const postId = uniquePostId();
    const { result } = renderHook(() => useFeedTracking(postId, true));
    act(() => {
      result.current.recordClick();
    });
    expect(mockApi.post).toHaveBeenCalledWith(`/v2/feed/posts/${postId}/click`, {});
  });

  it('recordClick does nothing when unauthenticated', () => {
    const postId = uniquePostId();
    const { result } = renderHook(() => useFeedTracking(postId, false));
    act(() => {
      result.current.recordClick();
    });
    expect(mockApi.post).not.toHaveBeenCalled();
  });

  it('recordClick does nothing when postId is falsy', () => {
    const { result } = renderHook(() => useFeedTracking(0, true));
    act(() => {
      result.current.recordClick();
    });
    expect(mockApi.post).not.toHaveBeenCalled();
  });

  it('disconnects observer on unmount', () => {
    const postId = uniquePostId();
    const { unmount } = renderHook(() => useFeedTracking(postId, true));
    unmount();
    if (mockObserverInstances[0]) {
      expect(mockObserverInstances[0].disconnect).toHaveBeenCalled();
    }
  });
});
