// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LinkPreview — displays an OG metadata preview card when a URL is detected
 * in the compose content. Debounces URL detection, fetches metadata from the
 * backend, and renders a dismissible card with image/title/description.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button, Skeleton } from '@heroui/react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { api } from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface LinkPreviewData {
  url: string;
  title?: string;
  description?: string;
  image?: string;
  siteName?: string;
}

interface LinkPreviewProps {
  content: string;
  onPreviewData?: (data: LinkPreviewData | null) => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const URL_REGEX = /https?:\/\/[^\s<]+/;
const DEBOUNCE_MS = 800;

/**
 * Extract the display domain from a URL string.
 * e.g. "https://www.example.com/path" -> "example.com"
 */
function extractDomain(url: string): string {
  try {
    const hostname = new URL(url).hostname;
    return hostname.replace(/^www\./, '');
  } catch {
    return url;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function LinkPreview({ content, onPreviewData }: LinkPreviewProps) {
  const { t } = useTranslation('feed');

  const [previewData, setPreviewData] = useState<LinkPreviewData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  // Track the last fetched URL to avoid redundant requests
  const lastFetchedUrl = useRef<string | null>(null);
  const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortController = useRef<AbortController | null>(null);

  const handleDismiss = useCallback(() => {
    setDismissed(true);
    setPreviewData(null);
    onPreviewData?.(null);
  }, [onPreviewData]);

  useEffect(() => {
    // Clear any pending debounce
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
    }

    const match = URL_REGEX.exec(content);
    const detectedUrl = match ? match[0] : null;

    // No URL found — clear preview
    if (!detectedUrl) {
      if (previewData) {
        setPreviewData(null);
        onPreviewData?.(null);
      }
      lastFetchedUrl.current = null;
      setDismissed(false);
      return;
    }

    // Same URL as last fetch — skip
    if (detectedUrl === lastFetchedUrl.current) {
      return;
    }

    // New URL detected — reset dismissed state
    if (detectedUrl !== lastFetchedUrl.current) {
      setDismissed(false);
    }

    // Debounce the fetch
    debounceTimer.current = setTimeout(() => {
      // Abort any in-flight request
      if (abortController.current) {
        abortController.current.abort();
      }

      const controller = new AbortController();
      abortController.current = controller;

      setIsLoading(true);

      const encodedUrl = encodeURIComponent(detectedUrl);

      api
        .get<LinkPreviewData>(`/v2/link-preview?url=${encodedUrl}`, {
          signal: controller.signal,
        })
        .then((res) => {
          if (controller.signal.aborted) return;

          if (res.success && res.data) {
            const data: LinkPreviewData = {
              url: res.data.url || detectedUrl,
              title: res.data.title,
              description: res.data.description,
              image: res.data.image,
              siteName: res.data.siteName,
            };
            setPreviewData(data);
            onPreviewData?.(data);
          } else {
            setPreviewData(null);
            onPreviewData?.(null);
          }

          lastFetchedUrl.current = detectedUrl;
        })
        .catch(() => {
          if (controller.signal.aborted) return;
          // Silently hide on error (404, network error, etc.)
          setPreviewData(null);
          onPreviewData?.(null);
          lastFetchedUrl.current = detectedUrl;
        })
        .finally(() => {
          if (!controller.signal.aborted) {
            setIsLoading(false);
          }
        });
    }, DEBOUNCE_MS);

    return () => {
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [content]);

  // Cleanup abort controller on unmount
  useEffect(() => {
    return () => {
      if (abortController.current) {
        abortController.current.abort();
      }
    };
  }, []);

  // ── Loading skeleton ────────────────────────────────────────────────────
  if (isLoading) {
    return (
      <div
        className="rounded-xl border border-[var(--border-default)] overflow-hidden bg-[var(--surface-elevated)] p-3"
        aria-label={t('compose.link_preview_loading')}
      >
        <Skeleton className="w-full h-32 rounded-lg mb-3" />
        <Skeleton className="h-4 w-3/4 rounded mb-2" />
        <Skeleton className="h-3 w-full rounded mb-1" />
        <Skeleton className="h-3 w-1/2 rounded mb-1" />
        <Skeleton className="h-3 w-1/4 rounded" />
      </div>
    );
  }

  // ── Nothing to show ─────────────────────────────────────────────────────
  if (!previewData || dismissed) {
    return null;
  }

  // ── Preview card ────────────────────────────────────────────────────────
  const domain = previewData.siteName || extractDomain(previewData.url);

  return (
    <div className="relative rounded-xl border border-[var(--border-default)] overflow-hidden bg-[var(--surface-elevated)]">
      {/* Dismiss button */}
      <Button
        isIconOnly
        size="sm"
        variant="flat"
        onPress={handleDismiss}
        className="absolute top-2 right-2 z-10 w-6 h-6 min-w-0 flex items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70 transition-colors"
        aria-label={t('compose.link_preview_remove')}
      >
        <X className="w-3.5 h-3.5" aria-hidden="true" />
      </Button>

      {/* Image */}
      {previewData.image && (
        <img
          src={previewData.image}
          alt={previewData.title || ''}
          className="w-full h-32 object-cover"
          loading="lazy"
          onError={(e) => {
            // Hide broken images
            (e.target as HTMLImageElement).style.display = 'none';
          }}
        />
      )}

      {/* Text content */}
      <div className="p-3">
        {previewData.title && (
          <p className="text-sm font-semibold text-[var(--text-primary)] line-clamp-1">
            {previewData.title}
          </p>
        )}
        {previewData.description && (
          <p className="text-xs text-[var(--text-muted)] line-clamp-2 mt-1">
            {previewData.description}
          </p>
        )}
        <p className="text-xs text-[var(--text-subtle)] mt-1">
          {domain}
        </p>
      </div>
    </div>
  );
}
