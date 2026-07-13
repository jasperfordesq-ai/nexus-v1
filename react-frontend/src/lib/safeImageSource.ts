// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import DOMPurify from 'dompurify';

export interface SafeImageSourceOptions {
  /** Allow browser-generated object URLs used for local file previews. */
  allowBlob?: boolean;
}

function purifyUrlText(value: string): string | null {
  // Keep the scanner-recognised DOMPurify boundary adjacent to the JSX URL
  // sink. RETURN_DOM_FRAGMENT lets us read textContent without HTML-entity
  // changes to query strings such as `?width=640&height=360`.
  const fragment = DOMPurify.sanitize(value, {
    ALLOWED_TAGS: [],
    ALLOWED_ATTR: [],
    KEEP_CONTENT: true,
    RETURN_DOM_FRAGMENT: true,
  });
  const purified = fragment.textContent;
  return purified === value ? purified : null;
}

/**
 * Parse and normalise an image source at the JSX boundary.
 *
 * Images may use HTTP(S), relative paths resolved against this application, or
 * explicitly opted-in browser blob URLs. Active and inline schemes such as
 * javascript:, data:, file:, and vbscript: never reach an <img src> sink.
 */
export function safeImageSource(
  value: string | null | undefined,
  options: SafeImageSourceOptions = {},
): string | null {
  const candidate = value?.trim();
  if (!candidate) return null;

  try {
    const baseUrl = typeof window === 'undefined'
      ? 'https://app.project-nexus.ie/'
      : window.location.href;
    const parsed = new URL(candidate, baseUrl);
    const safeNetworkSource = parsed.protocol === 'https:' || parsed.protocol === 'http:';
    const safeObjectSource = options.allowBlob === true && parsed.protocol === 'blob:';
    return safeNetworkSource || safeObjectSource ? purifyUrlText(parsed.href) : null;
  } catch {
    return null;
  }
}
