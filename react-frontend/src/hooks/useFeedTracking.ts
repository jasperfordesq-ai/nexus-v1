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
 *
 * The IntersectionObserver is shared across every feed card via
 * `useSharedFeedObserver`, so a 50-card feed registers ONE observer instead
 * of 50 + entries.
 */

import { useRef, useEffect, useCallback } from 'react';
import { api } from '@/lib/api';
import { readStoredConsent } from '@/contexts/CookieConsentContext';
import { useSharedFeedObserver } from '@/hooks/useSharedFeedObserver';

/** Set of post IDs already tracked this session (prevents duplicates) */
const impressedIds = new Set<string>();

/** Call this whenever the feed performs a fresh (non-append) load to clear dedup state. */
export function resetImpressions(): void {
  impressedIds.clear();
}

export function useFeedTracking(targetId: number, targetType: string, isAuthenticated: boolean) {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const targetKey = `${targetType}:${targetId}`;

  // Consent + auth gate. Read once per render — readStoredConsent is sync and cheap.
  const consent = readStoredConsent();
  const enabled = Boolean(consent?.analytics) && isAuthenticated && Boolean(targetId);

  const handleEntry = useCallback(
    (entry: IntersectionObserverEntry) => {
      if (entry.isIntersecting) {
        if (impressedIds.has(targetKey)) return;
        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => {
          if (!impressedIds.has(targetKey)) {
            impressedIds.add(targetKey);
            api.post('/v2/feed/impression', {
              target_type: targetType,
              target_id: targetId,
            }).catch(() => {});
          }
        }, 1000);
      } else if (timerRef.current) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
    },
    [targetId, targetKey, targetType]
  );

  const setRef = useSharedFeedObserver(handleEntry, {
    threshold: 0.5,
    enabled,
  });

  // Clear pending timer on unmount
  useEffect(() => {
    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, []);

  const recordClick = useCallback(() => {
    const c = readStoredConsent();
    if (!c?.analytics) return;
    if (!isAuthenticated || !targetId) return;
    api.post('/v2/feed/click', {
      target_type: targetType,
      target_id: targetId,
    }).catch(() => {});
  }, [targetId, targetType, isAuthenticated]);

  return { ref: setRef, recordClick };
}
