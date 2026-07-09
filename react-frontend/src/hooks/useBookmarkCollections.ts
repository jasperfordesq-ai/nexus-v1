// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useBookmarkCollections — Fetches and caches the user's bookmark collections.
 *
 * Provides the list, a create method, and a refetch method.
 * Caches collections for the session to avoid redundant API calls.
 *
 * Privacy: the module-level cache is keyed by the authenticated user (and the
 * active tenant) and is discarded whenever the auth user changes — including
 * logout. Without this, on a shared browser user B would be served user A's
 * private collection names/counts and could even write bookmarks against A's
 * collection ids.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export interface BookmarkCollection {
  id: number;
  name: string;
  description: string | null;
  is_default: boolean;
  bookmarks_count: number;
}

interface CollectionsCache {
  /** Identity (tenant:user) the cached collections belong to */
  key: string;
  collections: BookmarkCollection[];
}

let cache: CollectionsCache | null = null;

/**
 * Cache key for the current auth identity. Includes the active tenant id
 * (when available) as belt-and-braces scoping on top of the user id.
 * Returns null when logged out — nothing is served from or written to the
 * cache in that case.
 */
function getCacheKey(userId: number | null | undefined): string | null {
  if (userId == null) return null;
  let tenantId: string | null = null;
  try {
    tenantId = window.localStorage.getItem('nexus_tenant_id');
  } catch {
    // Storage unavailable — fall back to user-only scoping.
  }
  return `${tenantId ?? 'unknown'}:${userId}`;
}

export function useBookmarkCollections() {
  const { user } = useAuth();
  const cacheKey = getCacheKey(user?.id);

  const cachedForKey =
    cache !== null && cacheKey !== null && cache.key === cacheKey ? cache.collections : null;

  const [collections, setCollections] = useState<BookmarkCollection[]>(cachedForKey ?? []);
  const [isLoading, setIsLoading] = useState(cacheKey !== null && cachedForKey === null);
  // Which cache key this hook instance last handled (false = none yet)
  const handledKeyRef = useRef<string | null | false>(false);

  const fetchCollections = useCallback(async () => {
    if (cacheKey === null) {
      // No authenticated user — nothing to fetch and nothing to cache.
      setCollections([]);
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    try {
      const res = await api.get<BookmarkCollection[]>('/v2/bookmark-collections');
      if (res.success && Array.isArray(res.data)) {
        cache = { key: cacheKey, collections: res.data };
        setCollections(res.data);
      }
    } catch (err) {
      logError('Failed to fetch bookmark collections', err);
    } finally {
      setIsLoading(false);
    }
  }, [cacheKey]);

  useEffect(() => {
    if (handledKeyRef.current === cacheKey) return;
    handledKeyRef.current = cacheKey;

    if (cacheKey === null) {
      // Logged out (or auth user changed to none): drop the module cache so
      // the next authenticated user can never see this user's collections.
      cache = null;
      setCollections([]);
      setIsLoading(false);
      return;
    }

    if (cache !== null && cache.key === cacheKey) {
      // Cache belongs to the current user — serve it.
      setCollections(cache.collections);
      setIsLoading(false);
      return;
    }

    // Cache is empty or belongs to a different user/tenant — discard it and
    // clear any previously rendered collections before refetching, so the
    // prior user's data is never shown even transiently.
    cache = null;
    setCollections([]);
    fetchCollections();
  }, [cacheKey, fetchCollections]);

  const createCollection = useCallback(async (name: string): Promise<BookmarkCollection | null> => {
    try {
      const res = await api.post<BookmarkCollection>('/v2/bookmark-collections', { name });
      if (res.success && res.data) {
        const newCol = res.data;
        const base =
          cache !== null && cacheKey !== null && cache.key === cacheKey ? cache.collections : [];
        const next = [...base, newCol];
        if (cacheKey !== null) {
          cache = { key: cacheKey, collections: next };
        }
        setCollections(next);
        return newCol;
      }
    } catch (err) {
      logError('Failed to create bookmark collection', err);
    }
    return null;
  }, [cacheKey]);

  return { collections, isLoading, fetchCollections, createCollection };
}
