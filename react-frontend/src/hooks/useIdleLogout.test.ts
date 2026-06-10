// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useIdleLogout, parseIdleTimeoutMs } from './useIdleLogout';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(),
  useTenant: vi.fn(),
  useToast: vi.fn(),
}));

import { useAuth, useTenant, useToast } from '@/contexts';

const mockUseAuth = useAuth as ReturnType<typeof vi.fn>;
const mockUseTenant = useTenant as ReturnType<typeof vi.fn>;
const mockUseToast = useToast as ReturnType<typeof vi.fn>;

function setup({ timeoutMinutes, user }: { timeoutMinutes: unknown; user: object | null }) {
  const logout = vi.fn().mockResolvedValue(undefined);
  const toastInfo = vi.fn();
  mockUseAuth.mockReturnValue({ user, logout });
  mockUseTenant.mockReturnValue({
    tenant: { id: 2, settings: { inactivity_timeout_minutes: timeoutMinutes } },
  });
  mockUseToast.mockReturnValue({ info: toastInfo, success: vi.fn(), error: vi.fn(), warning: vi.fn() });
  return { logout, toastInfo };
}

describe('parseIdleTimeoutMs', () => {
  it('returns 0 for disabled/invalid values', () => {
    expect(parseIdleTimeoutMs(undefined)).toBe(0);
    expect(parseIdleTimeoutMs(null)).toBe(0);
    expect(parseIdleTimeoutMs(false)).toBe(0); // bootstrap coerces '0' to false
    expect(parseIdleTimeoutMs(true)).toBe(0);
    expect(parseIdleTimeoutMs('0')).toBe(0);
    expect(parseIdleTimeoutMs('4')).toBe(0); // below minimum
    expect(parseIdleTimeoutMs('abc')).toBe(0);
  });

  it('converts valid minute values to milliseconds', () => {
    expect(parseIdleTimeoutMs('30')).toBe(30 * 60_000);
    expect(parseIdleTimeoutMs(120)).toBe(120 * 60_000);
  });

  it('caps at 480 minutes', () => {
    expect(parseIdleTimeoutMs('9999')).toBe(480 * 60_000);
  });
});

describe('useIdleLogout', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    localStorage.clear();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
  });

  it('logs out after the configured idle period', async () => {
    const { logout } = setup({ timeoutMinutes: '5', user: { id: 1 } });
    renderHook(() => useIdleLogout());

    await act(async () => {
      vi.advanceTimersByTime(5 * 60_000 + 31_000);
    });

    expect(logout).toHaveBeenCalledTimes(1);
  });

  it('does not log out while the user stays active', async () => {
    const { logout } = setup({ timeoutMinutes: '5', user: { id: 1 } });
    renderHook(() => useIdleLogout());

    // Simulate activity every 2 minutes for 12 minutes total
    for (let i = 0; i < 6; i++) {
      await act(async () => {
        vi.advanceTimersByTime(2 * 60_000);
        window.dispatchEvent(new Event('pointerdown'));
      });
    }

    expect(logout).not.toHaveBeenCalled();
  });

  it('respects activity recorded by another tab via localStorage', async () => {
    const { logout } = setup({ timeoutMinutes: '5', user: { id: 1 } });
    renderHook(() => useIdleLogout());

    await act(async () => {
      vi.advanceTimersByTime(4 * 60_000);
      // Another tab records fresh activity
      localStorage.setItem('nexus_last_activity', String(Date.now()));
      vi.advanceTimersByTime(2 * 60_000);
    });

    expect(logout).not.toHaveBeenCalled();
  });

  it('does nothing when the timeout is disabled', async () => {
    const { logout } = setup({ timeoutMinutes: false, user: { id: 1 } });
    renderHook(() => useIdleLogout());

    await act(async () => {
      vi.advanceTimersByTime(24 * 60 * 60_000);
    });

    expect(logout).not.toHaveBeenCalled();
  });

  it('does nothing when no user is authenticated', async () => {
    const { logout } = setup({ timeoutMinutes: '5', user: null });
    renderHook(() => useIdleLogout());

    await act(async () => {
      vi.advanceTimersByTime(60 * 60_000);
    });

    expect(logout).not.toHaveBeenCalled();
  });

  it('only logs out once even across multiple check ticks', async () => {
    const { logout } = setup({ timeoutMinutes: '5', user: { id: 1 } });
    renderHook(() => useIdleLogout());

    await act(async () => {
      vi.advanceTimersByTime(10 * 60_000);
    });

    expect(logout).toHaveBeenCalledTimes(1);
  });
});
