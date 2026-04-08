// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useInfiniteScroll — IntersectionObserver-based auto-loading for paginated lists.
 *
 * Returns a callback ref to attach to a sentinel element. When the sentinel enters
 * the viewport (with configurable rootMargin for prefetch), calls `onLoadMore`.
 * Respects `hasMore` and `isLoading` to prevent double-triggers.
 *
 * Uses a callback ref (not useRef) so the observer re-attaches whenever the
 * sentinel element mounts/unmounts (e.g. when `hasMore` toggles).
 */

import { useRef, useCallback } from 'react';

interface UseInfiniteScrollOptions {
  /** Whether more items are available */
  hasMore: boolean;
  /** Whether a load is currently in progress */
  isLoading: boolean;
  /** Callback to load the next page */
  onLoadMore: () => void;
  /** How far before the sentinel to trigger (default: '400px') */
  rootMargin?: string;
  /** IntersectionObserver threshold (default: 0.1) */
  threshold?: number;
}

export function useInfiniteScroll({
  hasMore,
  isLoading,
  onLoadMore,
  rootMargin = '400px',
  threshold = 0.1,
}: UseInfiniteScrollOptions) {
  const observerRef = useRef<IntersectionObserver | null>(null);
  const loadingRef = useRef(isLoading);
  const hasMoreRef = useRef(hasMore);

  // Keep refs in sync to avoid stale closures in the observer callback
  loadingRef.current = isLoading;
  hasMoreRef.current = hasMore;

  const stableOnLoadMore = useRef(onLoadMore);
  stableOnLoadMore.current = onLoadMore;

  // Callback ref: called whenever the sentinel element mounts or unmounts.
  // This ensures the observer re-attaches even if the sentinel was conditionally
  // removed (hasMore=false) and then re-added (hasMore=true).
  const sentinelRef = useCallback(
    (node: HTMLDivElement | null) => {
      // Disconnect previous observer
      if (observerRef.current) {
        observerRef.current.disconnect();
        observerRef.current = null;
      }

      if (!node) return;

      observerRef.current = new IntersectionObserver(
        ([entry]) => {
          if (entry?.isIntersecting && hasMoreRef.current && !loadingRef.current) {
            stableOnLoadMore.current();
          }
        },
        { rootMargin, threshold }
      );

      observerRef.current.observe(node);
    },
    [rootMargin, threshold]
  );

  return sentinelRef;
}
