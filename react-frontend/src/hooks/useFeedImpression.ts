// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useFeedImpression — tracks when a feed card becomes ≥50% visible for ≥1 second,
 * then fires POST /api/v2/feed/posts/{id}/impression (fire-and-forget).
 *
 * Deduplication is handled by a module-level Set so each post ID fires at most
 * once per feed load. Call `resetFeedImpressions()` when the feed reloads.
 */

import { useEffect, useRef } from 'react';
import { api } from '@/lib/api';

/** Shared across all hook instances — cleared on feed reload. */
const reportedIds = new Set<number>();

/** Call this whenever the feed performs a fresh load (not append). */
export function resetFeedImpressions(): void {
  reportedIds.clear();
}

/**
 * Observes `ref` and fires an impression event when the element has been
 * ≥50% visible for ≥1 second. Safe to call unconditionally; does nothing
 * if `ref.current` is null or the post has already been reported this session.
 */
export function useFeedImpression(
  postId: number,
  ref: React.RefObject<HTMLElement | null>,
): void {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (reportedIds.has(postId)) return;

    const el = ref.current;
    if (!el) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        if (!entry) return;

        if (entry.isIntersecting) {
          // Start 1-second timer — fire impression after sustained visibility
          timerRef.current = setTimeout(() => {
            if (reportedIds.has(postId)) return;
            reportedIds.add(postId);
            // Fire-and-forget — never throw or block rendering
            api.post(`/v2/feed/posts/${postId}/impression`).catch(() => {});
            observer.disconnect();
          }, 1000);
        } else {
          // Element left viewport before 1 second elapsed — cancel the timer
          if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
          }
        }
      },
      { threshold: 0.5 },
    );

    observer.observe(el);

    return () => {
      observer.disconnect();
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
    };
  // postId and ref are stable per card instance; re-run only if they change
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [postId]);
}
