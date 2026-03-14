// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Converts an ISO date string to a human-readable relative time string.
 *
 * @param iso - ISO 8601 date string
 * @param short - If true, returns compact format (e.g. "5m") instead of "5m ago"
 */
export function formatRelativeTime(iso: string, short = false): string {
  const diff = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diff / 60_000);

  if (minutes < 1) return short ? 'now' : 'just now';
  if (minutes < 60) return short ? `${minutes}m` : `${minutes}m ago`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return short ? `${hours}h` : `${hours}h ago`;

  const days = Math.floor(hours / 24);
  if (short) return `${days}d`;
  if (days < 7) return `${days}d ago`;

  return new Date(iso).toLocaleDateString();
}
