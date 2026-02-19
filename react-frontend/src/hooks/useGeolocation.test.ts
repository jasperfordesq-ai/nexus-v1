// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useGeolocation hook
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useGeolocation } from './useGeolocation';

describe('useGeolocation', () => {
  const mockGetCurrentPosition = vi.fn();
  const originalGeolocation = navigator.geolocation;

  beforeEach(() => {
    sessionStorage.clear();
    // Mock navigator.geolocation
    Object.defineProperty(navigator, 'geolocation', {
      writable: true,
      value: {
        getCurrentPosition: mockGetCurrentPosition,
      },
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
    Object.defineProperty(navigator, 'geolocation', {
      writable: true,
      value: originalGeolocation,
    });
  });

  it('initializes with null coordinates', () => {
    const { result } = renderHook(() => useGeolocation());
    expect(result.current.latitude).toBeNull();
    expect(result.current.longitude).toBeNull();
    expect(result.current.accuracy).toBeNull();
    expect(result.current.loading).toBe(false);
    expect(result.current.error).toBeNull();
    expect(result.current.permissionGranted).toBe(false);
  });

  it('restores coordinates from sessionStorage', () => {
    sessionStorage.setItem('nexus_user_geo', JSON.stringify({
      latitude: 52.0,
      longitude: -1.0,
      accuracy: 50,
    }));

    const { result } = renderHook(() => useGeolocation());
    expect(result.current.latitude).toBe(52.0);
    expect(result.current.longitude).toBe(-1.0);
    expect(result.current.accuracy).toBe(50);
    expect(result.current.permissionGranted).toBe(true);
  });

  it('requests location and updates state on success', () => {
    mockGetCurrentPosition.mockImplementation((success) => {
      success({
        coords: { latitude: 51.5, longitude: -0.1, accuracy: 10 },
      });
    });

    const { result } = renderHook(() => useGeolocation());

    act(() => {
      result.current.requestLocation();
    });

    expect(result.current.latitude).toBe(51.5);
    expect(result.current.longitude).toBe(-0.1);
    expect(result.current.accuracy).toBe(10);
    expect(result.current.loading).toBe(false);
    expect(result.current.permissionGranted).toBe(true);
  });

  it('stores location in sessionStorage on success', () => {
    mockGetCurrentPosition.mockImplementation((success) => {
      success({
        coords: { latitude: 53.0, longitude: -2.0, accuracy: 20 },
      });
    });

    const { result } = renderHook(() => useGeolocation());

    act(() => {
      result.current.requestLocation();
    });

    const stored = JSON.parse(sessionStorage.getItem('nexus_user_geo')!);
    expect(stored.latitude).toBe(53.0);
    expect(stored.longitude).toBe(-2.0);
  });

  it('handles permission denied error', () => {
    mockGetCurrentPosition.mockImplementation((_, error) => {
      error({ code: 1 });
    });

    const { result } = renderHook(() => useGeolocation());

    act(() => {
      result.current.requestLocation();
    });

    expect(result.current.error).toBe('Location access denied');
    expect(result.current.loading).toBe(false);
  });

  it('handles location unavailable error', () => {
    mockGetCurrentPosition.mockImplementation((_, error) => {
      error({ code: 2 });
    });

    const { result } = renderHook(() => useGeolocation());

    act(() => {
      result.current.requestLocation();
    });

    expect(result.current.error).toBe('Location unavailable');
  });

  it('handles timeout error', () => {
    mockGetCurrentPosition.mockImplementation((_, error) => {
      error({ code: 3 });
    });

    const { result } = renderHook(() => useGeolocation());

    act(() => {
      result.current.requestLocation();
    });

    expect(result.current.error).toBe('Location request timed out');
  });

  it('handles geolocation not supported', () => {
    Object.defineProperty(navigator, 'geolocation', {
      writable: true,
      value: undefined,
    });

    const { result } = renderHook(() => useGeolocation());

    act(() => {
      result.current.requestLocation();
    });

    expect(result.current.error).toBe('Geolocation not supported');
  });

  it('clears location from state and sessionStorage', () => {
    sessionStorage.setItem('nexus_user_geo', JSON.stringify({
      latitude: 52.0,
      longitude: -1.0,
      accuracy: 50,
    }));

    const { result } = renderHook(() => useGeolocation());
    expect(result.current.latitude).toBe(52.0);

    act(() => {
      result.current.clearLocation();
    });

    expect(result.current.latitude).toBeNull();
    expect(result.current.longitude).toBeNull();
    expect(result.current.permissionGranted).toBe(false);
    expect(sessionStorage.getItem('nexus_user_geo')).toBeNull();
  });

  it('ignores invalid stored data', () => {
    sessionStorage.setItem('nexus_user_geo', 'not-json');
    const { result } = renderHook(() => useGeolocation());
    expect(result.current.latitude).toBeNull();
  });

  it('ignores stored data with non-numeric coordinates', () => {
    sessionStorage.setItem('nexus_user_geo', JSON.stringify({
      latitude: 'bad',
      longitude: 'data',
    }));
    const { result } = renderHook(() => useGeolocation());
    expect(result.current.latitude).toBeNull();
  });
});
