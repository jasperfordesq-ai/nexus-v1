// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { getAccessibleForegroundColor } from './colorContrast';

describe('getAccessibleForegroundColor', () => {
  it.each([
    ['#6366f1', '#000000'],
    ['#22c55e', '#000000'],
    ['#005ea5', '#ffffff'],
    ['#111827', '#ffffff'],
    ['#fff', '#000000'],
  ])('chooses the higher-contrast foreground for %s', (background, expected) => {
    expect(getAccessibleForegroundColor(background)).toBe(expected);
  });

  it('uses the caller fallback for unsupported color syntax', () => {
    expect(getAccessibleForegroundColor('var(--tenant-brand)', '#123456')).toBe('#123456');
  });
});
