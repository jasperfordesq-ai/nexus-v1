// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for map-styles
 */

import { describe, it, expect } from 'vitest';
import { DARK_MAP_STYLES } from './map-styles';

describe('map-styles', () => {
  it('exports DARK_MAP_STYLES as an array', () => {
    expect(Array.isArray(DARK_MAP_STYLES)).toBe(true);
  });

  it('has at least one style entry', () => {
    expect(DARK_MAP_STYLES.length).toBeGreaterThan(0);
  });

  it('each entry has stylers array', () => {
    for (const style of DARK_MAP_STYLES) {
      expect(Array.isArray(style.stylers)).toBe(true);
      expect(style.stylers.length).toBeGreaterThan(0);
    }
  });

  it('contains geometry and water styles', () => {
    const elementTypes = DARK_MAP_STYLES
      .filter((s) => !s.featureType)
      .map((s) => s.elementType);
    expect(elementTypes).toContain('geometry');

    const waterStyles = DARK_MAP_STYLES.filter((s) => s.featureType === 'water');
    expect(waterStyles.length).toBeGreaterThan(0);
  });
});
