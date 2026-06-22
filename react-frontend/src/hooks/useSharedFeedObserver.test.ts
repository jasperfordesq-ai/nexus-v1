// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useSharedFeedObserver } from './useSharedFeedObserver';

// ---------------------------------------------------------------------------
// Controllable IntersectionObserver mock
// ---------------------------------------------------------------------------
// The module uses a process-wide singleton map keyed on (rootMargin|threshold).
// We need to:
//   1. Install a mock IntersectionObserver whose callback we can fire manually.
//   2. Reset the module between tests so the observers Map is empty.
//
// After vi.resetModules() we re-import the hook dynamically in each test to
// get a fresh singleton map. The mock class is re-stubbed before each test.

type IoCallback = (entries: IntersectionObserverEntry[]) => void;

let capturedCallback: IoCallback | null = null;
let observeSpy: ReturnType<typeof vi.fn>;
let unobserveSpy: ReturnType<typeof vi.fn>;
let disconnectSpy: ReturnType<typeof vi.fn>;

function installMockIO() {
  observeSpy = vi.fn();
  unobserveSpy = vi.fn();
  disconnectSpy = vi.fn();
  capturedCallback = null;

  class ControllableIntersectionObserver {
    constructor(cb: IoCallback, _options?: IntersectionObserverInit) {
      capturedCallback = cb;
    }
    observe = observeSpy;
    unobserve = unobserveSpy;
    disconnect = disconnectSpy;
    readonly root = null;
    readonly rootMargin = '0px';
    readonly thresholds = [0.5];
    takeRecords = () => [];
  }

  vi.stubGlobal('IntersectionObserver', ControllableIntersectionObserver);
}

/** Fire a fake IntersectionObserver callback with synthetic entries. */
function fireIO(entries: Partial<IntersectionObserverEntry>[]) {
  if (!capturedCallback) throw new Error('No IntersectionObserver callback captured');
  capturedCallback(entries as IntersectionObserverEntry[]);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeElement(): Element {
  return document.createElement('div');
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useSharedFeedObserver', () => {
  beforeEach(() => {
    installMockIO();
    vi.resetModules();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.clearAllMocks();
  });

  // -------------------------------------------------------------------------
  // Returned ref is a callback function
  // -------------------------------------------------------------------------
  it('returns a callback ref function', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));
    expect(typeof result.current).toBe('function');
  });

  // -------------------------------------------------------------------------
  // Observing an element
  // -------------------------------------------------------------------------
  it('calls observer.observe when the ref is attached to a node', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));

    const el = makeElement();
    act(() => {
      result.current(el);
    });

    expect(observeSpy).toHaveBeenCalledWith(el);
  });

  it('does NOT call observer.observe when enabled is false', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry, { enabled: false }));

    const el = makeElement();
    act(() => {
      result.current(el);
    });

    expect(observeSpy).not.toHaveBeenCalled();
  });

  it('does NOT call observer.observe when null is passed as the node', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));

    act(() => {
      result.current(null);
    });

    expect(observeSpy).not.toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // Callback firing
  // -------------------------------------------------------------------------
  it('forwards IntersectionObserver entries to the provided callback', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));

    const el = makeElement();
    act(() => {
      result.current(el);
    });

    const fakeEntry = { target: el, isIntersecting: true } as Partial<IntersectionObserverEntry>;
    act(() => {
      fireIO([fakeEntry]);
    });

    expect(onEntry).toHaveBeenCalledTimes(1);
    expect(onEntry).toHaveBeenCalledWith(fakeEntry);
  });

  it('does NOT fire the callback for entries targeting a different element', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));

    const el = makeElement();
    const otherEl = makeElement();
    act(() => {
      result.current(el);
    });

    const fakeEntry = { target: otherEl, isIntersecting: true } as Partial<IntersectionObserverEntry>;
    act(() => {
      fireIO([fakeEntry]);
    });

    expect(onEntry).not.toHaveBeenCalled();
  });

  it('always calls the latest callback reference without re-subscribing', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry1 = vi.fn();
    const onEntry2 = vi.fn();

    let callback = onEntry1;
    const { result, rerender } = renderHook(() => hook(callback));

    const el = makeElement();
    act(() => {
      result.current(el);
    });

    // Update the callback reference — the hook should use onEntry2 next time
    callback = onEntry2;
    rerender();

    // observe should still have been called just once (no re-subscribe)
    expect(observeSpy).toHaveBeenCalledTimes(1);

    const fakeEntry = { target: el, isIntersecting: true } as Partial<IntersectionObserverEntry>;
    act(() => {
      fireIO([fakeEntry]);
    });

    expect(onEntry1).not.toHaveBeenCalled();
    expect(onEntry2).toHaveBeenCalledTimes(1);
  });

  // -------------------------------------------------------------------------
  // Cleanup on unmount
  // -------------------------------------------------------------------------
  it('calls observer.unobserve on unmount', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result, unmount } = renderHook(() => hook(onEntry));

    const el = makeElement();
    act(() => {
      result.current(el);
    });

    unmount();

    expect(unobserveSpy).toHaveBeenCalledWith(el);
  });

  it('does not call unobserve on unmount if no element was attached', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { unmount } = renderHook(() => hook(onEntry));

    // Never attach an element
    unmount();

    expect(unobserveSpy).not.toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // Node swap — detach old, attach new
  // -------------------------------------------------------------------------
  it('unobserves the previous element when a new element is attached', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));

    const el1 = makeElement();
    const el2 = makeElement();

    act(() => {
      result.current(el1);
    });
    expect(observeSpy).toHaveBeenCalledWith(el1);

    act(() => {
      result.current(el2);
    });

    expect(unobserveSpy).toHaveBeenCalledWith(el1);
    expect(observeSpy).toHaveBeenCalledWith(el2);
  });

  it('unobserves the previous element when null is passed (detach)', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));

    const el = makeElement();
    act(() => {
      result.current(el);
    });

    act(() => {
      result.current(null);
    });

    expect(unobserveSpy).toHaveBeenCalledWith(el);
    // After detaching, a new IO entry targeting el should not fire the callback
    const fakeEntry = { target: el, isIntersecting: true } as Partial<IntersectionObserverEntry>;
    act(() => {
      fireIO([fakeEntry]);
    });
    expect(onEntry).not.toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // Default options
  // -------------------------------------------------------------------------
  it('passes default rootMargin and threshold to IntersectionObserver', async () => {
    const ConstructorSpy = vi.fn();
    class SpyIO {
      constructor(cb: IoCallback, options?: IntersectionObserverInit) {
        capturedCallback = cb;
        ConstructorSpy(cb, options);
      }
      observe = observeSpy;
      unobserve = unobserveSpy;
      disconnect = disconnectSpy;
      readonly root = null;
      readonly rootMargin = '0px';
      readonly thresholds = [0.5];
      takeRecords = () => [];
    }
    vi.stubGlobal('IntersectionObserver', SpyIO);

    // Reset modules again to pick up the new mock
    vi.resetModules();
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');

    const onEntry = vi.fn();
    const { result } = renderHook(() => hook(onEntry));
    const el = makeElement();
    act(() => {
      result.current(el);
    });

    expect(ConstructorSpy).toHaveBeenCalledWith(
      expect.any(Function),
      { rootMargin: '0px', threshold: 0.5 }
    );
  });

  it('passes custom rootMargin and threshold when provided', async () => {
    const ConstructorSpy = vi.fn();
    class SpyIO2 {
      constructor(cb: IoCallback, options?: IntersectionObserverInit) {
        capturedCallback = cb;
        ConstructorSpy(cb, options);
      }
      observe = observeSpy;
      unobserve = unobserveSpy;
      disconnect = disconnectSpy;
      readonly root = null;
      readonly rootMargin = '100px';
      readonly thresholds = [0.8];
      takeRecords = () => [];
    }
    vi.stubGlobal('IntersectionObserver', SpyIO2);

    vi.resetModules();
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');

    const onEntry = vi.fn();
    const { result } = renderHook(() =>
      hook(onEntry, { rootMargin: '100px', threshold: 0.8 })
    );
    const el = makeElement();
    act(() => {
      result.current(el);
    });

    expect(ConstructorSpy).toHaveBeenCalledWith(
      expect.any(Function),
      { rootMargin: '100px', threshold: 0.8 }
    );
  });

  // -------------------------------------------------------------------------
  // Refcount / shared observer re-use
  // -------------------------------------------------------------------------
  it('reuses the same observer instance for two consumers with identical options', async () => {
    const ConstructorSpy = vi.fn();

    // Track all instances created
    const instances: { observe: ReturnType<typeof vi.fn>; unobserve: ReturnType<typeof vi.fn> }[] = [];

    class SharedIO {
      observe: ReturnType<typeof vi.fn>;
      unobserve: ReturnType<typeof vi.fn>;
      disconnect: ReturnType<typeof vi.fn>;
      readonly root = null;
      readonly rootMargin = '0px';
      readonly thresholds = [0.5];
      takeRecords = () => [];

      constructor(cb: IoCallback, _options?: IntersectionObserverInit) {
        capturedCallback = cb;
        ConstructorSpy();
        this.observe = vi.fn();
        this.unobserve = vi.fn();
        this.disconnect = vi.fn();
        instances.push(this);
      }
    }
    vi.stubGlobal('IntersectionObserver', SharedIO);

    vi.resetModules();
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');

    const { result: result1 } = renderHook(() => hook(vi.fn()));
    const { result: result2 } = renderHook(() => hook(vi.fn()));

    const el1 = makeElement();
    const el2 = makeElement();

    act(() => {
      result1.current(el1);
      result2.current(el2);
    });

    // Only ONE IntersectionObserver instance should have been created
    expect(ConstructorSpy).toHaveBeenCalledTimes(1);
  });

  it('creates separate observers for different (rootMargin, threshold) pairs', async () => {
    const ConstructorSpy = vi.fn();

    class MultiIO {
      observe = vi.fn();
      unobserve = vi.fn();
      disconnect = vi.fn();
      readonly root = null;
      readonly rootMargin = '0px';
      readonly thresholds = [0.5];
      takeRecords = () => [];

      constructor(cb: IoCallback, _options?: IntersectionObserverInit) {
        capturedCallback = cb;
        ConstructorSpy();
      }
    }
    vi.stubGlobal('IntersectionObserver', MultiIO);

    vi.resetModules();
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');

    const { result: result1 } = renderHook(() => hook(vi.fn(), { threshold: 0.1 }));
    const { result: result2 } = renderHook(() => hook(vi.fn(), { threshold: 0.9 }));

    const el1 = makeElement();
    const el2 = makeElement();

    act(() => {
      result1.current(el1);
      result2.current(el2);
    });

    // Two different threshold values → two distinct observer instances
    expect(ConstructorSpy).toHaveBeenCalledTimes(2);
  });

  // -------------------------------------------------------------------------
  // enabled toggle
  // -------------------------------------------------------------------------
  it('starts observing when enabled transitions from false to true', async () => {
    const { useSharedFeedObserver: hook } = await import('./useSharedFeedObserver');
    const onEntry = vi.fn();
    let enabled = false;

    const { result, rerender } = renderHook(() => hook(onEntry, { enabled }));

    const el = makeElement();
    act(() => {
      result.current(el);
    });
    expect(observeSpy).not.toHaveBeenCalled();

    enabled = true;
    rerender();

    // The returned ref is stable per (rootMargin, threshold, enabled) combo;
    // re-attaching el through the new ref should now observe it.
    act(() => {
      result.current(el);
    });
    expect(observeSpy).toHaveBeenCalledWith(el);
  });
});
