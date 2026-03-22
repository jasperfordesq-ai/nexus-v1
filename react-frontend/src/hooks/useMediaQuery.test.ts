// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useMediaQuery } from './useMediaQuery';

describe('useMediaQuery', () => {
  type MockMQL = {
    matches: boolean;
    addEventListener: ReturnType<typeof vi.fn>;
    removeEventListener: ReturnType<typeof vi.fn>;
    _trigger: (matches: boolean) => void;
  };

  let mockMql: MockMQL;
  let handlers: Array<(e: { matches: boolean }) => void>;

  beforeEach(() => {
    handlers = [];
    mockMql = {
      matches: false,
      addEventListener: vi.fn((_event: string, handler: (e: { matches: boolean }) => void) => {
        handlers.push(handler);
      }),
      removeEventListener: vi.fn(),
      _trigger: (matches: boolean) => {
        mockMql.matches = matches;
        handlers.forEach((h) => h({ matches }));
      },
    };
    vi.spyOn(window, 'matchMedia').mockReturnValue(mockMql as unknown as MediaQueryList);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('returns false when query does not match initially', () => {
    mockMql.matches = false;
    const { result } = renderHook(() => useMediaQuery('(min-width: 768px)'));
    expect(result.current).toBe(false);
  });

  it('returns true when query matches initially', () => {
    mockMql.matches = true;
    const { result } = renderHook(() => useMediaQuery('(min-width: 768px)'));
    expect(result.current).toBe(true);
  });

  it('updates when media query changes', () => {
    mockMql.matches = false;
    const { result } = renderHook(() => useMediaQuery('(min-width: 768px)'));
    expect(result.current).toBe(false);

    act(() => {
      mockMql._trigger(true);
    });
    expect(result.current).toBe(true);
  });

  it('changes back to false when query stops matching', () => {
    mockMql.matches = true;
    const { result } = renderHook(() => useMediaQuery('(max-width: 1024px)'));
    expect(result.current).toBe(true);

    act(() => {
      mockMql._trigger(false);
    });
    expect(result.current).toBe(false);
  });

  it('calls matchMedia with correct query string', () => {
    renderHook(() => useMediaQuery('(prefers-color-scheme: dark)'));
    expect(window.matchMedia).toHaveBeenCalledWith('(prefers-color-scheme: dark)');
  });

  it('removes event listener on unmount', () => {
    const { unmount } = renderHook(() => useMediaQuery('(min-width: 768px)'));
    unmount();
    expect(mockMql.removeEventListener).toHaveBeenCalledWith('change', expect.any(Function));
  });

  it('re-subscribes when query changes', () => {
    let query = '(min-width: 768px)';
    const { rerender } = renderHook(() => useMediaQuery(query));

    query = '(max-width: 1024px)';
    rerender();

    // Should have called matchMedia 3 times:
    // 1. useState initializer on first mount (query 1)
    // 2. useEffect on mount (query 1)
    // 3. useEffect when query changes (query 2)
    expect(window.matchMedia).toHaveBeenCalledTimes(3);
  });
});
