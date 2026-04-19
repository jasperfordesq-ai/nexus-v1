// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Helper utilities for the NEXUS React frontend
 */

import type { User } from '@/types/api';
import { logError } from './logger';
import i18n from '../i18n';

type DateValue = Date | number | string;

/**
 * API Base URL for resolving relative image/asset URLs
 * This is the PHP backend URL where uploads are stored
 */
const API_ASSET_BASE = import.meta.env.VITE_API_BASE?.replace(/\/api$/, '') || (import.meta.env.DEV ? '' : 'https://api.project-nexus.ie');

/**
 * Resolve a relative URL to an absolute URL pointing to the API server.
 * Used for images, avatars, and other assets served from the PHP backend.
 *
 * @param url - The URL to resolve (can be relative like '/uploads/...' or already absolute)
 * @param fallback - Optional fallback if url is null/empty
 * @returns Absolute URL or fallback
 */
export function resolveAssetUrl(url: string | null | undefined, fallback?: string): string {
  if (!url) {
    return fallback || '';
  }

  // Already absolute
  if (url.startsWith('http://') || url.startsWith('https://')) {
    // Re-root upload paths through the API server ONLY if the URL is from a known
    // local domain (stale DB rows). External partner URLs must be left as-is so
    // avatars from federation partners load from the correct server.
    try {
      const parsed = new URL(url);
      if (parsed.pathname.startsWith('/uploads/')) {
        const apiHost = API_ASSET_BASE ? new URL(API_ASSET_BASE).host : '';
        const knownLocalDomains = [apiHost, 'hour-timebank.ie', 'app.project-nexus.ie'];
        if (knownLocalDomains.some(d => d && parsed.host === d)) {
          return API_ASSET_BASE + parsed.pathname;
        }
      }
    } catch {
      // Not a valid URL — fall through and return as-is
    }
    return url;
  }

  // Protocol-relative
  if (url.startsWith('//')) {
    return 'https:' + url;
  }

  // Relative path - prepend API base
  const cleanUrl = url.startsWith('/') ? url : '/' + url;
  return API_ASSET_BASE + cleanUrl;
}

/**
 * Resolve an avatar URL with a default fallback
 */
export function resolveAvatarUrl(url: string | null | undefined): string {
  return resolveAssetUrl(url, `${API_ASSET_BASE}/assets/img/defaults/default_avatar.png`);
}

/**
 * Get a user's display name from their first and last name
 */
export function getUserDisplayName(user: Pick<User, 'first_name' | 'last_name'>): string {
  return `${user.first_name} ${user.last_name}`.trim();
}

/**
 * Get a user's initials for avatar fallback
 */
export function getUserInitials(user: Pick<User, 'first_name' | 'last_name'>): string {
  return `${user.first_name?.[0] ?? ''}${user.last_name?.[0] ?? ''}`.toUpperCase();
}

/**
 * Format a date string to a relative time string
 */
export function formatRelativeTime(dateString: string): string {
  const date = toDateValue(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSecs = Math.floor(diffMs / 1000);
  const diffMins = Math.floor(diffSecs / 60);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);
  const diffWeeks = Math.floor(diffDays / 7);

  const rtf = new Intl.RelativeTimeFormat(i18n.language, { numeric: 'auto', style: 'narrow' });

  if (diffSecs < 60) return rtf.format(-diffSecs, 'second');
  if (diffMins < 60) return rtf.format(-diffMins, 'minute');
  if (diffHours < 24) return rtf.format(-diffHours, 'hour');
  if (diffDays < 7) return rtf.format(-diffDays, 'day');
  if (diffDays < 30) return rtf.format(-diffWeeks, 'week');

  return formatDateValue(date);
}

/**
 * Normalize date-like values before formatting.
 */
function toDateValue(value: DateValue): Date {
  if (value instanceof Date) {
    return value;
  }

  if (typeof value === 'string' && value.includes(' ') && !value.includes('T')) {
    return new Date(value.replace(' ', 'T'));
  }

  return new Date(value);
}

/**
 * Format a date-like value for display.
 */
export function formatDateValue(value: DateValue, options?: Intl.DateTimeFormatOptions): string {
  return toDateValue(value).toLocaleDateString(i18n.language, options ?? {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Backwards-compatible date helper for existing call sites.
 */
export function formatDate(dateString: string, options?: Intl.DateTimeFormatOptions): string {
  return formatDateValue(dateString, options);
}

/**
 * Format a date-time value for display.
 */
export function formatDateTime(value: DateValue, options?: Intl.DateTimeFormatOptions): string {
  return toDateValue(value).toLocaleString(i18n.language, options);
}

/**
 * Format a time for display.
 */
export function formatTime(dateString: string): string {
  return toDateValue(dateString).toLocaleTimeString(i18n.language, {
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Format a locale-aware number string.
 */
export function formatNumber(value: number, options?: Intl.NumberFormatOptions): string {
  return new Intl.NumberFormat(i18n.language, options).format(value);
}

/**
 * Format a locale-aware currency string.
 */
export function formatCurrency(
  value: number,
  currency: string,
  options?: Omit<Intl.NumberFormatOptions, 'style' | 'currency'>
): string {
  return new Intl.NumberFormat(i18n.language, {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    ...options,
  }).format(value);
}

/**
 * Format a short month label for date badges and compact cards.
 */
export function formatMonthShort(value: DateValue, uppercase = false): string {
  const formatted = new Intl.DateTimeFormat(i18n.language, { month: 'short' }).format(toDateValue(value));
  return uppercase ? formatted.toUpperCase() : formatted;
}

/**
 * Format the day-of-month portion of a date-like value.
 */
export function formatDayOfMonth(value: DateValue): string {
  return String(toDateValue(value).getDate());
}

/**
 * Truncate text with ellipsis
 */
export function truncate(text: string, maxLength: number): string {
  if (text.length <= maxLength) return text;
  return `${text.slice(0, maxLength)}...`;
}

/**
 * Format hours for display
 */
export function formatHours(hours: number): string {
  return i18n.t('common:hours_display', { count: hours, defaultValue: `${hours} hours` });
}

/**
 * Debounce function for search inputs
 */
export function debounce<T extends (...args: unknown[]) => unknown>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: ReturnType<typeof setTimeout> | null = null;

  return function (this: unknown, ...args: Parameters<T>) {
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

/**
 * Class name utility (simple cn replacement)
 */
export function cn(...classes: (string | undefined | null | false)[]): string {
  return classes.filter(Boolean).join(' ');
}

/**
 * Storage helpers with error handling
 */
export const storage = {
  get<T>(key: string, defaultValue: T): T {
    try {
      const item = localStorage.getItem(key);
      return item ? JSON.parse(item) : defaultValue;
    } catch {
      return defaultValue;
    }
  },

  set<T>(key: string, value: T): void {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch {
      logError(`Failed to save ${key} to localStorage`);
    }
  },

  remove(key: string): void {
    try {
      localStorage.removeItem(key);
    } catch {
      logError(`Failed to remove ${key} from localStorage`);
    }
  },
};
