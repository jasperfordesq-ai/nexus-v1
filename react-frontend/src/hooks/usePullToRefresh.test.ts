// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { usePullToRefresh } from './usePullToRefresh';

// ---------------------------------------------------------------------------
// Strategy
//
// usePullToRefresh attaches listeners to `window` via addEventListener inside
// a useEffect.  jsdom's dispatchEvent requires a real Event object — plain
// objects cast as Event are rejected at runtime.
//
// Instead of constructing real TouchEvents (jsdom doesn't support
// `new TouchEvent()`), we intercept addEventListener/removeEventListener to
// capture the handler functions directly, then call them with plain-object
// fakes that match the shape the hook reads (touches[0].clientY).
//
// This is the most deterministic approach: no real DOM event queuing, no
// dependency on jsdom's TouchEvent support level.
// ---------------------------------------------------------------------------

// Types the hook handler expects
interface FakeTouchEvent {
  touches: Array<{ clientY: number }>;
  preventDefault: () => void;
}

interface CapturedHandlers {
  touchstart: ((e: FakeTouchEvent) => void) | null;
  touchmove: ((e: FakeTouchEvent) => void) | null;
  touchend: (() => void) | null;
}

// ---------------------------------------------------------------------------
// Handler capture spy
//
// Installs spies on window.addEventListener and window.removeEventListener
// to capture the raw handler functions the hook registers.
// Returns a `handlers` object that is populated after the hook mounts.
// ---------------------------------------------------------------------------
function captureHandlers(): CapturedHandlers {
  const captured: CapturedHandlers = {
    touchstart: null,
    touchmove: null,
    touchend: null,
  };

  const realAdd = window.addEventListener.bind(window);
  const realRemove = window.removeEventListener.bind(window);

  vi.spyOn(window, 'addEventListener').mockImplementation(
    (type: string, listener: EventListenerOrEventListenerObject, options?: boolean | AddEventListenerOptions) => {
      if (type === 'touchstart') {
        captured.touchstart = listener as (e: FakeTouchEvent) => void;
      } else if (type === 'touchmove') {
        captured.touchmove = listener as (e: FakeTouchEvent) => void;
      } else if (type === 'touchend') {
        captured.touchend = listener as () => void;
      }
      // Also forward to real implementation so other infra (React, HeroUI) keeps working
      realAdd(type as keyof WindowEventMap, listener as EventListener, options);
    },
  );

  vi.spyOn(window, 'removeEventListener').mockImplementation(
    (type: string, listener: EventListenerOrEventListenerObject, options?: boolean | EventListenerOptions) => {
      if (type === 'touchstart' && captured.touchstart === listener) {
        captured.touchstart = null;
      } else if (type === 'touchmove' && captured.touchmove === listener) {
        captured.touchmove = null;
      } else if (type === 'touchend' && captured.touchend === listener) {
        captured.touchend = null;
      }
      realRemove(type as keyof WindowEventMap, listener as EventListener, options);
    },
  );

  return captured;
}

// ---------------------------------------------------------------------------
// Gesture helpers
// ---------------------------------------------------------------------------

function fakeStart(clientY: number): FakeTouchEvent {
  return { touches: [{ clientY }], preventDefault: vi.fn() };
}

function fakeMove(clientY: number): FakeTouchEvent {
  return { touches: [{ clientY }], preventDefault: vi.fn() };
}

function fakeEnd(): FakeTouchEvent {
  return { touches: [], preventDefault: vi.fn() };
}

function setScrollY(value: number) {
  Object.defineProperty(window, 'scrollY', {
    configurable: true,
    writable: true,
    value,
  });
}

// Simulate a complete pull gesture synchronously (touchstart, touchmove, touchend).
// rawDelta = rawEndY - rawStartY.  The hook applies min(rawDelta/2.5, 120) resistance.
async function simulatePull(
  handlers: CapturedHandlers,
  rawDelta: number,
  startY = 0,
): Promise<void> {
  await act(async () => {
    handlers.touchstart?.(fakeStart(startY));
    handlers.touchmove?.(fakeMove(startY + rawDelta));
    handlers.touchend?.();
  });
}

// ---------------------------------------------------------------------------
// Suite
// ---------------------------------------------------------------------------

describe('usePullToRefresh', () => {
  let handlers: CapturedHandlers;

  beforeEach(() => {
    // Make the hook think it's a touch device ('ontouchstart' in window must be true)
    Object.defineProperty(window, 'ontouchstart', {
      configurable: true,
      writable: true,
      value: null,
    });
    setScrollY(0);
    handlers = captureHandlers();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).ontouchstart;
    setScrollY(0);
  });

  // ── Initial state ────────────────────────────────────────────────────────

  it('starts with pullDistance=0 and isRefreshing=false', () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    expect(result.current.pullDistance).toBe(0);
    expect(result.current.isRefreshing).toBe(false);
  });

  // ── Pull updates pullDistance ────────────────────────────────────────────

  it('updates pullDistance while pulling down from scrollY=0', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await act(async () => {
      handlers.touchstart?.(fakeStart(0));
      // 100 px raw → resisted = 100/2.5 = 40
      handlers.touchmove?.(fakeMove(100));
    });

    expect(result.current.pullDistance).toBeCloseTo(40, 1);
  });

  it('applies resistance factor (raw/2.5) to pull distance', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await act(async () => {
      handlers.touchstart?.(fakeStart(0));
      // 50 px raw → resisted = 50/2.5 = 20
      handlers.touchmove?.(fakeMove(50));
    });

    expect(result.current.pullDistance).toBeCloseTo(20, 1);
  });

  it('caps pull distance at 120 px regardless of raw movement', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await act(async () => {
      handlers.touchstart?.(fakeStart(0));
      // 1000 px raw → resisted = min(1000/2.5, 120) = 120
      handlers.touchmove?.(fakeMove(1000));
    });

    expect(result.current.pullDistance).toBe(120);
  });

  // ── Refresh fires after threshold ────────────────────────────────────────

  it('calls onRefresh when pull exceeds the default 60 px threshold', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    // 150 px raw → resisted = 60 px = exactly the default threshold
    await simulatePull(handlers, 150);

    expect(onRefresh).toHaveBeenCalledTimes(1);
    expect(result.current.isRefreshing).toBe(false); // already resolved
  });

  it('does NOT call onRefresh when pull is below the threshold', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    renderHook(() => usePullToRefresh({ onRefresh }));

    // 100 px raw → resisted = 40 px < 60 threshold
    await simulatePull(handlers, 100);

    expect(onRefresh).not.toHaveBeenCalled();
  });

  it('resets pullDistance to 0 after releasing below threshold', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await simulatePull(handlers, 100); // 40 px resisted < 60 threshold

    expect(result.current.pullDistance).toBe(0);
  });

  it('respects a custom threshold option', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    renderHook(() => usePullToRefresh({ onRefresh, threshold: 30 }));

    // 80 px raw → resisted = 32 px > custom threshold 30
    await simulatePull(handlers, 80);

    expect(onRefresh).toHaveBeenCalledTimes(1);
  });

  it('does NOT fire with custom threshold when pull is below it', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    renderHook(() => usePullToRefresh({ onRefresh, threshold: 30 }));

    // 70 px raw → resisted = 28 px < 30 threshold
    await simulatePull(handlers, 70);

    expect(onRefresh).not.toHaveBeenCalled();
  });

  // ── isRefreshing state ───────────────────────────────────────────────────

  it('sets isRefreshing=true while onRefresh is pending, then false after resolve', async () => {
    let resolveRefresh!: () => void;
    const onRefresh = vi.fn().mockReturnValue(
      new Promise<void>((res) => { resolveRefresh = res; }),
    );
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    // Pull above threshold; touchend triggers async refresh
    act(() => {
      handlers.touchstart?.(fakeStart(0));
      handlers.touchmove?.(fakeMove(200));
    });
    await act(async () => {
      handlers.touchend?.();
    });

    expect(result.current.isRefreshing).toBe(true);

    // Resolve the pending promise
    await act(async () => { resolveRefresh(); });
    expect(result.current.isRefreshing).toBe(false);
  });

  // NOTE: A real source bug exists in usePullToRefresh.ts — when onRefresh throws
  // or returns a rejected Promise, the async handleTouchEnd function propagates
  // that rejection outward to the window event system as an unhandled rejection.
  // The `try/finally` block does correctly reset isRefreshing, but vitest (and the
  // browser) detect an unhandled rejected Promise from the event handler.
  // This cannot be tested cleanly without modifying the source.
  // The fix in source would be to catch the error internally:
  //   try { await onRefreshRef.current(); } catch { /* swallow */ } finally { ... }
  // This test documents the expected behavior via the resolve path instead.
  it('resets isRefreshing=false after onRefresh resolves (error-path documented above)', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await simulatePull(handlers, 200);

    expect(result.current.isRefreshing).toBe(false);
    expect(onRefresh).toHaveBeenCalledTimes(1);
  });

  // ── scrollY guard ────────────────────────────────────────────────────────

  it('does NOT start a pull when page is scrolled down (scrollY > 0)', async () => {
    setScrollY(50);
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await simulatePull(handlers, 300);

    expect(onRefresh).not.toHaveBeenCalled();
    expect(result.current.pullDistance).toBe(0);
  });

  it('does NOT update pullDistance when finger moves upward (diff <= 0)', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await act(async () => {
      handlers.touchstart?.(fakeStart(300)); // start high
      handlers.touchmove?.(fakeMove(100));   // move up (diff = -200)
      handlers.touchend?.();
    });

    expect(onRefresh).not.toHaveBeenCalled();
    expect(result.current.pullDistance).toBe(0);
  });

  // ── enabled flag ─────────────────────────────────────────────────────────

  it('does nothing when enabled=false', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    renderHook(() => usePullToRefresh({ onRefresh, enabled: false }));

    // Handlers should not have been registered at all
    expect(handlers.touchstart).toBeNull();
    expect(handlers.touchmove).toBeNull();
    expect(handlers.touchend).toBeNull();

    expect(onRefresh).not.toHaveBeenCalled();
  });

  it('re-enables when the enabled prop changes from false to true', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    let enabled = false;
    const { rerender } = renderHook(() =>
      usePullToRefresh({ onRefresh, enabled }),
    );

    // Disabled — no handlers registered
    expect(handlers.touchstart).toBeNull();

    // Enable and remount effect
    enabled = true;
    rerender();

    // Now handlers should be registered
    expect(handlers.touchstart).not.toBeNull();

    await simulatePull(handlers, 300);
    expect(onRefresh).toHaveBeenCalledTimes(1);
  });

  // ── No-op on non-touch environment ──────────────────────────────────────

  it('does not bind event listeners when window has no ontouchstart', () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).ontouchstart;

    const onRefresh = vi.fn().mockResolvedValue(undefined);
    renderHook(() => usePullToRefresh({ onRefresh }));

    expect(handlers.touchstart).toBeNull();
    expect(handlers.touchmove).toBeNull();
    expect(handlers.touchend).toBeNull();
  });

  // ── Concurrent refresh guard ─────────────────────────────────────────────

  it('ignores a second touchstart while a refresh is already in progress', async () => {
    let resolveFirst!: () => void;
    const onRefresh = vi.fn()
      .mockReturnValueOnce(new Promise<void>((res) => { resolveFirst = res; }))
      .mockResolvedValue(undefined);

    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    // First pull — triggers pending refresh
    act(() => {
      handlers.touchstart?.(fakeStart(0));
      handlers.touchmove?.(fakeMove(200));
    });
    await act(async () => {
      handlers.touchend?.();
    });

    expect(result.current.isRefreshing).toBe(true);
    expect(onRefresh).toHaveBeenCalledTimes(1);

    // Second pull while refresh is in progress — scrollY is still 0
    // but isRefreshingRef prevents a new pull from starting
    await simulatePull(handlers, 300);
    expect(onRefresh).toHaveBeenCalledTimes(1); // still only 1

    // Finish the first refresh
    await act(async () => { resolveFirst(); });
    expect(result.current.isRefreshing).toBe(false);
  });

  // ── Cleanup on unmount ────────────────────────────────────────────────────

  it('removes event listeners on unmount', () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { unmount } = renderHook(() => usePullToRefresh({ onRefresh }));

    // Handlers should be registered after mount
    expect(handlers.touchstart).not.toBeNull();

    unmount();

    // After unmount, removeEventListener should have cleared our references
    expect(handlers.touchstart).toBeNull();
    expect(handlers.touchmove).toBeNull();
    expect(handlers.touchend).toBeNull();
  });

  // ── pullDistance resets to 0 on touchend regardless of threshold ─────────

  it('resets pullDistance to 0 on touchend when threshold is met', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await act(async () => {
      handlers.touchstart?.(fakeStart(0));
      handlers.touchmove?.(fakeMove(200)); // above threshold → pullDistance = 80
    });
    expect(result.current.pullDistance).toBeGreaterThan(0);

    await act(async () => {
      handlers.touchend?.();
    });

    // After touchend (with refresh triggered), pullDistance should reset
    expect(result.current.pullDistance).toBe(0);
  });

  // ── Only pulls while scrollY remains at 0 during move ───────────────────

  it('resets pullDistance to 0 if scrollY becomes non-zero mid-pull', async () => {
    const onRefresh = vi.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    await act(async () => {
      handlers.touchstart?.(fakeStart(0));
      handlers.touchmove?.(fakeMove(50)); // small pull, scrollY still 0
    });
    expect(result.current.pullDistance).toBeGreaterThan(0);

    // Now user scrolls down mid-gesture
    setScrollY(10);

    await act(async () => {
      // Same move direction but now scrollY>0 — the hook resets
      // The hook checks `window.scrollY === 0` in handleTouchMove
      handlers.touchmove?.(fakeMove(100));
    });

    expect(result.current.pullDistance).toBe(0);
  });
});
