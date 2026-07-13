// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { resolveAssetUrl } from '@/lib/helpers';

/**
 * Return a platform-hosted podcast image URL or null.
 *
 * Podcast artwork is creator-controlled. Loading arbitrary remote images would
 * disclose every listener's IP address and user agent to that creator. The API
 * rejects new remote artwork; this guard also protects clients from historical
 * rows and stale caches.
 */
export function safePodcastArtworkUrl(value: string | null | undefined): string | null {
  const input = value?.trim();
  if (!input || typeof window === 'undefined') return null;

  try {
    const resolved = resolveAssetUrl(input);
    if (!resolved) return null;

    const candidate = new URL(resolved, window.location.origin);
    const assetSentinel = new URL(resolveAssetUrl('/uploads/__podcast_origin_check__'), window.location.origin);
    const isPlatformOrigin = candidate.origin === window.location.origin || candidate.origin === assetSentinel.origin;
    const isPlatformPath = candidate.pathname.startsWith('/uploads/') || candidate.pathname.startsWith('/storage/');

    return isPlatformOrigin && isPlatformPath ? candidate.toString() : null;
  } catch {
    return null;
  }
}
