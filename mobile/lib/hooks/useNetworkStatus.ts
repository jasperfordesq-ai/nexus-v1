// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { API_BASE_URL } from '@/lib/constants';

/** How long to wait for the health-check ping before treating as offline (ms). */
const PING_TIMEOUT_MS = 5_000;

/** URL to ping — the lightweight health endpoint on the NEXUS API. */
const HEALTH_URL = `${API_BASE_URL}/health`;

export interface NetworkStatus {
  /** True when the API health check succeeds (or has not yet been checked). */
  isOnline: boolean;
  /** True while a connectivity check is in flight. */
  isChecking: boolean;
}

/**
 * Detects connectivity by pinging the API health endpoint every time the app
 * returns to the foreground (AppState → 'active').
 *
 * Rationale for not using @react-native-community/netinfo:
 *  - A real HTTP ping is more reliable than the OS-level reachability flag
 *    because it confirms actual backend connectivity, not just local network.
 *
 * Default: `isOnline = true` to avoid false-positive "offline" banners on
 * first render before the first check completes.
 */
export function useNetworkStatus(): NetworkStatus {
  const [isOnline, setIsOnline] = useState(true);
  const [isChecking, setIsChecking] = useState(false);

  const isMountedRef = useRef(true);
  const isCheckingRef = useRef(false); // guard against concurrent pings

  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  const checkConnectivity = useCallback(async () => {
    if (isCheckingRef.current) return;
    isCheckingRef.current = true;

    if (isMountedRef.current) setIsChecking(true);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), PING_TIMEOUT_MS);

    let online = false;
    try {
      const response = await fetch(HEALTH_URL, {
        method: 'GET',
        signal: controller.signal,
        // Bypass any HTTP caches — we need a live round-trip.
        cache: 'no-store',
      });
      online = response.ok || response.status < 500;
    } catch {
      // AbortError (timeout) or network error → offline
      online = false;
    } finally {
      clearTimeout(timeoutId);
    }

    if (isMountedRef.current) {
      setIsOnline(online);
      setIsChecking(false);
    }
    isCheckingRef.current = false;
  }, []);

  // Run an immediate check on mount.
  useEffect(() => {
    void checkConnectivity();
  }, [checkConnectivity]);

  // Re-check every time the app returns to the foreground.
  useEffect(() => {
    const handleAppStateChange = (nextState: AppStateStatus) => {
      if (nextState === 'active') {
        void checkConnectivity();
      }
    };

    const subscription = AppState.addEventListener('change', handleAppStateChange);
    return () => subscription.remove();
  }, [checkConnectivity]);

  return { isOnline, isChecking };
}
