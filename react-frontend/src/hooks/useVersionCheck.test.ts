// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useVersionCheck hook.
 *
 * Covers: version mismatch detection, update event dispatch,
 * no-op when versions match, error resilience.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useVersionCheck } from './useVersionCheck';

describe('useVersionCheck', () => {
  let fetchSpy: ReturnType<typeof vi.fn>;
  let dispatchEventSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    vi.useFakeTimers();
    fetchSpy = vi.fn();
    global.fetch = fetchSpy;
    dispatchEventSpy = vi.spyOn(window, 'dispatchEvent');
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('does not fire update event when commit matches', async () => {
    // __BUILD_COMMIT__ is 'test' per vitest.config.ts
    fetchSpy.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ commit: 'test' }),
    });

    renderHook(() => useVersionCheck());

    // Advance past initial delay (15s)
    await act(async () => {
      vi.advanceTimersByTime(16_000);
    });

    // No update event should be dispatched
    const updateEvents = dispatchEventSpy.mock.calls.filter(
      (call) => call[0] instanceof CustomEvent && call[0].type === 'nexus:sw_update_available'
    );
    expect(updateEvents).toHaveLength(0);
  });

  it('fires update event when commit differs', async () => {
    fetchSpy.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ commit: 'new-deploy-hash-abc123' }),
    });

    renderHook(() => useVersionCheck());

    // Advance past initial delay
    await act(async () => {
      vi.advanceTimersByTime(16_000);
    });

    const updateEvents = dispatchEventSpy.mock.calls.filter(
      (call) => call[0] instanceof CustomEvent && call[0].type === 'nexus:sw_update_available'
    );
    expect(updateEvents).toHaveLength(1);
  });

  it('does not fire update event when commit is dev', async () => {
    fetchSpy.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ commit: 'dev' }),
    });

    renderHook(() => useVersionCheck());

    await act(async () => {
      vi.advanceTimersByTime(16_000);
    });

    const updateEvents = dispatchEventSpy.mock.calls.filter(
      (call) => call[0] instanceof CustomEvent && call[0].type === 'nexus:sw_update_available'
    );
    expect(updateEvents).toHaveLength(0);
  });

  it('handles fetch failure gracefully without throwing', async () => {
    fetchSpy.mockRejectedValue(new Error('Network error'));

    renderHook(() => useVersionCheck());

    // Should not throw
    await act(async () => {
      vi.advanceTimersByTime(16_000);
    });

    // No event dispatched on error
    const updateEvents = dispatchEventSpy.mock.calls.filter(
      (call) => call[0] instanceof CustomEvent && call[0].type === 'nexus:sw_update_available'
    );
    expect(updateEvents).toHaveLength(0);
  });

  it('handles non-ok response gracefully', async () => {
    fetchSpy.mockResolvedValue({
      ok: false,
      status: 404,
    });

    renderHook(() => useVersionCheck());

    await act(async () => {
      vi.advanceTimersByTime(16_000);
    });

    const updateEvents = dispatchEventSpy.mock.calls.filter(
      (call) => call[0] instanceof CustomEvent && call[0].type === 'nexus:sw_update_available'
    );
    expect(updateEvents).toHaveLength(0);
  });

  it('only fires update event once per session', async () => {
    fetchSpy.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ commit: 'different-commit' }),
    });

    renderHook(() => useVersionCheck());

    // First check after 15s delay
    await act(async () => {
      vi.advanceTimersByTime(16_000);
    });

    // Second check after 5 min interval
    await act(async () => {
      vi.advanceTimersByTime(5 * 60 * 1000);
    });

    const updateEvents = dispatchEventSpy.mock.calls.filter(
      (call) => call[0] instanceof CustomEvent && call[0].type === 'nexus:sw_update_available'
    );
    // Should only fire once even after multiple checks
    expect(updateEvents).toHaveLength(1);
  });

  it('checks on visibility change', async () => {
    fetchSpy.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ commit: 'test' }),
    });

    renderHook(() => useVersionCheck());

    // Simulate visibility change
    Object.defineProperty(document, 'visibilityState', {
      value: 'visible',
      writable: true,
      configurable: true,
    });

    await act(async () => {
      document.dispatchEvent(new Event('visibilitychange'));
      // Let promises resolve
      await Promise.resolve();
    });

    // fetch should have been called
    expect(fetchSpy).toHaveBeenCalled();
  });

  it('cleans up timers and listeners on unmount', () => {
    const { unmount } = renderHook(() => useVersionCheck());

    const removeEventListenerSpy = vi.spyOn(document, 'removeEventListener');

    unmount();

    expect(removeEventListenerSpy).toHaveBeenCalledWith(
      'visibilitychange',
      expect.any(Function)
    );
  });
});
