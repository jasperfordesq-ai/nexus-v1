// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useAppUpdate hook
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useAppUpdate } from './useAppUpdate';

// Mock the api module
const mockApiPost = vi.fn();
vi.mock('@/lib/api', () => ({
  api: {
    post: (...args: unknown[]) => mockApiPost(...args),
  },
}));

describe('useAppUpdate', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    sessionStorage.clear();
    // Not a native app by default
    (window as any).Capacitor = undefined;
  });

  afterEach(() => {
    vi.useRealTimers();
    delete (window as any).Capacitor;
  });

  it('returns null updateInfo when not in native app', () => {
    const { result } = renderHook(() => useAppUpdate());

    expect(result.current.updateInfo).toBeNull();
    expect(result.current.isForceUpdate).toBe(false);
  });

  it('does not call API when not in native app', () => {
    renderHook(() => useAppUpdate());

    vi.advanceTimersByTime(5000);
    expect(mockApiPost).not.toHaveBeenCalled();
  });

  it('checks for updates in native app after delay', async () => {
    (window as any).Capacitor = { isNativePlatform: () => true };

    mockApiPost.mockResolvedValue({
      data: { update_available: false },
    });

    renderHook(() => useAppUpdate());

    // Before delay - no call
    expect(mockApiPost).not.toHaveBeenCalled();

    // After 3s delay
    await act(async () => {
      vi.advanceTimersByTime(3000);
    });

    expect(mockApiPost).toHaveBeenCalledWith('/api/app/check-version', {
      version: '1.1',
      platform: 'android',
    });
  });

  it('sets update info when update is available', async () => {
    (window as any).Capacitor = { isNativePlatform: () => true };

    mockApiPost.mockResolvedValue({
      data: {
        update_available: true,
        force_update: false,
        current_version: '1.2',
        client_version: '1.1',
        update_url: 'https://play.google.com/store/apps/details?id=com.nexus',
        update_message: 'A new version is available!',
        release_notes: { '1.2': ['Bug fixes'] },
      },
    });

    const { result } = renderHook(() => useAppUpdate());

    await act(async () => {
      vi.advanceTimersByTime(3000);
      // Let the promise resolve
      await vi.runAllTimersAsync();
    });

    expect(result.current.updateInfo).not.toBeNull();
    expect(result.current.updateInfo?.updateAvailable).toBe(true);
    expect(result.current.updateInfo?.currentVersion).toBe('1.2');
    expect(result.current.isForceUpdate).toBe(false);
  });

  it('dismiss hides update info', async () => {
    (window as any).Capacitor = { isNativePlatform: () => true };

    mockApiPost.mockResolvedValue({
      data: {
        update_available: true,
        force_update: false,
        current_version: '1.2',
        update_message: 'Update available',
      },
    });

    const { result } = renderHook(() => useAppUpdate());

    await act(async () => {
      vi.advanceTimersByTime(3000);
      await vi.runAllTimersAsync();
    });

    expect(result.current.updateInfo).not.toBeNull();

    act(() => {
      result.current.dismiss();
    });

    expect(result.current.updateInfo).toBeNull();
  });

  it('does not check more than once per session', async () => {
    (window as any).Capacitor = { isNativePlatform: () => true };
    sessionStorage.setItem('nexus_update_checked', '1');

    renderHook(() => useAppUpdate());

    await act(async () => {
      vi.advanceTimersByTime(5000);
    });

    expect(mockApiPost).not.toHaveBeenCalled();
  });

  it('handles API failure gracefully', async () => {
    (window as any).Capacitor = { isNativePlatform: () => true };

    const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    mockApiPost.mockRejectedValue(new Error('Network error'));

    const { result } = renderHook(() => useAppUpdate());

    await act(async () => {
      vi.advanceTimersByTime(3000);
      await vi.runAllTimersAsync();
    });

    expect(result.current.updateInfo).toBeNull();
    consoleSpy.mockRestore();
  });
});
