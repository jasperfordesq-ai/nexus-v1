// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

const STORAGE_KEY = 'nexus_proximity';
const STALE_MS = 24 * 60 * 60 * 1000; // 24 hours

export interface ProximityPosition {
  lat: number;
  lng: number;
  accuracy?: number;
  source: 'gps' | 'manual' | 'cached';
}

interface StoredPosition {
  lat: number;
  lng: number;
  accuracy?: number;
  ts: number;
}

function readCache(): ProximityPosition | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed: StoredPosition = JSON.parse(raw);
    if (
      typeof parsed.lat !== 'number' ||
      typeof parsed.lng !== 'number' ||
      typeof parsed.ts !== 'number'
    ) {
      return null;
    }
    if (Date.now() - parsed.ts > STALE_MS) {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }
    return { lat: parsed.lat, lng: parsed.lng, accuracy: parsed.accuracy, source: 'cached' };
  } catch {
    return null;
  }
}

function writeCache(lat: number, lng: number, accuracy?: number): void {
  try {
    const data: StoredPosition = { lat, lng, accuracy, ts: Date.now() };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  } catch {
    // ignore storage errors
  }
}

export function useProximity(): {
  position: ProximityPosition | null;
  isLoading: boolean;
  error: string | null;
  requestLocation: () => void;
  setPosition: (lat: number, lng: number) => void;
  clearPosition: () => void;
} {
  const { t } = useTranslation('common');

  const [position, setPositionState] = useState<ProximityPosition | null>(() => readCache());
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Re-check cache on mount (handles hydration across sessions)
  useEffect(() => {
    const cached = readCache();
    if (cached) {
      setPositionState(cached);
    }
  }, []);

  const requestLocation = useCallback(() => {
    if (!navigator.geolocation) {
      setError(t('proximity.error.unavailable'));
      return;
    }

    setIsLoading(true);
    setError(null);

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const { latitude, longitude, accuracy } = pos.coords;
        writeCache(latitude, longitude, accuracy);
        setPositionState({ lat: latitude, lng: longitude, accuracy, source: 'gps' });
        setIsLoading(false);
      },
      (err) => {
        setIsLoading(false);
        if (err.code === 1) {
          setError(t('proximity.error.denied'));
        } else {
          setError(t('proximity.error.unavailable'));
        }
      },
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
    );
  }, [t]);

  const setPosition = useCallback((lat: number, lng: number) => {
    writeCache(lat, lng, undefined);
    setPositionState({ lat, lng, source: 'manual' });
    setError(null);
  }, []);

  const clearPosition = useCallback(() => {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
    setPositionState(null);
    setError(null);
  }, []);

  return { position, isLoading, error, requestLocation, setPosition, clearPosition };
}
