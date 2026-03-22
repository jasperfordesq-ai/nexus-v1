// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useHeaderScroll } from './useHeaderScroll';

describe('useHeaderScroll', () => {
  let rafCallbacks: FrameRequestCallback[] = [];

  beforeEach(() => {
    // Mock requestAnimationFrame to execute synchronously
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb) => {
      rafCallbacks.push(cb);
      return rafCallbacks.length;
    });
    Object.defineProperty(window, 'scrollY', { value: 0, writable: true, configurable: true });
  });

  afterEach(() => {
    vi.restoreAllMocks();
    rafCallbacks = [];
  });

  function flushRaf() {
    const cbs = [...rafCallbacks];
    rafCallbacks = [];
    cbs.forEach((cb) => cb(0));
  }

  function setScrollY(y: number) {
    Object.defineProperty(window, 'scrollY', { value: y, writable: true, configurable: true });
  }

  it('returns default state on mount', () => {
    const { result } = renderHook(() => useHeaderScroll());
    expect(result.current.isScrolled).toBe(false);
    expect(result.current.isUtilityBarVisible).toBe(true);
    expect(result.current.scrollDirection).toBeNull();
  });

  it('marks isScrolled true when scrollY exceeds threshold', () => {
    const { result } = renderHook(() => useHeaderScroll(48));
    act(() => {
      setScrollY(100);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    expect(result.current.isScrolled).toBe(true);
  });

  it('does not update for micro-jitter less than 5px', () => {
    const { result } = renderHook(() => useHeaderScroll(48));
    act(() => {
      setScrollY(3);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    // Should not update — delta < 5
    expect(result.current.isScrolled).toBe(false);
    expect(result.current.scrollDirection).toBeNull();
  });

  it('sets scrollDirection to down when scrolling down', () => {
    const { result } = renderHook(() => useHeaderScroll(48));
    act(() => {
      setScrollY(60);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    expect(result.current.scrollDirection).toBe('down');
  });

  it('sets scrollDirection to up when scrolling up', () => {
    const { result } = renderHook(() => useHeaderScroll(48));
    // First scroll down
    act(() => {
      setScrollY(100);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    // Then scroll up
    act(() => {
      setScrollY(60);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    expect(result.current.scrollDirection).toBe('up');
  });

  it('hides utility bar when scrolling down past threshold', () => {
    const { result } = renderHook(() => useHeaderScroll(48));
    act(() => {
      setScrollY(100);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    expect(result.current.isUtilityBarVisible).toBe(false);
  });

  it('shows utility bar when scrolling up', () => {
    const { result } = renderHook(() => useHeaderScroll(48));
    act(() => {
      setScrollY(100);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    act(() => {
      setScrollY(50);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    expect(result.current.isUtilityBarVisible).toBe(true);
  });

  it('respects custom threshold', () => {
    const { result } = renderHook(() => useHeaderScroll(200));
    act(() => {
      setScrollY(100);
      window.dispatchEvent(new Event('scroll'));
      flushRaf();
    });
    // 100 < 200 threshold, should not be scrolled
    expect(result.current.isScrolled).toBe(false);
  });

  it('removes scroll event listener on unmount', () => {
    const removeSpy = vi.spyOn(window, 'removeEventListener');
    const { unmount } = renderHook(() => useHeaderScroll());
    unmount();
    expect(removeSpy).toHaveBeenCalledWith('scroll', expect.any(Function));
  });
});
