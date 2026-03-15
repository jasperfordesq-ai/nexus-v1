// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiResponseError } from '@/lib/api/client';

interface UseApiOptions {
  /** When false, the fetch is skipped entirely. Defaults to true. */
  enabled?: boolean;
}

interface UseApiState<T> {
  data: T | null;
  isLoading: boolean;
  error: string | null;
  /** Re-trigger the API call */
  refresh: () => void;
}

/**
 * Generic hook for data-fetching API calls.
 *
 * Usage:
 *   const { data, isLoading, error, refresh } = useApi(() => getMembers(page));
 *   const { data } = useApi(() => getEvent(id), [id], { enabled: id > 0 });
 *
 * - Automatically called on mount and whenever `deps` change.
 * - Pass `{ enabled: false }` to skip the fetch (useful for invalid IDs).
 * - Call `refresh()` to manually re-fetch (e.g. on pull-to-refresh).
 * - Cancelled automatically on unmount to avoid state-update-on-unmounted-component.
 */
export function useApi<T>(
  fetchFn: () => Promise<T>,
  deps: unknown[] = [],
  options?: UseApiOptions,
): UseApiState<T> {
  const enabled = options?.enabled ?? true;
  const [data, setData] = useState<T | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshToken, setRefreshToken] = useState(0);
  const isMountedRef = useRef(true);

  // Always track the latest fetchFn to avoid stale closure issues.
  // Call sites pass inline arrows (e.g. `useApi(() => getFeed(), [])`) whose
  // identity changes every render. Storing in a ref means the effect always
  // invokes the most recent version without needing it in the dep array.
  const fetchFnRef = useRef(fetchFn);
  fetchFnRef.current = fetchFn;

  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    if (!enabled) {
      setIsLoading(false);
      return;
    }

    let cancelled = false;

    async function run() {
      setIsLoading(true);
      setError(null);
      try {
        const result = await fetchFnRef.current();
        if (!cancelled && isMountedRef.current) {
          setData(result);
        }
      } catch (err) {
        if (!cancelled && isMountedRef.current) {
          if (err instanceof ApiResponseError) {
            setError(err.message);
          } else {
            setError('An unexpected error occurred.');
          }
        }
      } finally {
        if (!cancelled && isMountedRef.current) {
          setIsLoading(false);
        }
      }
    }

    void run();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, refreshToken, enabled]);

  const refresh = useCallback(() => setRefreshToken((n) => n + 1), []);

  return { data, isLoading, error, refresh };
}
