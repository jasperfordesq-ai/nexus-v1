import { useState, useCallback, useEffect, useRef } from 'react';
import { api, type ApiResponse } from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface UseApiState<T> {
  data: T | null;
  isLoading: boolean;
  error: string | null;
}

interface UseApiOptions {
  /** Fetch immediately on mount */
  immediate?: boolean;
  /** Dependencies that trigger refetch */
  deps?: unknown[];
}

interface UseApiReturn<T> extends UseApiState<T> {
  execute: () => Promise<ApiResponse<T>>;
  reset: () => void;
  setData: (data: T | null) => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// useApi Hook - For GET requests
// ─────────────────────────────────────────────────────────────────────────────

export function useApi<T>(
  endpoint: string,
  options: UseApiOptions = {}
): UseApiReturn<T> {
  const { immediate = true, deps = [] } = options;

  const [state, setState] = useState<UseApiState<T>>({
    data: null,
    isLoading: immediate,
    error: null,
  });

  const mountedRef = useRef(true);

  const execute = useCallback(async (): Promise<ApiResponse<T>> => {
    setState((prev) => ({ ...prev, isLoading: true, error: null }));

    const response = await api.get<T>(endpoint);

    if (mountedRef.current) {
      if (response.success) {
        setState({
          data: response.data ?? null,
          isLoading: false,
          error: null,
        });
      } else {
        setState((prev) => ({
          ...prev,
          isLoading: false,
          error: response.error ?? 'Request failed',
        }));
      }
    }

    return response;
  }, [endpoint]);

  const reset = useCallback(() => {
    setState({ data: null, isLoading: false, error: null });
  }, []);

  const setData = useCallback((data: T | null) => {
    setState((prev) => ({ ...prev, data }));
  }, []);

  useEffect(() => {
    mountedRef.current = true;

    if (immediate) {
      execute();
    }

    return () => {
      mountedRef.current = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [immediate, ...deps]);

  return {
    ...state,
    execute,
    reset,
    setData,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// useMutation Hook - For POST/PUT/DELETE requests
// ─────────────────────────────────────────────────────────────────────────────

type MutationMethod = 'post' | 'put' | 'patch' | 'delete';

interface UseMutationOptions<TData> {
  onSuccess?: (data: TData) => void;
  onError?: (error: string) => void;
}

interface UseMutationReturn<TData, TVariables> {
  mutate: (variables?: TVariables) => Promise<ApiResponse<TData>>;
  data: TData | null;
  isLoading: boolean;
  error: string | null;
  reset: () => void;
}

export function useMutation<TData = unknown, TVariables = unknown>(
  endpoint: string,
  method: MutationMethod = 'post',
  options: UseMutationOptions<TData> = {}
): UseMutationReturn<TData, TVariables> {
  const { onSuccess, onError } = options;

  const [state, setState] = useState<UseApiState<TData>>({
    data: null,
    isLoading: false,
    error: null,
  });

  const mutate = useCallback(
    async (variables?: TVariables): Promise<ApiResponse<TData>> => {
      setState((prev) => ({ ...prev, isLoading: true, error: null }));

      let response: ApiResponse<TData>;

      switch (method) {
        case 'post':
          response = await api.post<TData>(endpoint, variables);
          break;
        case 'put':
          response = await api.put<TData>(endpoint, variables);
          break;
        case 'patch':
          response = await api.patch<TData>(endpoint, variables);
          break;
        case 'delete':
          response = await api.delete<TData>(endpoint);
          break;
      }

      if (response.success) {
        setState({
          data: response.data ?? null,
          isLoading: false,
          error: null,
        });
        onSuccess?.(response.data as TData);
      } else {
        const errorMsg = response.error ?? 'Request failed';
        setState((prev) => ({
          ...prev,
          isLoading: false,
          error: errorMsg,
        }));
        onError?.(errorMsg);
      }

      return response;
    },
    [endpoint, method, onSuccess, onError]
  );

  const reset = useCallback(() => {
    setState({ data: null, isLoading: false, error: null });
  }, []);

  return {
    ...state,
    mutate,
    reset,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// usePaginatedApi Hook - For paginated endpoints
// ─────────────────────────────────────────────────────────────────────────────

interface PaginatedData<T> {
  items: T[];
  currentPage: number;
  totalPages: number;
  total: number;
  hasMore: boolean;
}

interface UsePaginatedApiReturn<T> {
  data: PaginatedData<T>;
  isLoading: boolean;
  error: string | null;
  loadMore: () => Promise<void>;
  refresh: () => Promise<void>;
  reset: () => void;
}

export function usePaginatedApi<T>(
  endpoint: string,
  pageSize = 20
): UsePaginatedApiReturn<T> {
  const [state, setState] = useState<{
    items: T[];
    currentPage: number;
    totalPages: number;
    total: number;
    isLoading: boolean;
    error: string | null;
  }>({
    items: [],
    currentPage: 0,
    totalPages: 1,
    total: 0,
    isLoading: true,
    error: null,
  });

  const mountedRef = useRef(true);

  const fetchPage = useCallback(
    async (page: number, reset = false) => {
      setState((prev) => ({ ...prev, isLoading: true, error: null }));

      const separator = endpoint.includes('?') ? '&' : '?';
      const url = `${endpoint}${separator}page=${page}&limit=${pageSize}`;

      const response = await api.get<{
        data: T[];
        meta: { current_page: number; last_page: number; total: number };
      }>(url);

      if (mountedRef.current) {
        if (response.success && response.data) {
          const { data, meta } = response.data;
          setState((prev) => ({
            items: reset ? data : [...prev.items, ...data],
            currentPage: meta.current_page,
            totalPages: meta.last_page,
            total: meta.total,
            isLoading: false,
            error: null,
          }));
        } else {
          setState((prev) => ({
            ...prev,
            isLoading: false,
            error: response.error ?? 'Failed to load data',
          }));
        }
      }
    },
    [endpoint, pageSize]
  );

  const loadMore = useCallback(async () => {
    if (state.currentPage < state.totalPages && !state.isLoading) {
      await fetchPage(state.currentPage + 1);
    }
  }, [state.currentPage, state.totalPages, state.isLoading, fetchPage]);

  const refresh = useCallback(async () => {
    await fetchPage(1, true);
  }, [fetchPage]);

  const reset = useCallback(() => {
    setState({
      items: [],
      currentPage: 0,
      totalPages: 1,
      total: 0,
      isLoading: false,
      error: null,
    });
  }, []);

  useEffect(() => {
    mountedRef.current = true;
    fetchPage(1, true);

    return () => {
      mountedRef.current = false;
    };
  }, [fetchPage]);

  return {
    data: {
      items: state.items,
      currentPage: state.currentPage,
      totalPages: state.totalPages,
      total: state.total,
      hasMore: state.currentPage < state.totalPages,
    },
    isLoading: state.isLoading,
    error: state.error,
    loadMore,
    refresh,
    reset,
  };
}

export default useApi;
