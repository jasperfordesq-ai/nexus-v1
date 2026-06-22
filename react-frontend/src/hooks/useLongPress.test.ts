// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useLongPress } from './useLongPress';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Build a minimal React.TouchEvent-like object that the hook reads. */
function makeTouchEvent(
  clientX: number,
  clientY: number,
  targetTag = 'DIV',
): Record<string, unknown> {
  const target = {
    tagName: targetTag,
    isContentEditable: false,
    closest: () => null,
  } as unknown as HTMLElement;
  return {
    target,
    touches: [{ clientX, clientY }],
    preventDefault: vi.fn(),
  };
}

// ---------------------------------------------------------------------------
// Suite
// ---------------------------------------------------------------------------

describe('useLongPress', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  // ── Basic fire-after-delay ──────────────────────────────────────────────

  it('fires onLongPress after the default 500 ms delay', async () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });

    // Just before threshold — must not fire yet
    act(() => { vi.advanceTimersByTime(499); });
    expect(onLongPress).not.toHaveBeenCalled();

    // Exactly at threshold — must fire
    act(() => { vi.advanceTimersByTime(1); });
    expect(onLongPress).toHaveBeenCalledTimes(1);
  });

  it('respects a custom delay option', async () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress, delay: 1000 }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });

    act(() => { vi.advanceTimersByTime(999); });
    expect(onLongPress).not.toHaveBeenCalled();

    act(() => { vi.advanceTimersByTime(1); });
    expect(onLongPress).toHaveBeenCalledTimes(1);
  });

  // ── Cancellation via touchEnd ───────────────────────────────────────────

  it('does NOT fire if touchEnd occurs before the delay elapses', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(400); });

    act(() => {
      result.current.onTouchEnd();
    });

    // Advance well past normal delay — timer was cancelled so callback stays silent
    act(() => { vi.advanceTimersByTime(600); });
    expect(onLongPress).not.toHaveBeenCalled();
  });

  // ── Cancellation via movement ───────────────────────────────────────────

  it('cancels when finger moves beyond the default 10 px threshold (x-axis)', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(100, 100) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(200); });

    // Move 11 px to the right — exceeds moveThreshold
    act(() => {
      result.current.onTouchMove(makeTouchEvent(111, 100) as unknown as React.TouchEvent);
    });

    act(() => { vi.advanceTimersByTime(400); });
    expect(onLongPress).not.toHaveBeenCalled();
  });

  it('cancels when finger moves beyond the threshold on the y-axis', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(50, 50) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(200); });

    act(() => {
      result.current.onTouchMove(makeTouchEvent(50, 62) as unknown as React.TouchEvent);
    });

    act(() => { vi.advanceTimersByTime(400); });
    expect(onLongPress).not.toHaveBeenCalled();
  });

  it('does NOT cancel when movement is within the threshold (≤ 10 px)', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(50, 50) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(100); });

    // Move only 5 px — below threshold
    act(() => {
      result.current.onTouchMove(makeTouchEvent(55, 50) as unknown as React.TouchEvent);
    });

    act(() => { vi.advanceTimersByTime(400); });
    expect(onLongPress).toHaveBeenCalledTimes(1);
  });

  it('respects a custom moveThreshold option', () => {
    const onLongPress = vi.fn();
    // Tight threshold of 3 px
    const { result } = renderHook(() => useLongPress({ onLongPress, moveThreshold: 3 }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(200); });

    // 4 px — exceeds the custom 3 px threshold
    act(() => {
      result.current.onTouchMove(makeTouchEvent(4, 0) as unknown as React.TouchEvent);
    });

    act(() => { vi.advanceTimersByTime(400); });
    expect(onLongPress).not.toHaveBeenCalled();
  });

  // ── Form-input guard ────────────────────────────────────────────────────

  it('does not start the timer when touch target is an INPUT', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0, 'INPUT') as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(1000); });

    expect(onLongPress).not.toHaveBeenCalled();
  });

  it('does not start the timer when touch target is a TEXTAREA', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0, 'TEXTAREA') as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(1000); });

    expect(onLongPress).not.toHaveBeenCalled();
  });

  it('does not start the timer when touch target is a SELECT', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0, 'SELECT') as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(1000); });

    expect(onLongPress).not.toHaveBeenCalled();
  });

  it('does not start the timer when touch target is contentEditable', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    const editableEvent = {
      target: {
        tagName: 'DIV',
        isContentEditable: true,
        closest: () => null,
      } as unknown as HTMLElement,
      touches: [{ clientX: 0, clientY: 0 }],
      preventDefault: vi.fn(),
    };

    act(() => {
      result.current.onTouchStart(editableEvent as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(1000); });

    expect(onLongPress).not.toHaveBeenCalled();
  });

  it('does not start the timer when touch target is inside a role=textbox element', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    const textboxEvent = {
      target: {
        tagName: 'SPAN',
        isContentEditable: false,
        closest: (selector: string) => selector === '[role="textbox"]' ? {} : null,
      } as unknown as HTMLElement,
      touches: [{ clientX: 0, clientY: 0 }],
      preventDefault: vi.fn(),
    };

    act(() => {
      result.current.onTouchStart(textboxEvent as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(1000); });

    expect(onLongPress).not.toHaveBeenCalled();
  });

  // ── onTouchMove is a no-op when no timer is running ────────────────────

  it('onTouchMove is a no-op when no timer is active (no prior touchStart)', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    // Should not throw
    act(() => {
      result.current.onTouchMove(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(1000); });

    expect(onLongPress).not.toHaveBeenCalled();
  });

  // ── Rapid repeat (second press after first fires) ───────────────────────

  it('fires again on a subsequent long-press after the first completed', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    // First press
    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(500); });
    expect(onLongPress).toHaveBeenCalledTimes(1);

    // Second press
    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(500); });
    expect(onLongPress).toHaveBeenCalledTimes(2);
  });

  // ── Callback reference is always fresh ─────────────────────────────────

  it('always calls the latest onLongPress reference even when it changes between renders', () => {
    const first = vi.fn();
    const second = vi.fn();
    let callback = first;

    const { result, rerender } = renderHook(() =>
      useLongPress({ onLongPress: callback }),
    );

    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(200); });

    // Swap callback before the timer fires
    callback = second;
    rerender();

    act(() => { vi.advanceTimersByTime(300); });

    expect(first).not.toHaveBeenCalled();
    expect(second).toHaveBeenCalledTimes(1);
  });

  // ── No touches in event ─────────────────────────────────────────────────

  it('does nothing when the touches array is empty on touchStart', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    const emptyTouchEvent = {
      target: {
        tagName: 'DIV',
        isContentEditable: false,
        closest: () => null,
      } as unknown as HTMLElement,
      touches: [],
      preventDefault: vi.fn(),
    };

    act(() => {
      result.current.onTouchStart(emptyTouchEvent as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(1000); });

    expect(onLongPress).not.toHaveBeenCalled();
  });

  it('does nothing when the touches array is empty on touchMove', () => {
    const onLongPress = vi.fn();
    const { result } = renderHook(() => useLongPress({ onLongPress }));

    // Start a valid press
    act(() => {
      result.current.onTouchStart(makeTouchEvent(0, 0) as unknown as React.TouchEvent);
    });
    act(() => { vi.advanceTimersByTime(200); });

    // Move with empty touches — should not throw or cancel
    const emptyMoveEvent = {
      target: { tagName: 'DIV', isContentEditable: false, closest: () => null } as unknown as HTMLElement,
      touches: [],
      preventDefault: vi.fn(),
    };
    act(() => {
      result.current.onTouchMove(emptyMoveEvent as unknown as React.TouchEvent);
    });

    // Timer should still fire
    act(() => { vi.advanceTimersByTime(300); });
    expect(onLongPress).toHaveBeenCalledTimes(1);
  });
});
