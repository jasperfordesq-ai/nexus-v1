// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { usePrerenderReady, initPrerenderReady } from './usePrerenderReady';

describe('usePrerenderReady', () => {
  beforeEach(() => {
    // Reset the window flag to undefined before each test
    // so we exercise the initialisation guard.
    delete (window as { prerenderReady?: boolean }).prerenderReady;
  });

  afterEach(() => {
    delete (window as { prerenderReady?: boolean }).prerenderReady;
  });

  it('sets window.prerenderReady = false on mount when isReady is false', () => {
    renderHook(() => usePrerenderReady(false));
    expect(window.prerenderReady).toBe(false);
  });

  it('sets window.prerenderReady = true when isReady is true', () => {
    renderHook(() => usePrerenderReady(true));
    expect(window.prerenderReady).toBe(true);
  });

  it('transitions from false to true when isReady changes', () => {
    let isReady = false;
    const { rerender } = renderHook(() => usePrerenderReady(isReady));

    expect(window.prerenderReady).toBe(false);

    act(() => {
      isReady = true;
      rerender();
    });

    expect(window.prerenderReady).toBe(true);
  });

  it('transitions back to false when isReady changes from true to false', () => {
    let isReady = true;
    const { rerender } = renderHook(() => usePrerenderReady(isReady));

    expect(window.prerenderReady).toBe(true);

    act(() => {
      isReady = false;
      rerender();
    });

    expect(window.prerenderReady).toBe(false);
  });

  it('initialises the flag to false if it was undefined on first render', () => {
    expect((window as { prerenderReady?: boolean }).prerenderReady).toBeUndefined();
    renderHook(() => usePrerenderReady(false));
    // After the effect the flag should be explicitly false (not undefined)
    expect(window.prerenderReady).toBe(false);
  });

  it('does not reset a pre-existing true flag to false when isReady is true', () => {
    window.prerenderReady = true;
    renderHook(() => usePrerenderReady(true));
    expect(window.prerenderReady).toBe(true);
  });

  it('overrides a pre-existing true flag to false when isReady is false', () => {
    // A prior component already set it true; mounting with isReady=false should set it back
    window.prerenderReady = true;
    renderHook(() => usePrerenderReady(false));
    expect(window.prerenderReady).toBe(false);
  });

  it('does not throw when window is available (normal browser env)', () => {
    expect(() => renderHook(() => usePrerenderReady(false))).not.toThrow();
    expect(() => renderHook(() => usePrerenderReady(true))).not.toThrow();
  });
});

describe('initPrerenderReady', () => {
  beforeEach(() => {
    delete (window as { prerenderReady?: boolean }).prerenderReady;
  });

  afterEach(() => {
    delete (window as { prerenderReady?: boolean }).prerenderReady;
  });

  it('sets window.prerenderReady = false when the flag is undefined', () => {
    expect((window as { prerenderReady?: boolean }).prerenderReady).toBeUndefined();
    initPrerenderReady();
    expect(window.prerenderReady).toBe(false);
  });

  it('does not overwrite an existing value if already set to true', () => {
    window.prerenderReady = true;
    initPrerenderReady();
    // Guard: only initialise when undefined
    expect(window.prerenderReady).toBe(true);
  });

  it('does not overwrite an existing value if already set to false', () => {
    window.prerenderReady = false;
    initPrerenderReady();
    expect(window.prerenderReady).toBe(false);
  });

  it('is idempotent — calling multiple times does not change the flag', () => {
    initPrerenderReady();
    initPrerenderReady();
    initPrerenderReady();
    expect(window.prerenderReady).toBe(false);
  });
});
