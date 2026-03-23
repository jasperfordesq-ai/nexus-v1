// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MessageLinkPreview — Detects URLs in message text and renders a compact
 * link preview card below the message body.
 *
 * Uses the same /v2/link-preview endpoint as the compose editor.
 * Only shows the first detected URL to keep messages clean.
 * Results are cached in a module-level Map to avoid refetching for
 * the same URL across renders.
 */

import { useEffect, useState, useRef } from 'react';
import { api } from '@/lib/api';
import { LinkPreviewCard } from '@/components/social/LinkPreviewCard';
import type { LinkPreview } from '@/components/social/LinkPreviewCard';

/* ───────────────────────── Cache ───────────────────────── */

/** Module-level cache to avoid refetching previews for the same URL */
const previewCache = new Map<string, LinkPreview | null>();

/* ───────────────────────── Constants ───────────────────────── */

const URL_REGEX = /https?:\/\/[^\s<>"')\]]+/;

/* ───────────────────────── Component ───────────────────────── */

interface MessageLinkPreviewProps {
  text: string;
}

export function MessageLinkPreview({ text }: MessageLinkPreviewProps) {
  const [preview, setPreview] = useState<LinkPreview | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    if (!text) return;

    const match = URL_REGEX.exec(text);
    if (!match) return;

    const url = match[0].replace(/[.,;:!?)>]+$/, '');

    // Check cache first
    if (previewCache.has(url)) {
      const cached = previewCache.get(url);
      if (cached) {
        setPreview(cached);
      }
      return;
    }

    // Fetch preview
    const controller = new AbortController();
    abortRef.current = controller;

    const encodedUrl = encodeURIComponent(url);
    api
      .get<LinkPreview>(`/v2/link-preview?url=${encodedUrl}`, {
        signal: controller.signal,
      })
      .then((res) => {
        if (controller.signal.aborted) return;
        if (res.success && res.data) {
          const data: LinkPreview = {
            ...res.data,
            url: res.data.url || url,
          };
          previewCache.set(url, data);
          setPreview(data);
        } else {
          previewCache.set(url, null);
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          previewCache.set(url, null);
        }
      });

    return () => {
      controller.abort();
    };
  }, [text]);

  if (!preview) return null;

  return (
    <div className="mt-2 max-w-[280px]">
      <LinkPreviewCard preview={preview} compact />
    </div>
  );
}

export default MessageLinkPreview;
