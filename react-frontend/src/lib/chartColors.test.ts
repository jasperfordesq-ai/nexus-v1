// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { CHART_COLORS, CHART_COLOR_MAP } from './chartColors';

describe('CHART_COLORS', () => {
  it('is a non-empty array', () => {
    expect(Array.isArray(CHART_COLORS)).toBe(true);
    expect(CHART_COLORS.length).toBeGreaterThan(0);
  });

  it('contains only valid hex color strings', () => {
    const hexPattern = /^#[0-9a-f]{6}$/i;
    for (const color of CHART_COLORS) {
      expect(color).toMatch(hexPattern);
    }
  });

  it('contains 8 colors', () => {
    expect(CHART_COLORS).toHaveLength(8);
  });

  it('has indigo as the first color', () => {
    expect(CHART_COLORS[0]).toBe('#6366f1');
  });
});

describe('CHART_COLOR_MAP', () => {
  it('contains all required semantic color keys', () => {
    const requiredKeys = ['primary', 'secondary', 'success', 'warning', 'danger', 'info'];
    for (const key of requiredKeys) {
      expect(CHART_COLOR_MAP).toHaveProperty(key);
    }
  });

  it('all values are valid hex strings', () => {
    const hexPattern = /^#[0-9a-f]{6}$/i;
    for (const value of Object.values(CHART_COLOR_MAP)) {
      expect(value).toMatch(hexPattern);
    }
  });

  it('primary color is indigo', () => {
    expect(CHART_COLOR_MAP.primary).toBe('#6366f1');
  });

  it('success color is emerald', () => {
    expect(CHART_COLOR_MAP.success).toBe('#10b981');
  });

  it('danger color is red', () => {
    expect(CHART_COLOR_MAP.danger).toBe('#ef4444');
  });
});
