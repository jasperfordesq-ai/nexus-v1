// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { dateLocale } from '@/lib/utils/dateLocale';

/**
 * Formats a date value as a human-readable absolute date string.
 *
 * @param date - ISO 8601 string or Date object
 */
export function formatDate(date: string | Date): string {
  return new Date(date).toLocaleDateString(dateLocale(), {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

/**
 * Whether the runtime can localize relative times. Hermes builds without
 * Intl.RelativeTimeFormat fall back to compact English strings.
 */
const relativeFormatterAvailable =
  typeof Intl !== 'undefined' &&
  typeof (Intl as { RelativeTimeFormat?: unknown }).RelativeTimeFormat === 'function';

/**
 * Converts an ISO date string to a human-readable relative time string in the
 * app's active language (e.g. "vor 5 Min.", "il y a 2 h").
 *
 * @param iso - ISO 8601 date string
 * @param short - If true, returns compact format (e.g. "5m") instead of "5m ago"
 */
export function formatRelativeTime(iso: string, short = false): string {
  if (!iso) return short ? 'now' : 'just now';

  const parsed = new Date(iso);
  if (isNaN(parsed.getTime())) return short ? 'now' : 'just now';

  const diff = Date.now() - parsed.getTime();
  const minutes = Math.floor(diff / 60_000);

  if (minutes < 1) return short ? 'now' : 'just now';

  if (relativeFormatterAvailable) {
    try {
      const rtf = new Intl.RelativeTimeFormat(dateLocale(), {
        numeric: 'always',
        style: short ? 'narrow' : 'short',
      });
      if (minutes < 60) return rtf.format(-minutes, 'minute');
      const localizedHours = Math.floor(minutes / 60);
      if (localizedHours < 24) return rtf.format(-localizedHours, 'hour');
      const localizedDays = Math.floor(localizedHours / 24);
      if (localizedDays < 7 || short) return rtf.format(-localizedDays, 'day');
      return parsed.toLocaleDateString(dateLocale());
    } catch {
      // Unsupported locale tag or partial Intl build — use the fallback below.
    }
  }

  if (minutes < 60) return short ? `${minutes}m` : `${minutes}m ago`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return short ? `${hours}h` : `${hours}h ago`;

  const days = Math.floor(hours / 24);
  if (short) return `${days}d`;
  if (days < 7) return `${days}d ago`;

  return parsed.toLocaleDateString(dateLocale());
}
