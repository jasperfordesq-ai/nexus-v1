// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useLinkPreview — Debounced URL extraction and preview fetching hook.
 *
 * Used in the compose/post editor to show link previews while the user types.
 * Extracts URLs from text content, debounces the detection (500ms), and fetches
 * OG metadata from the backend API.
 *
 * Returns previews array, loading state, and a removePreview function for
 * dismissing individual previews.
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { api } from '@/lib/api';
import type { LinkPreview } from '@/components/social/LinkPreviewCard';

/* ───────────────────────── Constants ───────────────────────── */

/** Regex for extracting URLs from text */
const URL_REGEX = /https?:\/\/[^\s<>"')\]]+/g;

/** Debounce delay in ms */
const DEBOUNCE_MS = 500;

/* ───────────────────────── Types ───────────────────────── */

interface UseLinkPreviewReturn {
  /** Array of fetched link previews */
  previews: LinkPreview[];
  /** Whether a preview is currently being fetched */
  loading: boolean;
  /** Remove a preview by URL (user dismissed it) */
  removePreview: (url: string) => void;
}

/* ───────────────────────── Hook ───────────────────────── */

export function useLinkPreview(text: string): UseLinkPreviewReturn {
  const [previews, setPreviews] = useState<LinkPreview[]>([]);
  const [loading, setLoading] = useState(false);

  // Track dismissed URLs so they don't reappear
  const dismissedUrls = useRef<Set<string>>(new Set());
  // Track already-fetched URLs to avoid redundant requests
  const fetchedUrls = useRef<Set<string>>(new Set());
  // Debounce timer
  const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Abort controller for in-flight requests
  const abortController = useRef<AbortController | null>(null);

  const removePreview = useCallback((url: string) => {
    dismissedUrls.current.add(url);
    setPreviews((prev) => prev.filter((p) => p.url !== url));
  }, []);

  useEffect(() => {
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
    }

    debounceTimer.current = setTimeout(async () => {
      // Extract URLs from text
      const plainText = text.replace(/<[^>]+>/g, ' '); // Strip HTML tags
      const matches = plainText.match(URL_REGEX);

      if (!matches || matches.length === 0) {
        // No URLs — clear previews for URLs no longer in text
        setPreviews((prev) => prev.filter((p) => {
          const stillInText = plainText.includes(p.url);
          if (!stillInText) {
            fetchedUrls.current.delete(p.url);
          }
          return stillInText;
        }));
        return;
      }

      // Deduplicate and clean
      const uniqueUrls = [...new Set(
        matches.map((url) => url.replace(/[.,;:!?)>]+$/, ''))
      )];

      // Find new URLs that haven't been fetched or dismissed
      const newUrls = uniqueUrls.filter(
        (url) => !fetchedUrls.current.has(url) && !dismissedUrls.current.has(url)
      );

      // Remove previews for URLs no longer in the text
      setPreviews((prev) => prev.filter((p) => {
        const stillInText = uniqueUrls.includes(p.url);
        if (!stillInText) {
          fetchedUrls.current.delete(p.url);
        }
        return stillInText;
      }));

      if (newUrls.length === 0) {
        return;
      }

      // Abort previous in-flight requests
      if (abortController.current) {
        abortController.current.abort();
      }
      const controller = new AbortController();
      abortController.current = controller;

      setLoading(true);

      // Fetch previews for new URLs (limit to first 3)
      const urlsToFetch = newUrls.slice(0, 3);

      try {
        const results = await Promise.allSettled(
          urlsToFetch.map((url) => {
            const encodedUrl = encodeURIComponent(url);
            return api.get<LinkPreview>(`/v2/link-preview?url=${encodedUrl}`, {
              signal: controller.signal,
            });
          })
        );

        if (controller.signal.aborted) return;

        const newPreviews: LinkPreview[] = [];
        results.forEach((result, i) => {
          const url = urlsToFetch[i] ?? '';
          fetchedUrls.current.add(url);

          if (
            result.status === 'fulfilled' &&
            result.value.success &&
            result.value.data
          ) {
            newPreviews.push({
              ...result.value.data,
              url: result.value.data.url || url,
            });
          }
        });

        if (newPreviews.length > 0) {
          setPreviews((prev) => {
            // Merge new previews with existing, avoid duplicates
            const existing = new Set(prev.map((p) => p.url));
            const merged = [...prev];
            for (const np of newPreviews) {
              if (!existing.has(np.url) && !dismissedUrls.current.has(np.url)) {
                merged.push(np);
              }
            }
            return merged;
          });
        }
      } catch {
        // Network errors, aborts — silently ignore
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    }, DEBOUNCE_MS);

    return () => {
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }
    };
  }, [text]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (abortController.current) {
        abortController.current.abort();
      }
    };
  }, []);

  return { previews, loading, removePreview };
}

export default useLinkPreview;
