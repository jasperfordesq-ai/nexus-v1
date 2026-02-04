/**
 * Helper utilities for the NEXUS React frontend
 */

import type { User } from '@/types/api';
import { logError } from './logger';

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
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  if (diffDays < 30) return `${Math.floor(diffDays / 7)}w ago`;

  return date.toLocaleDateString();
}

/**
 * Format a date for display
 */
export function formatDate(dateString: string, options?: Intl.DateTimeFormatOptions): string {
  return new Date(dateString).toLocaleDateString('en-US', options ?? {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format a time for display
 */
export function formatTime(dateString: string): string {
  return new Date(dateString).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  });
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
  if (hours === 1) return '1 hour';
  return `${hours} hours`;
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
