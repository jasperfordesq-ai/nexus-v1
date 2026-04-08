// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useBookmarkCollections — Fetches and caches the user's bookmark collections.
 *
 * Provides the list, a create method, and a refetch method.
 * Caches collections for the session to avoid redundant API calls.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export interface BookmarkCollection {
  id: number;
  name: string;
  description: string | null;
  is_default: boolean;
  bookmarks_count: number;
}

let cachedCollections: BookmarkCollection[] | null = null;

export function useBookmarkCollections() {
  const [collections, setCollections] = useState<BookmarkCollection[]>(cachedCollections ?? []);
  const [isLoading, setIsLoading] = useState(!cachedCollections);
  const fetchedRef = useRef(!!cachedCollections);

  const fetchCollections = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await api.get<BookmarkCollection[]>('/v2/bookmark-collections');
      if (res.success && Array.isArray(res.data)) {
        cachedCollections = res.data;
        setCollections(res.data);
      }
    } catch (err) {
      logError('Failed to fetch bookmark collections', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!fetchedRef.current) {
      fetchedRef.current = true;
      fetchCollections();
    }
  }, [fetchCollections]);

  const createCollection = useCallback(async (name: string): Promise<BookmarkCollection | null> => {
    try {
      const res = await api.post<BookmarkCollection>('/v2/bookmark-collections', { name });
      if (res.success && res.data) {
        const newCol = res.data;
        cachedCollections = [...(cachedCollections ?? []), newCol];
        setCollections(cachedCollections);
        return newCol;
      }
    } catch (err) {
      logError('Failed to create bookmark collection', err);
    }
    return null;
  }, []);

  return { collections, isLoading, fetchCollections, createCollection };
}
