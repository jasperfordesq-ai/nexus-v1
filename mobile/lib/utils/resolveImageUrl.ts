// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { API_BASE_URL } from '@/lib/constants';

/**
 * Ensures an image URL is absolute. The API sometimes returns relative paths
 * like `/uploads/avatars/123.jpg`. React Native's Image component and
 * expo-image both require absolute URLs — relative paths silently fail.
 *
 * Pass through: null, undefined, already-absolute URLs (http/https/data:).
 * Prefix with API_BASE_URL: paths starting with '/'.
 */
export function resolveImageUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:')) {
    return url;
  }
  if (url.startsWith('/')) {
    return `${API_BASE_URL}${url}`;
  }
  return url;
}
