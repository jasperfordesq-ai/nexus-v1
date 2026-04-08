// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * usePullToRefresh — Touch gesture hook for pull-to-refresh on mobile.
 *
 * Activates only when the page is scrolled to the very top.
 * Returns pull distance for rendering a visual indicator.
 * No-op on desktop (only binds touch events).
 */

import { useState, useRef, useEffect } from 'react';

interface UsePullToRefreshOptions {
  /** Callback fired when the user pulls past the threshold and releases */
  onRefresh: () => Promise<void> | void;
  /** Minimum pull distance (px) to trigger refresh (default: 60) */
  threshold?: number;
  /** Whether the hook is enabled (default: true) */
  enabled?: boolean;
}

interface PullToRefreshState {
  /** Current pull distance in pixels (0 when not pulling) */
  pullDistance: number;
  /** Whether a refresh is currently in progress */
  isRefreshing: boolean;
}

export function usePullToRefresh({
  onRefresh,
  threshold = 60,
  enabled = true,
}: UsePullToRefreshOptions): PullToRefreshState {
  const [pullDistance, setPullDistance] = useState(0);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const startYRef = useRef(0);
  const pullingRef = useRef(false);
  const pullDistanceRef = useRef(0);
  const onRefreshRef = useRef(onRefresh);
  onRefreshRef.current = onRefresh;
  const isRefreshingRef = useRef(false);

  const isTouchDevice = typeof window !== 'undefined' && 'ontouchstart' in window;

  useEffect(() => {
    if (!enabled || !isTouchDevice) return;

    const handleTouchStart = (e: TouchEvent) => {
      if (window.scrollY > 0 || isRefreshingRef.current) return;
      startYRef.current = e.touches[0]!.clientY;
      pullingRef.current = true;
    };

    const handleTouchMove = (e: TouchEvent) => {
      if (!pullingRef.current) return;

      const currentY = e.touches[0]!.clientY;
      const diff = currentY - startYRef.current;

      if (diff > 0 && window.scrollY === 0) {
        const resistedDistance = Math.min(diff / 2.5, 120);
        pullDistanceRef.current = resistedDistance;
        setPullDistance(resistedDistance);
        if (resistedDistance > 5) {
          e.preventDefault();
        }
      } else {
        pullDistanceRef.current = 0;
        setPullDistance(0);
      }
    };

    const handleTouchEnd = async () => {
      if (!pullingRef.current) return;
      pullingRef.current = false;

      const currentPull = pullDistanceRef.current;
      pullDistanceRef.current = 0;

      if (currentPull >= threshold) {
        isRefreshingRef.current = true;
        setIsRefreshing(true);
        setPullDistance(0);
        try {
          await onRefreshRef.current();
        } finally {
          isRefreshingRef.current = false;
          setIsRefreshing(false);
        }
      } else {
        setPullDistance(0);
      }
    };

    window.addEventListener('touchstart', handleTouchStart, { passive: true });
    window.addEventListener('touchmove', handleTouchMove, { passive: false });
    window.addEventListener('touchend', handleTouchEnd, { passive: true });

    return () => {
      window.removeEventListener('touchstart', handleTouchStart);
      window.removeEventListener('touchmove', handleTouchMove);
      window.removeEventListener('touchend', handleTouchEnd);
    };
  }, [enabled, isTouchDevice, threshold]);

  return { pullDistance, isRefreshing };
}
