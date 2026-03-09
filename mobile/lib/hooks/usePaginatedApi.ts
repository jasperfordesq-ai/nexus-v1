// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiResponseError } from '@/lib/api/client';

export interface PaginatedApiState<TItem> {
  items: TItem[];
  isLoading: boolean;
  isLoadingMore: boolean;
  error: string | null;
  hasMore: boolean;
  loadMore: () => void;
  refresh: () => void;
}

/**
 * Generic hook for paginated/infinite-scroll API calls.
 *
 * Supports both cursor-based and offset-based pagination by delegating
 * the pagination logic to the caller via `fetchFn` and `extractor`.
 *
 * Usage (cursor-based):
 *   const { items, isLoading, hasMore, loadMore, refresh } = usePaginatedApi(
 *     (cursor) => getFeed(cursor),
 *     (response) => ({
 *       items: response.data,
 *       cursor: response.meta.cursor,
 *       hasMore: response.meta.has_more,
 *     }),
 *   );
 *
 * Usage (page/offset-based — pass the page number as cursor):
 *   const pageRef = useRef(1);
 *   const { items, isLoading, hasMore, loadMore, refresh } = usePaginatedApi(
 *     (cursor) => getExchanges(cursor ? Number(cursor) : 1),
 *     (response) => ({
 *       items: response.data,
 *       cursor: response.meta.current_page < response.meta.last_page
 *         ? String(response.meta.current_page + 1)
 *         : null,
 *       hasMore: response.meta.current_page < response.meta.last_page,
 *     }),
 *   );
 *
 * @param fetchFn   Function that accepts the current cursor (null on first page)
 *                  and returns a Promise of the raw API response.
 * @param extractor Function that maps the raw API response to a normalised shape
 *                  containing `items`, the next `cursor`, and `hasMore`.
 */
export function usePaginatedApi<TItem, TResponse>(
  fetchFn: (cursor: string | null) => Promise<TResponse>,
  extractor: (response: TResponse) => {
    items: TItem[];
    cursor: string | null;
    hasMore: boolean;
  },
): PaginatedApiState<TItem> {
  const [items, setItems] = useState<TItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);

  // Tracks the cursor for the next page. null means "start from the beginning".
  const cursorRef = useRef<string | null>(null);

  // Guards against concurrent fetches (e.g. rapid loadMore taps or re-renders).
  const isFetchingRef = useRef(false);

  // Set to false when the component unmounts so we never set state after unmount.
  const isMountedRef = useRef(true);

  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  /** Internal fetch helper. `isInitial` replaces items; otherwise appends. */
  const fetchPage = useCallback(
    async (cursor: string | null, isInitial: boolean) => {
      if (isFetchingRef.current) return;
      isFetchingRef.current = true;

      if (isInitial) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      try {
        const response = await fetchFn(cursor);

        if (!isMountedRef.current) return;

        const { items: newItems, cursor: nextCursor, hasMore: more } = extractor(response);

        cursorRef.current = nextCursor;

        if (isInitial) {
          setItems(newItems);
        } else {
          setItems((prev) => [...prev, ...newItems]);
        }

        setHasMore(more);
        setError(null);
      } catch (err) {
        if (!isMountedRef.current) return;

        if (err instanceof ApiResponseError) {
          setError(err.message);
        } else {
          setError('An unexpected error occurred.');
        }
      } finally {
        if (isMountedRef.current) {
          if (isInitial) {
            setIsLoading(false);
          } else {
            setIsLoadingMore(false);
          }
        }
        isFetchingRef.current = false;
      }
    },
    // fetchFn and extractor are treated as stable references (callers should
    // wrap them in useCallback or define them outside the component if needed).
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [fetchFn, extractor],
  );

  // Initial load on mount.
  useEffect(() => {
    cursorRef.current = null;
    void fetchPage(null, true);
    // fetchPage is stable; this only runs once on mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /** Append the next page to the list. No-op if already fetching or no more pages. */
  const loadMore = useCallback(() => {
    if (!hasMore || isFetchingRef.current) return;
    void fetchPage(cursorRef.current, false);
  }, [hasMore, fetchPage]);

  /** Reset to the first page and replace the item list. */
  const refresh = useCallback(() => {
    cursorRef.current = null;
    void fetchPage(null, true);
  }, [fetchPage]);

  return { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh };
}
