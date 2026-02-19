// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';

const STORAGE_KEY = 'nexus_user_geo';

interface GeolocationState {
  latitude: number | null;
  longitude: number | null;
  accuracy: number | null;
  loading: boolean;
  error: string | null;
  permissionGranted: boolean;
}

export interface UseGeolocationReturn extends GeolocationState {
  /** Explicitly request the user's current position. */
  requestLocation: () => void;
  /** Clear stored location. */
  clearLocation: () => void;
}

function getStored(): Pick<GeolocationState, 'latitude' | 'longitude' | 'accuracy'> | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (typeof parsed.latitude === 'number' && typeof parsed.longitude === 'number') {
      return parsed;
    }
  } catch { /* ignore */ }
  return null;
}

export function useGeolocation(): UseGeolocationReturn {
  const stored = getStored();

  const [state, setState] = useState<GeolocationState>({
    latitude: stored?.latitude ?? null,
    longitude: stored?.longitude ?? null,
    accuracy: stored?.accuracy ?? null,
    loading: false,
    error: null,
    permissionGranted: !!stored,
  });

  const requestLocation = useCallback(() => {
    if (!navigator.geolocation) {
      setState((s) => ({ ...s, error: 'Geolocation not supported', loading: false }));
      return;
    }

    setState((s) => ({ ...s, loading: true, error: null }));

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const { latitude, longitude, accuracy } = position.coords;
        const geo = { latitude, longitude, accuracy };
        try {
          sessionStorage.setItem(STORAGE_KEY, JSON.stringify(geo));
        } catch { /* ignore */ }
        setState({
          latitude,
          longitude,
          accuracy,
          loading: false,
          error: null,
          permissionGranted: true,
        });
      },
      (err) => {
        const messages: Record<number, string> = {
          1: 'Location access denied',
          2: 'Location unavailable',
          3: 'Location request timed out',
        };
        setState((s) => ({
          ...s,
          loading: false,
          error: messages[err.code] || 'Unknown geolocation error',
        }));
      },
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
    );
  }, []);

  const clearLocation = useCallback(() => {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch { /* ignore */ }
    setState({
      latitude: null,
      longitude: null,
      accuracy: null,
      loading: false,
      error: null,
      permissionGranted: false,
    });
  }, []);

  return { ...state, requestLocation, clearLocation };
}
