// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useFeedTracking — IntersectionObserver-based impression tracking + click tracking
 * for EdgeRank Signal 13 (CTR Feedback Loop).
 *
 * Fires a POST /v2/feed/posts/{id}/impression when >=50% of a feed card
 * is visible for >=1 second. Each post is tracked at most once per page load.
 *
 * Provides recordClick() to fire POST /v2/feed/posts/{id}/click.
 */

import { useRef, useEffect, useCallback } from 'react';
import { api } from '@/lib/api';

/** Set of post IDs already tracked this session (prevents duplicates) */
const impressedIds = new Set<number>();

export function useFeedTracking(postId: number, isAuthenticated: boolean) {
  const ref = useRef<HTMLDivElement>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!isAuthenticated || !postId || impressedIds.has(postId)) return;

    const el = ref.current;
    if (!el) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          // Start 1-second timer
          timerRef.current = setTimeout(() => {
            if (!impressedIds.has(postId)) {
              impressedIds.add(postId);
              // Fire-and-forget — don't block UI
              api.post(`/v2/feed/posts/${postId}/impression`, {}).catch(() => {});
            }
          }, 1000);
        } else {
          // Left viewport before 1s — cancel
          if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
          }
        }
      },
      { threshold: 0.5 }
    );

    observer.observe(el);

    return () => {
      observer.disconnect();
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
    };
  }, [postId, isAuthenticated]);

  const recordClick = useCallback(() => {
    if (!isAuthenticated || !postId) return;
    api.post(`/v2/feed/posts/${postId}/click`, {}).catch(() => {});
  }, [postId, isAuthenticated]);

  return { ref, recordClick };
}
