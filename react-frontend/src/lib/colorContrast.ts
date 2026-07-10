// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const BLACK = '#000000';
const WHITE = '#ffffff';

function hexToRelativeLuminance(color: string): number | null {
  const normalized = color.trim().replace(/^#/, '');
  const hex = normalized.length === 3
    ? normalized.split('').map((character) => character.repeat(2)).join('')
    : normalized;

  if (!/^[0-9a-f]{6}$/i.test(hex)) {
    return null;
  }

  const [red = 0, green = 0, blue = 0] = [0, 2, 4].map((offset) => {
    const srgb = Number.parseInt(hex.slice(offset, offset + 2), 16) / 255;
    return srgb <= 0.04045 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
  });

  return (0.2126 * red) + (0.7152 * green) + (0.0722 * blue);
}

/**
 * Return the black or white foreground with the higher WCAG contrast ratio.
 * At least one of these two colors always reaches 4.5:1 for a valid solid
 * background, including mid-tone brand colors near the crossover point.
 */
export function getAccessibleForegroundColor(
  backgroundColor: string,
  fallback = WHITE,
): string {
  const luminance = hexToRelativeLuminance(backgroundColor);
  if (luminance === null) {
    return fallback;
  }

  const blackContrast = (luminance + 0.05) / 0.05;
  const whiteContrast = 1.05 / (luminance + 0.05);

  return blackContrast >= whiteContrast ? BLACK : WHITE;
}
