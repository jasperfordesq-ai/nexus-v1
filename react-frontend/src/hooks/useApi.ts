// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { api, type ApiResponse, type PaginationMeta } from '@/lib/api';

interface UseApiState<T> {
  data: T | null;
  isLoading: boolean;
  error: string | null;
  meta?: PaginationMeta | null;
}

interface UseApiOptions {
  /** Fetch immediately on mount. */
  immediate?: boolean;
  /** Dependencies that trigger a refetch. */
  deps?: unknown[];
}

interface UseApiReturn<T> extends UseApiState<T> {
  execute: () => Promise<ApiResponse<T>>;
  refetch: () => Promise<ApiResponse<T>>;
  reset: () => void;
  setData: (data: T | null) => void;
  /** Compatibility alias retained for current `useApi` consumers. */
  loading: boolean;
}

/** GET-only data hook used by current production consumers. */
export function useApi<T>(
  endpoint: string | null,
  options: UseApiOptions = {},
): UseApiReturn<T> {
  const { immediate = true, deps = [] } = options;
  const [state, setState] = useState<UseApiState<T>>({
    data: null,
    // A null endpoint is not a pending request.
    isLoading: immediate && endpoint !== null,
    error: null,
    meta: null,
  });
  const mountedRef = useRef(true);
  const requestIdRef = useRef(0);

  const execute = useCallback(async (): Promise<ApiResponse<T>> => {
    const requestId = ++requestIdRef.current;
    setState((previous) => ({ ...previous, isLoading: true, error: null }));

    if (!endpoint) {
      const response: ApiResponse<T> = { success: true, data: undefined };
      setState({ data: null, isLoading: false, error: null, meta: null });
      return response;
    }

    const response = await api.get<T>(endpoint);

    if (mountedRef.current && requestIdRef.current === requestId) {
      if (response.success) {
        setState({
          data: response.data ?? null,
          isLoading: false,
          error: null,
          meta: response.meta ?? null,
        });
      } else {
        setState((previous) => ({
          ...previous,
          isLoading: false,
          error: response.error ?? 'Request failed',
        }));
      }
    }

    return response;
  }, [endpoint]);

  const reset = useCallback(() => {
    setState({ data: null, isLoading: false, error: null, meta: null });
  }, []);

  const setData = useCallback((data: T | null) => {
    setState((previous) => ({ ...previous, data }));
  }, []);

  useEffect(() => {
    mountedRef.current = true;

    if (immediate && endpoint) {
      void execute();
    } else if (!endpoint) {
      setState((previous) => (
        previous.isLoading ? { ...previous, isLoading: false } : previous
      ));
    }

    return () => {
      mountedRef.current = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- endpoint and caller deps intentionally drive refetches
  }, [immediate, endpoint, ...deps]);

  return {
    ...state,
    execute,
    refetch: execute,
    reset,
    setData,
    loading: state.isLoading,
  };
}

export default useApi;
