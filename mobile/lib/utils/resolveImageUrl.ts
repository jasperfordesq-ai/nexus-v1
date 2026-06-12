// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { API_BASE_URL } from '@/lib/constants';

/**
 * Ensures a media URL is absolute. The API returns relative paths like
 * `/uploads/avatars/123.jpg` or `/uploads/2/voice_messages/voice_x.m4a`.
 * React Native's Image/expo-image AND expo-av all require absolute URLs —
 * relative paths silently fail to load (voice messages showed "Failed").
 *
 * Pass through unchanged: null, undefined, already-absolute URLs
 * (http/https/data:) and local URIs (file://, content://) — the latter are
 * used by optimistic just-recorded/just-picked media.
 * Prefix with API_BASE_URL: server-relative paths starting with '/'.
 */
export function resolveMediaUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  if (
    url.startsWith('http://') ||
    url.startsWith('https://') ||
    url.startsWith('data:') ||
    url.startsWith('file:') ||
    url.startsWith('content:')
  ) {
    return url;
  }
  if (url.startsWith('/')) {
    return `${API_BASE_URL}${url}`;
  }
  return url;
}

/**
 * Alias kept for the many existing image call sites; the implementation is
 * media-type agnostic. Prefer {@link resolveMediaUrl} for new code.
 */
export const resolveImageUrl = resolveMediaUrl;
