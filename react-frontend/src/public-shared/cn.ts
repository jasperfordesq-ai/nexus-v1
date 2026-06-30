// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Minimal class-name join for shared components. Kept dependency-free (no clsx /
 * tailwind-merge) so the shared presentational core has no runtime coupling. The
 * shared public components do not rely on conflicting-class merge behaviour.
 */
export function cn(...parts: Array<string | false | null | undefined>): string {
  return parts.filter(Boolean).join(' ');
}
