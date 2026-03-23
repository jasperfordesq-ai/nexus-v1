// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Add alpha (opacity) to a hex color string.
 *
 * Works with 3-digit (#RGB), 6-digit (#RRGGBB), or raw RRGGBB hex strings.
 * Falls back gracefully: if the input is not a valid hex color, returns
 * a transparent fallback so the UI never crashes.
 *
 * @param hex    The base color, e.g. '#3B82F6' or '3B82F6'
 * @param alpha  Opacity from 0 (transparent) to 1 (opaque)
 * @returns      8-digit hex string '#RRGGBBAA'
 */
export function withAlpha(hex: string, alpha: number): string {
  const clamped = Math.max(0, Math.min(1, alpha));
  const alphaHex = Math.round(clamped * 255)
    .toString(16)
    .padStart(2, '0');

  // Strip leading # if present
  let raw = hex.replace(/^#/, '');

  // Expand 3-digit shorthand (#RGB → RRGGBB)
  if (raw.length === 3) {
    raw = raw[0] + raw[0] + raw[1] + raw[1] + raw[2] + raw[2];
  }

  // Validate we have a 6-digit hex
  if (!/^[0-9a-fA-F]{6}$/.test(raw)) {
    // Fallback: return a semi-transparent gray
    return `#9ca3af${alphaHex}`;
  }

  return `#${raw}${alphaHex}`;
}

/**
 * Return either '#FFFFFF' or '#000000' depending on which provides
 * better contrast against the given background color (WCAG luminance).
 *
 * @param bgHex  Background hex color, e.g. '#3B82F6'
 * @returns      '#FFFFFF' or '#000000'
 */
export function contrastText(bgHex: string): string {
  let raw = bgHex.replace(/^#/, '');

  if (raw.length === 3) {
    raw = raw[0] + raw[0] + raw[1] + raw[1] + raw[2] + raw[2];
  }

  if (!/^[0-9a-fA-F]{6}$/.test(raw)) {
    return '#FFFFFF'; // default to white on unknown
  }

  const r = parseInt(raw.slice(0, 2), 16) / 255;
  const g = parseInt(raw.slice(2, 4), 16) / 255;
  const b = parseInt(raw.slice(4, 6), 16) / 255;

  // Relative luminance (WCAG 2.x formula)
  const toLinear = (c: number) =>
    c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);

  const L = 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);

  // Luminance > 0.179 → dark text has better contrast
  return L > 0.179 ? '#000000' : '#FFFFFF';
}
